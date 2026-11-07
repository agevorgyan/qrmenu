<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\ExpensePayment;
use App\Models\FinancialAuditLog;
use App\Models\PosPayment;
use App\Models\SalaryPayment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankReconciliationService
{
    public function __construct(
        protected FinancialLedgerService $ledgerService
    ) {}

    /**
     * Create a new corporate bank account.
     *
     * @param array{
     *     account_name: string,
     *     bank_name: string,
     *     account_number: string,
     *     iban?: string|null,
     *     swift?: string|null,
     *     currency?: string,
     *     opening_balance?: float|int,
     *     location_id?: int|null,
     *     account_id?: int|null
     * } $data
     */
    public function createBankAccount(Vendor $vendor, array $data, User $user): BankAccount
    {
        $accountNumber = trim($data['account_number']);

        $exists = BankAccount::where('vendor_id', $vendor->id)
            ->where('account_number', $accountNumber)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'account_number' => "Bank account number '{$accountNumber}' is already registered.",
            ]);
        }

        $openingBalance = round((float) ($data['opening_balance'] ?? 0.00), 2);
        $glAccount = isset($data['account_id'])
            ? ChartOfAccount::where('vendor_id', $vendor->id)->findOrFail($data['account_id'])
            : $this->ledgerService->getAccountByCode($vendor, '2521'); // Operating Bank Account (RA 2521 / legacy 1020)

        $bankAccount = BankAccount::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'location_id' => $data['location_id'] ?? null,
            'account_name' => trim($data['account_name']),
            'bank_name' => trim($data['bank_name']),
            'account_number' => $accountNumber,
            'iban' => isset($data['iban']) ? trim($data['iban']) : null,
            'swift' => isset($data['swift']) ? trim($data['swift']) : null,
            'currency' => $data['currency'] ?? $vendor->currency ?? 'AMD',
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'account_id' => $glAccount->id,
            'is_active' => true,
        ]);

        FinancialAuditLog::record(
            vendor: $vendor,
            action: 'bank_account_created',
            auditable: $bankAccount,
            user: $user,
            newValues: [
                'bank_name' => $bankAccount->bank_name,
                'account_number' => $bankAccount->account_number,
                'opening_balance' => $openingBalance,
            ],
            amount: $openingBalance,
            locationId: $bankAccount->location_id
        );

        return $bankAccount->load(['account', 'location']);
    }

    /**
     * Import bank statement transactions and auto-match against system records.
     *
     * @param array<int, array{
     *     transaction_date: string,
     *     value_date?: string|null,
     *     type: 'credit'|'debit',
     *     amount: float|int,
     *     reference_number?: string|null,
     *     counterparty_name?: string|null,
     *     counterparty_account?: string|null,
     *     description?: string|null
     * }> $transactions
     * @return array{imported_count: int, matched_count: int, skipped_count: int}
     */
    public function importTransactions(BankAccount $account, array $transactions, User $user): array
    {
        $vendor = $account->vendor;
        $importedCount = 0;
        $matchedCount = 0;
        $skippedCount = 0;

        foreach ($transactions as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $type = strtolower($line['type'] ?? 'credit');
            $ref = isset($line['reference_number']) ? trim($line['reference_number']) : null;
            $txDate = Carbon::parse($line['transaction_date'] ?? now());

            // Duplicate detection: check if exact transaction line already imported
            $exists = BankTransaction::where('bank_account_id', $account->id)
                ->where('transaction_date', $txDate->toDateString())
                ->where('amount', $amount)
                ->where('type', $type)
                ->when($ref, fn ($q) => $q->where('reference_number', $ref))
                ->exists();

            if ($exists) {
                $skippedCount++;

                continue;
            }

            $tx = BankTransaction::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'bank_account_id' => $account->id,
                'transaction_date' => $txDate->toDateString(),
                'value_date' => isset($line['value_date']) ? Carbon::parse($line['value_date'])->toDateString() : null,
                'type' => $type,
                'amount' => $amount,
                'currency' => $account->currency,
                'reference_number' => $ref,
                'counterparty_name' => $line['counterparty_name'] ?? null,
                'counterparty_account' => $line['counterparty_account'] ?? null,
                'description' => $line['description'] ?? null,
                'reconciliation_status' => 'unreconciled',
            ]);

            $importedCount++;

            // Attempt automated rule matching
            $matched = $this->attemptAutoMatch($tx, $user);
            if ($matched) {
                $matchedCount++;
            }
        }

        FinancialAuditLog::record(
            vendor: $vendor,
            action: 'bank_statement_imported',
            auditable: $account,
            user: $user,
            newValues: [
                'imported_count' => $importedCount,
                'matched_count' => $matchedCount,
                'skipped_count' => $skippedCount,
            ]
        );

        return [
            'imported_count' => $importedCount,
            'matched_count' => $matchedCount,
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * Automated matching engine for bank statement lines.
     */
    protected function attemptAutoMatch(BankTransaction $tx, User $user): bool
    {
        $amount = (float) $tx->amount;
        $vendorId = $tx->vendor_id;

        if ($tx->type === 'debit') {
            // Outgoing debit: match against SupplierPayment, SalaryPayment, or ExpensePayment
            if ($tx->reference_number) {
                // Match supplier payment by reference
                $sp = SupplierPayment::where('vendor_id', $vendorId)
                    ->where('amount', $amount)
                    ->where('reference_number', $tx->reference_number)
                    ->first();

                if ($sp) {
                    $this->matchTransaction($tx, 'SupplierPayment', $sp->id, $user);

                    return true;
                }

                // Match expense payment
                $ep = ExpensePayment::where('vendor_id', $vendorId)
                    ->where('amount', $amount)
                    ->where('reference_number', $tx->reference_number)
                    ->first();

                if ($ep) {
                    $this->matchTransaction($tx, 'ExpensePayment', $ep->id, $user);

                    return true;
                }
            }
        } else {
            // Incoming credit: match against PosPayment batch or Online Payment
            if ($tx->reference_number) {
                $pos = PosPayment::where('vendor_id', $vendorId)
                    ->where('amount', $amount)
                    ->where('transaction_reference', $tx->reference_number)
                    ->first();

                if ($pos) {
                    $this->matchTransaction($tx, 'PosPayment', $pos->id, $user);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Link bank transaction line to system settlement record.
     */
    public function matchTransaction(
        BankTransaction $tx,
        string $sourceType,
        int $sourceId,
        User $user
    ): BankTransaction {
        $tx->update([
            'reconciliation_status' => 'matched',
            'matched_source_type' => $sourceType,
            'matched_source_id' => $sourceId,
            'matched_at' => Carbon::now(),
            'matched_by' => $user->id,
        ]);

        return $tx;
    }

    /**
     * Complete bank reconciliation statement.
     *
     * @param array{
     *     statement_date: string,
     *     statement_ending_balance: float|int,
     *     notes?: string|null
     * } $data
     */
    public function performReconciliation(
        BankAccount $account,
        array $data,
        User $user
    ): BankReconciliation {
        $vendor = $account->vendor;
        $statementDate = Carbon::parse($data['statement_date'] ?? now());
        $statementEndingBalance = round((float) $data['statement_ending_balance'], 2);

        // Recalculate GL balance for the account
        $account->account->recalculateBalance();
        $glBookBalance = (float) $account->account->current_balance;

        $difference = round($statementEndingBalance - $glBookBalance, 2);

        return DB::transaction(function () use ($account, $vendor, $statementDate, $statementEndingBalance, $glBookBalance, $difference, $data, $user) {
            $year = $statementDate->format('Y');
            $reconciliationNumber = "BR-{$year}-".Str::random(6);

            $reconciliation = BankReconciliation::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'bank_account_id' => $account->id,
                'reconciliation_number' => $reconciliationNumber,
                'statement_date' => $statementDate->toDateString(),
                'statement_ending_balance' => $statementEndingBalance,
                'gl_book_balance' => $glBookBalance,
                'difference' => $difference,
                'status' => 'completed',
                'reconciled_by' => $user->id,
                'reconciled_at' => Carbon::now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Update bank account current balance
            $account->update(['current_balance' => $statementEndingBalance]);

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'bank_reconciliation_completed',
                auditable: $reconciliation,
                user: $user,
                newValues: [
                    'statement_ending_balance' => $statementEndingBalance,
                    'gl_book_balance' => $glBookBalance,
                    'difference' => $difference,
                ],
                amount: $statementEndingBalance,
                locationId: $account->location_id
            );

            return $reconciliation;
        });
    }
}
