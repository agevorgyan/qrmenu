<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendor = Vendor::create([
            'name' => 'Localization Bistro',
            'slug' => 'loc-bistro',
            'email' => 'loc@test.com',
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
            'name' => 'Center Branch',
            'slug' => 'center-branch',
            'table_count' => 10,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Appetizers',
            'name_translations' => [
                'hy' => 'Նախուտեստներ',
                'ru' => 'Закуски',
                'en' => 'Appetizers',
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Bruschetta',
            'name_translations' => [
                'hy' => 'Բրուսկետա',
                'ru' => 'Брускетта',
                'en' => 'Bruschetta',
            ],
            'description' => 'Crispy bread with tomato and basil',
            'description_translations' => [
                'hy' => 'Խռթխռթան հաց լոլիկով և ռեհանով',
                'ru' => 'Хрустящий хлеб с томатами и базиликом',
                'en' => 'Crispy bread with tomato and basil',
            ],
            'price' => 2000,
            'preparation_time_min' => 15,
            'is_available' => true,
        ]);
    }

    public function test_storefront_renders_armenian_by_default_or_explicitly(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'hy']));

        $response->assertStatus(200);
        $response->assertSee('Բրուսկետա');
        $response->assertSee('Նախուտեստներ');
        $response->assertSee(__('menu.cart', [], 'hy'));
        $response->assertSee(__('menu.browse_menu', [], 'hy'));
        $response->assertSee(__('menu.mins', [], 'hy'));
    }

    public function test_storefront_renders_english_when_requested(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'en']));

        $response->assertStatus(200);
        $response->assertSee('Bruschetta');
        $response->assertSee('Appetizers');
        $response->assertSee(__('menu.view_cart', [], 'en'));
        $response->assertSee(__('menu.browse_menu', [], 'en'));
        $response->assertSee(__('menu.mins', [], 'en'));
    }

    public function test_storefront_renders_russian_when_requested(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));

        $response->assertStatus(200);
        $response->assertSee('Брускетта');
        $response->assertSee('Закуски');
        $response->assertSee(__('menu.view_cart', [], 'ru'));
        $response->assertSee(__('menu.browse_menu', [], 'ru'));
        $response->assertSee(__('menu.mins', [], 'ru'));
    }

    public function test_storefront_persists_language_in_session(): void
    {
        // First request sets session to 'ru'
        $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']))
            ->assertSessionHas('locale', 'ru');

        // Subsequent request without query string preserves Russian
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertSee('Брускетта');
    }

    public function test_all_storefront_themes_support_localization(): void
    {
        $themes = ['modern-bistro', 'luxury-dark', 'vibrant-glass'];

        foreach ($themes as $theme) {
            $this->vendor->update(['theme' => $theme]);

            $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));
            $response->assertStatus(200);
            $response->assertSee(__('menu.checkout', [], 'ru'));
            $response->assertSee(__('menu.order_via_whatsapp', [], 'ru'));
            $response->assertSee(__('menu.order_summary', [], 'ru'));
        }
    }

    public function test_storefront_renders_translated_variations_for_different_languages(): void
    {
        ProductVariation::create([
            'product_id' => $this->product->id,
            'name' => 'Single Serving',
            'name_translations' => [
                'en' => 'Single Serving',
                'hy' => 'Մեկ բաժին',
                'ru' => 'Одинарная порция',
            ],
            'price' => 2000,
            'is_default' => true,
        ]);

        ProductVariation::create([
            'product_id' => $this->product->id,
            'name' => 'Double Serving',
            'name_translations' => [
                'en' => 'Double Serving',
                'hy' => 'Կրկնակի բաժին',
                'ru' => 'Двойная порция',
            ],
            'price' => 3500,
            'is_default' => false,
        ]);

        // Armenian storefront
        $responseHy = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'hy']));
        $responseHy->assertStatus(200);
        $responseHy->assertSee('Մեկ բաժին');
        $responseHy->assertSee('Կրկնակի բաժին');

        // Russian storefront
        $responseRu = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));
        $responseRu->assertStatus(200);
        $responseRu->assertSee('Одинарная порция');
        $responseRu->assertSee('Двойная порция');

        // English storefront
        $responseEn = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'en']));
        $responseEn->assertStatus(200);
        $responseEn->assertSee('Single Serving');
        $responseEn->assertSee('Double Serving');
    }

    public function test_storefront_operating_schedule_notices_change_language_properly(): void
    {
        // Configure delivery schedule 11:00 to 22:30, takeaway 10:00 to 23:00
        $this->vendor->update([
            'delivery_enabled' => true,
            'delivery_schedule_enabled' => true,
            'delivery_start_time' => '11:00',
            'delivery_end_time' => '22:30',
            'delivery_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'takeaway_enabled' => true,
            'takeaway_schedule_enabled' => true,
            'takeaway_start_time' => '10:00',
            'takeaway_end_time' => '23:00',
            'takeaway_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
        ]);

        // Fix time outside operating hours (03:00 AM)
        Carbon::setTestNow(Carbon::parse('2026-10-25 03:00:00', 'Asia/Yerevan'));

        // 1. English Storefront (luxury-dark theme as in user screenshot)
        $this->vendor->update(['theme' => 'luxury-dark']);
        $resEn = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'en']));
        $resEn->assertStatus(200);
        $resEn->assertSee('Delivery service is currently closed');
        $resEn->assertSee('Takeaway service is currently closed');
        $resEn->assertDontSee('Առաքման ծառայությունը այս պահին փակ է');

        // 2. Armenian Storefront
        $resHy = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'hy']));
        $resHy->assertStatus(200);
        $resHy->assertSee('Առաքման ծառայությունը այս պահին փակ է');
        $resHy->assertSee('Տանելու (Takeaway) ծառայությունը այս պահին փակ է');

        // 3. Russian Storefront
        $resRu = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));
        $resRu->assertStatus(200);
        $resRu->assertSee('Служба доставки сейчас закрыта');
        $resRu->assertSee('Служба навынос сейчас закрыта');

        Carbon::setTestNow();
    }
}
