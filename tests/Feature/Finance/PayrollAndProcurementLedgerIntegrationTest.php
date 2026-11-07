<?php

namespace Tests\Feature\Finance;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\FinancialLedgerService;
use App\Services\FinancialReportingService;
use App\Services\FinancialTransactionService;
use App\Services\HrPaymentService;
use App\Services\ProcurementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAndProcurementLedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $manager;

    protected User $employee;

    protected FinancialLedgerService $ledgerService;

    protected FinancialReportingService $reportingService;

    protected ProcurementService $procurementService;

    protected HrPaymentService $hrPaymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);
        $this->reportingService = app(FinancialReportingService::class);
        $this->procurementService = app(ProcurementService::class);
        $this->hrPaymentService = app(HrPaymentService::class);

        $this->vendor = Vendor::create([
            'name' => 'Dilijan Mountain Resort',
            'slug' => 'dilijan-mountain',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Lodge',
            'slug' => 'main-lodge',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->employee = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'staff',
        ]);

        $this->ledgerService->initializeDefaultAccounts($this->vendor);
    }

    public function test_receiving_procurement_order_creates_supplier_invoice_and_accrues_inventory_and_ap_in_gl(): void
    {
        $warehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Central Dry Storage',
            'code' => 'WH-CENTRAL',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Yerevan Dairy Factory',
            'contact_person' => 'Armen Sargsyan',
            'phone' => '+37410123456',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Kilogram',
            'code' => 'kg',
            'symbol' => 'kg',
            'type' => 'weight',
            'is_base' => true,
        ]);

        $cheese = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Lori Cheese',
            'sku' => 'ING-LORI-01',
            'unit_id' => $unit->id,
            'cost_per_unit' => 2500.00,
            'is_active' => true,
        ]);

        // 1. Direct procurement receipt of 40 kg cheese at 2,500 AMD/kg = 100,000 AMD
        $invoice = $this->procurementService->receiveDirect(
            $supplier,
            $warehouse,
            [
                [
                    'ingredient_id' => $cheese->id,
                    'quantity' => 40,
                    'unit_id' => $unit->id,
                    'unit_cost' => 2500.00,
                ],
            ],
            'INV-DAIRY-2026-01',
            now()->toDateString(),
            now()->addDays(30)->toDateString(),
            $this->manager,
            'Fresh cheese delivery'
        );

        $this->assertEquals(100000.00, $invoice->total_amount);

        // GL Verification:
        // Dr 1300 (Food & Beverage Inventory) = 100,000
        // Cr 2010 (Accounts Payable) = 100,000
        $inventoryAccount = $this->ledgerService->getAccountByCode($this->vendor, '1300');
        $apAccount = $this->ledgerService->getAccountByCode($this->vendor, '2010');

        $this->assertEquals(100000.00, $inventoryAccount->fresh()->current_balance);
        $this->assertEquals(100000.00, $apAccount->fresh()->current_balance);

        // 2. Disburse full payment to supplier via bank transfer
        $this->procurementService->recordPayment(
            $supplier,
            100000.00,
            'bank_transfer',
            $invoice,
            now()->toDateString(),
            'WIRE-DAIRY-100K',
            'Settlement for cheese',
            $this->manager
        );

        // Accounts Payable settled to 0.00 AMD
        $this->assertEquals(0.00, $apAccount->fresh()->current_balance);

        // Bank Account (1020) credited by 100,000 AMD
        $bankAccount = $this->ledgerService->getAccountByCode($this->vendor, '1020');
        $this->assertEquals(-100000.00, $bankAccount->fresh()->current_balance);

        // P&L Check: Purchasing inventory is a balance sheet asset swap, NOT an immediate P&L operating expense!
        $pnl = $this->reportingService->getProfitAndLoss(
            $this->vendor,
            null,
            Carbon::today()->startOfMonth(),
            Carbon::today()->endOfMonth()
        );
        $this->assertEquals(0.00, $pnl['operating_expenses']['total_opex']);
    }

    public function test_approving_payroll_period_accrues_wage_expense_and_disbursement_settles_payroll_liabilities(): void
    {
        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'November 2026 Monthly Payroll',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'cutoff_date' => '2026-11-28',
            'payment_date' => '2026-12-05',
            'status' => 'pending_approval',
            'total_gross' => 200000.00,
            'total_tax' => 40000.00,
            'total_net' => 160000.00,
        ]);

        $item = PayrollItem::create([
            'vendor_id' => $this->vendor->id,
            'payroll_period_id' => $period->id,
            'user_id' => $this->employee->id,
            'gross_salary' => 200000.00,
            'income_tax' => 40000.00,
            'net_salary' => 160000.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
        ]);

        // 1. Accrue Payroll in General Ledger
        app(FinancialTransactionService::class)->recordPayrollAccrual($period, $this->manager);

        // GL Verification:
        // Dr 5100 (Wages & Salaries Expense) = 200,000
        // Cr 2350 (Payroll Taxes Payable) = 40,000
        // Cr 2200 (Net Salaries Payable) = 160,000
        $wageExpense = $this->ledgerService->getAccountByCode($this->vendor, '5100');
        $taxPayable = $this->ledgerService->getAccountByCode($this->vendor, '2350');
        $salaryPayable = $this->ledgerService->getAccountByCode($this->vendor, '2200');

        $this->assertEquals(200000.00, $wageExpense->fresh()->current_balance);
        $this->assertEquals(40000.00, $taxPayable->fresh()->current_balance);
        $this->assertEquals(160000.00, $salaryPayable->fresh()->current_balance);

        // 2. Disburse Net Salary to Employee
        $this->hrPaymentService->recordSalaryPayment(
            $item,
            $this->manager,
            160000.00,
            'bank_transfer',
            'SAL-WIRE-NOV26'
        );

        // Net Salaries Payable liability settled to 0.00 AMD
        $this->assertEquals(0.00, $salaryPayable->fresh()->current_balance);

        // Anti-duplication check: Wage expense remains exactly 200,000.00 AMD on P&L (never duplicated to 360,000)
        $this->assertEquals(200000.00, $wageExpense->fresh()->current_balance);

        $pnl = $this->reportingService->getProfitAndLoss(
            $this->vendor,
            null,
            Carbon::today()->startOfMonth(),
            Carbon::today()->endOfMonth()
        );
        $this->assertEquals(200000.00, $pnl['operating_expenses']['total_opex']);
    }
}
