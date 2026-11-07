<?php

declare(strict_types=1);

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Models\WholesaleCategory;
use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleCustomerAddress;
use App\Domains\Wholesale\Models\WholesalePekExport;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSaleItem;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Models\WholesaleSellerBankAccount;
use App\Domains\Wholesale\Models\WholesaleSellerLocation;
use App\Domains\Wholesale\Models\WholesaleSellerTaxProfile;
use App\Domains\Wholesale\Models\WholesaleTaxDocument;
use App\Domains\Wholesale\Services\PekXsdValidationService;
use App\Domains\Wholesale\Services\WholesaleTaxDocumentService;
use App\Models\SubscriptionPlan;
use App\Models\TenantModuleAccess;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesalePekXmlGenerationAndValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected WholesaleSeller $vatSeller;

    protected WholesaleSeller $turnoverSeller;

    protected WholesaleCustomer $customer;

    protected WholesaleProduct $product;

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

        // VAT Seller
        $this->vatSeller = WholesaleSeller::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Sales Plus LLC',
            'legal_name' => '«ՍԵՅԼՍ ՊԼՅՈՒՍ» ՍՊԸ',
            'tin' => '08293072',
            'vat_number' => '08293072/1',
            'tax_regime' => 'vat_payer',
            'address' => 'ԵՐԵՎԱՆ ԴԱՎԹԱՇԵՆ 1 ԹՂՄ. 38 7 ԲՆ.',
            'pek_username' => 'salesplus25',
            'is_active' => true,
        ]);
        WholesaleSellerTaxProfile::create([
            'seller_id' => $this->vatSeller->id,
            'tax_regime' => 'vat_payer',
            'is_vat_payer' => true,
            'vat_rate' => 20.00,
            'document_series' => 'A',
            'effective_from' => now()->subYear(),
        ]);
        WholesaleSellerLocation::create([
            'seller_id' => $this->vatSeller->id,
            'name' => 'Main Warehouse',
            'address' => 'Հայաստան, ԿՈՏԱՅՔ, ԶՈՎՈՒՆԻ, ԶՈՎՈՒՆԻ, 30փ 2նրբ 31, 182տար',
            'is_default' => true,
        ]);
        WholesaleSellerBankAccount::create([
            'seller_id' => $this->vatSeller->id,
            'bank_name' => 'ԱԿԲԱ ԲԱՆԿ ԲԲԸ',
            'account_number' => '220001234567890',
            'is_default' => true,
        ]);

        // Turnover Tax Seller (Non-VAT)
        $this->turnoverSeller = WholesaleSeller::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Gayane Kitchen LLC',
            'legal_name' => '«ԳԱՅԱՆԵԻ ԽՈՀԱՆՈՑ» ՍՊԸ',
            'tin' => '08285927',
            'tax_regime' => 'turnover_tax',
            'address' => 'ԵՐԵՎԱՆ ԴԱՎԹԱՇԵՆ ԹԱՂԱՄԱՍ 1 ԹՂՄ. 38 7 ԲՆ.',
            'pek_username' => 'khohanots24',
            'is_active' => true,
        ]);
        WholesaleSellerTaxProfile::create([
            'seller_id' => $this->turnoverSeller->id,
            'tax_regime' => 'turnover_tax',
            'is_vat_payer' => false,
            'vat_rate' => 0.00,
            'document_series' => 'B',
            'effective_from' => now()->subYear(),
        ]);
        WholesaleSellerLocation::create([
            'seller_id' => $this->turnoverSeller->id,
            'name' => 'Kitchen Facility',
            'address' => 'Հայաստան, ԵՐԵՎԱՆ, ԴԱՎԹԱՇԵՆ, 2րդ թաղ 37 շենքի դիմաց',
            'is_default' => true,
        ]);
        WholesaleSellerBankAccount::create([
            'seller_id' => $this->turnoverSeller->id,
            'bank_name' => '«ԱՄԵՐԻԱԲԱՆԿ» ՓԲԸ',
            'account_number' => '1570077654886500',
            'is_default' => true,
        ]);

        // Corporate Customer
        $this->customer = WholesaleCustomer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'SDS Supermarket LLC',
            'legal_name' => '«ՍԴՍ ՍՈՒՊԵՐՄԱՐԿԵՏ» ՍՊԸ',
            'tin' => '03576728',
            'tax_regime' => 'vat_payer',
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

        // Category & Product
        $category = WholesaleCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Frozen Foods',
        ]);
        $this->product = WholesaleProduct::create([
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
    }

    public function test_xsd_validator_validates_raw_reference_fixtures(): void
    {
        $validator = app(PekXsdValidationService::class);

        $taxInvoiceXml = file_get_contents(base_path('tests/Fixtures/Wholesale/tax_invoice_fixture.xml'));
        $invoiceResult = $validator->validateXml($taxInvoiceXml, 'TAX_INVOICE');
        $this->assertTrue($invoiceResult['isValid'], 'Tax Invoice fixture must be W3C XSD valid: '.implode('; ', $invoiceResult['errors']));

        $accDocXml = file_get_contents(base_path('tests/Fixtures/Wholesale/accounting_document_fixture.xml'));
        $accDocResult = $validator->validateXml($accDocXml, 'ACCOUNTING_DOCUMENT');
        $this->assertTrue($accDocResult['isValid'], 'Accounting Document fixture must be W3C XSD valid: '.implode('; ', $accDocResult['errors']));
    }

    public function test_generates_and_validates_tax_invoice_for_vat_seller(): void
    {
        $sale = WholesaleSale::create([
            'vendor_id' => $this->vendor->id,
            'seller_id' => $this->vatSeller->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'WS-20261108-0001',
            'sale_date' => now()->toDateString(),
            'subtotal' => 3625.00,
            'discount_total' => 0.00,
            'tax_total' => 725.00,
            'delivery_fee' => 0.00,
            'total_amount' => 4350.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'ISSUED',
        ]);

        WholesaleSaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'classifier_code' => '1602',
            'unit_code' => 'unit',
            'quantity' => 3,
            'unit_price' => 1208.33333,
            'subtotal' => 3625.00,
            'vat_amount' => 725.00,
            'total_amount' => 4350.00,
        ]);

        $service = app(WholesaleTaxDocumentService::class);
        $taxDoc = $service->createTaxDocumentFromSale($sale, $this->user);

        $this->assertEquals('TAX_INVOICE', $taxDoc->document_type);
        $this->assertEquals('A', $taxDoc->series);
        $this->assertTrue($taxDoc->is_xsd_valid);
        $this->assertEmpty($taxDoc->xsd_validation_errors);

        $xml = $taxDoc->xml_content;
        $this->assertStringContainsString('<ExportedData', $xml);
        $this->assertStringContainsString('xsi:type="Invoice"', $xml);
        $this->assertStringContainsString('<VATRate>20.0</VATRate>', $xml);
        $this->assertStringContainsString('<ClassifierCode>1602</ClassifierCode>', $xml);
        $this->assertStringContainsString('<SignatureType>PKCS7</SignatureType>', $xml);
        $this->assertStringContainsString('<InvoiceMetadata>', $xml);
    }

    public function test_generates_and_validates_accounting_document_for_non_vat_turnover_seller(): void
    {
        $sale = WholesaleSale::create([
            'vendor_id' => $this->vendor->id,
            'seller_id' => $this->turnoverSeller->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'WS-20261108-0002',
            'sale_date' => now()->toDateString(),
            'subtotal' => 6400.00,
            'discount_total' => 0.00,
            'tax_total' => 0.00,
            'delivery_fee' => 0.00,
            'total_amount' => 6400.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'ISSUED',
        ]);

        WholesaleSaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'classifier_code' => '1602',
            'unit_code' => 'unit',
            'quantity' => 4,
            'unit_price' => 1600.00,
            'subtotal' => 6400.00,
            'vat_amount' => 0.00,
            'total_amount' => 6400.00,
        ]);

        $service = app(WholesaleTaxDocumentService::class);
        $taxDoc = $service->createTaxDocumentFromSale($sale, $this->user);

        $this->assertEquals('ACCOUNTING_DOCUMENT', $taxDoc->document_type);
        $this->assertEquals('B', $taxDoc->series);
        $this->assertTrue($taxDoc->is_xsd_valid);
        $this->assertEmpty($taxDoc->xsd_validation_errors);

        $xml = $taxDoc->xml_content;
        $this->assertStringContainsString('<ExportedAccDocData', $xml);
        $this->assertStringContainsString('xsi:type="AccountingDocument"', $xml);
        $this->assertStringContainsString('<DealType>SPECIAL_TAX_SYSTEM</DealType>', $xml);
        $this->assertStringContainsString('<BankAccount>', $xml);
        $this->assertStringNotContainsString('<VATRate>', $xml);
        $this->assertStringContainsString('<AccDocMetadata>', $xml);
    }

    public function test_tax_document_controller_endpoints(): void
    {
        $sale = WholesaleSale::create([
            'vendor_id' => $this->vendor->id,
            'seller_id' => $this->vatSeller->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'WS-20261108-0003',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000.00,
            'discount_total' => 0.00,
            'tax_total' => 200.00,
            'delivery_fee' => 0.00,
            'total_amount' => 1200.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'ISSUED',
        ]);

        WholesaleSaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'classifier_code' => '1602',
            'unit_code' => 'unit',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
            'vat_amount' => 200.00,
            'total_amount' => 1200.00,
        ]);

        // Generate via POST
        $response = $this->actingAs($this->user)
            ->post(route('admin.wholesale.tax_documents.generate_from_sale', $sale));
        $response->assertRedirect();

        $taxDoc = WholesaleTaxDocument::where('sale_id', $sale->id)->first();
        $this->assertNotNull($taxDoc);

        // View index
        $indexResp = $this->actingAs($this->user)
            ->get(route('admin.wholesale.tax_documents.index'));
        $indexResp->assertOk();
        $indexResp->assertSee($taxDoc->document_number);

        // View show
        $showResp = $this->actingAs($this->user)
            ->get(route('admin.wholesale.tax_documents.show', $taxDoc));
        $showResp->assertOk();
        $showResp->assertSee('W3C XSD Valid');

        // Download XML
        $xmlResp = $this->actingAs($this->user)
            ->get(route('admin.wholesale.tax_documents.download_xml', $taxDoc));
        $xmlResp->assertOk();
        $xmlResp->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        // Revalidate POST
        $revalResp = $this->actingAs($this->user)
            ->post(route('admin.wholesale.tax_documents.revalidate', $taxDoc));
        $revalResp->assertRedirect();
    }

    public function test_batch_pek_zip_export(): void
    {
        $sale = WholesaleSale::create([
            'vendor_id' => $this->vendor->id,
            'seller_id' => $this->turnoverSeller->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'WS-20261108-0004',
            'sale_date' => now()->toDateString(),
            'subtotal' => 5000.00,
            'discount_total' => 0.00,
            'tax_total' => 0.00,
            'delivery_fee' => 0.00,
            'total_amount' => 5000.00,
            'payment_method' => 'CASH',
            'status' => 'ISSUED',
        ]);
        WholesaleSaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'classifier_code' => '1602',
            'unit_code' => 'unit',
            'quantity' => 5,
            'unit_price' => 1000.00,
            'subtotal' => 5000.00,
            'vat_amount' => 0.00,
            'total_amount' => 5000.00,
        ]);

        $service = app(WholesaleTaxDocumentService::class);
        $doc = $service->createTaxDocumentFromSale($sale, $this->user);

        // Export Batch via POST
        $exportResp = $this->actingAs($this->user)
            ->post(route('admin.wholesale.pek.export_batch'), [
                'document_ids' => [$doc->id],
            ]);
        $exportResp->assertRedirect();

        $export = WholesalePekExport::latest()->first();
        $this->assertNotNull($export);
        $this->assertEquals(1, $export->document_count);
        $this->assertGreaterThan(0, $export->file_size_bytes);

        // Download ZIP
        $zipResp = $this->actingAs($this->user)
            ->get(route('admin.wholesale.pek.download_zip', $export));
        $zipResp->assertOk();
        $zipResp->assertHeader('Content-Type', 'application/zip');
    }

    public function test_cross_tenant_tax_document_isolation(): void
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

        $sale = WholesaleSale::create([
            'vendor_id' => $this->vendor->id,
            'seller_id' => $this->vatSeller->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'WS-20261108-0005',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000.00,
            'discount_total' => 0.00,
            'tax_total' => 200.00,
            'delivery_fee' => 0.00,
            'total_amount' => 1200.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'ISSUED',
        ]);
        WholesaleSaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'classifier_code' => '1602',
            'unit_code' => 'unit',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
            'vat_amount' => 200.00,
            'total_amount' => 1200.00,
        ]);

        $service = app(WholesaleTaxDocumentService::class);
        $doc = $service->createTaxDocumentFromSale($sale, $this->user);

        // Other user attempting to view Tenant A's tax document must receive 404 (due to TenantScope)
        $resp = $this->actingAs($otherUser)
            ->get(route('admin.wholesale.tax_documents.show', $doc));
        $resp->assertNotFound();
    }
}
