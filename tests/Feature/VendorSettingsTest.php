<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_vendor_can_view_settings_page(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Bistro Settings Test',
            'slug' => 'bistro-settings',
            'email' => 'settings@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'service_fee_enabled' => true,
            'service_fee_type' => 'percent',
            'service_fee_value' => 10.00,
            'delivery_enabled' => true,
            'delivery_fee' => 1000.00,
            'delivery_min_amount' => 3000.00,
            'delivery_free_from' => 10000.00,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'settings@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertSee(__('Settings'));
        $response->assertSee(__('Service Fee'));
        $response->assertSee(__('Delivery Service Settings'));
        $response->assertSee('10');
        $response->assertSee('1000');
    }

    public function test_vendor_with_multiple_locations_can_view_settings_and_switch_branches(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Multi Branch Test',
            'slug' => 'multi-branch-test',
            'email' => 'multi@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $loc1 = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown',
            'address' => 'Center 1',
        ]);

        $loc2 = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Uptown Branch',
            'slug' => 'uptown',
            'address' => 'North 5',
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'multi@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.settings.index', ['location_id' => $loc2->id]));
        $response->assertStatus(200);
        $response->assertSee(__('Current Branch:'));
        $response->assertSee('Uptown Branch');
        $response->assertSee('Downtown Branch');
    }

    public function test_vendor_can_update_service_fee_and_delivery_settings(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Bistro Update Test',
            'slug' => 'bistro-update',
            'email' => 'update@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'service_fee_enabled' => false,
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_enabled' => false,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'update@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('admin.settings.update'), [
            'service_fee_enabled' => '1',
            'service_fee_type' => 'fixed',
            'service_fee_value' => 750,
            'service_fee_min_order' => 5000,
            'delivery_enabled' => '1',
            'delivery_fee' => 1200,
            'delivery_min_amount' => 4000,
            'delivery_free_from' => 15000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $vendor->refresh();
        $this->assertTrue($vendor->service_fee_enabled);
        $this->assertEquals('fixed', $vendor->service_fee_type);
        $this->assertEquals(750, (float) $vendor->service_fee_value);
        $this->assertEquals(5000, (float) $vendor->service_fee_min_order);
        $this->assertTrue($vendor->delivery_enabled);
        $this->assertEquals(1200, (float) $vendor->delivery_fee);
        $this->assertEquals(4000, (float) $vendor->delivery_min_amount);
        $this->assertEquals(15000, (float) $vendor->delivery_free_from);
    }

    public function test_vendor_settings_validation(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Bistro Validation Test',
            'slug' => 'bistro-validation',
            'email' => 'val@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'val@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('admin.settings.update'), [
            'service_fee_type' => 'invalid_type',
            'service_fee_value' => -5,
            'delivery_fee' => 'invalid',
            'delivery_min_amount' => -100,
        ]);

        $response->assertSessionHasErrors([
            'service_fee_type',
            'service_fee_value',
            'delivery_fee',
            'delivery_min_amount',
        ]);
    }

    public function test_vendor_can_update_branch_info_and_legal_details(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Initial Brand',
            'slug' => 'initial-brand',
            'email' => 'branch@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $location = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Initial Branch',
            'slug' => 'initial-branch',
            'address' => 'Old Address',
            'phone' => '+374 10 000000',
            'whatsapp_number' => '+374 91 000000',
            'table_count' => 15,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'branch@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('admin.settings.update'), [
            'location_id' => $location->id,
            'name' => 'Bistro Downtown Prime',
            'address' => 'Northern Ave 12/4',
            'phone' => '+374 10 998877',
            'whatsapp_number' => '+374 91 556677',
            'wifi_ssid' => 'Prime_Guest_WiFi',
            'wifi_password' => 'PrimePass2026',
            'working_hours' => '09:00 - 01:00 (Ամեն օր)',
            'legal_name' => '«Փրայմ Բիստրո» ՍՊԸ',
            'tax_id' => '01234567',
            'operating_address' => 'ք. Երևան, Հյուսիսային պողոտա 12',
            'director_name' => 'Գևորգ Գևորգյան',
            'director_phone' => '+374 99 112233',
            'contact_person_name' => 'Մարիամ Սարգսյան',
            'contact_person_phone' => '+374 98 445566',
            'service_fee_type' => 'percent',
            'service_fee_value' => 10,
            'delivery_fee' => 1000,
            'delivery_min_amount' => 3000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $vendor->refresh();
        $this->assertEquals('Bistro Downtown Prime', $vendor->name);
        $this->assertEquals('Prime_Guest_WiFi', $vendor->wifi_ssid);
        $this->assertEquals('PrimePass2026', $vendor->wifi_password);
        $this->assertEquals('09:00 - 01:00 (Ամեն օր)', $vendor->working_hours);
        $this->assertEquals('«Փրայմ Բիստրո» ՍՊԸ', $vendor->legal_name);
        $this->assertEquals('01234567', $vendor->tax_id);
        $this->assertEquals('Գևորգ Գևորգյան', $vendor->director_name);
        $this->assertEquals('+374 99 112233', $vendor->director_phone);
        $this->assertEquals('Մարիամ Սարգսյան', $vendor->contact_person_name);
        $this->assertEquals('+374 98 445566', $vendor->contact_person_phone);

        $location->refresh();
        $this->assertEquals('Bistro Downtown Prime', $location->name);
        $this->assertEquals('Northern Ave 12/4', $location->address);
        $this->assertEquals('Prime_Guest_WiFi', $location->wifi_ssid);
        $this->assertEquals('PrimePass2026', $location->wifi_password);
        $this->assertEquals('09:00 - 01:00 (Ամեն օր)', $location->working_hours);

        // Verify storefront displays updated Wi-Fi & working hours
        $storefront = $this->get(route('client.menu', ['vendor_slug' => $vendor->slug]));
        $storefront->assertStatus(200);
        $storefront->assertSee('Prime_Guest_WiFi');
        $storefront->assertSee('PrimePass2026');
        $storefront->assertSee('09:00 - 01:00 (Ամեն օր)');
    }

    public function test_vendor_can_update_regional_settings_timezone_currency_and_measurement_units(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Bistro Regional Test',
            'slug' => 'bistro-regional',
            'email' => 'regional@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'currency' => 'AMD',
            'weight_unit' => 'g',
            'volume_unit' => 'ml',
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'regional@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('admin.settings.update'), [
            'service_fee_type' => 'percent',
            'service_fee_value' => 10,
            'delivery_fee' => 500,
            'delivery_min_amount' => 2000,
            'timezone' => 'Europe/Paris',
            'currency' => 'EUR',
            'weight_unit' => 'kg',
            'volume_unit' => 'l',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $vendor->refresh();
        $this->assertEquals('Europe/Paris', $vendor->timezone);
        $this->assertEquals('EUR', $vendor->currency);
        $this->assertEquals('kg', $vendor->weight_unit);
        $this->assertEquals('l', $vendor->volume_unit);
    }

    public function test_vendor_regional_settings_validation_rejects_invalid_values(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Bistro Invalid Regional Test',
            'slug' => 'bistro-invalid-regional',
            'email' => 'invalid-reg@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'invalid-reg@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('admin.settings.update'), [
            'service_fee_type' => 'percent',
            'service_fee_value' => 10,
            'delivery_fee' => 500,
            'delivery_min_amount' => 2000,
            'timezone' => 'Mars/Olympus_Mons',
            'currency' => 'BITCOIN',
            'weight_unit' => 'metric_ton',
            'volume_unit' => 'barrel',
        ]);

        $response->assertSessionHasErrors([
            'timezone',
            'currency',
            'weight_unit',
            'volume_unit',
        ]);
    }

    public function test_vendor_settings_page_displays_regional_settings_and_selected_options(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Bistro Display Regional Test',
            'slug' => 'bistro-display-regional',
            'email' => 'display-reg@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'weight_unit' => 'oz',
            'volume_unit' => 'fl_oz',
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'display-reg@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertSee('Տարածաշրջանային և տեղայնացման կարգավորումներ');
        $response->assertSee('Ժամային գոտի (Timezone)');
        $response->assertSee('Հիմնական արժույթ');
        $response->assertSee('Քաշի չափման միավոր');
        $response->assertSee('Ծավալի չափման միավոր');
        $response->assertSee('value="America/New_York" selected', false);
        $response->assertSee('value="USD" selected', false);
        $response->assertSee('value="oz" selected', false);
        $response->assertSee('value="fl_oz" selected', false);
    }
}
