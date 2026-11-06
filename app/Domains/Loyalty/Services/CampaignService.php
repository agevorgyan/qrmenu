<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\CashbackTransactionDTO;
use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\DTOs\RedemptionRequestDTO;
use App\Domains\Loyalty\Enums\CampaignTriggerType;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Models\LoyaltyCampaign;
use App\Domains\Loyalty\Models\LoyaltyCampaignEvent;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function __construct(
        protected PointsLedgerService $pointsService,
        protected CashbackService $cashbackService,
        protected RewardsService $rewardsService,
    ) {}

    /**
     * Trigger campaigns matching a specific event for a customer.
     */
    public function triggerCampaigns(Customer $customer, CampaignTriggerType $trigger): array
    {
        $campaigns = LoyaltyCampaign::where('vendor_id', $customer->vendor_id)
            ->where('trigger_type', $trigger)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get();

        $executed = [];

        foreach ($campaigns as $campaign) {
            // Deduplication: Has customer already received this campaign reward?
            $alreadyGranted = LoyaltyCampaignEvent::where('vendor_id', $customer->vendor_id)
                ->where('campaign_id', $campaign->id)
                ->where('customer_id', $customer->id)
                ->when($trigger === CampaignTriggerType::Birthday, function ($q) {
                    $q->where('created_at', '>=', Carbon::now()->startOfYear());
                })
                ->exists();

            if ($alreadyGranted) {
                continue;
            }

            $result = $this->disburseCampaignReward($customer, $campaign);
            if ($result) {
                $executed[] = $result;
            }
        }

        return $executed;
    }

    /**
     * Disburse the configured reward for a campaign to a customer.
     */
    protected function disburseCampaignReward(Customer $customer, LoyaltyCampaign $campaign): ?LoyaltyCampaignEvent
    {
        return DB::transaction(function () use ($customer, $campaign) {
            $description = '';

            if ($campaign->reward_type === 'points') {
                $points = (int) $campaign->reward_value;
                if ($points > 0) {
                    $this->pointsService->creditPoints(new PointsTransactionDTO(
                        vendorId: $customer->vendor_id,
                        customerId: $customer->id,
                        points: $points,
                        type: LoyaltyTransactionType::Earned,
                        referenceType: 'campaign',
                        referenceId: $campaign->id,
                        notes: "Campaign reward: {$campaign->name}"
                    ));
                    $description = "{$points} Points";
                }
            } elseif ($campaign->reward_type === 'cashback') {
                $amount = (float) $campaign->reward_value;
                if ($amount > 0) {
                    $this->cashbackService->creditCashback(new CashbackTransactionDTO(
                        vendorId: $customer->vendor_id,
                        customerId: $customer->id,
                        amount: $amount,
                        type: LoyaltyTransactionType::Earned,
                        notes: "Campaign cashback: {$campaign->name}"
                    ));
                    $description = "{$amount} Cashback";
                }
            } elseif ($campaign->reward_type === 'reward_voucher' && $campaign->reward_id) {
                try {
                    $redemption = $this->rewardsService->redeemReward(new RedemptionRequestDTO(
                        vendorId: $customer->vendor_id,
                        customerId: $customer->id,
                        rewardId: $campaign->reward_id,
                    ));
                    $description = "Voucher code: {$redemption->redemption_code}";
                } catch (\Throwable $e) {
                    return null;
                }
            }

            return LoyaltyCampaignEvent::create([
                'vendor_id' => $customer->vendor_id,
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'reward_granted' => $description ?: 'Campaign Reward',
            ]);
        });
    }

    /**
     * Run daily automated birthday campaigns for customers whose birthday is today.
     */
    public function processDailyBirthdayCampaigns(int $vendorId): int
    {
        $today = Carbon::today();
        $customers = Customer::where('vendor_id', $vendorId)
            ->whereNotNull('birthdate')
            ->whereMonth('birthdate', $today->month)
            ->whereDay('birthdate', $today->day)
            ->get();

        $processed = 0;
        foreach ($customers as $customer) {
            $events = $this->triggerCampaigns($customer, CampaignTriggerType::Birthday);
            $processed += count($events);
        }

        return $processed;
    }
}
