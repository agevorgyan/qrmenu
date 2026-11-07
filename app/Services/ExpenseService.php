<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\FinancialApprovalPolicy;
use App\Models\FinancialAuditLog;
use App\Models\FinancialCategory;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        protected FinancialTransactionService $transactionService,
        protected FinancialLedgerService $ledgerService
    ) {}

    /**
     * Generate the next sequential expense number: EXP-YYYY-XXXXX
     */
    public function generateExpenseNumber(Vendor $vendor, ?Carbon $date = null): string
    {
        $year = ($date ?: now())->format('Y');
        $prefix = "EXP-{$year}-";

        $latest = Expense::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('expense_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('expense_number');

        $nextSeq = 1;
        if ($latest && preg_match('/EXP-\d{4}-(\d+)/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('EXP-%s-%05d', $year, $nextSeq);
    }

    /**
     * Create a new operating expense with approval threshold verification.
     *
     * @param array{
     *     category_id: int,
     *     account_id?: int|null,
     *     location_id?: int|null,
     *     supplier_id?: int|null,
     *     title: string,
     *     description?: string|null,
     *     amount: float|int,
     *     currency?: string,
     *     tax_amount?: float|int,
     *     expense_date?: string,
     *     due_date?: string|null,
     *     payment_method?: string|null,
     *     cash_register_id?: int|null,
     *     bank_account_id?: int|null,
     *     pay_immediately?: bool,
     *     attachment_path?: string|null,
     *     is_recurring?: bool,
     *     recurring_frequency?: string|null,
     *     idempotency_key?: string|null
     * } $data
     */
    public function createExpense(Vendor $vendor, array $data, User $creator): Expense
    {
        $categoryId = $data['financial_category_id'] ?? $data['category_id'] ?? null;
        $category = $categoryId ? FinancialCategory::where('vendor_id', $vendor->id)->find($categoryId) : null;
        $amount = round((float) ($data['total_amount'] ?? $data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Expense amount must be greater than zero.',
            ]);
        }

        $expenseDate = isset($data['expense_date']) ? Carbon::parse($data['expense_date']) : Carbon::now();
        $accountId = $data['account_id'] ?? $category?->account_id;

        // Determine status based on approval policies
        $policy = FinancialApprovalPolicy::where('vendor_id', $vendor->id)
            ->where('entity_type', 'expense')
            ->where('is_active', true)
            ->first();

        $requiresApproval = false;
        if ($policy && $amount >= (float) $policy->threshold_amount) {
            $requiresApproval = true;
        } elseif (! $policy && $amount >= 50000.0) {
            $requiresApproval = true;
        }

        // Vendor owners and finance managers auto-approve their own submissions
        if ($creator->isVendorOwner() || $creator->hasPermission(Permission::FINANCE_EXPENSES_APPROVE)) {
            $requiresApproval = false;
        }

        $status = $requiresApproval ? 'pending_approval' : 'approved';
        $approvedBy = $requiresApproval ? null : $creator->id;
        $approvedAt = $requiresApproval ? null : now();

        return DB::transaction(function () use ($vendor, $data, $category, $accountId, $amount, $expenseDate, $status, $approvedBy, $approvedAt, $creator) {
            $expenseNumber = $this->generateExpenseNumber($vendor, $expenseDate);

            $expense = Expense::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'location_id' => $data['location_id'] ?? null,
                'category_id' => $category?->id,
                'account_id' => $accountId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'expense_number' => $expenseNumber,
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'currency' => $data['currency'] ?? $vendor->currency ?? 'AMD',
                'tax_amount' => round((float) ($data['tax_amount'] ?? 0), 2),
                'expense_date' => $expenseDate->toDateString(),
                'due_date' => isset($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null,
                'status' => $status,
                'payment_method' => $data['payment_method'] ?? null,
                'cash_register_id' => $data['cash_register_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'paid_amount' => 0.00,
                'remaining_amount' => $amount,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
                'attachment_path' => $data['attachment_path'] ?? null,
                'is_recurring' => (bool) ($data['is_recurring'] ?? false),
                'recurring_frequency' => $data['recurring_frequency'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? "exp-{$expenseNumber}",
                'created_by' => $creator->id,
            ]);

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'expense_created',
                auditable: $expense,
                user: $creator,
                newValues: [
                    'expense_number' => $expense->expense_number,
                    'title' => $expense->title,
                    'amount' => $amount,
                    'status' => $status,
                ],
                amount: $amount,
                locationId: $expense->location_id
            );

            // Record accrual journal entry if auto-approved
            if ($status === 'approved') {
                $entry = $this->transactionService->recordExpenseAccrual($expense, $creator);
                $expense->update(['journal_entry_id' => $entry->id]);
            }

            // If pay immediately was requested and expense is approved
            if (! empty($data['pay_immediately']) && $expense->isApproved()) {
                $this->recordPayment($expense, [
                    'amount' => $amount,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'cash_register_id' => $data['cash_register_id'] ?? null,
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'payment_date' => $expenseDate->toDateString(),
                    'reference_number' => 'Direct settlement upon creation',
                ], $creator);
            }

            return $expense->fresh(['category', 'account', 'supplier', 'createdByUser', 'payments']);
        });
    }

    /**
     * Approve a pending expense.
     */
    public function approveExpense(Expense $expense, User $approver): Expense
    {
        if (! $approver->hasPermission(Permission::FINANCE_EXPENSES_APPROVE) && ! $approver->isVendorOwner()) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to approve expenses.',
            ]);
        }

        if ($expense->status === 'approved') {
            if (! $expense->journal_entry_id) {
                $entry = $this->transactionService->recordExpenseAccrual($expense, $approver);
                $expense->update(['journal_entry_id' => $entry->id]);
            }

            return $expense->fresh(['category', 'approvedByUser']);
        }

        if ($expense->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'status' => "Cannot approve expense with status '{$expense->status}'.",
            ]);
        }

        $entry = $this->transactionService->recordExpenseAccrual($expense, $approver);

        $expense->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => Carbon::now(),
            'journal_entry_id' => $entry->id,
        ]);

        FinancialAuditLog::record(
            vendor: $expense->vendor,
            action: 'expense_approved',
            auditable: $expense,
            user: $approver,
            newValues: ['status' => 'approved', 'approved_by' => $approver->id],
            amount: (float) $expense->amount,
            locationId: $expense->location_id
        );

        return $expense->fresh(['category', 'approvedByUser']);
    }

    /**
     * Reject a pending expense.
     */
    public function rejectExpense(Expense $expense, User $rejector, string $reason): Expense
    {
        if (! $rejector->hasPermission(Permission::FINANCE_EXPENSES_APPROVE) && ! $rejector->isVendorOwner()) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to reject expenses.',
            ]);
        }

        $expense->update([
            'status' => 'rejected',
            'rejected_by' => $rejector->id,
            'rejected_at' => Carbon::now(),
            'rejection_reason' => trim($reason),
        ]);

        FinancialAuditLog::record(
            vendor: $expense->vendor,
            action: 'expense_rejected',
            auditable: $expense,
            user: $rejector,
            newValues: ['status' => 'rejected', 'reason' => $reason],
            amount: (float) $expense->amount,
            locationId: $expense->location_id
        );

        return $expense->fresh(['category', 'rejectedByUser']);
    }

    /**
     * Record a partial or full payment for an approved expense.
     *
     * @param array{
     *     amount: float|int,
     *     payment_method: string,
     *     payment_date?: string,
     *     cash_register_id?: int|null,
     *     bank_account_id?: int|null,
     *     reference_number?: string|null,
     *     notes?: string|null
     * } $paymentData
     */
    public function recordPayment(Expense $expense, array $paymentData, User $user): ExpensePayment
    {
        if (! $expense->isApproved()) {
            throw ValidationException::withMessages([
                'expense' => 'Cannot record payment for an unapproved expense.',
            ]);
        }

        $amount = round((float) $paymentData['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $remaining = (float) $expense->remaining_amount;
        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => "Payment amount ({$amount}) exceeds remaining balance of expense ({$remaining}).",
            ]);
        }

        $method = strtolower($paymentData['payment_method']);
        $cashRegisterId = $paymentData['cash_register_id'] ?? $expense->cash_register_id;
        $bankAccountId = $paymentData['bank_account_id'] ?? $expense->bank_account_id;

        // If paying via cash, check register balance
        if ($method === 'cash' && $cashRegisterId) {
            $register = CashRegister::where('vendor_id', $expense->vendor_id)->find($cashRegisterId);
            if ($register) {
                $shift = $register->activeShift();
                if ($shift) {
                    $shift->recalculateExpectedCash();
                    if ($amount > (float) $shift->expected_cash) {
                        throw ValidationException::withMessages([
                            'amount' => "Payment amount ({$amount}) exceeds cash in drawer ({$shift->expected_cash}).",
                        ]);
                    }
                }
            }
        }

        return DB::transaction(function () use ($expense, $paymentData, $amount, $method, $cashRegisterId, $bankAccountId, $user) {
            $year = now()->format('Y');
            $paymentNumber = "EXPPAY-{$year}-".Str::random(6);

            $payment = ExpensePayment::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $expense->vendor_id,
                'expense_id' => $expense->id,
                'payment_number' => $paymentNumber,
                'amount' => $amount,
                'currency' => $expense->currency ?: 'AMD',
                'payment_date' => $paymentData['payment_date'] ?? Carbon::now()->toDateString(),
                'payment_method' => $method,
                'cash_register_id' => $cashRegisterId,
                'bank_account_id' => $bankAccountId,
                'reference_number' => $paymentData['reference_number'] ?? null,
                'notes' => $paymentData['notes'] ?? null,
                'created_by' => $user->id,
                'idempotency_key' => "exp-pay-{$expense->id}-{$paymentNumber}",
            ]);

            // Post to General Ledger
            $this->transactionService->recordExpensePayment($expense, $payment, $user);

            // Deduct from cash register or bank account if applicable
            if ($method === 'cash' && $cashRegisterId) {
                $register = CashRegister::find($cashRegisterId);
                if ($register) {
                    $register->decrement('current_balance', $amount);
                    $shift = $register->activeShift();
                    if ($shift) {
                        $shift->recalculateExpectedCash();
                    }
                }
            } elseif ($bankAccountId) {
                $bank = BankAccount::find($bankAccountId);
                $bank?->decrement('current_balance', $amount);
            }

            // Update expense paid/remaining numbers and status
            $expense->recalculatePaymentStatus();

            FinancialAuditLog::record(
                vendor: $expense->vendor,
                action: 'expense_payment_recorded',
                auditable: $payment,
                user: $user,
                newValues: [
                    'payment_number' => $payment->payment_number,
                    'expense_id' => $expense->id,
                    'amount' => $amount,
                    'method' => $method,
                ],
                amount: $amount,
                locationId: $expense->location_id
            );

            return $payment;
        });
    }

    /**
     * Cancel an expense.
     */
    public function cancelExpense(Expense $expense, User $user, string $reason): Expense
    {
        if ($expense->paid_amount > 0) {
            throw ValidationException::withMessages([
                'expense' => 'Cannot cancel an expense that has recorded payments. Please void payments first.',
            ]);
        }

        $expense->update([
            'status' => 'cancelled',
            'description' => ($expense->description ? $expense->description."\n" : '')."Cancelled: {$reason}",
        ]);

        FinancialAuditLog::record(
            vendor: $expense->vendor,
            action: 'expense_cancelled',
            auditable: $expense,
            user: $user,
            newValues: ['status' => 'cancelled', 'reason' => $reason],
            amount: (float) $expense->amount,
            locationId: $expense->location_id
        );

        return $expense;
    }
}
