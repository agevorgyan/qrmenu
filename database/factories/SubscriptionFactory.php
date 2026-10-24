<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $plan = SubscriptionPlan::factory();

        return [
            'vendor_id' => Vendor::factory(),
            'subscription_plan_id' => $plan,
            'subscription_plan_price_id' => null,
            'price' => 19900.00,
            'currency' => 'AMD',
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'trial_duration_days' => 14,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'grace_ends_at' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => false,
            'cancellation_reason' => null,
            'renewal_state' => null,
            'auto_renew' => true,
            'last_payment_reference' => null,
            'metadata' => [],
        ];
    }

    public function trialing(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Trialing,
            'trial_starts_at' => Carbon::now(),
            'trial_ends_at' => Carbon::now()->addDays($days),
            'trial_duration_days' => $days,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addDays($days),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Active,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'cancel_at_period_end' => false,
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::PastDue,
            'current_period_end' => Carbon::now()->subMinute(),
        ]);
    }

    public function grace(int $days = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Grace,
            'current_period_end' => Carbon::now()->subMinute(),
            'grace_ends_at' => Carbon::now()->addDays($days),
        ]);
    }

    public function cancelAtPeriodEnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::CancelAtPeriodEnd,
            'cancel_at_period_end' => true,
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => Carbon::now()->addDays(15),
            'cancellation_reason' => 'User requested cancellation at period end',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => Carbon::now(),
            'current_period_end' => Carbon::now(),
            'cancel_at_period_end' => false,
            'cancellation_reason' => 'Immediate cancellation',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Expired,
            'current_period_end' => Carbon::now()->subDay(),
            'grace_ends_at' => Carbon::now()->subMinute(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Suspended,
        ]);
    }
}
