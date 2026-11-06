<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WasteLog;
use App\Services\InventoryLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryReportsAndWasteTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected User $ownerA;

    protected User $ownerB;

    protected Warehouse $warehouseA;

    protected Warehouse $warehouseB;

    protected Unit $kg;

    protected Ingredient $lamb;

    protected InventoryLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(InventoryLedgerService::class);

        $this->vendorA = Vendor::create([
            'name' => 'Grill Bar A',
            'slug' => 'grill-a-'.uniqid(),
            'email' => 'admin-'.uniqid().'@grill-a.am',
            'phone' => '+37410998811',
            'currency' => 'AMD',
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Sushi Bar B',
            'slug' => 'sushi-b-'.uniqid(),
            'email' => 'admin-'.uniqid().'@sushi-b.am',
            'phone' => '+37410998822',
            'currency' => 'USD',
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'ownera-'.uniqid().'@grill-a.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'ownerb-'.uniqid().'@sushi-b.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouseA = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Main Meat Cooler A',
            'code' => 'WH-A-MEAT',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Warehouse B',
            'code' => 'WH-B-01',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();

        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Lamb Cuts',
        ]);

        $this->lamb = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Rack of Lamb',
            'sku' => 'LAMB-RACK-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 7000.0,
            'is_active' => true,
        ]);

        // Stock 30 kg lamb into Warehouse A
        $this->ledgerService->recordReceipt(
            $this->warehouseA,
            [
                [
                    'ingredient_id' => $this->lamb->id,
                    'quantity' => 30.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 7000.0,
                ],
            ],
            $this->ownerA,
            'Initial lamb stock',
            'PO-LAMB-INIT-01'
        );
    }

    public function test_can_view_waste_index_and_create_write_off_via_http(): void
    {
        // 1. View waste index
        $indexResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.waste.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSeeText('Waste & Loss Management');
        $indexResponse->assertSeeText('Total Loss Value');

        // 2. View create form
        $createResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.waste.create'));
        $createResponse->assertOk();
        $createResponse->assertSeeText('Record Waste Write-Off');

        // 3. Post waste write-off
        $payload = [
            'warehouse_id' => $this->warehouseA->id,
            'ingredient_id' => $this->lamb->id,
            'quantity' => 4.5,
            'unit_id' => $this->kg->id,
            'unit_cost' => 7000.0,
            'reason' => 'spoilage',
            'action_taken' => 'discarded',
            'notes' => 'Defrost leak in cooler',
        ];

        $postResponse = $this->actingAs($this->ownerA)->post(route('admin.inventory.waste.store'), $payload);
        $postResponse->assertRedirect(route('admin.inventory.waste.index'));
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('waste_logs', [
            'vendor_id' => $this->vendorA->id,
            'ingredient_id' => $this->lamb->id,
            'quantity' => 4.5,
            'total_cost' => 31500.0, // 4.5 * 7000
            'reason' => 'spoilage',
        ]);

        // Verify warehouse stock depleted from 30 kg to 25.5 kg
        $stock = $this->ledgerService->getAvailableStock($this->lamb, $this->warehouseA);
        $this->assertEquals(25.5, $stock);
    }

    public function test_can_view_waste_detail_sheet_via_http(): void
    {
        $log = WasteLog::create([
            'vendor_id' => $this->vendorA->id,
            'waste_number' => 'WST-TEST-9999',
            'warehouse_id' => $this->warehouseA->id,
            'ingredient_id' => $this->lamb->id,
            'quantity' => 2.0,
            'unit_id' => $this->kg->id,
            'unit_cost' => 7000.0,
            'total_cost' => 14000.0,
            'reason' => 'prep_mistake',
            'action_taken' => 'staff_meal',
            'logged_by' => $this->ownerA->id,
            'notes' => 'Over-trimmed bone portion',
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.waste.show', $log));
        $response->assertOk();
        $response->assertSee('WST-TEST-9999');
        $response->assertSee('Rack of Lamb');
        $response->assertSee('Over-trimmed bone portion');
    }

    public function test_can_view_inventory_reports_dashboard_and_variance_via_http(): void
    {
        // 1. BI Executive Hub
        $hubResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.reports.inventory.index'));
        $hubResponse->assertOk();
        $hubResponse->assertSeeText('Inventory & Costing Intelligence');
        $hubResponse->assertSeeText('Actual vs. Theoretical Food Cost');
        $hubResponse->assertSeeText('ABC Inventory Classification');

        // 2. Food Cost Variance
        $varianceResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.reports.inventory.food-cost'));
        $varianceResponse->assertOk();
        $varianceResponse->assertSeeText('Actual vs. Theoretical Food Cost Variance');
        $varianceResponse->assertSeeText('Theoretical Cost');

        // 3. Waste Analysis
        $wasteAnalysisResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.reports.inventory.waste'));
        $wasteAnalysisResponse->assertOk();
        $wasteAnalysisResponse->assertSeeText('Waste & Spoilage Financial Breakdown');
    }

    public function test_can_view_abc_analysis_and_turnover_reports_via_http(): void
    {
        // 1. ABC Analysis
        $abcResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.reports.inventory.abc-analysis'));
        $abcResponse->assertOk();
        $abcResponse->assertSeeText('ABC Inventory Analysis');
        $abcResponse->assertSeeText('Class A');

        // 2. Turnover & Dead Stock
        $turnoverResponse = $this->actingAs($this->ownerA)->get(route('admin.inventory.reports.inventory.turnover', ['days' => 30]));
        $turnoverResponse->assertOk();
        $turnoverResponse->assertSeeText('Inventory Turnover & Dead Stock Analysis');
        $turnoverResponse->assertSeeText('Cost of Goods Consumed');
    }

    public function test_cross_vendor_waste_and_reports_isolation(): void
    {
        $logA = WasteLog::create([
            'vendor_id' => $this->vendorA->id,
            'waste_number' => 'WST-SECRET-A-001',
            'warehouse_id' => $this->warehouseA->id,
            'ingredient_id' => $this->lamb->id,
            'quantity' => 1.0,
            'unit_id' => $this->kg->id,
            'unit_cost' => 7000.0,
            'total_cost' => 7000.0,
            'reason' => 'damage',
        ]);

        // Owner B cannot view Owner A's waste sheet (returns 403 or 404 due to tenant scope)
        $response = $this->actingAs($this->ownerB)->get(route('admin.inventory.waste.show', $logA));
        $this->assertTrue(in_array($response->status(), [403, 404], true));

        // Owner B viewing waste index does NOT see Owner A's secret write-off
        $indexResponse = $this->actingAs($this->ownerB)->get(route('admin.inventory.waste.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('WST-SECRET-A-001');
    }
}
