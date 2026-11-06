<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use App\Services\OrderStockDepletionService;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderStockDepletionServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected OrderStockDepletionService $service;

    protected InventoryLedgerService $ledgerService;

    protected UnitConversionService $conversionService;

    protected Vendor $vendor;

    protected Warehouse $kitchenWarehouse;

    protected Unit $kg;

    protected Unit $portion;

    protected Ingredient $beef;

    protected Ingredient $butter;

    protected Product $steakDish;

    protected Recipe $steakRecipe;

    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderStockDepletionService::class);
        $this->ledgerService = app(InventoryLedgerService::class);
        $this->conversionService = app(UnitConversionService::class);

        $this->vendor = Vendor::create([
            'name' => 'Gourmet Steakhouse LLC',
            'slug' => 'steakhouse-'.uniqid(),
            'email' => 'info-'.uniqid().'@steakhouse.am',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown-'.uniqid(),
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->portion = Unit::whereNull('vendor_id')->where('code', 'portion')->firstOrFail();

        $this->kitchenWarehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Kitchen Bar & Grill',
            'code' => 'WH-GRILL-01',
            'type' => Warehouse::TYPE_KITCHEN,
            'is_default' => true,
            'is_active' => true,
        ]);

        $ingCat = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Meat & Dairy',
        ]);

        $this->beef = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Black Angus Ribeye Cut',
            'sku' => 'BEEF-RIBEYE',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $ingCat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 8000.0,
        ]);

        $this->butter = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Compound Herb Butter',
            'sku' => 'BUTTER-HERB',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $ingCat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 4000.0,
        ]);

        $menuCat = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Prime Steaks',
        ]);

        $this->steakDish = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $menuCat->id,
            'name' => 'Grilled Ribeye 350g',
            'price' => 12000.0,
            'is_available' => true,
            'allow_out_of_stock_sale' => false,
        ]);

        // Recipe: 1 portion of steak requires 0.35kg beef + 0.05kg butter
        $this->steakRecipe = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->steakDish->id,
            'name' => 'Ribeye Steak Standard Prep',
            'code' => 'REC-STEAK-350',
            'type' => Recipe::TYPE_DISH,
            'status' => Recipe::STATUS_APPROVED,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'is_active' => true,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $this->steakRecipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $this->beef->id,
            'gross_weight' => 0.35,
            'quantity' => 0.35,
            'unit_id' => $this->kg->id,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $this->steakRecipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $this->butter->id,
            'gross_weight' => 0.05,
            'quantity' => 0.05,
            'unit_id' => $this->kg->id,
        ]);
    }

    public function test_deplete_order_deducts_ingredients_from_recipe(): void
    {
        // 1. Initial stock: 10kg beef, 2kg butter
        $this->ledgerService->recordReceipt($this->kitchenWarehouse, [
            ['ingredient_id' => $this->beef->id, 'quantity' => 10.0, 'unit_id' => $this->kg->id, 'unit_cost' => 8000.0],
            ['ingredient_id' => $this->butter->id, 'quantity' => 2.0, 'unit_id' => $this->kg->id, 'unit_cost' => 4000.0],
        ]);

        // 2. Customer orders 2 Ribeye Steaks
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-TEST-001',
            'status' => 'confirmed',
            'subtotal' => 24000.0,
            'total_amount' => 24000.0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->steakDish->id,
            'product_name' => $this->steakDish->name,
            'unit_price' => 12000.0,
            'quantity' => 2,
            'subtotal' => 24000.0,
        ]);

        // 3. Trigger depletion
        $tx = $this->service->depleteOrder($order);

        $this->assertNotNull($tx);
        $this->assertEquals(InventoryTransaction::TYPE_ORDER_CONSUMPTION, $tx->type);
        $this->assertEquals("ORDER-DEPLETE-{$order->id}", $tx->idempotency_key);
        $this->assertEquals(Order::class, $tx->reference_type);
        $this->assertEquals($order->id, $tx->reference_id);

        // 2 steaks * 0.35kg = 0.70kg beef deducted. (10.0 - 0.70 = 9.30kg remaining)
        $beefBalance = InventoryBalance::where('warehouse_id', $this->kitchenWarehouse->id)
            ->where('ingredient_id', $this->beef->id)
            ->first();
        $this->assertEquals(9.30, (float) $beefBalance->quantity_on_hand);

        // 2 steaks * 0.05kg = 0.10kg butter deducted. (2.0 - 0.10 = 1.90kg remaining)
        $butterBalance = InventoryBalance::where('warehouse_id', $this->kitchenWarehouse->id)
            ->where('ingredient_id', $this->butter->id)
            ->first();
        $this->assertEquals(1.90, (float) $butterBalance->quantity_on_hand);
    }

    public function test_double_depletion_is_prevented_by_idempotency(): void
    {
        $this->ledgerService->recordReceipt($this->kitchenWarehouse, [
            ['ingredient_id' => $this->beef->id, 'quantity' => 5.0, 'unit_id' => $this->kg->id, 'unit_cost' => 8000.0],
            ['ingredient_id' => $this->butter->id, 'quantity' => 1.0, 'unit_id' => $this->kg->id, 'unit_cost' => 4000.0],
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-TEST-002',
            'status' => 'confirmed',
            'subtotal' => 12000.0,
            'total_amount' => 12000.0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->steakDish->id,
            'product_name' => $this->steakDish->name,
            'unit_price' => 12000.0,
            'quantity' => 1,
            'subtotal' => 12000.0,
        ]);

        // First depletion
        $tx1 = $this->service->depleteOrder($order);
        // Second depletion attempt
        $tx2 = $this->service->depleteOrder($order);

        $this->assertNotNull($tx1);
        $this->assertEquals($tx1->id, $tx2->id);

        // Only 1 portion (0.35kg) deducted, not 0.70kg!
        $beefBalance = InventoryBalance::where('warehouse_id', $this->kitchenWarehouse->id)
            ->where('ingredient_id', $this->beef->id)
            ->first();
        $this->assertEquals(4.65, (float) $beefBalance->quantity_on_hand); // 5.0 - 0.35
    }

    public function test_reverse_order_depletion_returns_stock_on_cancellation(): void
    {
        $this->ledgerService->recordReceipt($this->kitchenWarehouse, [
            ['ingredient_id' => $this->beef->id, 'quantity' => 5.0, 'unit_id' => $this->kg->id, 'unit_cost' => 8000.0],
            ['ingredient_id' => $this->butter->id, 'quantity' => 1.0, 'unit_id' => $this->kg->id, 'unit_cost' => 4000.0],
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-TEST-003',
            'status' => 'confirmed',
            'subtotal' => 12000.0,
            'total_amount' => 12000.0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->steakDish->id,
            'product_name' => $this->steakDish->name,
            'unit_price' => 12000.0,
            'quantity' => 2,
            'subtotal' => 24000.0,
        ]);

        // Deplete: 2 * 0.35 = 0.7kg beef
        $this->service->depleteOrder($order);

        $beefBal1 = InventoryBalance::where('warehouse_id', $this->kitchenWarehouse->id)
            ->where('ingredient_id', $this->beef->id)
            ->first();
        $this->assertEquals(4.30, (float) $beefBal1->quantity_on_hand);

        // Reverse
        $reversalTx = $this->service->reverseOrderDepletion($order, 'Customer cancelled table order');

        $this->assertNotNull($reversalTx);
        $this->assertEquals("ORDER-REVERSE-{$order->id}", $reversalTx->idempotency_key);

        // Stock restored back to original 5.0kg!
        $beefBal2 = InventoryBalance::where('warehouse_id', $this->kitchenWarehouse->id)
            ->where('ingredient_id', $this->beef->id)
            ->first();
        $this->assertEquals(5.0, (float) $beefBal2->quantity_on_hand);
    }

    public function test_calculate_available_portions_and_stop_list_trigger(): void
    {
        // 1. Initial stock: 1.05kg beef (exactly 3 portions of 0.35kg), 1.0kg butter
        $this->ledgerService->recordReceipt($this->kitchenWarehouse, [
            ['ingredient_id' => $this->beef->id, 'quantity' => 1.05, 'unit_id' => $this->kg->id, 'unit_cost' => 8000.0],
            ['ingredient_id' => $this->butter->id, 'quantity' => 1.0, 'unit_id' => $this->kg->id, 'unit_cost' => 4000.0],
        ]);

        // Portions available = floor(1.05 / 0.35) = 3 portions
        $portions = $this->service->calculateAvailablePortions($this->steakDish, $this->kitchenWarehouse);
        $this->assertEquals(3, $portions);
        $this->assertTrue($this->steakDish->fresh()->is_available);

        // 2. Order 3 portions to consume all remaining beef
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-TEST-004',
            'status' => 'confirmed',
            'subtotal' => 36000.0,
            'total_amount' => 36000.0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->steakDish->id,
            'product_name' => $this->steakDish->name,
            'unit_price' => 12000.0,
            'quantity' => 3,
            'subtotal' => 36000.0,
        ]);

        $this->service->depleteOrder($order);

        // Remaining beef = 0. Available portions = 0.
        $newPortions = $this->service->calculateAvailablePortions($this->steakDish, $this->kitchenWarehouse);
        $this->assertEquals(0, $newPortions);

        // Assert that product was automatically put on Stop-List (is_available = false)
        $this->assertFalse($this->steakDish->fresh()->is_available);

        // 3. Goods intake replenishment: receive 3.5kg beef (10 portions)
        $this->ledgerService->recordReceipt($this->kitchenWarehouse, [
            ['ingredient_id' => $this->beef->id, 'quantity' => 3.5, 'unit_id' => $this->kg->id, 'unit_cost' => 8000.0],
        ]);

        $this->service->syncStopList((int) $this->vendor->id, [$this->steakDish->id], $this->kitchenWarehouse);

        // Assert that product is now restored back to available
        $this->assertTrue($this->steakDish->fresh()->is_available);
        $this->assertEquals(10, $this->service->calculateAvailablePortions($this->steakDish, $this->kitchenWarehouse));
    }
}
