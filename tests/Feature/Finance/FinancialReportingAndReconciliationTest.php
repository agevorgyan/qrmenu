<?php

namespace Tests\Feature\Finance;

use App\Models\Location;
use App\Models\Order;
use App\Models\PosPayment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FinancialLedgerService;
use App\Services\FinancialReportingService;
use App\Services\FinancialTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportingAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $manager;

    protected FinancialLedgerService $ledgerService;

    protected FinancialTransactionService $transactionService;

    protected FinancialReportingService $reportingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);
        $this->transactionService = app(FinancialTransactionService::class);
        $this->reportingService = app(FinancialReportingService::class);

        $this->vendor = Vendor::create([
            'name' => 'Armenian Heritage Tavern',
            'slug' => 'armenian-heritage',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Cascade Branch',
            'slug' => 'cascade',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->ledgerService->initializeDefaultAccounts($this->vendor);
    }

    public function test_profit_and_loss_calculates_accurately_from_posted_ledger_entries(): void
    {
        $today = Carbon::today();
        $foodRev = $this->ledgerService->getAccountByCode($this->vendor, '4010');
        $bevRev = $this->ledgerService->getAccountByCode($this->vendor, '4020');
        $cashAcc = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $cogsAcc = $this->ledgerService->getAccountByCode($this->vendor, '5010');
        $inventoryAcc = $this->ledgerService->getAccountByCode($this->vendor, '1300');
        $utilAcc = $this->ledgerService->getAccountByCode($this->vendor, '5300');
        $apAcc = $this->ledgerService->getAccountByCode($this->vendor, '2010');

        // 1. Post Food (200,000) & Beverage (50,000) Revenue against Cash (250,000)
        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => $today->toDateString(),
            'source_type' => 'ManualJournal',
            'source_id' => 101,
            'location_id' => $this->location->id,
            'description' => 'Daily Sales Settlement',
            'lines' => [
                [
                    'account_id' => $cashAcc->id,
                    'type' => 'debit',
                    'amount' => 250000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $foodRev->id,
                    'type' => 'credit',
                    'amount' => 200000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $bevRev->id,
                    'type' => 'credit',
                    'amount' => 50000.00,
                    'currency' => 'AMD',
                ],
            ],
        ], $this->manager);

        // 2. Post COGS Expense (60,000) reducing Inventory Asset (60,000)
        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => $today->toDateString(),
            'source_type' => 'ManualJournal',
            'source_id' => 102,
            'location_id' => $this->location->id,
            'description' => 'Daily Kitchen Food Depletion',
            'lines' => [
                [
                    'account_id' => $cogsAcc->id,
                    'type' => 'debit',
                    'amount' => 60000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $inventoryAcc->id,
                    'type' => 'credit',
                    'amount' => 60000.00,
                    'currency' => 'AMD',
                ],
            ],
        ], $this->manager);

        // 3. Post Utilities OPEX (30,000) against Accounts Payable
        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => $today->toDateString(),
            'source_type' => 'ManualJournal',
            'source_id' => 103,
            'location_id' => $this->location->id,
            'description' => 'Electric Heating Utility Accrual',
            'lines' => [
                [
                    'account_id' => $utilAcc->id,
                    'type' => 'debit',
                    'amount' => 30000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $apAcc->id,
                    'type' => 'credit',
                    'amount' => 30000.00,
                    'currency' => 'AMD',
                ],
            ],
        ], $this->manager);

        $pnl = $this->reportingService->getProfitAndLoss(
            $this->vendor,
            $this->location->id,
            $today->copy()->startOfMonth(),
            $today->copy()->endOfMonth()
        );

        $this->assertEquals(250000.00, $pnl['revenue']['gross_revenue']);
        $this->assertEquals(250000.00, $pnl['revenue']['net_revenue']);
        $this->assertEquals(60000.00, $pnl['cogs']['total_cogs']);
        $this->assertEquals(190000.00, $pnl['gross_profit']);
        $this->assertEquals(30000.00, $pnl['operating_expenses']['total_opex']);
        $this->assertEquals(160000.00, $pnl['operating_profit']);
        $this->assertEquals(160000.00, $pnl['net_profit']);
    }

    public function test_balance_sheet_enforces_fundamental_accounting_equation(): void
    {
        $today = Carbon::today();
        $cashAcc = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $equityAcc = $this->ledgerService->getAccountByCode($this->vendor, '3010');

        // Capital contribution: Dr Cash 500,000 / Cr Equity 500,000
        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => $today->toDateString(),
            'source_type' => 'ManualJournal',
            'source_id' => 201,
            'description' => 'Initial Capital Injection',
            'lines' => [
                [
                    'account_id' => $cashAcc->id,
                    'type' => 'debit',
                    'amount' => 500000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $equityAcc->id,
                    'type' => 'credit',
                    'amount' => 500000.00,
                    'currency' => 'AMD',
                ],
            ],
        ], $this->manager);

        $bs = $this->reportingService->getBalanceSheet($this->vendor, null, $today);

        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals(0.00, $bs['discrepancy']);
        $this->assertEquals(500000.00, $bs['assets']['total_assets']);
        $this->assertEquals(500000.00, $bs['total_liabilities_and_equity']);
    }

    public function test_trial_balance_verifies_debits_equal_credits(): void
    {
        $today = Carbon::today();
        $cashAcc = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $bankAcc = $this->ledgerService->getAccountByCode($this->vendor, '1020');
        $equityAcc = $this->ledgerService->getAccountByCode($this->vendor, '3010');

        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => $today->toDateString(),
            'source_type' => 'ManualJournal',
            'source_id' => 301,
            'description' => 'Multi-account setup',
            'lines' => [
                [
                    'account_id' => $cashAcc->id,
                    'type' => 'debit',
                    'amount' => 120000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $bankAcc->id,
                    'type' => 'debit',
                    'amount' => 380000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $equityAcc->id,
                    'type' => 'credit',
                    'amount' => 500000.00,
                    'currency' => 'AMD',
                ],
            ],
        ], $this->manager);

        $tb = $this->reportingService->getTrialBalance($this->vendor, null, $today);

        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals(0.00, $tb['difference']);
        $this->assertEquals(500000.00, $tb['total_debit']);
        $this->assertEquals(500000.00, $tb['total_credit']);
    }

    public function test_single_source_of_truth_proof_preventing_duplicate_calculations(): void
    {
        $today = Carbon::today();

        // Simulate an Order of 15,000 AMD
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-PROOF-01',
            'order_type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 15000.00,
            'tax_amount' => 0.00,
            'total_amount' => 15000.00,
            'currency' => 'AMD',
        ]);

        // 1. Accrue Order Sale in General Ledger (Sales Dimension)
        $this->transactionService->recordOrderSale($order, $this->manager);

        // 2. Settle Order with Split Payments: 10,000 Cash + 5,000 Card (Settlement Dimension)
        $p1 = PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'user_id' => $this->manager->id,
            'payment_method' => 'cash',
            'amount' => 10000.00,
            'currency' => 'AMD',
            'status' => 'completed',
        ]);
        $p2 = PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'user_id' => $this->manager->id,
            'payment_method' => 'card',
            'amount' => 5000.00,
            'currency' => 'AMD',
            'status' => 'completed',
        ]);

        $this->transactionService->recordPaymentSettlement($order, $p1, $this->manager);
        $this->transactionService->recordPaymentSettlement($order, $p2, $this->manager);

        // 3. Query Financial Reporting P&L
        $pnl = $this->reportingService->getProfitAndLoss(
            $this->vendor,
            $this->location->id,
            $today->copy()->startOfDay(),
            $today->copy()->endOfDay()
        );

        // Mathematical Proof:
        // Naive summation of Order + Payments would equal 15,000 + 15,000 = 30,000 (Catastrophic duplicate!)
        // General Ledger Single Source of Truth must report EXACTLY 15,000.00 AMD
        $this->assertEquals(15000.00, $pnl['revenue']['net_revenue']);
        $this->assertEquals(15000.00, $pnl['revenue']['gross_revenue']);

        // Accounts Receivable (1200) should be 0.00 because the order sale was 100% settled by payments
        $arAccount = $this->ledgerService->getAccountByCode($this->vendor, '1200');
        $this->assertEquals(0.00, $arAccount->fresh()->current_balance);

        // Cash Till (1010) holds 10,000
        $cashTill = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $this->assertEquals(10000.00, $cashTill->fresh()->current_balance);

        // Card Clearing (1030) holds 5,000
        $cardClearing = $this->ledgerService->getAccountByCode($this->vendor, '1030');
        $this->assertEquals(5000.00, $cardClearing->fresh()->current_balance);
    }
}
