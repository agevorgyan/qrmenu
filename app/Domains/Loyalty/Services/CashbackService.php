<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\CashbackTransactionDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Events\CashbackEarned;
use App\Domains\Loyalty\Events\CashbackRedeemed;
use App\Domains\Loyalty\Exceptions\InsufficientCashbackException;
use App\Domains\Loyalty\Models\LoyaltyCashbackAccount;
use App\Domains\Loyalty\Models\LoyaltyCashbackLot;
use App\Domains\Loyalty\Models\LoyaltyCashbackTransaction;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashbackService
{
    /**
     * Credit cashback to a customer's account.
     */
    public function creditCashback(CashbackTransactionDTO $dto): LoyaltyCashbackTransaction
    {
        return DB::transaction(function () use ($dto) {
            $account = LoyaltyCashbackAccount::where('vendor_id', $dto->vendorId)
                ->where('customer_id', $dto->customerId)
                ->lockForUpdate()
                ->first();

            $program = LoyaltyProgram::where('vendor_id', $dto->vendorId)->first();
            $currency = $program?->currency ?? 'AMD';

            if (! $account) {
                $account = LoyaltyCashbackAccount::create([
                    'vendor_id' => $dto->vendorId,
                    'customer_id' => $dto->customerId,
                    'currency' => $currency,
                    'current_balance' => 0.00,
                    'lifetime_earned' => 0.00,
                    'lifetime_redeemed' => 0.00,
                ]);
            }

            if ($dto->idempotencyKey) {
                $existing = LoyaltyCashbackTransaction::where('vendor_id', $dto->vendorId)
                    ->where('idempotency_key', $dto->idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $amountToAdd = abs($dto->amount);
            $balanceBefore = (float) $account->current_balance;
            $balanceAfter = round($balanceBefore + $amountToAdd, 2);

            $transaction = LoyaltyCashbackTransaction::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $dto->vendorId,
                'account_id' => $account->id,
                'customer_id' => $dto->customerId,
                'order_id' => $dto->orderId,
                'staff_id' => $dto->staffId,
                'type' => $dto->type,
                'amount' => $amountToAdd,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'idempotency_key' => $dto->idempotencyKey,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
            ]);

            $expiresAt = null;
            if ($program && $program->cashback_expiration_days) {
                $expiresAt = Carbon::now()->addDays($program->cashback_expiration_days);
            }

            LoyaltyCashbackLot::create([
                'vendor_id' => $dto->vendorId,
                'account_id' => $account->id,
                'transaction_id' => $transaction->id,
                'initial_amount' => $amountToAdd,
                'remaining_amount' => $amountToAdd,
                'expires_at' => $expiresAt,
                'is_expired' => false,
            ]);

            $account->update([
                'current_balance' => $balanceAfter,
                'lifetime_earned' => round((float) $account->lifetime_earned + $amountToAdd, 2),
                'last_activity_at' => now(),
            ]);

            $customer = Customer::withoutGlobalScopes()->find($dto->customerId);
            if ($customer) {
                event(new CashbackEarned($customer, $transaction));
            }

            return $transaction;
        });
    }

    /**
     * Debit cashback from a customer's account using FIFO lot consumption.
     */
    public function debitCashback(CashbackTransactionDTO $dto): LoyaltyCashbackTransaction
    {
        return DB::transaction(function () use ($dto) {
            $account = LoyaltyCashbackAccount::where('vendor_id', $dto->vendorId)
                ->where('customer_id', $dto->customerId)
                ->lockForUpdate()
                ->firstOrFail();

            $amountToDeduct = abs($dto->amount);

            if ((float) $account->current_balance < $amountToDeduct) {
                throw new InsufficientCashbackException("Insufficient cashback balance. Balance: {$account->current_balance}, Requested: {$amountToDeduct}");
            }

            if ($dto->idempotencyKey) {
                $existing = LoyaltyCashbackTransaction::where('vendor_id', $dto->vendorId)
                    ->where('idempotency_key', $dto->idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $balanceBefore = (float) $account->current_balance;
            $balanceAfter = round($balanceBefore - $amountToDeduct, 2);

            $transaction = LoyaltyCashbackTransaction::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $dto->vendorId,
                'account_id' => $account->id,
                'customer_id' => $dto->customerId,
                'order_id' => $dto->orderId,
                'staff_id' => $dto->staffId,
                'type' => $dto->type,
                'amount' => -$amountToDeduct,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'idempotency_key' => $dto->idempotencyKey,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
            ]);

            // Consume Lots FIFO
            $remainingToConsume = $amountToDeduct;
            $lots = LoyaltyCashbackLot::where('account_id', $account->id)
                ->where('is_expired', false)
                ->where('remaining_amount', '>', 0)
                ->orderByRaw('expires_at ASC NULLS LAST')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remainingToConsume <= 0) {
                    break;
                }

                $lotRemaining = (float) $lot->remaining_amount;
                if ($lotRemaining <= $remainingToConsume) {
                    $remainingToConsume -= $lotRemaining;
                    $lot->update(['remaining_amount' => 0.00]);
                } else {
                    $lot->update(['remaining_amount' => round($lotRemaining - $remainingToConsume, 2)]);
                    $remainingToConsume = 0;
                }
            }

            $account->update([
                'current_balance' => $balanceAfter,
                'lifetime_redeemed' => round((float) $account->lifetime_redeemed + $amountToDeduct, 2),
                'last_activity_at' => now(),
            ]);

            $customer = Customer::withoutGlobalScopes()->find($dto->customerId);
            if ($customer) {
                event(new CashbackRedeemed($customer, $transaction));
            }

            return $transaction;
        });
    }

    /**
     * Reverse cashback on order refund or cancellation.
     */
    public function reverseOrderCashback(int $vendorId, int $orderId, float $refundRatio = 1.0, ?string $reason = null): ?LoyaltyCashbackTransaction
    {
        return DB::transaction(function () use ($vendorId, $orderId, $refundRatio, $reason) {
            $originalTx = LoyaltyCashbackTransaction::where('vendor_id', $vendorId)
                ->where('order_id', $orderId)
                ->where('type', LoyaltyTransactionType::Earned)
                ->first();

            if (! $originalTx || (float) $originalTx->amount <= 0) {
                return null;
            }

            $alreadyReversed = LoyaltyCashbackTransaction::where('vendor_id', $vendorId)
                ->where('order_id', $orderId)
                ->where('type', LoyaltyTransactionType::Reversal)
                ->exists();

            if ($alreadyReversed) {
                return null;
            }

            $amountToReverse = round((float) $originalTx->amount * min(1.0, max(0.0, $refundRatio)), 2);
            if ($amountToReverse <= 0) {
                return null;
            }

            $account = LoyaltyCashbackAccount::where('vendor_id', $vendorId)
                ->where('customer_id', $originalTx->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) $account->current_balance;
            $balanceAfter = max(0.0, round($balanceBefore - $amountToReverse, 2));

            return LoyaltyCashbackTransaction::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendorId,
                'account_id' => $account->id,
                'customer_id' => $originalTx->customer_id,
                'order_id' => $orderId,
                'type' => LoyaltyTransactionType::Reversal,
                'amount' => -$amountToReverse,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'notes' => $reason ?: "Cashback reversal for order #{$orderId} (".round($refundRatio * 100).'% refund)',
            ]);
        });
    }
}
