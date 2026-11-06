<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryLedgerServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected InventoryLedgerService $service;

    protected Vendor $vendor;

    protected User $user;

    protected Warehouse $warehouseMain;

    protected Warehouse $warehouseKitchen;

    protected Unit $kg;

    protected Unit $g;

    protected Ingredient $flour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventoryLedgerService::class);

        $this->vendor = Vendor::create([
            'name' => 'Ledger Test Bistro',
            'slug' => 'ledger-test-bistro-'.uniqid(),
            'email' => 'ledger-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Ledger Manager',
            'email' => 'manager-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->warehouseMain = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Dry Storage',
            'type' => 'main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->warehouseKitchen = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Kitchen Dispensary',
            'type' => 'kitchen',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->g = Unit::whereNull('vendor_id')->where('code', 'g')->firstOrFail();

        $this->flour = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Wheat Flour Premium',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'min_stock' => 10.0,
            'current_cost' => 0.0,
        ]);
    }

    public function test_record_receipt_increases_stock_and_computes_weighted_average_cost(): void
    {
        // 1. Initial receipt: 10 kg @ 100 AMD
        $tx1 = $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 100.0,
                ],
            ],
            user: $this->user,
            notes: 'Batch 1'
        );

        $this->assertEquals(InventoryTransaction::STATUS_COMPLETED, $tx1->status);
        $this->assertEquals(1000.0, (float) $tx1->total_amount);

        $balance1 = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseMain->id)
            ->where('ingredient_id', $this->flour->id)
            ->first();

        $this->assertNotNull($balance1);
        $this->assertEquals(10.0, (float) $balance1->quantity_on_hand);
        $this->assertEquals(100.0, (float) $balance1->unit_cost);
        $this->assertEquals(1000.0, (float) $balance1->total_value);

        // 2. Second receipt: 10 kg @ 200 AMD
        // Weighted Average Cost formula: ((10 * 100) + (10 * 200)) / 20 = 3000 / 20 = 150 AMD
        $tx2 = $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 200.0,
                ],
            ],
            user: $this->user,
            notes: 'Batch 2'
        );

        $balance2 = $balance1->fresh();
        $this->assertEquals(20.0, (float) $balance2->quantity_on_hand);
        $this->assertEquals(150.0, (float) $balance2->unit_cost);
        $this->assertEquals(3000.0, (float) $balance2->total_value);

        $this->flour->refresh();
        $this->assertEquals(150.0, (float) $this->flour->current_cost);
        $this->assertEquals(200.0, (float) $this->flour->last_purchase_price);
    }

    public function test_record_receipt_normalizes_different_unit(): void
    {
        // 5000 grams @ 0.2 AMD/gram = 1000 AMD total
        // In kg: 5 kg @ 200 AMD/kg
        $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 5000.0,
                    'unit_id' => $this->g->id,
                    'unit_cost' => 0.20,
                ],
            ],
            user: $this->user
        );

        $balance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseMain->id)
            ->where('ingredient_id', $this->flour->id)
            ->first();

        $this->assertEquals(5.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(200.0, (float) $balance->unit_cost);
        $this->assertEquals(1000.0, (float) $balance->total_value);
    }

    public function test_record_issue_decreases_stock_and_prevents_negative_balance(): void
    {
        // Setup initial 15 kg
        $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 15.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 120.0,
                ],
            ],
            user: $this->user
        );

        // Issue 5 kg
        $this->service->recordIssue(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 5.0,
                    'unit_id' => $this->kg->id,
                ],
            ],
            user: $this->user,
            notes: 'Used in baking'
        );

        $balance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseMain->id)
            ->where('ingredient_id', $this->flour->id)
            ->first();

        $this->assertEquals(10.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(1200.0, (float) $balance->total_value);

        // Attempting to issue 15 kg when only 10 kg are available must fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->recordIssue(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 15.0,
                    'unit_id' => $this->kg->id,
                ],
            ],
            user: $this->user
        );
    }

    public function test_record_transfer_between_warehouses(): void
    {
        // 1. Put 20 kg in Main warehouse @ 100 AMD
        $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 20.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 100.0,
                ],
            ],
            user: $this->user
        );

        // 2. Transfer 8 kg from Main to Kitchen
        $tx = $this->service->recordTransfer(
            sourceWarehouse: $this->warehouseMain,
            targetWarehouse: $this->warehouseKitchen,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 8.0,
                    'unit_id' => $this->kg->id,
                ],
            ],
            user: $this->user,
            notes: 'Kitchen restock'
        );

        $this->assertEquals(InventoryTransaction::TYPE_TRANSFER, $tx->type);
        $this->assertEquals(800.0, (float) $tx->total_amount);

        // Source balance: 20 - 8 = 12 kg
        $sourceBal = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseMain->id)
            ->where('ingredient_id', $this->flour->id)
            ->first();
        $this->assertEquals(12.0, (float) $sourceBal->quantity_on_hand);

        // Target balance: 8 kg @ 100 AMD = 800 AMD
        $targetBal = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseKitchen->id)
            ->where('ingredient_id', $this->flour->id)
            ->first();
        $this->assertEquals(8.0, (float) $targetBal->quantity_on_hand);
        $this->assertEquals(100.0, (float) $targetBal->unit_cost);
        $this->assertEquals(800.0, (float) $targetBal->total_value);
    }

    public function test_record_adjustment_reconciles_actual_count(): void
    {
        // 1. Initial 10 kg
        $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 150.0,
                ],
            ],
            user: $this->user
        );

        // 2. Physical count discovers 8.5 kg (1.5 kg shortage/shrinkage)
        $tx = $this->service->recordAdjustment(
            warehouse: $this->warehouseMain,
            ingredient: $this->flour,
            actualQuantity: 8.5,
            reason: 'Physical count audit discrepancy',
            user: $this->user
        );

        $this->assertEquals(InventoryTransaction::TYPE_ADJUSTMENT, $tx->type);

        $balance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseMain->id)
            ->where('ingredient_id', $this->flour->id)
            ->first();

        $this->assertEquals(8.5, (float) $balance->quantity_on_hand);
        $this->assertEquals(8.5 * 150.0, (float) $balance->total_value);
    }

    public function test_stock_reservation_and_availability(): void
    {
        // 10 kg received
        $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 100.0,
                ],
            ],
            user: $this->user
        );

        $this->assertEquals(10.0, $this->service->getAvailableStock($this->flour, $this->warehouseMain));

        // Reserve 4 kg for an upcoming banquet order
        $this->service->reserveStock($this->warehouseMain, $this->flour, 4.0);

        $this->assertEquals(6.0, $this->service->getAvailableStock($this->flour, $this->warehouseMain));

        // Trying to reserve 7 kg when only 6 kg are available should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->service->reserveStock($this->warehouseMain, $this->flour, 7.0);
    }

    public function test_idempotent_receipt_avoids_duplicate_deductions(): void
    {
        $idempotencyKey = 'po_rcv_'.uniqid();

        $tx1 = $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 100.0,
                ],
            ],
            user: $this->user,
            notes: 'First call',
            idempotencyKey: $idempotencyKey
        );

        // Call again with same idempotency key
        $tx2 = $this->service->recordReceipt(
            warehouse: $this->warehouseMain,
            items: [
                [
                    'ingredient_id' => $this->flour->id,
                    'quantity' => 10.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 100.0,
                ],
            ],
            user: $this->user,
            notes: 'Duplicate retry',
            idempotencyKey: $idempotencyKey
        );

        $this->assertEquals($tx1->id, $tx2->id);

        $balance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseMain->id)
            ->where('ingredient_id', $this->flour->id)
            ->first();

        // Must still be exactly 10 kg, NOT 20 kg
        $this->assertEquals(10.0, (float) $balance->quantity_on_hand);
    }
}
