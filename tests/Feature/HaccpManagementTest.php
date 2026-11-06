<?php

namespace Tests\Feature;

use App\Models\HaccpCcp;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBatch;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HaccpManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected User $ownerA;

    protected User $ownerB;

    protected Warehouse $warehouseA;

    protected Warehouse $warehouseB;

    protected Unit $kg;

    protected Ingredient $beef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendorA = Vendor::create([
            'name' => 'Steakhouse A',
            'slug' => 'steakhouse-a-'.uniqid(),
            'email' => 'admin-'.uniqid().'@steak-a.am',
            'phone' => '+37410998811',
            'currency' => 'AMD',
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Bakery B',
            'slug' => 'bakery-b-'.uniqid(),
            'email' => 'admin-'.uniqid().'@bakery-b.am',
            'phone' => '+37410998822',
            'currency' => 'USD',
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'ownera-'.uniqid().'@steak-a.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'ownerb-'.uniqid().'@bakery-b.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouseA = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Meat Chiller A',
            'code' => 'WH-A-CHILL',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Flour Store B',
            'code' => 'WH-B-FLOUR',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();

        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Meat Supplies',
        ]);

        $this->beef = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Angus Ribeye',
            'sku' => 'ANGUS-RIB-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 7500.0,
        ]);
    }

    public function test_can_view_haccp_dashboard_and_temperatures(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.haccp.dashboard'));

        $response->assertOk();
        $response->assertSeeText('HACCP & Food Safety Management');
        $response->assertSeeText('Critical Control Points (CCPs)');
        $response->assertSeeText('Recent Temperature Logs');
    }

    public function test_can_seed_ccps_via_http(): void
    {
        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.haccp.ccps.seed'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('haccp_ccps', [
            'vendor_id' => $this->vendorA->id,
            'code' => 'CCP-2-STORAGE-FRIDGE',
        ]);
    }

    public function test_can_log_temperature_and_submit_checklist_via_http(): void
    {
        // 1. Seed CCP
        $this->actingAs($this->ownerA)->post(route('admin.inventory.haccp.ccps.seed'));
        $ccp = HaccpCcp::where('vendor_id', $this->vendorA->id)->where('code', 'CCP-2-STORAGE-FRIDGE')->firstOrFail();

        // 2. Post temperature log
        $tempPayload = [
            'ccp_id' => $ccp->id,
            'warehouse_id' => $this->warehouseA->id,
            'equipment_name' => 'Walk-in Chiller #1',
            'measured_value' => 3.2,
            'notes' => 'Afternoon standard inspection',
        ];

        $tempResponse = $this->actingAs($this->ownerA)->post(route('admin.inventory.haccp.temperatures.store'), $tempPayload);
        $tempResponse->assertRedirect();
        $tempResponse->assertSessionHas('success');

        $this->assertDatabaseHas('haccp_temperature_logs', [
            'vendor_id' => $this->vendorA->id,
            'ccp_id' => $ccp->id,
            'measured_value' => 3.2,
            'is_breached' => false,
        ]);

        // 3. Post checklist
        $checklistPayload = [
            'title' => 'Evening Sanitation Walkthrough',
            'type' => 'closing',
            'shift' => 'evening',
            'items' => [
                ['task' => 'Sterilize prep tables with quaternary ammonium', 'is_passed' => 1, 'remarks' => 'Completed'],
                ['task' => 'Ensure raw meat walk-in door sealed tight', 'is_passed' => 1, 'remarks' => 'Door sealed'],
            ],
        ];

        $chkResponse = $this->actingAs($this->ownerA)->post(route('admin.inventory.haccp.checklists.store'), $checklistPayload);
        $chkResponse->assertRedirect();
        $chkResponse->assertSessionHas('success');

        $this->assertDatabaseHas('haccp_checklists', [
            'vendor_id' => $this->vendorA->id,
            'title' => 'Evening Sanitation Walkthrough',
            'status' => 'completed',
        ]);
    }

    public function test_can_view_batches_fefo_and_traceability_recall_via_http(): void
    {
        $supplier = Supplier::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Highland Meats',
            'phone' => '+37477112233',
        ]);

        $batch = InventoryBatch::create([
            'vendor_id' => $this->vendorA->id,
            'ingredient_id' => $this->beef->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_number' => 'LOT-ANGUS-FEFO-001',
            'initial_quantity' => 20.0,
            'remaining_quantity' => 18.0,
            'unit_id' => $this->kg->id,
            'production_date' => now()->subDay(),
            'expiry_date' => now()->addDays(4),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'supplier_id' => $supplier->id,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        // Test batches view
        $batchesResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.haccp.batches'));
        $batchesResponse->assertOk();
        $batchesResponse->assertSee('LOT-ANGUS-FEFO-001');
        $batchesResponse->assertSee('Angus Ribeye');

        // Test traceability recall audit view
        $traceResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.haccp.traceability', [
            'batch_number' => 'LOT-ANGUS-FEFO-001',
        ]));
        $traceResponse->assertOk();
        $traceResponse->assertSee('Traceability & Recall Audit');
        $traceResponse->assertSee('LOT-ANGUS-FEFO-001');
        $traceResponse->assertSee('Highland Meats');
    }

    public function test_cross_vendor_haccp_access_is_forbidden(): void
    {
        // Owner B cannot see batches or CCPs of Vendor A
        InventoryBatch::create([
            'vendor_id' => $this->vendorA->id,
            'ingredient_id' => $this->beef->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_number' => 'SECRET-A-LOT-777',
            'initial_quantity' => 10.0,
            'remaining_quantity' => 10.0,
            'unit_id' => $this->kg->id,
            'production_date' => now(),
            'expiry_date' => now()->addDays(5),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        // When Owner B accesses batches, they cannot see Vendor A's batch
        $response = $this->actingAs($this->ownerB)->get(route('admin.inventory.haccp.batches'));
        $response->assertOk();
        $response->assertDontSee('SECRET-A-LOT-777');

        // When Owner B searches for Vendor A's batch in traceability, it returns not found / empty
        $traceResponse = $this->actingAs($this->ownerB)->get(route('admin.inventory.haccp.traceability', [
            'batch_number' => 'SECRET-A-LOT-777',
        ]));
        $traceResponse->assertOk();
        $traceResponse->assertSee('No inventory batch record matches batch number');
    }
}
