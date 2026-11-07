<?php

namespace Tests\Feature\Finance;

use App\Models\Location;
use App\Models\Order;
use App\Models\PosPayment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FinancialAuditService;
use App\Services\FinancialLedgerService;
use App\Services\FinancialReportingService;
use App\Services\FinancialTransactionService;
use App\Services\RefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class IndependentFinancialAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $manager;

    protected FinancialLedgerService $ledgerService;

    protected FinancialTransactionService $transactionService;

    protected FinancialReportingService $reportingService;

    protected FinancialAuditService $auditService;

    protected RefundService $refundService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);
        $this->transactionService = app(FinancialTransactionService::class);
        $this->reportingService = app(FinancialReportingService::class);
        $this->auditService = app(FinancialAuditService::class);
        $this->refundService = app(RefundService::class);

        $this->vendor = Vendor::create([
            'name' => 'Independent Audit Bistro',
            'slug' => 'audit-bistro',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Audit Branch',
            'slug' => 'audit-branch',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->ledgerService->initializeDefaultAccounts($this->vendor);
    }

    public function test_duplicate_order_sale_events_are_strictly_idempotent(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-01',
            'status' => 'completed',
            'subtotal' => 20000.00,
            'total_amount' => 20000.00,
            'currency' => 'AMD',
        ]);

        // Event 1
        $e1 = $this->transactionService->recordOrderSale($order, $this->manager);
        // Duplicate Event 2 (e.g. queue retry, network replay)
        $e2 = $this->transactionService->recordOrderSale($order, $this->manager);

        $this->assertEquals($e1->id, $e2->id);
        $count = DB::table('journal_entries')
            ->where('vendor_id', $this->vendor->id)
            ->where('source_type', 'Order')
            ->where('source_id', $order->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_duplicate_payment_webhooks_prevent_double_settlement(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-02',
            'status' => 'completed',
            'subtotal' => 12000.00,
            'total_amount' => 12000.00,
            'currency' => 'AMD',
        ]);

        $this->transactionService->recordOrderSale($order, $this->manager);

        $payment = PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'user_id' => $this->manager->id,
            'payment_method' => 'card',
            'amount' => 12000.00,
            'currency' => 'AMD',
            'status' => 'completed',
        ]);

        // Webhook delivery 1
        $e1 = $this->transactionService->recordPaymentSettlement($order, $payment, $this->manager);
        // Duplicate Webhook delivery 2
        $e2 = $this->transactionService->recordPaymentSettlement($order, $payment, $this->manager);

        $this->assertEquals($e1->id, $e2->id);

        $paymentEntries = DB::table('journal_entries')
            ->where('vendor_id', $this->vendor->id)
            ->where('source_type', 'PosPayment')
            ->where('source_id', $payment->id)
            ->count();
        $this->assertEquals(1, $paymentEntries);

        // Accounts Receivable (1200) settled exactly to 0.00 AMD
        $ar = $this->ledgerService->getAccountByCode($this->vendor, '1200');
        $this->assertEquals(0.00, $ar->fresh()->current_balance);
    }

    public function test_duplicate_refund_requests_are_idempotent(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-03',
            'status' => 'completed',
            'paid_amount' => 18000.00,
            'subtotal' => 18000.00,
            'total_amount' => 18000.00,
            'currency' => 'AMD',
        ]);

        $this->transactionService->recordOrderSale($order, $this->manager);

        // Attempt identical refund requests twice
        $refundKey = 'ref-webhook-998811';
        $e1 = $this->transactionService->recordOrderRefund($order, 18000.00, 'cash', 'Customer dissatisfaction', $this->manager, $refundKey);
        $e2 = $this->transactionService->recordOrderRefund($order, 18000.00, 'cash', 'Customer dissatisfaction', $this->manager, $refundKey);

        $this->assertEquals($e1->id, $e2->id);

        $refundEntries = DB::table('journal_entries')
            ->where('vendor_id', $this->vendor->id)
            ->where('source_type', 'Refund')
            ->count();
        $this->assertEquals(1, $refundEntries);
    }

    public function test_cancelled_order_cannot_post_sales_revenue(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-04',
            'status' => 'cancelled',
            'subtotal' => 25000.00,
            'total_amount' => 25000.00,
            'currency' => 'AMD',
        ]);

        $this->expectException(ValidationException::class);
        $this->transactionService->recordOrderSale($order, $this->manager);
    }

    public function test_cancelled_order_reversal_wipes_premature_revenue(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-05',
            'status' => 'completed',
            'subtotal' => 30000.00,
            'total_amount' => 30000.00,
            'currency' => 'AMD',
        ]);

        // Post original sale
        $orig = $this->transactionService->recordOrderSale($order, $this->manager);
        $this->assertEquals('posted', $orig->status);

        // Reverse when customer leaves before eating
        $reversal = $this->transactionService->reverseOrderSale($order, 'Walkout before food served', $this->manager);
        $this->assertNotNull($reversal);
        $this->assertTrue($reversal->is_reversal);
        $this->assertEquals($orig->id, $reversal->reversal_of_entry_id);

        // Verify Net Revenue on P&L is 0.00 AMD
        $pnl = $this->reportingService->getProfitAndLoss(
            $this->vendor,
            null,
            Carbon::today()->startOfMonth(),
            Carbon::today()->endOfMonth()
        );
        $this->assertEquals(0.00, $pnl['revenue']['net_revenue']);
    }

    public function test_posted_journal_entries_and_lines_are_immutable_against_deletion(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-06',
            'status' => 'completed',
            'subtotal' => 10000.00,
            'total_amount' => 10000.00,
            'currency' => 'AMD',
        ]);

        $entry = $this->transactionService->recordOrderSale($order, $this->manager);

        // Attempting to delete a posted entry must throw LogicException
        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_posted_journal_entry_lines_are_immutable_against_deletion(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-07',
            'status' => 'completed',
            'subtotal' => 10000.00,
            'total_amount' => 10000.00,
            'currency' => 'AMD',
        ]);

        $entry = $this->transactionService->recordOrderSale($order, $this->manager);
        $line = $entry->lines()->first();

        // Attempting to delete a line from a posted entry must throw LogicException
        $this->expectException(LogicException::class);
        $line->delete();
    }

    public function test_cryptographic_hmac_hash_chain_detects_malicious_database_alteration(): void
    {
        $cashAcc = $this->ledgerService->getAccountByCode($this->vendor, '1010');
        $equityAcc = $this->ledgerService->getAccountByCode($this->vendor, '3010');

        // Post 3 consecutive balanced entries
        for ($i = 1; $i <= 3; $i++) {
            $this->ledgerService->postJournalEntry($this->vendor, [
                'entry_date' => Carbon::today()->toDateString(),
                'source_type' => 'ManualJournal',
                'source_id' => 800 + $i,
                'description' => "Contribution round {$i}",
                'lines' => [
                    [
                        'account_id' => $cashAcc->id,
                        'type' => 'debit',
                        'amount' => 50000.00,
                        'currency' => 'AMD',
                    ],
                    [
                        'account_id' => $equityAcc->id,
                        'type' => 'credit',
                        'amount' => 50000.00,
                        'currency' => 'AMD',
                    ],
                ],
            ], $this->manager);
        }

        // 1. Audit reports pristine health
        $report1 = $this->auditService->verifyTamperIntegrity($this->vendor);
        $this->assertTrue($report1['is_valid']);
        $this->assertEquals('healthy', $report1['status']);

        // 2. Malicious actor alters entry #2 debit amount directly in the database
        $entry2 = DB::table('journal_entries')
            ->where('vendor_id', $this->vendor->id)
            ->where('source_id', 802)
            ->first();

        DB::table('journal_entries')
            ->where('id', $entry2->id)
            ->update(['total_debit' => 99999.00]);

        // 3. Cryptographic integrity check flags the signature mismatch and breaks the chain
        $report2 = $this->auditService->verifyTamperIntegrity($this->vendor);
        $this->assertFalse($report2['is_valid']);
        $this->assertEquals('compromised', $report2['status']);

        $issueTypes = array_column($report2['issues'], 'type');
        $this->assertTrue(in_array('cryptographic_signature_mismatch', $issueTypes, true) || in_array('unbalanced_journal_entry', $issueTypes, true));
    }

    public function test_service_charge_delivery_fee_and_discounts_accounting_balance(): void
    {
        // Order with:
        // Subtotal = 20,000 (Food)
        // Service Fee = 2,000 (10%)
        // Delivery Fee = 1,000
        // Discount = 1,500
        // Tax = 3,000
        // Total = 20,000 + 2,000 + 1,000 + 3,000 - 1,500 = 24,500 AMD
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-AUDIT-FULL-FEES',
            'status' => 'completed',
            'subtotal' => 20000.00,
            'service_fee' => 2000.00,
            'delivery_fee' => 1000.00,
            'discount_amount' => 1500.00,
            'tax_amount' => 3000.00,
            'total_amount' => 24500.00,
            'currency' => 'AMD',
        ]);

        $entry = $this->transactionService->recordOrderSale($order, $this->manager);

        // Verification:
        // Debits: AR (1200) = 24,500 + Promo Discounts (4910) = 1,500 => Total Debit = 26,000
        // Credits: Food Sales (4010) = 20,000 + Service Charge (4030) = 2,000 + Delivery (4100) = 1,000 + VAT (2300) = 3,000 => Total Credit = 26,000
        $this->assertEquals(26000.00, (float) $entry->total_debit);
        $this->assertEquals(26000.00, (float) $entry->total_credit);
        $this->assertTrue($entry->isBalanced());

        $foodRev = $this->ledgerService->getAccountByCode($this->vendor, '4010');
        $serviceRev = $this->ledgerService->getAccountByCode($this->vendor, '4030');
        $deliveryRev = $this->ledgerService->getAccountByCode($this->vendor, '4100');
        $discountContra = $this->ledgerService->getAccountByCode($this->vendor, '4910');

        $this->assertEquals(20000.00, (float) $foodRev->fresh()->current_balance);
        $this->assertEquals(2000.00, (float) $serviceRev->fresh()->current_balance);
        $this->assertEquals(1000.00, (float) $deliveryRev->fresh()->current_balance);
        $this->assertEquals(1500.00, (float) $discountContra->fresh()->current_balance);
    }
}
