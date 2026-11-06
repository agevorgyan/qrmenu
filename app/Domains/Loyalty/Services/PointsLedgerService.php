<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Events\PointsEarned;
use App\Domains\Loyalty\Events\PointsRedeemed;
use App\Domains\Loyalty\Exceptions\InsufficientPointsException;
use App\Domains\Loyalty\Models\LoyaltyLedgerTransaction;
use App\Domains\Loyalty\Models\LoyaltyPointLot;
use App\Domains\Loyalty\Models\LoyaltyPointsAccount;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PointsLedgerService
{
    public function __construct(
        protected TierService $tierService
    ) {}

    /**
     * Credit loyalty points to a customer's account.
     */
    public function creditPoints(PointsTransactionDTO $dto): LoyaltyLedgerTransaction
    {
        return DB::transaction(function () use ($dto) {
            $account = LoyaltyPointsAccount::where('vendor_id', $dto->vendorId)
                ->where('customer_id', $dto->customerId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = LoyaltyPointsAccount::create([
                    'vendor_id' => $dto->vendorId,
                    'customer_id' => $dto->customerId,
                    'current_balance' => 0,
                    'lifetime_earned' => 0,
                    'lifetime_redeemed' => 0,
                    'lifetime_expired' => 0,
                ]);
            }

            // Check idempotency key if provided
            if ($dto->idempotencyKey) {
                $existing = LoyaltyLedgerTransaction::where('vendor_id', $dto->vendorId)
                    ->where('idempotency_key', $dto->idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $pointsToAdd = abs($dto->points);
            $balanceBefore = $account->current_balance;
            $balanceAfter = $balanceBefore + $pointsToAdd;

            $transaction = LoyaltyLedgerTransaction::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $dto->vendorId,
                'account_id' => $account->id,
                'customer_id' => $dto->customerId,
                'order_id' => $dto->orderId,
                'staff_id' => $dto->staffId,
                'type' => $dto->type,
                'points' => $pointsToAdd,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'idempotency_key' => $dto->idempotencyKey,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
            ]);

            // Calculate Lot Expiration from Program
            $program = LoyaltyProgram::where('vendor_id', $dto->vendorId)->first();
            $expiresAt = null;
            if ($program && $program->points_expiration_days) {
                $expiresAt = Carbon::now()->addDays($program->points_expiration_days);
            }

            LoyaltyPointLot::create([
                'vendor_id' => $dto->vendorId,
                'account_id' => $account->id,
                'transaction_id' => $transaction->id,
                'initial_points' => $pointsToAdd,
                'remaining_points' => $pointsToAdd,
                'expires_at' => $expiresAt,
                'is_expired' => false,
            ]);

            $account->update([
                'current_balance' => $balanceAfter,
                'lifetime_earned' => $account->lifetime_earned + $pointsToAdd,
                'last_activity_at' => now(),
            ]);

            // Sync Customer points column
            $customer = Customer::withoutGlobalScopes()->find($dto->customerId);
            if ($customer) {
                $customer->update(['loyalty_points' => $balanceAfter]);
                $this->tierService->evaluateCustomerTier($customer);
                event(new PointsEarned($customer, $transaction));
            }

            return $transaction;
        });
    }

    /**
     * Debit loyalty points from a customer's account using FIFO lot consumption.
     */
    public function debitPoints(PointsTransactionDTO $dto): LoyaltyLedgerTransaction
    {
        return DB::transaction(function () use ($dto) {
            $account = LoyaltyPointsAccount::where('vendor_id', $dto->vendorId)
                ->where('customer_id', $dto->customerId)
                ->lockForUpdate()
                ->firstOrFail();

            $pointsToDeduct = abs($dto->points);

            if ($account->current_balance < $pointsToDeduct) {
                throw new InsufficientPointsException("Insufficient loyalty points. Balance: {$account->current_balance}, Requested: {$pointsToDeduct}");
            }

            // Check idempotency key if provided
            if ($dto->idempotencyKey) {
                $existing = LoyaltyLedgerTransaction::where('vendor_id', $dto->vendorId)
                    ->where('idempotency_key', $dto->idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $balanceBefore = $account->current_balance;
            $balanceAfter = $balanceBefore - $pointsToDeduct;

            $transaction = LoyaltyLedgerTransaction::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $dto->vendorId,
                'account_id' => $account->id,
                'customer_id' => $dto->customerId,
                'order_id' => $dto->orderId,
                'staff_id' => $dto->staffId,
                'type' => $dto->type,
                'points' => -$pointsToDeduct,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'idempotency_key' => $dto->idempotencyKey,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
            ]);

            // Consume Lots FIFO
            $remainingToConsume = $pointsToDeduct;
            $lots = LoyaltyPointLot::where('account_id', $account->id)
                ->where('is_expired', false)
                ->where('remaining_points', '>', 0)
                ->orderByRaw('expires_at ASC NULLS LAST')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remainingToConsume <= 0) {
                    break;
                }

                if ($lot->remaining_points <= $remainingToConsume) {
                    $remainingToConsume -= $lot->remaining_points;
                    $lot->update(['remaining_points' => 0]);
                } else {
                    $lot->update(['remaining_points' => $lot->remaining_points - $remainingToConsume]);
                    $remainingToConsume = 0;
                }
            }

            $account->update([
                'current_balance' => $balanceAfter,
                'lifetime_redeemed' => $account->lifetime_redeemed + $pointsToDeduct,
                'last_activity_at' => now(),
            ]);

            $customer = Customer::withoutGlobalScopes()->find($dto->customerId);
            if ($customer) {
                $customer->update(['loyalty_points' => $balanceAfter]);
                event(new PointsRedeemed($customer, $transaction));
            }

            return $transaction;
        });
    }

    /**
     * Reverse points awarded for an order (e.g. upon order refund or cancellation).
     */
    public function reverseOrderPoints(int $vendorId, int $orderId, float $refundRatio = 1.0, ?string $reason = null): ?LoyaltyLedgerTransaction
    {
        return DB::transaction(function () use ($vendorId, $orderId, $refundRatio, $reason) {
            $originalTx = LoyaltyLedgerTransaction::where('vendor_id', $vendorId)
                ->where('order_id', $orderId)
                ->where('type', LoyaltyTransactionType::Earned)
                ->first();

            if (! $originalTx || $originalTx->points <= 0) {
                return null;
            }

            $alreadyReversed = LoyaltyLedgerTransaction::where('vendor_id', $vendorId)
                ->where('order_id', $orderId)
                ->where('type', LoyaltyTransactionType::Reversal)
                ->exists();

            if ($alreadyReversed) {
                return null;
            }

            $pointsToReverse = (int) ceil($originalTx->points * min(1.0, max(0.0, $refundRatio)));
            if ($pointsToReverse <= 0) {
                return null;
            }

            $account = LoyaltyPointsAccount::where('vendor_id', $vendorId)
                ->where('customer_id', $originalTx->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = $account->current_balance;
            $balanceAfter = max(0, $balanceBefore - $pointsToReverse);

            $reversalTx = LoyaltyLedgerTransaction::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendorId,
                'account_id' => $account->id,
                'customer_id' => $originalTx->customer_id,
                'order_id' => $orderId,
                'type' => LoyaltyTransactionType::Reversal,
                'points' => -$pointsToReverse,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'order_refund',
                'reference_id' => $orderId,
                'notes' => $reason ?: "Points reversal for order #{$orderId} (".round($refundRatio * 100).'% refund)',
            ]);

            $account->update([
                'current_balance' => $balanceAfter,
                'last_activity_at' => now(),
            ]);

            $customer = Customer::withoutGlobalScopes()->find($originalTx->customer_id);
            if ($customer) {
                $customer->update(['loyalty_points' => $balanceAfter]);
            }

            return $reversalTx;
        });
    }

    /**
     * Expire points lots that have passed their expiration date.
     */
    public function expirePointLots(): int
    {
        $expiredLots = LoyaltyPointLot::where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('remaining_points', '>', 0)
            ->get();

        $expiredCount = 0;

        foreach ($expiredLots as $lot) {
            DB::transaction(function () use ($lot, &$expiredCount) {
                $lockedLot = LoyaltyPointLot::where('id', $lot->id)->lockForUpdate()->first();
                if (! $lockedLot || $lockedLot->is_expired || $lockedLot->remaining_points <= 0) {
                    return;
                }

                $account = LoyaltyPointsAccount::where('id', $lockedLot->account_id)->lockForUpdate()->first();
                if (! $account) {
                    return;
                }

                $pointsToExpire = $lockedLot->remaining_points;
                $balanceBefore = $account->current_balance;
                $balanceAfter = max(0, $balanceBefore - $pointsToExpire);

                LoyaltyLedgerTransaction::create([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $account->vendor_id,
                    'account_id' => $account->id,
                    'customer_id' => $account->customer_id,
                    'type' => LoyaltyTransactionType::Expired,
                    'points' => -$pointsToExpire,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'notes' => "Expired lot #{$lockedLot->id} ({$pointsToExpire} points)",
                ]);

                $lockedLot->update([
                    'remaining_points' => 0,
                    'is_expired' => true,
                ]);

                $account->update([
                    'current_balance' => $balanceAfter,
                    'lifetime_expired' => $account->lifetime_expired + $pointsToExpire,
                    'last_activity_at' => now(),
                ]);

                $customer = Customer::withoutGlobalScopes()->find($account->customer_id);
                if ($customer) {
                    $customer->update(['loyalty_points' => $balanceAfter]);
                }

                $expiredCount += $pointsToExpire;
            });
        }

        return $expiredCount;
    }
}
