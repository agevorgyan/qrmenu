<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Models\WholesaleAtgaaClassification;
use App\Domains\Wholesale\Models\WholesaleCategory;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleCategoryAndRecipeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

    protected Unit $unitKg;

    protected Unit $unitPiece;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::first();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Superadmin
        $this->superadmin = User::create([
            'name' => 'Master Super Admin',
            'email' => 'super@admin.am',
            'password' => bcrypt('password123'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        // Entitle Vendor A
        $accessService = app(WholesaleAccessService::class);
        $accessService->grantAccess($this->vendorA, $this->superadmin, null, 'Test Entitlement');

        // Units
        $this->unitKg = Unit::create([
            'vendor_id' => null,
            'name' => 'Kilogram',
            'code' => 'kg',
            'symbol' => 'kg',
            'type' => 'mass',
            'is_active' => true,
        ]);

        $this->unitPiece = Unit::create([
            'vendor_id' => null,
            'name' => 'Piece',
            'code' => 'piece',
            'symbol' => 'pc',
            'type' => 'unit',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Main Production Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        // ATGAA classification
        WholesaleAtgaaClassification::firstOrCreate(
            ['code' => '1902'],
            ['name_hy' => 'Մակարոնեղեն և խմորեղեն', 'name_en' => 'Pasta & Pastry', 'is_active' => true]
        );
    }

    public function test_tenant_can_list_and_create_wholesale_categories(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.wholesale.categories.index'));
        $response->assertStatus(200);
        $response->assertViewIs('wholesale.categories.index');

        $payload = [
            'name' => 'Կիսաֆաբրիկատներ',
            'description' => 'Սառեցված կիսաֆաբրիկատների բաժին',
            'sort_order' => 10,
            'is_active' => '1',
        ];

        $postResponse = $this->actingAs($this->ownerA)->post(route('admin.wholesale.categories.store'), $payload);
        $postResponse->assertRedirect(route('admin.wholesale.categories.index'));

        $this->assertDatabaseHas('wholesale_categories', [
            'vendor_id' => $this->vendorA->id,
            'name' => 'Կիսաֆաբրիկատներ',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    public function test_tenant_can_create_nested_subcategory(): void
    {
        $parent = WholesaleCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Մսամթերք',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Պելմենիներ և Խինկալի',
            'parent_id' => $parent->id,
            'sort_order' => 1,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.categories.store'), $payload);
        $response->assertRedirect(route('admin.wholesale.categories.index'));

        $this->assertDatabaseHas('wholesale_categories', [
            'vendor_id' => $this->vendorA->id,
            'name' => 'Պելմենիներ և Խինկալի',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_tenant_can_update_wholesale_category(): void
    {
        $category = WholesaleCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Հին Անվանում',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Թարմացված Անվանում',
            'sort_order' => 20,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->ownerA)->put(route('admin.wholesale.categories.update', $category), $payload);
        $response->assertRedirect(route('admin.wholesale.categories.index'));

        $category->refresh();
        $this->assertEquals('Թարմացված Անվանում', $category->name);
        $this->assertEquals(20, $category->sort_order);
    }

    public function test_cannot_delete_category_when_products_are_assigned(): void
    {
        $category = WholesaleCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Պելմենի Կատեգորիա',
            'is_active' => true,
        ]);

        WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $category->id,
            'name' => 'Պելմենի Ֆիրմային',
            'sku' => 'PELM-TEST-001',
            'classifier_code' => '1902',
            'unit_id' => $this->unitPiece->id,
            'base_wholesale_price' => 1600.00,
            'min_wholesale_price' => 1400.00,
            'is_vat_applicable' => true,
            'vat_rate' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerA)->delete(route('admin.wholesale.categories.destroy', $category));

        $response->assertRedirect(route('admin.wholesale.categories.index'));
        $response->assertSessionHasErrors('category');

        $this->assertDatabaseHas('wholesale_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_can_delete_empty_wholesale_category(): void
    {
        $category = WholesaleCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Դատարկ Կատեգորիա',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerA)->delete(route('admin.wholesale.categories.destroy', $category));

        $response->assertRedirect(route('admin.wholesale.categories.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('wholesale_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_wholesale_product_can_be_assigned_category_and_recipe(): void
    {
        $category = WholesaleCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Արտադրական Ապրանքներ',
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Տեխ․ Քարտ - Պելմենի Ֆիրմային',
            'code' => 'RCP-PELM-01',
            'type' => Recipe::TYPE_SEMI_FINISHED,
            'status' => Recipe::STATUS_ACTIVE,
            'yield_quantity' => 10,
            'yield_unit_id' => $this->unitKg->id,
        ]);

        $payload = [
            'name' => 'Պելմենի Ֆիրմային 450գ',
            'sku' => 'PELM-FIRM-450',
            'classifier_code' => '1902',
            'category_id' => $category->id,
            'recipe_id' => $recipe->id,
            'unit_id' => $this->unitPiece->id,
            'default_warehouse_id' => $this->warehouse->id,
            'base_wholesale_price' => 1600.00,
            'min_wholesale_price' => 1400.00,
            'is_vat_applicable' => 1,
            'vat_rate' => 20.00,
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), $payload);
        $response->assertRedirect();

        $product = WholesaleProduct::where('sku', 'PELM-FIRM-450')->first();
        $this->assertNotNull($product);
        $this->assertEquals($category->id, $product->category_id);
        $this->assertEquals($recipe->id, $product->recipe_id);
        $this->assertEquals($this->warehouse->id, $product->default_warehouse_id);

        // Verify relationships
        $this->assertEquals($recipe->id, $product->recipe->id);
        $this->assertTrue($recipe->wholesaleProducts->contains('id', $product->id));
    }

    public function test_wholesale_product_show_view_renders_technological_card_details_and_production_link(): void
    {
        $recipe = Recipe::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Տեխնոլոգիական Քարտ #102',
            'code' => 'BOM-102',
            'type' => Recipe::TYPE_SEMI_FINISHED,
            'status' => Recipe::STATUS_ACTIVE,
            'yield_quantity' => 50,
            'yield_unit_id' => $this->unitPiece->id,
        ]);

        $flour = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Ալյուր Բ/Տ',
            'unit_id' => $this->unitKg->id,
            'cost_per_unit' => 300,
            'is_active' => true,
        ]);

        RecipeItem::create([
            'vendor_id' => $this->vendorA->id,
            'recipe_id' => $recipe->id,
            'type' => RecipeItem::TYPE_INGREDIENT,
            'ingredient_id' => $flour->id,
            'quantity' => 10,
            'unit_id' => $this->unitKg->id,
            'gross_weight' => 10,
            'net_weight' => 10,
            'unit_cost' => 300,
            'total_cost' => 3000,
        ]);

        $product = WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Պելմենի Տեխ․ Քարտով',
            'sku' => 'PELM-BOM-001',
            'classifier_code' => '1902',
            'recipe_id' => $recipe->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->unitPiece->id,
            'base_wholesale_price' => 2000.00,
            'min_wholesale_price' => 1800.00,
            'is_vat_applicable' => true,
            'vat_rate' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.wholesale.products.show', $product));

        $response->assertStatus(200);
        $response->assertSee(__('Տեխնոլոգիական Քարտ (Recipe / BOM) և Արտադրություն'));
        $response->assertSee('Տեխնոլոգիական Քարտ #102');
        $response->assertSee('BOM-102');
        $response->assertSee('Ալյուր Բ/Տ');
        $response->assertSee(route('admin.inventory.production.create', [
            'recipe_id' => $recipe->id,
            'destination_warehouse_id' => $this->warehouse->id,
        ]));
    }

    public function test_tenant_isolation_prevents_assigning_cross_tenant_category_or_recipe(): void
    {
        // Category and recipe belonging to Vendor B
        $otherCategory = WholesaleCategory::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Other Tenant Category',
            'is_active' => true,
        ]);

        $otherRecipe = Recipe::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Other Tenant Recipe',
            'code' => 'RCP-OTHER-01',
            'type' => Recipe::TYPE_DISH,
            'status' => Recipe::STATUS_ACTIVE,
            'yield_quantity' => 1,
            'yield_unit_id' => $this->unitPiece->id,
        ]);

        $payload = [
            'name' => 'Malicious Product',
            'sku' => 'MALICIOUS-001',
            'classifier_code' => '1902',
            'category_id' => $otherCategory->id,
            'recipe_id' => $otherRecipe->id,
            'unit_id' => $this->unitPiece->id,
            'base_wholesale_price' => 1000.00,
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), $payload);
        $response->assertSessionHasErrors(['category_id', 'recipe_id']);
    }
}
