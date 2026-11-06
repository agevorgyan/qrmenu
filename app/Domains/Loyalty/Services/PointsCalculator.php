<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\LoyaltyCalculationResult;
use App\Domains\Loyalty\Models\LoyaltyEarningRule;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;

class PointsCalculator
{
    /**
     * Calculate loyalty points, cashback, and stamps earned for an order.
     */
    public function calculateForOrder(Order $order, LoyaltyProgram $program, ?Customer $customer = null): LoyaltyCalculationResult
    {
        $eligibleSpend = $this->getEligibleOrderSpend($order, $program);

        if ($eligibleSpend <= 0) {
            return new LoyaltyCalculationResult(
                pointsEarned: 0,
                cashbackEarned: 0.0,
                stampsEarned: 0,
                multiplier: 1.0,
                appliedRules: []
            );
        }

        // 1. Resolve Tier Multiplier & Benefits
        $tierMultiplier = 1.0;
        $tierCashbackPercent = 0.0;
        if ($customer) {
            $tier = LoyaltyTier::where('vendor_id', $order->vendor_id)
                ->where('name', $customer->loyalty_tier)
                ->first();

            if ($tier) {
                $tierMultiplier = (float) $tier->points_multiplier;
                $tierCashbackPercent = (float) $tier->cashback_percent;
            }
        }

        $appliedRules = [];
        $basePoints = 0;
        $cashbackEarned = 0.0;

        // 2. Base Points from Program Default Ratio
        if ($program->points_enabled) {
            $ratio = (float) $program->default_points_ratio;
            $basePoints = (int) floor($eligibleSpend * $ratio);
            $appliedRules[] = [
                'rule' => 'default_points_ratio',
                'points' => $basePoints,
                'rate' => $ratio,
            ];
        }

        // 2b. Base Cashback from Program Settings
        $defaultCashbackPercent = (float) ($program->settings['default_cashback_percent'] ?? 0.0);
        if ($program->cashback_enabled && $defaultCashbackPercent > 0) {
            $defaultCashback = round(($eligibleSpend * $defaultCashbackPercent) / 100, 2);
            $cashbackEarned += $defaultCashback;
            $appliedRules[] = [
                'rule' => 'default_cashback_percent',
                'amount' => $defaultCashback,
                'percent' => $defaultCashbackPercent,
            ];
        }

        // 3. Evaluate Configured Earning Rules (Priority Descending)
        $rules = LoyaltyEarningRule::where('vendor_id', $order->vendor_id)
            ->where('program_id', $program->id)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matchesRuleConditions($order, $rule, $eligibleSpend)) {
                continue;
            }

            if ($rule->reward_type === 'cashback' && $program->cashback_enabled) {
                $ruleCashback = match ($rule->type) {
                    'percentage' => round(($eligibleSpend * (float) $rule->rate_value) / 100, 2),
                    'fixed' => (float) $rule->rate_value,
                    default => 0.0,
                };
                $cashbackEarned += $ruleCashback;
                $appliedRules[] = [
                    'rule' => $rule->name,
                    'type' => 'cashback',
                    'amount' => $ruleCashback,
                ];
            } elseif ($program->points_enabled) {
                $rulePoints = match ($rule->type) {
                    'percentage' => (int) floor(($eligibleSpend * (float) $rule->rate_value) / 100),
                    'spending' => (int) floor($eligibleSpend * (float) $rule->rate_value),
                    'fixed' => (int) $rule->rate_value,
                    'multiplier' => (int) floor($basePoints * ((float) $rule->rate_value - 1.0)),
                    default => 0,
                };

                if ($rule->max_points_per_order !== null && $rulePoints > $rule->max_points_per_order) {
                    $rulePoints = $rule->max_points_per_order;
                }

                $basePoints += $rulePoints;
                $appliedRules[] = [
                    'rule' => $rule->name,
                    'type' => 'points',
                    'points' => $rulePoints,
                ];
            }
        }

        // 4. Apply Tier Multiplier to total earned points
        $finalPoints = (int) floor($basePoints * $tierMultiplier);

        // 5. Calculate Tier Cashback if cashback is enabled
        if ($program->cashback_enabled && $tierCashbackPercent > 0) {
            $tierCashback = round(($eligibleSpend * $tierCashbackPercent) / 100, 2);
            $cashbackEarned += $tierCashback;
            $appliedRules[] = [
                'rule' => "tier_cashback_{$customer?->loyalty_tier}",
                'type' => 'cashback',
                'amount' => $tierCashback,
            ];
        }

        return new LoyaltyCalculationResult(
            pointsEarned: max(0, $finalPoints),
            cashbackEarned: max(0.0, round($cashbackEarned, 2)),
            stampsEarned: 1, // Will be verified against stamp programs in StampCardService
            multiplier: $tierMultiplier,
            appliedRules: $appliedRules
        );
    }

    /**
     * Determine eligible spend amount for an order according to loyalty rules.
     */
    public function getEligibleOrderSpend(Order $order, LoyaltyProgram $program): float
    {
        // Net order amount after discounts
        $subtotal = (float) $order->subtotal;
        $discount = (float) $order->discount_amount;
        $netSpend = max(0.0, $subtotal - $discount);

        $settings = $program->settings ?? [];
        $includeDelivery = (bool) ($settings['include_delivery_fee'] ?? false);
        $includeTips = (bool) ($settings['include_tips'] ?? false);
        $includeService = (bool) ($settings['include_service_fee'] ?? true);

        if ($includeDelivery && $order->delivery_fee) {
            $netSpend += (float) $order->delivery_fee;
        }

        if ($includeTips && $order->tip_amount) {
            $netSpend += (float) $order->tip_amount;
        }

        if ($includeService && $order->service_fee) {
            $netSpend += (float) $order->service_fee;
        }

        return round($netSpend, 2);
    }

    /**
     * Check if an order matches conditions of an earning rule.
     */
    protected function matchesRuleConditions(Order $order, LoyaltyEarningRule $rule, float $spend): bool
    {
        if ($rule->min_order_spend !== null && $spend < (float) $rule->min_order_spend) {
            return false;
        }

        $conditions = $rule->conditions ?? [];
        if (empty($conditions)) {
            return true;
        }

        // Branch condition
        if (! empty($conditions['branch_ids']) && is_array($conditions['branch_ids'])) {
            if (! in_array($order->location_id, $conditions['branch_ids'], false)) {
                return false;
            }
        }

        // Days of week condition (0=Sunday, 6=Saturday)
        if (! empty($conditions['days_of_week']) && is_array($conditions['days_of_week'])) {
            $currentDay = Carbon::now()->dayOfWeek;
            if (! in_array($currentDay, $conditions['days_of_week'], false)) {
                return false;
            }
        }

        // Time window condition
        if (! empty($conditions['start_time']) && ! empty($conditions['end_time'])) {
            $now = Carbon::now()->format('H:i');
            if ($now < $conditions['start_time'] || $now > $conditions['end_time']) {
                return false;
            }
        }

        // Product / Category conditions
        if (! empty($conditions['product_ids']) || ! empty($conditions['category_ids'])) {
            $order->loadMissing('items.product');
            $hasMatchingItem = false;

            foreach ($order->items as $item) {
                if (! empty($conditions['product_ids']) && in_array($item->product_id, $conditions['product_ids'], false)) {
                    $hasMatchingItem = true;
                    break;
                }
                if (! empty($conditions['category_ids']) && $item->product && in_array($item->product->category_id, $conditions['category_ids'], false)) {
                    $hasMatchingItem = true;
                    break;
                }
            }

            if (! $hasMatchingItem) {
                return false;
            }
        }

        return true;
    }
}
