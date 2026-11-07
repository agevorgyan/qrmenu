<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\FinancialAuditLog;
use App\Models\FinancialTransfer;
use App\Models\PosCashMovement;
use App\Models\PosDevice;
use App\Models\PosOperationLog;
use App\Models\PosShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashMovementService
{
    public function __construct(
        protected FinancialTransactionService $transactionService
    ) {}

    public function recordCashIn(PosShift $shift, float $amount, string $reason, User $user, ?PosDevice $device = null): PosCashMovement
    {
        return $this->recordCashMovement($shift, [
            'type' => 'cash_in',
            'amount' => $amount,
            'reason' => $reason,
        ], $user, $device);
    }

    public function recordCashOut(PosShift $shift, float $amount, string $reason, User $user, ?PosDevice $device = null): PosCashMovement
    {
        return $this->recordCashMovement($shift, [
            'type' => 'cash_out',
            'amount' => $amount,
            'reason' => $reason,
        ], $user, $device);
    }

    public function recordSafeDrop(PosShift $shift, float $amount, string $reason, User $user, ?PosDevice $device = null): PosCashMovement
    {
        return $this->recordCashMovement($shift, [
            'type' => 'safe_drop',
            'amount' => $amount,
            'reason' => $reason,
        ], $user, $device);
    }

    /**
     * Record a cash movement (cash-in, cash-out, safe drop, expense) during an active shift.
     *
     * @param array{
     *     type: 'cash_in'|'cash_out'|'safe_drop'|'expense',
     *     amount: float|int,
     *     reason?: string|null,
     *     notes?: string|null,
     *     destination_type?: string|null,
     *     destination_id?: int|null
     * } $data
     */
    public function recordCashMovement(
        PosShift $shift,
        array $data,
        User $user,
        ?PosDevice $device = null
    ): PosCashMovement {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'Cannot record cash movements on a closed shift.',
            ]);
        }

        if ((int) $shift->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized cash movement.');
        }

        $validTypes = ['cash_in', 'cash_out', 'safe_drop', 'expense'];
        $type = $data['type'] ?? '';
        if (! in_array($type, $validTypes, true)) {
            throw ValidationException::withMessages([
                'type' => "Invalid cash movement type '{$type}'.",
            ]);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Cash movement amount must be greater than zero.',
            ]);
        }

        // Validate withdrawal against live expected cash in drawer
        $shift->recalculateExpectedCash();
        $isWithdrawal = in_array($type, ['cash_out', 'safe_drop', 'expense'], true);

        if ($isWithdrawal && $amount > (float) $shift->expected_cash) {
            $currency = $shift->vendor->currency ?: 'AMD';
            throw ValidationException::withMessages([
                'amount' => "Withdrawal amount ({$amount} {$currency}) exceeds current cash in drawer (".number_format((float) $shift->expected_cash, 2)." {$currency}).",
            ]);
        }

        return DB::transaction(function () use ($shift, $data, $type, $amount, $user, $device) {
            $movement = PosCashMovement::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $shift->vendor_id,
                'pos_shift_id' => $shift->id,
                'pos_device_id' => $device?->id ?? $shift->pos_device_id,
                'cash_register_id' => $shift->cash_register_id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $data['reason'] ?? ucfirst(str_replace('_', ' ', $type)),
                'notes' => $data['notes'] ?? null,
                'destination_type' => $data['destination_type'] ?? ($type === 'safe_drop' ? 'safe' : null),
                'destination_id' => $data['destination_id'] ?? null,
            ]);

            // If this is a safe drop, record an asset transfer to the safe in General Ledger
            if ($type === 'safe_drop') {
                $transferNumber = 'TR-SAFE-'.now()->format('Ymd').'-'.Str::random(5);
                $transfer = FinancialTransfer::create([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $shift->vendor_id,
                    'transfer_number' => $transferNumber,
                    'from_type' => 'cash_register',
                    'from_id' => $shift->cash_register_id ?? 1,
                    'to_type' => 'safe',
                    'to_id' => 1,
                    'amount' => $amount,
                    'currency' => $shift->vendor->currency ?: 'AMD',
                    'transfer_date' => Carbon::now()->toDateString(),
                    'status' => 'completed',
                    'initiated_by' => $user->id,
                    'notes' => "Safe drop from Shift #{$shift->id}: ".($data['notes'] ?? 'Mid-shift safe drop'),
                    'idempotency_key' => "safe-drop-movement-{$movement->id}",
                ]);

                $journalEntry = $this->transactionService->recordCashTransfer($transfer, $user);

                $movement->update([
                    'transfer_id' => $transfer->id,
                    'journal_entry_id' => $journalEntry->id,
                ]);
            }

            $shift->recalculateExpectedCash();

            // Update cash register balance if bound
            if ($shift->cashRegister) {
                $shift->cashRegister->update(['current_balance' => (float) $shift->expected_cash]);
            }

            FinancialAuditLog::record(
                vendor: $shift->vendor,
                action: 'cash_movement_recorded',
                auditable: $movement,
                user: $user,
                newValues: [
                    'shift_id' => $shift->id,
                    'type' => $type,
                    'amount' => $amount,
                    'reason' => $movement->reason,
                ],
                amount: $amount,
                locationId: $shift->location_id
            );

            PosOperationLog::log(
                vendor: $shift->vendor,
                action: 'pos_cash_movement',
                user: $user,
                device: $device ?? $shift->posDevice,
                entity: $movement,
                details: [
                    'shift_id' => $shift->id,
                    'type' => $type,
                    'amount' => $amount,
                    'reason' => $movement->reason,
                ]
            );

            return $movement;
        });
    }

    /**
     * Execute an inter-register cash transfer between two branches or tills.
     */
    public function executeRegisterTransfer(
        CashRegister $fromRegister,
        CashRegister $toRegister,
        float $amount,
        User $user,
        ?string $notes = null
    ): FinancialTransfer {
        if ($fromRegister->id === $toRegister->id) {
            throw ValidationException::withMessages([
                'to_register' => 'Source and destination registers must be different.',
            ]);
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Transfer amount must be greater than zero.',
            ]);
        }

        $fromShift = $fromRegister->activeShift();
        if ($fromShift) {
            $fromShift->recalculateExpectedCash();
            if ($amount > (float) $fromShift->expected_cash) {
                throw ValidationException::withMessages([
                    'amount' => "Transfer amount ({$amount}) exceeds available cash in source register (".number_format((float) $fromShift->expected_cash, 2).').',
                ]);
            }
        } elseif ($amount > (float) $fromRegister->current_balance) {
            throw ValidationException::withMessages([
                'amount' => "Transfer amount ({$amount}) exceeds balance of source register ({$fromRegister->current_balance}).",
            ]);
        }

        return DB::transaction(function () use ($fromRegister, $toRegister, $amount, $fromShift, $user, $notes) {
            $transferNumber = 'TR-REG-'.now()->format('Ymd').'-'.Str::random(5);
            $transfer = FinancialTransfer::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $fromRegister->vendor_id,
                'transfer_number' => $transferNumber,
                'from_type' => 'cash_register',
                'from_id' => $fromRegister->id,
                'to_type' => 'cash_register',
                'to_id' => $toRegister->id,
                'amount' => $amount,
                'currency' => $fromRegister->vendor->currency ?: 'AMD',
                'transfer_date' => Carbon::now()->toDateString(),
                'status' => 'completed',
                'initiated_by' => $user->id,
                'notes' => $notes ?: "Transfer from {$fromRegister->name} to {$toRegister->name}",
                'idempotency_key' => "reg-transfer-{$fromRegister->id}-{$toRegister->id}-".Str::random(8),
            ]);

            // Post asset transfer to General Ledger
            $journalEntry = $this->transactionService->recordCashTransfer($transfer, $user);

            // Record cash out on source shift if open
            if ($fromShift) {
                PosCashMovement::create([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $fromRegister->vendor_id,
                    'pos_shift_id' => $fromShift->id,
                    'pos_device_id' => $fromShift->pos_device_id,
                    'cash_register_id' => $fromRegister->id,
                    'user_id' => $user->id,
                    'type' => 'cash_out',
                    'amount' => $amount,
                    'reason' => "Transfer Out to {$toRegister->name}",
                    'notes' => $notes,
                    'destination_type' => 'cash_register',
                    'destination_id' => $toRegister->id,
                    'transfer_id' => $transfer->id,
                    'journal_entry_id' => $journalEntry->id,
                ]);
                $fromShift->recalculateExpectedCash();
            }

            // Record cash in on destination shift if open
            $toShift = $toRegister->activeShift();
            if ($toShift) {
                PosCashMovement::create([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $toRegister->vendor_id,
                    'pos_shift_id' => $toShift->id,
                    'pos_device_id' => $toShift->pos_device_id,
                    'cash_register_id' => $toRegister->id,
                    'user_id' => $user->id,
                    'type' => 'cash_in',
                    'amount' => $amount,
                    'reason' => "Transfer In from {$fromRegister->name}",
                    'notes' => $notes,
                    'destination_type' => 'cash_register',
                    'destination_id' => $fromRegister->id,
                    'transfer_id' => $transfer->id,
                    'journal_entry_id' => $journalEntry->id,
                ]);
                $toShift->recalculateExpectedCash();
            }

            // Update register balances
            $fromRegister->decrement('current_balance', $amount);
            $toRegister->increment('current_balance', $amount);

            FinancialAuditLog::record(
                vendor: $fromRegister->vendor,
                action: 'register_transfer_executed',
                auditable: $transfer,
                user: $user,
                newValues: [
                    'from_register' => $fromRegister->code,
                    'to_register' => $toRegister->code,
                    'amount' => $amount,
                ],
                amount: $amount
            );

            return $transfer;
        });
    }
}
