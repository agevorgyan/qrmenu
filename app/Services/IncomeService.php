<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\FinancialAuditLog;
use App\Models\FinancialCategory;
use App\Models\Income;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IncomeService
{
    public function __construct(
        protected FinancialLedgerService $ledgerService
    ) {}

    /**
     * Generate the next sequential income number: INC-YYYY-XXXXX
     */
    public function generateIncomeNumber(Vendor $vendor, ?Carbon $date = null): string
    {
        $year = ($date ?: now())->format('Y');
        $prefix = "INC-{$year}-";

        $latest = Income::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('income_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('income_number');

        $nextSeq = 1;
        if ($latest && preg_match('/INC-\d{4}-(\d+)/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('INC-%s-%05d', $year, $nextSeq);
    }

    /**
     * Record non-menu operational income.
     *
     * @param array{
     *     category_id: int,
     *     account_id?: int|null,
     *     location_id?: int|null,
     *     title: string,
     *     description?: string|null,
     *     amount: float|int,
     *     currency?: string,
     *     income_date?: string,
     *     payment_method: string,
     *     cash_register_id?: int|null,
     *     bank_account_id?: int|null,
     *     idempotency_key?: string|null
     * } $data
     */
    public function recordIncome(Vendor $vendor, array $data, User $user): Income
    {
        $categoryId = $data['financial_category_id'] ?? $data['category_id'] ?? null;
        $category = $categoryId ? FinancialCategory::where('vendor_id', $vendor->id)->find($categoryId) : null;
        $amount = round((float) ($data['total_amount'] ?? $data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Income amount must be greater than zero.',
            ]);
        }

        $incomeDate = isset($data['income_date']) ? Carbon::parse($data['income_date']) : Carbon::now();
        $accountId = $data['account_id'] ?? $category?->account_id;
        $method = strtolower($data['payment_method'] ?? 'cash');

        return DB::transaction(function () use ($vendor, $data, $category, $accountId, $amount, $incomeDate, $method, $user) {
            $incomeNumber = $this->generateIncomeNumber($vendor, $incomeDate);
            $idempotencyKey = $data['idempotency_key'] ?? "inc-{$incomeNumber}";

            $income = Income::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'location_id' => $data['location_id'] ?? null,
                'category_id' => $category?->id,
                'account_id' => $accountId,
                'income_number' => $incomeNumber,
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'currency' => $data['currency'] ?? $vendor->currency ?? 'AMD',
                'income_date' => $incomeDate->toDateString(),
                'status' => 'received',
                'payment_method' => $method,
                'cash_register_id' => $data['cash_register_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'received_at' => Carbon::now(),
                'created_by' => $user->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Resolve receiving asset account
            if ($method === 'cash') {
                $assetAccount = $this->ledgerService->getAccountByCode($vendor, '1010');
            } else {
                $assetAccount = $income->bankAccount?->account ?: $this->ledgerService->getAccountByCode($vendor, '1020');
            }

            // Post to General Ledger
            // Debit: Cash/Bank Asset, Credit: Income Revenue
            $lines = [
                [
                    'account_id' => $assetAccount->id,
                    'location_id' => $income->location_id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'currency' => $income->currency,
                    'memo' => "Received: {$income->title} (#{$income->income_number})",
                ],
                [
                    'account_id' => $income->account_id,
                    'location_id' => $income->location_id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'currency' => $income->currency,
                    'memo' => "Revenue: {$income->title}",
                ],
            ];

            $entry = $this->ledgerService->postJournalEntry($vendor, [
                'entry_date' => $incomeDate->toDateString(),
                'source_type' => 'Income',
                'source_id' => $income->id,
                'location_id' => $income->location_id,
                'idempotency_key' => $idempotencyKey,
                'description' => "Operational Income: {$income->title}",
                'lines' => $lines,
            ], $user);

            $income->update(['journal_entry_id' => $entry->id]);

            // Increment register balance if cash
            if ($method === 'cash' && $income->cash_register_id) {
                $register = CashRegister::find($income->cash_register_id);
                if ($register) {
                    $register->increment('current_balance', $amount);
                    $shift = $register->activeShift;
                    if ($shift) {
                        $shift->recalculateExpectedCash();
                    }
                }
            } elseif ($income->bank_account_id) {
                $bank = BankAccount::find($income->bank_account_id);
                $bank?->increment('current_balance', $amount);
            }

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'income_recorded',
                auditable: $income,
                user: $user,
                newValues: [
                    'income_number' => $income->income_number,
                    'amount' => $amount,
                    'method' => $method,
                ],
                amount: $amount,
                locationId: $income->location_id
            );

            return $income->fresh(['category', 'account', 'cashRegister', 'bankAccount', 'createdByUser']);
        });
    }

    /**
     * Cancel an operational income and reverse its ledger entry.
     */
    public function cancelIncome(Income $income, User $user, string $reason): Income
    {
        if ($income->status === 'cancelled') {
            throw ValidationException::withMessages([
                'income' => 'This income has already been cancelled.',
            ]);
        }

        return DB::transaction(function () use ($income, $user, $reason) {
            // Reverse journal entry if posted
            if ($income->journalEntry) {
                $this->ledgerService->createReversalEntry($income->journalEntry, $reason, $user);
            }

            // Deduct from register if was cash
            if ($income->payment_method === 'cash' && $income->cash_register_id) {
                $register = CashRegister::find($income->cash_register_id);
                if ($register) {
                    $register->decrement('current_balance', (float) $income->amount);
                    $shift = $register->activeShift();
                    if ($shift) {
                        $shift->recalculateExpectedCash();
                    }
                }
            }

            $income->update([
                'status' => 'cancelled',
                'description' => ($income->description ? $income->description."\n" : '')."Cancelled: {$reason}",
            ]);

            FinancialAuditLog::record(
                vendor: $income->vendor,
                action: 'income_cancelled',
                auditable: $income,
                user: $user,
                newValues: ['status' => 'cancelled', 'reason' => $reason],
                amount: (float) $income->amount,
                locationId: $income->location_id
            );

            return $income->fresh();
        });
    }
}
