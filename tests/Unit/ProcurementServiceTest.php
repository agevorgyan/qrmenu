<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProcurementServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ProcurementService $procurementService;

    protected Vendor $vendor;

    protected User $user;

    protected Warehouse $warehouse;

    protected Unit $kg;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procurementService = app(ProcurementService::class);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();

        $this->vendor = Vendor::create([
            'name' => 'Steakhouse Elite',
            'slug' => 'steakhouse-elite-'.uniqid(),
            'email' => 'elite-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->user = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Purchasing Manager',
            'email' => 'purchaser-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Cold Room',
            'type' => 'main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Prime Meats CJSC',
            'tax_id' => '09876543',
            'payment_terms' => 'net_30',
            'current_balance' => 0.0,
            'is_active' => true,
        ]);
    }

    public function test_auto_replenishment_suggestion_identifies_low_stock_items(): void
    {
        // Low stock item: Salmon (on hand = 2kg, min stock = 10kg, max stock = 30kg)
        $salmon = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Fresh Salmon',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'min_stock' => 10.0,
            'max_stock' => 30.0,
            'current_cost' => 18.00,
            'is_active' => true,
        ]);

        InventoryBalance::create([
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $salmon->id,
            'quantity_on_hand' => 2.0,
            'quantity_reserved' => 0.0,
            'unit_cost' => 18.00,
            'total_value' => 36.00,
            'last_movement_at' => now(),
        ]);

        SupplierIngredient::create([
            'vendor_id' => $this->vendor->id,
            'supplier_id' => $this->supplier->id,
            'ingredient_id' => $salmon->id,
            'unit_id' => $this->kg->id,
            'price' => 17.50,
            'is_primary_supplier' => true,
        ]);

        $suggestions = $this->procurementService->suggestReplenishment($this->vendor, $this->warehouse);

        $this->assertNotEmpty($suggestions);
        $salmonSuggestion = $suggestions->firstWhere('ingredient.id', $salmon->id);
        $this->assertNotNull($salmonSuggestion);
        // Needed: max_stock (30) - current (2) = 28kg
        $this->assertEquals(28.0, $salmonSuggestion['suggested_qty']);
        $this->assertEquals(17.50, $salmonSuggestion['unit_price']);
        $this->assertEquals($this->supplier->id, $salmonSuggestion['supplier']?->id);
    }

    public function test_create_and_approve_purchase_order(): void
    {
        $beef = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Wagyu Striploin',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 50.00,
            'is_active' => true,
        ]);

        $po = $this->procurementService->createPurchaseOrder($this->vendor, [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'ingredient_id' => $beef->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_price' => 45.00,
                    'tax_percent' => 20.0, // 20% VAT
                ],
            ],
        ], $this->user);

        // Subtotal: 10 * 45 = 450.00, Tax: 90.00, Total: 540.00
        $this->assertEquals(450.00, (float) $po->subtotal);
        $this->assertEquals(90.00, (float) $po->tax_amount);
        $this->assertEquals(540.00, (float) $po->total_amount);
        $this->assertEquals(PurchaseOrder::STATUS_DRAFT, $po->status);

        $approvedPo = $this->procurementService->approvePurchaseOrder($po, $this->user);
        $this->assertEquals(PurchaseOrder::STATUS_APPROVED, $approvedPo->status);
        $this->assertEquals($this->user->id, $approvedPo->approved_by);
    }

    public function test_receiving_goods_updates_inventory_balance_and_creates_invoice(): void
    {
        $flour = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Italian 00 Flour',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 1.00,
            'last_purchase_price' => 1.00,
            'is_active' => true,
        ]);

        // Existing initial balance: 10 kg @ $1.00 = $10.00
        InventoryBalance::create([
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $flour->id,
            'quantity_on_hand' => 10.0,
            'quantity_reserved' => 0.0,
            'unit_cost' => 1.00,
            'total_value' => 10.00,
            'last_movement_at' => now(),
        ]);

        $po = $this->procurementService->createPurchaseOrder($this->vendor, [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'ingredient_id' => $flour->id,
                    'quantity' => 20.0,
                    'unit_id' => $this->kg->id,
                    'unit_price' => 1.50,
                    'tax_percent' => 0.0,
                ],
            ],
        ], $this->user);

        $this->procurementService->approvePurchaseOrder($po, $this->user);
        $this->procurementService->markAsSent($po);

        $poItem = $po->items->first();

        // Intake full 20 kg @ $1.50
        $invoice = $this->procurementService->receiveGoodsFromPo(
            po: $po,
            receivedItems: [
                [
                    'item_id' => $poItem->id,
                    'quantity_received' => 20.0,
                    'unit_price' => 1.50,
                ],
            ],
            invoiceNumber: 'INV-TEST-001',
            dueDate: now()->addDays(30)->toDateString(),
            user: $this->user,
            notes: 'All bags verified intact.'
        );

        $po->refresh();
        $this->assertEquals(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertEquals(20.0, (float) $po->items->first()->quantity_received);

        // Inventory balance should now be: 10 old + 20 received = 30 kg
        $balance = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('ingredient_id', $flour->id)
            ->first();
        $this->assertEquals(30.0, (float) $balance->quantity_on_hand);

        // Weighted Average Cost: (10 * 1.00 + 20 * 1.50) / 30 = (10 + 30) / 30 = 40 / 30 = 1.3333
        $this->assertEquals(1.3333, (float) $flour->fresh()->current_cost);
        $this->assertEquals(1.50, (float) $flour->fresh()->last_purchase_price);

        // Accounts payable invoice check
        $this->assertEquals(30.00, (float) $invoice->total_amount);
        $this->assertEquals(30.00, (float) $invoice->remaining_balance);
        $this->assertEquals(SupplierInvoice::PAYMENT_UNPAID, $invoice->payment_status);

        // Supplier outstanding debt increased by $30.00
        $this->assertEquals(30.00, (float) $this->supplier->fresh()->current_balance);
    }

    public function test_supplier_payment_updates_invoice_and_supplier_balance(): void
    {
        // Create an unpaid invoice for $100.00
        $invoice = SupplierInvoice::create([
            'vendor_id' => $this->vendor->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_number' => 'INV-PAY-01',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'paid_amount' => 0.0,
            'remaining_balance' => 100.00,
            'payment_status' => SupplierInvoice::PAYMENT_UNPAID,
        ]);
        $this->supplier->recalculateBalance();
        $this->assertEquals(100.00, (float) $this->supplier->fresh()->current_balance);

        // Pay $40.00
        $payment1 = $this->procurementService->recordPayment(
            supplier: $this->supplier,
            amount: 40.00,
            paymentMethod: SupplierPayment::METHOD_BANK_TRANSFER,
            invoice: $invoice,
            referenceNumber: 'TXN-001',
            user: $this->user
        );

        $invoice->refresh();
        $this->assertEquals(40.00, (float) $invoice->paid_amount);
        $this->assertEquals(60.00, (float) $invoice->remaining_balance);
        $this->assertEquals(SupplierInvoice::PAYMENT_PARTIALLY_PAID, $invoice->payment_status);
        $this->assertEquals(60.00, (float) $this->supplier->fresh()->current_balance);

        // Pay remaining $60.00
        $payment2 = $this->procurementService->recordPayment(
            supplier: $this->supplier,
            amount: 60.00,
            paymentMethod: SupplierPayment::METHOD_CASH,
            invoice: $invoice,
            referenceNumber: 'CASH-002',
            user: $this->user
        );

        $invoice->refresh();
        $this->assertEquals(100.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remaining_balance);
        $this->assertEquals(SupplierInvoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertEquals(0.00, (float) $this->supplier->fresh()->current_balance);
    }
}
