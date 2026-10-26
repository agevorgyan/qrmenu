<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use App\Services\AiWaiterService;
use App\Services\TimeAwareService;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerExperienceAndAiWaiterMasterTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $categoryMains;

    protected Category $categoryChicken;

    protected Category $categoryDrinks;

    protected Category $categoryBreakfast;

    protected Product $chickenKiev;

    protected Product $grilledChicken;

    protected Product $ribeyeSteak;

    protected Product $porkRibs;

    protected Product $pancakes;

    protected Product $houseWine;

    protected RestaurantTable $table5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Armenian Heritage Bistro',
            'slug' => 'heritage-bistro',
            'email' => 'contact@heritagebistro.am',
            'phone' => '+37410123456',
            'address' => 'Abovyan St 12, Yerevan',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
            'theme' => 'modern-bistro',
            'currency' => 'AMD',
            'ai_waiter_enabled' => true,
            'ai_waiter_name' => 'Արամ AI',
            'timezone' => 'Asia/Yerevan',
            'supported_locales' => ['hy', 'en', 'ru'],
            'delivery_enabled' => true,
            'delivery_fee' => 1000,
            'delivery_min_amount' => 5000,
            'takeaway_enabled' => true,
            'table_reservation_enabled' => true,
            'ai_waiter_settings' => [
                'time_aware_recommendations' => true,
                'meal_periods' => [
                    [
                        'id' => 'breakfast',
                        'name' => 'Breakfast',
                        'names' => ['hy' => 'Նախաճաշ', 'en' => 'Breakfast', 'ru' => 'Завтрак'],
                        'start_time' => '07:00',
                        'end_time' => '11:00',
                        'days' => [0, 1, 2, 3, 4, 5, 6],
                    ],
                    [
                        'id' => 'lunch',
                        'name' => 'Lunch',
                        'names' => ['hy' => 'Ճաշ', 'en' => 'Lunch', 'ru' => 'Обед'],
                        'start_time' => '12:00',
                        'end_time' => '16:00',
                        'days' => [0, 1, 2, 3, 4, 5, 6],
                    ],
                    [
                        'id' => 'dinner',
                        'name' => 'Dinner',
                        'names' => ['hy' => 'Ընթրիք', 'en' => 'Dinner', 'ru' => 'Ужин'],
                        'start_time' => '18:00',
                        'end_time' => '23:00',
                        'days' => [0, 1, 2, 3, 4, 5, 6],
                    ],
                    [
                        'id' => 'late_night',
                        'name' => 'Late Night',
                        'names' => ['hy' => 'Գիշերային մենյու', 'en' => 'Late Night', 'ru' => 'Ночное меню'],
                        'start_time' => '23:00',
                        'end_time' => '04:00',
                        'days' => [0, 1, 2, 3, 4, 5, 6],
                    ],
                ],
            ],
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Yerevan',
            'slug' => 'downtown',
            'address' => 'Abovyan St 12',
            'table_count' => 15,
            'is_active' => true,
        ]);

        $this->table5 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '5',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $this->categoryBreakfast = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Breakfast & Brunch',
            'name_translations' => ['hy' => 'Նախաճաշեր', 'en' => 'Breakfast', 'ru' => 'Завтраки'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->categoryChicken = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Chicken Specialties',
            'name_translations' => ['hy' => 'Հավով ուտեստներ', 'en' => 'Chicken Dishes', 'ru' => 'Блюда из курицы'],
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->categoryMains = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Meat & Steaks',
            'name_translations' => ['hy' => 'Մսային ուտեստներ', 'en' => 'Meat Dishes', 'ru' => 'Мясные блюда'],
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $this->categoryDrinks = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Wines & Drinks',
            'name_translations' => ['hy' => 'Խմիչքներ', 'en' => 'Drinks', 'ru' => 'Напитки'],
            'sort_order' => 4,
            'is_active' => true,
        ]);

        $this->chickenKiev = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryChicken->id,
            'name' => 'Chicken Kiev',
            'name_translations' => ['hy' => 'Կոտլետ Կիևյան', 'en' => 'Chicken Kiev', 'ru' => 'Котлета по-киевски'],
            'description' => 'Crispy chicken breast stuffed with herb butter',
            'price' => 3500,
            'is_active' => true,
            'is_available' => true,
            'ai_enabled' => true,
        ]);

        $this->grilledChicken = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryChicken->id,
            'name' => 'Grilled Chicken Fillet',
            'name_translations' => ['hy' => 'Հավի կրծքամիս գրիլ', 'en' => 'Grilled Chicken Fillet', 'ru' => 'Куриное филе на гриле'],
            'description' => 'Tender grilled chicken with seasonal vegetables',
            'price' => 3800,
            'is_active' => true,
            'is_available' => true,
            'ai_enabled' => true,
        ]);

        $this->ribeyeSteak = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryMains->id,
            'name' => 'Ribeye Steak',
            'name_translations' => ['hy' => 'Ռիբայ Սթեյք', 'en' => 'Ribeye Steak', 'ru' => 'Стейк Рибай'],
            'description' => 'Prime Black Angus beef steak',
            'price' => 8500,
            'is_active' => true,
            'is_available' => true,
            'ai_enabled' => true,
        ]);

        $this->porkRibs = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryMains->id,
            'name' => 'BBQ Pork Ribs',
            'name_translations' => ['hy' => 'Խոզի մատեր BBQ', 'en' => 'BBQ Pork Ribs', 'ru' => 'Свиные ребрышки BBQ'],
            'description' => 'Glazed pork ribs',
            'price' => 5500,
            'is_active' => true,
            'is_available' => true,
            'ai_enabled' => true,
        ]);

        $this->pancakes = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryBreakfast->id,
            'name' => 'Classic Pancakes',
            'name_translations' => ['hy' => 'Փանքեյքներ', 'en' => 'Pancakes', 'ru' => 'Блинчики'],
            'description' => 'Fluffy pancakes with maple syrup and berries',
            'price' => 2200,
            'is_active' => true,
            'is_available' => true,
            'ai_enabled' => true,
        ]);

        $this->houseWine = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryDrinks->id,
            'name' => 'Areni Dry Red Wine',
            'name_translations' => ['hy' => 'Արենի Կարմիր Չոր Գինի', 'en' => 'Areni Dry Red Wine', 'ru' => 'Красное сухое вино Арени'],
            'description' => 'Classic Armenian red wine from Vayots Dzor',
            'price' => 2500,
            'is_active' => true,
            'is_available' => true,
            'ai_enabled' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Mandatory Customer Entry Flow: Language Selection
    |--------------------------------------------------------------------------
    */

    public function test_fresh_storefront_visit_requires_language_selection_and_lists_only_enabled_languages(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertOk();
        $response->assertViewHas('needsLanguageSelection', true);
        $response->assertViewHas('supportedLanguages');

        $langs = $response->viewData('supportedLanguages');
        $codes = array_map(fn ($l) => $l['code'] ?? $l['locale'] ?? '', $langs);

        $this->assertContains('hy', $codes);
        $this->assertContains('en', $codes);
        $this->assertContains('ru', $codes);
        $this->assertNotContains('fr', $codes);

        // Native names must be present
        $names = array_map(fn ($l) => $l['native_name'] ?? $l['name'] ?? '', $langs);
        $this->assertContains('Հայերեն', $names);
        $this->assertContains('English', $names);
        $this->assertContains('Русский', $names);
    }

    public function test_setting_language_persists_in_session_and_removes_selection_prompt(): void
    {
        $setResponse = $this->postJson(route('client.language.set', ['vendor_slug' => $this->vendor->slug]), [
            'locale' => 'ru',
        ]);

        $setResponse->assertOk();
        $setResponse->assertJson(['success' => true, 'locale' => 'ru']);

        // Subsequent menu visit should not require language selection
        $menuResponse = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $menuResponse->assertOk();
        $menuResponse->assertViewHas('needsLanguageSelection', false);
        $menuResponse->assertViewHas('lang', 'ru');
    }

    public function test_invalid_or_disabled_language_is_rejected_and_falls_back(): void
    {
        $response = $this->postJson(route('client.language.set', ['vendor_slug' => $this->vendor->slug]), [
            'locale' => 'nonexistent_lang',
        ]);

        $response->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Context-Aware Entry: Table QR vs General Menu
    |--------------------------------------------------------------------------
    */

    public function test_general_menu_entry_sets_general_context(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertOk();
        $response->assertViewHas('entryContext', 'general_menu');
        $response->assertViewHas('table', null);
    }

    public function test_table_qr_entry_validates_table_and_sets_table_qr_context(): void
    {
        $response = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'table' => '5',
        ]));

        $response->assertOk();
        $response->assertViewHas('entryContext', 'table_qr');
        $response->assertViewHas('table', '5');
        $response->assertSessionHas('storefront_table_'.$this->vendor->slug, '5');
        $response->assertSessionHas('storefront_entry_context_'.$this->vendor->slug, 'table_qr');
    }

    public function test_table_session_persists_when_navigating_without_table_param(): void
    {
        // Initial Table QR entry
        $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'table' => '5',
        ]))->assertOk();

        // Second request without ?table=
        $secondResponse = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
        ]));

        $secondResponse->assertOk();
        $secondResponse->assertViewHas('table', '5');
        $secondResponse->assertViewHas('entryContext', 'table_qr');
    }

    public function test_forged_or_nonexistent_table_is_rejected(): void
    {
        $response = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'table' => '99999',
        ]));

        $response->assertOk();
        // Table 99999 does not exist in location (table_count=15) and has no RestaurantTable record
        $response->assertViewHas('table', null);
        $response->assertViewHas('entryContext', 'general_menu');
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Adaptive Conversation: Direct Requests Ask Zero Questions
    |--------------------------------------------------------------------------
    */

    public function test_direct_chicken_dish_request_asks_zero_questions_and_returns_chicken_dishes(): void
    {
        $session = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $this->vendor->slug]), [
            'lang' => 'hy',
            'entry_context' => 'table_qr',
            'table_number' => '5',
        ])->json();

        $token = $session['session_token'];

        $chatResponse = $this->postJson(route('client.ai_waiter.session.chat', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'message' => 'Ի՞նչ համեղ բան կառաջարկես հավով։',
        ]);

        $chatResponse->assertOk();
        $data = $chatResponse->json();

        $this->assertEquals('menu_recommendation', $data['intent']);
        $this->assertNotEmpty($data['suggested_products']);

        // Check that products returned are indeed chicken dishes
        $productNames = array_column($data['suggested_products'], 'name');
        $hasChicken = false;
        foreach ($productNames as $name) {
            if (mb_stripos($name, 'chicken') !== false || mb_stripos($name, 'հավ') !== false || mb_stripos($name, 'կիևյան') !== false) {
                $hasChicken = true;
                break;
            }
        }
        $this->assertTrue($hasChicken, 'Expected chicken dish in suggestions');

        // Verify quick replies are provided for effortless ordering
        $this->assertNotEmpty($data['quick_replies']);
    }

    public function test_direct_dinner_budget_and_party_size_request_calculates_combination_without_repetitive_questions(): void
    {
        $session = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $this->vendor->slug]), [
            'lang' => 'hy',
        ])->json();

        $token = $session['session_token'];

        $chatResponse = $this->postJson(route('client.ai_waiter.session.chat', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'message' => 'Երկու հոգու համար մինչև 10,000 դրամի սահմաններում ընթրիք առաջարկիր։',
        ]);

        $chatResponse->assertOk();
        $data = $chatResponse->json();

        $this->assertEquals('budget_combo', $data['intent']);
        $this->assertNotEmpty($data['suggested_products']);

        // Verify total price of suggested dishes is <= 10,000 AMD
        $totalPrice = 0;
        foreach ($data['suggested_products'] as $prod) {
            $totalPrice += (float) $prod['price'];
        }
        $this->assertLessThanOrEqual(10000, $totalPrice);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Recommendation Diversity & Rejection Tracking
    |--------------------------------------------------------------------------
    */

    public function test_rejecting_product_records_it_and_excludes_it_from_immediate_suggestions(): void
    {
        $session = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $this->vendor->slug]), [
            'lang' => 'hy',
        ])->json();

        $token = $session['session_token'];

        // Reject Chicken Kiev
        $rejectResponse = $this->postJson(route('client.ai_waiter.session.reject', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'product_id' => $this->chickenKiev->id,
        ]);

        $rejectResponse->assertOk();
        $rejectResponse->assertJson(['success' => true]);

        // Ask for chicken dishes now
        $chatResponse = $this->postJson(route('client.ai_waiter.session.chat', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'message' => 'Ի՞նչ ունեք հավով',
        ]);

        $data = $chatResponse->json();
        $suggestedIds = array_column($data['suggested_products'], 'id');

        $this->assertNotContains($this->chickenKiev->id, $suggestedIds, 'Rejected dish must not be immediately suggested');
    }

    public function test_strict_dietary_safety_never_recommends_meat_when_vegetarian_requested(): void
    {
        $aiService = app(AiWaiterService::class);

        $recommendations = $aiService->recommendDishes(
            $this->vendor,
            [
                'dietary' => 'vegetarian',
                'disliked_ingredients' => ['meat', 'beef', 'pork', 'chicken'],
            ],
            null,
            'hy',
            $this->location->id
        );

        $allRecs = array_merge($recommendations['main_recommendations'] ?? [], $recommendations['secondary_recommendations'] ?? []);
        foreach ($allRecs as $rec) {
            $productId = $rec['id'];
            $this->assertNotEquals($this->ribeyeSteak->id, $productId);
            $this->assertNotEquals($this->porkRibs->id, $productId);
            $this->assertNotEquals($this->chickenKiev->id, $productId);
            $this->assertNotEquals($this->grilledChicken->id, $productId);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Customer Service Integrations (Waiter Call, Reservations, Delivery, Events)
    |--------------------------------------------------------------------------
    */

    public function test_table_qr_session_calling_waiter_via_chat_dispatches_call_with_anti_spam(): void
    {
        $session = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $this->vendor->slug]), [
            'lang' => 'hy',
            'entry_context' => 'table_qr',
            'table_number' => '5',
        ])->json();

        $token = $session['session_token'];

        $firstCall = $this->postJson(route('client.ai_waiter.session.chat', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'message' => 'Մատուցող կանչեք',
        ]);

        $firstCall->assertOk();
        $this->assertEquals('waiter_call', $firstCall->json('intent'));
        $this->assertEquals('call_waiter', $firstCall->json('action'));

        // Immediate duplicate call within 60s should trigger anti-spam cooldown notice
        $secondCall = $this->postJson(route('client.ai_waiter.session.chat', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'message' => 'Մատուցող կանչեք',
        ]);

        $secondCall->assertOk();
        $this->assertStringContainsString('արդեն փոխանցվել է', $secondCall->json('reply'));
    }

    public function test_delivery_inquiry_returns_accurate_minimum_order_and_fee(): void
    {
        $session = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $this->vendor->slug]), [
            'lang' => 'hy',
        ])->json();

        $token = $session['session_token'];

        $chatResponse = $this->postJson(route('client.ai_waiter.session.chat', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'message' => 'Առաքում ունե՞ք',
        ]);

        $chatResponse->assertOk();
        $reply = $chatResponse->json('reply');
        $this->assertEquals('delivery', $chatResponse->json('intent'));
        $this->assertStringContainsString('5,000 ֏', $reply);
        $this->assertStringContainsString('1,000 ֏', $reply);
    }

    public function test_event_planning_inquiry_provides_guidance_and_submission_option(): void
    {
        $session = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $this->vendor->slug]), [
            'lang' => 'hy',
        ])->json();

        $token = $session['session_token'];

        $chatResponse = $this->postJson(route('client.ai_waiter.session.chat', [
            'vendor_slug' => $this->vendor->slug,
            'token' => $token,
        ]), [
            'message' => 'Ուզում եմ ծնունդ նշել 20 հոգով',
        ]);

        $chatResponse->assertOk();
        $this->assertEquals('event_inquiry', $chatResponse->json('intent'));
        $this->assertNotEmpty($chatResponse->json('quick_replies'));
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Section 13: Time-Aware AI Waiter, Greetings & Meal Periods
    |--------------------------------------------------------------------------
    */

    public function test_timezone_resolution_uses_vendor_configured_timezone(): void
    {
        $this->vendor->update(['timezone' => 'America/New_York']);

        $timeAwareService = app(TimeAwareService::class);
        $this->assertEquals('America/New_York', $timeAwareService->resolveTimezone($this->vendor));

        // When test time is 12:00 UTC, New York is 08:00 (EDT/EST)
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 12, 0, 0, 'UTC'));
        $localTime = $timeAwareService->getVendorLocalTime($this->vendor);
        $this->assertEquals('America/New_York', $localTime->timezoneName);
        $this->assertEquals(8, $localTime->hour);
    }

    public function test_time_aware_greetings_vary_by_time_of_day_and_language(): void
    {
        $timeAwareService = app(TimeAwareService::class);
        $this->vendor->update(['timezone' => 'Asia/Yerevan']);

        // 1. Morning (08:30 Yerevan time)
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 8, 30, 0, 'Asia/Yerevan'));
        $morningHy = $timeAwareService->getGreeting($this->vendor, 'hy', 'general_menu');
        $morningRu = $timeAwareService->getGreeting($this->vendor, 'ru', 'general_menu');
        $morningEn = $timeAwareService->getGreeting($this->vendor, 'en', 'general_menu');

        $this->assertStringContainsString('Բարի լույս', $morningHy);
        $this->assertStringContainsString('Доброе утро', $morningRu);
        $this->assertStringContainsString('Good morning', $morningEn);

        // 2. Daytime (14:30 Yerevan time)
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 14, 30, 0, 'Asia/Yerevan'));
        $daytimeHy = $timeAwareService->getGreeting($this->vendor, 'hy', 'general_menu');
        $daytimeRu = $timeAwareService->getGreeting($this->vendor, 'ru', 'general_menu');
        $daytimeEn = $timeAwareService->getGreeting($this->vendor, 'en', 'general_menu');

        $this->assertStringContainsString('Բարի օր', $daytimeHy);
        $this->assertStringContainsString('Добрый день', $daytimeRu);
        $this->assertStringContainsString('Good afternoon', $daytimeEn);

        // 3. Evening (19:30 Yerevan time)
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 19, 30, 0, 'Asia/Yerevan'));
        $eveningHy = $timeAwareService->getGreeting($this->vendor, 'hy', 'general_menu');
        $eveningRu = $timeAwareService->getGreeting($this->vendor, 'ru', 'general_menu');
        $eveningEn = $timeAwareService->getGreeting($this->vendor, 'en', 'general_menu');

        $this->assertStringContainsString('Բարի երեկո', $eveningHy);
        $this->assertStringContainsString('Добрый вечер', $eveningRu);
        $this->assertStringContainsString('Good evening', $eveningEn);

        // 4. Late Night (02:00 Yerevan time)
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 2, 0, 0, 'Asia/Yerevan'));
        $nightHy = $timeAwareService->getGreeting($this->vendor, 'hy', 'general_menu');
        $nightRu = $timeAwareService->getGreeting($this->vendor, 'ru', 'general_menu');

        $this->assertStringContainsString('Բարի գիշեր', $nightHy);
        $this->assertStringContainsString('Доброй ночи', $nightRu);

        // Clean up test time
        Carbon::setTestNow();
    }

    public function test_table_qr_greeting_is_concise_and_mentions_table_number(): void
    {
        $timeAwareService = app(TimeAwareService::class);
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 13, 0, 0, 'Asia/Yerevan'));

        $tableGreeting = $timeAwareService->getGreeting($this->vendor, 'hy', 'table_qr', '5');

        $this->assertStringContainsString('5', $tableGreeting);
        $this->assertTrue(str_contains(mb_strtolower($tableGreeting), 'մենյու'));

        Carbon::setTestNow();
    }

    public function test_meal_period_schedule_crossing_midnight_is_active(): void
    {
        $timeAwareService = app(TimeAwareService::class);
        $this->vendor->update(['timezone' => 'Asia/Yerevan']);

        // 01:30 AM is inside late_night period (23:00 to 04:00)
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 1, 30, 0, 'Asia/Yerevan'));
        $period = $timeAwareService->getActiveMealPeriod($this->vendor);

        $this->assertNotNull($period);
        $this->assertEquals('late_night', $period['id']);

        Carbon::setTestNow();
    }

    public function test_breakfast_meal_period_prioritizes_breakfast_dishes(): void
    {
        $timeAwareService = app(TimeAwareService::class);
        $aiService = app(AiWaiterService::class);

        // Morning 09:00 AM (Breakfast period)
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 9, 0, 0, 'Asia/Yerevan'));
        $activePeriod = $timeAwareService->getActiveMealPeriod($this->vendor);
        $this->assertEquals('breakfast', $activePeriod['id']);

        $recommendations = $aiService->recommendDishes(
            $this->vendor,
            [
                'active_meal_period' => $activePeriod,
            ],
            null,
            'hy',
            $this->location->id
        );

        $mainRecs = $recommendations['main_recommendations'] ?? [];
        $this->assertNotEmpty($mainRecs);
        $topIds = array_column(array_slice($mainRecs, 0, 2), 'id');
        $this->assertContains($this->pancakes->id, $topIds);

        Carbon::setTestNow();
    }

    public function test_ai_disabled_mode_does_not_initialize_ai_waiter(): void
    {
        $this->vendor->update(['ai_waiter_enabled' => false]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));
        $response->assertOk();

        // When AI waiter is disabled, session endpoint returns error/disabled
        $sessionResponse = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $this->vendor->slug]), [
            'lang' => 'hy',
        ]);

        $sessionResponse->assertStatus(403);
    }
}
