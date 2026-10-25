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

class EnterpriseSidebarAndSettingsRefactoringTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $owner;

    protected Location $location;

    protected SubscriptionPlan $businessPlan;

    protected SubscriptionPlan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

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

        $this->businessPlan = SubscriptionPlan::where('slug', 'business')->first();
        $this->basicPlan = SubscriptionPlan::where('slug', 'basic')->first();

        $this->vendor = Vendor::create([
            'name' => 'Enterprise Bistro',
            'slug' => 'enterprise-bistro',
            'email' => 'owner@enterprisebistro.am',
            'password' => bcrypt('Secret123!'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $this->businessPlan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'reservation_settings' => [
                'enabled' => true,
                'min_party_size' => 1,
                'max_party_size' => 12,
                'opening_time' => '10:00',
                'closing_time' => '23:00',
                'slot_interval_minutes' => 30,
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'advance_booking_days' => 14,
                'booking_cutoff_minutes' => 0,
                'operating_days' => [0, 1, 2, 3, 4, 5, 6],
            ],
            'payment_settings' => [
                'online_enabled' => true,
                'cash_enabled' => true,
                'pos_terminal_enabled' => true,
                'gateways' => [
                    'idram' => [
                        'enabled' => true,
                        'merchant_id' => '12345678',
                        'secret_key' => 'super_secret_idram_key_9999',
                        'configured' => true,
                        'masked' => 'key_9999',
                    ],
                ],
            ],
        ]);

        $this->owner = User::create([
            'name' => 'Owner Arthur',
            'email' => 'owner@enterprisebistro.am',
            'password' => bcrypt('Secret123!'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall Branch',
            'slug' => 'main-hall-branch',
            'address' => 'Northern Avenue 10',
            'is_active' => true,
            'table_count' => 6,
        ]);
    }

    public function test_owner_can_access_all_primary_navigation_destinations(): void
    {
        $routes = [
            'admin.dashboard',
            'admin.orders.index',
            'admin.reservations.index',
            'admin.floor_plan.index',
            'admin.tables.index',
            'admin.qr.index',
            'admin.menu.index',
            'admin.branding.index',
            'admin.translations.index',
            'admin.ai.import',
            'admin.settings.ai',
            'admin.customers.index',
            'admin.analytics.index',
            'admin.locations.index',
            'admin.team.index',
            'admin.settings.index',
            'admin.subscription.index',
        ];

        foreach ($routes as $routeName) {
            $response = $this->actingAs($this->owner)->get(route($routeName));
            $this->assertEquals(
                200,
                $response->status(),
                "Failed asserting that navigation route [{$routeName}] resolves with HTTP 200."
            );
        }
    }

    public function test_sidebar_active_route_and_group_expansion(): void
    {
        // 1. Visit Table Map (Child of Dining Room & Tables)
        $response = $this->actingAs($this->owner)->get(route('admin.floor_plan.index'));
        $response->assertStatus(200);

        // Sidebar should contain the collapsible parent group initialized to open
        $response->assertSee('tablesOpen: true', false);
        // Active link styling
        $response->assertSee('active', false);
        $response->assertSee(route('admin.floor_plan.index'), false);

        // 2. Visit Dashboard
        $dashResponse = $this->actingAs($this->owner)->get(route('admin.dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee(route('admin.dashboard'), false);

        // 3. Visit Settings Hub
        $settingsResponse = $this->actingAs($this->owner)->get(route('admin.settings.index'));
        $settingsResponse->assertStatus(200);
        $settingsResponse->assertSee(route('admin.settings.index'), false);
        $settingsResponse->assertSee('settingsOpen: true', false);
        $settingsResponse->assertSee("selectSettingsTab('profile', \$event)", false);
        $settingsResponse->assertSee("selectSettingsTab('payments', \$event)", false);
    }

    public function test_role_based_navigation_visibility(): void
    {
        // Chef user
        $chef = User::create([
            'name' => 'Chef Karen',
            'email' => 'chef@enterprisebistro.am',
            'password' => bcrypt('Secret123!'),
            'vendor_id' => $this->vendor->id,
            'role' => 'chef',
            'email_verified_at' => now(),
        ]);

        $chefResponse = $this->actingAs($chef)->get(route('admin.orders.index'));
        $chefResponse->assertStatus(200);
        $chefResponse->assertSee(route('admin.orders.index'), false);
        $chefResponse->assertSee(route('admin.menu.index'), false);
        // Chef should not see Settings or Billing in sidebar
        $chefResponse->assertDontSee(route('admin.settings.index'), false);
        $chefResponse->assertDontSee(route('admin.subscription.index'), false);
        $chefResponse->assertDontSee(route('admin.team.index'), false);

        // Manager user
        $manager = User::create([
            'name' => 'Manager Suren',
            'email' => 'manager@enterprisebistro.am',
            'password' => bcrypt('Secret123!'),
            'vendor_id' => $this->vendor->id,
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        $managerResponse = $this->actingAs($manager)->get(route('admin.dashboard'));
        $managerResponse->assertStatus(200);
        $managerResponse->assertSee(route('admin.orders.index'), false);
        $managerResponse->assertSee(route('admin.team.index'), false);
        $managerResponse->assertSee(route('admin.settings.index'), false);
        // Manager must NOT see Billing
        $managerResponse->assertDontSee(route('admin.subscription.index'), false);

        // Staff user
        $staff = User::create([
            'name' => 'Staff Anna',
            'email' => 'staff@enterprisebistro.am',
            'password' => bcrypt('Secret123!'),
            'vendor_id' => $this->vendor->id,
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $staffResponse = $this->actingAs($staff)->get(route('admin.orders.index'));
        $staffResponse->assertStatus(200);
        $staffResponse->assertDontSee(route('admin.settings.index'), false);
        $staffResponse->assertDontSee(route('admin.subscription.index'), false);
        $staffResponse->assertDontSee(route('admin.team.index'), false);
    }

    public function test_unentitled_features_show_badges_in_sidebar(): void
    {
        // Downgrade vendor to basic plan
        $this->vendor->update([
            'subscription_plan' => 'basic',
            'subscription_plan_id' => $this->basicPlan->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // PRO & BIZ badges should appear next to locked features
        $response->assertSee('nav-lock-badge', false);
        $response->assertSee('PRO', false);
        $response->assertSee('BIZ', false);
    }

    public function test_settings_hub_renders_all_ten_category_tabs(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.settings.index'));
        $response->assertStatus(200);

        // Check for presence of category IDs and names in the tabs
        $categories = [
            'profile',
            'orders',
            'reservations',
            'payments',
            'delivery',
            'notifications',
            'regional',
            'ai',
            'integrations',
            'security',
        ];

        foreach ($categories as $cat) {
            $response->assertSee("activeTab === '{$cat}'", false);
        }

        // Deep linking via query param
        $paramResponse = $this->actingAs($this->owner)->get(route('admin.settings.index', ['tab' => 'payments']));
        $paramResponse->assertStatus(200);
        $paramResponse->assertSee("activeTab === 'payments'", false);

        // Secret credentials protection: raw secret key must NEVER leak in HTML
        $response->assertDontSee('super_secret_idram_key_9999', false);
        $response->assertSee('••••••••', false);
    }

    public function test_settings_update_persists_valid_data(): void
    {
        $response = $this->actingAs($this->owner)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'name' => 'Updated Enterprise Bistro',
                'phone' => '+37410123456',
                'address' => 'Tumanyan 5, Yerevan',
                'timezone' => 'Asia/Yerevan',
                'currency' => 'AMD',
                'service_fee_enabled' => 1,
                'service_fee_type' => 'percent',
                'service_fee_value' => 10,
                'delivery_enabled' => 1,
                'delivery_fee' => 500,
                'delivery_min_amount' => 3000,
            ]);

        $response->assertRedirect(route('admin.settings.index'));
        $response->assertSessionHas('success');

        $this->vendor->refresh();
        $this->location->refresh();
        $this->assertEquals('AMD', $this->vendor->currency);
        $this->assertEquals('Asia/Yerevan', $this->vendor->timezone);
        $this->assertEquals(10, $this->vendor->service_fee_value);
        $this->assertEquals('Tumanyan 5, Yerevan', $this->location->address);
    }

    public function test_settings_update_handles_validation_errors(): void
    {
        // Invalid service fee type and negative delivery fee
        $response = $this->actingAs($this->owner)->post(route('admin.settings.update'), [
            'service_fee_type' => 'invalid_fee_type',
            'service_fee_value' => -50,
            'delivery_fee' => -100,
        ]);

        $response->assertSessionHasErrors(['service_fee_type', 'service_fee_value', 'delivery_fee']);
    }

    public function test_multilingual_sidebar_and_settings_rendering(): void
    {
        // 1. Armenian locale
        $armResponse = $this->actingAs($this->owner)
            ->withSession(['admin_locale' => 'hy'])
            ->get(route('admin.dashboard'));

        $armResponse->assertStatus(200);
        $armResponse->assertSee('Գլխավոր վահանակ');
        $armResponse->assertSee('Պատվերներ');
        $armResponse->assertSee('Ամրագրումներ');
        $armResponse->assertSee('Սրահ և սեղաններ');
        $armResponse->assertSee('Մենյու');
        $armResponse->assertSee('Մենյուի ձևավորում');
        $armResponse->assertSee('Լեզուներ և թարգմանություններ');
        $armResponse->assertSee('AI օգնական');
        $armResponse->assertSee('Հաճախորդներ և CRM');
        $armResponse->assertSee('Մասնաճյուղեր');
        $armResponse->assertSee('Թիմ և աշխատակիցներ');
        $armResponse->assertSee('Կարգավորումներ');
        $armResponse->assertSee('Բաժանորդագրություն և վճարումներ');
        $armResponse->assertSee('Օգնություն և աջակցություն');

        // Armenian Settings Hub
        $armSettingsResponse = $this->actingAs($this->owner)
            ->withSession(['admin_locale' => 'hy'])
            ->get(route('admin.settings.index'));

        $armSettingsResponse->assertStatus(200);
        $armSettingsResponse->assertSee('Կարգավորումներ');
        $armSettingsResponse->assertSee('Ռեստորանի պրոֆիլ');
        $armSettingsResponse->assertSee('Պատվերներ և խոհանոց');
        $armSettingsResponse->assertSee('Առաքում և վերցնել տեղում');
        $armSettingsResponse->assertSee('Վճարումներ և դրամապանակներ');
        $armSettingsResponse->assertSee('Անվտանգություն և հասանելիություն');

        // 2. Russian locale
        $ruResponse = $this->actingAs($this->owner)
            ->withSession(['admin_locale' => 'ru'])
            ->get(route('admin.dashboard'));

        $ruResponse->assertStatus(200);
        $ruResponse->assertSee('Панель управления');
        $ruResponse->assertSee('Заказы');
        $ruResponse->assertSee('Бронирования');
        $ruResponse->assertSee('Зал и столы');
        $ruResponse->assertSee('Меню');
        $ruResponse->assertSee('Оформление меню');
        $ruResponse->assertSee('Языки и переводы');
        $ruResponse->assertSee('Клиенты и CRM');
        $ruResponse->assertSee('Филиалы');
        $ruResponse->assertSee('Команда и сотрудники');
        $ruResponse->assertSee('Настройки');
        $ruResponse->assertSee('Подписка и оплата');
    }

    public function test_cross_tenant_isolation(): void
    {
        // Vendor B
        $vendorB = Vendor::create([
            'name' => 'Competitor Cafe',
            'slug' => 'competitor-cafe',
            'email' => 'owner@competitorcafe.am',
            'password' => bcrypt('Secret123!'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $this->businessPlan->id,
            'is_active' => true,
        ]);

        $ownerB = User::create([
            'name' => 'Owner Bob',
            'email' => 'owner@competitorcafe.am',
            'password' => bcrypt('Secret123!'),
            'vendor_id' => $vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $locationB = Location::create([
            'vendor_id' => $vendorB->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'address' => 'Baghramyan 1',
            'is_active' => true,
            'table_count' => 4,
        ]);

        // Owner B visiting settings index should see Vendor B's data, NOT Vendor A's
        $response = $this->actingAs($ownerB)->get(route('admin.settings.index'));
        $response->assertStatus(200);
        $response->assertSee('Competitor Cafe');
        $response->assertDontSee('Enterprise Bistro');

        // Owner B trying to post settings for Vendor A's location ID should not alter Vendor A
        $postResponse = $this->actingAs($ownerB)->post(route('admin.settings.update'), [
            'location_id' => $this->location->id, // Vendor A's location
            'name' => 'Hacked Vendor Name',
            'phone' => '+37499999999',
        ]);

        $this->vendor->refresh();
        $this->assertNotEquals('Hacked Vendor Name', $this->vendor->name);
        $this->assertEquals('Enterprise Bistro', $this->vendor->name);
    }
}
