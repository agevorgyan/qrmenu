<?php

namespace Tests\Feature\Finance;

use App\Models\FinancialCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BankReconciliationService;
use App\Services\ExpenseService;
use App\Services\FinancialLedgerService;
use App\Services\FinancialTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $manager;

    protected FinancialLedgerService $ledgerService;

    protected FinancialTransactionService $transactionService;

    protected BankReconciliationService $bankService;

    protected ExpenseService $expenseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);
        $this->transactionService = app(FinancialTransactionService::class);
        $this->bankService = app(BankReconciliationService::class);
        $this->expenseService = app(ExpenseService::class);

        $this->vendor = Vendor::create([
            'name' => 'Grand Yerevan Restaurant',
            'slug' => 'grand-yerevan',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Restaurant',
            'slug' => 'main-restaurant',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->ledgerService->initializeDefaultAccounts($this->vendor);
    }

    public function test_creates_bank_account_linked_to_chart_of_accounts(): void
    {
        $bankAccount = $this->bankService->createBankAccount($this->vendor, [
            'account_name' => 'Ameriabank Main Checking',
            'bank_name' => 'Ameriabank CJSC',
            'account_number' => '1570023456780100',
            'iban' => 'AM98AMER1570023456780100',
            'swift' => 'AMERAM22',
            'currency' => 'AMD',
            'opening_balance' => 500000.00,
            'location_id' => $this->location->id,
        ], $this->manager);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccount->id,
            'account_number' => '1570023456780100',
            'current_balance' => 500000.00,
        ]);

        $this->assertNotNull($bankAccount->account_id);
        $this->assertTrue(in_array($bankAccount->account->code, ['1020', '2521'], true));

        // Attempting duplicate account number registration must fail
        $this->expectException(ValidationException::class);
        $this->bankService->createBankAccount($this->vendor, [
            'account_name' => 'Duplicate Account',
            'bank_name' => 'Ameriabank CJSC',
            'account_number' => '1570023456780100',
        ], $this->manager);
    }

    public function test_imports_bank_statement_with_duplicate_skipping(): void
    {
        $bankAccount = $this->bankService->createBankAccount($this->vendor, [
            'account_name' => 'Ardshinbank Operations',
            'bank_name' => 'Ardshinbank CJSC',
            'account_number' => '2470098765430100',
            'currency' => 'AMD',
            'opening_balance' => 100000.00,
        ], $this->manager);

        $statementLines = [
            [
                'transaction_date' => '2026-11-01',
                'type' => 'credit',
                'amount' => 45000.00,
                'reference_number' => 'TX-20261101-01',
                'counterparty_name' => 'Acba POS Batch Payout',
                'description' => 'Card Settlement',
            ],
            [
                'transaction_date' => '2026-11-02',
                'type' => 'debit',
                'amount' => 15000.00,
                'reference_number' => 'TX-20261102-02',
                'counterparty_name' => 'Armenia Electricity Utility',
                'description' => 'Utility Payment',
            ],
        ];

        // 1. Initial import
        $result = $this->bankService->importTransactions($bankAccount, $statementLines, $this->manager);
        $this->assertEquals(2, $result['imported_count']);
        $this->assertEquals(0, $result['skipped_count']);
        $this->assertCount(2, $bankAccount->transactions);

        // 2. Re-import of the exact same statement lines must be skipped
        $reimportResult = $this->bankService->importTransactions($bankAccount, $statementLines, $this->manager);
        $this->assertEquals(0, $reimportResult['imported_count']);
        $this->assertEquals(2, $reimportResult['skipped_count']);
        $this->assertCount(2, $bankAccount->fresh()->transactions);
    }

    public function test_auto_matches_statement_lines_with_system_expense_payments(): void
    {
        $bankAccount = $this->bankService->createBankAccount($this->vendor, [
            'account_name' => 'Converse Operating',
            'bank_name' => 'Converse Bank',
            'account_number' => '193005544332211',
            'currency' => 'AMD',
            'opening_balance' => 300000.00,
        ], $this->manager);

        $category = FinancialCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Dining Supplies',
            'code' => 'CAT-SUPPLIES',
            'type' => 'expense',
            'account_id' => $this->ledgerService->getAccountByCode($this->vendor, '5500')->id,
        ]);

        $expense = $this->expenseService->createExpense($this->vendor, [
            'financial_category_id' => $category->id,
            'location_id' => $this->location->id,
            'title' => 'Table Napkins and Cutlery',
            'total_amount' => 35000.00,
            'expense_date' => Carbon::today()->toDateString(),
        ], $this->manager);

        $this->expenseService->approveExpense($expense, $this->manager);

        // Record wire payment with distinctive reference
        $wireRef = 'WIRE-2026-7890';
        $payment = $this->expenseService->recordPayment($expense, [
            'amount' => 35000.00,
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $bankAccount->id,
            'reference_number' => $wireRef,
        ], $this->manager);

        // Import bank statement containing this outgoing debit
        $result = $this->bankService->importTransactions($bankAccount, [
            [
                'transaction_date' => Carbon::today()->toDateString(),
                'type' => 'debit',
                'amount' => 35000.00,
                'reference_number' => $wireRef,
                'counterparty_name' => 'Horeca Supplies LLC',
                'description' => 'Invoice payment napkins',
            ],
        ], $this->manager);

        $this->assertEquals(1, $result['imported_count']);
        $this->assertEquals(1, $result['matched_count']);

        $tx = $bankAccount->fresh()->transactions()->first();
        $this->assertEquals('matched', $tx->reconciliation_status);
        $this->assertEquals('ExpensePayment', $tx->matched_source_type);
        $this->assertEquals($payment->id, $tx->matched_source_id);
    }

    public function test_performs_bank_reconciliation_and_calculates_difference(): void
    {
        $bankAccount = $this->bankService->createBankAccount($this->vendor, [
            'account_name' => 'Evocabank Main',
            'bank_name' => 'Evocabank CJSC',
            'account_number' => '166007788990011',
            'currency' => 'AMD',
            'opening_balance' => 0.00,
        ], $this->manager);

        // Post a GL entry depositing 150,000 to bank account 1020
        $bankGlAccount = $bankAccount->account;
        $ownerEquityAccount = $this->ledgerService->getAccountByCode($this->vendor, '3010');

        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => Carbon::today()->toDateString(),
            'source_type' => 'ManualJournal',
            'source_id' => 1,
            'description' => 'Owner capital deposit to bank',
            'lines' => [
                [
                    'account_id' => $bankGlAccount->id,
                    'type' => 'debit',
                    'amount' => 150000.00,
                    'currency' => 'AMD',
                ],
                [
                    'account_id' => $ownerEquityAccount->id,
                    'type' => 'credit',
                    'amount' => 150000.00,
                    'currency' => 'AMD',
                ],
            ],
        ], $this->manager);

        // Perform reconciliation when statement ending balance matches GL book balance exactly
        $reconciliation = $this->bankService->performReconciliation($bankAccount, [
            'statement_date' => Carbon::today()->toDateString(),
            'statement_ending_balance' => 150000.00,
            'notes' => 'Month-end reconciliation verified',
        ], $this->manager);

        $this->assertEquals(150000.00, $reconciliation->statement_ending_balance);
        $this->assertEquals(150000.00, $reconciliation->gl_book_balance);
        $this->assertEquals(0.00, $reconciliation->difference);
        $this->assertEquals('completed', $reconciliation->status);
        $this->assertEquals(150000.00, $bankAccount->fresh()->current_balance);
    }
}
