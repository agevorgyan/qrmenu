<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Language;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultilingualUiUxAuditTest extends TestCase
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

        Language::firstOrCreate(
            ['code' => 'hy'],
            ['name' => 'Armenian', 'native_name' => 'Հայերեն', 'flag' => '🇦🇲', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]
        );
        Language::firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'native_name' => 'English', 'flag' => '🇬🇧', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]
        );
        Language::firstOrCreate(
            ['code' => 'ru'],
            ['name' => 'Russian', 'native_name' => 'Русский', 'flag' => '🇷🇺', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 3]
        );

        $plan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendor = Vendor::create([
            'name' => 'Multilingual Bistro',
            'slug' => 'multilingual-bistro',
            'email' => 'i18n@bistro.am',
            'password' => bcrypt('password'),
            'currency' => 'AMD',
            'theme' => 'modern-bistro',
            'supported_languages' => ['hy', 'en', 'ru'],
            'reservation_settings' => [
                'enabled' => true,
                'auto_confirm' => false,
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'min_party_size' => 1,
                'max_party_size' => 10,
                'advance_booking_days' => 30,
                'booking_cutoff_minutes' => 0,
                'slot_interval_minutes' => 30,
                'opening_time' => '10:00',
                'closing_time' => '23:00',
                'operating_days' => [0, 1, 2, 3, 4, 5, 6],
                'pre_orders_mode' => 'optional',
            ],
            'subscription_plan_id' => $plan?->id,
            'subscription_plan' => 'business',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'table_count' => 12,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Hot Dishes',
            'name_translations' => [
                'hy' => 'Տաք ուտեստներ',
                'en' => 'Hot Dishes',
                'ru' => 'Горячие блюда',
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Chicken Broth with Dumplings',
            'name_translations' => [
                'hy' => 'Հավի արգանակ պելմենիներով',
                'en' => 'Chicken Broth with Dumplings',
                'ru' => 'Куриный бульон с пельменями',
            ],
            'description' => 'Rich homemade chicken broth served hot',
            'description_translations' => [
                'hy' => 'Տնական հարուստ հավի արգանակ՝ տաք մատուցմամբ',
                'en' => 'Rich homemade chicken broth served hot',
                'ru' => 'Наваристый домашний куриный бульон',
            ],
            'price' => 2200,
            'preparation_time_min' => 15,
            'is_available' => true,
        ]);

        ProductVariation::create([
            'product_id' => $this->product->id,
            'name' => 'Standard',
            'price' => 2200,
            'is_default' => true,
        ]);

        RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '2',
            'name' => 'Table 2',
            'capacity' => 4,
            'min_capacity' => 1,
            'floor_zone' => 'Գլխավոր Սրահ',
            'status' => 'available',
            'is_active' => true,
            'is_online_bookable' => true,
        ]);
    }

    public function test_php_and_json_translation_files_have_complete_parity_across_hy_en_ru(): void
    {
        $locales = ['en', 'hy', 'ru'];
        $phpNamespaces = ['menu', 'common', 'orders', 'tips', 'split_bill', 'pagination'];

        foreach ($phpNamespaces as $ns) {
            $keysByLocale = [];
            foreach ($locales as $locale) {
                $path = base_path("lang/{$locale}/{$ns}.php");
                $this->assertFileExists($path, "Missing {$path}");
                $translations = include $path;
                $this->assertIsArray($translations);
                $keysByLocale[$locale] = array_keys($translations);

                foreach ($translations as $key => $value) {
                    $this->assertIsString($value, "Non-string value for {$ns}.{$key} in {$locale}");
                    $this->assertNotSame('', trim($value), "Empty translation for {$ns}.{$key} in {$locale}");
                }
            }

            $this->assertEquals(
                $keysByLocale['en'],
                $keysByLocale['hy'],
                "Key mismatch between en and hy in {$ns}.php"
            );
            $this->assertEquals(
                $keysByLocale['en'],
                $keysByLocale['ru'],
                "Key mismatch between en and ru in {$ns}.php"
            );
        }

        $jsonByLocale = [];
        foreach ($locales as $locale) {
            $path = base_path("lang/{$locale}.json");
            $this->assertFileExists($path);
            $decoded = json_decode((string) file_get_contents($path), true);
            $this->assertIsArray($decoded, "Invalid JSON in lang/{$locale}.json");
            $jsonByLocale[$locale] = $decoded;
        }

        $this->assertEquals(
            array_keys($jsonByLocale['en']),
            array_keys($jsonByLocale['hy']),
            'JSON key parity mismatch between en.json and hy.json'
        );
        $this->assertEquals(
            array_keys($jsonByLocale['en']),
            array_keys($jsonByLocale['ru']),
            'JSON key parity mismatch between en.json and ru.json'
        );
    }

    public function test_language_selection_modal_has_consistent_card_heights_subtitles_and_circular_radio(): void
    {
        foreach (['hy', 'en', 'ru'] as $locale) {
            $response = $this->get(route('client.menu', [
                'vendor_slug' => $this->vendor->slug,
                'lang' => $locale,
            ]));

            $response->assertStatus(200);
            $content = $response->getContent();

            // Circular radio indicator & consistent card height rules
            $this->assertStringContainsString('min-height: 64px;', $content);
            $this->assertStringContainsString('.lang-choice-radio', $content);
            $this->assertStringContainsString('role="dialog"', $content);
            $this->assertStringContainsString('aria-labelledby="langModalTitle"', $content);

            // Every enabled language option has a subtitle (English name or uppercase ISO code)
            $this->assertStringContainsString('Հայերեն', $content);
            $this->assertStringContainsString('Armenian', $content);
            $this->assertStringContainsString('English', $content);
            $this->assertStringContainsString('EN', $content);
            $this->assertStringContainsString('Русский', $content);
            $this->assertStringContainsString('Russian', $content);

            // Localized modal header and footer
            $this->assertStringContainsString(__('menu.select_your_language', [], $locale), $content);
            $this->assertStringContainsString(__('menu.change_language_anytime', [], $locale), $content);
        }
    }

    public function test_reservation_page_fixes_preorder_layout_badge_wrapping_duplicate_plus_and_area_tabs(): void
    {
        foreach (['hy', 'en', 'ru'] as $locale) {
            $response = $this->get(route('client.reserve', [
                'vendor_slug' => $this->vendor->slug,
                'lang' => $locale,
            ]));

            $response->assertStatus(200);
            $content = $response->getContent();

            // Pre-order badge nowrap & responsive stacked item card rules
            $this->assertStringContainsString('.preorder-count-badge', $content);
            $this->assertStringContainsString('white-space: nowrap;', $content);
            $this->assertStringContainsString('.preorder-item-main', $content);
            $this->assertStringContainsString('.preorder-item-controls', $content);

            // Area filter tabs horizontal scroll & nowrap
            $this->assertStringContainsString('.time-filter-tabs', $content);
            $this->assertStringContainsString('overflow-x: auto;', $content);

            // No duplicate "+ +" in Add More Dishes button translation
            $addMoreText = __('Add More Dishes in Main Menu', [], $locale);
            $this->assertStringStartsNotWith('+', ltrim($addMoreText));
            $this->assertStringContainsString($addMoreText, $content);

            // Currency normalized from AMD to ֏
            $this->assertStringContainsString("+ ' ' + '֏'", $content);
            // Viewport allows zoom (WCAG 2.2 AA)
            $this->assertStringNotContainsString('user-scalable=no', $content);
        }
    }

    public function test_cart_modal_ai_pairings_image_fallback_and_localized_reasons_across_languages(): void
    {
        foreach (['hy', 'en', 'ru'] as $locale) {
            $response = $this->get(route('client.menu', [
                'vendor_slug' => $this->vendor->slug,
                'lang' => $locale,
            ]));

            $response->assertStatus(200);
            $content = $response->getContent();

            // Image error fallback in Cart AI Pairings card
            $this->assertStringContainsString('x-data="{ imgError: false }"', $content);
            $this->assertStringContainsString('x-show="rec.image && !imgError"', $content);
            $this->assertStringContainsString('x-on:error="imgError = true"', $content);
            $this->assertStringContainsString('cart-ai-rec-img-placeholder', $content);

            // Localized AI pairing header & reasons passed to JS
            $this->assertStringContainsString(__('menu.ai_pairings_badge', [], $locale), $content);
            $this->assertStringContainsString(__('menu.frequently_ordered_together', [], $locale), $content);
            $this->assertStringContainsString(__('menu.perfect_pairings_subtitle', [], $locale), $content);
            $this->assertStringContainsString(__('menu.rec_reason_meat_side', [], $locale), $content);
            $this->assertStringContainsString(__('menu.rec_reason_sauce', [], $locale), $content);

            // When in English or Russian, Armenian hardcoded AI pairing header must not appear
            if ($locale !== 'hy') {
                $this->assertStringNotContainsString('Հաճախ պատվիրում են միասին', $content);
                $this->assertStringNotContainsString('Կատարյալ համադրություններ Ձեր զամբյուղի հետ', $content);
            }
        }
    }

    public function test_all_four_storefront_themes_normalize_amd_currency_symbol_and_support_rtl_and_long_strings(): void
    {
        $themes = ['modern-bistro', 'minimalist-light', 'vibrant-glass', 'luxury-dark'];

        Language::firstOrCreate(
            ['code' => 'ar'],
            ['name' => 'Arabic', 'native_name' => 'العربية', 'flag' => '🇦🇪', 'direction' => 'rtl', 'is_active' => true, 'is_default' => false, 'sort_order' => 4]
        );

        // Set a very long compound dish name & RTL language to test layout & dir support
        $this->vendor->update([
            'supported_languages' => ['hy', 'en', 'ru', 'ar'],
        ]);

        $this->product->update([
            'name_translations' => [
                'hy' => 'Հավի արգանակ տնական պելմենիներով և թարմ կանաչիներով հատուկ բաղադրատոմսով',
                'en' => 'Extraordinarily Long Artisanal Chicken Broth with Handcrafted Dumplings and Fresh Herbs',
                'ru' => 'Необычайно длинное название домашнего куриного бульона с пельменями ручной лепки и зеленью',
                'ar' => 'مرق دجاج منزلي الصنع مع الزلابية والأعشاب الطازجة',
            ],
        ]);

        foreach ($themes as $themeSlug) {
            $tmpl = MenuTemplate::firstOrCreate(
                ['slug' => $themeSlug],
                ['name' => ucfirst(str_replace('-', ' ', $themeSlug)), 'is_active' => true]
            );
            $this->vendor->update([
                'theme' => $themeSlug,
                'menu_template_id' => $tmpl->id,
            ]);

            foreach (['hy', 'en', 'ru'] as $locale) {
                $response = $this->get(route('client.menu', [
                    'vendor_slug' => $this->vendor->slug,
                    'lang' => $locale,
                ]));

                $response->assertStatus(200);
                $content = $response->getContent();

                $this->assertStringContainsString('dir="ltr"', $content);
                $this->assertStringContainsString('2,200 ֏', $content);
                $this->assertStringNotContainsString('user-scalable=no', $content);
            }

            // Verify RTL direction attribute when Arabic ('ar') is requested
            $rtlResponse = $this->get(route('client.menu', [
                'vendor_slug' => $this->vendor->slug,
                'lang' => 'ar',
            ]));
            $rtlResponse->assertStatus(200);
            $this->assertStringContainsString('dir="rtl"', $rtlResponse->getContent());
        }
    }
}
