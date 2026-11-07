<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\FinancialAuditLog;
use App\Models\PosDevice;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashShiftService
{
    public function __construct(
        protected FinancialTransactionService $transactionService
    ) {}

    /**
     * Get active shift for a register, location, device, or user.
     */
    public function getCurrentShift(
        Vendor $vendor,
        ?CashRegister $register = null,
        ?int $locationId = null,
        ?PosDevice $device = null,
        ?User $user = null
    ): ?PosShift {
        return PosShift::where('vendor_id', $vendor->id)
            ->where('status', 'open')
            ->when($register, fn ($q) => $q->where('cash_register_id', $register->id))
            ->when(! $register && $device, fn ($q) => $q->where('pos_device_id', $device->id))
            ->when(! $register && ! $device && $locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when(! $register && ! $device && ! $locationId && $user, fn ($q) => $q->where('user_id', $user->id))
            ->with(['user', 'cashRegister', 'posDevice', 'cashMovements'])
            ->latest('id')
            ->first();
    }

    /**
     * Open a new cash drawer shift.
     *
     * @param array{
     *     opening_float?: float|int,
     *     cash_register_id?: int|null,
     *     location_id?: int|null,
     *     opening_notes?: string|null
     * } $data
     */
    public function openShift(
        Vendor $vendor,
        array $data,
        User $user,
        ?CashRegister $register = null,
        ?PosDevice $device = null
    ): PosShift {
        if (! $user->hasPermission(Permission::FINANCE_SHIFTS_MANAGE) &&
            ! $user->hasPermission(Permission::POS_CASH_DRAWER) &&
            ! $user->hasPermission(Permission::POS_ACCESS)) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to open a cash shift.',
            ]);
        }

        // Resolve cash register
        $regId = $data['cash_register_id'] ?? $register?->id;
        $resolvedRegister = $regId ? CashRegister::where('vendor_id', $vendor->id)->find($regId) : $register;

        // Ensure only one open shift per register
        if ($resolvedRegister) {
            $existing = PosShift::where('vendor_id', $vendor->id)
                ->where('cash_register_id', $resolvedRegister->id)
                ->where('status', 'open')
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'shift' => "Register '{$resolvedRegister->name}' already has an active open shift (Shift #{$existing->id}). Please close it first.",
                ]);
            }

            if (! empty($data['location_id']) && $resolvedRegister->location_id && (int) $data['location_id'] !== (int) $resolvedRegister->location_id) {
                throw ValidationException::withMessages([
                    'location_id' => 'Register does not belong to the specified location.',
                ]);
            }
        }

        // Ensure user does not already have an active open shift
        $userActiveShift = PosShift::where('vendor_id', $vendor->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($userActiveShift) {
            throw ValidationException::withMessages([
                'user' => "User already has an active open shift (Shift #{$userActiveShift->id}). Please close it before opening another.",
            ]);
        }

        $openingFloat = max(0, (float) ($data['opening_float'] ?? 0.00));
        $locationId = $data['location_id']
            ?? $resolvedRegister?->location_id
            ?? $device?->location_id
            ?? $user->location_id
            ?? $vendor->locations->first()?->id;

        $shift = PosShift::create([
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'cash_register_id' => $resolvedRegister?->id,
            'pos_device_id' => $device?->id ?? $resolvedRegister?->pos_device_id,
            'user_id' => $user->id,
            'status' => 'open',
            'opened_at' => Carbon::now(),
            'opening_float' => $openingFloat,
            'expected_cash' => $openingFloat,
            'opening_notes' => $data['opening_notes'] ?? null,
            'variance_status' => 'balanced',
        ]);

        if ($resolvedRegister) {
            $resolvedRegister->update(['current_balance' => $openingFloat]);
        }

        FinancialAuditLog::record(
            vendor: $vendor,
            action: 'cash_shift_opened',
            auditable: $shift,
            user: $user,
            newValues: [
                'shift_id' => $shift->id,
                'register_id' => $resolvedRegister?->id,
                'opening_float' => $openingFloat,
            ],
            amount: $openingFloat,
            locationId: $shift->location_id
        );

        PosOperationLog::log(
            vendor: $vendor,
            action: 'pos_shift_open',
            user: $user,
            device: $device ?? $resolvedRegister?->posDevice,
            entity: $shift,
            details: [
                'shift_id' => $shift->id,
                'opening_float' => $openingFloat,
                'opened_at' => $shift->opened_at->toIso8601String(),
            ]
        );

        return $shift->load(['user', 'cashRegister', 'posDevice']);
    }

    /**
     * Compute comprehensive expected cash breakdown for a shift.
     */
    public function getShiftBreakdown(PosShift $shift): array
    {
        $shift->load(['cashMovements', 'payments']);

        $openingFloat = (float) $shift->opening_float;

        // Payments during shift
        $cashPayments = (float) PosPayment::where('pos_shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->sum('amount');

        $cardPayments = (float) PosPayment::where('pos_shift_id', $shift->id)
            ->where('payment_method', 'card')
            ->where('status', 'completed')
            ->sum('amount');

        $otherPayments = (float) PosPayment::where('pos_shift_id', $shift->id)
            ->whereNotIn('payment_method', ['cash', 'card'])
            ->where('status', 'completed')
            ->sum('amount');

        $totalTips = (float) PosPayment::where('pos_shift_id', $shift->id)
            ->where('status', 'completed')
            ->sum('tip_amount');

        $cashRefunds = (float) PosPayment::where('pos_shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->sum('refunded_amount');

        // Cash movements during shift
        $cashIn = (float) $shift->cashMovements()->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) $shift->cashMovements()->where('type', 'cash_out')->sum('amount');
        $safeDrops = (float) $shift->cashMovements()->where('type', 'safe_drop')->sum('amount');
        $expenses = (float) $shift->cashMovements()->where('type', 'expense')->sum('amount');

        // Formula:
        // Expected Cash = Opening Float + Cash Payments + Cash In - Cash Refunds - Cash Out - Safe Drops - Expenses
        $expectedCash = $openingFloat + $cashPayments + $cashIn - $cashRefunds - $cashOut - $safeDrops - $expenses;
        $expectedCash = max(0, round($expectedCash, 2));

        return [
            'shift_id' => $shift->id,
            'opening_float' => $openingFloat,
            'cash_sales' => $cashPayments,
            'cash_payments' => $cashPayments,
            'card_sales' => $cardPayments,
            'other_sales' => $otherPayments,
            'total_sales' => round($cashPayments + $cardPayments + $otherPayments, 2),
            'tips' => $totalTips,
            'refunds' => $cashRefunds,
            'cash_refunds' => $cashRefunds,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'safe_drops' => $safeDrops,
            'expenses' => $expenses,
            'expected_cash' => $expectedCash,
            'orders_count' => $shift->orders()->count(),
        ];
    }

    /**
     * Close an active shift with blind count reconciliation and ledger variance posting.
     */
    public function closeShift(
        PosShift $shift,
        float $countedCash,
        User $user,
        ?string $closingNotes = null
    ): PosShift {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'This shift is already closed.',
            ]);
        }

        if ((int) $shift->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized shift closing.');
        }

        $breakdown = $this->getShiftBreakdown($shift);
        $expectedCash = $breakdown['expected_cash'];
        $countedCash = max(0, round($countedCash, 2));
        $discrepancy = round($countedCash - $expectedCash, 2);

        $varianceStatus = 'balanced';
        if ($discrepancy < 0) {
            $varianceStatus = 'shortage';
        } elseif ($discrepancy > 0) {
            $varianceStatus = 'overage';
        }

        return DB::transaction(function () use ($shift, $countedCash, $expectedCash, $discrepancy, $varianceStatus, $breakdown, $user, $closingNotes) {
            $shift->update([
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'closed_by_user_id' => $user->id,
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'discrepancy' => $discrepancy,
                'variance_status' => $varianceStatus,
                'total_cash_sales' => $breakdown['cash_sales'],
                'total_card_sales' => $breakdown['card_sales'],
                'total_other_sales' => $breakdown['other_sales'],
                'total_tips' => $breakdown['tips'],
                'total_refunds' => $breakdown['refunds'],
                'total_cash_in' => $breakdown['cash_in'],
                'total_cash_out' => round($breakdown['cash_out'] + $breakdown['safe_drops'] + $breakdown['expenses'], 2),
                'orders_count' => $breakdown['orders_count'],
                'closing_notes' => $closingNotes,
            ]);

            // Update register balance to counted cash
            if ($shift->cashRegister) {
                $shift->cashRegister->update(['current_balance' => $countedCash]);
            }

            // Post discrepancy to General Ledger if non-zero
            if ($discrepancy != 0.0) {
                $this->transactionService->recordTillVariance($shift, $discrepancy, $user);
            }

            FinancialAuditLog::record(
                vendor: $shift->vendor,
                action: 'cash_shift_closed',
                auditable: $shift,
                user: $user,
                newValues: [
                    'shift_id' => $shift->id,
                    'expected_cash' => $expectedCash,
                    'counted_cash' => $countedCash,
                    'discrepancy' => $discrepancy,
                    'variance_status' => $varianceStatus,
                ],
                amount: $countedCash,
                locationId: $shift->location_id
            );

            PosOperationLog::log(
                vendor: $shift->vendor,
                action: 'pos_shift_close',
                user: $user,
                device: $shift->posDevice,
                entity: $shift,
                details: [
                    'shift_id' => $shift->id,
                    'expected_cash' => $expectedCash,
                    'counted_cash' => $countedCash,
                    'discrepancy' => $discrepancy,
                    'variance_status' => $varianceStatus,
                ]
            );

            return $shift->fresh(['user', 'closedByUser', 'cashRegister', 'cashMovements']);
        });
    }

    /**
     * Manager sign-off and approval on shift closing and variances.
     */
    public function approveShift(
        PosShift $shift,
        User $manager,
        ?string $approvalNotes = null
    ): PosShift {
        if (! $manager->hasPermission(Permission::FINANCE_SHIFTS_APPROVE) &&
            ! $manager->isVendorOwner() &&
            ! $manager->isManager()) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to approve cash shifts.',
            ]);
        }

        $shift->update([
            'manager_approved_by' => $manager->id,
            'manager_approved_at' => Carbon::now(),
            'approval_notes' => $approvalNotes,
            'variance_status' => 'approved',
        ]);

        if (abs((float) $shift->discrepancy) >= 0.01) {
            $this->transactionService->recordTillVariance($shift, (float) $shift->discrepancy, $manager);
        }

        FinancialAuditLog::record(
            vendor: $shift->vendor,
            action: 'cash_shift_approved',
            auditable: $shift,
            user: $manager,
            newValues: ['approval_notes' => $approvalNotes],
            locationId: $shift->location_id
        );

        return $shift->fresh(['user', 'managerApprovedByUser']);
    }

    public function calculateExpectedCashBreakdown(PosShift $shift): array
    {
        return $this->getShiftBreakdown($shift);
    }

    public function approveShiftVariance(PosShift $shift, User $manager, ?string $notes = null): PosShift
    {
        return $this->approveShift($shift, $manager, $notes);
    }

    /**
     * Generate complete fiscal & accounting Z-Report for a shift.
     */
    public function generateZReport(PosShift $shift): array
    {
        $breakdown = $this->getShiftBreakdown($shift);
        $reportNumber = 'Z-'.date('Ymd', strtotime($shift->closed_at ?: $shift->opened_at))."-{$shift->id}";

        return [
            'report_number' => $reportNumber,
            'z_report_number' => $reportNumber,
            'vendor_name' => $shift->vendor->name,
            'location_name' => $shift->location?->name,
            'register_code' => $shift->cashRegister?->code ?? 'DEFAULT',
            'cashier_name' => $shift->user->name,
            'opened_at' => $shift->opened_at->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'currency' => $shift->vendor->currency ?? 'AMD',
            'opening_float' => (float) $shift->opening_float,
            'cash_sales' => $breakdown['cash_sales'],
            'card_sales' => $breakdown['card_sales'],
            'other_sales' => $breakdown['other_sales'],
            'total_sales' => $breakdown['total_sales'],
            'tips' => $breakdown['tips'],
            'refunds' => $breakdown['refunds'],
            'cash_in' => $breakdown['cash_in'],
            'cash_out' => $breakdown['cash_out'],
            'safe_drops' => $breakdown['safe_drops'],
            'expenses' => $breakdown['expenses'],
            'expected_cash' => (float) $shift->expected_cash,
            'counted_cash' => (float) ($shift->counted_cash ?? 0.00),
            'discrepancy' => (float) $shift->discrepancy,
            'variance_status' => $shift->variance_status,
            'orders_count' => $breakdown['orders_count'],
            'manager_approved' => (bool) $shift->manager_approved_at,
            'approved_by' => $shift->managerApprovedByUser?->name,
            'cash_reconciliation' => [
                'opening_float' => (float) $shift->opening_float,
                'cash_payments' => $breakdown['cash_sales'],
                'cash_in' => $breakdown['cash_in'],
                'cash_out' => $breakdown['cash_out'],
                'safe_drops' => $breakdown['safe_drops'],
                'expected_cash' => (float) $shift->expected_cash,
                'actual_cash' => (float) ($shift->counted_cash ?? 0.00),
                'discrepancy' => (float) $shift->discrepancy,
            ],
            'payment_breakdown' => [
                'cash_payments' => $breakdown['cash_sales'],
                'card_payments' => $breakdown['card_sales'],
                'online_payments' => $breakdown['other_sales'],
                'total_revenue_settled' => $breakdown['total_sales'],
            ],
        ];
    }

    /**
     * Generate interim X-Report for an active/open shift audit.
     */
    public function generateXReport(PosShift $shift): array
    {
        $breakdown = $this->getShiftBreakdown($shift);
        $reportNumber = 'X-'.date('Ymd')."-{$shift->id}";

        return [
            'report_number' => $reportNumber,
            'x_report_number' => $reportNumber,
            'vendor_name' => $shift->vendor->name,
            'location_name' => $shift->location?->name,
            'register_code' => $shift->cashRegister?->code ?? 'DEFAULT',
            'cashier_name' => $shift->user->name,
            'opened_at' => $shift->opened_at->toIso8601String(),
            'current_time' => Carbon::now()->toIso8601String(),
            'currency' => $shift->vendor->currency ?? 'AMD',
            'opening_float' => (float) $shift->opening_float,
            'cash_sales' => $breakdown['cash_sales'],
            'card_sales' => $breakdown['card_sales'],
            'other_sales' => $breakdown['other_sales'],
            'total_sales' => $breakdown['total_sales'],
            'tips' => $breakdown['tips'],
            'refunds' => $breakdown['refunds'],
            'cash_in' => $breakdown['cash_in'],
            'cash_out' => $breakdown['cash_out'],
            'safe_drops' => $breakdown['safe_drops'],
            'expenses' => $breakdown['expenses'],
            'expected_cash' => (float) $breakdown['expected_cash'],
            'orders_count' => $breakdown['orders_count'],
            'cash_reconciliation' => [
                'opening_float' => (float) $shift->opening_float,
                'cash_payments' => $breakdown['cash_sales'],
                'cash_in' => $breakdown['cash_in'],
                'refunds' => $breakdown['refunds'],
                'cash_out' => $breakdown['cash_out'],
                'safe_drops' => $breakdown['safe_drops'],
                'expected_cash' => (float) $breakdown['expected_cash'],
            ],
            'payment_breakdown' => [
                'cash_payments' => $breakdown['cash_sales'],
                'card_payments' => $breakdown['card_sales'],
                'online_payments' => $breakdown['other_sales'],
                'total_revenue_settled' => $breakdown['total_sales'],
            ],
        ];
    }
}
