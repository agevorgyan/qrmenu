<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WasteLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SupplierAndProcurementTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Warehouse $warehouseA;

    protected Unit $kg;

    protected Supplier $supplierA;

    protected Ingredient $coffee;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Café Central',
            'slug' => 'cafe-central-'.uniqid(),
            'email' => 'cafe-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->ownerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Barista Owner',
            'email' => 'owner-cafe-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouseA = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Central Storage',
            'type' => 'main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Artisan Roasters LLC',
            'tax_id' => '11223344',
            'payment_terms' => 'net_15',
            'current_balance' => 0.0,
            'is_active' => true,
        ]);

        $this->coffee = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Arabica Coffee Beans',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 15.00,
            'is_active' => true,
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Bakery Delight',
            'slug' => 'bakery-delight-'.uniqid(),
            'email' => 'bakery-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->ownerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Baker Bob',
            'email' => 'bob-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);
    }

    public function test_can_list_suppliers_scoped_to_vendor(): void
    {
        $supplierB = Supplier::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Dairy Farm B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.suppliers.index'));

        $response->assertOk();
        $response->assertSee('Artisan Roasters LLC');
        $response->assertDontSee('Dairy Farm B');
    }

    public function test_can_create_supplier_with_contract_prices(): void
    {
        $payload = [
            'name' => 'Dairy Best Corp',
            'tax_id' => '99887766',
            'payment_terms' => 'net_30',
            'contact_person' => 'Ani Grigoryan',
            'phone' => '+374 10 123456',
            'email' => 'orders@dairybest.am',
            'ingredients' => [
                [
                    'ingredient_id' => $this->coffee->id,
                    'price' => 14.50,
                    'unit_id' => $this->kg->id,
                    'is_primary_supplier' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.suppliers.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('suppliers', [
            'vendor_id' => $this->vendorA->id,
            'name' => 'Dairy Best Corp',
            'tax_id' => '99887766',
        ]);

        $supplier = Supplier::where('vendor_id', $this->vendorA->id)->where('name', 'Dairy Best Corp')->firstOrFail();
        $this->assertCount(1, $supplier->supplierIngredients);
        $this->assertEquals(14.50, (float) $supplier->supplierIngredients->first()->price);
    }

    public function test_can_render_supplier_edit_view_without_parse_errors(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.suppliers.edit', $this->supplierA));

        $response->assertOk();
        $response->assertSee('Artisan Roasters LLC');
        $response->assertSee('supplierEditForm');
    }

    public function test_can_update_supplier_with_vat_and_discounts(): void
    {
        $payload = [
            'name' => 'Artisan Roasters LLC Updated',
            'tax_id' => '11223344',
            'vat_number' => '11223344/1',
            'discount_percent' => 7.5,
            'payment_terms' => 'net_30',
            'ingredients' => [
                [
                    'ingredient_id' => $this->coffee->id,
                    'price' => 13.50,
                    'discount_percent' => 5.0,
                    'unit_id' => $this->kg->id,
                    'supplier_sku' => 'ART-COF-01',
                    'is_primary_supplier' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->ownerA)->put(route('admin.inventory.suppliers.update', $this->supplierA), $payload);

        $response->assertRedirect();
        $this->supplierA->refresh();
        $this->assertEquals('Artisan Roasters LLC Updated', $this->supplierA->name);
        $this->assertEquals('11223344/1', $this->supplierA->vat_number);
        $this->assertEquals(7.5, (float) $this->supplierA->discount_percent);

        $si = $this->supplierA->supplierIngredients->first();
        $this->assertNotNull($si);
        $this->assertEquals(13.50, (float) $si->price);
        $this->assertEquals(5.0, (float) $si->discount_percent);
        $this->assertEquals('ART-COF-01', $si->supplier_sku);
    }

    public function test_cannot_delete_supplier_with_unpaid_balance(): void
    {
        $this->supplierA->current_balance = 250.00;
        $this->supplierA->save();

        $response = $this->actingAs($this->ownerA)->delete(route('admin.inventory.suppliers.destroy', $this->supplierA));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $this->supplierA->id, 'deleted_at' => null]);
    }

    public function test_full_procurement_workflow_order_approve_and_receive(): void
    {
        // 1. Create Purchase Order
        $poPayload = [
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'ingredient_id' => $this->coffee->id,
                    'quantity' => 15.0,
                    'unit_id' => $this->kg->id,
                    'unit_price' => 16.00,
                    'tax_percent' => 0.0,
                ],
            ],
        ];

        $res1 = $this->actingAs($this->ownerA)->post(route('admin.inventory.procurement.store'), $poPayload);
        $res1->assertRedirect();

        $po = PurchaseOrder::where('vendor_id', $this->vendorA->id)->where('supplier_id', $this->supplierA->id)->firstOrFail();
        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $po->status);
        $this->assertEquals(240.00, (float) $po->total_amount);

        // 2. Approve Purchase Order
        $res2 = $this->actingAs($this->ownerA)->post(route('admin.inventory.procurement.approve', $po));
        $res2->assertRedirect();
        $this->assertEquals(PurchaseOrder::STATUS_APPROVED, $po->fresh()->status);

        // 3. Receive Goods
        $item = $po->items->first();
        $receivePayload = [
            'invoice_number' => 'INV-ROAST-900',
            'due_date' => now()->addDays(15)->toDateString(),
            'notes' => 'Received 15kg fresh roasted batch',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity_received' => 15.0,
                    'unit_price' => 16.00,
                ],
            ],
        ];

        $res3 = $this->actingAs($this->ownerA)->post(route('admin.inventory.procurement.receive', $po), $receivePayload);
        $res3->assertRedirect();

        $po->refresh();
        $this->assertEquals(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertEquals(15.0, (float) $po->items->first()->quantity_received);

        // Verify generated supplier invoice and AP debt
        $this->assertDatabaseHas('supplier_invoices', [
            'vendor_id' => $this->vendorA->id,
            'supplier_id' => $this->supplierA->id,
            'invoice_number' => 'INV-ROAST-900',
            'total_amount' => 240.00,
            'remaining_balance' => 240.00,
            'payment_status' => SupplierInvoice::PAYMENT_UNPAID,
        ]);

        $this->assertEquals(240.00, (float) $this->supplierA->fresh()->current_balance);
    }

    public function test_record_supplier_payment_via_controller(): void
    {
        $invoice = SupplierInvoice::create([
            'vendor_id' => $this->vendorA->id,
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'invoice_number' => 'INV-PAY-TEST',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => 150.00,
            'total_amount' => 150.00,
            'paid_amount' => 0.0,
            'remaining_balance' => 150.00,
            'payment_status' => SupplierInvoice::PAYMENT_UNPAID,
        ]);
        $this->supplierA->recalculateBalance();

        $payPayload = [
            'supplier_id' => $this->supplierA->id,
            'supplier_invoice_id' => $invoice->id,
            'amount' => 150.00,
            'payment_method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
            'reference_number' => 'TXN-BANK-009',
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.payments.store'), $payPayload);

        $response->assertRedirect();
        $invoice->refresh();

        $this->assertEquals(150.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remaining_balance);
        $this->assertEquals(SupplierInvoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertEquals(0.00, (float) $this->supplierA->fresh()->current_balance);
    }

    public function test_cross_vendor_supplier_and_po_access_is_forbidden(): void
    {
        // Create PO for Vendor A while acting as Owner A
        $po = $this->actingAs($this->ownerA);
        $poModel = PurchaseOrder::create([
            'vendor_id' => $this->vendorA->id,
            'po_number' => 'PO-A-SECRET',
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        // Vendor B attempts to view or delete Vendor A's supplier
        $resSupplier = $this->actingAs($this->ownerB)->delete(route('admin.inventory.suppliers.destroy', $this->supplierA));
        $this->assertTrue(in_array($resSupplier->status(), [403, 404], true));

        // Vendor B attempts to approve Vendor A's PO
        $resPo = $this->actingAs($this->ownerB)->post(route('admin.inventory.procurement.approve', $poModel));
        $this->assertTrue(in_array($resPo->status(), [403, 404], true));
    }

    public function test_can_create_purchase_order_with_item_and_order_discounts(): void
    {
        $poPayload = [
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'discount_percent' => 5.0, // 5% overall order discount
            'items' => [
                [
                    'ingredient_id' => $this->coffee->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_price' => 1000.0, // Line base = 10,000
                    'discount_percent' => 10.0, // 10% item discount = 1,000. Net = 9,000
                    'tax_percent' => 20.0, // 20% VAT on 9,000 = 1,800. Line total = 10,800
                ],
            ],
        ];

        $res = $this->actingAs($this->ownerA)->post(route('admin.inventory.procurement.store'), $poPayload);
        $res->assertRedirect();

        $po = PurchaseOrder::where('vendor_id', $this->vendorA->id)->latest('id')->firstOrFail();
        $this->assertEquals(10000.0, (float) $po->subtotal);
        $this->assertEquals(5.0, (float) $po->discount_percent);
        // Line discount = 1000. Remaining net = 9000. Order discount (5% of 9000) = 450. Total discount = 1450.
        $this->assertEquals(1450.0, (float) $po->discount_amount);
        $this->assertEquals(1800.0, (float) $po->tax_amount);

        $item = $po->items->first();
        $this->assertEquals(10.0, (float) $item->discount_percent);
        $this->assertEquals(1000.0, (float) $item->discount_amount);
        $this->assertEquals(1800.0, (float) $item->tax_amount);
    }

    public function test_purchase_order_create_view_displays_vendor_currency_without_hardcoded_dollars(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.procurement.create'));

        $response->assertOk();
        $response->assertSee($this->vendorA->currency);
        $response->assertDontSee('$<span x-text=');
        $response->assertDontSee('$<span');
        $response->assertSee('ingredientPickerModal');
    }

    public function test_can_import_armenian_tax_invoice_xml(): void
    {
        $xmlContent = <<<'XML'
<ExportedData xmlns="http://www.taxservice.am/tp3/invoice/definitions">
  <SignedData>
    <Data>
      <SignableData xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="1.0" xsi:type="Invoice">
        <Type>1</Type>
        <GeneralInfo>
          <InvoiceNumber>
            <Number>9348872896</Number>
            <Series>A</Series>
          </InvoiceNumber>
          <SupplyDate>2026-11-06+04:00</SupplyDate>
          <AdditionalData>846 փ</AdditionalData>
        </GeneralInfo>
        <SupplierInfo>
          <VATNumber>01282185/1</VATNumber>
          <Taxpayer>
            <TIN>01282185</TIN>
            <Name>«ԱԼԵՔՍ ԷՆԴ ՀՈԼԴԻՆԳ» ՍՊԸ</Name>
            <Address>Երևան, Սեբաստիա 143</Address>
          </Taxpayer>
          <SupplyLocation>ք. Երևան, Մալաթիա-Սեբաստիա, Շրջանային 102/3</SupplyLocation>
        </SupplierInfo>
        <BuyerInfo>
          <DeliveryLocation>Դավթաշեն 2-րդ թաղ.37շ. դիմաց</DeliveryLocation>
        </BuyerInfo>
        <GoodsInfo>
          <Good>
            <Description>Ալյուր 10կգ</Description>
            <ClassifierCode>1101</ClassifierCode>
            <Unit>փաթեթ</Unit>
            <Amount>10</Amount>
            <PricePerUnit>1616.667</PricePerUnit>
            <Price>16166.67</Price>
            <VATRate>20.0</VATRate>
            <VAT>3233.33</VAT>
            <TotalPrice>19400</TotalPrice>
          </Good>
          <Good>
            <Description>Արևածաղկի ձեթ 5լ</Description>
            <ClassifierCode>1512</ClassifierCode>
            <Unit>շիշ</Unit>
            <Amount>2</Amount>
            <PricePerUnit>3242.915</PricePerUnit>
            <Price>6485.83</Price>
            <VATRate>20.0</VATRate>
            <VAT>1297.17</VAT>
            <TotalPrice>7783</TotalPrice>
          </Good>
          <Total>
            <TotalAmount>12</TotalAmount>
            <TotalPriceWithoutVAT>22652.5</TotalPriceWithoutVAT>
            <TotalVAT>4530.5</TotalVAT>
            <TotalPrice>27183</TotalPrice>
          </Total>
        </GoodsInfo>
      </SignableData>
    </Data>
  </SignedData>
</ExportedData>
XML;

        $file = UploadedFile::fake()->createWithContent('tax_invoice.xml', $xmlContent);

        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.invoices.tax-import'), [
            'file' => $file,
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $response->assertRedirect();

        // 1. Check Supplier
        $supplier = Supplier::where('vendor_id', $this->vendorA->id)->where('tax_id', '01282185')->firstOrFail();
        $this->assertStringContainsString('ԱԼԵՔՍ ԷՆԴ ՀՈԼԴԻՆԳ', $supplier->name);

        // 2. Check Supplier Invoice
        $invoice = SupplierInvoice::where('vendor_id', $this->vendorA->id)
            ->where('invoice_number', 'A9348872896')
            ->firstOrFail();
        $this->assertEquals(27183.00, (float) $invoice->total_amount);
        $this->assertEquals(4530.50, (float) $invoice->tax_amount);
        $this->assertEquals('A', $invoice->series);
        $this->assertEquals('9348872896', $invoice->number);

        // 3. Check Invoice Items
        $this->assertCount(2, $invoice->items);
        $item1 = $invoice->items->where('classifier_code', '1101')->firstOrFail();
        $this->assertEquals('Ալյուր 10կգ', $item1->description);
        $this->assertEquals(10.0, (float) $item1->quantity);
        $this->assertEquals(1940.0, (float) $item1->unit_price); // 19400 / 10 = 1940.0 (including VAT)
        $this->assertEquals(19400.0, (float) $item1->total_price);

        // 4. Check Warehouse Stock Intake
        $flourIngredient = Ingredient::where('vendor_id', $this->vendorA->id)->where('name', 'Ալյուր 10կգ')->firstOrFail();
        $this->assertEquals(1940.0, (float) $flourIngredient->current_cost);
        $this->assertEquals(1940.0, (float) $flourIngredient->last_purchase_price);
        $balance = InventoryBalance::where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $flourIngredient->id)
            ->firstOrFail();
        $this->assertEquals(10.0, (float) $balance->quantity_on_hand);
    }

    public function test_can_render_purchase_order_edit_view(): void
    {
        $po = PurchaseOrder::create([
            'vendor_id' => $this->vendorA->id,
            'po_number' => 'PO-EDIT-001',
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal' => 150.0,
            'total_amount' => 150.0,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.procurement.edit', $po));

        $response->assertOk();
        $response->assertSee('PO-EDIT-001');
        $response->assertSee('poEditForm');
    }

    public function test_can_update_purchase_order_items_and_discounts(): void
    {
        $po = PurchaseOrder::create([
            'vendor_id' => $this->vendorA->id,
            'po_number' => 'PO-UPDATE-002',
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal' => 100.0,
            'total_amount' => 100.0,
        ]);

        $updatePayload = [
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'discount_percent' => 10.0, // 10% order discount
            'notes' => 'Updated notes for shipment',
            'items' => [
                [
                    'ingredient_id' => $this->coffee->id,
                    'quantity' => 20.0,
                    'unit_id' => $this->kg->id,
                    'unit_price' => 500.0, // 20 * 500 = 10,000
                    'discount_percent' => 5.0, // 5% = 500. Net = 9,500
                    'tax_percent' => 0.0,
                ],
            ],
        ];

        $response = $this->actingAs($this->ownerA)->put(route('admin.inventory.procurement.update', $po), $updatePayload);

        $response->assertRedirect();
        $po->refresh();

        $this->assertEquals(10000.0, (float) $po->subtotal);
        $this->assertEquals(10.0, (float) $po->discount_percent);
        // Line discount = 500. Remaining net = 9500. Order discount (10% of 9500) = 950. Total discount = 1450.
        $this->assertEquals(1450.0, (float) $po->discount_amount);
        $this->assertEquals(8550.0, (float) $po->total_amount);
        $this->assertEquals('Updated notes for shipment', $po->notes);
        $this->assertCount(1, $po->items);
        $this->assertEquals(20.0, (float) $po->items->first()->quantity_ordered);
    }

    public function test_cannot_edit_or_delete_received_purchase_order(): void
    {
        $po = PurchaseOrder::create([
            'vendor_id' => $this->vendorA->id,
            'po_number' => 'PO-RECEIVED-003',
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'subtotal' => 500.0,
            'total_amount' => 500.0,
        ]);

        // Attempt edit
        $resEdit = $this->actingAs($this->ownerA)->get(route('admin.inventory.procurement.edit', $po));
        $resEdit->assertRedirect(route('admin.inventory.procurement.show', $po));
        $resEdit->assertSessionHas('error');

        // Attempt delete
        $resDelete = $this->actingAs($this->ownerA)->delete(route('admin.inventory.procurement.destroy', $po));
        $resDelete->assertRedirect();
        $resDelete->assertSessionHas('error');
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'deleted_at' => null]);
    }

    public function test_can_delete_unreceived_purchase_order(): void
    {
        $po = PurchaseOrder::create([
            'vendor_id' => $this->vendorA->id,
            'po_number' => 'PO-DEL-004',
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal' => 200.0,
            'total_amount' => 200.0,
        ]);

        $response = $this->actingAs($this->ownerA)->delete(route('admin.inventory.procurement.destroy', $po));

        $response->assertRedirect(route('admin.inventory.procurement.index'));
        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    public function test_can_convert_imported_tax_invoice_item_to_target_ingredient_and_unbundle_quantity(): void
    {
        $xmlContent = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<e-InvoicingElectronDocument>
    <SignedData>
        <Data>
            <SignableData>
                <GeneralInfo>
                    <InvoiceNumber>
                        <Series>A</Series>
                        <Number>88991122</Number>
                    </InvoiceNumber>
                    <SupplyDate>2026-11-06</SupplyDate>
                </GeneralInfo>
                <SupplierInfo>
                    <Taxpayer>
                        <TIN>01234567</TIN>
                        <Name>Grand Mills LLC</Name>
                    </Taxpayer>
                </SupplierInfo>
                <BuyerInfo>
                    <Taxpayer>
                        <TIN>99999999</TIN>
                        <Name>Test Café</Name>
                    </Taxpayer>
                </BuyerInfo>
                <GoodsInfo>
                    <Good>
                        <ClassifierCode>11010001</ClassifierCode>
                        <Description>Flour 10kg Sacks</Description>
                        <Unit>փաթեթ</Unit>
                        <Amount>15.0000</Amount>
                        <PricePerUnit>1616.6667</PricePerUnit>
                        <Price>24250.00</Price>
                        <VATRate>20.00</VATRate>
                        <VAT>4850.00</VAT>
                        <TotalPrice>29100.00</TotalPrice>
                    </Good>
                    <Total>
                        <TotalPrice>29100.00</TotalPrice>
                    </Total>
                </GoodsInfo>
            </SignableData>
        </Data>
    </SignedData>
</e-InvoicingElectronDocument>
XML;

        $file = UploadedFile::fake()->createWithContent('tax_inv.xml', $xmlContent);

        // 1. Import Invoice
        $importResponse = $this->actingAs($this->ownerA)->post(route('admin.inventory.invoices.tax-import'), [
            'file' => $file,
            'warehouse_id' => $this->warehouseA->id,
        ]);
        $importResponse->assertSessionHasNoErrors();

        $invoice = SupplierInvoice::where('invoice_number', 'A88991122')->firstOrFail();
        $invoiceItem = $invoice->items()->firstOrFail();

        // Target ingredient: Existing kitchen ingredient "Ցորենի ալյուր բարձր տեսակի" in kg
        $kitchenFlour = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Ցորենի ալյուր բարձր տեսակի',
            'sku' => 'ING-FLOUR-KITCHEN',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'unit_id' => $this->kg->id,
            'current_cost' => 0.0,
            'last_purchase_price' => 0.0,
            'is_active' => true,
        ]);

        $oldIngredient = $invoiceItem->ingredient;
        $oldBalance = InventoryBalance::where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $oldIngredient->id)
            ->first();
        $this->assertEquals(15.0, (float) $oldBalance->quantity_on_hand);

        // 2. Convert line item: 15 sacks -> 150 kg (factor: 10, total: 29100 => unit_price: 194.0 AMD/kg)
        $convertResponse = $this->actingAs($this->ownerA)->post(
            route('admin.inventory.invoices.convert-item', [$invoice, $invoiceItem]),
            [
                'target_ingredient_id' => $kitchenFlour->id,
                'conversion_factor' => 10,
                'new_quantity' => 150.0,
                'remember_mapping' => 1,
            ]
        );

        $convertResponse->assertRedirect();
        $convertResponse->assertSessionHas('success');

        $invoiceItem->refresh();
        $this->assertEquals($kitchenFlour->id, $invoiceItem->ingredient_id);
        $this->assertEquals(150.0, (float) $invoiceItem->quantity);
        $this->assertEquals(15.0, (float) $invoiceItem->original_quantity);
        $this->assertEquals('փաթեթ', $invoiceItem->original_unit_name);
        $this->assertEquals(10.0, (float) $invoiceItem->conversion_factor);
        $this->assertEquals(194.0, (float) $invoiceItem->unit_price);
        $this->assertTrue($invoiceItem->is_converted);

        // Check kitchen ingredient updated costing
        $kitchenFlour->refresh();
        $this->assertEquals(194.0, (float) $kitchenFlour->current_cost);
        $this->assertEquals(194.0, (float) $kitchenFlour->last_purchase_price);

        // Check Warehouse stock balances:
        // Kitchen flour should have 150 kg
        $kitchenBalance = InventoryBalance::where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $kitchenFlour->id)
            ->first();
        $this->assertNotNull($kitchenBalance);
        $this->assertEquals(150.0, (float) $kitchenBalance->quantity_on_hand);

        // Old package ingredient should have 0 kg
        $oldBalance->refresh();
        $this->assertEquals(0.0, (float) $oldBalance->quantity_on_hand);

        // Check SupplierIngredient mapping remembered
        $mapping = SupplierIngredient::where('supplier_id', $invoice->supplier_id)
            ->where('ingredient_id', $kitchenFlour->id)
            ->first();
        $this->assertNotNull($mapping);
        $this->assertEquals(10.0, (float) $mapping->package_size);
        $this->assertEquals('11010001', $mapping->supplier_sku);
    }

    public function test_subsequent_invoice_import_auto_converts_using_saved_mapping(): void
    {
        // Setup saved mapping: supplierA sells to kitchenFlour with package size 10 and sku '11010002'
        $kitchenFlour = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Ցորենի ալյուր բարձր տեսակի',
            'sku' => 'ING-FLOUR-AUTO',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'unit_id' => $this->kg->id,
            'current_cost' => 0.0,
            'last_purchase_price' => 0.0,
            'is_active' => true,
        ]);

        SupplierIngredient::create([
            'vendor_id' => $this->vendorA->id,
            'supplier_id' => $this->supplierA->id,
            'ingredient_id' => $kitchenFlour->id,
            'unit_id' => $this->kg->id,
            'price' => 200.0,
            'supplier_sku' => '11010002',
            'package_size' => 10.0,
        ]);

        $xmlContent = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<e-InvoicingElectronDocument>
    <SignedData>
        <Data>
            <SignableData>
                <GeneralInfo>
                    <InvoiceNumber>
                        <Series>B</Series>
                        <Number>55443322</Number>
                    </InvoiceNumber>
                    <SupplyDate>2026-11-06</SupplyDate>
                </GeneralInfo>
                <SupplierInfo>
                    <Taxpayer>
                        <TIN>{$this->supplierA->tax_id}</TIN>
                        <Name>{$this->supplierA->name}</Name>
                    </Taxpayer>
                </SupplierInfo>
                <GoodsInfo>
                    <Good>
                        <ClassifierCode>11010002</ClassifierCode>
                        <Description>Flour 10kg Sacks</Description>
                        <Unit>փաթեթ</Unit>
                        <Amount>6.0000</Amount>
                        <PricePerUnit>1666.6667</PricePerUnit>
                        <Price>10000.00</Price>
                        <VATRate>20.00</VATRate>
                        <VAT>2000.00</VAT>
                        <TotalPrice>12000.00</TotalPrice>
                    </Good>
                    <Total>
                        <TotalPrice>12000.00</TotalPrice>
                    </Total>
                </GoodsInfo>
            </SignableData>
        </Data>
    </SignedData>
</e-InvoicingElectronDocument>
XML;

        $file = UploadedFile::fake()->createWithContent('tax_inv_auto.xml', $xmlContent);

        $importResponse = $this->actingAs($this->ownerA)->post(route('admin.inventory.invoices.tax-import'), [
            'file' => $file,
            'warehouse_id' => $this->warehouseA->id,
        ]);
        $importResponse->assertSessionHasNoErrors();

        $invoice = SupplierInvoice::where('invoice_number', 'B55443322')->firstOrFail();
        $item = $invoice->items()->firstOrFail();

        // Should be automatically converted to 60 kg of $kitchenFlour (6 * 10 = 60 kg)
        $this->assertEquals($kitchenFlour->id, $item->ingredient_id);
        $this->assertEquals(60.0, (float) $item->quantity);
        $this->assertEquals(6.0, (float) $item->original_quantity);
        $this->assertEquals('փաթեթ', $item->original_unit_name);
        $this->assertEquals(10.0, (float) $item->conversion_factor);
        // Unit price = 12000 / 60 = 200 AMD/kg
        $this->assertEquals(200.0, (float) $item->unit_price);
        $this->assertTrue($item->is_converted);

        // Warehouse balance
        $balance = InventoryBalance::where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $kitchenFlour->id)
            ->first();
        $this->assertEquals(60.0, (float) $balance->quantity_on_hand);
    }

    public function test_cannot_convert_invoice_item_of_another_vendor(): void
    {
        $invoiceB = SupplierInvoice::create([
            'vendor_id' => $this->vendorB->id,
            'supplier_id' => Supplier::create([
                'vendor_id' => $this->vendorB->id,
                'name' => 'Supplier B',
                'is_active' => true,
            ])->id,
            'warehouse_id' => Warehouse::create([
                'vendor_id' => $this->vendorB->id,
                'name' => 'Warehouse B',
                'is_active' => true,
            ])->id,
            'invoice_number' => 'INV-B-999',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 1000.0,
            'remaining_balance' => 1000.0,
        ]);

        $itemB = SupplierInvoiceItem::create([
            'vendor_id' => $this->vendorB->id,
            'supplier_invoice_id' => $invoiceB->id,
            'description' => 'Product B',
            'quantity' => 10.0,
            'unit_price' => 100.0,
            'price' => 1000.0,
            'total_price' => 1000.0,
        ]);

        // Vendor A attempts to convert Vendor B's invoice item
        $response = $this->actingAs($this->ownerA)->post(
            route('admin.inventory.invoices.convert-item', [$invoiceB, $itemB]),
            [
                'target_ingredient_id' => $this->coffee->id,
                'conversion_factor' => 1,
                'new_quantity' => 10.0,
            ]
        );

        $response->assertNotFound();
    }

    public function test_import_tax_invoice_handles_string_enum_payment_terms(): void
    {
        // Supplier with string enum 'net_15'
        $supplier = Supplier::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Armenian Bakeries CJSC',
            'tax_id' => '09988776',
            'payment_terms' => 'net_15',
            'is_active' => true,
        ]);

        $xmlContent = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<e-InvoicingElectronDocument>
    <SignedData>
        <Data>
            <SignableData>
                <GeneralInfo>
                    <InvoiceNumber>
                        <Series>D</Series>
                        <Number>12345678</Number>
                    </InvoiceNumber>
                    <SupplyDate>2026-11-01</SupplyDate>
                </GeneralInfo>
                <SupplierInfo>
                    <Taxpayer>
                        <TIN>09988776</TIN>
                        <Name>Armenian Bakeries CJSC</Name>
                    </Taxpayer>
                </SupplierInfo>
                <GoodsInfo>
                    <Good>
                        <ClassifierCode>11019999</ClassifierCode>
                        <Description>Baking Yeast</Description>
                        <Unit>կգ</Unit>
                        <Amount>5.0000</Amount>
                        <PricePerUnit>1000.00</PricePerUnit>
                        <Price>5000.00</Price>
                        <VATRate>20.00</VATRate>
                        <VAT>1000.00</VAT>
                        <TotalPrice>6000.00</TotalPrice>
                    </Good>
                    <Total>
                        <TotalPrice>6000.00</TotalPrice>
                    </Total>
                </GoodsInfo>
            </SignableData>
        </Data>
    </SignedData>
</e-InvoicingElectronDocument>
XML;

        $file = UploadedFile::fake()->createWithContent('tax_inv_terms.xml', $xmlContent);

        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.invoices.tax-import'), [
            'file' => $file,
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $invoice = SupplierInvoice::where('invoice_number', 'D12345678')->firstOrFail();
        $this->assertEquals('2026-11-01', $invoice->supply_date->toDateString());
        // 2026-11-01 + 15 days = 2026-11-16
        $this->assertEquals('2026-11-16', $invoice->due_date->toDateString());
    }

    public function test_can_render_official_purchase_order_printable_document(): void
    {
        $po = PurchaseOrder::create([
            'vendor_id' => $this->vendorA->id,
            'po_number' => 'PO-TEST-901',
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'created_by' => $this->ownerA->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_APPROVED,
            'total_amount' => 15000,
        ]);

        $po->items()->create([
            'vendor_id' => $this->vendorA->id,
            'ingredient_id' => $this->coffee->id,
            'quantity_ordered' => 10,
            'unit_id' => $this->kg->id,
            'unit_price' => 1500,
            'total_price' => 1500,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.procurement.print', $po));
        $response->assertOk();
        $response->assertViewIs('admin.inventory.procurement.print');
        $response->assertSee($po->po_number);
        $response->assertSee($this->supplierA->name);
        $response->assertSee($this->coffee->name);
    }

    public function test_can_render_official_supplier_invoice_printable_document(): void
    {
        $invoice = SupplierInvoice::create([
            'vendor_id' => $this->vendorA->id,
            'supplier_id' => $this->supplierA->id,
            'warehouse_id' => $this->warehouseA->id,
            'invoice_number' => 'INV-TEST-999',
            'series' => 'TAX',
            'number' => 'ARM-888',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 30000,
            'remaining_balance' => 30000,
            'payment_status' => 'unpaid',
        ]);

        $invoice->items()->create([
            'vendor_id' => $this->vendorA->id,
            'ingredient_id' => $this->coffee->id,
            'description' => 'Coffee Beans Arabica',
            'quantity' => 10,
            'unit_id' => $this->kg->id,
            'price' => 3000,
            'unit_price' => 3000,
            'total_price' => 30000,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.invoices.print', $invoice));
        $response->assertOk();
        $response->assertViewIs('admin.inventory.invoices.print');
        $response->assertSee('INV-TEST-999');
        $response->assertSee('TAX');
        $response->assertSee('ARM-888');
        $response->assertSee('Coffee Beans Arabica');
    }

    public function test_can_render_official_recipe_technological_card_printable_document(): void
    {
        $recipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Signature Espresso Shot',
            'code' => 'TECH-ESP-01',
            'type' => 'dish',
            'yield_quantity' => 1,
            'yield_unit_id' => $this->kg->id,
            'status' => 'approved',
            'version' => 1,
            'instructions' => 'Grind 18g of beans at 9 bars pressure.',
            'created_by' => $this->ownerA->id,
        ]);

        $recipe->items()->create([
            'vendor_id' => $this->vendorA->id,
            'ingredient_id' => $this->coffee->id,
            'quantity' => 0.018,
            'quantity_gross' => 0.018,
            'quantity_net' => 0.018,
            'unit_id' => $this->kg->id,
            'unit_cost' => 5000,
            'total_cost' => 90,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.recipes.print', $recipe));
        $response->assertOk();
        $response->assertViewIs('admin.inventory.recipes.print');
        $response->assertSee('Signature Espresso Shot');
        $response->assertSee('TECH-ESP-01');
        $response->assertSee('Grind 18g of beans');
    }

    public function test_can_render_official_production_order_printable_document(): void
    {
        $recipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Signature Espresso Shot',
            'code' => 'TECH-ESP-02',
            'type' => 'dish',
            'yield_quantity' => 1,
            'yield_unit_id' => $this->kg->id,
            'status' => 'approved',
            'version' => 1,
            'created_by' => $this->ownerA->id,
        ]);

        $production = ProductionOrder::create([
            'vendor_id' => $this->vendorA->id,
            'recipe_id' => $recipe->id,
            'order_number' => 'PRD-TEST-901',
            'batch_number' => 'BATCH-2026-001',
            'produced_ingredient_id' => $this->coffee->id,
            'source_warehouse_id' => $this->warehouseA->id,
            'destination_warehouse_id' => $this->warehouseA->id,
            'unit_id' => $this->kg->id,
            'planned_quantity' => 5,
            'actual_quantity' => 5,
            'total_cost' => 25000,
            'status' => 'completed',
            'completed_at' => now(),
            'produced_by' => $this->ownerA->id,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.production.print', $production));
        $response->assertOk();
        $response->assertViewIs('admin.inventory.production.print');
        $response->assertSee('BATCH-2026-001');
    }

    public function test_can_render_official_waste_log_printable_document(): void
    {
        $waste = WasteLog::create([
            'vendor_id' => $this->vendorA->id,
            'waste_number' => 'WST-TEST-901',
            'warehouse_id' => $this->warehouseA->id,
            'ingredient_id' => $this->coffee->id,
            'unit_id' => $this->kg->id,
            'quantity' => 2.5,
            'unit_cost' => 3000,
            'total_cost' => 7500,
            'reason' => 'expired',
            'logged_by' => $this->ownerA->id,
            'notes' => 'Beans expired during storage',
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.waste.print', $waste));
        $response->assertOk();
        $response->assertViewIs('admin.inventory.waste.print');
        $response->assertSee($waste->waste_number);
        $response->assertSee('Beans expired during storage');
    }
}
