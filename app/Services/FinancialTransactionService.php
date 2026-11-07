<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FinancialTransfer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PayrollPeriod;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\SalaryPayment;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;

class FinancialTransactionService
{
    public function __construct(
        protected FinancialLedgerService $ledgerService
    ) {}

    /**
     * Record sales revenue for a completed customer order.
     */
    public function recordOrderSale(Order $order, ?User $user = null): JournalEntry
    {
        $vendor = $order->vendor;
        $idempotencyKey = "order-sale-{$order->id}";

        // Check if already posted
        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $arAccount = $this->ledgerService->getAccountByCode($vendor, '1200'); // Accounts Receivable / Clearing
        $foodRev = $this->ledgerService->getAccountByCode($vendor, '4010');   // Food Sales Revenue
        $bevRev = $this->ledgerService->getAccountByCode($vendor, '4020');    // Beverage Sales Revenue
        $taxAccount = $this->ledgerService->getAccountByCode($vendor, '2300'); // Sales Tax / VAT
        $discountAccount = $this->ledgerService->getAccountByCode($vendor, '4910'); // Promo Discounts

        $subtotal = round((float) $order->subtotal, 2);
        $tax = round((float) ($order->tax_amount ?? 0), 2);
        $discount = round((float) ($order->discount_amount ?? 0), 2);
        $total = round((float) $order->total_amount, 2);

        $lines = [];

        // Debit: Accounts Receivable (or Customer Clearing) for net total
        $lines[] = [
            'account_id' => $arAccount->id,
            'location_id' => $order->location_id,
            'type' => 'debit',
            'amount' => $total,
            'currency' => $order->currency ?: 'AMD',
            'memo' => "Order #{$order->order_number} Receivable",
        ];

        // If a discount was given, record Debit to Contra-Revenue (Discounts Allowed)
        if ($discount > 0) {
            $lines[] = [
                'account_id' => $discountAccount->id,
                'location_id' => $order->location_id,
                'type' => 'debit',
                'amount' => $discount,
                'currency' => $order->currency ?: 'AMD',
                'memo' => "Discount on Order #{$order->order_number}",
            ];
        }

        // Credit: Sales Revenue (Food & Bev)
        // Note: subtotal represents gross sales before discount
        $grossSales = $subtotal > 0 ? $subtotal : round($total + $discount - $tax, 2);

        $lines[] = [
            'account_id' => $foodRev->id,
            'location_id' => $order->location_id,
            'type' => 'credit',
            'amount' => $grossSales,
            'currency' => $order->currency ?: 'AMD',
            'memo' => "Order #{$order->order_number} Food Sales Revenue",
        ];

        // Credit: Tax Liability if applicable
        if ($tax > 0) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'location_id' => $order->location_id,
                'type' => 'credit',
                'amount' => $tax,
                'currency' => $order->currency ?: 'AMD',
                'memo' => "VAT / Sales Tax on Order #{$order->order_number}",
            ];
        }

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => $order->closed_at ? Carbon::parse($order->closed_at)->toDateString() : Carbon::now()->toDateString(),
            'source_type' => 'Order',
            'source_id' => $order->id,
            'location_id' => $order->location_id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Sales Revenue: Order #{$order->order_number}",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record settlement / payment collection for an order.
     */
    public function recordPaymentSettlement(Order $order, PosPayment $payment, ?User $user = null): JournalEntry
    {
        $vendor = $order->vendor;
        $idempotencyKey = "payment-settlement-{$payment->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $method = strtolower($payment->payment_method);
        $amount = round((float) $payment->amount, 2);
        $tipAmount = round((float) ($payment->tip_amount ?? 0), 2);
        $netPayment = $amount;

        // Resolve asset account based on payment method
        if ($method === 'cash') {
            $assetAccount = $this->ledgerService->getAccountByCode($vendor, '1010'); // Cash in Registers
        } elseif (in_array($method, ['card', 'pos_terminal'], true)) {
            $assetAccount = $this->ledgerService->getAccountByCode($vendor, '1030'); // Card Clearing
        } else {
            $assetAccount = $this->ledgerService->getAccountByCode($vendor, '1040'); // Online Gateway Clearing
        }

        $arAccount = $this->ledgerService->getAccountByCode($vendor, '1200'); // Accounts Receivable
        $tipsAccount = $this->ledgerService->getAccountByCode($vendor, '2100'); // Tips Payable (Liability)

        $lines = [];

        // Total cash/card taken into asset account includes tip
        $totalTendered = round($netPayment + $tipAmount, 2);

        $lines[] = [
            'account_id' => $assetAccount->id,
            'location_id' => $order->location_id,
            'type' => 'debit',
            'amount' => $totalTendered,
            'currency' => $payment->currency ?: 'AMD',
            'memo' => "Payment #{$payment->id} ({$method}) for Order #{$order->order_number}",
        ];

        // Credit Accounts Receivable to clear the order's sale amount
        $lines[] = [
            'account_id' => $arAccount->id,
            'location_id' => $order->location_id,
            'type' => 'credit',
            'amount' => $netPayment,
            'currency' => $payment->currency ?: 'AMD',
            'memo' => "Clear Receivable for Order #{$order->order_number}",
        ];

        // Credit Tips Payable (Liability to staff, NOT revenue)
        if ($tipAmount > 0) {
            $lines[] = [
                'account_id' => $tipsAccount->id,
                'location_id' => $order->location_id,
                'type' => 'credit',
                'amount' => $tipAmount,
                'currency' => $payment->currency ?: 'AMD',
                'memo' => "Staff tip collected on Order #{$order->order_number}",
            ];
        }

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::now()->toDateString(),
            'source_type' => 'PosPayment',
            'source_id' => $payment->id,
            'location_id' => $order->location_id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Settlement: Order #{$order->order_number} ({$method})",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record customer refund with contra-revenue and cash/clearing reversal.
     */
    public function recordOrderRefund(
        Order $order,
        float $refundAmount,
        string $paymentMethod = 'cash',
        ?string $reason = null,
        ?User $user = null
    ): JournalEntry {
        $vendor = $order->vendor;
        $idempotencyKey = "refund-{$order->id}-".round($refundAmount, 2).'-'.Str::random(6);

        $returnsAccount = $this->ledgerService->getAccountByCode($vendor, '4900'); // Sales Returns & Allowances (Contra-Rev)

        if (strtolower($paymentMethod) === 'cash') {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1010'); // Cash in Registers
        } else {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1030'); // Card Clearing
        }

        $lines = [
            [
                'account_id' => $returnsAccount->id,
                'location_id' => $order->location_id,
                'type' => 'debit',
                'amount' => round($refundAmount, 2),
                'currency' => $order->currency ?: 'AMD',
                'memo' => "Refund for Order #{$order->order_number}: ".($reason ?: 'Customer Refund'),
            ],
            [
                'account_id' => $disbAccount->id,
                'location_id' => $order->location_id,
                'type' => 'credit',
                'amount' => round($refundAmount, 2),
                'currency' => $order->currency ?: 'AMD',
                'memo' => "Disbursement for Order #{$order->order_number} refund",
            ],
        ];

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::now()->toDateString(),
            'source_type' => 'Refund',
            'source_id' => $order->id,
            'location_id' => $order->location_id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Refund: Order #{$order->order_number}",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record operating expense accrual liability in general ledger.
     * Dr [Expense Account]
     * Cr 2010 (Accounts Payable)
     */
    public function recordExpenseAccrual(
        Expense $expense,
        ?User $user = null
    ): JournalEntry {
        $vendor = $expense->vendor;
        $idempotencyKey = "expense-accrual-{$expense->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $expenseAccount = $expense->account ?: $expense->category?->account ?: $this->ledgerService->getAccountByCode($vendor, '5300');
        $apAccount = $this->ledgerService->getAccountByCode($vendor, '2010'); // Accounts Payable
        $amount = round((float) $expense->amount, 2);

        $lines = [
            [
                'account_id' => $expenseAccount->id,
                'location_id' => $expense->location_id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => $expense->currency ?: 'AMD',
                'memo' => "Expense: {$expense->title} ({$expense->expense_number})",
            ],
            [
                'account_id' => $apAccount->id,
                'location_id' => $expense->location_id,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => $expense->currency ?: 'AMD',
                'memo' => "Accrued AP: {$expense->expense_number}",
            ],
        ];

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => $expense->expense_date ? Carbon::parse($expense->expense_date)->toDateString() : Carbon::now()->toDateString(),
            'source_type' => 'Expense',
            'source_id' => $expense->id,
            'location_id' => $expense->location_id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Expense Accrual: {$expense->title}",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record operating expense disbursement.
     */
    public function recordExpensePayment(
        Expense $expense,
        ExpensePayment $payment,
        ?User $user = null
    ): JournalEntry {
        $vendor = $expense->vendor;
        $idempotencyKey = "expense-payment-{$payment->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        // If expense was accrued to Accounts Payable, debit Accounts Payable (2010)
        // Otherwise, debit the expense account directly
        if ($expense->journal_entry_id) {
            $debitAccount = $this->ledgerService->getAccountByCode($vendor, '2010');
        } else {
            $debitAccount = $expense->account ?: $expense->category?->account ?: $this->ledgerService->getAccountByCode($vendor, '5300');
        }

        $method = strtolower($payment->payment_method);

        if ($method === 'cash') {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1010'); // Cash in Registers
        } else {
            $disbAccount = $payment->bankAccount?->account ?: $this->ledgerService->getAccountByCode($vendor, '1020'); // Bank Account
        }

        $amount = round((float) $payment->amount, 2);

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'location_id' => $expense->location_id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => $payment->currency ?: 'AMD',
                'memo' => "Settlement: {$expense->title} ({$expense->expense_number})",
            ],
            [
                'account_id' => $disbAccount->id,
                'location_id' => $expense->location_id,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => $payment->currency ?: 'AMD',
                'memo' => "Payment for {$expense->expense_number} via {$method}",
            ],
        ];

        $entry = $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateString() : Carbon::now()->toDateString(),
            'source_type' => 'ExpensePayment',
            'source_id' => $payment->id,
            'location_id' => $expense->location_id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Expense Paid: {$expense->title}",
            'lines' => $lines,
        ], $user);

        $payment->update(['journal_entry_id' => $entry->id]);

        return $entry;
    }

    /**
     * Record accrual supplier invoice bill from inventory procurement.
     */
    public function recordSupplierInvoice(SupplierInvoice $invoice, ?User $user = null): JournalEntry
    {
        $vendor = $invoice->vendor;
        $idempotencyKey = "supplier-invoice-{$invoice->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $inventoryAccount = $this->ledgerService->getAccountByCode($vendor, '1300'); // Inventory Asset (or 5010 COGS)
        $apAccount = $this->ledgerService->getAccountByCode($vendor, '2010');        // Accounts Payable
        $taxAccount = $this->ledgerService->getAccountByCode($vendor, '2300');       // Tax

        $subtotal = round((float) $invoice->subtotal, 2);
        $tax = round((float) $invoice->tax_amount, 2);
        $total = round((float) $invoice->total_amount, 2);

        $lines = [];

        // Debit Inventory asset for purchase
        $lines[] = [
            'account_id' => $inventoryAccount->id,
            'type' => 'debit',
            'amount' => $subtotal > 0 ? $subtotal : $total,
            'currency' => 'AMD',
            'memo' => "Supplier Invoice #{$invoice->invoice_number} from {$invoice->supplier?->name}",
        ];

        if ($tax > 0) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'type' => 'debit',
                'amount' => $tax,
                'currency' => 'AMD',
                'memo' => "Input VAT on Invoice #{$invoice->invoice_number}",
            ];
        }

        // Credit Accounts Payable
        $lines[] = [
            'account_id' => $apAccount->id,
            'type' => 'credit',
            'amount' => $total,
            'currency' => 'AMD',
            'memo' => "Payable to {$invoice->supplier?->name} for #{$invoice->invoice_number}",
        ];

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::parse($invoice->invoice_date)->toDateString(),
            'source_type' => 'SupplierInvoice',
            'source_id' => $invoice->id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Supplier Invoice: {$invoice->supplier?->name} (#{$invoice->invoice_number})",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record supplier payment (AP liability settlement).
     */
    public function recordSupplierPayment(SupplierPayment $payment, ?User $user = null): JournalEntry
    {
        $vendor = $payment->vendor;
        $idempotencyKey = "supplier-payment-{$payment->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $apAccount = $this->ledgerService->getAccountByCode($vendor, '2010'); // Accounts Payable
        $method = strtolower($payment->payment_method);

        if ($method === 'cash') {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1010');
        } else {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1020'); // Bank Account
        }

        $amount = round((float) $payment->amount, 2);

        $lines = [
            [
                'account_id' => $apAccount->id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => 'AMD',
                'memo' => "Settle AP for Supplier {$payment->supplier?->name}",
            ],
            [
                'account_id' => $disbAccount->id,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => 'AMD',
                'memo' => "Disbursement #{$payment->payment_number} to {$payment->supplier?->name}",
            ],
        ];

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::parse($payment->payment_date)->toDateString(),
            'source_type' => 'SupplierPayment',
            'source_id' => $payment->id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Supplier Disbursement: {$payment->supplier?->name}",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record payroll period approval & labor cost accrual.
     */
    public function recordPayrollAccrual(PayrollPeriod $period, ?User $user = null): JournalEntry
    {
        $vendor = $period->vendor;
        $idempotencyKey = "payroll-period-{$period->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $wageExpense = $this->ledgerService->getAccountByCode($vendor, '5100'); // Wages & Salaries Expense
        $salariesPayable = $this->ledgerService->getAccountByCode($vendor, '2200'); // Net Salaries Payable (Liability)
        $taxPayable = $this->ledgerService->getAccountByCode($vendor, '2350'); // Payroll Taxes Payable
        $advancesAccount = $this->ledgerService->getAccountByCode($vendor, '1400'); // Employee Advances

        $gross = round((float) $period->total_gross, 2);
        $net = round((float) $period->total_net, 2);
        $tax = round((float) $period->total_tax, 2);
        $advances = round((float) ($period->payrollItems()->sum('advance_deduction') ?? 0), 2);
        $otherDeductions = round((float) $period->total_deductions - $tax - $advances, 2);

        $lines = [];

        // Debit: Gross wages expense
        $lines[] = [
            'account_id' => $wageExpense->id,
            'type' => 'debit',
            'amount' => $gross,
            'currency' => $period->currency ?: 'AMD',
            'memo' => "Gross Payroll for {$period->name}",
        ];

        // Credit: Net salaries payable
        $lines[] = [
            'account_id' => $salariesPayable->id,
            'type' => 'credit',
            'amount' => $net,
            'currency' => $period->currency ?: 'AMD',
            'memo' => "Net Salaries Payable for {$period->name}",
        ];

        // Credit: Taxes payable (Income tax, pension, stamp fee)
        $totalTaxAndDeductions = round($tax + max(0, $otherDeductions), 2);
        if ($totalTaxAndDeductions > 0) {
            $lines[] = [
                'account_id' => $taxPayable->id,
                'type' => 'credit',
                'amount' => $totalTaxAndDeductions,
                'currency' => $period->currency ?: 'AMD',
                'memo' => "Withheld Payroll Taxes & Statutory Fees for {$period->name}",
            ];
        }

        // Credit: Advances recovered
        if ($advances > 0) {
            $lines[] = [
                'account_id' => $advancesAccount->id,
                'type' => 'credit',
                'amount' => $advances,
                'currency' => $period->currency ?: 'AMD',
                'memo' => "Recovered Employee Advances for {$period->name}",
            ];
        }

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::parse($period->end_date)->toDateString(),
            'source_type' => 'PayrollPeriod',
            'source_id' => $period->id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Payroll Accrual: {$period->name}",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record salary payout disbursement (Net salaries liability settlement).
     */
    public function recordSalaryPayout(SalaryPayment $payment, ?User $user = null): JournalEntry
    {
        $vendor = $payment->vendor;
        $idempotencyKey = "salary-payment-{$payment->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $salariesPayable = $this->ledgerService->getAccountByCode($vendor, '2200'); // Net Salaries Payable
        $method = strtolower($payment->payment_method);

        if ($method === 'cash') {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1010'); // Cash in Registers
        } else {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1020'); // Bank Account
        }

        $amount = round((float) $payment->amount, 2);

        $lines = [
            [
                'account_id' => $salariesPayable->id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => 'AMD',
                'memo' => "Settle Net Salary for {$payment->user?->name}",
            ],
            [
                'account_id' => $disbAccount->id,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => 'AMD',
                'memo' => "Salary Payout #{$payment->payment_number} via {$method}",
            ],
        ];

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::parse($payment->payment_date)->toDateString(),
            'source_type' => 'SalaryPayment',
            'source_id' => $payment->id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Salary Payout: {$payment->user?->name} ({$payment->payment_number})",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record employee advance loan disbursement.
     */
    public function recordEmployeeAdvance(EmployeeAdvance $advance, ?User $user = null): JournalEntry
    {
        $vendor = $advance->vendor;
        $idempotencyKey = "employee-advance-{$advance->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $advancesAccount = $this->ledgerService->getAccountByCode($vendor, '1400'); // Employee Advances Asset
        $method = strtolower($advance->payment_method);

        if ($method === 'cash') {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1010');
        } else {
            $disbAccount = $this->ledgerService->getAccountByCode($vendor, '1020');
        }

        $amount = round((float) $advance->amount, 2);

        $lines = [
            [
                'account_id' => $advancesAccount->id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => 'AMD',
                'memo' => "Advance disbursed to {$advance->user?->name}",
            ],
            [
                'account_id' => $disbAccount->id,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => 'AMD',
                'memo' => "Advance funding via {$method}",
            ],
        ];

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => $advance->disbursement_date ? Carbon::parse($advance->disbursement_date)->toDateString() : Carbon::now()->toDateString(),
            'source_type' => 'EmployeeAdvance',
            'source_id' => $advance->id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Salary Advance: {$advance->user?->name}",
            'lines' => $lines,
        ], $user);
    }

    /**
     * Record asset-to-asset transfer (Safe drop, Register-to-Register, Bank Deposit).
     * Strictly creates ZERO revenue and ZERO expense.
     */
    public function recordCashTransfer(FinancialTransfer $transfer, ?User $user = null): JournalEntry
    {
        $vendor = $transfer->vendor;
        $idempotencyKey = "transfer-{$transfer->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Resolve source and destination asset accounts
        $fromAccount = $this->resolveTransferAccount($vendor, $transfer->from_type, $transfer->from_id);
        $toAccount = $this->resolveTransferAccount($vendor, $transfer->to_type, $transfer->to_id);

        $amount = round((float) $transfer->amount, 2);

        $lines = [
            [
                'account_id' => $toAccount->id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => $transfer->currency ?: 'AMD',
                'memo' => "Transfer In to {$transfer->to_type} #{$transfer->to_id}",
            ],
            [
                'account_id' => $fromAccount->id,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => $transfer->currency ?: 'AMD',
                'memo' => "Transfer Out from {$transfer->from_type} #{$transfer->from_id}",
            ],
        ];

        $entry = $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::parse($transfer->transfer_date)->toDateString(),
            'source_type' => 'FinancialTransfer',
            'source_id' => $transfer->id,
            'idempotency_key' => $idempotencyKey,
            'description' => "Asset Transfer: {$transfer->from_type} -> {$transfer->to_type} (#{$transfer->transfer_number})",
            'lines' => $lines,
        ], $user);

        $transfer->update(['journal_entry_id' => $entry->id]);

        return $entry;
    }

    /**
     * Record cash drawer shift discrepancy (Cash Over / Short) upon closing.
     */
    public function recordTillVariance(PosShift $shift, float $variance, ?User $user = null): ?JournalEntry
    {
        if (bccomp((string) $variance, '0.00', 2) === 0) {
            return null; // Balanced, no discrepancy entry needed
        }

        $vendor = $shift->vendor;
        $idempotencyKey = "shift-variance-{$shift->id}";

        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $cashAccount = $this->ledgerService->getAccountByCode($vendor, '1010'); // Cash in Registers
        $varianceAccount = $this->ledgerService->getAccountByCode($vendor, '5800'); // Cash Over / Short

        $absVariance = round(abs($variance), 2);
        $lines = [];

        if ($variance < 0) {
            // Shortage: Debit expense (loss), Credit cash asset
            $lines[] = [
                'account_id' => $varianceAccount->id,
                'location_id' => $shift->location_id,
                'type' => 'debit',
                'amount' => $absVariance,
                'currency' => 'AMD',
                'memo' => "Cash Shortage on Shift #{$shift->id}",
            ];
            $lines[] = [
                'account_id' => $cashAccount->id,
                'location_id' => $shift->location_id,
                'type' => 'credit',
                'amount' => $absVariance,
                'currency' => 'AMD',
                'memo' => "Till balance reduction for Shift #{$shift->id} shortage",
            ];
        } else {
            // Overage / Surplus: Debit cash asset, Credit expense (contra-expense / other income)
            $lines[] = [
                'account_id' => $cashAccount->id,
                'location_id' => $shift->location_id,
                'type' => 'debit',
                'amount' => $absVariance,
                'currency' => 'AMD',
                'memo' => "Cash Surplus on Shift #{$shift->id}",
            ];
            $lines[] = [
                'account_id' => $varianceAccount->id,
                'location_id' => $shift->location_id,
                'type' => 'credit',
                'amount' => $absVariance,
                'currency' => 'AMD',
                'memo' => "Till surplus credit on Shift #{$shift->id}",
            ];
        }

        return $this->ledgerService->postJournalEntry($vendor, [
            'entry_date' => Carbon::now()->toDateString(),
            'source_type' => 'PosShift',
            'source_id' => $shift->id,
            'location_id' => $shift->location_id,
            'idempotency_key' => $idempotencyKey,
            'description' => 'Shift Till Discrepancy: '.($variance < 0 ? 'Shortage' : 'Overage'),
            'lines' => $lines,
        ], $user);
    }

    /**
     * Resolve ChartOfAccount model for a transfer party.
     */
    protected function resolveTransferAccount(Vendor $vendor, string $type, int $id): ChartOfAccount
    {
        return match ($type) {
            'safe' => $this->ledgerService->getAccountByCode($vendor, '1015'),
            'bank_account' => BankAccount::find($id)?->account ?: $this->ledgerService->getAccountByCode($vendor, '1020'),
            default => $this->ledgerService->getAccountByCode($vendor, '1010'), // cash_register
        };
    }
}
