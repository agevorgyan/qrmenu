<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidSubscriptionTransitionException;
use App\Models\MenuTemplate;
use App\Models\Subscription;
use App\Models\SubscriptionLifecycleEvent;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\Vendor;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionDomainAndStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        MenuTemplate::firstOrCreate(
            ['slug' => 'modern-minimal'],
            ['name' => 'Modern Minimal', 'is_active' => true]
        );

        $this->basicPlan = SubscriptionPlan::where('slug', 'basic')->firstOrFail();
        $this->proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $this->subscriptionService = app(SubscriptionService::class);
    }

    private function createTestVendor(string $name = 'Test Bistro'): Vendor
    {
        return Vendor::create([
            'name' => $name,
            'slug' => 'test-bistro-'.uniqid(),
            'type' => 'restaurant',
            'email' => 'vendor_'.uniqid().'@example.com',
            'phone' => '091'.rand(100000, 999999),
            'subscription_plan_id' => $this->proPlan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_valid_subscription_state_transitions(): void
    {
        $vendor = $this->createTestVendor();

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Trialing,
            'billing_interval' => 'monthly',
            'trial_starts_at' => Carbon::now(),
            'trial_ends_at' => Carbon::now()->addDays(14),
            'trial_duration_days' => 14,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addDays(14),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        // Trialing -> Active
        $subscription->transitionTo(SubscriptionStatus::Active, 'Payment confirmed');
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->allowsAccess());

        // Active -> CancelAtPeriodEnd
        $subscription->transitionTo(SubscriptionStatus::CancelAtPeriodEnd, 'Cancellation requested by merchant');
        $this->assertEquals(SubscriptionStatus::CancelAtPeriodEnd, $subscription->status);
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertTrue($subscription->allowsAccess());

        // CancelAtPeriodEnd -> Active (reactivate/resume)
        $subscription->transitionTo(SubscriptionStatus::Active, 'Merchant resumed subscription');
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertFalse($subscription->cancel_at_period_end);

        // Active -> PastDue
        $subscription->transitionTo(SubscriptionStatus::PastDue, 'Card charge failed');
        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->status);

        // PastDue -> Grace
        $subscription->transitionTo(SubscriptionStatus::Grace, 'Entered grace period');
        $this->assertEquals(SubscriptionStatus::Grace, $subscription->status);

        // Grace -> Expired
        $subscription->transitionTo(SubscriptionStatus::Expired, 'Grace period elapsed');
        $this->assertEquals(SubscriptionStatus::Expired, $subscription->status);
        $this->assertFalse($subscription->allowsAccess());

        // Re-subscribe: Expired -> Active (via new payment / re-subscribe)
        $subscription->transitionTo(SubscriptionStatus::Active, 'Re-subscribed with valid payment');
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);

        // Active -> Suspended
        $subscription->transitionTo(SubscriptionStatus::Suspended, 'Suspended by admin for policy review');
        $this->assertEquals(SubscriptionStatus::Suspended, $subscription->status);
        $this->assertFalse($subscription->allowsAccess());

        // Suspended -> Active
        $subscription->transitionTo(SubscriptionStatus::Active, 'Admin restored subscription');
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->allowsAccess());

        // Active -> Cancelled
        $subscription->transitionTo(SubscriptionStatus::Cancelled, 'Immediate cancellation');
        $this->assertEquals(SubscriptionStatus::Cancelled, $subscription->status);
    }

    public function test_invalid_state_transitions_throw_exception(): void
    {
        $vendor = $this->createTestVendor();

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Expired,
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::now()->subMonths(2),
            'current_period_end' => Carbon::now()->subMonth(),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        // Expired -> Trialing is illegal
        $this->expectException(InvalidSubscriptionTransitionException::class);
        $subscription->transitionTo(SubscriptionStatus::Trialing, 'Attempting illegal transition');
    }

    public function test_incomplete_to_grace_is_rejected(): void
    {
        $vendor = $this->createTestVendor();

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Incomplete,
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        $this->expectException(InvalidSubscriptionTransitionException::class);
        $subscription->transitionTo(SubscriptionStatus::Grace, 'Illegal transition');
    }

    public function test_trial_start_records_explicit_duration_and_is_idempotent(): void
    {
        $vendor = $this->createTestVendor();

        $subscription = $this->subscriptionService->startTrial($vendor, $this->proPlan, 21);

        $this->assertEquals(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertEquals(21, $subscription->trial_duration_days);
        $this->assertNotNull($subscription->trial_starts_at);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertEquals(
            Carbon::now()->addDays(21)->toDateString(),
            $subscription->trial_ends_at->toDateString()
        );

        $initialEnd = $subscription->trial_ends_at;

        // Calling startTrial again on already trialing subscription must NOT reset duration
        $refreshed = $this->subscriptionService->startTrial($vendor, $this->proPlan, 30);
        $this->assertEquals($initialEnd->toDateTimeString(), $refreshed->trial_ends_at->toDateTimeString());
        $this->assertEquals(21, $refreshed->trial_duration_days);
    }

    public function test_cancellation_at_period_end_preserves_access_until_period_end(): void
    {
        $vendor = $this->createTestVendor();

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::now()->subDays(10),
            'current_period_end' => Carbon::now()->addDays(20),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        $this->subscriptionService->cancelSubscription($subscription, false, 'Moving to another service');

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::CancelAtPeriodEnd, $subscription->status);
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertTrue($subscription->isCancellationRequested());
        $this->assertTrue($subscription->allowsAccess());

        // Fast forward past current_period_end
        Carbon::setTestNow(Carbon::now()->addDays(25));
        $this->assertFalse($subscription->allowsAccess());
        Carbon::setTestNow(); // reset
    }

    public function test_immediate_cancellation_terminates_access_immediately(): void
    {
        $vendor = $this->createTestVendor();

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::now()->subDays(10),
            'current_period_end' => Carbon::now()->addDays(20),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        $this->subscriptionService->cancelSubscription($subscription, true, 'Immediate termination requested');

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertFalse($subscription->allowsAccess());
        $this->assertEquals('cancelled', $vendor->fresh()->subscription_status);
        $this->assertFalse($vendor->fresh()->is_active);
    }

    public function test_idempotent_subscription_renewal(): void
    {
        $vendor = $this->createTestVendor();

        $initialEnd = Carbon::now()->addDays(15);
        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => $initialEnd,
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        // First renewal with payment reference
        $paymentRef = 'PAY-'.uniqid();
        $renewed = $this->subscriptionService->renewSubscription($subscription, $paymentRef);

        $expectedNewEnd = $initialEnd->copy()->addMonth();
        $this->assertEquals($expectedNewEnd->toDateString(), $renewed->current_period_end->toDateString());
        $this->assertEquals($paymentRef, $renewed->last_payment_reference);

        // Repeated renewal with identical payment reference must be a no-op (idempotent)
        $idempotentRenewed = $this->subscriptionService->renewSubscription($renewed, $paymentRef);
        $this->assertEquals($expectedNewEnd->toDateString(), $idempotentRenewed->current_period_end->toDateString());
    }

    public function test_plan_price_change_does_not_rewrite_historical_subscription_price(): void
    {
        $vendor = $this->createTestVendor();

        $priceModel = SubscriptionPlanPrice::where('subscription_plan_id', $this->proPlan->id)
            ->where('billing_interval', 'monthly')
            ->firstOrFail();

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'subscription_plan_price_id' => $priceModel->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'price' => $priceModel->price,
            'currency' => $priceModel->currency,
        ]);

        $originalPrice = (float) $subscription->price;

        // Admin updates the plan price
        $priceModel->update(['price' => $originalPrice + 5000]);
        $this->proPlan->update(['price' => $originalPrice + 5000]);

        // Existing subscription price snapshot remains untouched
        $subscription->refresh();
        $this->assertEquals($originalPrice, (float) $subscription->price);
        $this->assertEquals($originalPrice, (float) $subscription->effective_price);
    }

    public function test_subscription_lifecycle_events_are_recorded_on_transitions(): void
    {
        $vendor = $this->createTestVendor();

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Trialing,
            'billing_interval' => 'monthly',
            'trial_starts_at' => Carbon::now(),
            'trial_ends_at' => Carbon::now()->addDays(14),
            'trial_duration_days' => 14,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addDays(14),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        $subscription->transitionTo(SubscriptionStatus::Active, 'Upgrade after successful card charge', null, 'customer');

        $event = SubscriptionLifecycleEvent::where('subscription_id', $subscription->id)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertEquals($vendor->id, $event->vendor_id);
        $this->assertEquals('trialing', $event->from_status);
        $this->assertEquals('active', $event->to_status);
        $this->assertEquals('Upgrade after successful card charge', $event->reason);
        $this->assertEquals('customer', $event->actor_type);
    }

    public function test_tenant_isolation_composite_foreign_key_and_integrity_check(): void
    {
        $vendor1 = $this->createTestVendor('Vendor One');
        $vendor2 = $this->createTestVendor('Vendor Two');

        $subscription1 = Subscription::create([
            'vendor_id' => $vendor1->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        // Attempting to record an event for subscription 1 with vendor 2
        $this->expectException(InvalidArgumentException::class);

        SubscriptionLifecycleEvent::create([
            'vendor_id' => $vendor2->id, // Mismatched vendor!
            'subscription_id' => $subscription1->id,
            'event' => 'status_changed',
            'from_status' => 'active',
            'to_status' => 'suspended',
            'occurred_at' => Carbon::now(),
        ]);
    }

    public function test_reconcile_subscriptions_command_expires_overdue_trials_and_grace_periods(): void
    {
        $vendor = $this->createTestVendor();

        // Expired trial subscription
        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Trialing,
            'billing_interval' => 'monthly',
            'trial_starts_at' => Carbon::now()->subDays(20),
            'trial_ends_at' => Carbon::now()->subDays(6),
            'trial_duration_days' => 14,
            'current_period_start' => Carbon::now()->subDays(20),
            'current_period_end' => Carbon::now()->subDays(6),
            'price' => 19900.00,
            'currency' => 'AMD',
        ]);

        $exitCode = Artisan::call('subscriptions:reconcile');
        $this->assertEquals(0, $exitCode);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Expired, $subscription->status);

        $vendor->refresh();
        $this->assertEquals('expired', $vendor->subscription_status);
        $this->assertFalse($vendor->is_active);

        // Verify lifecycle event logged
        $this->assertDatabaseHas('subscription_lifecycle_events', [
            'vendor_id' => $vendor->id,
            'subscription_id' => $subscription->id,
            'from_status' => 'trialing',
            'to_status' => 'expired',
            'event' => 'expired',
        ]);
    }
}
