<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use App\Services\InventoryReportService;
use App\Services\RecipeCostingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected InventoryReportService $reportService;

    protected InventoryLedgerService $ledgerService;

    protected RecipeCostingService $costingService;

    protected Vendor $vendor;

    protected User $user;

    protected Warehouse $warehouse;

    protected Location $location;

    protected Unit $kg;

    protected Unit $portion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->costingService = app(RecipeCostingService::class);
        $this->ledgerService = app(InventoryLedgerService::class);
        $this->reportService = app(InventoryReportService::class);

        $this->vendor = Vendor::create([
            'name' => 'Report Grill House',
            'slug' => 'report-grill-'.uniqid(),
            'email' => 'grill-'.uniqid().'@test.com',
            'phone' => '+37410998822',
            'currency' => 'AMD',
        ]);

        $this->user = User::create([
            'name' => 'General Manager',
            'email' => 'gm-'.uniqid().'@test.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Central Storage',
            'code' => 'CS-REP-01',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall',
            'slug' => 'main-hall-'.uniqid(),
            'is_active' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->portion = Unit::whereNull('vendor_id')->where('code', 'portion')->firstOrFail();
    }

    public function test_food_cost_variance_calculates_theoretical_and_actual_cost(): void
    {
        $ingCat = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Beef Supplies',
        ]);

        $beef = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Ribeye Beef',
            'sku' => 'BEEF-RIB-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $ingCat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 5000.0,
        ]);

        // Stock warehouse with 50 kg beef
        $this->ledgerService->recordReceipt(
            $this->warehouse,
            [
                [
                    'ingredient_id' => $beef->id,
                    'quantity' => 50.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 5000.0,
                ],
            ],
            $this->user,
            'Initial Beef Stock',
            'PO-INIT-BEEF-01',
            'purchase_order'
        );

        $menuCat = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Steaks',
        ]);

        $steakProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $menuCat->id,
            'name' => 'Grilled Ribeye Steak',
            'price' => 7500.0,
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $steakProduct->id,
            'name' => 'Ribeye Steak Recipe',
            'code' => 'REC-STEAK-01',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'cost_per_serving' => 1500.0, // 0.3 kg beef @ 5000/kg
            'status' => Recipe::STATUS_APPROVED,
            'is_approved' => true,
            'is_active' => true,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $recipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $beef->id,
            'quantity' => 0.3,
            'unit_id' => $this->kg->id,
            'gross_weight' => 0.3,
            'net_weight' => 0.3,
            'cost' => 1500.0,
        ]);

        // 1. Create completed Order with 2 steaks (Theoretical Cost = 2 * 1500 = 3000 AMD)
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-TEST-VAR-001',
            'status' => 'completed',
            'total_amount' => 15000.0,
            'type' => 'dine_in',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $steakProduct->id,
            'product_name' => 'Grilled Ribeye Steak',
            'quantity' => 2,
            'unit_price' => 7500.0,
            'subtotal' => 15000.0,
        ]);

        // 2. Issue 0.72 kg from physical warehouse (Over-consumption / trim waste: 0.72 kg @ 5000 = 3600 AMD)
        $this->ledgerService->recordIssue(
            $this->warehouse,
            [
                [
                    'ingredient_id' => $beef->id,
                    'quantity' => 0.72,
                    'unit_id' => $this->kg->id,
                ],
            ],
            InventoryTransaction::TYPE_ISSUE,
            $this->user,
            'Kitchen steak prep consumption'
        );

        $variance = $this->reportService->getFoodCostVariance($this->vendor, now()->subHour(), now()->addHour());

        $this->assertEquals(15000.0, $variance['total_revenue']);
        $this->assertEquals(3000.0, $variance['theoretical_food_cost']);
        $this->assertEquals(3600.0, $variance['actual_food_cost']);
        $this->assertEquals(600.0, $variance['cost_variance']); // +600 overrun
        $this->assertEquals(20.0, $variance['cost_variance_percentage']); // +20%
    }

    public function test_abc_analysis_classifies_inventory_into_a_b_c(): void
    {
        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'General Ingredients',
        ]);

        // Create 3 ingredients with distinct stock valuation
        // Ing 1: 100 kg @ 800 AMD = 80,000 AMD (~80% of value) -> Class A
        $ingA = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Prime Wagyu',
            'sku' => 'WAGYU-001',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 8000.0,
            'is_active' => true,
        ]);
        $balanceA = $this->ledgerService->getBalanceForUpdate($this->warehouse, $ingA);
        $balanceA->quantity_on_hand = 10.0; // 10 * 8000 = 80,000
        $balanceA->save();

        // Ing 2: 15 kg @ 1000 AMD = 15,000 AMD (~15% of value) -> Class B
        $ingB = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Cheese Mozzarella',
            'sku' => 'MOZZ-002',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 1000.0,
            'is_active' => true,
        ]);
        $balanceB = $this->ledgerService->getBalanceForUpdate($this->warehouse, $ingB);
        $balanceB->quantity_on_hand = 15.0; // 15 * 1000 = 15,000
        $balanceB->save();

        // Ing 3: 50 kg @ 100 AMD = 5,000 AMD (~5% of value) -> Class C
        $ingC = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Sea Salt',
            'sku' => 'SALT-003',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 100.0,
            'is_active' => true,
        ]);
        $balanceC = $this->ledgerService->getBalanceForUpdate($this->warehouse, $ingC);
        $balanceC->quantity_on_hand = 50.0; // 50 * 100 = 5,000
        $balanceC->save();

        $abc = $this->reportService->getAbcAnalysis($this->vendor);

        $this->assertEquals(3, $abc['total_items']);
        $this->assertEquals(100000.0, $abc['total_inventory_value']);
        $this->assertEquals(1, $abc['class_a']['count']);
        $this->assertEquals(80000.0, $abc['class_a']['value']);
        $this->assertEquals(80.0, $abc['class_a']['percentage']);

        $itemA = $abc['items']->firstWhere('name', 'Prime Wagyu');
        $this->assertEquals('A', $itemA['abc_class']);

        $itemB = $abc['items']->firstWhere('name', 'Cheese Mozzarella');
        $this->assertEquals('B', $itemB['abc_class']);

        $itemC = $abc['items']->firstWhere('name', 'Sea Salt');
        $this->assertEquals('C', $itemC['abc_class']);
    }

    public function test_inventory_turnover_and_dead_stock_detection(): void
    {
        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bar Supplies',
        ]);

        // Item 1: Active item with recent issue
        $activeItem = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Espresso Coffee Beans',
            'sku' => 'COFFEE-001',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 4000.0,
            'is_active' => true,
        ]);
        $bal1 = $this->ledgerService->getBalanceForUpdate($this->warehouse, $activeItem);
        $bal1->quantity_on_hand = 20.0;
        $bal1->save();

        // Issue 5 kg of coffee
        $this->ledgerService->recordIssue(
            $this->warehouse,
            [['ingredient_id' => $activeItem->id, 'quantity' => 5.0, 'unit_id' => $this->kg->id]],
            InventoryTransaction::TYPE_ISSUE,
            $this->user,
            'Coffee consumption'
        );

        // Item 2: Dead stock item with stock balance 10 kg, but 0 issues in last 30 days
        $deadItem = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Rare Truffle Oil',
            'sku' => 'TRUFFLE-002',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 15000.0,
            'is_active' => true,
        ]);
        $bal2 = $this->ledgerService->getBalanceForUpdate($this->warehouse, $deadItem);
        $bal2->quantity_on_hand = 10.0;
        $bal2->save();

        $turnover = $this->reportService->getInventoryTurnoverAndDeadStock($this->vendor, 30);

        $this->assertGreaterThan(0, $turnover['inventory_value']);
        $this->assertEquals(1, $turnover['dead_stock_count']);
        $this->assertEquals('Rare Truffle Oil', $turnover['dead_stock_items'][0]['name']);
        $this->assertEquals(150000.0, $turnover['dead_stock_value']); // 10 * 15,000
    }
}
