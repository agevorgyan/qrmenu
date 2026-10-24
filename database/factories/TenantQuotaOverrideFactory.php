<?php

namespace Database\Factories;

use App\Models\TenantQuotaOverride;
use App\Models\User;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantQuotaOverride>
 */
class TenantQuotaOverrideFactory extends Factory
{
    protected $model = TenantQuotaOverride::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'quota_key' => fake()->randomElement(QuotaCatalog::all()),
            'quota_limit' => fake()->numberBetween(10, 1000),
            'reason' => 'Administrative pilot trial extension',
            'expires_at' => now()->addDays(30),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}
