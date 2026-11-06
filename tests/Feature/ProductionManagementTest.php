<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductionManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected User $ownerA;

    protected User $ownerB;

    protected Warehouse $warehouseA_Dry;

    protected Warehouse $warehouseA_Kitchen;

    protected Warehouse $warehouseB;

    protected Unit $kg;

    protected Unit $portion;

    protected Ingredient $meat;

    protected Ingredient $patty;

    protected Recipe $pattyRecipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendorA = Vendor::create([
            'name' => 'Burger Joint A',
            'slug' => 'burger-a-'.uniqid(),
            'email' => 'admin-'.uniqid().'@burger-a.am',
            'phone' => '+37410998811',
            'currency' => 'AMD',
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Pizza Place B',
            'slug' => 'pizza-b-'.uniqid(),
            'email' => 'admin-'.uniqid().'@pizza-b.am',
            'phone' => '+37410998822',
            'currency' => 'USD',
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'ownera-'.uniqid().'@burger-a.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'ownerb-'.uniqid().'@pizza-b.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->portion = Unit::whereNull('vendor_id')->where('code', 'portion')->firstOrFail();

        $this->warehouseA_Dry = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Meat Walk-in Freezer',
            'code' => 'WH-A-FREEZER',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->warehouseA_Kitchen = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Kitchen Prep Line',
            'code' => 'WH-A-PREP',
            'type' => Warehouse::TYPE_KITCHEN,
            'is_default' => false,
        ]);

        $this->warehouseB = Warehouse::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Warehouse B',
            'code' => 'WH-B-01',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Meats',
        ]);

        $this->meat = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Prime Beef Chuck',
            'sku' => 'BEEF-CHUCK-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 3500.0,
        ]);

        $this->patty = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'House Ground Beef Patty (180g)',
            'sku' => 'PATTY-180G',
            'type' => Ingredient::TYPE_SEMI_FINISHED,
            'category_id' => $cat->id,
            'unit_id' => $this->portion->id,
            'purchase_price' => 0.0,
        ]);

        $this->pattyRecipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Burger Patty Production',
            'code' => 'REC-PATTY-01',
            'type' => Recipe::TYPE_SEMI_FINISHED,
            'yield_quantity' => 10.0,
            'yield_unit_id' => $this->portion->id,
            'produced_ingredient_id' => $this->patty->id,
            'shelf_life_hours' => 72,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendorA->id,
            'recipe_id' => $this->pattyRecipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $this->meat->id,
            'quantity' => 1.8,
            'unit_id' => $this->kg->id,
            'gross_weight' => 1.8,
        ]);
    }

    public function test_can_view_production_orders_and_kpis(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.production.index'));

        $response->assertOk();
        $response->assertSee('Production Orders & Processing');
        $response->assertSee('In Progress');
        $response->assertSee('Completed Batches');
    }

    public function test_can_create_production_order_via_http(): void
    {
        $payload = [
            'recipe_id' => $this->pattyRecipe->id,
            'source_warehouse_id' => $this->warehouseA_Dry->id,
            'destination_warehouse_id' => $this->warehouseA_Kitchen->id,
            'planned_quantity' => 50.0,
            'produced_ingredient_id' => $this->patty->id,
            'labor_cost' => 1500.0,
            'overhead_cost' => 500.0,
            'notes' => 'Minced coarse grind through 8mm plate.',
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.production.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_orders', [
            'vendor_id' => $this->vendorA->id,
            'recipe_id' => $this->pattyRecipe->id,
            'planned_quantity' => 50.0,
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $order = ProductionOrder::where('vendor_id', $this->vendorA->id)->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->items);
        // 1.8kg * 5 = 9.0kg beef
        $this->assertEquals(9.0, (float) $order->items->first()->planned_quantity);
    }

    public function test_can_start_and_complete_production_order_via_http(): void
    {
        // 1. Supply raw beef into warehouseA_Dry
        app(InventoryLedgerService::class)->recordReceipt($this->warehouseA_Dry, [
            ['ingredient_id' => $this->meat->id, 'quantity' => 50.0, 'unit_id' => $this->kg->id, 'unit_cost' => 3500.0],
        ]);

        // 2. Create production order for 20 patties
        $payload = [
            'recipe_id' => $this->pattyRecipe->id,
            'source_warehouse_id' => $this->warehouseA_Dry->id,
            'destination_warehouse_id' => $this->warehouseA_Kitchen->id,
            'planned_quantity' => 20.0,
            'produced_ingredient_id' => $this->patty->id,
        ];

        $this->actingAs($this->ownerA)->post(route('admin.inventory.production.store'), $payload);
        $order = ProductionOrder::where('vendor_id', $this->vendorA->id)->firstOrFail();

        // 3. Start order
        $startRes = $this->actingAs($this->ownerA)->post(route('admin.inventory.production.start', $order));
        $startRes->assertRedirect();
        $this->assertEquals(ProductionOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        // 4. Complete order
        $completePayload = [
            'actual_quantity' => 20.0,
            'labor_cost' => 800.0,
            'overhead_cost' => 200.0,
            'batch_number' => 'LOT-PATTY-202611',
            'items' => [
                [
                    'id' => $order->items->first()->id,
                    'actual_quantity' => 3.6, // exactly 1.8 * 2
                    'waste_quantity' => 0.1,
                ],
            ],
        ];

        $completeRes = $this->actingAs($this->ownerA)->post(route('admin.inventory.production.complete', $order), $completePayload);
        $completeRes->assertRedirect();

        $order->refresh();
        $this->assertEquals(ProductionOrder::STATUS_COMPLETED, $order->status);
        $this->assertEquals(20.0, (float) $order->actual_quantity);
        $this->assertEquals('LOT-PATTY-202611', $order->batch_number);

        // Verify that 3.6kg was deducted from warehouseA_Dry (50 - 3.6 = 46.4)
        $beefBal = app(InventoryLedgerService::class)->getBalanceForUpdate($this->warehouseA_Dry, $this->meat);
        $this->assertEquals(46.4, (float) $beefBal->quantity_on_hand);

        // Verify that 20 patties were received into warehouseA_Kitchen
        $pattyBal = app(InventoryLedgerService::class)->getBalanceForUpdate($this->warehouseA_Kitchen, $this->patty);
        $this->assertEquals(20.0, (float) $pattyBal->quantity_on_hand);
    }

    public function test_cross_vendor_production_order_access_is_forbidden(): void
    {
        // Create order for Vendor A while acting as Owner A
        $this->actingAs($this->ownerA);
        $order = ProductionOrder::create([
            'vendor_id' => $this->vendorA->id,
            'order_number' => 'PRD-A-SECRET',
            'recipe_id' => $this->pattyRecipe->id,
            'source_warehouse_id' => $this->warehouseA_Dry->id,
            'destination_warehouse_id' => $this->warehouseA_Kitchen->id,
            'planned_quantity' => 10.0,
            'unit_id' => $this->portion->id,
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        // Vendor B attempts to view or start Vendor A's order
        $viewRes = $this->actingAs($this->ownerB)->get(route('admin.inventory.production.show', $order));
        $this->assertTrue(in_array($viewRes->status(), [403, 404], true));

        $startRes = $this->actingAs($this->ownerB)->post(route('admin.inventory.production.start', $order));
        $this->assertTrue(in_array($startRes->status(), [403, 404], true));
    }

    public function test_cannot_delete_completed_production_order(): void
    {
        $this->actingAs($this->ownerA);
        $order = ProductionOrder::create([
            'vendor_id' => $this->vendorA->id,
            'order_number' => 'PRD-A-DONE',
            'recipe_id' => $this->pattyRecipe->id,
            'source_warehouse_id' => $this->warehouseA_Dry->id,
            'destination_warehouse_id' => $this->warehouseA_Kitchen->id,
            'planned_quantity' => 10.0,
            'actual_quantity' => 10.0,
            'unit_id' => $this->portion->id,
            'status' => ProductionOrder::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->ownerA)->delete(route('admin.inventory.production.destroy', $order));
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('production_orders', ['id' => $order->id, 'deleted_at' => null]);
    }
}
