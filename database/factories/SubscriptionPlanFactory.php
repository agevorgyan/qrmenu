<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Security\QuotaCatalog;
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
            'feature_codes' => ['menu.base', 'menu.translations', 'analytics.basic'],
            'quota_limits' => QuotaCatalog::getDefaultQuotasForPlan('basic'),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_custom' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'feature_codes' => [
                'menu.base',
                'menu.translations',
                'analytics.basic',
                'orders.online',
                'orders.delivery',
                'reservations.manage',
                'preorders.manage',
                'customers.crm',
                'ai.menu_builder',
                'ai.waiter',
                'branding.custom_domain',
            ],
            'quota_limits' => QuotaCatalog::getDefaultQuotasForPlan('pro'),
        ]);
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Business',
            'slug' => 'business-'.uniqid(),
            'feature_codes' => [
                'menu.base',
                'menu.translations',
                'analytics.basic',
                'orders.online',
                'orders.delivery',
                'reservations.manage',
                'preorders.manage',
                'customers.crm',
                'branches.multiple',
                'team.manage',
                'analytics.advanced',
                'ai.menu_builder',
                'ai.waiter',
                'branding.custom_domain',
                'api.access',
            ],
            'quota_limits' => QuotaCatalog::getDefaultQuotasForPlan('business'),
        ]);
    }
}
