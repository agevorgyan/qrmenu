<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FinancialAuditLog;
use App\Models\FinancialCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialLedgerService
{
    /**
     * Standard default Chart of Accounts template.
     *
     * @var array<int, array{code: string, name: string, type: string, subtype: string, desc: string}>
     */
    public const DEFAULT_ACCOUNTS = [
        ['code' => '1010', 'name' => 'Cash in Registers (Petty Cash/Till)', 'type' => 'asset', 'subtype' => 'cash', 'desc' => 'Physical cash notes and coins in registers and cash drawers.'],
        ['code' => '1015', 'name' => 'Cash in Safe / Vault', 'type' => 'asset', 'subtype' => 'cash', 'desc' => 'Physical cash stored in main restaurant vault/safe.'],
        ['code' => '1020', 'name' => 'Operating Bank Account', 'type' => 'asset', 'subtype' => 'bank', 'desc' => 'Primary commercial bank checking account.'],
        ['code' => '1030', 'name' => 'Card Clearing / Undeposited Funds', 'type' => 'asset', 'subtype' => 'clearing', 'desc' => 'POS credit/debit card settlements awaiting bank deposit.'],
        ['code' => '1040', 'name' => 'Online Gateway Clearing', 'type' => 'asset', 'subtype' => 'clearing', 'desc' => 'Stripe, Idram, Telcell, and online gateway settlements in transit.'],
        ['code' => '1200', 'name' => 'Accounts Receivable (Customer Tabs)', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'desc' => 'Customer unsettled dining tabs and corporate billing receivables.'],
        ['code' => '1300', 'name' => 'Food & Beverage Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'desc' => 'Current inventory asset value for ingredients, beverages, and packaging.'],
        ['code' => '1400', 'name' => 'Employee Advances & Loans', 'type' => 'asset', 'subtype' => 'receivables', 'desc' => 'Salary advances disbursed to employees awaiting payroll deduction.'],
        ['code' => '2010', 'name' => 'Accounts Payable (Suppliers)', 'type' => 'liability', 'subtype' => 'accounts_payable', 'desc' => 'Unpaid procurement bills and supplier invoices.'],
        ['code' => '2100', 'name' => 'Tips Payable (Employee Pool)', 'type' => 'liability', 'subtype' => 'tips_payable', 'desc' => 'Collected customer tips awaiting distribution to service staff.'],
        ['code' => '2200', 'name' => 'Net Salaries Payable', 'type' => 'liability', 'subtype' => 'payroll_payable', 'desc' => 'Net payroll obligations approved and owed to employees.'],
        ['code' => '2300', 'name' => 'Sales Tax / VAT Payable (Armenia SRC)', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'Collected VAT and sales taxes owed to the state tax service.'],
        ['code' => '2350', 'name' => 'Payroll Taxes & Pension Payable', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'Withheld income tax, social security, pension, and stamp fees.'],
        ['code' => '3010', 'name' => 'Owner Capital & Equity', 'type' => 'equity', 'subtype' => 'equity', 'desc' => 'Owner contributed capital and initial investment.'],
        ['code' => '3020', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'desc' => 'Accumulated net profit/loss carried forward from prior periods.'],
        ['code' => '4010', 'name' => 'Food Sales Revenue', 'type' => 'revenue', 'subtype' => 'sales_food', 'desc' => 'Gross sales revenue derived from kitchen and food orders.'],
        ['code' => '4020', 'name' => 'Beverage Sales Revenue', 'type' => 'revenue', 'subtype' => 'sales_beverage', 'desc' => 'Gross sales revenue derived from bar and beverage orders.'],
        ['code' => '4030', 'name' => 'Service Charge Revenue', 'type' => 'revenue', 'subtype' => 'sales_service', 'desc' => 'Mandatory restaurant service charges billed to customers.'],
        ['code' => '4100', 'name' => 'Other Operational Income', 'type' => 'revenue', 'subtype' => 'other_income', 'desc' => 'Ancillary revenue from hall rental, delivery fees, and catering.'],
        ['code' => '4900', 'name' => 'Sales Returns & Allowances', 'type' => 'revenue', 'subtype' => 'contra_revenue', 'desc' => 'Contra-revenue account tracking customer refunds and returns.'],
        ['code' => '4910', 'name' => 'Promotional Discounts Allowed', 'type' => 'revenue', 'subtype' => 'contra_revenue', 'desc' => 'Contra-revenue account tracking menu discounts and promo codes.'],
        ['code' => '5010', 'name' => 'Cost of Goods Sold (Food Cost)', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold', 'desc' => 'Direct cost of ingredients consumed in prepared food dishes.'],
        ['code' => '5020', 'name' => 'Cost of Goods Sold (Beverage Cost)', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold', 'desc' => 'Direct cost of alcohol and beverages consumed.'],
        ['code' => '5100', 'name' => 'Wages & Salaries Expense', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Gross base pay, overtime, night hours, and bonuses for staff.'],
        ['code' => '5150', 'name' => 'Employer Social & Statutory Contributions', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Mandatory employer-paid social and statutory fees.'],
        ['code' => '5200', 'name' => 'Rent & Facility Expense', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Physical property leases and dining room rent.'],
        ['code' => '5300', 'name' => 'Utilities (Gas, Electric, Water)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Electricity, natural gas, municipal water, and sanitation.'],
        ['code' => '5400', 'name' => 'Repairs & Kitchen Maintenance', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Service equipment repairs, plumbing, HVAC, and refrigeration.'],
        ['code' => '5500', 'name' => 'General Operating & Cleaning Supplies', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Consumable supplies, napkins, chemicals, and cleaning.'],
        ['code' => '5600', 'name' => 'Marketing & Advertising', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Promotions, social media ads, food photography, and printing.'],
        ['code' => '5700', 'name' => 'Bank Fees & Payment Processing', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Card terminal acquirer fees, gateway charges, and bank fees.'],
        ['code' => '5800', 'name' => 'Cash Over / Short (Till Discrepancy)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Physical till count variance recorded at shift close.'],
    ];

    /**
     * Default Financial Categories template.
     */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Food & Beverage Supplies', 'code' => 'F_B_SUPPLIES', 'type' => 'expense', 'account_code' => '5010', 'color' => '#10b981', 'icon' => 'fa-utensils'],
        ['name' => 'Kitchen Equipment & Repairs', 'code' => 'MAINTENANCE', 'type' => 'expense', 'account_code' => '5400', 'color' => '#f59e0b', 'icon' => 'fa-wrench'],
        ['name' => 'Utilities & Internet', 'code' => 'UTILITIES', 'type' => 'expense', 'account_code' => '5300', 'color' => '#06b6d4', 'icon' => 'fa-bolt'],
        ['name' => 'Cleaning & Sanitation Supplies', 'code' => 'CLEANING', 'type' => 'expense', 'account_code' => '5500', 'color' => '#3b82f6', 'icon' => 'fa-soap'],
        ['name' => 'Rent & Lease Payments', 'code' => 'RENT', 'type' => 'expense', 'account_code' => '5200', 'color' => '#8b5cf6', 'icon' => 'fa-building'],
        ['name' => 'Marketing & Promotions', 'code' => 'MARKETING', 'type' => 'expense', 'account_code' => '5600', 'color' => '#ec4899', 'icon' => 'fa-bullhorn'],
        ['name' => 'Bank & Merchant Fees', 'code' => 'BANK_FEES', 'type' => 'expense', 'account_code' => '5700', 'color' => '#64748b', 'icon' => 'fa-credit-card'],
        ['name' => 'Event Hall Rental Income', 'code' => 'HALL_RENTAL', 'type' => 'income', 'account_code' => '4100', 'color' => '#10b981', 'icon' => 'fa-champagne-glasses'],
        ['name' => 'Catering & Delivery Surcharge', 'code' => 'CATERING', 'type' => 'income', 'account_code' => '4100', 'color' => '#06b6d4', 'icon' => 'fa-truck'],
    ];

    /**
     * Ensure the tenant has a complete standard Chart of Accounts.
     */
    public function initializeDefaultAccounts(Vendor $vendor): void
    {
        $currency = $vendor->currency ?: 'AMD';

        foreach (self::DEFAULT_ACCOUNTS as $acc) {
            ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'code' => $acc['code'],
                ],
                [
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'subtype' => $acc['subtype'],
                    'currency' => $currency,
                    'current_balance' => 0.00,
                    'is_system' => true,
                    'is_active' => true,
                    'description' => $acc['desc'],
                ]
            );
        }

        // Initialize default categories
        foreach (self::DEFAULT_CATEGORIES as $cat) {
            $account = ChartOfAccount::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('code', $cat['account_code'])
                ->first();

            if ($account) {
                FinancialCategory::withoutGlobalScopes()->firstOrCreate(
                    [
                        'vendor_id' => $vendor->id,
                        'code' => $cat['code'],
                    ],
                    [
                        'name' => $cat['name'],
                        'type' => $cat['type'],
                        'account_id' => $account->id,
                        'color' => $cat['color'],
                        'icon' => $cat['icon'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Get account by standard code, auto-initializing if missing.
     */
    public function getAccountByCode(Vendor $vendor, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::where('vendor_id', $vendor->id)->where('code', $code)->first();

        if (! $account) {
            $this->initializeDefaultAccounts($vendor);
            $account = ChartOfAccount::where('vendor_id', $vendor->id)->where('code', $code)->firstOrFail();
        }

        return $account;
    }

    /**
     * Generate the next sequential entry number for a vendor: JE-YYYY-XXXXX
     */
    public function generateEntryNumber(Vendor $vendor, ?Carbon $date = null): string
    {
        $year = ($date ?: now())->format('Y');
        $prefix = "JE-{$year}-";

        $latest = JournalEntry::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('entry_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('entry_number');

        $nextSeq = 1;
        if ($latest && preg_match('/JE-\d{4}-(\d+)/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('JE-%s-%05d', $year, $nextSeq);
    }

    /**
     * Post an immutable balanced journal entry.
     *
     * @param array{
     *     entry_date?: string,
     *     source_type: string,
     *     source_id?: int|null,
     *     idempotency_key: string,
     *     description: string,
     *     location_id?: int|null,
     *     lines: array<int, array{
     *         account_id: int,
     *         type: 'debit'|'credit',
     *         amount: float|int|string,
     *         currency?: string,
     *         exchange_rate?: float,
     *         location_id?: int|null,
     *         memo?: string|null
     *     }>
     * } $data
     */
    public function postJournalEntry(Vendor $vendor, array $data, ?User $user = null): JournalEntry
    {
        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();

        // Idempotency check: if entry already exists, return it safely without duplicate posting
        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->with('lines.account')
            ->first();

        if ($existing) {
            return $existing;
        }

        $linesData = $data['lines'] ?? [];
        if (count($linesData) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry must contain at least 2 lines (debit and credit).',
            ]);
        }

        $entryDate = isset($data['entry_date']) ? Carbon::parse($data['entry_date']) : Carbon::now();

        // Verify that the entry date is not within a closed accounting period
        $closedPeriod = AccountingPeriod::where('vendor_id', $vendor->id)
            ->where('status', 'closed')
            ->where('start_date', '<=', $entryDate->toDateString())
            ->where('end_date', '>=', $entryDate->toDateString())
            ->first();

        if ($closedPeriod) {
            throw ValidationException::withMessages([
                'entry_date' => "Cannot post transactions into closed accounting period '{$closedPeriod->name}'.",
            ]);
        }

        // Validate Double-Entry balancing invariant
        $totalDebit = 0.00;
        $totalCredit = 0.00;
        $baseCurrency = $vendor->currency ?: 'AMD';

        foreach ($linesData as $index => $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount" => 'Line amount must be greater than zero.',
                ]);
            }

            $type = strtolower($line['type'] ?? '');
            if (! in_array($type, ['debit', 'credit'], true)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.type" => 'Line type must be debit or credit.',
                ]);
            }

            $rate = (float) ($line['exchange_rate'] ?? 1.000000);
            $baseAmount = round($amount * $rate, 2);

            if ($type === 'debit') {
                $totalDebit += $baseAmount;
            } else {
                $totalCredit += $baseAmount;
            }
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        // Balancing Invariant Check down to 0.01 precision
        if (bccomp((string) $totalDebit, (string) $totalCredit, 2) !== 0) {
            throw ValidationException::withMessages([
                'lines' => "Unbalanced Journal Entry: Total Debit ({$totalDebit}) does not equal Total Credit ({$totalCredit}).",
            ]);
        }

        return DB::transaction(function () use ($vendor, $data, $entryDate, $linesData, $totalDebit, $totalCredit, $idempotencyKey, $user, $baseCurrency) {
            $entryNumber = $this->generateEntryNumber($vendor, $entryDate);

            // Find matching open accounting period if any
            $period = AccountingPeriod::where('vendor_id', $vendor->id)
                ->where('status', 'open')
                ->where('start_date', '<=', $entryDate->toDateString())
                ->where('end_date', '>=', $entryDate->toDateString())
                ->first();

            // Compute cryptographic HMAC-SHA256 hash chain
            $lastEntry = JournalEntry::where('vendor_id', $vendor->id)
                ->where('status', 'posted')
                ->whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->first();

            $previousHash = $lastEntry?->entry_hash ?? hash('sha256', "GENESIS-{$vendor->id}");
            $appSecret = config('app.key') ?: 'qrmenu-finance-secret';
            $debitStr = number_format($totalDebit, 2, '.', '');
            $creditStr = number_format($totalCredit, 2, '.', '');
            $dataToSign = "{$vendor->id}|{$entryNumber}|{$entryDate->toDateString()}|{$debitStr}|{$creditStr}|{$previousHash}";
            $entryHash = hash_hmac('sha256', $dataToSign, $appSecret);

            $entry = JournalEntry::create([
                'vendor_id' => $vendor->id,
                'location_id' => $data['location_id'] ?? null,
                'accounting_period_id' => $period?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate->toDateString(),
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'status' => 'posted',
                'is_reversal' => false,
                'description' => $data['description'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
                'created_by' => $user?->id ?? auth()->id(),
                'posted_at' => Carbon::now(),
            ]);

            $affectedAccountIds = [];

            foreach ($linesData as $line) {
                $amount = round((float) $line['amount'], 2);
                $rate = (float) ($line['exchange_rate'] ?? 1.000000);
                $baseAmount = round($amount * $rate, 2);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'location_id' => $line['location_id'] ?? $entry->location_id,
                    'type' => strtolower($line['type']),
                    'amount' => $amount,
                    'currency' => $line['currency'] ?? $baseCurrency,
                    'exchange_rate' => $rate,
                    'amount_base_currency' => $baseAmount,
                    'memo' => $line['memo'] ?? null,
                ]);

                $affectedAccountIds[] = $line['account_id'];
            }

            // Update real-time balances for affected accounts
            foreach (array_unique($affectedAccountIds) as $accId) {
                $account = ChartOfAccount::find($accId);
                $account?->recalculateBalance();
            }

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'journal_entry_posted',
                auditable: $entry,
                user: $user,
                newValues: [
                    'entry_number' => $entry->entry_number,
                    'total' => $totalDebit,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                ],
                amount: $totalDebit,
                locationId: $entry->location_id
            );

            return $entry->load('lines.account');
        });
    }

    /**
     * Create an immutable reversal journal entry to correct a posted transaction.
     */
    public function createReversalEntry(
        JournalEntry $entry,
        ?string $reason = null,
        ?User $user = null
    ): JournalEntry {
        if ($entry->isReversed()) {
            throw ValidationException::withMessages([
                'entry' => 'This journal entry has already been reversed.',
            ]);
        }

        $entry->load('lines');
        $vendor = $entry->vendor;

        return DB::transaction(function () use ($entry, $reason, $user, $vendor) {
            $reversalNumber = $this->generateEntryNumber($vendor);
            $idempotencyKey = "reversal-{$entry->id}-".Str::random(10);

            $reversalEntry = JournalEntry::create([
                'vendor_id' => $entry->vendor_id,
                'location_id' => $entry->location_id,
                'accounting_period_id' => $entry->accounting_period_id,
                'entry_number' => $reversalNumber,
                'entry_date' => Carbon::now()->toDateString(),
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'posted',
                'is_reversal' => true,
                'reversal_of_entry_id' => $entry->id,
                'description' => "Reversal of {$entry->entry_number}: ".($reason ?: $entry->description),
                'total_debit' => $entry->total_credit, // Swapped
                'total_credit' => $entry->total_debit,
                'created_by' => $user?->id ?? auth()->id(),
                'posted_at' => Carbon::now(),
            ]);

            $affectedAccountIds = [];

            foreach ($entry->lines as $line) {
                // Invert type: debit becomes credit, credit becomes debit
                $invertedType = $line->isDebit() ? 'credit' : 'debit';

                JournalEntryLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line->account_id,
                    'location_id' => $line->location_id,
                    'type' => $invertedType,
                    'amount' => $line->amount,
                    'currency' => $line->currency,
                    'exchange_rate' => $line->exchange_rate,
                    'amount_base_currency' => $line->amount_base_currency,
                    'memo' => "Reversal line for #{$entry->entry_number}",
                ]);

                $affectedAccountIds[] = $line->account_id;
            }

            // Mark original entry as reversed
            $entry->update(['status' => 'reversed']);

            // Recalculate balances
            foreach (array_unique($affectedAccountIds) as $accId) {
                $account = ChartOfAccount::find($accId);
                $account?->recalculateBalance();
            }

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'journal_entry_reversed',
                auditable: $reversalEntry,
                user: $user,
                oldValues: ['original_entry_number' => $entry->entry_number],
                newValues: ['reversal_entry_number' => $reversalEntry->entry_number, 'reason' => $reason],
                amount: (float) $reversalEntry->total_debit
            );

            return $reversalEntry->load('lines.account');
        });
    }

    /**
     * Lock an accounting period preventing retroactive modifications.
     */
    public function closeAccountingPeriod(
        AccountingPeriod $period,
        User $user,
        ?string $notes = null
    ): AccountingPeriod {
        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'period' => 'This accounting period is already closed.',
            ]);
        }

        $period->update([
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => Carbon::now(),
            'notes' => $notes ?: $period->notes,
        ]);

        FinancialAuditLog::record(
            vendor: $period->vendor,
            action: 'accounting_period_closed',
            auditable: $period,
            user: $user,
            newValues: ['period_name' => $period->name, 'closed_at' => $period->closed_at->toIso8601String()]
        );

        return $period->fresh();
    }
}
