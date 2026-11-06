<?php

namespace Tests\Unit;

use App\Models\HaccpCcp;
use App\Models\HaccpChecklist;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBatch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\HaccpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HaccpServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected HaccpService $service;

    protected Vendor $vendor;

    protected User $user;

    protected Warehouse $warehouse;

    protected Unit $kg;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(HaccpService::class);

        $this->vendor = Vendor::create([
            'name' => 'HACCP Test Restaurant',
            'slug' => 'haccp-test-'.uniqid(),
            'email' => 'haccp-'.uniqid().'@test.com',
            'phone' => '+37410998800',
            'currency' => 'AMD',
        ]);

        $this->user = User::create([
            'name' => 'HACCP Manager',
            'email' => 'manager-'.uniqid().'@test.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Cold Storage 1',
            'code' => 'CS-01',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();

        $category = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Poultry',
        ]);

        $this->ingredient = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Fresh Chicken Breast',
            'sku' => 'CHICKEN-001',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $category->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 2200.0,
        ]);
    }

    public function test_seed_default_ccps_creates_seven_standard_ccps(): void
    {
        $ccps = $this->service->seedDefaultCcps($this->vendor);

        $this->assertCount(7, $ccps);
        $this->assertDatabaseHas('haccp_ccps', [
            'vendor_id' => $this->vendor->id,
            'code' => 'CCP-2-STORAGE-FRIDGE',
            'min_value' => 0.0,
            'max_value' => 4.0,
        ]);

        // Calling seed again must be idempotent and not create duplicates
        $ccpsSecondRun = $this->service->seedDefaultCcps($this->vendor);
        $this->assertCount(7, $ccpsSecondRun);
        $this->assertEquals(7, HaccpCcp::where('vendor_id', $this->vendor->id)->count());
    }

    public function test_record_temperature_detects_breaches(): void
    {
        $this->service->seedDefaultCcps($this->vendor);
        $fridgeCcp = HaccpCcp::where('vendor_id', $this->vendor->id)
            ->where('code', 'CCP-2-STORAGE-FRIDGE')
            ->firstOrFail();

        // 1. Normal reading (2.4°C is within 0.0 to 4.0)
        $normalLog = $this->service->recordTemperature($this->vendor, [
            'ccp_id' => $fridgeCcp->id,
            'warehouse_id' => $this->warehouse->id,
            'equipment_name' => 'Walk-in Cooler A',
            'measured_value' => 2.4,
            'notes' => 'Routine morning check',
        ], $this->user);

        $this->assertFalse($normalLog->is_breached);
        $this->assertNull($normalLog->corrective_action_taken);

        // 2. High breach reading (7.5°C > 4.0°C limit)
        $breachLog = $this->service->recordTemperature($this->vendor, [
            'ccp_id' => $fridgeCcp->id,
            'warehouse_id' => $this->warehouse->id,
            'equipment_name' => 'Walk-in Cooler A',
            'measured_value' => 7.5,
            'corrective_action_taken' => 'Transferred dairy & poultry to backup cooler #2, called technician',
            'notes' => 'Defrost cycle malfunction suspected',
        ], $this->user);

        $this->assertTrue($breachLog->is_breached);
        $this->assertEquals('Transferred dairy & poultry to backup cooler #2, called technician', $breachLog->corrective_action_taken);
        $this->assertEquals($this->user->id, $breachLog->logged_by);
    }

    public function test_create_checklist_evaluates_pass_rate(): void
    {
        // 1. All passed checklist
        $passedChecklist = $this->service->createChecklist($this->vendor, [
            'title' => 'Opening Sanitation Shift',
            'type' => HaccpChecklist::TYPE_OPENING,
            'shift' => 'morning',
            'items' => [
                ['task' => 'Check handwash stations soap & paper towels', 'is_passed' => true, 'remarks' => 'Full'],
                ['task' => 'Verify sanitizing bucket chemical concentration', 'is_passed' => true, 'remarks' => '200ppm'],
                ['task' => 'Ensure refrigeration displays read <= 4°C', 'is_passed' => true, 'remarks' => 'All ok'],
            ],
        ], $this->user);

        $this->assertEquals(HaccpChecklist::STATUS_COMPLETED, $passedChecklist->status);
        $this->assertCount(3, $passedChecklist->items);

        // 2. Failed checklist when any item fails
        $failedChecklist = $this->service->createChecklist($this->vendor, [
            'title' => 'Closing Hygiene Checklist',
            'type' => HaccpChecklist::TYPE_CLOSING,
            'shift' => 'evening',
            'items' => [
                ['task' => 'Sweep and mop kitchen floor with degreaser', 'is_passed' => true, 'remarks' => 'Done'],
                ['task' => 'Take out meat prep trash bins', 'is_passed' => false, 'remarks' => 'Bin full, awaiting morning crew'],
            ],
        ], $this->user);

        $this->assertEquals(HaccpChecklist::STATUS_FAILED, $failedChecklist->status);
    }

    public function test_get_expiring_batches_categorizes_correctly(): void
    {
        // Batch 1: Expired (2 days ago)
        InventoryBatch::create([
            'vendor_id' => $this->vendor->id,
            'ingredient_id' => $this->ingredient->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-EXP-01',
            'initial_quantity' => 10.0,
            'remaining_quantity' => 10.0,
            'unit_id' => $this->kg->id,
            'production_date' => now()->subDays(10),
            'expiry_date' => now()->subDays(2),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        // Batch 2: Critical (expiring in 2 days <= 3 days)
        InventoryBatch::create([
            'vendor_id' => $this->vendor->id,
            'ingredient_id' => $this->ingredient->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-CRIT-02',
            'initial_quantity' => 15.0,
            'remaining_quantity' => 15.0,
            'unit_id' => $this->kg->id,
            'production_date' => now()->subDays(5),
            'expiry_date' => now()->addDays(2),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        // Batch 3: Warning (expiring in 5 days <= 7 days)
        InventoryBatch::create([
            'vendor_id' => $this->vendor->id,
            'ingredient_id' => $this->ingredient->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-WARN-03',
            'initial_quantity' => 20.0,
            'remaining_quantity' => 20.0,
            'unit_id' => $this->kg->id,
            'production_date' => now()->subDays(2),
            'expiry_date' => now()->addDays(5),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        // Batch 4: Safe (expiring in 20 days > 7 days threshold)
        InventoryBatch::create([
            'vendor_id' => $this->vendor->id,
            'ingredient_id' => $this->ingredient->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-SAFE-04',
            'initial_quantity' => 25.0,
            'remaining_quantity' => 25.0,
            'unit_id' => $this->kg->id,
            'production_date' => now(),
            'expiry_date' => now()->addDays(20),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        $result = $this->service->getExpiringBatches($this->vendor, 7);

        $this->assertEquals(3, $result['total_expiring']);
        $this->assertCount(1, $result['expired']);
        $this->assertEquals('BATCH-EXP-01', $result['expired'][0]->batch_number);

        $this->assertCount(1, $result['critical']);
        $this->assertEquals('BATCH-CRIT-02', $result['critical'][0]->batch_number);

        $this->assertCount(1, $result['warning']);
        $this->assertEquals('BATCH-WARN-03', $result['warning'][0]->batch_number);
    }

    public function test_trace_batch_bidirectional_lookup(): void
    {
        $supplier = Supplier::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Farm Fresh Poultry Ltd',
            'contact_person' => 'Aram Sargsyan',
            'phone' => '+37499112233',
        ]);

        $po = PurchaseOrder::create([
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-HACCP-2026-001',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'total_amount' => 50000.0,
            'order_date' => now()->subDays(3)->toDateString(),
            'received_at' => now()->subDays(1),
        ]);

        $batch = InventoryBatch::create([
            'vendor_id' => $this->vendor->id,
            'ingredient_id' => $this->ingredient->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-TRACE-999',
            'initial_quantity' => 50.0,
            'remaining_quantity' => 38.5,
            'unit_id' => $this->kg->id,
            'unit_cost' => 2200.0,
            'production_date' => now()->subDays(2),
            'expiry_date' => now()->addDays(10),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        $trace = $this->service->traceBatch($this->vendor, 'LOT-TRACE-999');

        $this->assertNotNull($trace['batch']);
        $this->assertEquals('LOT-TRACE-999', $trace['batch']->batch_number);
        $this->assertEquals(38.5, $trace['current_stock']);
        $this->assertEquals('purchase', $trace['source']['type']);
        $this->assertEquals('PO-HACCP-2026-001', $trace['source']['po_number']);
        $this->assertEquals('Farm Fresh Poultry Ltd', $trace['source']['supplier_name']);
    }
}
