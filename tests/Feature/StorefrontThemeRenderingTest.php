<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontThemeRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Gourmet House',
            'slug' => 'gourmet-house',
            'email' => 'gourmet@house.am',
            'password' => bcrypt('password'),
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall',
            'slug' => 'main-hall',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Dishes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Duck Breast',
            'price' => 6500,
            'is_available' => true,
        ]);

        ProductVariation::create([
            'product_id' => $this->product->id,
            'name' => 'Standard',
            'price' => 6500,
            'is_default' => true,
        ]);
    }

    public function test_modern_bistro_theme_renders_and_includes_storefront_scripts(): void
    {
        $tmpl = MenuTemplate::create([
            'name' => 'Modern Bistro',
            'slug' => 'modern-bistro',
            'is_active' => true,
        ]);
        $this->vendor->update(['menu_template_id' => $tmpl->id]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('createStorefrontApp');
        $response->assertSee('modernBistroApp');
        $response->assertSee('Duck Breast');
        $response->assertSee('addSelectedVariationToCart');
        $response->assertSee('submitOrder');
    }

    public function test_luxury_dark_theme_renders_and_includes_storefront_scripts(): void
    {
        $tmpl = MenuTemplate::create([
            'name' => 'Luxury Dark',
            'slug' => 'luxury-dark',
            'is_active' => true,
        ]);
        $this->vendor->update(['menu_template_id' => $tmpl->id]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('createStorefrontApp');
        $response->assertSee('storefrontApp');
        $response->assertSee('Duck Breast');
        $response->assertSee('addSelectedVariationToCart');
        $response->assertSee('submitOrder');
    }

    public function test_vibrant_glass_theme_renders_and_includes_storefront_scripts(): void
    {
        $tmpl = MenuTemplate::create([
            'name' => 'Vibrant Glass',
            'slug' => 'vibrant-glass',
            'is_active' => true,
        ]);
        $this->vendor->update(['menu_template_id' => $tmpl->id]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('createStorefrontApp');
        $response->assertSee('vibrantGlassApp');
        $response->assertSee('Duck Breast');
        $response->assertSee('addSelectedVariationToCart');
        $response->assertSee('submitOrder');
    }

    public function test_custom_css_renders_in_head_for_all_themes(): void
    {
        $themes = ['modern-bistro', 'luxury-dark', 'vibrant-glass'];
        $customCss = '.custom-badge { background-color: purple; }';
        $this->vendor->update(['custom_css' => $customCss]);

        foreach ($themes as $themeSlug) {
            $tmpl = MenuTemplate::firstOrCreate(['slug' => $themeSlug], [
                'name' => ucfirst(str_replace('-', ' ', $themeSlug)),
                'is_active' => true,
            ]);
            $this->vendor->update(['menu_template_id' => $tmpl->id]);

            $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
            $response->assertStatus(200);
            $response->assertSee('<style>'.strip_tags($customCss).'</style>', false);
        }
    }

    public function test_product_without_image_renders_default_dish_placeholder_in_storefront(): void
    {
        $this->assertNull($this->product->getRawOriginal('image'));
        $this->assertEquals('/images/default-dish.png', $this->product->image);
        $this->assertFalse($this->product->hasCustomImage());

        $themes = ['modern-bistro', 'luxury-dark', 'vibrant-glass'];
        foreach ($themes as $themeSlug) {
            $tmpl = MenuTemplate::firstOrCreate(['slug' => $themeSlug], [
                'name' => ucfirst(str_replace('-', ' ', $themeSlug)),
                'is_active' => true,
            ]);
            $this->vendor->update(['menu_template_id' => $tmpl->id]);

            $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
            $response->assertStatus(200);
            $response->assertSee('/images/default-dish.png', false);
        }
    }

    public function test_desktop_max_width_and_app_shell_renders_for_all_themes(): void
    {
        $themes = ['modern-bistro', 'luxury-dark', 'vibrant-glass', 'minimalist-light'];
        $this->vendor->update(['desktop_max_width' => '600px']);

        foreach ($themes as $themeSlug) {
            $tmpl = MenuTemplate::firstOrCreate(['slug' => $themeSlug], [
                'name' => ucfirst(str_replace('-', ' ', $themeSlug)),
                'is_active' => true,
            ]);
            $this->vendor->update(['menu_template_id' => $tmpl->id]);

            $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
            $response->assertStatus(200);
            $response->assertSee('storefront-app-shell');
            $response->assertSee('--desktop-max-width: 600px', false);
        }

        // Test custom width override
        $this->vendor->update(['desktop_max_width' => '480px']);
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertSee('--desktop-max-width: 480px', false);
    }

    public function test_bottom_nav_adapts_to_light_and_dark_themes(): void
    {
        // 1. Light mode -> nav-light
        $this->vendor->update([
            'theme_mode' => 'light',
            'bg_color' => null,
        ]);
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertSee('storefront-bottom-nav nav-light', false);
        $response->assertSee('storefront-scroll-top-btn btn-light', false);

        // 2. Dark mode -> nav-dark
        $this->vendor->update([
            'theme_mode' => 'dark',
            'bg_color' => null,
        ]);
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertSee('storefront-bottom-nav nav-dark', false);
        $response->assertSee('storefront-scroll-top-btn btn-dark', false);

        // 3. Custom dark background color -> nav-dark
        $this->vendor->update([
            'theme_mode' => 'light',
            'bg_color' => '#111827',
        ]);
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertSee('storefront-bottom-nav nav-dark', false);

        // 4. Custom light background color -> nav-light
        $this->vendor->update([
            'theme_mode' => 'dark',
            'bg_color' => '#ffffff',
        ]);
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertSee('storefront-bottom-nav nav-light', false);
    }
}
