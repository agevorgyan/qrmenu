<?php

namespace Tests\Feature\Finance;

use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\FinancialCategory;
use App\Models\Income;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ExpenseService;
use App\Services\FinancialApprovalService;
use App\Services\FinancialLedgerService;
use App\Services\IncomeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseAndIncomeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialLedgerService $ledgerService;

    protected ExpenseService $expenseService;

    protected IncomeService $incomeService;

    protected FinancialApprovalService $approvalService;

    protected Vendor $vendor;

    protected Location $location;

    protected User $manager;

    protected User $staff;

    protected FinancialCategory $utilitiesCategory;

    protected FinancialCategory $cateringIncomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);
        $this->expenseService = app(ExpenseService::class);
        $this->incomeService = app(IncomeService::class);
        $this->approvalService = app(FinancialApprovalService::class);

        $this->vendor = Vendor::create([
            'name' => 'Expense Management Bistro',
            'slug' => 'exp-bistro',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->staff = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'staff',
        ]);

        $this->ledgerService->initializeDefaultAccounts($this->vendor);

        $this->utilitiesCategory = FinancialCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Electricity & Gas',
            'code' => 'CAT-UTIL',
            'type' => 'expense',
            'account_id' => $this->ledgerService->getAccountByCode($this->vendor, '5300')->id,
        ]);

        $this->cateringIncomeCategory = FinancialCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Private Event Rentals',
            'code' => 'CAT-EVENTS',
            'type' => 'income',
            'account_id' => $this->ledgerService->getAccountByCode($this->vendor, '4100')->id,
        ]);
    }

    public function test_creates_expense_with_sequential_number_and_approval_threshold(): void
    {
        // 1. Below 50,000 AMD -> Auto-approved
        $smallExpense = $this->expenseService->createExpense($this->vendor, [
            'financial_category_id' => $this->utilitiesCategory->id,
            'location_id' => $this->location->id,
            'title' => 'Light bulbs purchase',
            'total_amount' => 12000.00,
            'expense_date' => Carbon::today()->toDateString(),
        ], $this->manager);

        $this->assertEquals('approved', $smallExpense->status);
        $this->assertStringStartsWith('EXP-', $smallExpense->expense_number);

        // 2. Over 50,000 AMD created by staff -> Pending approval
        $largeExpense = $this->expenseService->createExpense($this->vendor, [
            'financial_category_id' => $this->utilitiesCategory->id,
            'location_id' => $this->location->id,
            'title' => 'Monthly Gas Heating Bill',
            'total_amount' => 180000.00,
            'expense_date' => Carbon::today()->toDateString(),
        ], $this->staff);

        $this->assertEquals('pending_approval', $largeExpense->status);
    }

    public function test_approving_expense_recognizes_liability_in_general_ledger(): void
    {
        $expense = $this->expenseService->createExpense($this->vendor, [
            'financial_category_id' => $this->utilitiesCategory->id,
            'location_id' => $this->location->id,
            'title' => 'High-voltage power line service',
            'total_amount' => 75000.00,
            'expense_date' => Carbon::today()->toDateString(),
        ], $this->staff);

        $this->assertEquals('pending_approval', $expense->status);

        $approvedExpense = $this->expenseService->approveExpense($expense, $this->manager);

        $this->assertEquals('approved', $approvedExpense->status);
        $this->assertNotNull($approvedExpense->journal_entry_id);

        // General Ledger Verification:
        // Dr 5300 (Electricity & Gas) = 75,000
        // Cr 2010 (Accounts Payable) = 75,000
        $expenseAccount = $this->ledgerService->getAccountByCode($this->vendor, '5300');
        $apAccount = $this->ledgerService->getAccountByCode($this->vendor, '2010');

        $this->assertEquals(75000.00, $expenseAccount->fresh()->current_balance);
        $this->assertEquals(75000.00, $apAccount->fresh()->current_balance);
    }

    public function test_records_partial_and_full_disbursements_against_expense(): void
    {
        $bankAccount = BankAccount::create([
            'vendor_id' => $this->vendor->id,
            'account_id' => $this->ledgerService->getAccountByCode($this->vendor, '1040')->id,
            'bank_name' => 'Ameriabank',
            'account_name' => 'Operating Bank',
            'account_number' => '15700112233',
            'currency' => 'AMD',
            'current_balance' => 200000.00,
            'is_active' => true,
        ]);

        $expense = $this->expenseService->createExpense($this->vendor, [
            'financial_category_id' => $this->utilitiesCategory->id,
            'location_id' => $this->location->id,
            'title' => 'Commercial Kitchen Hood Deep Cleaning',
            'total_amount' => 100000.00,
            'expense_date' => Carbon::today()->toDateString(),
        ], $this->manager);

        $this->expenseService->approveExpense($expense, $this->manager);

        // 1. First partial payment: 40,000 AMD from Bank
        $p1 = $this->expenseService->recordPayment($expense, [
            'amount' => 40000.00,
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $bankAccount->id,
            'reference_number' => 'WIRE-P1',
        ], $this->manager);

        $this->assertEquals('partially_paid', $expense->fresh()->payment_status);
        $this->assertEquals(40000.00, $expense->fresh()->paid_amount);
        $this->assertEquals(160000.00, $bankAccount->fresh()->current_balance);

        // 2. Final payment: 60,000 AMD from Bank
        $p2 = $this->expenseService->recordPayment($expense, [
            'amount' => 60000.00,
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $bankAccount->id,
            'reference_number' => 'WIRE-P2',
        ], $this->manager);

        $this->assertEquals('paid', $expense->fresh()->payment_status);
        $this->assertEquals(100000.00, $expense->fresh()->paid_amount);
        $this->assertEquals(100000.00, $bankAccount->fresh()->current_balance);

        // AP Liability in GL should now be completely settled to 0.00
        $apAccount = $this->ledgerService->getAccountByCode($this->vendor, '2010');
        $this->assertEquals(0.00, $apAccount->fresh()->current_balance);

        // Anti-duplication check: Expense account 5300 remains exactly 100,000 (not 200,000)
        $expenseAccount = $this->ledgerService->getAccountByCode($this->vendor, '5300');
        $this->assertEquals(100000.00, $expenseAccount->fresh()->current_balance);
    }

    public function test_rejects_disbursement_exceeding_outstanding_expense_balance(): void
    {
        $expense = $this->expenseService->createExpense($this->vendor, [
            'financial_category_id' => $this->utilitiesCategory->id,
            'title' => 'Plumbing Maintenance',
            'total_amount' => 30000.00,
            'expense_date' => Carbon::today()->toDateString(),
        ], $this->manager);

        $this->expectException(ValidationException::class);

        // Attempting to disburse 35,000 on a 30,000 expense must fail
        $this->expenseService->recordPayment($expense, [
            'amount' => 35000.00,
            'payment_method' => 'cash',
        ], $this->manager);
    }

    public function test_records_other_operating_income_and_posts_to_ledger(): void
    {
        $bankAccount = BankAccount::create([
            'vendor_id' => $this->vendor->id,
            'account_id' => $this->ledgerService->getAccountByCode($this->vendor, '1040')->id,
            'bank_name' => 'Converse Bank',
            'account_name' => 'Revenue Account',
            'account_number' => '19300445566',
            'currency' => 'AMD',
            'current_balance' => 50000.00,
            'is_active' => true,
        ]);

        $income = $this->incomeService->recordIncome($this->vendor, [
            'financial_category_id' => $this->cateringIncomeCategory->id,
            'location_id' => $this->location->id,
            'title' => 'Wedding Reception Venue Rental Deposit',
            'amount' => 250000.00,
            'income_date' => Carbon::today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $bankAccount->id,
            'customer_payer' => 'Petrosyan Family',
            'reference_number' => 'DEP-2026-001',
        ], $this->manager);

        $this->assertEquals(250000.00, $income->amount);
        $this->assertStringStartsWith('INC-', $income->income_number);
        $this->assertEquals(300000.00, $bankAccount->fresh()->current_balance);

        // GL Check:
        // Dr 1040 (Bank) = 250,000
        // Cr 4100 (Other Income) = 250,000
        $otherIncomeAccount = $this->ledgerService->getAccountByCode($this->vendor, '4100');
        $this->assertEquals(250000.00, $otherIncomeAccount->fresh()->current_balance);
    }
}
