<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBatch;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use App\Services\ProductionService;
use App\Services\RecipeCostingService;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ProductionService $service;

    protected InventoryLedgerService $ledgerService;

    protected UnitConversionService $conversionService;

    protected RecipeCostingService $costingService;

    protected Vendor $vendor;

    protected Warehouse $rawWarehouse;

    protected Warehouse $kitchenWarehouse;

    protected Unit $kg;

    protected Unit $liter;

    protected Unit $portion;

    protected Ingredient $flour;

    protected Ingredient $water;

    protected Ingredient $pizzaDough;

    protected Recipe $doughRecipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversionService = app(UnitConversionService::class);
        $this->costingService = app(RecipeCostingService::class);
        $this->ledgerService = app(InventoryLedgerService::class);
        $this->service = app(ProductionService::class);

        $this->vendor = Vendor::create([
            'name' => 'Artisan Bakery LLC',
            'slug' => 'artisan-'.uniqid(),
            'email' => 'chef-'.uniqid().'@artisan.am',
            'phone' => '+37410223344',
            'currency' => 'AMD',
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->liter = Unit::whereNull('vendor_id')->where('code', 'l')->firstOrFail();
        $this->portion = Unit::whereNull('vendor_id')->where('code', 'portion')->firstOrFail();

        $this->rawWarehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Dry Storage Warehouse',
            'code' => 'WH-DRY-01',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->kitchenWarehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Kitchen Prep Station',
            'code' => 'WH-PREP-01',
            'type' => Warehouse::TYPE_KITCHEN,
            'is_default' => false,
        ]);

        $category = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Baking Ingredients',
        ]);

        $this->flour = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Organic Bread Flour',
            'sku' => 'FLOUR-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $category->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 500.0, // 500 AMD/kg
        ]);

        $this->water = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Filtered Water',
            'sku' => 'WATER-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $category->id,
            'unit_id' => $this->liter->id,
            'last_purchase_price' => 50.0, // 50 AMD/L
        ]);

        $this->pizzaDough = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Neapolitan Pizza Dough Ball',
            'sku' => 'SEMI-DOUGH-01',
            'type' => Ingredient::TYPE_SEMI_FINISHED,
            'category_id' => $category->id,
            'unit_id' => $this->portion->id,
            'last_purchase_price' => 0.0,
        ]);

        // Recipe: 10 portions of pizza dough uses 2.0 kg flour + 1.2 L water
        $this->doughRecipe = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Pizza Dough Recipe',
            'code' => 'REC-DOUGH-01',
            'type' => Recipe::TYPE_SEMI_FINISHED,
            'yield_quantity' => 10.0,
            'yield_unit_id' => $this->portion->id,
            'produced_ingredient_id' => $this->pizzaDough->id,
            'shelf_life_hours' => 48,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $this->doughRecipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $this->flour->id,
            'quantity' => 2.0,
            'unit_id' => $this->kg->id,
            'gross_weight' => 2.0,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $this->doughRecipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $this->water->id,
            'quantity' => 1.2,
            'unit_id' => $this->liter->id,
            'gross_weight' => 1.2,
        ]);
    }

    public function test_create_order_derives_and_scales_bom_items_from_recipe(): void
    {
        // Produce 50 portions (5x standard yield of 10 portions)
        $order = $this->service->createOrder($this->vendor, [
            'recipe_id' => $this->doughRecipe->id,
            'source_warehouse_id' => $this->rawWarehouse->id,
            'destination_warehouse_id' => $this->kitchenWarehouse->id,
            'planned_quantity' => 50.0,
            'labor_cost' => 2000.0,
            'overhead_cost' => 500.0,
        ]);

        $this->assertEquals(ProductionOrder::STATUS_DRAFT, $order->status);
        $this->assertEquals(50.0, (float) $order->planned_quantity);
        $this->assertEquals($this->pizzaDough->id, $order->produced_ingredient_id);
        $this->assertCount(2, $order->items);

        // Flour: 2.0kg * 5 = 10.0kg. Cost: 10 * 500 = 5000 AMD
        $flourItem = $order->items->firstWhere('ingredient_id', $this->flour->id);
        $this->assertNotNull($flourItem);
        $this->assertEquals(10.0, (float) $flourItem->planned_quantity);
        $this->assertEquals(5000.0, (float) $flourItem->total_cost);

        // Water: 1.2L * 5 = 6.0L. Cost: 6 * 50 = 300 AMD
        $waterItem = $order->items->firstWhere('ingredient_id', $this->water->id);
        $this->assertNotNull($waterItem);
        $this->assertEquals(6.0, (float) $waterItem->planned_quantity);
        $this->assertEquals(300.0, (float) $waterItem->total_cost);

        // Total Material Cost = 5000 + 300 = 5300 AMD
        $this->assertEquals(5300.0, (float) $order->material_cost);
        // Total Cost = 5300 + 2000 (labor) + 500 (overhead) = 7800 AMD
        $this->assertEquals(7800.0, (float) $order->total_cost);
        // Unit Cost = 7800 / 50 = 156 AMD / portion
        $this->assertEquals(156.0, (float) $order->unit_cost);
    }

    public function test_start_order_transitions_status_to_in_progress(): void
    {
        $order = $this->service->createOrder($this->vendor, [
            'recipe_id' => $this->doughRecipe->id,
            'source_warehouse_id' => $this->rawWarehouse->id,
            'destination_warehouse_id' => $this->kitchenWarehouse->id,
            'planned_quantity' => 20.0,
        ]);

        $this->assertTrue($order->isDraft());
        $startedOrder = $this->service->startOrder($order);

        $this->assertTrue($startedOrder->isInProgress());
        $this->assertNotNull($startedOrder->actual_start_at);
    }

    public function test_complete_order_deducts_raw_materials_and_stocks_produced_item_with_batch(): void
    {
        // 1. Initial intake of raw materials into rawWarehouse
        $this->ledgerService->recordReceipt($this->rawWarehouse, [
            ['ingredient_id' => $this->flour->id, 'quantity' => 100.0, 'unit_id' => $this->kg->id, 'unit_cost' => 500.0],
            ['ingredient_id' => $this->water->id, 'quantity' => 100.0, 'unit_id' => $this->liter->id, 'unit_cost' => 50.0],
        ]);

        // 2. Create and start order for 20 dough portions
        // Standard BOM for 20 portions: Flour = 4.0kg, Water = 2.4L
        $order = $this->service->createOrder($this->vendor, [
            'recipe_id' => $this->doughRecipe->id,
            'source_warehouse_id' => $this->rawWarehouse->id,
            'destination_warehouse_id' => $this->kitchenWarehouse->id,
            'planned_quantity' => 20.0,
            'labor_cost' => 1000.0,
        ]);

        $this->service->startOrder($order);

        // 3. Complete order with actual yield of 22 portions (better than planned)
        $completedOrder = $this->service->completeOrder($order, [
            'actual_quantity' => 22.0,
            'labor_cost' => 1000.0,
            'overhead_cost' => 200.0,
            'batch_number' => 'LOT-DOUGH-BATCH-01',
        ]);

        $this->assertTrue($completedOrder->isCompleted());
        $this->assertEquals(22.0, (float) $completedOrder->actual_quantity);
        $this->assertNotNull($completedOrder->issue_transaction_id);
        $this->assertNotNull($completedOrder->receipt_transaction_id);

        // Yield variance: (22 - 20) / 20 * 100 = +10.0%
        $this->assertEquals(10.0, (float) $completedOrder->yield_variance_percent);

        // Check raw material stock deduction in rawWarehouse
        $flourBalance = $this->ledgerService->getBalanceForUpdate($this->rawWarehouse, $this->flour);
        $this->assertEquals(96.0, (float) $flourBalance->quantity_on_hand); // 100 - 4 = 96

        $waterBalance = $this->ledgerService->getBalanceForUpdate($this->rawWarehouse, $this->water);
        $this->assertEquals(97.6, (float) $waterBalance->quantity_on_hand); // 100 - 2.4 = 97.6

        // Check produced item stock intake in kitchenWarehouse
        $doughBalance = $this->ledgerService->getBalanceForUpdate($this->kitchenWarehouse, $this->pizzaDough);
        $this->assertEquals(22.0, (float) $doughBalance->quantity_on_hand);

        // Check that Batch record was created
        $batch = InventoryBatch::where('vendor_id', $this->vendor->id)
            ->where('batch_number', 'LOT-DOUGH-BATCH-01')
            ->first();

        $this->assertNotNull($batch);
        $this->assertEquals($this->pizzaDough->id, $batch->ingredient_id);
        $this->assertEquals($this->kitchenWarehouse->id, $batch->warehouse_id);
        $this->assertEquals(22.0, (float) $batch->initial_quantity);
        $this->assertEquals(22.0, (float) $batch->remaining_quantity);
        $this->assertEquals(InventoryBatch::STATUS_ACTIVE, $batch->status);
        $this->assertEquals(InventoryBatch::SOURCE_PRODUCTION, $batch->source_type);
        $this->assertNotNull($batch->expiry_date);
    }

    public function test_cannot_complete_already_completed_or_cancelled_order(): void
    {
        $order = $this->service->createOrder($this->vendor, [
            'recipe_id' => $this->doughRecipe->id,
            'source_warehouse_id' => $this->rawWarehouse->id,
            'destination_warehouse_id' => $this->kitchenWarehouse->id,
            'planned_quantity' => 10.0,
        ]);

        $this->service->cancelOrder($order, 'Cancelled for test');
        $this->assertTrue($order->isCancelled());

        $this->expectException(InvalidArgumentException::class);
        $this->service->completeOrder($order, ['actual_quantity' => 10.0]);
    }
}
