<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected ProductVariation $variation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendor = Vendor::create([
            'name' => 'Cart Bistro',
            'slug' => 'cart-bistro',
            'email' => 'cart@test.com',
            'password' => bcrypt('password'),
            'theme' => 'modern-bistro',
            'subscription_plan_id' => $plan?->id,
            'subscription_plan' => 'business',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Central Hub',
            'slug' => 'central-hub',
            'table_count' => 8,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Dishes',
            'name_translations' => [
                'hy' => 'Հիմնական ուտեստներ',
                'ru' => 'Главные блюда',
                'en' => 'Main Dishes',
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Beef Kebab',
            'name_translations' => [
                'hy' => 'Տավարի քյաբաբ',
                'ru' => 'Кебаб из говядины',
                'en' => 'Beef Kebab',
            ],
            'price' => 3000,
            'is_available' => true,
        ]);

        $this->variation = ProductVariation::create([
            'product_id' => $this->product->id,
            'name' => 'Large Portion',
            'name_translations' => [
                'hy' => 'Մեծ բաժին',
                'ru' => 'Большая порция',
                'en' => 'Large Portion',
            ],
            'price' => 4500,
            'is_default' => false,
        ]);
    }

    public function test_storefront_bootstrap_includes_translated_catalog_products_and_variations(): void
    {
        // Armenian
        $responseHy = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'hy']));
        $responseHy->assertStatus(200);
        $responseHy->assertSee('Տավարի քյաբաբ');
        $responseHy->assertSee('Մեծ բաժին');

        // Russian
        $responseRu = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));
        $responseRu->assertStatus(200);
        $responseRu->assertSee('Кебаб из говядины');
        $responseRu->assertSee('Большая порция');

        // English
        $responseEn = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'en']));
        $responseEn->assertStatus(200);
        $responseEn->assertSee('Beef Kebab');
        $responseEn->assertSee('Large Portion');
    }

    public function test_all_storefront_themes_include_change_language_handler(): void
    {
        $themes = ['modern-bistro', 'luxury-dark', 'vibrant-glass', 'minimalist-light'];

        foreach ($themes as $theme) {
            $this->vendor->update(['theme' => $theme]);

            $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
            $response->assertStatus(200);
            $response->assertSee('changeLanguage($event.target.value)');
        }
    }

    public function test_storefront_js_contains_cart_persistence_methods(): void
    {
        $resourceJs = file_get_contents(resource_path('js/storefront.js'));
        $publicJs = file_get_contents(public_path('js/storefront.js'));

        $this->assertStringContainsString('saveCartToStorage', $resourceJs);
        $this->assertStringContainsString('restoreCartFromStorage', $resourceJs);
        $this->assertStringContainsString('clearStoredCart', $resourceJs);
        $this->assertStringContainsString('changeLanguage', $resourceJs);

        $this->assertStringContainsString('saveCartToStorage', $publicJs);
        $this->assertStringContainsString('restoreCartFromStorage', $publicJs);
        $this->assertStringContainsString('clearStoredCart', $publicJs);
        $this->assertStringContainsString('changeLanguage', $publicJs);

        $this->assertSame($resourceJs, $publicJs, 'public/js/storefront.js must remain identical to resources/js/storefront.js');
    }
}
