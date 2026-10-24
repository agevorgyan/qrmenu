<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.uniqid(),
            'price' => fake()->randomElement([9900, 19900, 39900]),
            'currency' => 'AMD',
            'billing_interval' => 'monthly',
            'duration_days' => 30,
            'trial_days' => 14,
            'features' => ['QR Menu', 'Product Catalog', 'Multilingual'],
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_custom' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
