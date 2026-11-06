<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\DTOs\OrderItemDTO;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderInventoryDepletionIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected Warehouse $warehouse;

    protected Unit $kg;

    protected Unit $portion;

    protected Ingredient $coffeeBeans;

    protected Product $espresso;

    protected Recipe $espressoRecipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Artisan Roastery',
            'slug' => 'roastery-'.uniqid(),
            'email' => 'roastery-'.uniqid().'@test.am',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Roastery Coffee Bar',
            'slug' => 'bar-'.uniqid(),
        ]);

        $this->owner = User::create([
            'name' => 'Roaster Owner',
            'email' => 'owner-'.uniqid().'@test.am',
            'password' => bcrypt('secret123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->portion = Unit::whereNull('vendor_id')->where('code', 'portion')->firstOrFail();

        $this->warehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bar Prep Counter',
            'code' => 'WH-BAR-01',
            'type' => Warehouse::TYPE_KITCHEN,
            'is_default' => true,
            'is_active' => true,
        ]);

        $ingCat = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Coffee & Syrups',
        ]);

        $this->coffeeBeans = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Ethiopia Yirgacheffe Beans',
            'sku' => 'BEANS-ETHIOPIA',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $ingCat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 12000.0,
        ]);

        $menuCat = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Espresso Drinks',
        ]);

        $this->espresso = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $menuCat->id,
            'name' => 'Single Origin Double Espresso',
            'price' => 1500.0,
            'is_available' => true,
            'allow_out_of_stock_sale' => false,
        ]);

        // Recipe: 1 double shot espresso uses 0.018 kg (18g) of ground coffee
        $this->espressoRecipe = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->espresso->id,
            'name' => 'Double Espresso Technical Card',
            'code' => 'REC-ESP-DOUBLE',
            'type' => Recipe::TYPE_BEVERAGE,
            'status' => Recipe::STATUS_APPROVED,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'is_active' => true,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $this->espressoRecipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $this->coffeeBeans->id,
            'gross_weight' => 0.018,
            'quantity' => 0.018,
            'unit_id' => $this->kg->id,
        ]);
    }

    public function test_order_creation_triggers_automatic_stock_depletion(): void
    {
        // 1. Initial stock: 1.0 kg of coffee beans (approx 55 cups)
        app(InventoryLedgerService::class)->recordReceipt($this->warehouse, [
            ['ingredient_id' => $this->coffeeBeans->id, 'quantity' => 1.0, 'unit_id' => $this->kg->id, 'unit_cost' => 12000.0],
        ]);

        // 2. Submit order for 5 double espressos
        $dto = new CreateOrderDTO(
            locationId: $this->location->id,
            type: 'dine_in',
            items: [
                new OrderItemDTO(
                    productId: $this->espresso->id,
                    quantity: 5,
                ),
            ],
            customerName: 'Aram Sarkisyan',
            tableNumber: 'Table 4',
        );

        $result = app(CreateOrderAction::class)->execute($this->vendor, $dto);
        $order = $result['order'];

        $this->assertNotNull($order);
        $this->assertEquals(1, $order->items->count());

        // 5 espressos * 0.018kg = 0.090kg coffee consumed. (1.0 - 0.09 = 0.910kg remaining)
        $beansBalance = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('ingredient_id', $this->coffeeBeans->id)
            ->first();

        $this->assertEquals(0.910, (float) $beansBalance->quantity_on_hand);
    }

    public function test_order_cancellation_via_http_reverses_stock_depletion(): void
    {
        // 1. Initial stock: 0.500 kg beans
        app(InventoryLedgerService::class)->recordReceipt($this->warehouse, [
            ['ingredient_id' => $this->coffeeBeans->id, 'quantity' => 0.500, 'unit_id' => $this->kg->id, 'unit_cost' => 12000.0],
        ]);

        // 2. Submit order for 10 double espressos (0.180kg beans)
        $dto = new CreateOrderDTO(
            locationId: $this->location->id,
            type: 'dine_in',
            items: [
                new OrderItemDTO(
                    productId: $this->espresso->id,
                    quantity: 10,
                ),
            ],
            customerName: 'Tigran',
            tableNumber: 'Bar 1',
        );

        $result = app(CreateOrderAction::class)->execute($this->vendor, $dto);
        $order = $result['order'];

        // Balance is 0.500 - 0.180 = 0.320 kg
        $beansBal1 = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('ingredient_id', $this->coffeeBeans->id)
            ->first();
        $this->assertEquals(0.320, (float) $beansBal1->quantity_on_hand);

        // 3. Admin cancels order
        $response = $this->actingAs($this->owner)->post(route('admin.orders.status', $order), [
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer changed mind before grinding',
        ]);

        $response->assertRedirect();
        $this->assertEquals('cancelled', $order->fresh()->status);

        // Stock restored back to 0.500 kg!
        $beansBal2 = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('ingredient_id', $this->coffeeBeans->id)
            ->first();
        $this->assertEquals(0.500, (float) $beansBal2->quantity_on_hand);
    }
}
