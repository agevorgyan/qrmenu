<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
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
        $this->assertEquals(19400.0, (float) $item1->total_price);

        // 4. Check Warehouse Stock Intake
        $flourIngredient = Ingredient::where('vendor_id', $this->vendorA->id)->where('name', 'Ալյուր 10կգ')->firstOrFail();
        $balance = InventoryBalance::where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $flourIngredient->id)
            ->firstOrFail();
        $this->assertEquals(10.0, (float) $balance->quantity_on_hand);
    }
}
