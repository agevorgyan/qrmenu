<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\Enums\LoyaltyTierLevel;
use App\Domains\Loyalty\Events\TierUpgraded;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Domains\Loyalty\Models\LoyaltyTierHistory;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class TierService
{
    /**
     * Ensure default tiers exist for a loyalty program.
     */
    public function ensureDefaultTiers(Vendor $vendor, LoyaltyProgram $program): void
    {
        $existing = LoyaltyTier::where('vendor_id', $vendor->id)->count();
        if ($existing > 0) {
            return;
        }

        $defaultTiers = [
            [
                'level' => LoyaltyTierLevel::Bronze->value,
                'name' => 'Bronze',
                'min_points' => 0,
                'min_spend' => 0.00,
                'points_multiplier' => 1.00,
                'cashback_percent' => 1.00,
                'order_discount_percent' => 0.00,
                'color_hex' => '#cd7f32',
                'icon_name' => 'shield',
                'perks_description' => 'Entry level membership. Earn standard points on every purchase.',
            ],
            [
                'level' => LoyaltyTierLevel::Silver->value,
                'name' => 'Silver',
                'min_points' => 500,
                'min_spend' => 25000.00,
                'points_multiplier' => 1.25,
                'cashback_percent' => 2.00,
                'order_discount_percent' => 3.00,
                'color_hex' => '#94a3b8',
                'icon_name' => 'medal',
                'perks_description' => '1.25x Points multiplier, 2% cashback, and exclusive seasonal promotions.',
            ],
            [
                'level' => LoyaltyTierLevel::Gold->value,
                'name' => 'Gold',
                'min_points' => 1500,
                'min_spend' => 75000.00,
                'points_multiplier' => 1.50,
                'cashback_percent' => 3.00,
                'order_discount_percent' => 5.00,
                'color_hex' => '#eab308',
                'icon_name' => 'crown',
                'perks_description' => '1.5x Points multiplier, 3% cashback, 5% order discount, and birthday gifts.',
            ],
            [
                'level' => LoyaltyTierLevel::Platinum->value,
                'name' => 'Platinum',
                'min_points' => 4000,
                'min_spend' => 200000.00,
                'points_multiplier' => 2.00,
                'cashback_percent' => 5.00,
                'order_discount_percent' => 10.00,
                'color_hex' => '#6366f1',
                'icon_name' => 'gem',
                'perks_description' => 'VIP status: 2x Points, 5% cashback, 10% discount, and priority seating reservations.',
            ],
        ];

        foreach ($defaultTiers as $tierData) {
            LoyaltyTier::create(array_merge($tierData, [
                'vendor_id' => $vendor->id,
                'program_id' => $program->id,
            ]));
        }
    }

    /**
     * Evaluate and upgrade/downgrade customer tier based on points or total spend.
     */
    public function evaluateCustomerTier(Customer $customer): ?LoyaltyTier
    {
        $currentPoints = (int) $customer->loyalty_points;
        $totalSpent = (float) $customer->total_spent;

        $qualifyingTier = LoyaltyTier::where('vendor_id', $customer->vendor_id)
            ->where(function ($q) use ($currentPoints, $totalSpent) {
                $q->where('min_points', '<=', $currentPoints)
                    ->orWhere('min_spend', '<=', $totalSpent);
            })
            ->orderBy('level', 'desc')
            ->first();

        if (! $qualifyingTier) {
            return null;
        }

        $currentTier = LoyaltyTier::where('vendor_id', $customer->vendor_id)
            ->where('name', $customer->loyalty_tier)
            ->first();

        // If tier upgraded
        if (! $currentTier || $qualifyingTier->level > $currentTier->level) {
            return DB::transaction(function () use ($customer, $qualifyingTier, $currentTier) {
                $customer->update(['loyalty_tier' => $qualifyingTier->name]);

                LoyaltyTierHistory::create([
                    'vendor_id' => $customer->vendor_id,
                    'customer_id' => $customer->id,
                    'tier_id' => $qualifyingTier->id,
                    'previous_tier_id' => $currentTier?->id,
                    'reason' => 'points_threshold',
                ]);

                event(new TierUpgraded($customer, $qualifyingTier, $currentTier));

                return $qualifyingTier;
            });
        }

        return $currentTier;
    }

    /**
     * Get tier benefits by customer or tier name.
     */
    public function getTierBenefits(int $vendorId, string $tierName): array
    {
        $tier = LoyaltyTier::where('vendor_id', $vendorId)
            ->where('name', $tierName)
            ->first();

        if (! $tier) {
            return [
                'name' => 'Bronze',
                'level' => 1,
                'points_multiplier' => 1.0,
                'cashback_percent' => 0.0,
                'order_discount_percent' => 0.0,
            ];
        }

        return [
            'name' => $tier->name,
            'level' => $tier->level,
            'points_multiplier' => (float) $tier->points_multiplier,
            'cashback_percent' => (float) $tier->cashback_percent,
            'order_discount_percent' => (float) $tier->order_discount_percent,
            'color' => $tier->color_hex,
            'perks' => $tier->perks_description,
        ];
    }
}
