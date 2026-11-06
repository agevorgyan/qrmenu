<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
