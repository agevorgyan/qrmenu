<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\DTOs\RedemptionRequestDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Events\RewardRedeemed;
use App\Domains\Loyalty\Exceptions\InvalidRedemptionException;
use App\Domains\Loyalty\Exceptions\RewardUnavailableException;
use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Domains\Loyalty\Models\LoyaltyRewardRedemption;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RewardsService
{
    public function __construct(
        protected PointsLedgerService $ledgerService
    ) {}

    /**
     * Redeem a reward for a customer.
     */
    public function redeemReward(RedemptionRequestDTO $dto): LoyaltyRewardRedemption
    {
        return DB::transaction(function () use ($dto) {
            $reward = LoyaltyReward::where('vendor_id', $dto->vendorId)
                ->where('id', $dto->rewardId)
                ->lockForUpdate()
                ->firstOrFail();

            $customer = Customer::where('vendor_id', $dto->vendorId)
                ->where('id', $dto->customerId)
                ->firstOrFail();

            $this->validateRewardEligibility($reward, $customer);

            // Deduct points from ledger
            if ($reward->points_cost > 0) {
                $this->ledgerService->debitPoints(new PointsTransactionDTO(
                    vendorId: $dto->vendorId,
                    customerId: $dto->customerId,
                    points: $reward->points_cost,
                    type: LoyaltyTransactionType::Redeemed,
                    orderId: $dto->orderId,
                    staffId: $dto->staffId,
                    referenceType: 'reward_redemption',
                    referenceId: $reward->id,
                    notes: "Redemption of reward '{$reward->title}'"
                ));
            }

            // Increment redeemed count
            $reward->increment('redeemed_count');

            // Generate redemption record with a 24-hour expiration window
            $redemption = LoyaltyRewardRedemption::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $dto->vendorId,
                'reward_id' => $reward->id,
                'customer_id' => $customer->id,
                'order_id' => $dto->orderId,
                'staff_id' => $dto->staffId,
                'redemption_code' => 'RWD-'.strtoupper(Str::random(8)),
                'points_spent' => $reward->points_cost,
                'status' => 'pending',
                'expires_at' => Carbon::now()->addHours(24),
            ]);

            event(new RewardRedeemed($customer, $redemption));

            return $redemption;
        });
    }

    /**
     * Mark a redemption voucher as used by staff.
     */
    public function useRedemptionCode(int $vendorId, string $redemptionCode, ?int $staffId = null, ?int $orderId = null): LoyaltyRewardRedemption
    {
        return DB::transaction(function () use ($vendorId, $redemptionCode, $staffId, $orderId) {
            $redemption = LoyaltyRewardRedemption::where('vendor_id', $vendorId)
                ->where('redemption_code', strtoupper(trim($redemptionCode)))
                ->lockForUpdate()
                ->firstOrFail();

            if ($redemption->status !== 'pending') {
                throw new InvalidRedemptionException("Voucher has already been {$redemption->status}.");
            }

            if ($redemption->expires_at->isPast()) {
                $redemption->update(['status' => 'expired']);
                throw new InvalidRedemptionException('Voucher has expired.');
            }

            $redemption->update([
                'status' => 'used',
                'used_at' => now(),
                'staff_id' => $staffId ?: $redemption->staff_id,
                'order_id' => $orderId ?: $redemption->order_id,
            ]);

            return $redemption;
        });
    }

    /**
     * Validate whether a customer is eligible to redeem a reward.
     */
    public function validateRewardEligibility(LoyaltyReward $reward, Customer $customer): void
    {
        if (! $reward->is_active) {
            throw new RewardUnavailableException('Reward is currently inactive.');
        }

        if ($reward->starts_at && $reward->starts_at->isFuture()) {
            throw new RewardUnavailableException('Reward is not yet active.');
        }

        if ($reward->ends_at && $reward->ends_at->isPast()) {
            throw new RewardUnavailableException('Reward has expired.');
        }

        if (! $reward->hasStock()) {
            throw new RewardUnavailableException('Reward is out of stock.');
        }

        // Tier validation
        if ($reward->required_tier_id) {
            $requiredTier = LoyaltyTier::find($reward->required_tier_id);
            $customerTier = LoyaltyTier::where('vendor_id', $customer->vendor_id)
                ->where('name', $customer->loyalty_tier)
                ->first();

            if (! $customerTier || $customerTier->level < ($requiredTier?->level ?? 0)) {
                throw new RewardUnavailableException("This reward is exclusive to {$requiredTier?->name} tier members.");
            }
        }

        // Limit per customer
        if ($reward->max_per_customer !== null) {
            $usedCount = LoyaltyRewardRedemption::where('reward_id', $reward->id)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'used'])
                ->count();

            if ($usedCount >= $reward->max_per_customer) {
                throw new RewardUnavailableException("You have reached the limit of {$reward->max_per_customer} redemptions for this reward.");
            }
        }

        // Points balance check
        if ((int) $customer->loyalty_points < $reward->points_cost) {
            throw new RewardUnavailableException("Insufficient points. Required: {$reward->points_cost}, Available: {$customer->loyalty_points}");
        }
    }
}
