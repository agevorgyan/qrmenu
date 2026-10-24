<?php

namespace Database\Factories;

use App\Models\TenantFeatureOverride;
use App\Models\Vendor;
use App\Security\FeatureCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantFeatureOverride>
 */
class TenantFeatureOverrideFactory extends Factory
{
    protected $model = TenantFeatureOverride::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'feature_key' => FeatureCatalog::AI_WAITER,
            'is_enabled' => true,
            'scope' => 'global',
            'reason' => 'Administrative beta access granted for customer',
            'expires_at' => Carbon::now()->addDays(30),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
            'reason' => 'Administratively disabled due to policy restriction',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->subDay(),
        ]);
    }
}
