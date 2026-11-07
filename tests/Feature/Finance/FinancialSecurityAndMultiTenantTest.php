<?php

namespace Tests\Feature\Finance;

use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\ChartOfAccount;
use App\Models\FinancialCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use App\Services\CashShiftService;
use App\Services\ExpenseService;
use App\Services\FinancialAuditService;
use App\Services\FinancialLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialSecurityAndMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected Location $locationA1;

    protected Location $locationA2;

    protected User $ownerA;

    protected User $cashierA1;

    protected User $ownerB;

    protected FinancialLedgerService $ledgerService;

    protected CashShiftService $shiftService;

    protected ExpenseService $expenseService;

    protected FinancialAuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);
        $this->shiftService = app(CashShiftService::class);
        $this->expenseService = app(ExpenseService::class);
        $this->auditService = app(FinancialAuditService::class);

        // Tenant A
        $this->vendorA = Vendor::create([
            'name' => 'Bistro Armenia A',
            'slug' => 'bistro-armenia-a',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->locationA1 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Downtown Branch A1',
            'slug' => 'downtown-a1',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->locationA2 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Arabkir Branch A2',
            'slug' => 'arabkir-a2',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->ownerA = User::factory()->create([
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
        ]);

        $this->cashierA1 = User::factory()->create([
            'vendor_id' => $this->vendorA->id,
            'role' => 'staff',
        ]);

        // Tenant B
        $this->vendorB = Vendor::create([
            'name' => 'Cafe Dilijan B',
            'slug' => 'cafe-dilijan-b',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->ownerB = User::factory()->create([
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
        ]);

        // Initialize COA for both tenants
        $this->ledgerService->initializeDefaultAccounts($this->vendorA);
        $this->ledgerService->initializeDefaultAccounts($this->vendorB);
    }

    public function test_strict_tenant_isolation_prevents_cross_tenant_access(): void
    {
        // Create Bank Accounts for both
        $bankA = BankAccount::create([
            'vendor_id' => $this->vendorA->id,
            'account_name' => 'Tenant A Operating Bank',
            'bank_name' => 'Ameriabank',
            'account_number' => '15700111111111',
            'currency' => 'AMD',
            'current_balance' => 100000.00,
            'account_id' => $this->ledgerService->getAccountByCode($this->vendorA, '1020')->id,
        ]);

        $bankB = BankAccount::create([
            'vendor_id' => $this->vendorB->id,
            'account_name' => 'Tenant B Private Bank',
            'bank_name' => 'Ardshinbank',
            'account_number' => '24700222222222',
            'currency' => 'AMD',
            'current_balance' => 900000.00,
            'account_id' => $this->ledgerService->getAccountByCode($this->vendorB, '1020')->id,
        ]);

        // When acting as Owner A, querying Bank Accounts must NEVER return Tenant B's data
        $this->actingAs($this->ownerA);
        $tenantABankAccounts = BankAccount::all();

        $this->assertTrue($tenantABankAccounts->contains('id', $bankA->id));
        $this->assertFalse($tenantABankAccounts->contains('id', $bankB->id));

        // When acting as Owner B, querying Chart of Accounts must only return Tenant B's accounts
        $this->actingAs($this->ownerB);
        $tenantBChartAccounts = ChartOfAccount::all();

        foreach ($tenantBChartAccounts as $acc) {
            $this->assertEquals($this->vendorB->id, $acc->vendor_id);
            $this->assertNotEquals($this->vendorA->id, $acc->vendor_id);
        }
    }

    public function test_branch_isolation_restricts_cashier_to_assigned_location_registers(): void
    {
        $tillAccount = $this->ledgerService->getAccountByCode($this->vendorA, '1010');

        $reg1 = CashRegister::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA1->id,
            'account_id' => $tillAccount->id,
            'name' => 'Branch 1 Main Register',
            'code' => 'REG-A1-01',
            'current_balance' => 20000.00,
            'is_active' => true,
        ]);

        $reg2 = CashRegister::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA2->id,
            'account_id' => $tillAccount->id,
            'name' => 'Branch 2 Main Register',
            'code' => 'REG-A2-01',
            'current_balance' => 15000.00,
            'is_active' => true,
        ]);

        // Cashier A1 assigned to location A1 opens shift on Register 1 (success)
        $shift1 = $this->shiftService->openShift($this->vendorA, [
            'location_id' => $this->locationA1->id,
            'cash_register_id' => $reg1->id,
            'opening_float' => 10000.00,
        ], $this->cashierA1, $reg1);

        $this->assertEquals('open', $shift1->status);
        $this->assertEquals($this->locationA1->id, $shift1->location_id);

        // Attempting to open shift on another register while cashier already has an active open shift must fail
        $this->expectException(ValidationException::class);
        $this->shiftService->openShift($this->vendorA, [
            'location_id' => $this->locationA2->id,
            'cash_register_id' => $reg2->id,
            'opening_float' => 5000.00,
        ], $this->cashierA1, $reg2);
    }

    public function test_role_based_permissions_prevent_unauthorized_expense_approval_or_journal_posting(): void
    {
        $category = FinancialCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Equipment Repair',
            'code' => 'CAT-REPAIR',
            'type' => 'expense',
            'account_id' => $this->ledgerService->getAccountByCode($this->vendorA, '5500')->id,
        ]);

        $expense = $this->expenseService->createExpense($this->vendorA, [
            'financial_category_id' => $category->id,
            'location_id' => $this->locationA1->id,
            'title' => 'Espresso Machine Group Head Overhaul',
            'total_amount' => 85000.00,
            'expense_date' => Carbon::today()->toDateString(),
        ], $this->cashierA1);

        $this->assertEquals('pending_approval', $expense->status);

        // Cashier A1 lacks Permission::FINANCE_EXPENSES_APPROVE -> Approval must be denied
        $this->expectException(ValidationException::class);
        $this->expenseService->approveExpense($expense, $this->cashierA1);
    }

    public function test_immutable_audit_trail_and_mathematical_tamper_integrity_verification(): void
    {
        $cashAcc = $this->ledgerService->getAccountByCode($this->vendorA, '1010');
        $equityAcc = $this->ledgerService->getAccountByCode($this->vendorA, '3010');

        // Post legitimate balanced entry: Dr Cash 300,000 / Cr Equity 300,000
        $entry = $this->ledgerService->postJournalEntry($this->vendorA, [
            'entry_date' => Carbon::today()->toDateString(),
            'source_type' => 'ManualJournal',
            'source_id' => 777,
            'description' => 'Working Capital Contribution',
            'lines' => [
                [
                    'account_id' => $cashAcc->id,
                    'type' => 'debit',
                    'amount' => 300000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $equityAcc->id,
                    'type' => 'credit',
                    'amount' => 300000.00,
                    'currency' => 'AMD',
                ],
            ],
        ], $this->ownerA);

        // 1. Initial integrity verification must be 100% healthy
        $auditReport = $this->auditService->verifyTamperIntegrity($this->vendorA);
        $this->assertTrue($auditReport['is_valid']);
        $this->assertEquals('healthy', $auditReport['status']);
        $this->assertEquals(0, $auditReport['issues_count']);

        // 2. Simulate malicious SQL manipulation directly in the database (bypassing application service)
        // Altering line amount from 300,000 to 250,000 to steal 50,000
        DB::table('journal_entry_lines')
            ->where('journal_entry_id', $entry->id)
            ->where('type', 'debit')
            ->update(['amount_base_currency' => 250000.00]);

        // 3. Mathematical tamper verification must catch the imbalance immediately
        $tamperedReport = $this->auditService->verifyTamperIntegrity($this->vendorA);

        $this->assertFalse($tamperedReport['is_valid']);
        $this->assertEquals('compromised', $tamperedReport['status']);
        $this->assertGreaterThan(0, $tamperedReport['issues_count']);

        // Verify the specific issue detected is line_sum_mismatch or account_balance_mismatch
        $issueTypes = array_column($tamperedReport['issues'], 'type');
        $this->assertTrue(in_array('line_sum_mismatch', $issueTypes, true) || in_array('account_balance_mismatch', $issueTypes, true));
    }
}
