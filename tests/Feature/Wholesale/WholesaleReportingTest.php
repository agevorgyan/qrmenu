<?php

declare(strict_types=1);

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Models\WholesaleCategory;
use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleCustomerAddress;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSaleItem;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Models\WholesaleSellerBankAccount;
use App\Domains\Wholesale\Models\WholesaleSellerLocation;
use App\Domains\Wholesale\Models\WholesaleSellerTaxProfile;
use App\Domains\Wholesale\Services\WholesaleReportService;
use App\Domains\Wholesale\Services\WholesaleTaxDocumentService;
use App\Models\SubscriptionPlan;
use App\Models\TenantModuleAccess;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleReportingTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected WholesaleSeller $seller;

    protected WholesaleCustomer $customer;

    protected WholesaleProduct $product1;

    protected WholesaleProduct $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::first();

        $this->vendor = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        TenantModuleAccess::create([
            'vendor_id' => $this->vendor->id,
            'module_key' => 'wholesale_pos',
            'is_enabled' => true,
            'granted_at' => now(),
        ]);

        // Seller
        $this->seller = WholesaleSeller::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Sales Plus LLC',
            'legal_name' => '«ՍԵՅԼՍ ՊԼՅՈՒՍ» ՍՊԸ',
            'tin' => '08293072',
            'tax_regime' => 'vat_payer',
            'address' => 'ԵՐԵՎԱՆ ԴԱՎԹԱՇԵՆ 1 ԹՂՄ. 38 7 ԲՆ.',
            'pek_username' => 'salesplus25',
            'is_active' => true,
        ]);
        WholesaleSellerTaxProfile::create([
            'seller_id' => $this->seller->id,
            'tax_regime' => 'vat_payer',
            'is_vat_payer' => true,
            'vat_rate' => 20.00,
            'document_series' => 'A',
            'effective_from' => now()->subYear(),
        ]);
        WholesaleSellerLocation::create([
            'seller_id' => $this->seller->id,
            'name' => 'Main Warehouse',
            'address' => 'Հայաստան, ԿՈՏԱՅՔ, ԶՈՎՈՒՆԻ, ԶՈՎՈՒՆԻ, 30փ 2նրբ 31, 182տար',
            'is_default' => true,
        ]);
        WholesaleSellerBankAccount::create([
            'seller_id' => $this->seller->id,
            'bank_name' => 'ԱԿԲԱ ԲԱՆԿ ԲԲԸ',
            'account_number' => '220001234567890',
            'is_default' => true,
        ]);

        // Customer
        $this->customer = WholesaleCustomer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'SDS Supermarket LLC',
            'legal_name' => '«ՍԴՍ ՍՈՒՊԵՐՄԱՐԿԵՏ» ՍՊԸ',
            'tin' => '03576728',
            'tax_regime' => 'vat_payer',
            'current_balance' => 4350.00,
            'credit_limit' => 500000.00,
            'is_active' => true,
        ]);
        WholesaleCustomerAddress::create([
            'customer_id' => $this->customer->id,
            'name' => 'Store #1',
            'address_line1' => 'ԴԱՎԹԱՇԵՆ 1 ԹՂՄ. 38 7 ԲՆ.',
            'city' => 'ԵՐԵՎԱՆ',
            'is_default' => true,
        ]);

        // Unit
        $unit = Unit::create([
            'name' => 'հատ',
            'code' => 'հատ',
            'symbol' => 'հատ',
            'type' => 'unit',
        ]);

        // Category & Products
        $category = WholesaleCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Prepared Foods',
        ]);

        $this->product1 = WholesaleProduct::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'sku' => 'PELM-FIRM-450',
            'name' => 'Պելմենի Ֆիրմային (450գր)',
            'classifier_code' => '1602',
            'unit_id' => $unit->id,
            'base_wholesale_price' => 1208.33,
            'min_wholesale_price' => 1000.00,
            'is_active' => true,
        ]);

        $this->product2 = WholesaleProduct::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'sku' => 'PASTA-FUSI-500',
            'name' => 'Մակարոն Ֆուզիլի (500գր)',
            'classifier_code' => '1902',
            'unit_id' => $unit->id,
            'base_wholesale_price' => 600.00,
            'min_wholesale_price' => 500.00,
            'is_active' => true,
        ]);

        // Create Sale
        $sale = WholesaleSale::create([
            'vendor_id' => $this->vendor->id,
            'seller_id' => $this->seller->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'WS-20261108-0099',
            'sale_date' => now()->toDateString(),
            'subtotal' => 6025.00,
            'discount_total' => 0.00,
            'tax_total' => 1205.00,
            'delivery_fee' => 0.00,
            'total_amount' => 7230.00,
            'paid_amount' => 2880.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'ISSUED',
        ]);

        WholesaleSaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'sku' => $this->product1->sku,
            'classifier_code' => '1602',
            'unit_code' => 'unit',
            'quantity' => 3,
            'unit_price' => 1208.33333,
            'subtotal' => 3625.00,
            'vat_amount' => 725.00,
            'total_amount' => 4350.00,
        ]);

        WholesaleSaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product2->id,
            'product_name' => $this->product2->name,
            'sku' => $this->product2->sku,
            'classifier_code' => '1902',
            'unit_code' => 'unit',
            'quantity' => 4,
            'unit_price' => 600.00,
            'subtotal' => 2400.00,
            'vat_amount' => 480.00,
            'total_amount' => 2880.00,
        ]);

        // Generate Tax Document
        app(WholesaleTaxDocumentService::class)->createTaxDocumentFromSale($sale, $this->user);
    }

    public function test_report_service_computes_executive_summary(): void
    {
        $service = app(WholesaleReportService::class);
        $summary = $service->getExecutiveSummary($this->vendor);

        $this->assertEquals(7230.00, $summary['total_revenue']);
        $this->assertEquals(6025.00, $summary['total_taxable']);
        $this->assertEquals(1205.00, $summary['total_vat']);
        $this->assertEquals(2880.00, $summary['total_paid']);
        $this->assertEquals(1, $summary['sales_count']);
        $this->assertEquals(4350.00, $summary['outstanding_ar']);
    }

    public function test_report_service_computes_sales_by_seller_and_customer(): void
    {
        $service = app(WholesaleReportService::class);

        $bySeller = $service->getSalesBySeller($this->vendor);
        $this->assertCount(1, $bySeller);
        $this->assertEquals($this->seller->id, $bySeller->first()->seller_id);
        $this->assertEquals(7230.00, (float) $bySeller->first()->grand_total);

        $byCustomer = $service->getSalesByCustomer($this->vendor);
        $this->assertCount(1, $byCustomer);
        $this->assertEquals($this->customer->id, $byCustomer->first()->customer_id);
        $this->assertEquals(7230.00, (float) $byCustomer->first()->total_purchased);
    }

    public function test_report_service_computes_sales_by_product_and_atgaa(): void
    {
        $service = app(WholesaleReportService::class);

        $byProduct = $service->getSalesByProduct($this->vendor);
        $this->assertCount(2, $byProduct);

        $byAtgaa = $service->getSalesByAtgaa($this->vendor);
        $this->assertCount(2, $byAtgaa);

        $atgaa1602 = $byAtgaa->firstWhere('atgaa_code', '1602');
        $this->assertNotNull($atgaa1602);
        $this->assertEquals(3.0, (float) $atgaa1602->total_units_sold);
        $this->assertEquals(4350.00, (float) $atgaa1602->total_gross);

        $atgaa1902 = $byAtgaa->firstWhere('atgaa_code', '1902');
        $this->assertNotNull($atgaa1902);
        $this->assertEquals(4.0, (float) $atgaa1902->total_units_sold);
        $this->assertEquals(2880.00, (float) $atgaa1902->total_gross);
    }

    public function test_report_service_computes_tax_summary(): void
    {
        $service = app(WholesaleReportService::class);
        $taxSummary = $service->getTaxSummary($this->vendor);

        $this->assertEquals(1, $taxSummary['vat']['count']);
        $this->assertEquals(6025.00, $taxSummary['vat']['taxable']);
        $this->assertEquals(1205.00, $taxSummary['vat']['vat_amount']);
        $this->assertEquals(7230.00, $taxSummary['vat']['total']);
        $this->assertEquals(0, $taxSummary['turnover']['count']);
    }

    public function test_report_controller_index_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.wholesale.reports.index'));

        $response->assertOk();
        $response->assertSee('Wholesale Analytics &amp; Tax Reports', false);
        $response->assertSee('7,230.00 AMD');
        $response->assertSee('1,205.00 AMD');
        $response->assertSee('1602');
        $response->assertSee('1902');
        $response->assertSee('Sales Plus LLC');
    }

    public function test_report_controller_csv_export(): void
    {
        $atgaaCsvResp = $this->actingAs($this->user)
            ->get(route('admin.wholesale.reports.export', ['type' => 'atgaa']));
        $atgaaCsvResp->assertOk();
        $atgaaCsvResp->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('1602', $atgaaCsvResp->getContent());
        $this->assertStringContainsString('1902', $atgaaCsvResp->getContent());

        $sellersCsvResp = $this->actingAs($this->user)
            ->get(route('admin.wholesale.reports.export', ['type' => 'sellers']));
        $sellersCsvResp->assertOk();
        $this->assertStringContainsString('08293072', $sellersCsvResp->getContent());
    }

    public function test_cross_tenant_report_isolation(): void
    {
        $plan = SubscriptionPlan::first();
        $otherVendor = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);
        $otherUser = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $otherVendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);
        TenantModuleAccess::create([
            'vendor_id' => $otherVendor->id,
            'module_key' => 'wholesale_pos',
            'is_enabled' => true,
        ]);

        // User from Tenant B viewing reports must see 0 revenue (isolated)
        $response = $this->actingAs($otherUser)
            ->get(route('admin.wholesale.reports.index'));
        $response->assertOk();
        $response->assertSee('0.00 AMD');
        $response->assertDontSee('7,230.00 AMD');
    }
}
