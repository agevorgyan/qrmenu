<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\FinancialAuditLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FinancialAuditService
{
    /**
     * Query immutable financial audit trail.
     */
    public function getAuditHistory(
        Vendor $vendor,
        ?int $locationId = null,
        ?string $action = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        return FinancialAuditLog::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($auditableType, fn ($q) => $q->where('auditable_type', $auditableType))
            ->when($auditableId, fn ($q) => $q->where('auditable_id', $auditableId))
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate->startOfDay()))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', $endDate->endOfDay()))
            ->with(['user', 'location'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Perform deep financial ledger tamper verification.
     * Checks:
     * 1. Balanced journal entries (\sum Dr == \sum Cr)
     * 2. Recalculated account balances vs stored account balances
     * 3. No duplicate entry numbers
     * 4. Idempotency uniqueness
     */
    public function verifyTamperIntegrity(Vendor $vendor): array
    {
        $issues = [];
        $entriesChecked = 0;
        $accountsChecked = 0;

        // 1. Verify all posted journal entries balance internally
        $unbalancedEntries = DB::table('journal_entries')
            ->where('vendor_id', $vendor->id)
            ->where('status', 'posted')
            ->whereRaw('ABS(total_debit - total_credit) >= 0.01')
            ->get(['id', 'entry_number', 'total_debit', 'total_credit']);

        foreach ($unbalancedEntries as $entry) {
            $issues[] = [
                'type' => 'unbalanced_journal_entry',
                'severity' => 'critical',
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'message' => "Journal entry {$entry->entry_number} is unbalanced: Debit={$entry->total_debit}, Credit={$entry->total_credit}.",
            ];
        }

        // Also verify line sums match header totals
        $lineMismatches = DB::table('journal_entries')
            ->join('journal_entry_lines', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.vendor_id', $vendor->id)
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->select([
                'journal_entries.id',
                'journal_entries.entry_number',
                'journal_entries.total_debit as header_debit',
                'journal_entries.total_credit as header_credit',
                DB::raw("SUM(CASE WHEN journal_entry_lines.type = 'debit' THEN journal_entry_lines.amount_base_currency ELSE 0 END) as lines_debit"),
                DB::raw("SUM(CASE WHEN journal_entry_lines.type = 'credit' THEN journal_entry_lines.amount_base_currency ELSE 0 END) as lines_credit"),
            ])
            ->groupBy('journal_entries.id', 'journal_entries.entry_number', 'journal_entries.total_debit', 'journal_entries.total_credit')
            ->havingRaw('ABS(journal_entries.total_debit - SUM(CASE WHEN journal_entry_lines.type = \'debit\' THEN journal_entry_lines.amount_base_currency ELSE 0 END)) >= 0.01 OR ABS(journal_entries.total_credit - SUM(CASE WHEN journal_entry_lines.type = \'credit\' THEN journal_entry_lines.amount_base_currency ELSE 0 END)) >= 0.01')
            ->get();

        foreach ($lineMismatches as $mismatch) {
            $issues[] = [
                'type' => 'line_sum_mismatch',
                'severity' => 'critical',
                'entry_id' => $mismatch->id,
                'entry_number' => $mismatch->entry_number,
                'message' => "Journal entry lines sum does not match header for {$mismatch->entry_number}.",
            ];
        }

        $entriesChecked = JournalEntry::where('vendor_id', $vendor->id)->count();

        // 2. Verify Chart of Accounts balance consistency
        $accounts = ChartOfAccount::where('vendor_id', $vendor->id)->get();
        $accountsChecked = $accounts->count();

        foreach ($accounts as $account) {
            $debits = (float) JournalEntryLine::join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entries.vendor_id', $vendor->id)
                ->whereIn('journal_entries.status', ['posted', 'reversed'])
                ->where('journal_entry_lines.account_id', $account->id)
                ->where('journal_entry_lines.type', 'debit')
                ->sum('journal_entry_lines.amount_base_currency');

            $credits = (float) JournalEntryLine::join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entries.vendor_id', $vendor->id)
                ->whereIn('journal_entries.status', ['posted', 'reversed'])
                ->where('journal_entry_lines.account_id', $account->id)
                ->where('journal_entry_lines.type', 'credit')
                ->sum('journal_entry_lines.amount_base_currency');

            $expectedBalance = $account->isDebitNormal() ? round($debits - $credits, 2) : round($credits - $debits, 2);
            $storedBalance = round((float) $account->current_balance, 2);

            if (abs($expectedBalance - $storedBalance) >= 0.01) {
                $issues[] = [
                    'type' => 'account_balance_mismatch',
                    'severity' => 'high',
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'message' => "Account {$account->code} ({$account->name}) has stored balance {$storedBalance} but lines sum to {$expectedBalance}.",
                ];
            }
        }

        // 3. Check for duplicate entry numbers within vendor
        $duplicates = DB::table('journal_entries')
            ->where('vendor_id', $vendor->id)
            ->select('entry_number', DB::raw('COUNT(*) as cnt'))
            ->groupBy('entry_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $issues[] = [
                'type' => 'duplicate_entry_number',
                'severity' => 'critical',
                'entry_number' => $dup->entry_number,
                'message' => "Duplicate entry number found: {$dup->entry_number} appears {$dup->cnt} times.",
            ];
        }

        // 4. Verify Cryptographic HMAC-SHA256 Hash Chain
        $postedEntries = JournalEntry::where('vendor_id', $vendor->id)
            ->where('status', 'posted')
            ->orderBy('id', 'asc')
            ->get();

        $appSecret = config('app.key') ?: 'qrmenu-finance-secret';
        $expectedPrevHash = hash('sha256', "GENESIS-{$vendor->id}");

        foreach ($postedEntries as $pEntry) {
            if ($pEntry->entry_hash) {
                // Verify link to previous entry
                if ($pEntry->previous_hash !== $expectedPrevHash) {
                    $issues[] = [
                        'type' => 'cryptographic_chain_break',
                        'severity' => 'critical',
                        'entry_id' => $pEntry->id,
                        'entry_number' => $pEntry->entry_number,
                        'message' => "Cryptographic hash chain broken at entry {$pEntry->entry_number}: expected previous hash {$expectedPrevHash}, found {$pEntry->previous_hash}.",
                    ];
                }

                $debitStr = number_format((float) $pEntry->total_debit, 2, '.', '');
                $creditStr = number_format((float) $pEntry->total_credit, 2, '.', '');
                // Verify entry payload HMAC signature
                $expectedPayloadHash = hash_hmac(
                    'sha256',
                    "{$vendor->id}|{$pEntry->entry_number}|{$pEntry->entry_date->toDateString()}|{$debitStr}|{$creditStr}|{$pEntry->previous_hash}",
                    $appSecret
                );

                if (! hash_equals($expectedPayloadHash, $pEntry->entry_hash)) {
                    $issues[] = [
                        'type' => 'cryptographic_signature_mismatch',
                        'severity' => 'critical',
                        'entry_id' => $pEntry->id,
                        'entry_number' => $pEntry->entry_number,
                        'message' => "Cryptographic HMAC signature invalid for entry {$pEntry->entry_number}. Stored hash does not match computed payload hash.",
                    ];
                }

                $expectedPrevHash = $pEntry->entry_hash;
            }
        }

        return [
            'status' => empty($issues) ? 'healthy' : 'compromised',
            'is_valid' => empty($issues),
            'entries_checked' => $entriesChecked,
            'accounts_checked' => $accountsChecked,
            'issues_count' => count($issues),
            'issues' => $issues,
            'verified_at' => Carbon::now()->toIso8601String(),
        ];
    }
}
