<?php

namespace Tests\Feature;

use App\Models\MenuTemplate;
use App\Models\PaymentAttempt;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscriptionAddon;
use App\Services\Payments\Gateways\IdramGateway;
use App\Services\Payments\Gateways\TelcellGateway;
use App\Services\SubscriptionService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAddonsAndSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Vendor $vendor;

    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);

        MenuTemplate::create([
            'name' => 'Default Theme',
            'slug' => 'default-theme',
            'view_path' => 'client.themes.modern',
            'is_active' => true,
        ]);

        $this->plan = SubscriptionPlan::first();

        $this->vendor = Vendor::create([
            'name' => 'Tavern Yerevan',
            'slug' => 'tavern-yerevan',
            'subscription_plan_id' => $this->plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'currency' => 'AMD',
            'email' => 'contact@tavern.am',
            'phone' => '+37410500000',
        ]);

        app(SubscriptionService::class)->getOrCreateForVendor($this->vendor);

        $this->superAdmin = User::create([
            'name' => 'Platform Superadmin',
            'email' => 'superadmin@elab.am',
            'password' => bcrypt('secret123'),
            'role' => 'superadmin',
            'vendor_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_superadmin_can_create_subscription_addon(): void
    {
        $payload = [
            'code' => 'addon.multi_warehouse',
            'addon_type' => 'LIMIT',
            'feature_code' => 'inventory.warehouses.limit',
            'value_increment' => 5,
            'monthly_price' => 5000,
            'yearly_price' => 50000,
            'billing_period' => 'monthly',
            'name_translations' => [
                'hy' => 'Լրացուցիչ Պահեստներ (+5)',
                'en' => 'Additional Warehouses (+5)',
                'ru' => 'Дополнительные склады (+5)',
            ],
            'description_translations' => [
                'hy' => 'Ավելացնում է 5 պահեստի հնարավորություն',
                'en' => 'Adds 5 warehouse slots',
                'ru' => 'Добавляет 5 дополнительных складов',
            ],
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.addons.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subscription_addons', [
            'code' => 'addon.multi_warehouse',
            'name' => 'Լրացուցիչ Պահեստներ (+5)',
            'addon_type' => 'LIMIT',
            'billing_type' => 'monthly',
            'monthly_price' => 5000,
        ]);

        $addon = SubscriptionAddon::where('code', 'addon.multi_warehouse')->first();
        $this->assertNotNull($addon);
        $this->assertEquals(5, $addon->value_increment);
        $this->assertEquals('monthly', $addon->billing_period);
    }

    public function test_superadmin_can_update_subscription_addon(): void
    {
        $addon = SubscriptionAddon::create([
            'code' => 'addon.test_edit',
            'name' => 'Original Name',
            'name_translations' => ['hy' => 'Սկզբնական', 'en' => 'Original', 'ru' => 'Оригинал'],
            'description' => 'Original description',
            'description_translations' => ['hy' => 'Սկզբնական նկարագրություն'],
            'addon_type' => 'USAGE',
            'billing_type' => 'monthly',
            'feature_code' => 'ai.requests.quota',
            'quantity_value' => 500,
            'monthly_price' => 2000,
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $payload = [
            'addon_type' => 'USAGE',
            'feature_code' => 'ai.requests.quota',
            'value_increment' => 1000,
            'monthly_price' => 3500,
            'yearly_price' => 35000,
            'billing_period' => 'yearly',
            'name_translations' => [
                'hy' => 'AI Հարցումներ (+1000)',
                'en' => 'AI Requests (+1000)',
                'ru' => 'AI Запросы (+1000)',
            ],
            'description_translations' => [
                'hy' => 'Թարմացված նկարագրություն',
                'en' => 'Updated description',
                'ru' => 'Обновленное описание',
            ],
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->put(route('superadmin.addons.update', $addon->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $addon->refresh();
        $this->assertEquals('AI Հարցումներ (+1000)', $addon->name);
        $this->assertEquals(1000, $addon->value_increment);
        $this->assertEquals(3500.0, (float) $addon->monthly_price);
        $this->assertEquals('yearly', $addon->billing_period);
    }

    public function test_superadmin_can_delete_subscription_addon(): void
    {
        $addon = SubscriptionAddon::create([
            'code' => 'addon.to_delete',
            'name' => 'To Delete',
            'name_translations' => ['hy' => 'Ջնջվելիք', 'en' => 'To Delete'],
            'addon_type' => 'FEATURE',
            'billing_type' => 'monthly',
            'feature_code' => 'reports.advanced',
            'monthly_price' => 1000,
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.addons.destroy', $addon->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('subscription_addons', [
            'id' => $addon->id,
        ]);
    }

    public function test_superadmin_cannot_delete_addon_when_active_on_vendor(): void
    {
        $addon = SubscriptionAddon::create([
            'code' => 'addon.active_usage',
            'name' => 'Active Addon',
            'addon_type' => 'FEATURE',
            'billing_type' => 'monthly',
            'feature_code' => 'kds.display',
            'monthly_price' => 1500,
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        VendorSubscriptionAddon::create([
            'vendor_id' => $this->vendor->id,
            'subscription_addon_id' => $addon->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'billing_period' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.addons.destroy', $addon->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('subscription_addons', [
            'id' => $addon->id,
        ]);
    }

    public function test_superadmin_can_terminate_and_delete_vendor_subscription(): void
    {
        $this->assertNotNull($this->vendor->subscription);
        $this->assertEquals('active', $this->vendor->subscription_status);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.subscriptions.destroy', $this->vendor->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->vendor->refresh();
        $this->assertEquals('cancelled', $this->vendor->subscription_status);
        $this->assertNull($this->vendor->subscription_plan_id);
        $this->assertNull($this->vendor->subscription);
    }

    public function test_superadmin_can_update_platform_payment_gateway_settings(): void
    {
        $payload = [
            'payment_idram_enabled' => '1',
            'payment_idram_merchant_id' => '119988776',
            'payment_idram_secret_key' => 'secret_idram_key_test',
            'payment_idram_sandbox' => '0',

            'payment_telcell_enabled' => '1',
            'payment_telcell_shop_id' => '554433',
            'payment_telcell_key' => 'telcell_sec_key_test',
            'payment_telcell_sandbox' => '0',

            'payment_fastshift_enabled' => '1',
            'payment_fastshift_merchant_id' => 'FS_MERCHANT_99',
            'payment_fastshift_api_key' => 'fastshift_key_test',
            'payment_fastshift_sandbox' => '0',

            'payment_arca_enabled' => '1',
            'payment_arca_merchant_id' => 'ARCA_MERC_77',
            'payment_arca_terminal_id' => 'TERM_01',
            'payment_arca_username' => 'arca_api_user',
            'payment_arca_password' => 'arca_api_pass',
            'payment_arca_sandbox' => '0',

            'payment_stripe_enabled' => '1',
            'payment_stripe_publishable_key' => 'pk_live_custom123',
            'payment_stripe_secret_key' => 'sk_live_custom123',
            'payment_stripe_webhook_secret' => 'whsec_custom123',
            'payment_stripe_sandbox' => '0',

            'payment_bank_transfer_enabled' => '1',
            'payment_bank_transfer_company_name' => '«ԷԼԱԲ ՏԵԽ» ՍՊԸ',
            'payment_bank_transfer_tin' => '02998877',
            'payment_bank_transfer_bank_name' => '«ԱյԴի Բանկ» ՓԲԸ',
            'payment_bank_transfer_account_number' => '2500012345678900',
            'payment_bank_transfer_notes' => 'Վճարման նպատակ՝ QR Menu տարեկան բաժանորդագրություն',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.settings.payments.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $settings = SystemSetting::getPaymentGatewaySettings();

        $this->assertTrue($settings['idram']['enabled']);
        $this->assertEquals('119988776', $settings['idram']['merchant_id']);
        $this->assertEquals('secret_idram_key_test', $settings['idram']['secret_key']);

        $this->assertTrue($settings['telcell']['enabled']);
        $this->assertEquals('554433', $settings['telcell']['shop_id']);

        $this->assertTrue($settings['bank_transfer']['enabled']);
        $this->assertEquals('«ԷԼԱԲ ՏԵԽ» ՍՊԸ', $settings['bank_transfer']['company_name']);
        $this->assertEquals('2500012345678900', $settings['bank_transfer']['account_number']);
    }

    public function test_platform_payment_gateways_fallback_to_system_settings_for_subscription(): void
    {
        SystemSetting::set('payment_idram_merchant_id', '998877665', 'payment');
        SystemSetting::set('payment_telcell_shop_id', '776655', 'payment');

        $subPayment = SubscriptionPayment::create([
            'vendor_id' => $this->vendor->id,
            'subscription_id' => $this->vendor->subscription->id,
            'subscription_plan_id' => $this->plan->id,
            'amount' => 19900,
            'currency' => 'AMD',
            'payment_method' => 'idram',
            'invoice_number' => 'INV-TEST-001',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'status' => 'pending',
        ]);

        $subscriptionAttempt = PaymentAttempt::create([
            'vendor_id' => $this->vendor->id,
            'subscription_id' => $subPayment->id,
            'gateway' => 'idram',
            'amount' => 19900,
            'currency' => 'AMD',
            'merchant_reference' => 'SUB-REF-12345',
        ]);

        $this->assertTrue($subscriptionAttempt->isSubscriptionPayment());

        // Idram Gateway initiation
        $idramGateway = new IdramGateway;
        $result = $idramGateway->initiatePayment($subscriptionAttempt);

        $this->assertStringContainsString('EDP_REC_ACCOUNT=998877665', $result->redirectUrl);

        // Telcell Gateway initiation
        $telcellAttempt = PaymentAttempt::create([
            'vendor_id' => $this->vendor->id,
            'subscription_id' => $subPayment->id,
            'gateway' => 'telcell',
            'amount' => 19900,
            'currency' => 'AMD',
            'merchant_reference' => 'SUB-REF-TELCELL',
        ]);

        $telcellGateway = new TelcellGateway;
        $telResult = $telcellGateway->initiatePayment($telcellAttempt);

        $this->assertEquals('776655', $telResult->gatewayData['issuer']);
    }

    public function test_vendor_subscription_page_loads_with_valid_alpine_data_and_no_raw_json(): void
    {
        $vendorUser = User::create([
            'name' => 'Vendor Manager',
            'email' => 'manager@tavern.am',
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $addon = SubscriptionAddon::create([
            'code' => 'addon.kds',
            'name' => 'KDS Ekran',
            'name_translations' => ['hy' => 'KDS Էկրան', 'en' => 'KDS Screen'],
            'addon_type' => 'FEATURE',
            'billing_type' => 'monthly',
            'feature_code' => 'kds.active',
            'monthly_price' => 3000,
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $response = $this->actingAs($vendorUser)
            ->get(route('admin.subscription'));

        $response->assertOk();

        // Must not contain unescaped attribute quote collisions like @click="openAddonModal({"
        $response->assertDontSee('@click="openAddonModal({', false);
        // Must contain safe button call openAddonModal(id)
        $response->assertSee("openAddonModal({$addon->id})", false);
    }
}
