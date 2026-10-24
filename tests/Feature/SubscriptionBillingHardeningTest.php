<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBillingHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $businessPlan;

    protected SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->basicPlan = SubscriptionPlan::where('slug', 'basic')->firstOrFail();
        $this->proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $this->businessPlan = SubscriptionPlan::where('slug', 'business')->firstOrFail();

        $this->subscriptionService = app(SubscriptionService::class);
    }

    /**
     * Helper to create vendor and owner.
     */
    protected function createVendorWithOwner(array $vendorAttributes = []): array
    {
        $vendor = Vendor::create(array_merge([
            'name' => 'Armenian Grill House',
            'slug' => 'armenian-grill-'.uniqid(),
            'type' => 'restaurant',
            'email' => 'grill_'.uniqid().'@example.com',
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
     * 1. Test trial: initialization, authoritative subscription creation, access allowed.
     */
    public function test_trial_initialization_sets_authoritative_subscription_and_allows_access(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);

        $subscription = $this->subscriptionService->startTrial($vendor, $this->proPlan, 14);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals($this->proPlan->id, $subscription->subscription_plan_id);
        $this->assertEquals(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($subscription->isTrialing());
        $this->assertFalse($subscription->isExpired());
        $this->assertTrue($subscription->allowsAccess());
        $this->assertEquals(14, $subscription->daysLeft());

        // Check vendor model is synchronized with authoritative subscription
        $vendor->refresh();
        $this->assertEquals($this->proPlan->id, $vendor->subscription_plan_id);
        $this->assertEquals('trialing', $vendor->subscription_status);
        $this->assertTrue($vendor->isTrialing());
        $this->assertFalse($vendor->isExpired());
        $this->assertTrue($vendor->allowsAccess());

        // Access to admin menu builder allowed during trial
        $response = $this->actingAs($user)->get(route('admin.menu.index'));
        $response->assertStatus(200);
    }

    /**
     * 2. Test activation: successful payment transitions subscription from trialing to active.
     */
    public function test_activation_on_successful_payment_transitions_to_active(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->startTrial($vendor, $this->proPlan, 14);

        $paymentService = app(PaymentService::class);
        $renewal = $paymentService->initiateSubscriptionRenewal($vendor, $this->proPlan, 1, 'arca');

        $payment = $renewal['payment'];
        $attempt = $renewal['attempt'];

        $this->assertEquals('pending', $payment->status);
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);
        $this->assertEquals(SubscriptionStatus::Trialing, $subscription->fresh()->status);

        // Verify webhook callback from ArCa
        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-AUTH-999',
            'status' => 2, // Paid
            'amount' => ((int) round($attempt->amount)) * 100,
        ]);
        $response->assertStatus(200);

        $subscription->refresh();
        $payment->refresh();
        $attempt->refresh();
        $vendor->refresh();

        $this->assertEquals('completed', $payment->status);
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->allowsAccess());
        $this->assertEquals('active', $subscription->renewal_state);
        $this->assertNotNull($subscription->current_period_end);

        // Vendor synchronized
        $this->assertEquals('active', $vendor->subscription_status);
        $this->assertTrue($vendor->hasActiveSubscription());
    }

    /**
     * 3. Test renewal: successful renewal extends billing period from existing expiry.
     */
    public function test_renewal_extends_billing_period_properly(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->startTrial($vendor, $this->proPlan, 14);

        // Set initial active period ending in 15 days
        $initialEnd = now()->addDays(15);
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subDays(15),
            'current_period_end' => $initialEnd,
        ]);
        $this->subscriptionService->syncVendor($subscription);

        $paymentService = app(PaymentService::class);
        $renewal = $paymentService->initiateSubscriptionRenewal($vendor, $this->proPlan, 1, 'arca');
        $attempt = $renewal['attempt'];

        // Confirm payment via webhook
        $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-RENEW-100',
            'status' => 2,
            'amount' => ((int) round($attempt->amount)) * 100,
        ])->assertStatus(200);

        $subscription->refresh();
        $vendor->refresh();

        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        // Expiry should be extended beyond the initial 15 days
        $this->assertTrue($subscription->current_period_end->gt($initialEnd));
        $this->assertEquals($subscription->current_period_end->toDateTimeString(), $vendor->subscription_expires_at->toDateTimeString());
    }

    /**
     * 4. Test failed renewal: failed payment NEVER accidentally activates or extends subscription.
     */
    public function test_failed_renewal_never_extends_and_transitions_to_past_due_or_grace(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->startTrial($vendor, $this->proPlan, 14);

        $originalEnd = now()->subHours(2); // Period already expired
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => $originalEnd,
        ]);
        $this->subscriptionService->syncVendor($subscription);

        $paymentService = app(PaymentService::class);
        $renewal = $paymentService->initiateSubscriptionRenewal($vendor, $this->proPlan, 1, 'arca');
        $attempt = $renewal['attempt'];
        $payment = $renewal['payment'];

        // ArCa reports payment declined / failed
        $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-DECLINE-001',
            'status' => 3, // Failed status
            'amount' => ((int) round($attempt->amount)) * 100,
        ])->assertStatus(400);

        $subscription->refresh();
        $payment->refresh();
        $attempt->refresh();
        $vendor->refresh();

        // 1. Payment marked failed
        $this->assertEquals('failed', $payment->status);
        $this->assertEquals(PaymentStatus::Failed, $attempt->status);

        // 2. Subscription current_period_end MUST NOT have been extended!
        $this->assertEquals($originalEnd->toDateTimeString(), $subscription->current_period_end->toDateTimeString());

        // 3. Subscription status transitioned to grace or past_due, NOT active!
        $this->assertNotEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertEquals('failed', $subscription->renewal_state);
        $this->assertTrue(in_array($subscription->status, [SubscriptionStatus::Grace, SubscriptionStatus::PastDue], true));
    }

    /**
     * 5. Test duplicate webhook: idempotent processing does NOT extend subscription period multiple times.
     */
    public function test_duplicate_webhook_is_idempotent_and_does_not_extend_multiple_times(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->startTrial($vendor, $this->proPlan, 14);

        $paymentService = app(PaymentService::class);
        $renewal = $paymentService->initiateSubscriptionRenewal($vendor, $this->proPlan, 1, 'arca');
        $attempt = $renewal['attempt'];

        $payload = [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-TXN-IDEMPOTENT',
            'status' => 2,
            'amount' => ((int) round($attempt->amount)) * 100,
        ];

        // First delivery: succeeds and extends period
        $res1 = $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), $payload);
        $res1->assertStatus(200);

        $subscription->refresh();
        $firstPeriodEnd = $subscription->current_period_end;
        $this->assertNotNull($firstPeriodEnd);

        // Second delivery (duplicate webhook replay):
        $res2 = $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), $payload);
        $res2->assertStatus(200);

        $subscription->refresh();
        $secondPeriodEnd = $subscription->current_period_end;

        // Invariant: duplicate webhook must NOT extend period a second time!
        $this->assertEquals($firstPeriodEnd->toDateTimeString(), $secondPeriodEnd->toDateTimeString());
    }

    /**
     * 6. Test grace period: allows access while in grace, blocks access once grace expires.
     */
    public function test_grace_period_allows_access_until_grace_expires(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->getOrCreateForVendor($vendor);

        // Enter grace period with 3 days remaining
        $subscription->update([
            'status' => SubscriptionStatus::Grace,
            'current_period_end' => now()->subDay(), // period ended
            'grace_ends_at' => now()->addDays(3),
        ]);
        $this->subscriptionService->syncVendor($subscription);

        $this->assertTrue($subscription->isInGracePeriod());
        $this->assertTrue($subscription->allowsAccess());
        $this->assertFalse($subscription->isExpired());
        $this->assertTrue($vendor->allowsAccess());

        // Middleware allows access during grace period
        $response = $this->actingAs($user)->get(route('admin.menu.index'));
        $response->assertStatus(200);

        // Now expire the grace period
        $subscription->update([
            'grace_ends_at' => now()->subMinute(),
        ]);
        $this->subscriptionService->syncVendor($subscription);
        $vendor->refresh();
        $user->unsetRelation('vendor');

        $this->assertFalse($subscription->isInGracePeriod());
        $this->assertFalse($subscription->allowsAccess());
        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($vendor->allowsAccess());

        // Middleware now blocks access and redirects to subscription page
        $blockedResponse = $this->actingAs($user)->get(route('admin.menu.index'));
        $blockedResponse->assertRedirect(route('admin.subscription'));
    }

    /**
     * 7. Test cancellation: sets cancelled_at, retains access until period end, blocks after.
     */
    public function test_cancellation_retains_access_until_period_end_and_sets_cancelled_at(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->getOrCreateForVendor($vendor);

        $periodEnd = now()->addDays(10);
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subDays(20),
            'current_period_end' => $periodEnd,
        ]);
        $this->subscriptionService->syncVendor($subscription);

        // Cancel subscription (standard cancel at end of period)
        $this->subscriptionService->cancel($subscription, immediately: false);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::CancelAtPeriodEnd, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertFalse($subscription->auto_renew);
        $this->assertEquals('cancelled', $subscription->renewal_state);

        // Since 10 days remain in current period, access is still granted!
        $this->assertTrue($subscription->allowsAccess());
        $this->assertTrue($vendor->allowsAccess());

        $resAllowed = $this->actingAs($user)->get(route('admin.menu.index'));
        $resAllowed->assertStatus(200);

        // Once period passes, access is revoked
        $subscription->update([
            'current_period_end' => now()->subMinute(),
        ]);
        $this->subscriptionService->syncVendor($subscription);
        $vendor->refresh();
        $user->unsetRelation('vendor');

        $this->assertFalse($subscription->allowsAccess());
        $resBlocked = $this->actingAs($user)->get(route('admin.menu.index'));
        $resBlocked->assertRedirect(route('admin.subscription'));
    }

    /**
     * 8. Test expiration: completely revokes access.
     */
    public function test_expiration_revokes_access(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->getOrCreateForVendor($vendor);

        $this->subscriptionService->expire($subscription);

        $subscription->refresh();
        $vendor->refresh();

        $this->assertEquals(SubscriptionStatus::Expired, $subscription->status);
        $this->assertFalse($subscription->allowsAccess());
        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($vendor->allowsAccess());

        $response = $this->actingAs($user)->get(route('admin.menu.index'));
        $response->assertRedirect(route('admin.subscription'));
    }

    /**
     * 9. Test reactivation: expired or cancelled subscription is reactivated by new payment.
     */
    public function test_reactivation_from_expired_or_cancelled_status(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->getOrCreateForVendor($vendor);

        // Mark expired
        $this->subscriptionService->expire($subscription);
        $this->assertEquals(SubscriptionStatus::Expired, $subscription->fresh()->status);

        // Initiate renewal payment
        $paymentService = app(PaymentService::class);
        $renewal = $paymentService->initiateSubscriptionRenewal($vendor, $this->proPlan, 1, 'arca');
        $attempt = $renewal['attempt'];

        // Confirm payment
        $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-REACTIVATE-777',
            'status' => 2,
            'amount' => ((int) round($attempt->amount)) * 100,
        ])->assertStatus(200);

        $subscription->refresh();
        $vendor->refresh();

        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->allowsAccess());
        $this->assertNull($subscription->cancelled_at);
        $this->assertNull($subscription->grace_ends_at);
        $this->assertTrue($vendor->allowsAccess());

        $response = $this->actingAs($user)->get(route('admin.menu.index'));
        $response->assertStatus(200);
    }

    /**
     * 10. Test refund: refund webhook revokes subscription access and cancels subscription.
     */
    public function test_refund_revokes_access_and_cancels_subscription(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->getOrCreateForVendor($vendor);

        $paymentService = app(PaymentService::class);
        $renewal = $paymentService->initiateSubscriptionRenewal($vendor, $this->proPlan, 1, 'arca');
        $attempt = $renewal['attempt'];
        $payment = $renewal['payment'];

        // Confirm payment
        $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-REFUND-INIT',
            'status' => 2,
            'amount' => ((int) round($attempt->amount)) * 100,
        ])->assertStatus(200);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);

        // Gateway issues refund callback (via Stripe or gateway verification)
        // Simulate refund verification result
        $this->subscriptionService->handleRefund($subscription, $payment, $attempt);

        $subscription->refresh();
        $payment->refresh();
        $attempt->refresh();
        $vendor->refresh();

        $this->assertEquals('refunded', $payment->status);
        $this->assertEquals(PaymentStatus::Refunded, $attempt->status);
        $this->assertEquals(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertFalse($subscription->allowsAccess());
        $this->assertFalse($vendor->allowsAccess());
    }

    /**
     * 11. Test payment mismatch: underpayment or wrong currency is rejected and does not activate.
     */
    public function test_payment_mismatch_amount_or_currency_is_rejected_and_does_not_activate(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $subscription = $this->subscriptionService->getOrCreateForVendor($vendor);

        $paymentService = app(PaymentService::class);
        $renewal = $paymentService->initiateSubscriptionRenewal($vendor, $this->proPlan, 1, 'arca');
        $attempt = $renewal['attempt'];
        $payment = $renewal['payment'];

        // Attacker attempts to pay 100 AMD (10000 tiyins) instead of 19,900 AMD (1990000 tiyins)
        $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-CHEAT-001',
            'status' => 2, // Says paid!
            'amount' => 10000, // But only 100 AMD!
        ])->assertStatus(400);

        $payment->refresh();
        $attempt->refresh();
        $subscription->refresh();
        $vendor->refresh();

        // 1. Payment rejected and marked failed
        $this->assertEquals('failed', $payment->status);
        $this->assertEquals(PaymentStatus::Failed, $attempt->status);

        // 2. Subscription MUST NOT be active!
        $this->assertNotEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertEquals('failed', $subscription->renewal_state);
    }

    /**
     * 12. Test single authoritative model and subscription_plan_id as source of truth.
     */
    public function test_subscription_plan_id_is_source_of_truth_and_eliminates_duplicated_state(): void
    {
        [$vendor, $user] = $this->createVendorWithOwner([
            'subscription_plan_id' => $this->basicPlan->id,
        ]);
        $subscription = $this->subscriptionService->getOrCreateForVendor($vendor);

        // Basic plan does not have multi_location feature
        $this->assertEquals($this->basicPlan->id, $subscription->subscription_plan_id);
        $this->assertFalse($vendor->hasFeature('multi_location'));
        $this->assertEquals('basic', $vendor->subscription_plan);

        // Change plan authoritatively to Business plan (ID 3)
        $subscription->update([
            'subscription_plan_id' => $this->businessPlan->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $this->subscriptionService->syncVendor($subscription);

        $vendor->refresh();

        // Single source of truth: subscription_plan_id controls everything
        $this->assertEquals($this->businessPlan->id, $vendor->subscription_plan_id);
        $this->assertEquals('business', $vendor->subscription_plan);
        $this->assertTrue($vendor->hasFeature('multi_location'));
        $this->assertTrue($subscription->hasFeature('multi_location'));

        // If someone sets subscription_plan string slug on vendor model, it automatically resolves subscription_plan_id
        $vendor->subscription_plan = 'pro';
        $vendor->save();

        $this->assertEquals($this->proPlan->id, $vendor->subscription_plan_id);
        $this->assertEquals('pro', $vendor->subscription_plan);
    }
}
