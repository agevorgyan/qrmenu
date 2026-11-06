<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Unit $kg;

    protected Unit $portion;

    protected Ingredient $beef;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->portion = Unit::whereNull('vendor_id')->where('code', 'portion')->firstOrFail();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Royal Grill',
            'slug' => 'royal-grill-'.uniqid(),
            'email' => 'royal-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->ownerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Grill Master',
            'email' => 'owner-royal-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->beef = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Ribeye Beef',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 25.00,
            'is_active' => true,
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Green Garden',
            'slug' => 'green-garden-'.uniqid(),
            'email' => 'green-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->ownerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Garden Chef',
            'email' => 'owner-garden-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);
    }

    public function test_can_view_recipes_index_scoped_to_vendor(): void
    {
        $recipeA = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Ribeye Steak',
            'code' => 'STEAK-A',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
        ]);

        $recipeB = Recipe::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Garden Salad',
            'code' => 'SALAD-B',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.recipes.index'));

        $response->assertOk();
        $response->assertSee('Ribeye Steak');
        $response->assertDontSee('Garden Salad');
    }

    public function test_can_create_recipe_with_items_and_recalculate(): void
    {
        $payload = [
            'name' => 'Signature Ribeye',
            'code' => 'REC-RIB-01',
            'type' => 'dish',
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'portion_size' => 350.0,
            'portion_unit_id' => $this->portion->id,
            'selling_price' => 30.00,
            'target_food_cost_percent' => 30.0,
            'instructions' => 'Sear on charcoal grill for 4 min each side.',
            'items' => [
                [
                    'type' => 'ingredient',
                    'ingredient_id' => $this->beef->id,
                    'quantity' => 0.4,
                    'unit_id' => $this->kg->id,
                    'gross_weight' => 0.4,
                    'prep_loss_percent' => 5.0,
                    'cooking_loss_percent' => 15.0,
                    'notes' => 'Trim outer fat',
                ],
            ],
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.recipes.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('recipes', [
            'vendor_id' => $this->vendorA->id,
            'code' => 'REC-RIB-01',
            'name' => 'Signature Ribeye',
        ]);

        $recipe = Recipe::where('vendor_id', $this->vendorA->id)->where('code', 'REC-RIB-01')->firstOrFail();
        $this->assertCount(1, $recipe->items);

        // 0.4kg * $25.00/kg = $10.00 cost
        $this->assertNotNull($recipe->latestCost);
        $this->assertEquals(10.00, (float) $recipe->latestCost->portion_cost);
        // Food cost: 10 / 30 = 33.33%
        $this->assertEquals(33.33, (float) $recipe->latestCost->food_cost_percent);
    }

    public function test_can_approve_recipe_and_generate_version_snapshot(): void
    {
        $recipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'T-Bone Special',
            'code' => 'TBONE-001',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
            'status' => Recipe::STATUS_DRAFT,
            'selling_price' => 35.00,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendorA->id,
            'recipe_id' => $recipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $this->beef->id,
            'quantity' => 0.5,
            'unit_id' => $this->kg->id,
            'gross_weight' => 0.5,
        ]);

        $response = $this->actingAs($this->ownerA)->post(route('admin.inventory.recipes.approve', $recipe), [
            'notes' => 'Head chef approved for summer menu',
        ]);

        $response->assertRedirect();
        $recipe->refresh();

        $this->assertEquals(Recipe::STATUS_APPROVED, $recipe->status);
        $this->assertEquals($this->ownerA->id, $recipe->approved_by);
        $this->assertNotNull($recipe->approved_at);
        $this->assertCount(1, $recipe->versions);

        $version = $recipe->versions->first();
        $this->assertEquals(1, $version->version_number);
        $this->assertEquals('Head chef approved for summer menu', $version->change_summary);
    }

    public function test_cross_vendor_recipe_access_is_forbidden(): void
    {
        $recipeA = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Secret Beef Recipe',
            'code' => 'SECRET-A',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
        ]);

        // Vendor B attempts to update Vendor A's recipe
        $response = $this->actingAs($this->ownerB)->put(route('admin.inventory.recipes.update', $recipeA), [
            'name' => 'Hacked Recipe',
            'code' => 'SECRET-A',
            'type' => 'dish',
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $this->assertEquals('Secret Beef Recipe', $recipeA->fresh()->name);
    }

    public function test_cannot_delete_recipe_used_as_sub_recipe(): void
    {
        $subRecipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Demi-Glace Base',
            'code' => 'DEMI-01',
            'type' => Recipe::TYPE_SAUCE,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->kg->id,
        ]);

        $mainRecipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Filet Mignon with Demi-Glace',
            'code' => 'FILET-01',
            'type' => Recipe::TYPE_DISH,
            'yield_quantity' => 1.0,
            'yield_unit_id' => $this->portion->id,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendorA->id,
            'recipe_id' => $mainRecipe->id,
            'type' => RecipeItem::TYPE_SUB_RECIPE,
            'sub_recipe_id' => $subRecipe->id,
            'quantity' => 0.1,
            'unit_id' => $this->kg->id,
            'gross_weight' => 0.1,
        ]);

        $response = $this->actingAs($this->ownerA)->delete(route('admin.inventory.recipes.destroy', $subRecipe));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('recipes', ['id' => $subRecipe->id, 'deleted_at' => null]);
    }
}
