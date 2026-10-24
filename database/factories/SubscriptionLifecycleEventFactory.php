<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionLifecycleEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionLifecycleEvent>
 */
class SubscriptionLifecycleEventFactory extends Factory
{
    protected $model = SubscriptionLifecycleEvent::class;

    public function definition(): array
    {
        $subscription = Subscription::factory()->create();

        return [
            'vendor_id' => $subscription->vendor_id,
            'subscription_id' => $subscription->id,
            'event' => 'status_changed',
            'from_status' => 'trialing',
            'to_status' => 'active',
            'from_plan_id' => $subscription->subscription_plan_id,
            'to_plan_id' => $subscription->subscription_plan_id,
            'actor_id' => null,
            'actor_type' => 'system',
            'reason' => 'Automated transition or payment confirmation',
            'metadata' => [],
            'occurred_at' => Carbon::now(),
        ];
    }
}
