<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\Models\LoyaltyAuditLog;
use App\Domains\Loyalty\Models\LoyaltyCashbackAccount;
use App\Domains\Loyalty\Models\LoyaltyPointsAccount;
use Illuminate\Support\Facades\DB;

class TransactionReconciliationService
{
    /**
     * Audit and reconcile all customer points accounts for a vendor.
     *
     * @return array<int, array{
     *     customer_id: int,
     *     cached_balance: int,
     *     ledger_balance: int,
     *     discrepancy: int,
     *     reconciled: bool
     * }>
     */
    public function reconcilePointsAccounts(int $vendorId, bool $fixDiscrepancies = false): array
    {
        $accounts = LoyaltyPointsAccount::where('vendor_id', $vendorId)->get();
        $discrepancies = [];

        foreach ($accounts as $account) {
            $ledgerSum = (int) DB::table('loyalty_ledger_transactions')
                ->where('vendor_id', $vendorId)
                ->where('account_id', $account->id)
                ->sum('points');

            $cached = (int) $account->current_balance;
            $diff = $ledgerSum - $cached;

            if ($diff !== 0) {
                $fixed = false;
                if ($fixDiscrepancies) {
                    $account->update(['current_balance' => max(0, $ledgerSum)]);
                    LoyaltyAuditLog::create([
                        'vendor_id' => $vendorId,
                        'customer_id' => $account->customer_id,
                        'action' => 'reconciliation_adjustment',
                        'details' => [
                            'account' => 'points',
                            'previous_balance' => $cached,
                            'corrected_balance' => $ledgerSum,
                            'discrepancy' => $diff,
                        ],
                    ]);
                    $fixed = true;
                }

                $discrepancies[] = [
                    'customer_id' => $account->customer_id,
                    'cached_balance' => $cached,
                    'ledger_balance' => $ledgerSum,
                    'discrepancy' => $diff,
                    'reconciled' => $fixed,
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Audit and reconcile cashback accounts for a vendor.
     */
    public function reconcileCashbackAccounts(int $vendorId, bool $fixDiscrepancies = false): array
    {
        $accounts = LoyaltyCashbackAccount::where('vendor_id', $vendorId)->get();
        $discrepancies = [];

        foreach ($accounts as $account) {
            $ledgerSum = (float) DB::table('loyalty_cashback_transactions')
                ->where('vendor_id', $vendorId)
                ->where('account_id', $account->id)
                ->sum('amount');

            $cached = (float) $account->current_balance;
            $diff = round($ledgerSum - $cached, 2);

            if (abs($diff) >= 0.01) {
                $fixed = false;
                if ($fixDiscrepancies) {
                    $account->update(['current_balance' => max(0.0, round($ledgerSum, 2))]);
                    LoyaltyAuditLog::create([
                        'vendor_id' => $vendorId,
                        'customer_id' => $account->customer_id,
                        'action' => 'reconciliation_adjustment',
                        'details' => [
                            'account' => 'cashback',
                            'previous_balance' => $cached,
                            'corrected_balance' => $ledgerSum,
                            'discrepancy' => $diff,
                        ],
                    ]);
                    $fixed = true;
                }

                $discrepancies[] = [
                    'customer_id' => $account->customer_id,
                    'cached_balance' => $cached,
                    'ledger_balance' => $ledgerSum,
                    'discrepancy' => $diff,
                    'reconciled' => $fixed,
                ];
            }
        }

        return $discrepancies;
    }
}
