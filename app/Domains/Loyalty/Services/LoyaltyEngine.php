<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\CashbackTransactionDTO;
use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\Enums\CampaignTriggerType;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class LoyaltyEngine
{
    public function __construct(
        protected PointsCalculator $calculator,
        protected PointsLedgerService $pointsService,
        protected CashbackService $cashbackService,
        protected StampCardService $stampCardService,
        protected ReferralService $referralService,
        protected CampaignService $campaignService,
        protected CustomerLoyaltyService $customerLoyaltyService,
    ) {}

    /**
     * Process loyalty rewards for a completed or paid order.
     */
    public function processOrder(Order $order): array
    {
        $vendor = Vendor::withoutGlobalScopes()->find($order->vendor_id);
        if (! $vendor) {
            return ['status' => 'skipped', 'reason' => 'Vendor not found'];
        }

        $program = LoyaltyProgram::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            return ['status' => 'skipped', 'reason' => 'No active loyalty program'];
        }

        // Resolve customer
        $customer = null;
        if ($order->customer_id) {
            $customer = Customer::withoutGlobalScopes()->find($order->customer_id);
        }

        if (! $customer && ($order->customer_phone || $order->customer_email)) {
            $customer = $this->customerLoyaltyService->enrollOrGetCustomer(
                $vendor,
                $order->customer_phone ?: $order->customer_email,
                $order->customer_name,
                $order->customer_birthdate?->format('Y-m-d'),
                (bool) $order->marketing_opt_in
            );
            $order->update(['customer_id' => $customer->id]);
        }

        if (! $customer) {
            return ['status' => 'skipped', 'reason' => 'No identified customer attached to order'];
        }

        // Check if points were already awarded for this order to prevent double earning
        $alreadyAwarded = DB::table('loyalty_ledger_transactions')
            ->where('vendor_id', $vendor->id)
            ->where('order_id', $order->id)
            ->where('type', LoyaltyTransactionType::Earned->value)
            ->exists();

        if ($alreadyAwarded) {
            return ['status' => 'skipped', 'reason' => 'Loyalty already processed for this order'];
        }

        return DB::transaction(function () use ($order, $vendor, $program, $customer) {
            $calc = $this->calculator->calculateForOrder($order, $program, $customer);
            $results = [
                'points_earned' => 0,
                'cashback_earned' => 0.0,
                'stamps_earned' => 0,
                'referral_qualified' => false,
            ];

            // 1. Credit Points
            if ($program->points_enabled && $calc->pointsEarned > 0) {
                $this->pointsService->creditPoints(new PointsTransactionDTO(
                    vendorId: $vendor->id,
                    customerId: $customer->id,
                    points: $calc->pointsEarned,
                    type: LoyaltyTransactionType::Earned,
                    orderId: $order->id,
                    referenceType: 'order',
                    referenceId: $order->id,
                    notes: "Points earned from order #{$order->order_number} ({$customer->loyalty_tier} tier)",
                    metadata: ['applied_rules' => $calc->appliedRules]
                ));
                $results['points_earned'] = $calc->pointsEarned;
            }

            // 2. Credit Cashback
            if ($program->cashback_enabled && $calc->cashbackEarned > 0) {
                $this->cashbackService->creditCashback(new CashbackTransactionDTO(
                    vendorId: $vendor->id,
                    customerId: $customer->id,
                    amount: $calc->cashbackEarned,
                    type: LoyaltyTransactionType::Earned,
                    orderId: $order->id,
                    notes: "Cashback earned from order #{$order->order_number}",
                    metadata: ['applied_rules' => $calc->appliedRules]
                ));
                $results['cashback_earned'] = $calc->cashbackEarned;
            }

            // 3. Award Stamp Card
            if ($program->stamps_enabled) {
                $stampCard = $this->stampCardService->awardStampsForOrder($order, $customer);
                if ($stampCard) {
                    $results['stamps_earned'] = 1;
                }
            }

            // 4. Qualify Referral if applicable
            if ($program->referrals_enabled) {
                $referral = $this->referralService->qualifyReferralOnOrder($order, $customer);
                if ($referral) {
                    $results['referral_qualified'] = true;
                }
            }

            // 5. Trigger First Order Campaign if this is customer's 1st completed order
            if ($customer->total_orders_count <= 1) {
                $this->campaignService->triggerCampaigns($customer, CampaignTriggerType::FirstOrder);
            }

            return [
                'status' => 'success',
                'customer_id' => $customer->id,
                'results' => $results,
            ];
        });
    }

    /**
     * Process reversal for an order (cancellation, full refund, or partial refund).
     */
    public function reverseOrderLoyalty(Order $order, float $refundRatio = 1.0, ?string $reason = null): array
    {
        $pointsTx = $this->pointsService->reverseOrderPoints(
            $order->vendor_id,
            $order->id,
            $refundRatio,
            $reason
        );

        $cashbackTx = $this->cashbackService->reverseOrderCashback(
            $order->vendor_id,
            $order->id,
            $refundRatio,
            $reason
        );

        return [
            'status' => 'reversed',
            'points_reversed' => $pointsTx ? abs($pointsTx->points) : 0,
            'cashback_reversed' => $cashbackTx ? abs((float) $cashbackTx->amount) : 0.0,
        ];
    }
}
