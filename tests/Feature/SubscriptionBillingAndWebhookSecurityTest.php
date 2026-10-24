<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionBillingAndWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $businessPlan;

    protected SubscriptionService $subscriptionService;

    protected PaymentService $paymentService;

    protected PaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->basicPlan = SubscriptionPlan::where('slug', 'basic')->firstOrFail();
        $this->proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $this->businessPlan = SubscriptionPlan::where('slug', 'business')->firstOrFail();

        $this->subscriptionService = app(SubscriptionService::class);
        $this->paymentService = app(PaymentService::class);
        $this->reconciliationService = app(PaymentReconciliationService::class);
    }

    /**
     * Helper to create vendor and owner.
     */
    protected function createVendorWithOwner(array $vendorAttributes = []): array
    {
        $vendor = Vendor::create(array_merge([
            'name' => 'Armenian Kitchen',
            'slug' => 'armenian-kitchen-'.uniqid(),
            'type' => 'restaurant',
            'email' => 'kitchen_'.uniqid().'@example.com',
            'subscription_plan_id' => $this->proPlan->id,
            'subscription_status' => 'trialing',
            'is_active' => true,
        ], $vendorAttributes));

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Owner',
            'email' => $vendor->email,
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        return [$vendor, $user];
    }

    /**
     * Helper to create Stripe signature header.
     */
    protected function createStripeSignatureHeader(array $payload, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $raw = json_encode($payload);
        $signed = "{$t}.{$raw}";
        $v1 = hash_hmac('sha256', $signed, $secret);

        return "t={$t},v1={$v1}";
    }

    /**
     * 1. Test checkout initiation for monthly and yearly plans sets authoritative amounts and intervals.
     */
    public function test_checkout_initiation_monthly_and_yearly_plans_sets_authoritative_amounts_and_intervals(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner();

        // 1. Monthly plan checkout
        $monthlyCheckout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );

        $this->assertInstanceOf(SubscriptionPayment::class, $monthlyCheckout['payment']);
        $this->assertInstanceOf(PaymentAttempt::class, $monthlyCheckout['attempt']);
        $this->assertEquals((float) $this->proPlan->price, (float) $monthlyCheckout['payment']->amount);
        $this->assertEquals('pending', $monthlyCheckout['payment']->status);
        $this->assertEquals(PaymentStatus::Pending, $monthlyCheckout['attempt']->status);
        $this->assertEquals('monthly', $monthlyCheckout['attempt']->request_payload['billing_interval']);
        $this->assertEquals(1, $monthlyCheckout['attempt']->request_payload['months_count']);

        // 2. Yearly plan checkout (with 20% volume discount)
        $yearlyCheckout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'yearly',
            paymentMethod: 'stripe'
        );

        $expectedYearly = round(((float) $this->proPlan->price) * 12 * 0.80, 2);
        $this->assertEquals($expectedYearly, (float) $yearlyCheckout['payment']->amount);
        $this->assertEquals('yearly', $yearlyCheckout['attempt']->request_payload['billing_interval']);
        $this->assertEquals(12, $yearlyCheckout['attempt']->request_payload['months_count']);
    }

    /**
     * 2. Test plan upgrade and downgrade via checkout initiation.
     */
    public function test_plan_upgrade_and_downgrade_via_checkout_initiation(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->basicPlan->id,
        ]);
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);
        $this->assertEquals($this->basicPlan->id, $sub->subscription_plan_id);

        // Initiate upgrade to Business plan
        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->businessPlan,
            billingInterval: 'monthly',
            paymentMethod: 'arca'
        );

        $attempt = $checkout['attempt'];

        // Confirm payment via ArCa webhook
        $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-UPGRADE-100',
            'status' => 2,
            'amount' => ((int) round($attempt->amount)) * 100,
        ])->assertStatus(200);

        $sub->refresh();
        $vendor->refresh();

        // Single source of truth: Business plan is active!
        $this->assertEquals($this->businessPlan->id, $sub->subscription_plan_id);
        $this->assertEquals($this->businessPlan->id, $vendor->subscription_plan_id);
        $this->assertEquals(SubscriptionStatus::Active, $sub->status);
    }

    /**
     * 3. Test trust boundary: frontend redirect alone NEVER activates paid subscription without provider verification.
     */
    public function test_trust_boundary_client_redirect_alone_never_activates_subscription(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);
        $sub->update(['status' => SubscriptionStatus::Pending]);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        // Attacker accesses callback endpoint claiming success with fake session ID
        $response = $this->get(route('client.payment.callback', [
            'vendor_slug' => $vendor->slug,
            'reference' => $attempt->merchant_reference,
            'session_id' => 'mock_stripe_session',
            'status' => 'success',
        ]));

        $sub->refresh();
        $attempt->refresh();

        // Invariant: Status must remain Pending, NEVER activated by client parameters!
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);
        $this->assertEquals(SubscriptionStatus::Pending, $sub->status);
        $this->assertFalse($sub->isActive());
    }

    /**
     * 4. Test Stripe webhook with valid HMAC signature activates subscription.
     */
    public function test_stripe_webhook_valid_signature_activates_subscription(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_valid_session',
                    'payment_intent' => 'pi_test_valid_intent',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_total' => ((int) round($attempt->amount)) * 100,
                    'currency' => 'amd',
                    'metadata' => [
                        'merchant_reference' => $attempt->merchant_reference,
                    ],
                ],
            ],
        ];

        $signatureHeader = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123');

        $response = $this->withHeaders([
            'Stripe-Signature' => $signatureHeader,
        ])->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);

        $response->assertStatus(200);

        $attempt->refresh();
        $sub->refresh();
        $vendor->refresh();

        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals('pi_test_valid_intent', $attempt->provider_transaction_id);
        $this->assertEquals(SubscriptionStatus::Active, $sub->status);
        $this->assertTrue($sub->isActive());
        $this->assertTrue($vendor->hasActiveSubscription());
    }

    /**
     * 5. Test Stripe webhook with invalid signature is rejected with 400.
     */
    public function test_stripe_webhook_invalid_signature_rejected_with_400(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_bad_sig',
                    'payment_intent' => 'pi_test_bad_sig',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_total' => ((int) round($attempt->amount)) * 100,
                    'currency' => 'amd',
                ],
            ],
        ];

        // Tampered signature
        $response = $this->withHeaders([
            'Stripe-Signature' => 't='.time().',v1=bad_hash_signature_value',
        ])->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);

        $response->assertStatus(400);

        $attempt->refresh();
        $this->assertNotEquals(PaymentStatus::Paid, $attempt->status);
    }

    /**
     * 6. Test Stripe webhook missing signature when secret is configured is rejected.
     */
    public function test_stripe_webhook_missing_signature_when_secret_configured_rejected(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_no_sig',
                    'client_reference_id' => $checkout['attempt']->merchant_reference,
                    'amount_total' => 1990000,
                    'currency' => 'amd',
                ],
            ],
        ];

        $response = $this->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);
        $response->assertStatus(400);
    }

    /**
     * 7. Test Stripe webhook replay attack beyond 300s tolerance is rejected.
     */
    public function test_stripe_webhook_expired_timestamp_replay_rejected(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_replay',
                    'payment_intent' => 'pi_test_replay',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_total' => ((int) round($attempt->amount)) * 100,
                    'currency' => 'amd',
                ],
            ],
        ];

        // Expired timestamp: 400 seconds ago
        $expiredSig = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123', time() - 400);

        $response = $this->withHeaders([
            'Stripe-Signature' => $expiredSig,
        ])->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);

        $response->assertStatus(400);
    }

    /**
     * 8. Test duplicate webhook delivery is strictly idempotent and does not extend period twice.
     */
    public function test_duplicate_webhook_delivery_is_idempotent_and_does_not_extend_period_twice(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_idempotent',
                    'payment_intent' => 'pi_test_idempotent',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_total' => ((int) round($attempt->amount)) * 100,
                    'currency' => 'amd',
                ],
            ],
        ];

        $now = time();
        $sig = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123', $now);

        // First delivery: extends subscription
        $res1 = $this->withHeaders(['Stripe-Signature' => $sig])
            ->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);
        $res1->assertStatus(200);

        $sub->refresh();
        $firstExpiry = $sub->current_period_end;
        $this->assertNotNull($firstExpiry);

        // Second delivery (duplicate delivery): returns 200 without re-extending
        $res2 = $this->withHeaders(['Stripe-Signature' => $sig])
            ->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);
        $res2->assertStatus(200);

        $sub->refresh();
        $secondExpiry = $sub->current_period_end;

        // Invariant: duplicate webhook must NOT extend period a second time!
        $this->assertEquals($firstExpiry->toDateTimeString(), $secondExpiry->toDateTimeString());
    }

    /**
     * 9. Test webhook underpayment / amount mismatch is rejected and does not activate.
     */
    public function test_webhook_amount_mismatch_rejected_and_subscription_not_activated(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_mismatch',
                    'payment_intent' => 'pi_test_mismatch',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_total' => 500, // Only 5 AMD instead of 19900 AMD!
                    'currency' => 'amd',
                ],
            ],
        ];

        $sig = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123');

        $response = $this->withHeaders(['Stripe-Signature' => $sig])
            ->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);

        $response->assertStatus(400);

        $attempt->refresh();
        $sub->refresh();

        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertNotEquals(SubscriptionStatus::Active, $sub->status);
    }

    /**
     * 10. Test webhook currency mismatch is rejected.
     */
    public function test_webhook_currency_mismatch_rejected_and_subscription_not_activated(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_curr_mismatch',
                    'payment_intent' => 'pi_test_curr_mismatch',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_total' => ((int) round($attempt->amount)) * 100,
                    'currency' => 'usd', // Attacker sent USD instead of AMD!
                ],
            ],
        ];

        $sig = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123');

        $response = $this->withHeaders(['Stripe-Signature' => $sig])
            ->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);

        $response->assertStatus(400);

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertNotEquals(SubscriptionStatus::Active, $sub->fresh()->status);
    }

    /**
     * 11. Test webhook duplicate provider transaction ID across attempts is rejected.
     */
    public function test_webhook_duplicate_provider_transaction_across_attempts_rejected(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor1] = $this->createVendorWithOwner();
        [$vendor2] = $this->createVendorWithOwner();

        $checkout1 = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor1,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );

        $checkout2 = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor2,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );

        // Pre-existing paid attempt with provider transaction ID pi_replayed_100
        $checkout1['attempt']->update([
            'status' => PaymentStatus::Paid,
            'provider_transaction_id' => 'pi_replayed_100',
        ]);

        // Attacker attempts to reuse pi_replayed_100 on vendor2's attempt
        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_replay_attempt',
                    'payment_intent' => 'pi_replayed_100', // Already used!
                    'client_reference_id' => $checkout2['attempt']->merchant_reference,
                    'amount_total' => ((int) round($checkout2['attempt']->amount)) * 100,
                    'currency' => 'amd',
                ],
            ],
        ];

        $sig = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123');

        $response = $this->withHeaders(['Stripe-Signature' => $sig])
            ->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);

        $response->assertStatus(400);

        $checkout2['attempt']->refresh();
        $this->assertEquals(PaymentStatus::Failed, $checkout2['attempt']->status);
    }

    /**
     * 12. Test payment failure webhook transitions subscription to grace or past_due and never extends.
     */
    public function test_payment_failure_webhook_transitions_to_grace_or_past_due_never_extends(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);
        $originalEnd = now()->subHours(2);
        $sub->update([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => $originalEnd,
        ]);
        $this->subscriptionService->syncVendor($sub);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_card_declined',
                    'client_reference_id' => $attempt->merchant_reference,
                    'last_payment_error' => [
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ];

        $sig = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123');

        $this->withHeaders(['Stripe-Signature' => $sig])
            ->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload)
            ->assertStatus(400);

        $attempt->refresh();
        $sub->refresh();

        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        // Expiry date must NOT be extended!
        $this->assertEquals($originalEnd->toDateTimeString(), $sub->current_period_end->toDateTimeString());
        $this->assertTrue(in_array($sub->status, [SubscriptionStatus::Grace, SubscriptionStatus::PastDue], true));
        $this->assertEquals('failed', $sub->renewal_state);
    }

    /**
     * 13. Test refund webhook revokes subscription access and cancels subscription.
     */
    public function test_refund_webhook_revokes_access_and_cancels_subscription(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret_123']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];

        // Mark paid initially
        $attempt->update([
            'status' => PaymentStatus::Paid,
            'provider_transaction_id' => 'pi_to_be_refunded',
        ]);
        $sub->update(['status' => SubscriptionStatus::Active]);

        // Stripe sends charge.refunded
        $payload = [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_refunded_123',
                    'payment_intent' => 'pi_to_be_refunded',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_refunded' => ((int) round($attempt->amount)) * 100,
                    'currency' => 'amd',
                ],
            ],
        ];

        $sig = $this->createStripeSignatureHeader($payload, 'whsec_test_secret_123');

        $response = $this->withHeaders(['Stripe-Signature' => $sig])
            ->postJson(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);

        $response->assertStatus(200);

        $attempt->refresh();
        $sub->refresh();
        $vendor->refresh();

        $this->assertEquals(PaymentStatus::Refunded, $attempt->status);
        $this->assertEquals(SubscriptionStatus::Cancelled, $sub->status);
        $this->assertFalse($sub->allowsAccess());
        $this->assertFalse($vendor->allowsAccess());
    }

    /**
     * 14. Test tenant cancellation and resumption controls.
     */
    public function test_tenant_cancellation_and_resumption_controls(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);
        $sub->update([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
            'auto_renew' => true,
        ]);

        // 1. Tenant cancels at period end
        $cancelResponse = $this->actingAs($user)->post(route('admin.subscription.cancel'), [
            'immediately' => false,
            'reason' => 'Too expensive for our budget',
        ]);
        $cancelResponse->assertRedirect();

        $sub->refresh();
        $this->assertEquals(SubscriptionStatus::CancelAtPeriodEnd, $sub->status);
        $this->assertTrue($sub->cancel_at_period_end);
        $this->assertTrue($sub->allowsAccess()); // Retains access until period end

        // 2. Tenant resumes auto-renewal before period ends
        $resumeResponse = $this->actingAs($user)->post(route('admin.subscription.resume'));
        $resumeResponse->assertRedirect();

        $sub->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $sub->status);
        $this->assertFalse($sub->cancel_at_period_end);
        $this->assertTrue($sub->auto_renew);
    }

    /**
     * 15. Test reconciliation service resolves paid attempt when provider confirms.
     */
    public function test_reconciliation_service_resolves_paid_attempt_when_provider_confirms(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];
        $attempt->forceFill([
            'provider_transaction_id' => 'cs_test_recon_paid',
            'created_at' => now()->subMinutes(20),
        ])->save();

        config(['services.stripe.secret' => 'sk_test_mock_secret']);

        // Mock Stripe API returning checkout session as paid
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_test_recon_paid' => Http::response([
                'id' => 'cs_test_recon_paid',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_recon_paid_123',
                'amount_total' => ((int) round($attempt->amount)) * 100,
                'currency' => 'amd',
            ], 200),
        ]);

        $summary = $this->reconciliationService->reconcilePendingAttempts(olderThanMinutes: 15, dryRun: false);

        $this->assertEquals(1, $summary['resolved_paid']);
        $this->assertEquals(0, $summary['discrepancies']);

        $attempt->refresh();
        $sub->refresh();

        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals(SubscriptionStatus::Active, $sub->status);
    }

    /**
     * 16. Test reconciliation service resolves failed attempt when provider reports failure.
     */
    public function test_reconciliation_service_resolves_failed_attempt_when_provider_reports_failure(): void
    {
        config(['services.stripe.secret' => 'sk_test_mock_secret']);

        [$vendor, $user] = $this->createVendorWithOwner();

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];
        $attempt->forceFill([
            'provider_transaction_id' => 'cs_test_recon_expired',
            'created_at' => now()->subMinutes(20),
        ])->save();

        // Mock Stripe API returning session as expired
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_test_recon_expired' => Http::response([
                'id' => 'cs_test_recon_expired',
                'status' => 'expired',
                'payment_status' => 'unpaid',
            ], 200),
        ]);

        $summary = $this->reconciliationService->reconcilePendingAttempts(olderThanMinutes: 15, dryRun: false);

        $this->assertEquals(1, $summary['resolved_failed']);
        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
    }

    /**
     * 17. Test reconciliation service detects discrepancy and does NOT grant access.
     */
    public function test_reconciliation_service_detects_discrepancy_and_does_not_grant_access(): void
    {
        config(['services.stripe.secret' => 'sk_test_mock_secret']);

        [$vendor, $user] = $this->createVendorWithOwner();
        $sub = $this->subscriptionService->getOrCreateForVendor($vendor);
        $sub->update(['status' => SubscriptionStatus::PastDue]);

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];
        $attempt->forceFill([
            'provider_transaction_id' => 'cs_test_recon_discrepancy',
            'created_at' => now()->subMinutes(20),
        ])->save();

        // Provider reports paid, but with wrong amount!
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_test_recon_discrepancy' => Http::response([
                'id' => 'cs_test_recon_discrepancy',
                'payment_status' => 'paid',
                'amount_total' => 100, // Only 1 AMD instead of 19900!
                'currency' => 'amd',
            ], 200),
        ]);

        $summary = $this->reconciliationService->reconcilePendingAttempts(olderThanMinutes: 15, dryRun: false);

        $this->assertEquals(1, $summary['discrepancies']);
        $this->assertEquals(0, $summary['resolved_paid']);

        $attempt->refresh();
        $sub->refresh();

        // Critical safety invariant: Discrepancy MUST NOT grant access!
        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertNotEquals(SubscriptionStatus::Active, $sub->status);
        $this->assertTrue($attempt->response_payload['discrepancy']);
    }

    /**
     * 18. Test reconciliation service dry-run does not mutate database records.
     */
    public function test_reconciliation_service_dry_run_does_not_mutate_state(): void
    {
        config(['services.stripe.secret' => 'sk_test_mock_secret']);

        [$vendor, $user] = $this->createVendorWithOwner();

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $attempt = $checkout['attempt'];
        $attempt->forceFill([
            'provider_transaction_id' => 'cs_test_dry_run',
            'created_at' => now()->subMinutes(20),
        ])->save();

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_test_dry_run' => Http::response([
                'id' => 'cs_test_dry_run',
                'payment_status' => 'paid',
                'amount_total' => ((int) round($attempt->amount)) * 100,
                'currency' => 'amd',
            ], 200),
        ]);

        $summary = $this->reconciliationService->reconcilePendingAttempts(olderThanMinutes: 15, dryRun: true);

        $this->assertTrue($summary['dry_run']);
        $this->assertEquals(1, $summary['resolved_paid']);

        // Invariant: In dry run, attempt remains Pending in database
        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);
    }

    /**
     * 19. Test reconciliation artisan command execution.
     */
    public function test_reconciliation_artisan_command_execution(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner();

        $this->artisan('billing:reconcile', [
            '--minutes' => 0,
            '--dry-run' => true,
            '--full' => true,
        ])->assertSuccessful();

        $this->artisan('subscriptions:reconcile', [
            '--provider' => true,
            '--dry-run' => true,
        ])->assertSuccessful();
    }

    /**
     * 20. Test vendor invoice history and cross-tenant access authorization.
     */
    public function test_vendor_invoice_history_and_authorization(): void
    {
        [$vendor1, $owner1] = $this->createVendorWithOwner();
        [$vendor2, $owner2] = $this->createVendorWithOwner();

        $checkout = $this->paymentService->initiateSubscriptionCheckout(
            vendor: $vendor1,
            plan: $this->proPlan,
            billingInterval: 'monthly',
            paymentMethod: 'stripe'
        );
        $invoice = $checkout['payment'];

        // 1. Owner 1 can view own invoice
        $responseAllowed = $this->actingAs($owner1)->getJson(route('admin.subscription.invoices.show', $invoice));
        $responseAllowed->assertStatus(200);
        $responseAllowed->assertJsonFragment([
            'invoice_number' => $invoice->invoice_number,
        ]);

        // 2. Owner 2 is blocked from viewing Owner 1's invoice
        $responseBlocked = $this->actingAs($owner2)->get(route('admin.subscription.invoices.show', $invoice));
        $responseBlocked->assertStatus(403);
    }
}
