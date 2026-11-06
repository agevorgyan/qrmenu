<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\StampAwardDTO;
use App\Domains\Loyalty\Enums\StampStatus;
use App\Domains\Loyalty\Events\StampCardCompleted;
use App\Domains\Loyalty\Models\LoyaltyStampCard;
use App\Domains\Loyalty\Models\LoyaltyStampProgram;
use App\Domains\Loyalty\Models\LoyaltyStampTransaction;
use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StampCardService
{
    /**
     * Award stamps to customer from a qualifying order.
     */
    public function awardStampsForOrder(Order $order, Customer $customer): ?LoyaltyStampCard
    {
        $programs = LoyaltyStampProgram::where('vendor_id', $order->vendor_id)
            ->where('is_active', true)
            ->get();

        if ($programs->isEmpty()) {
            return null;
        }

        foreach ($programs as $program) {
            if ($this->orderQualifiesForStampProgram($order, $program)) {
                return $this->awardStamps(new StampAwardDTO(
                    vendorId: $order->vendor_id,
                    customerId: $customer->id,
                    programId: $program->id,
                    stamps: 1,
                    orderId: $order->id,
                    notes: "Earned from order #{$order->order_number}"
                ));
            }
        }

        return null;
    }

    /**
     * Award a specified number of stamps to customer's active card for a program.
     */
    public function awardStamps(StampAwardDTO $dto): LoyaltyStampCard
    {
        return DB::transaction(function () use ($dto) {
            $program = LoyaltyStampProgram::where('vendor_id', $dto->vendorId)
                ->where('id', $dto->programId)
                ->firstOrFail();

            // Find existing active card or create new one
            $card = LoyaltyStampCard::where('vendor_id', $dto->vendorId)
                ->where('customer_id', $dto->customerId)
                ->where('program_id', $dto->programId)
                ->where('status', StampStatus::Active)
                ->lockForUpdate()
                ->first();

            if (! $card) {
                $expiresAt = $program->card_validity_days ? Carbon::now()->addDays($program->card_validity_days) : null;
                $card = LoyaltyStampCard::create([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $dto->vendorId,
                    'program_id' => $dto->programId,
                    'customer_id' => $dto->customerId,
                    'stamps_collected' => 0,
                    'stamps_required' => $program->stamps_required,
                    'status' => StampStatus::Active,
                    'expires_at' => $expiresAt,
                ]);
            }

            $newCount = $card->stamps_collected + $dto->stamps;
            $isCompleted = $newCount >= $card->stamps_required;

            $card->update([
                'stamps_collected' => min($newCount, $card->stamps_required),
                'status' => $isCompleted ? StampStatus::Completed : StampStatus::Active,
                'completed_at' => $isCompleted ? now() : null,
            ]);

            LoyaltyStampTransaction::create([
                'vendor_id' => $dto->vendorId,
                'card_id' => $card->id,
                'order_id' => $dto->orderId,
                'staff_id' => $dto->staffId,
                'type' => 'awarded',
                'stamps' => $dto->stamps,
                'notes' => $dto->notes,
            ]);

            if ($isCompleted) {
                $customer = Customer::withoutGlobalScopes()->find($dto->customerId);
                if ($customer) {
                    event(new StampCardCompleted($customer, $card));
                }
            }

            return $card;
        });
    }

    /**
     * Redeem a completed stamp card.
     */
    public function redeemStampCard(LoyaltyStampCard $card, ?int $staffId = null): LoyaltyStampCard
    {
        if ($card->status !== StampStatus::Completed) {
            throw new \InvalidArgumentException('Only completed stamp cards can be redeemed.');
        }

        return DB::transaction(function () use ($card, $staffId) {
            $card->update([
                'status' => StampStatus::Redeemed,
                'redeemed_at' => now(),
            ]);

            LoyaltyStampTransaction::create([
                'vendor_id' => $card->vendor_id,
                'card_id' => $card->id,
                'staff_id' => $staffId,
                'type' => 'redeemed',
                'stamps' => -$card->stamps_required,
                'notes' => 'Reward redeemed by customer',
            ]);

            return $card;
        });
    }

    /**
     * Check if an order qualifies for a stamp program.
     */
    protected function orderQualifiesForStampProgram(Order $order, LoyaltyStampProgram $program): bool
    {
        $paid = (float) ($order->paid_amount ?: $order->total_amount);
        if ($program->min_order_spend !== null && $paid < (float) $program->min_order_spend) {
            return false;
        }

        $cats = $program->qualifying_category_ids;
        $prods = $program->qualifying_product_ids;

        if (empty($cats) && empty($prods)) {
            return true;
        }

        $order->loadMissing('items.product');
        foreach ($order->items as $item) {
            if (! empty($prods) && in_array($item->product_id, $prods, false)) {
                return true;
            }
            if (! empty($cats) && $item->product && in_array($item->product->category_id, $cats, false)) {
                return true;
            }
        }

        return false;
    }
}
