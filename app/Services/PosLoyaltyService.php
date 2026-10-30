<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PosCustomerLoyaltyTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosLoyaltyService
{
    /**
     * Conversion rate: how many currency units (e.g. AMD) spent to earn 1 point.
     */
    public const SPEND_PER_POINT = 100.0;

    /**
     * Value of 1 loyalty point in currency units (e.g. 1 point = 1 AMD discount).
     */
    public const POINT_DISCOUNT_VALUE = 1.0;

    /**
     * Award loyalty points to a customer for a completed order.
     */
    public function awardPointsForOrder(Order $order): ?PosCustomerLoyaltyTransaction
    {
        if (! $order->customer_id) {
            return null;
        }

        $customer = Customer::withoutGlobalScopes()->find($order->customer_id);
        if (! $customer || (int) $customer->vendor_id !== (int) $order->vendor_id) {
            return null;
        }

        // Avoid duplicate earning for the same order
        $alreadyAwarded = PosCustomerLoyaltyTransaction::where('order_id', $order->id)
            ->where('type', 'earned')
            ->exists();

        if ($alreadyAwarded) {
            return null;
        }

        $paidAmount = (float) ($order->paid_amount ?: $order->total_amount);
        if ($paidAmount <= 0) {
            return null;
        }

        $multiplier = $this->getTierMultiplier($customer->loyalty_tier);
        $pointsEarned = (int) floor(($paidAmount / self::SPEND_PER_POINT) * $multiplier);

        if ($pointsEarned <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $customer, $pointsEarned) {
            $newBalance = (int) $customer->loyalty_points + $pointsEarned;
            $newTier = $this->calculateTier($newBalance);

            $customer->update([
                'loyalty_points' => $newBalance,
                'loyalty_tier' => $newTier,
            ]);

            return PosCustomerLoyaltyTransaction::create([
                'vendor_id' => $order->vendor_id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'type' => 'earned',
                'points' => $pointsEarned,
                'balance_after' => $newBalance,
                'notes' => "Earned from Order #{$order->order_number} ({$newTier} tier)",
            ]);
        });
    }

    /**
     * Redeem customer loyalty points as an order discount.
     */
    public function redeemPointsForOrder(Order $order, Customer $customer, int $pointsToRedeem): array
    {
        if ((int) $customer->vendor_id !== (int) $order->vendor_id) {
            throw ValidationException::withMessages([
                'customer' => 'Customer does not belong to this vendor.',
            ]);
        }

        if ($pointsToRedeem <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Points to redeem must be greater than zero.',
            ]);
        }

        if ((int) $customer->loyalty_points < $pointsToRedeem) {
            throw ValidationException::withMessages([
                'points' => "Insufficient loyalty points. Available: {$customer->loyalty_points}",
            ]);
        }

        return DB::transaction(function () use ($order, $customer, $pointsToRedeem) {
            $discountValue = $pointsToRedeem * self::POINT_DISCOUNT_VALUE;
            $currentTotal = (float) $order->total_amount;

            // Cap discount to order total
            if ($discountValue > $currentTotal) {
                $discountValue = $currentTotal;
                $pointsToRedeem = (int) ceil($discountValue / self::POINT_DISCOUNT_VALUE);
            }

            $newBalance = (int) $customer->loyalty_points - $pointsToRedeem;
            $newTier = $this->calculateTier($newBalance);

            $customer->update([
                'loyalty_points' => $newBalance,
                'loyalty_tier' => $newTier,
            ]);

            $newTotal = max(0.0, $currentTotal - $discountValue);
            $newDiscountAmount = (float) ($order->discount_amount ?: 0) + $discountValue;

            $order->update([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_email' => $customer->email,
                'discount_amount' => $newDiscountAmount,
                'discount_type' => 'loyalty',
                'discount_reason' => "Loyalty Redemption ({$pointsToRedeem} pts)",
                'total_amount' => $newTotal,
            ]);

            $tx = PosCustomerLoyaltyTransaction::create([
                'vendor_id' => $order->vendor_id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'type' => 'redeemed',
                'points' => -$pointsToRedeem,
                'balance_after' => $newBalance,
                'notes' => "Redeemed on Order #{$order->order_number} for {$discountValue} discount",
            ]);

            return [
                'points_redeemed' => $pointsToRedeem,
                'discount_value' => $discountValue,
                'remaining_balance' => $newBalance,
                'order_total' => $newTotal,
                'transaction_id' => $tx->id,
            ];
        });
    }

    /**
     * Get tier points earning multiplier.
     */
    public function getTierMultiplier(string $tier): float
    {
        return match (strtolower($tier)) {
            'silver' => 1.25,
            'gold' => 1.5,
            'platinum' => 2.0,
            default => 1.0,
        };
    }

    /**
     * Calculate loyalty tier from points balance.
     */
    public function calculateTier(int $points): string
    {
        if ($points >= 15000) {
            return 'platinum';
        }
        if ($points >= 5000) {
            return 'gold';
        }
        if ($points >= 1000) {
            return 'silver';
        }

        return 'bronze';
    }

    /**
     * Get loyalty summary for a customer.
     */
    public function getLoyaltySummary(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'points' => (int) $customer->loyalty_points,
            'tier' => $customer->loyalty_tier ?: 'bronze',
            'discount_value' => (int) $customer->loyalty_points * self::POINT_DISCOUNT_VALUE,
            'multiplier' => $this->getTierMultiplier($customer->loyalty_tier ?: 'bronze'),
            'recent_transactions' => $customer->loyaltyTransactions()->take(10)->get(),
        ];
    }
}
