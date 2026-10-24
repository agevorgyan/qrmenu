<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlanPrice>
 */
class SubscriptionPlanPriceFactory extends Factory
{
    protected $model = SubscriptionPlanPrice::class;

    public function definition(): array
    {
        return [
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'billing_interval' => 'monthly',
            'currency' => 'AMD',
            'price' => fake()->randomElement([9900, 19900, 39900]),
            'is_active' => true,
            'description' => fake()->sentence(),
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_interval' => 'yearly',
            'price' => isset($attributes['price']) ? round($attributes['price'] * 12 * 0.8, 2) : 190000,
            'description' => 'Yearly billing with 20% discount',
        ]);
    }
}
