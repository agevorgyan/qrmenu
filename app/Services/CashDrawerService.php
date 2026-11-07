<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\FinancialAuditLog;
use App\Models\PosOperationLog;
use App\Models\User;

class CashDrawerService
{
    /**
     * Trigger a physical or virtual cash drawer kick (Open Drawer).
     */
    public function kickDrawer(CashRegister $register, User $user, string $reason = 'Manual Drawer Open'): array
    {
        FinancialAuditLog::record(
            vendor: $register->vendor,
            action: 'cash_drawer_kick',
            auditable: $register,
            user: $user,
            newValues: ['reason' => $reason, 'register_code' => $register->code],
            locationId: $register->location_id
        );

        PosOperationLog::log(
            vendor: $register->vendor,
            action: 'cash_drawer_open',
            user: $user,
            device: $register->posDevice,
            entity: $register,
            details: [
                'register_id' => $register->id,
                'register_code' => $register->code,
                'reason' => $reason,
            ]
        );

        return [
            'success' => true,
            'register_id' => $register->id,
            'register_code' => $register->code,
            'opened_at' => now()->toIso8601String(),
            'status' => 'drawer_kicked',
        ];
    }

    /**
     * Inspect current drawer status and cash breakdown.
     */
    public function getDrawerStatus(CashRegister $register): array
    {
        $activeShift = $register->activeShift();
        if ($activeShift) {
            $activeShift->recalculateExpectedCash();
        }

        return [
            'register_id' => $register->id,
            'register_name' => $register->name,
            'register_code' => $register->code,
            'has_active_shift' => (bool) $activeShift,
            'active_shift_id' => $activeShift?->id,
            'current_cash_in_drawer' => $activeShift ? (float) $activeShift->expected_cash : (float) $register->current_balance,
            'opening_float' => $activeShift ? (float) $activeShift->opening_float : 0.00,
            'total_cash_sales' => $activeShift ? (float) $activeShift->total_cash_sales : 0.00,
            'total_card_sales' => $activeShift ? (float) $activeShift->total_card_sales : 0.00,
            'cash_in' => $activeShift ? (float) $activeShift->total_cash_in : 0.00,
            'cash_out' => $activeShift ? (float) $activeShift->total_cash_out : 0.00,
        ];
    }
}
