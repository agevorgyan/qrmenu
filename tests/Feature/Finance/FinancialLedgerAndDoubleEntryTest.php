<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FinancialLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialLedgerAndDoubleEntryTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialLedgerService $ledgerService;

    protected Vendor $vendor;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);

        $this->vendor = Vendor::create([
            'name' => 'Finance Bistro CJSC',
            'slug' => 'finance-bistro',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);
    }

    public function test_initializes_standard_chart_of_accounts(): void
    {
        $this->ledgerService->initializeDefaultAccounts($this->vendor);

        $accountsCount = ChartOfAccount::where('vendor_id', $this->vendor->id)->count();
        $this->assertGreaterThan(15, $accountsCount);

        $cash = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $this->assertEquals('asset', $cash->type);
        $this->assertTrue($cash->isDebitNormal());

        $revenue = $this->ledgerService->getAccountByCode($this->vendor, '4010');
        $this->assertEquals('revenue', $revenue->type);
        $this->assertTrue($revenue->isCreditNormal());
    }

    public function test_posts_balanced_journal_entry(): void
    {
        $this->ledgerService->initializeDefaultAccounts($this->vendor);
        $cash = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $revenue = $this->ledgerService->getAccountByCode($this->vendor, '4010');

        $entry = $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => Carbon::today()->toDateString(),
            'source_type' => 'manual_test',
            'source_id' => 1,
            'idempotency_key' => 'test-balanced-entry-1',
            'description' => 'Direct cash sale',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'type' => 'debit',
                    'amount' => 15000.00,
                    'memo' => 'Cash received',
                ],
                [
                    'account_id' => $revenue->id,
                    'type' => 'credit',
                    'amount' => 15000.00,
                    'memo' => 'Food revenue',
                ],
            ],
        ], $this->user);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'status' => 'posted',
            'total_debit' => 15000.00,
            'total_credit' => 15000.00,
        ]);

        $this->assertEquals(15000.00, $cash->fresh()->current_balance);
        $this->assertEquals(15000.00, $revenue->fresh()->current_balance);
    }

    public function test_rejects_unbalanced_journal_entry(): void
    {
        $this->ledgerService->initializeDefaultAccounts($this->vendor);
        $cash = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $revenue = $this->ledgerService->getAccountByCode($this->vendor, '4010');

        $this->expectException(ValidationException::class);

        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => Carbon::today()->toDateString(),
            'source_type' => 'manual_test',
            'source_id' => 2,
            'idempotency_key' => 'test-unbalanced-entry',
            'description' => 'Unbalanced error',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'type' => 'debit',
                    'amount' => 15000.00,
                ],
                [
                    'account_id' => $revenue->id,
                    'type' => 'credit',
                    'amount' => 14000.00, // Discrepancy of 1000
                ],
            ],
        ], $this->user);
    }

    public function test_idempotent_processing_prevents_duplicate_entries(): void
    {
        $this->ledgerService->initializeDefaultAccounts($this->vendor);
        $cash = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $revenue = $this->ledgerService->getAccountByCode($this->vendor, '4010');

        $payload = [
            'entry_date' => Carbon::today()->toDateString(),
            'source_type' => 'pos_payment',
            'source_id' => 88,
            'idempotency_key' => 'payment-88-settlement',
            'description' => 'Settlement for payment #88',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'type' => 'debit',
                    'amount' => 5000.00,
                ],
                [
                    'account_id' => $revenue->id,
                    'type' => 'credit',
                    'amount' => 5000.00,
                ],
            ],
        ];

        $entry1 = $this->ledgerService->postJournalEntry($this->vendor, $payload, $this->user);
        $entry2 = $this->ledgerService->postJournalEntry($this->vendor, $payload, $this->user);

        $this->assertEquals($entry1->id, $entry2->id);
        $this->assertEquals(1, JournalEntry::where('idempotency_key', 'payment-88-settlement')->count());
        $this->assertEquals(5000.00, $cash->fresh()->current_balance);
    }

    public function test_creates_reversal_entry_and_neutralizes_balances(): void
    {
        $this->ledgerService->initializeDefaultAccounts($this->vendor);
        $cash = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $revenue = $this->ledgerService->getAccountByCode($this->vendor, '4010');

        $entry = $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => Carbon::today()->toDateString(),
            'source_type' => 'manual_test',
            'source_id' => 3,
            'idempotency_key' => 'entry-to-be-reversed',
            'description' => 'Mistaken entry',
            'lines' => [
                [
                    'account_id' => $cash->id,
                    'type' => 'debit',
                    'amount' => 8000.00,
                ],
                [
                    'account_id' => $revenue->id,
                    'type' => 'credit',
                    'amount' => 8000.00,
                ],
            ],
        ], $this->user);

        $this->assertEquals(8000.00, $cash->fresh()->current_balance);

        $reversal = $this->ledgerService->createReversalEntry($entry, 'Customer dispute resolution', $this->user);

        $this->assertTrue($reversal->is_reversal);
        $this->assertEquals($entry->id, $reversal->reversal_of_entry_id);
        $this->assertEquals('reversed', $entry->fresh()->status);

        // Account balances should now be neutralized back to 0.00
        $this->assertEquals(0.00, $cash->fresh()->current_balance);
        $this->assertEquals(0.00, $revenue->fresh()->current_balance);
    }

    public function test_rejects_modifications_to_closed_accounting_period(): void
    {
        $this->ledgerService->initializeDefaultAccounts($this->vendor);
        $cash = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $revenue = $this->ledgerService->getAccountByCode($this->vendor, '4010');

        // Create and close period for last month
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $period = AccountingPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Last Month',
            'start_date' => $lastMonthStart->toDateString(),
            'end_date' => $lastMonthEnd->toDateString(),
            'status' => 'closed',
            'closed_at' => Carbon::now(),
            'closed_by' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->ledgerService->postJournalEntry($this->vendor, [
            'entry_date' => $lastMonthStart->addDays(5)->toDateString(),
            'source_type' => 'late_entry',
            'source_id' => 99,
            'idempotency_key' => 'late-entry-1',
            'description' => 'Late entry after period close',
            'lines' => [
                ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 1000.00],
                ['account_id' => $revenue->id, 'type' => 'credit', 'amount' => 1000.00],
            ],
        ], $this->user);
    }
}
