<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure base languages exist in test environment
        Language::firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'native_name' => 'English', 'flag' => '🇬🇧', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]
        );
        Language::firstOrCreate(
            ['code' => 'hy'],
            ['name' => 'Armenian', 'native_name' => 'Հայերեն', 'flag' => '🇦🇲', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]
        );
        Language::firstOrCreate(
            ['code' => 'ru'],
            ['name' => 'Russian', 'native_name' => 'Русский', 'flag' => '🇷🇺', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 3]
        );

        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_default_system_locale_is_english(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@qrmenu.local',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        // Default access to dashboard without session is English
        $dashRes = $this->actingAs($superadmin)->get(route('superadmin.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertSee('Subscription Plans');
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_switching_to_valid_supported_locale_works(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@qrmenu.local',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        $response = $this->actingAs($superadmin)->get(route('lang.switch', 'hy'));
        $response->assertRedirect();
        $this->assertEquals('hy', session('admin_locale'));

        $ruResponse = $this->actingAs($superadmin)->get(route('lang.switch', 'ru'));
        $ruResponse->assertRedirect();
        $this->assertEquals('ru', session('admin_locale'));
    }

    public function test_switching_to_unsupported_locale_is_rejected(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@qrmenu.local',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        // Set to English
        $this->actingAs($superadmin)->get(route('lang.switch', 'en'));
        $this->assertEquals('en', session('admin_locale'));

        // Attempt invalid locale
        $invalidResponse = $this->actingAs($superadmin)->get(route('lang.switch', 'invalid_xx'));
        $invalidResponse->assertRedirect();
        $this->assertEquals('en', session('admin_locale'));
    }

    public function test_landing_page_displays_language_switcher(): void
    {
        $response = $this->get(route('landing'));
        $response->assertStatus(200);
        $response->assertSee(route('lang.switch', 'hy'));
        $response->assertSee(route('lang.switch', 'en'));
        $response->assertSee(route('lang.switch', 'ru'));
        $response->assertSee('ՀԱՅ');
        $response->assertSee('ENG');
        $response->assertSee('РУС');
    }

    public function test_auth_pages_display_language_switcher(): void
    {
        // 1. Login page
        $loginRes = $this->get(route('login'));
        $loginRes->assertStatus(200);
        $loginRes->assertSee(route('lang.switch', 'hy'));
        $loginRes->assertSee(route('lang.switch', 'en'));
        $loginRes->assertSee(route('lang.switch', 'ru'));

        // 2. Register page
        $registerRes = $this->get(route('register.show'));
        $registerRes->assertStatus(200);
        $registerRes->assertSee(route('lang.switch', 'hy'));
        $registerRes->assertSee(route('lang.switch', 'en'));
        $registerRes->assertSee(route('lang.switch', 'ru'));

        // 3. Forgot Password page
        $forgotRes = $this->get(route('password.request'));
        $forgotRes->assertStatus(200);
        $forgotRes->assertSee(route('lang.switch', 'hy'));
        $forgotRes->assertSee(route('lang.switch', 'en'));
        $forgotRes->assertSee(route('lang.switch', 'ru'));

        // 4. Demo Login page
        $demoRes = $this->get(route('demo.login'));
        $demoRes->assertStatus(200);
        $demoRes->assertSee(route('lang.switch', 'hy'));
        $demoRes->assertSee(route('lang.switch', 'en'));
        $demoRes->assertSee(route('lang.switch', 'ru'));
    }

    public function test_partner_admin_header_displays_language_switcher(): void
    {
        $user = User::create([
            'name' => 'Vendor Admin',
            'email' => 'vendor@test.am',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        $dashRes = $this->actingAs($user)->get(route('superadmin.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertSee(route('lang.switch', 'hy'));
        $dashRes->assertSee(route('lang.switch', 'en'));
        $dashRes->assertSee(route('lang.switch', 'ru'));
        $dashRes->assertSee('Հայերեն');
        $dashRes->assertSee('English');
        $dashRes->assertSee('Русский');
    }

    public function test_selected_language_persists_across_refreshes_via_cookies_and_session(): void
    {
        // Switch to Armenian
        $switchRes = $this->get(route('lang.switch', 'hy'));
        $switchRes->assertRedirect();
        $switchRes->assertCookie('app_locale', 'hy');
        $switchRes->assertCookie('admin_locale', 'hy');

        // Refresh/re-visit landing with the cookie
        $landingRes = $this->withUnencryptedCookie('app_locale', 'hy')
            ->withSession(['app_locale' => 'hy', 'admin_locale' => 'hy', 'locale' => 'hy'])
            ->get(route('landing'));
        $landingRes->assertStatus(200);
        $this->assertEquals('hy', app()->getLocale());

        // Refresh/re-visit login page with cookie
        $loginRes = $this->withUnencryptedCookie('app_locale', 'hy')
            ->withSession(['app_locale' => 'hy', 'admin_locale' => 'hy', 'locale' => 'hy'])
            ->get(route('login'));
        $loginRes->assertStatus(200);
        $this->assertEquals('hy', app()->getLocale());

        // Switch to Russian
        $switchRu = $this->get(route('lang.switch', 'ru'));
        $switchRu->assertRedirect();
        $switchRu->assertCookie('app_locale', 'ru');

        // Refresh login in Russian
        $loginRu = $this->withUnencryptedCookie('app_locale', 'ru')
            ->withSession(['app_locale' => 'ru', 'admin_locale' => 'ru', 'locale' => 'ru'])
            ->get(route('login'));
        $loginRu->assertStatus(200);
        $this->assertEquals('ru', app()->getLocale());
    }

    public function test_authenticated_user_persists_locale_in_database_and_across_logout(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.am',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        $this->actingAs($user)->get(route('lang.switch', 'hy'));
        $user->refresh();
        $this->assertEquals('hy', $user->locale);

        // Logging out should keep locale in cookie and session
        $logoutRes = $this->actingAs($user)->post(route('logout'));
        $logoutRes->assertRedirect(route('login'));
        $logoutRes->assertCookie('app_locale', 'hy');
    }

    public function test_login_page_renders_complete_russian_translations(): void
    {
        $res = $this->withUnencryptedCookie('app_locale', 'ru')
            ->withSession(['app_locale' => 'ru', 'admin_locale' => 'ru'])
            ->get(route('login'));

        $res->assertStatus(200);
        $res->assertSee('Умное ресторанное QR-меню и система заказов');
        $res->assertSee('Запомнить меня');
        $res->assertSee('Забыли пароль?');
        $res->assertSee('Проверка безопасности (CAPTCHA)');
        $res->assertSee('Введите ответ');
        $res->assertSee('или');
        $res->assertSee('Ознакомиться с Демо');
        $res->assertSee('Протестируйте систему в 1 клик');
        $res->assertSee('Еще нет зарегистрированного ресторана?');
        $res->assertSee('Зарегистрироваться как партнер (14 дней бесплатно)');
    }

    public function test_auth_pages_render_responsive_mobile_dropdown_and_segmented_desktop_switch(): void
    {
        $res = $this->get(route('login'));
        $res->assertStatus(200);
        $res->assertSee('auth-lang-segmented');
        $res->assertSee('auth-lang-dropdown');
        $res->assertSee('auth-lang-trigger');
        $res->assertSee('auth-lang-menu');
        $res->assertSee('toggleAuthLang');
    }

    public function test_kitchen_orders_page_renders_localized_translations_in_russian(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Mario Pizza',
            'slug' => 'mario-pizza',
            'email' => 'mario@pizza.am',
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $loc = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Main',
            'slug' => 'main',
            'is_active' => true,
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'location_id' => $loc->id,
            'name' => 'Chef Mario',
            'email' => 'chef@pizza.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
            'locale' => 'ru',
        ]);

        $res = $this->actingAs($user)
            ->withSession(['admin_locale' => 'ru', 'app_locale' => 'ru'])
            ->get(route('admin.orders.index'));

        $res->assertOk();
        $res->assertSee('Экран заказов кухни');
        $res->assertSee('Заказы со столов и на доставку в реальном времени для');
        $res->assertSee('Звук включен');
        $res->assertSee('Включить звук');
        $res->assertSee('Все заказы');
        $res->assertSee('Готовится');
        $res->assertSee('Готово');
        $res->assertSee('Заказов не найдено');
        $res->assertSee('Новые заказы со столов и на доставку будут автоматически появляться здесь в реальном времени.');
        $res->assertSee('Языки и переводы');

        $resHy = $this->actingAs($user)
            ->withSession(['admin_locale' => 'hy', 'app_locale' => 'hy'])
            ->get(route('admin.orders.index'));

        $resHy->assertOk();
        $resHy->assertSee('Խոհանոցի պատվերների էկրան');
        $resHy->assertSee('Սեղանների և առաքման պատվերներ իրական ժամանակում՝');
        $resHy->assertSee('Ձայնը միացված');
        $resHy->assertSee('Միացնել ձայնը');
        $resHy->assertSee('Բոլոր պատվերները');
        $resHy->assertSee('Պատրաստվում է');
        $resHy->assertSee('Պատրաստ է');
        $resHy->assertSee('Պատվերներ չեն գտնվել');
        $resHy->assertSee('Սեղանների և առաքման նոր պատվերներն ավտոմատ կհայտնվեն այստեղ իրական ժամանակում:');
        $resHy->assertSee('Լեզուներ և թարգմանություններ');
    }

    public function test_analytics_subscription_and_settings_render_in_selected_locale(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Cascades Main Branch',
            'slug' => 'cascades-main',
            'email' => 'cascades@example.com',
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $loc = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Cascades Main Branch',
            'slug' => 'cascades-main-loc',
            'is_active' => true,
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'location_id' => $loc->id,
            'name' => 'Owner',
            'email' => 'owner@cascades.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
            'locale' => 'ru',
        ]);

        // 1. Analytics in Russian
        $resRuAnalytics = $this->actingAs($user)
            ->withSession(['admin_locale' => 'ru', 'app_locale' => 'ru'])
            ->get(route('admin.analytics.index'));

        $resRuAnalytics->assertOk();
        $resRuAnalytics->assertSee('Аналитика и Трафик');
        $resRuAnalytics->assertSee('Всего просмотров меню');
        $resRuAnalytics->assertSee('Популярные блюда');
        $resRuAnalytics->assertSee('Сравнение каналов');
        $resRuAnalytics->assertSee('Последние 7 дней');
        $resRuAnalytics->assertSee('QR-сканы столов');

        // Analytics in Armenian
        $resHyAnalytics = $this->actingAs($user)
            ->withSession(['admin_locale' => 'hy', 'app_locale' => 'hy'])
            ->get(route('admin.analytics.index'));

        $resHyAnalytics->assertOk();
        $resHyAnalytics->assertSee('Վերլուծություն և Տրաֆիկ');
        $resHyAnalytics->assertSee('Մենյուի ընդհանուր դիտումներ');
        $resHyAnalytics->assertSee('Վերջին 7 օր');

        // 2. Subscription in Russian
        $resRuSub = $this->actingAs($user)
            ->withSession(['admin_locale' => 'ru', 'app_locale' => 'ru'])
            ->get(route('admin.subscription'));

        $resRuSub->assertOk();
        $resRuSub->assertSee('Все возможности тарифа Basic');
        $resRuSub->assertSee('Онлайн-заказы');
        $resRuSub->assertSee('Кошелек Idram / QR');
        $resRuSub->assertSee('/ мес');

        // Subscription in Armenian
        $resHySub = $this->actingAs($user)
            ->withSession(['admin_locale' => 'hy', 'app_locale' => 'hy'])
            ->get(route('admin.subscription'));

        $resHySub->assertOk();
        $resHySub->assertSee('Basic-ի բոլոր հնարավորությունները');
        $resHySub->assertSee('Օնլայն պատվերներ');
        $resHySub->assertSee('/ ամիս');

        // 3. Settings in Russian
        $resRuSettings = $this->actingAs($user)
            ->withSession(['admin_locale' => 'ru', 'app_locale' => 'ru'])
            ->get(route('admin.settings.index'));

        $resRuSettings->assertOk();
        $resRuSettings->assertSee('Армянский драм');
        $resRuSettings->assertSee('Грамм (г / g)');
        $resRuSettings->assertSee('Миллилитр (мл / ml)');
        $resRuSettings->assertSee('Пн');
        $resRuSettings->assertSee('Вт');
        $resRuSettings->assertSee('Ср');
        $resRuSettings->assertSee('За 30 минут (Рекомендуется)');

        // Settings in Armenian
        $resHySettings = $this->actingAs($user)
            ->withSession(['admin_locale' => 'hy', 'app_locale' => 'hy'])
            ->get(route('admin.settings.index'));

        $resHySettings->assertOk();
        $resHySettings->assertSee('ՀՀ Դրամ');
        $resHySettings->assertSee('Երկ');
        $resHySettings->assertSee('Երք');
        $resHySettings->assertSee('Չրք');
        $resHySettings->assertSee('30 րոպե առաջ (Խորհուրդ է տրվում)');

        // 4. Settings update flash message in Russian
        $resUpdateRu = $this->actingAs($user)
            ->withSession(['admin_locale' => 'ru', 'app_locale' => 'ru'])
            ->post(route('admin.settings.update'), [
                'name' => 'Cascades Main Branch Updated',
                'currency' => 'AMD',
                'weight_unit' => 'g',
                'volume_unit' => 'ml',
                'service_fee_type' => 'percent',
                'service_fee_value' => 10,
                'delivery_fee' => 0,
                'delivery_min_amount' => 0,
            ]);

        $resUpdateRu->assertRedirect();
        $resUpdateRu->assertSessionHas('success', 'Настройки успешно сохранены.');
    }
}
