<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Currency\CurrencyExchangeService;
use App\Services\TenantCache;
use App\Services\TenantContext;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondaryCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $owner;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Armenian Grill Bistro',
            'slug' => 'armenian-grill',
            'email' => 'bistro@armenian-grill.am',
            'password' => bcrypt('Password123!'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan?->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonths(6),
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'currency' => 'AMD',
        ]);

        $this->owner = User::create([
            'name' => 'Tigran Owner',
            'email' => 'bistro@armenian-grill.am',
            'password' => bcrypt('Password123!'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Center Branch',
            'slug' => 'main-center',
            'table_count' => 15,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Meat Dishes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Grilled Veal Kebab',
            'price' => 3500.00,
            'is_available' => true,
        ]);
    }

    public function test_new_and_existing_vendors_default_to_secondary_currency_disabled(): void
    {
        $this->assertFalse((bool) $this->vendor->secondary_currency_enabled);
        $this->assertFalse($this->vendor->isSecondaryCurrencyActive());
        $this->assertNull($this->vendor->convertPriceToSecondary(3500));
        $this->assertNull($this->vendor->formatSecondaryPrice(3500));
        $this->assertNull($this->vendor->getPublicSecondaryCurrencyData());
    }

    public function test_disabled_secondary_currency_menu_displays_only_primary_price(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('3,500');
        $response->assertSee('AMD');
        $response->assertDontSee('secondary-price');
        $response->assertDontSee('≈ $');
    }

    public function test_authorized_vendor_can_enable_and_configure_secondary_currency(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'USD',
            'secondary_currency_rate_mode' => 'manual',
            'secondary_currency_exchange_rate' => '350.00',
            'secondary_currency_decimal_places' => '2',
            'secondary_currency_prefix' => '≈',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->vendor->refresh();

        $this->assertTrue($this->vendor->secondary_currency_enabled);
        $this->assertEquals('USD', $this->vendor->secondary_currency);
        $this->assertEquals('350.000000', (string) $this->vendor->secondary_currency_exchange_rate);
        $this->assertEquals(2, $this->vendor->secondary_currency_decimal_places);
        $this->assertEquals('≈', $this->vendor->secondary_currency_prefix);
        $this->assertNotNull($this->vendor->secondary_currency_rate_updated_at);
        $this->assertTrue($this->vendor->isSecondaryCurrencyActive());
    }

    public function test_enabled_secondary_currency_displays_correctly_converted_price_on_public_menu(): void
    {
        $this->vendor->update([
            'secondary_currency_enabled' => true,
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => 350.00,
            'secondary_currency_decimal_places' => 2,
            'secondary_currency_prefix' => '≈',
        ]);
        TenantCache::increment($this->vendor, 'menu_version');

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('3,500');
        $response->assertSee('AMD');
        $response->assertSee('≈ $10.00');
        $response->assertSee('secondary-price');
    }

    public function test_custom_precision_and_prefix_render_correctly(): void
    {
        $this->vendor->update([
            'secondary_currency_enabled' => true,
            'secondary_currency' => 'EUR',
            'secondary_currency_exchange_rate' => 350.00,
            'secondary_currency_decimal_places' => 0,
            'secondary_currency_prefix' => '~',
        ]);
        TenantCache::increment($this->vendor, 'menu_version');

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('~ €10');

        $this->vendor->update([
            'secondary_currency_decimal_places' => 3,
            'secondary_currency_prefix' => 'ca.',
        ]);
        TenantCache::increment($this->vendor, 'menu_version');

        $response2 = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response2->assertStatus(200);
        $response2->assertSee('ca. €10.000');
    }

    public function test_disabling_feature_immediately_hides_secondary_price_and_preserves_configuration(): void
    {
        // First enable
        $this->vendor->update([
            'secondary_currency_enabled' => true,
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => 350.00,
            'secondary_currency_decimal_places' => 2,
            'secondary_currency_prefix' => '≈',
        ]);
        TenantCache::increment($this->vendor, 'menu_version');

        $res1 = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $res1->assertSee('≈ $10.00');

        // Now disable via settings update
        $resDisable = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'secondary_currency_enabled' => '0',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);
        $resDisable->assertSessionHasNoErrors();

        $this->vendor->refresh();
        $this->assertFalse($this->vendor->secondary_currency_enabled);
        $this->assertEquals('USD', $this->vendor->secondary_currency);
        $this->assertEquals('350.000000', (string) $this->vendor->secondary_currency_exchange_rate);
        $this->assertEquals(2, $this->vendor->secondary_currency_decimal_places);

        // Storefront menu must not show secondary price anymore
        $res2 = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $res2->assertDontSee('≈ $10.00');
        $res2->assertDontSee('secondary-price');
    }

    public function test_re_enabling_feature_restores_saved_configuration(): void
    {
        $this->vendor->update([
            'secondary_currency_enabled' => false,
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => 350.00,
            'secondary_currency_decimal_places' => 2,
            'secondary_currency_prefix' => '≈',
        ]);

        $resEnable = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'secondary_currency_enabled' => '1',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);
        $resEnable->assertSessionHasNoErrors();

        $this->vendor->refresh();
        $this->assertTrue($this->vendor->secondary_currency_enabled);
        $this->assertTrue($this->vendor->isSecondaryCurrencyActive());

        $res = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $res->assertSee('≈ $10.00');
    }

    public function test_validation_rejects_secondary_currency_matching_primary_currency(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'AMD',
            'secondary_currency_exchange_rate' => '1.00',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);

        $response->assertSessionHasErrors(['secondary_currency']);
    }

    public function test_validation_rejects_missing_zero_negative_or_malformed_exchange_rate(): void
    {
        // 1. Zero rate
        $r1 = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => '0',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);
        $r1->assertSessionHasErrors(['secondary_currency_exchange_rate']);

        // 2. Negative rate
        $r2 = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => '-350',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);
        $r2->assertSessionHasErrors(['secondary_currency_exchange_rate']);

        // 3. Malformed rate
        $r3 = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => 'not-a-number',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);
        $r3->assertSessionHasErrors(['secondary_currency_exchange_rate']);
    }

    public function test_multi_tenant_isolation_vendor_a_cannot_modify_vendor_b_settings(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendorB = Vendor::create([
            'name' => 'Competitor Cafe',
            'slug' => 'competitor-cafe',
            'email' => 'cafe@competitor.am',
            'password' => bcrypt('Secret999!'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan?->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonths(6),
            'is_active' => true,
            'currency' => 'AMD',
            'secondary_currency_enabled' => false,
        ]);

        $locationB = Location::create([
            'vendor_id' => $vendorB->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'is_active' => true,
        ]);

        $catB = Category::create([
            'vendor_id' => $vendorB->id,
            'name' => 'Cafe Coffee',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $productB = Product::create([
            'vendor_id' => $vendorB->id,
            'category_id' => $catB->id,
            'name' => 'Cappuccino',
            'price' => 1400.00,
            'is_available' => true,
        ]);

        // Enable secondary currency for Vendor A only
        $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => '350.00',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);

        $vendorB->refresh();
        $this->assertFalse((bool) $vendorB->secondary_currency_enabled);

        auth()->logout();
        app(TenantContext::class)->clear();

        // Vendor B's public menu must NOT render secondary price
        $resB = $this->get(route('client.menu', ['vendor_slug' => $vendorB->slug]));
        $resB->assertStatus(200);
        $resB->assertSee('1,400');
        $resB->assertDontSee('secondary-price');
        $resB->assertDontSee('≈ $');
    }

    public function test_unauthenticated_user_cannot_update_settings(): void
    {
        $response = $this->post(route('admin.settings.update'), [
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => '350.00',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_unit_currency_exchange_service_conversion_and_rounding(): void
    {
        $service = app(CurrencyExchangeService::class);

        // 3500 AMD at 350 AMD/USD = 10.00 USD
        $this->assertEquals(10.00, $service->convert(3500, 350, 2));
        $this->assertEquals(10.0, $service->convert(3500, 350, 1));
        $this->assertEquals(10.000, $service->convert(3500, 350, 3));
        $this->assertEquals(10.0, $service->convert(3500, 350, 0));

        // Format amount
        $this->assertEquals('≈ $10.00', $service->formatSecondaryPrice(3500, 'USD', 350, 2, '≈'));
        $this->assertEquals('≈ €10.00', $service->formatSecondaryPrice(3500, 'EUR', 350, 2, '≈'));
        $this->assertEquals('≈ 10.00 ₽', $service->formatSecondaryPrice(3500, 'RUB', 350, 2, '≈'));
        $this->assertEquals('≈ 10.00 ₾', $service->formatSecondaryPrice(3500, 'GEL', 350, 2, '≈'));
        $this->assertEquals('≈ £10.00', $service->formatSecondaryPrice(3500, 'GBP', 350, 2, '≈'));
        $this->assertEquals('≈ 10.00 د.إ', $service->formatSecondaryPrice(3500, 'AED', 350, 2, '≈'));

        // Edge cases
        $this->assertNull($service->convert(0, 350));
        $this->assertNull($service->convert(-500, 350));
        $this->assertNull($service->convert(3500, 0));
        $this->assertNull($service->convert(3500, -350));
        $this->assertNull($service->convert(null, 350));
        $this->assertNull($service->convert(3500, null));
    }

    public function test_regression_primary_prices_and_order_pricing_remain_strictly_authoritative(): void
    {
        $this->vendor->update([
            'secondary_currency_enabled' => true,
            'secondary_currency' => 'USD',
            'secondary_currency_exchange_rate' => 350.00,
        ]);

        // Product regular and effective price must remain strictly in primary currency AMD
        $this->assertEquals(3500.00, $this->product->getEffectivePrice());
        $this->assertEquals(3500.00, $this->product->getRegularPrice());

        // Variation prices remain strictly in primary currency
        $variation = ProductVariation::create([
            'product_id' => $this->product->id,
            'name' => 'Large Portion',
            'price' => 4500.00,
            'is_default' => false,
        ]);

        $this->assertEquals(4500.00, (float) $variation->price);
        $this->assertEquals(4500.00, (float) $variation->getEffectivePrice());

        // Secondary price method produces converted display, but does NOT modify the product model
        $this->assertEquals('≈ $12.86', $this->vendor->formatSecondaryPrice($variation->getEffectivePrice()));
        $this->assertEquals(4500.00, (float) $variation->getEffectivePrice());
    }

    public function test_admin_settings_live_exchange_rate_endpoint_returns_json_rate(): void
    {
        $response = $this->actingAs($this->owner)->getJson(route('admin.settings.currency.live_rate', [
            'currency' => 'AMD',
            'secondary_currency' => 'USD',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'primary',
            'target',
            'rate',
            'rate_full',
            'provider',
            'updated_at',
            'label',
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('AMD', $response->json('primary'));
        $this->assertEquals('USD', $response->json('target'));
        $this->assertGreaterThan(0, (float) $response->json('rate'));
    }

    public function test_vendor_can_save_settings_with_automatic_rate_mode(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'EUR',
            'secondary_currency_rate_mode' => 'automatic',
            'secondary_currency_decimal_places' => '2',
            'secondary_currency_prefix' => '≈',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->vendor->refresh();
        $this->assertTrue($this->vendor->secondary_currency_enabled);
        $this->assertEquals('EUR', $this->vendor->secondary_currency);
        $this->assertEquals('automatic', $this->vendor->secondary_currency_rate_mode);
        $this->assertGreaterThan(0, (float) $this->vendor->secondary_currency_exchange_rate);
        $this->assertEquals('exchangerate_api', $this->vendor->secondary_currency_rate_provider);
        $this->assertNotNull($this->vendor->secondary_currency_rate_updated_at);
    }

    public function test_vendor_can_save_settings_with_auto_by_language_mode(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'secondary_currency_enabled' => '1',
            'secondary_currency' => 'USD',
            'secondary_currency_rate_mode' => 'auto_by_language',
            'secondary_currency_decimal_places' => '2',
            'secondary_currency_prefix' => '≈',
            'service_fee_type' => 'percent',
            'service_fee_value' => '0',
            'delivery_fee' => '0',
            'delivery_min_amount' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->vendor->refresh();
        $this->assertTrue($this->vendor->secondary_currency_enabled);
        $this->assertEquals('auto_by_language', $this->vendor->secondary_currency_rate_mode);
        $this->assertTrue($this->vendor->isSecondaryCurrencyActive());
        $this->assertGreaterThan(0, (float) $this->vendor->secondary_currency_exchange_rate);
    }

    public function test_auto_by_language_mode_switches_secondary_currency_dynamically_per_storefront_language(): void
    {
        $this->vendor->update([
            'secondary_currency_enabled' => true,
            'secondary_currency' => 'USD',
            'secondary_currency_rate_mode' => 'auto_by_language',
            'secondary_currency_decimal_places' => 2,
            'secondary_currency_prefix' => '≈',
        ]);
        TenantCache::increment($this->vendor, 'menu_version');

        auth()->logout();
        app(TenantContext::class)->clear();

        // Russian -> RUB (₽)
        $ruRes = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));
        $ruRes->assertStatus(200);
        $ruRes->assertSee('₽');

        // German -> EUR (€)
        $deRes = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'de']));
        $deRes->assertStatus(200);
        $deRes->assertSee('€');

        // English -> USD ($)
        $enRes = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'en']));
        $enRes->assertStatus(200);
        $enRes->assertSee('$');

        // Georgian -> GEL (₾)
        $geRes = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ge']));
        $geRes->assertStatus(200);
        $geRes->assertSee('₾');

        // Armenian -> fallback USD ($)
        $hyRes = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'hy']));
        $hyRes->assertStatus(200);
        $hyRes->assertSee('$');
    }

    public function test_all_four_storefront_themes_support_language_aware_secondary_currency(): void
    {
        $this->vendor->update([
            'secondary_currency_enabled' => true,
            'secondary_currency' => 'USD',
            'secondary_currency_rate_mode' => 'auto_by_language',
            'secondary_currency_decimal_places' => 2,
            'secondary_currency_prefix' => '≈',
        ]);

        auth()->logout();
        app(TenantContext::class)->clear();

        $themes = ['modern-bistro', 'minimalist-light', 'vibrant-glass', 'luxury-dark'];

        foreach ($themes as $theme) {
            $this->vendor->update(['theme' => $theme]);
            TenantCache::increment($this->vendor, 'menu_version');

            $res = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));
            $res->assertStatus(200);
            $res->assertSee('₽', false);
        }
    }
}
