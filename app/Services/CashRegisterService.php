<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\ChartOfAccount;
use App\Models\FinancialAuditLog;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    public function __construct(
        protected FinancialLedgerService $ledgerService
    ) {}

    /**
     * Create a new cash register for a branch.
     *
     * @param array{
     *     location_id: int,
     *     name: string,
     *     code: string,
     *     pos_device_id?: int|null,
     *     assigned_user_id?: int|null,
     *     account_id?: int|null
     * } $data
     */
    public function createRegister(Vendor $vendor, array $data, ?User $creator = null): CashRegister
    {
        $locationId = (int) $data['location_id'];
        $location = Location::where('vendor_id', $vendor->id)->findOrFail($locationId);

        $code = strtoupper(trim($data['code']));

        // Check unique code per location
        $exists = CashRegister::where('vendor_id', $vendor->id)
            ->where('location_id', $location->id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => "Register code '{$code}' already exists for branch {$location->name}.",
            ]);
        }

        // Resolve GL Cash Account (default 1010)
        $account = isset($data['account_id'])
            ? ChartOfAccount::where('vendor_id', $vendor->id)->findOrFail($data['account_id'])
            : $this->ledgerService->getAccountByCode($vendor, '1010');

        $register = CashRegister::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'name' => trim($data['name']),
            'code' => $code,
            'pos_device_id' => $data['pos_device_id'] ?? null,
            'account_id' => $account->id,
            'status' => 'active',
            'current_balance' => 0.00,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
        ]);

        FinancialAuditLog::record(
            vendor: $vendor,
            action: 'cash_register_created',
            auditable: $register,
            user: $creator,
            newValues: ['name' => $register->name, 'code' => $register->code],
            locationId: $register->location_id
        );

        return $register->load(['location', 'posDevice', 'account', 'assignedUser']);
    }

    /**
     * Update cash register configuration.
     */
    public function updateRegister(CashRegister $register, array $data, ?User $updater = null): CashRegister
    {
        $oldValues = $register->only(['name', 'code', 'status', 'assigned_user_id', 'pos_device_id']);

        if (isset($data['code']) && $data['code'] !== $register->code) {
            $code = strtoupper(trim($data['code']));
            $exists = CashRegister::where('vendor_id', $register->vendor_id)
                ->where('location_id', $register->location_id)
                ->where('code', $code)
                ->where('id', '!=', $register->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'code' => "Register code '{$code}' already exists for this branch.",
                ]);
            }
            $register->code = $code;
        }

        if (isset($data['name'])) {
            $register->name = trim($data['name']);
        }

        if (isset($data['pos_device_id'])) {
            $register->pos_device_id = $data['pos_device_id'] ?: null;
        }

        if (array_key_exists('assigned_user_id', $data)) {
            $register->assigned_user_id = $data['assigned_user_id'] ?: null;
        }

        if (isset($data['status'])) {
            $register->status = $data['status'];
        }

        $register->save();

        FinancialAuditLog::record(
            vendor: $register->vendor,
            action: 'cash_register_updated',
            auditable: $register,
            user: $updater,
            oldValues: $oldValues,
            newValues: $register->only(['name', 'code', 'status', 'assigned_user_id', 'pos_device_id']),
            locationId: $register->location_id
        );

        return $register->fresh(['location', 'posDevice', 'account', 'assignedUser']);
    }

    /**
     * Get all active registers for a branch.
     *
     * @return Collection<int, CashRegister>
     */
    public function getRegistersForBranch(Vendor $vendor, int $locationId): Collection
    {
        return CashRegister::where('vendor_id', $vendor->id)
            ->where('location_id', $locationId)
            ->with(['posDevice', 'assignedUser', 'shifts' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('code')
            ->get();
    }

    /**
     * Compute current physical cash balance in register.
     */
    public function calculateRegisterBalance(CashRegister $register): float
    {
        $activeShift = $register->activeShift();
        if ($activeShift) {
            $activeShift->recalculateExpectedCash();
            $balance = (float) $activeShift->expected_cash;
        } else {
            // Last closed shift ending balance or zero
            $lastClosed = $register->shifts()->where('status', 'closed')->latest('closed_at')->first();
            $balance = $lastClosed ? (float) $lastClosed->counted_cash : 0.00;
        }

        $register->update(['current_balance' => round($balance, 2)]);

        return (float) $register->current_balance;
    }
}
