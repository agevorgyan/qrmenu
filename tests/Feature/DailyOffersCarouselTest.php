<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyOffer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DailyOffersCarouselTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Category $category;

    protected Product $product1;

    protected Product $product2;

    protected Product $product3;

    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Offers Carousel Bistro',
            'slug' => 'offers-carousel-bistro',
            'email' => 'carousel@bistro.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'featured_dish_enabled' => true,
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'carousel@bistro.am',
            'password' => bcrypt('password'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown-branch',
            'address' => 'Northern Ave 10',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Chef Specials',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product1 = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Dry Aged Tomahawk',
            'description' => 'Served with truffle garlic butter',
            'price' => 18000,
            'is_available' => true,
        ]);

        $this->product2 = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Black Truffle Risotto',
            'description' => 'Creamy Arborio rice with wild mushrooms',
            'price' => 7500,
            'is_available' => true,
        ]);

        $this->product3 = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Atlantic Salmon Fillet',
            'description' => 'Pan-seared with lemon dill velouté',
            'price' => 9200,
            'is_available' => true,
        ]);
    }

    public function test_vendor_can_save_multiple_products_as_daily_offers(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.settings.update'), [
            'location_id' => $this->location->id,
            'name' => 'Offers Carousel Bistro',
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
            'featured_dish_enabled' => '1',
            'daily_offers' => [
                [
                    'product_id' => $this->product1->id,
                    'sort_order' => 0,
                    'badge' => '⭐ SPECIAL #1',
                    'subtitle' => 'Chef recommendation for tomahawk',
                    'discount_price' => 16000,
                ],
                [
                    'product_id' => $this->product2->id,
                    'sort_order' => 1,
                    'badge' => '🔥 BESTSELLER',
                    'subtitle' => 'Fresh truffles imported daily',
                    'discount_price' => 6800,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('daily_offers', [
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product1->id,
            'badge' => '⭐ SPECIAL #1',
            'discount_price' => 16000,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('daily_offers', [
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product2->id,
            'badge' => '🔥 BESTSELLER',
            'discount_price' => 6800,
            'sort_order' => 1,
        ]);

        $this->vendor->refresh();
        $this->assertSame($this->product1->id, $this->vendor->featured_product_id);
    }

    public function test_vendor_can_reorder_daily_offers_and_order_is_persisted(): void
    {
        // Setup initial offers
        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product1->id,
            'sort_order' => 0,
            'badge' => 'First',
        ]);
        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product2->id,
            'sort_order' => 1,
            'badge' => 'Second',
        ]);

        // Reorder via AJAX endpoint
        $response = $this->actingAs($this->user)->postJson(route('admin.settings.daily_offers.update'), [
            'featured_dish_enabled' => true,
            'daily_offers' => [
                [
                    'product_id' => $this->product2->id,
                    'sort_order' => 0,
                    'badge' => 'Now First',
                ],
                [
                    'product_id' => $this->product1->id,
                    'sort_order' => 1,
                    'badge' => 'Now Second',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $offer2 = DailyOffer::where('vendor_id', $this->vendor->id)->where('product_id', $this->product2->id)->first();
        $offer1 = DailyOffer::where('vendor_id', $this->vendor->id)->where('product_id', $this->product1->id)->first();

        $this->assertSame(0, $offer2->sort_order);
        $this->assertSame(1, $offer1->sort_order);
    }

    public function test_vendor_can_remove_product_from_daily_offers_without_deleting_from_catalog(): void
    {
        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product1->id,
            'sort_order' => 0,
        ]);
        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product2->id,
            'sort_order' => 1,
        ]);

        // Save with only product2 (product1 removed from offers)
        $this->actingAs($this->user)->postJson(route('admin.settings.daily_offers.update'), [
            'featured_dish_enabled' => true,
            'daily_offers' => [
                [
                    'product_id' => $this->product2->id,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $this->assertDatabaseMissing('daily_offers', [
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product1->id,
        ]);

        $this->assertDatabaseHas('daily_offers', [
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product2->id,
        ]);

        // Verify product1 is still in products catalog
        $this->assertDatabaseHas('products', [
            'id' => $this->product1->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_add_products_belonging_to_another_vendor(): void
    {
        $otherVendor = Vendor::create([
            'name' => 'Foreign Vendor',
            'slug' => 'foreign-vendor',
            'email' => 'foreign@vendor.am',
            'password' => bcrypt('password'),
            'is_active' => true,
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);

        $otherCat = Category::create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Foreign Cat',
        ]);

        $otherProduct = Product::create([
            'vendor_id' => $otherVendor->id,
            'category_id' => $otherCat->id,
            'name' => 'Steal This Dish',
            'price' => 5000,
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.settings.daily_offers.update'), [
            'featured_dish_enabled' => true,
            'daily_offers' => [
                [
                    'product_id' => $otherProduct->id,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('daily_offers', [
            'vendor_id' => $this->vendor->id,
            'product_id' => $otherProduct->id,
        ]);
    }

    public function test_cannot_add_duplicate_products_in_daily_offers(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('admin.settings.daily_offers.update'), [
            'featured_dish_enabled' => true,
            'daily_offers' => [
                ['product_id' => $this->product1->id, 'sort_order' => 0],
                ['product_id' => $this->product1->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_storefront_menu_displays_carousel_with_all_active_offers_in_order(): void
    {
        $this->vendor->update(['featured_dish_enabled' => true]);

        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product1->id,
            'sort_order' => 0,
            'badge' => '⭐ SPECIAL #1',
            'subtitle' => 'Juicy tomahawk discount',
            'discount_price' => 16500,
            'is_active' => true,
        ]);

        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product2->id,
            'sort_order' => 1,
            'badge' => '🔥 BESTSELLER #2',
            'subtitle' => 'Silky risotto',
            'discount_price' => null,
            'is_active' => true,
        ]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('featured-dish-banner-container');
        $response->assertSee('daily-offers-track');
        $response->assertSee('Dry Aged Tomahawk');
        $response->assertSee('Black Truffle Risotto');
        $response->assertSee('⭐ SPECIAL #1');
        $response->assertSee('🔥 BESTSELLER #2');
        $response->assertSee('16,500');
    }

    public function test_storefront_does_not_display_unavailable_or_trashed_products_in_carousel(): void
    {
        $this->vendor->update(['featured_dish_enabled' => true]);

        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product1->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // Product 2 is unavailable
        $this->product2->update(['is_available' => false]);
        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product2->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('Dry Aged Tomahawk');
        $response->assertDontSee('Black Truffle Risotto');
    }

    public function test_storefront_does_not_render_carousel_when_empty_or_disabled(): void
    {
        // 1. When disabled
        $this->vendor->update(['featured_dish_enabled' => false]);
        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product1->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertDontSee('featured-dish-banner-container');

        // 2. When enabled but 0 offers
        $this->vendor->update(['featured_dish_enabled' => true, 'featured_product_id' => null]);
        DailyOffer::truncate();

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertDontSee('featured-dish-banner-container');
    }

    public function test_daily_offer_model_level_cross_tenant_prevention(): void
    {
        $otherVendor = Vendor::create([
            'name' => 'Isolation Vendor',
            'slug' => 'isolation-vendor',
            'email' => 'iso@vendor.am',
            'password' => bcrypt('password'),
            'is_active' => true,
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);

        $otherCat = Category::create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Iso Cat',
        ]);

        $otherProduct = Product::create([
            'vendor_id' => $otherVendor->id,
            'category_id' => $otherCat->id,
            'name' => 'Alien Dish',
            'price' => 1000,
            'is_available' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);

        DailyOffer::create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $otherProduct->id,
            'sort_order' => 0,
        ]);
    }
}
