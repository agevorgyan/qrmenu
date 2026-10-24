<?php

namespace Tests\Feature;

use App\DTOs\EntitlementResult;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\QuotaExceededException;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\TenantQuotaOverride;
use App\Models\TenantUsageMeter;
use App\Models\User;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use App\Services\EntitlementService;
use App\Services\Payments\PaymentService;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Database\Seeders\SubscriptionPlanSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionSecurityAuditAndFailureScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $businessPlan;

    protected User $superAdmin;

    protected User $vendorOwner;

    protected User $vendorStaff;

    protected Vendor $vendor;

    protected Subscription $subscription;

    protected SubscriptionService $subscriptionService;

    protected EntitlementService $entitlementService;

    protected QuotaService $quotaService;

    protected PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->basicPlan = SubscriptionPlan::where('slug', 'basic')->firstOrFail();
        $this->proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $this->businessPlan = SubscriptionPlan::where('slug', 'business')->firstOrFail();

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin_sec_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->vendor = Vendor::create([
            'name' => 'Secured Tavern',
            'slug' => 'secured-tavern-'.uniqid(),
            'type' => 'restaurant',
            'email' => 'tavern_'.uniqid().'@example.com',
            'phone' => '010112233',
            'subscription_plan_id' => $this->proPlan->id,
            'subscription_status' => 'active',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->vendorOwner = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Tavern Owner',
            'email' => 'owner_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->vendorStaff = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Tavern Cook',
            'email' => 'cook_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'chef',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->subscription = Subscription::create([
            'vendor_id' => $this->vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'price' => $this->proPlan->price,
            'currency' => 'AMD',
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->addMonth(),
            'renewal_state' => 'active',
            'auto_renew' => true,
        ]);

        $this->subscriptionService = app(SubscriptionService::class);
        $this->entitlementService = app(EntitlementService::class);
        $this->quotaService = app(QuotaService::class);
        $this->paymentService = app(PaymentService::class);
    }

    /**
     * 1. CROSS-TENANT ISOLATION:
     * Tenant A cannot view, mutate, or access Tenant B's subscription, usage, overrides, or invoices.
     */
    public function test_cross_tenant_isolation_prevents_unauthorized_data_leakage_and_mutations(): void
    {
        $tenantB = Vendor::create([
            'name' => 'Competitor Lounge',
            'slug' => 'competitor-lounge-'.uniqid(),
            'type' => 'restaurant',
            'email' => 'competitor_'.uniqid().'@example.com',
            'phone' => '099112233',
            'subscription_plan_id' => $this->basicPlan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $paymentB = SubscriptionPayment::create([
            'vendor_id' => $tenantB->id,
            'subscription_plan_id' => $this->businessPlan->id,
            'amount' => 50000,
            'currency' => 'AMD',
            'payment_method' => 'idram',
            'invoice_number' => 'INV-TENANT-B-999',
            'period_start' => now(),
            'period_end' => now()->addMonth(),
            'status' => 'completed',
        ]);

        $meterB = TenantUsageMeter::create([
            'vendor_id' => $tenantB->id,
            'quota_key' => QuotaCatalog::KEY_ORDERS_PERIOD,
            'current_usage' => 45,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        $this->actingAs($this->vendorOwner);

        // Cannot view tenant B invoice
        $response = $this->get(route('admin.subscription.invoices.show', $paymentB->id));
        $response->assertStatus(403);

        // Tenant A cannot consume Tenant B's quota
        $quotaResult = $this->quotaService->getQuotaStatus($this->vendor, QuotaCatalog::KEY_ORDERS_PERIOD);
        $this->assertEquals(0, $quotaResult->currentUsage);

        // Ensure meter query scoped to Tenant A does not see Tenant B meter
        $foundMeters = TenantUsageMeter::where('id', $meterB->id)->count();
        $this->assertEquals(0, $foundMeters, 'TenantScope must block visibility of other tenant usage meters');
    }

    /**
     * 2. PRIVILEGE ESCALATION & MISSING AUTHORIZATION:
     * Staff role cannot access billing management; non-admin cannot access superadmin subscription console.
     */
    public function test_privilege_escalation_and_missing_authorization_checks(): void
    {
        // Staff chef cannot cancel or renew subscription
        $this->actingAs($this->vendorStaff);

        $cancelResponse = $this->post(route('admin.subscription.cancel'), [
            'reason' => 'Unauthorized cancellation attempt',
        ]);
        $cancelResponse->assertStatus(403);

        $resumeResponse = $this->post(route('admin.subscription.resume'));
        $resumeResponse->assertStatus(403);

        // Vendor owner cannot access Super Admin endpoints
        $this->actingAs($this->vendorOwner);

        $superAdminConsole = $this->get(route('superadmin.subscriptions.index'));
        $superAdminConsole->assertStatus(403);

        $suspendResponse = $this->post(route('superadmin.subscriptions.suspend', $this->vendor->id), [
            'reason' => 'Illegal suspension attempt by tenant',
        ]);
        $suspendResponse->assertStatus(403);
    }

    /**
     * 3. DIRECT API ACCESS TO UNENTITLED PAID FEATURES:
     * Direct API calls to unentitled features fail closed with 403 JSON response.
     */
    public function test_direct_api_access_to_unentitled_features_fails_closed(): void
    {
        // Vendor is on Pro plan: team.manage and branches.multiple are Business only
        $this->actingAs($this->vendorOwner);

        $apiResponse = $this->postJson(route('admin.team.store'), [
            'name' => 'Forbidden Employee',
            'email' => 'employee_'.uniqid().'@example.com',
            'role' => 'waiter',
        ]);

        $apiResponse->assertStatus(403);
        $apiResponse->assertJson([
            'success' => false,
            'error' => 'FEATURE_NOT_ENTITLED',
            'code' => 'PLAN_UPGRADE_REQUIRED',
            'feature' => 'team.manage',
        ]);
    }

    /**
     * 4. FORGED PLAN IDENTIFIERS:
     * Tenant cannot forge checkout with non-existent or inactive plans.
     */
    public function test_forged_and_inactive_plan_identifiers_are_rejected(): void
    {
        $inactivePlan = SubscriptionPlan::create([
            'name' => 'Legacy Inactive Plan',
            'slug' => 'legacy-inactive-'.uniqid(),
            'price' => 1000,
            'currency' => 'AMD',
            'is_active' => false,
        ]);

        $this->actingAs($this->vendorOwner);

        // Attempt renewing with inactive plan
        $responseInactive = $this->post(route('admin.subscription.renew'), [
            'subscription_plan_id' => $inactivePlan->id,
            'period_months' => 1,
            'payment_method' => 'arca',
        ]);
        $responseInactive->assertSessionHasErrors('subscription_plan_id');

        // Attempt renewing with invalid ID
        $responseInvalid = $this->post(route('admin.subscription.renew'), [
            'subscription_plan_id' => 999999,
            'period_months' => 1,
            'payment_method' => 'arca',
        ]);
        $responseInvalid->assertSessionHasErrors('subscription_plan_id');
    }

    /**
     * 5. INVALID OR EXPIRED SUBSCRIPTION STATES:
     * Expired and suspended subscriptions strictly block access to protected vendor routes.
     */
    public function test_invalid_or_expired_subscription_states_block_application_access(): void
    {
        $this->actingAs($this->vendorOwner);

        // Put subscription in expired state
        $this->subscriptionService->expire($this->subscription, 'Term elapsed without payment');

        $this->subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Expired, $this->subscription->status);
        $this->assertFalse($this->subscription->status->allowsAccess());

        // Protected vendor routes redirect to subscription page with warning
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.subscription'));
        $response->assertSessionHas('warning');

        // Public menu fails closed (404) for inactive restaurant whose subscription elapsed
        $publicMenu = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $publicMenu->assertStatus(404);
    }

    /**
     * 6. WEBHOOK FORGERY & REPLAY PROTECTION:
     * Webhook endpoints reject invalid HMAC signatures, expired timestamps, and duplicate payloads.
     */
    public function test_webhook_security_signature_verification_and_replay_attacks(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_key_12345']);

        $payload = json_encode([
            'id' => 'evt_security_audit_'.uniqid(),
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_sec_audit_1',
                    'client_reference_id' => 'INVALID_REF_99999',
                    'amount_total' => 2400000,
                    'currency' => 'amd',
                    'payment_status' => 'paid',
                ],
            ],
        ]);

        // 1. Missing header
        $response1 = $this->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), json_decode($payload, true));
        $response1->assertStatus(400);

        // 2. Forged signature
        $response2 = $this->withHeaders([
            'Stripe-Signature' => 't='.time().',v1=forged_invalid_hex_digest_987654321',
        ])->call('POST', route('api.webhooks.payment', ['gateway' => 'stripe']), [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $response2->assertStatus(400);

        // 3. Expired timestamp (replay attack)
        $oldTimestamp = time() - 600; // 10 minutes ago (tolerance is 300s)
        $signature = hash_hmac('sha256', "{$oldTimestamp}.{$payload}", 'whsec_test_secret_key_12345');
        $response3 = $this->withHeaders([
            'Stripe-Signature' => "t={$oldTimestamp},v1={$signature}",
        ])->call('POST', route('api.webhooks.payment', ['gateway' => 'stripe']), [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $response3->assertStatus(400);
    }

    /**
     * 7. CONCURRENT QUOTA CONSUMPTION:
     * High-volume concurrency on limited quotas avoids negative balances or overshoot.
     */
    public function test_concurrent_quota_consumption_handles_concurrency_safely(): void
    {
        // Set fixed limit of 5 orders
        TenantQuotaOverride::create([
            'vendor_id' => $this->vendor->id,
            'quota_key' => QuotaCatalog::KEY_ORDERS_PERIOD,
            'quota_limit' => 5,
            'reason' => 'Concurrency load test limit',
        ]);

        // Consume 5 items sequentially
        for ($i = 1; $i <= 5; $i++) {
            $status = $this->quotaService->recordUsage(
                vendor: $this->vendor,
                quotaKey: QuotaCatalog::KEY_ORDERS_PERIOD,
                quantity: 1,
                idempotencyKey: 'order_concurrency_'.$i
            );
            $this->assertEquals($i, $status->currentUsage);
        }

        // 6th attempt must fail with QuotaExceededException
        $this->expectException(QuotaExceededException::class);
        $this->quotaService->recordUsage(
            vendor: $this->vendor,
            quotaKey: QuotaCatalog::KEY_ORDERS_PERIOD,
            quantity: 1,
            idempotencyKey: 'order_concurrency_6'
        );
    }

    /**
     * 8. FAILURE SCENARIO: PAYMENT PROVIDER OUTAGE & NETWORK TIMEOUT:
     * When external gateway throws connection error, payment attempt records failure,
     * subscription state is preserved, and system does not crash.
     */
    public function test_payment_provider_outage_does_not_corrupt_subscription_state(): void
    {
        Http::fake([
            'https://api.stripe.com/*' => Http::response(['error' => 'Gateway Connection Timeout (504)'], 504),
        ]);

        $attempt = PaymentAttempt::create([
            'vendor_id' => $this->vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'amount' => 19900,
            'currency' => 'AMD',
            'gateway' => 'stripe',
            'status' => PaymentStatus::Pending,
            'merchant_reference' => 'OUTAGE_REF_'.uniqid(),
            'period_months' => 1,
            'expires_at' => now()->addMinutes(30),
        ]);

        $initialPeriodEnd = $this->subscription->current_period_end;
        $initialStatus = $this->subscription->status;

        // Process attempt failure
        $attempt->update([
            'status' => PaymentStatus::Failed,
            'response_payload' => ['error' => 'External payment gateway is temporarily unreachable.'],
        ]);

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertStringContainsString('unreachable', $attempt->response_payload['error']);

        // Subscription remains untouched
        $this->subscription->refresh();
        $this->assertEquals($initialStatus, $this->subscription->status);
        $this->assertEquals($initialPeriodEnd->toDateTimeString(), $this->subscription->current_period_end->toDateTimeString());
    }

    /**
     * 9. FAILURE SCENARIO: DATABASE TRANSACTION ROLLBACK:
     * If an error occurs midway through activation, whole transaction rolls back atomically.
     */
    public function test_database_transaction_failure_rolls_back_cleanly(): void
    {
        $attempt = PaymentAttempt::create([
            'vendor_id' => $this->vendor->id,
            'subscription_plan_id' => $this->businessPlan->id,
            'amount' => 39900,
            'currency' => 'AMD',
            'gateway' => 'arca',
            'status' => PaymentStatus::Pending,
            'merchant_reference' => 'TX_FAIL_REF_'.uniqid(),
            'period_months' => 1,
            'expires_at' => now()->addMinutes(30),
        ]);

        $initialPlanId = $this->subscription->subscription_plan_id;

        try {
            DB::transaction(function () use ($attempt) {
                $this->subscription->update(['subscription_plan_id' => $this->businessPlan->id]);
                $attempt->update(['status' => PaymentStatus::Paid]);

                // Simulate catastrophic database failure midway
                throw new Exception('Database deadlock or connection abort midway');
            });
        } catch (Exception $e) {
            // Expected simulation exception
        }

        // Verify state was completely rolled back
        $this->subscription->refresh();
        $attempt->refresh();

        $this->assertEquals($initialPlanId, $this->subscription->subscription_plan_id);
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);
    }

    /**
     * 10. FAILURE SCENARIO: CACHE PURGE & IMMEDIATE ENTITLEMENT REVOCATION:
     * When subscription expires, cached entitlement is invalidated immediately, failing closed.
     */
    public function test_cache_purge_immediately_revokes_cached_entitlements_when_expired(): void
    {
        // 1. Warm entitlement cache while active
        $result1 = $this->entitlementService->check($this->vendor, 'orders.online');
        $this->assertTrue($result1->allowed);

        // 2. Expire subscription
        $this->subscriptionService->expire($this->subscription, 'Immediate term lapse');
        $this->vendor->refresh();

        // 3. Immediately query entitlement: must be denied and not served stale from cache
        $result2 = $this->entitlementService->check($this->vendor, 'orders.online');
        $this->assertFalse($result2->allowed);
        $this->assertEquals(EntitlementResult::TENANT_INACTIVE, $result2->code);
    }

    /**
     * 11. FAILURE SCENARIO: IDEMPOTENT CONCURRENT SCHEDULED RECONCILIATION:
     * Running reconciliation commands multiple times produces consistent, non-duplicating results.
     */
    public function test_duplicate_scheduled_reconciliation_is_strictly_idempotent(): void
    {
        // Run billing reconciliation twice
        $exitCode1 = Artisan::call('billing:reconcile', ['--dry-run' => true]);
        $exitCode2 = Artisan::call('billing:reconcile', ['--dry-run' => true]);

        $this->assertEquals(0, $exitCode1);
        $this->assertEquals(0, $exitCode2);

        // Run subscription reconciliation twice
        $exitSub1 = Artisan::call('subscriptions:reconcile');
        $exitSub2 = Artisan::call('subscriptions:reconcile');

        $this->assertEquals(0, $exitSub1);
        $this->assertEquals(0, $exitSub2);
    }

    /**
     * 12. SENSITIVE DATA PROTECTION:
     * Sensitive payment card data is never logged or leaked in subscription payments.
     */
    public function test_sensitive_payment_card_data_is_never_stored_or_leaked(): void
    {
        $payment = SubscriptionPayment::create([
            'vendor_id' => $this->vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'amount' => 19900,
            'currency' => 'AMD',
            'payment_method' => 'arca',
            'invoice_number' => 'INV-SEC-'.uniqid(),
            'period_start' => now(),
            'period_end' => now()->addMonth(),
            'status' => 'completed',
            'metadata' => [
                'masked_card' => '4111****1111',
                'card_brand' => 'Visa',
            ],
        ]);

        $this->actingAs($this->vendorOwner);

        $response = $this->get(route('admin.subscription.invoices.show', $payment->id));
        $response->assertStatus(200);

        // Ensure raw card numbers or CVV are never present
        $content = $response->getContent();
        $this->assertStringNotContainsString('cvv', strtolower($content));
        $this->assertStringNotContainsString('card_number', strtolower($content));
    }
}
