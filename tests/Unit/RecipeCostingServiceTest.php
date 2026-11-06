<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\RecipeCostingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class RecipeCostingServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected RecipeCostingService $costingService;

    protected Vendor $vendor;

    protected User $user;

    protected Unit $kg;

    protected Unit $g;

    protected Unit $portion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->costingService = app(RecipeCostingService::class);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->g = Unit::whereNull('vendor_id')->where('code', 'g')->firstOrFail();
        $this->portion = Unit::whereNull('vendor_id')->where('code', 'portion')->firstOrFail();

        $this->vendor = Vendor::create([
            'name' => 'Gourmet Bistro',
            'slug' => 'gourmet-bistro-'.uniqid(),
            'email' => 'gourmet-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->user = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Chef Pierre',
            'email' => 'chef-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);
    }

    public function test_shrinkage_loss_and_weight_calculations(): void
    {
        // Ingredient: Beef Tenderloin, unit = kg, cost = 20.00/kg
        $beef = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Beef Tenderloin',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 20.00,
            'last_purchase_price' => 22.00,
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Seared Steak',
            'code' => 'STEAK-001',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'selling_price' => 15.00,
        ]);

        // Gross = 300g, Cold Prep Loss = 10% (sinew/fat trim), Cook Loss = 20% (moisture evaporation)
        $item = RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $recipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $beef->id,
            'quantity' => 300.0,
            'unit_id' => $this->g->id,
            'gross_weight' => 300.0,
            'prep_loss_percent' => 10.0,
            'cooking_loss_percent' => 20.0,
        ]);

        $cost = $this->costingService->calculateItemCost($item);

        // Expected Net: 300 * (1 - 0.10) = 270g
        $this->assertEquals(270.0, $cost['net_weight']);
        // Expected Final: 270 * (1 - 0.20) = 216g
        $this->assertEquals(216.0, $cost['final_weight']);

        // Cost is charged on GROSS weight: 300g = 0.3kg * $20.00/kg = $6.00
        $this->assertEquals(6.00, $cost['total_cost']);
    }

    public function test_recipe_cost_and_food_cost_percentage_calculation(): void
    {
        $potato = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Potatoes',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 2.00,
            'is_active' => true,
        ]);

        $butter = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Butter',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 10.00,
            'is_active' => true,
        ]);

        // Recipe: Mashed Potatoes (Yield: 4 portions, Selling price: $5.00/portion)
        $recipe = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mashed Potatoes',
            'code' => 'MASH-001',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 4.0,
            'yield_unit_id' => $this->portion->id,
            'selling_price' => 5.00,
        ]);

        // 1 kg Potato ($2.00) + 200g Butter (0.2 * 10 = $2.00) = $4.00 total
        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $recipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $potato->id,
            'quantity' => 1.0,
            'unit_id' => $this->kg->id,
            'gross_weight' => 1.0,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $recipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $butter->id,
            'quantity' => 200.0,
            'unit_id' => $this->g->id,
            'gross_weight' => 200.0,
        ]);

        $costRecord = $this->costingService->calculateAndPersist($recipe);

        // Total cost: 2.00 + 2.00 = 4.00
        $this->assertEquals(4.00, (float) $costRecord->total_cost);
        // Portion cost: 4.00 / 4 portions = 1.00
        $this->assertEquals(1.00, (float) $costRecord->portion_cost);
        // Food cost %: (1.00 / 5.00) * 100 = 20.00%
        $this->assertEquals(20.00, (float) $costRecord->food_cost_percent);
        // Gross profit: 5.00 - 1.00 = 4.00
        $this->assertEquals(4.00, (float) $costRecord->gross_profit);
        // Profit margin: (4.00 / 5.00) * 100 = 80.00%
        $this->assertEquals(80.00, (float) $costRecord->profit_margin_percent);
    }

    public function test_nested_sub_recipe_bill_of_materials_costing(): void
    {
        $tomato = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'San Marzano Tomatoes',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 5.00,
            'is_active' => true,
        ]);

        // Sub-recipe: Marinara Sauce (Yield: 1 kg)
        // Ingredients: 2 kg Tomatoes ($10.00 total) -> cost per kg = $10.00/kg
        $marinara = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Marinara Sauce',
            'code' => 'SAUCE-001',
            'type' => Recipe::TYPE_SAUCE,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->kg->id,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $marinara->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $tomato->id,
            'quantity' => 2.0,
            'unit_id' => $this->kg->id,
            'gross_weight' => 2.0,
        ]);

        // Main Recipe: Pasta Marinara (Yield: 1 portion)
        // Uses 200g of Marinara Sauce (0.2 kg * $10/kg = $2.00)
        $pasta = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Pasta Marinara',
            'code' => 'PASTA-001',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'selling_price' => 10.00,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $pasta->id,
            'type' => RecipeItem::TYPE_SUB_RECIPE,
            'sub_recipe_id' => $marinara->id,
            'quantity' => 200.0,
            'unit_id' => $this->g->id,
            'gross_weight' => 200.0,
        ]);

        $pastaCost = $this->costingService->calculateAndPersist($pasta);

        $this->assertEquals(2.00, (float) $pastaCost->total_cost);
        $this->assertEquals(2.00, (float) $pastaCost->portion_cost);
        $this->assertEquals(20.00, (float) $pastaCost->food_cost_percent);
    }

    public function test_circular_sub_recipe_dependency_is_blocked_by_dag(): void
    {
        // Recipe A
        $recipeA = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Recipe A',
            'code' => 'REC-A',
            'type' => Recipe::TYPE_SEMI_FINISHED,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
        ]);

        // Recipe B
        $recipeB = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Recipe B',
            'code' => 'REC-B',
            'type' => Recipe::TYPE_SEMI_FINISHED,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
        ]);

        // Recipe A includes Recipe B
        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $recipeA->id,
            'type' => RecipeItem::TYPE_SUB_RECIPE,
            'sub_recipe_id' => $recipeB->id,
            'quantity' => 1.0,
            'unit_id' => $this->portion->id,
            'gross_weight' => 1.0,
        ]);

        // Now attempt to make Recipe B include Recipe A (Cycle: A -> B -> A)
        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $recipeB->id,
            'type' => RecipeItem::TYPE_SUB_RECIPE,
            'sub_recipe_id' => $recipeA->id,
            'quantity' => 1.0,
            'unit_id' => $this->portion->id,
            'gross_weight' => 1.0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->costingService->validateNoCycles($recipeA);
    }

    public function test_recipe_version_snapshot_captures_bom_and_costs(): void
    {
        $cheese = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mozzarella',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 12.00,
            'is_active' => true,
        ]);

        $pizza = Recipe::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Margherita Pizza',
            'code' => 'PIZZA-001',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'selling_price' => 14.00,
            'instructions' => 'Bake at 450C in wood-fired oven for 90 seconds.',
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendor->id,
            'recipe_id' => $pizza->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $cheese->id,
            'quantity' => 250.0,
            'unit_id' => $this->g->id,
            'gross_weight' => 250.0,
        ]);

        $version = $this->costingService->createVersion($pizza, 'Standardized mozzarella portion to 250g', $this->user);

        $this->assertEquals(1, $version->version_number);
        $this->assertEquals(3.00, (float) $version->portion_cost); // 0.25kg * 12 = 3.00
        $this->assertNotNull($version->snapshot_data);
        $this->assertEquals('Mozzarella', $version->snapshot_data['items'][0]['ingredient_name']);
        $this->assertEquals(1, $pizza->fresh()->current_version);
    }
}
