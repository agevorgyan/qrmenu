<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesalePayment;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Domains\Wholesale\Services\WholesaleAccountingService;
use App\Domains\Wholesale\Services\WholesaleOrderService;
use App\Models\CashRegister;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Location;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FinancialLedgerService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleGeneralLedgerPostingTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

    protected Unit $unitKg;

    protected WholesaleSeller $sellerA;

    protected WholesaleCustomer $customerA;

    protected WholesaleProduct $productPelmeni;

    protected WholesaleOrderService $orderService;

    protected WholesaleAccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::first();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Downtown Central',
            'slug' => 'downtown-central',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Superadmin
        $this->superadmin = User::create([
            'name' => 'Master Super Admin',
            'email' => 'super@admin.am',
            'password' => bcrypt('password123'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        app(WholesaleAccessService::class)->grantAccess($this->vendorA, $this->superadmin, null, 'Entitlement A');

        $this->unitKg = Unit::create([
            'vendor_id' => null,
            'name' => 'Kilogram',
            'code' => 'kg',
            'symbol' => 'kg',
            'type' => 'mass',
            'is_active' => true,
        ]);

        // Seller
        $this->sellerA = WholesaleSeller::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Wholesale Foods LLC',
            'legal_name' => 'Alpha Wholesale Foods LLC',
            'tin' => '12345678',
            'tax_regime' => SellerTaxRegime::VAT_PAYER,
            'address' => 'Yerevan, Arshakunyats 15',
            'is_active' => true,
        ]);

        // Customer
        $this->customerA = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'SAS Supermarkets',
            'tin' => '02555555',
            'tax_regime' => 'vat_payer',
            'credit_limit' => 500000.00,
            'is_credit_allowed' => true,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        // Product
        $this->productPelmeni = WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Պելմենի Ֆիրմային (1կգ)',
            'sku' => 'PELM-FIRM-1000',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 2000.00,
            'min_wholesale_price' => 1600.00,
            'is_vat_applicable' => true,
            'vat_rate' => 20.00,
            'is_active' => true,
        ]);

        $this->orderService = app(WholesaleOrderService::class);
        $this->accountingService = app(WholesaleAccountingService::class);
    }

    public function test_commercial_sale_posts_balanced_double_entry_to_general_ledger(): void
    {
        // 10 units at 2,000 AMD gross = 20,000 Total, 16,666.67 Subtotal + 3,333.33 VAT (20% inclusive)
        $sale = $this->orderService->createSale($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 10],
            ],
        ], null, $this->ownerA);

        $journalEntry = $this->accountingService->postSaleToLedger($sale, $this->ownerA);

        $this->assertNotNull($journalEntry);
        $this->assertEquals($this->vendorA->id, $journalEntry->vendor_id);
        $this->assertEquals(20000.00, (float) $journalEntry->total_debit);
        $this->assertEquals(20000.00, (float) $journalEntry->total_credit);

        // Verify GL Lines:
        // Debit: 2211 Trade Receivables (20,000)
        // Credit: 6111 Sales Revenue (16,666.67)
        // Credit: 5243 Armenia SRC VAT Payable (3,333.33)
        $lines = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->with('account')->get();

        $debitLine = $lines->firstWhere('type', 'debit');
        $this->assertEquals('2211', $debitLine->account->code);
        $this->assertEquals(20000.00, (float) $debitLine->amount);

        $creditRevenue = $lines->where('type', 'credit')->first(fn ($l) => $l->account->code === '6111');
        $this->assertNotNull($creditRevenue);
        $this->assertEquals(16666.67, (float) $creditRevenue->amount);

        $creditVat = $lines->where('type', 'credit')->first(fn ($l) => $l->account->code === '5243');
        $this->assertNotNull($creditVat);
        $this->assertEquals(3333.33, (float) $creditVat->amount);
    }

    public function test_recording_bank_transfer_payment_reduces_ar_and_posts_to_bank_account_2521(): void
    {
        // Create an unpaid sale of 30,000 AMD (15 units * 2000 gross)
        $sale = $this->orderService->createSale($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'paid_amount' => 0,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 15],
            ],
        ], null, $this->ownerA);

        $this->assertEquals(30000.00, (float) $this->customerA->fresh()->current_balance);
        $this->assertEquals('ISSUED', $sale->status);

        // Record Bank Wire payment of 30,000 AMD allocated to this sale
        $payment = $this->accountingService->recordPayment($this->vendorA, [
            'customer_id' => $this->customerA->id,
            'seller_id' => $this->sellerA->id,
            'payment_method' => 'BANK_TRANSFER',
            'amount' => 30000.00,
            'reference_number' => 'AMERIABANK-WIRE-9922',
            'sale_id' => $sale->id,
        ], $this->ownerA);

        $this->assertEquals('COMPLETED', $payment->status);
        $this->assertEquals(30000.00, $payment->allocated_amount);
        $this->assertEquals(0.00, $payment->unallocated_amount);

        // Customer AR balance must be fully cleared to 0
        $this->assertEquals(0.00, (float) $this->customerA->fresh()->current_balance);

        // Sale status must be transitioned to PAID
        $this->assertEquals('PAID', $sale->fresh()->status);
        $this->assertEquals(30000.00, (float) $sale->fresh()->paid_amount);

        // Verify GL Double-Entry:
        // Debit: 2521 Operating Bank Account (30,000)
        // Credit: 2211 Trade Receivables (30,000)
        $this->assertNotNull($payment->journal_entry_id);
        $je = JournalEntry::find($payment->journal_entry_id);
        $this->assertEquals(30000.00, (float) $je->total_debit);
        $this->assertEquals(30000.00, (float) $je->total_credit);

        $debitLine = $je->lines->firstWhere('type', 'debit');
        $this->assertEquals('2521', $debitLine->account->code);

        $creditLine = $je->lines->firstWhere('type', 'credit');
        $this->assertEquals('2211', $creditLine->account->code);
    }

    public function test_recording_cash_payment_into_register_creates_pos_cash_movement(): void
    {
        $tillAccount = app(FinancialLedgerService::class)->getAccountByCode($this->vendorA, '2511');

        $cashRegister = CashRegister::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'account_id' => $tillAccount->id,
            'name' => 'Main Front Cash Drawer',
            'code' => 'CR-FRONT-01',
            'is_active' => true,
        ]);

        $activeShift = PosShift::create([
            'vendor_id' => $this->vendorA->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $this->ownerA->id,
            'start_time' => now(),
            'opening_cash' => 50000.00,
            'status' => 'open',
        ]);

        $this->customerA->update(['current_balance' => 15000.00]);

        // Record Cash Payment of 15,000 AMD into Cash Register
        $payment = $this->accountingService->recordPayment($this->vendorA, [
            'customer_id' => $this->customerA->id,
            'seller_id' => $this->sellerA->id,
            'payment_method' => 'CASH',
            'amount' => 15000.00,
            'cash_register_id' => $cashRegister->id,
        ], $this->ownerA);

        $this->assertEquals(0.00, (float) $this->customerA->fresh()->current_balance);

        // Verify PosCashMovement created in active shift
        $movement = PosCashMovement::where('vendor_id', $this->vendorA->id)
            ->where('cash_register_id', $cashRegister->id)
            ->where('reason', 'WHOLESALE_PAYMENT')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(15000.00, (float) $movement->amount);
        $this->assertEquals('cash_in', $movement->type);
        $this->assertEquals($activeShift->id, $movement->pos_shift_id);
    }

    public function test_accounts_receivable_summary_and_aging_buckets(): void
    {
        // Customer A owes 50,000
        $this->customerA->update(['current_balance' => 50000.00]);

        $summary = $this->accountingService->getAccountsReceivableSummary($this->vendorA);

        $this->assertEquals(50000.00, $summary['total_receivables']);
        $this->assertEquals(1, $summary['active_debtors_count']);
        $this->assertEquals(500000.00, $summary['total_credit_limit']);
    }

    public function test_cross_tenant_isolation_protects_payments(): void
    {
        app(WholesaleAccessService::class)->grantAccess($this->vendorB, $this->superadmin, null, 'Entitlement B');

        $paymentA = WholesalePayment::create([
            'vendor_id' => $this->vendorA->id,
            'customer_id' => $this->customerA->id,
            'seller_id' => $this->sellerA->id,
            'payment_number' => 'WP-TEST-ALPHA',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'BANK_TRANSFER',
            'amount' => 10000.00,
        ]);

        // Tenant B cannot view Tenant A's payment -> 404
        $response = $this->actingAs($this->ownerB)->get(route('admin.wholesale.payments.show', $paymentA));
        $response->assertStatus(404);
    }
}
