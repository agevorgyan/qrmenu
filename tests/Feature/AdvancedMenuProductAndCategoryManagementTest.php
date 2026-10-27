<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\MenuManagementService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedMenuProductAndCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Location $locationA;

    protected User $ownerA;

    protected Category $categoryA1;

    protected Category $categoryA2;

    protected Product $productA1;

    protected Product $productA2;

    protected Vendor $vendorB;

    protected Location $locationB;

    protected User $ownerB;

    protected Category $categoryB;

    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seed(SubscriptionPlanSeeder::class);
        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Main',
            'slug' => 'alpha-main',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->categoryA1 = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Appetizers',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->categoryA2 = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Main Courses',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->productA1 = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA1->id,
            'name' => 'Bruschetta Trio',
            'price' => 1500,
            'is_available' => true,
            'sort_order' => 1,
            'available_for_dine_in' => true,
            'available_for_takeaway' => true,
            'available_for_delivery' => true,
        ]);

        ProductVariation::create([
            'product_id' => $this->productA1->id,
            'name' => 'Standard',
            'price' => 1500,
            'is_default' => true,
        ]);

        $this->productA2 = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA1->id,
            'name' => 'Garlic Bread',
            'price' => 1000,
            'is_available' => true,
            'sort_order' => 2,
            'available_for_dine_in' => true,
            'available_for_takeaway' => true,
            'available_for_delivery' => true,
        ]);

        ProductVariation::create([
            'product_id' => $this->productA2->id,
            'name' => 'Standard',
            'price' => 1000,
            'is_default' => true,
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'USD',
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Downtown',
            'slug' => 'beta-downtown',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->categoryB = Category::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Salads',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'vendor_id' => $this->vendorB->id,
            'category_id' => $this->categoryB->id,
            'name' => 'Caesar Salad',
            'price' => 12,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_menu_index_renders_with_density_and_view_modes(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.menu.index'));

        $response->assertStatus(200);
        $response->assertSee('Menu Builder');
        $response->assertSee('Categorized');
        $response->assertSee('Compact Table');
        $response->assertSee('Category Organizer');
        $response->assertSee('Density:');
    }

    public function test_single_product_inline_update_price_and_syncs_default_variation(): void
    {
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.inline_update', $this->productA1), [
            'field' => 'price',
            'value' => 1800,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'product' => [
                'id' => $this->productA1->id,
                'price' => 1800,
            ],
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'price' => 1800,
        ]);

        // Default portion variation synchronized
        $this->assertDatabaseHas('product_variations', [
            'product_id' => $this->productA1->id,
            'is_default' => true,
            'price' => 1800,
        ]);
    }

    public function test_single_product_inline_update_name_and_availability(): void
    {
        $res1 = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.inline_update', $this->productA1), [
            'field' => 'name',
            'value' => 'Bruschetta Deluxe',
        ]);
        $res1->assertStatus(200);

        $res2 = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.inline_update', $this->productA1), [
            'field' => 'is_available',
            'value' => 0,
        ]);
        $res2->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'name' => 'Bruschetta Deluxe',
            'is_available' => false,
        ]);
    }

    public function test_bulk_update_products_fixed_amount_increase_and_rounding(): void
    {
        // Product 1: 1500, Product 2: 1000
        // Increase by 255 with nearest_50 rounding:
        // P1: 1500 + 255 = 1755 -> 1750
        // P2: 1000 + 255 = 1255 -> 1250
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.bulk_update'), [
            'product_ids' => [$this->productA1->id, $this->productA2->id],
            'price_action' => 'increase_amount',
            'price_value' => 255,
            'price_rounding' => 'nearest_50',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated_count' => 2,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'price' => 1750,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA2->id,
            'price' => 1250,
        ]);
    }

    public function test_bulk_update_products_percentage_increase(): void
    {
        // Increase 20% on 1500 and 1000:
        // P1: 1500 * 1.20 = 1800
        // P2: 1000 * 1.20 = 1200
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.bulk_update'), [
            'product_ids' => [$this->productA1->id, $this->productA2->id],
            'price_action' => 'increase_percent',
            'price_value' => 20,
            'price_rounding' => 'none',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'price' => 1800,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA2->id,
            'price' => 1200,
        ]);
    }

    public function test_bulk_update_products_prevents_negative_prices_by_clamping(): void
    {
        // Decrease by 5000 (exceeds existing 1500 and 1000)
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.bulk_update'), [
            'product_ids' => [$this->productA1->id, $this->productA2->id],
            'price_action' => 'decrease_amount',
            'price_value' => 5000,
        ]);

        $response->assertStatus(200);

        // Clamped to 0
        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'price' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA2->id,
            'price' => 0,
        ]);
    }

    public function test_bulk_update_products_category_and_channels(): void
    {
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.bulk_update'), [
            'product_ids' => [$this->productA1->id, $this->productA2->id],
            'category_id' => $this->categoryA2->id,
            'is_available' => false,
            'is_featured' => true,
            'available_for_delivery' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'category_id' => $this->categoryA2->id,
            'is_available' => false,
            'is_featured' => true,
            'available_for_delivery' => false,
        ]);
    }

    public function test_bulk_delete_products_removes_dishes(): void
    {
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.products.bulk_delete'), [
            'product_ids' => [$this->productA1->id, $this->productA2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'deleted_count' => 2,
        ]);

        $this->assertSoftDeleted('products', ['id' => $this->productA1->id]);
        $this->assertSoftDeleted('products', ['id' => $this->productA2->id]);
    }

    public function test_reorder_categories_updates_sort_order(): void
    {
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.categories.reorder'), [
            'order' => [
                ['id' => $this->categoryA2->id, 'sort_order' => 0],
                ['id' => $this->categoryA1->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('categories', [
            'id' => $this->categoryA2->id,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $this->categoryA1->id,
            'sort_order' => 1,
        ]);
    }

    public function test_duplicate_category_duplicates_category_and_dishes(): void
    {
        $allergen = Allergen::create([
            'code' => 'gluten',
            'name' => 'Gluten',
            'name_translations' => ['en' => 'Gluten'],
            'icon' => '🌾',
        ]);
        $this->productA1->allergens()->attach($allergen->id);

        $response = $this->actingAs($this->ownerA)->postJson(route('admin.menu.categories.duplicate', $this->categoryA1));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Duplicated category exists
        $newCat = Category::where('vendor_id', $this->vendorA->id)
            ->where('id', '!=', $this->categoryA1->id)
            ->where('id', '!=', $this->categoryA2->id)
            ->first();

        $this->assertNotNull($newCat);
        $this->assertStringContainsString('Appetizers', $newCat->name);

        // Dishes inside duplicated category
        $newProducts = Product::where('category_id', $newCat->id)->get();
        $this->assertCount(2, $newProducts);

        // Check variation copied
        $copiedProduct1 = $newProducts->firstWhere('price', 1500);
        $this->assertNotNull($copiedProduct1);
        $this->assertDatabaseHas('product_variations', [
            'product_id' => $copiedProduct1->id,
            'price' => 1500,
        ]);

        // Check allergen copied
        $this->assertCount(1, $copiedProduct1->allergens);
    }

    public function test_safe_category_deletion_with_dish_reassignment(): void
    {
        // Category A1 has 2 dishes. Reassign them to Category A2 upon deletion.
        $response = $this->actingAs($this->ownerA)->deleteJson(
            route('admin.menu.categories.destroy', $this->categoryA1),
            ['reassign_to_category_id' => $this->categoryA2->id]
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Category A1 soft deleted
        $this->assertSoftDeleted('categories', ['id' => $this->categoryA1->id]);

        // Dishes reassigned to Category A2
        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'category_id' => $this->categoryA2->id,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $this->productA2->id,
            'category_id' => $this->categoryA2->id,
        ]);
    }

    public function test_safe_category_deletion_with_force_delete(): void
    {
        // Force delete category and its dishes
        $response = $this->actingAs($this->ownerA)->deleteJson(
            route('admin.menu.categories.destroy', $this->categoryA1),
            ['force_delete' => true]
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertSoftDeleted('categories', ['id' => $this->categoryA1->id]);
        $this->assertSoftDeleted('products', ['id' => $this->productA1->id]);
        $this->assertSoftDeleted('products', ['id' => $this->productA2->id]);
    }

    public function test_safe_category_deletion_fails_if_dishes_exist_without_reassign_or_force(): void
    {
        $this->expectException(ValidationException::class);

        // Direct service invocation with dishes present
        app(MenuManagementService::class)->deleteCategorySafely($this->categoryA1, null, false);
    }

    public function test_multi_tenant_isolation_prevents_unauthorized_cross_tenant_operations(): void
    {
        // Owner B attempts to inline update Owner A's product (global tenant scope returns 404)
        $res1 = $this->actingAs($this->ownerB)->postJson(route('admin.menu.products.inline_update', $this->productA1), [
            'field' => 'price',
            'value' => 9999,
        ]);
        $res1->assertStatus(404);

        // Owner B attempts to bulk update Owner A's products
        $res2 = $this->actingAs($this->ownerB)->postJson(route('admin.menu.products.bulk_update'), [
            'product_ids' => [$this->productA1->id],
            'price_action' => 'set',
            'price_value' => 9999,
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['updated_count' => 0]); // Tenant isolation rejected product A1
        $this->assertDatabaseHas('products', [
            'id' => $this->productA1->id,
            'price' => 1500, // Price unchanged
        ]);

        // Owner B attempts to duplicate Owner A's category (global tenant scope returns 404)
        $res3 = $this->actingAs($this->ownerB)->postJson(route('admin.menu.categories.duplicate', $this->categoryA1));
        $res3->assertStatus(404);

        // Owner B attempts to delete Owner A's category (global tenant scope returns 404)
        $res4 = $this->actingAs($this->ownerB)->deleteJson(route('admin.menu.categories.destroy', $this->categoryA1));
        $res4->assertStatus(404);
    }
}
