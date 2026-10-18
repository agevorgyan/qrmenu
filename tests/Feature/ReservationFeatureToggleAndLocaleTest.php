<?php

namespace Tests\Feature;

use App\Mail\CustomerReservationMail;
use App\Mail\RestaurantReservationMail;
use App\Models\Category;
use App\Models\Language;
use App\Models\Location;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\RestaurantTable;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReservationNotificationService;
use App\Services\TenantCache;
use Carbon\Carbon;
use Database\Seeders\EmailManagementSeeder;
use Database\Seeders\ReservationEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationFeatureToggleAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $superAdmin;

    protected RestaurantTable $table;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->seed(EmailManagementSeeder::class);
        $this->seed(ReservationEmailTemplateSeeder::class);

        $this->vendor = Vendor::create([
            'name' => 'Republic Lounge',
            'slug' => 'republic-lounge',
            'email' => 'info@republiclounge.am',
            'phone' => '+37410112233',
            'currency' => 'AMD',
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'subscription_status' => 'active',
            'subscription_plan' => 'pro',
            'takeaway_enabled' => true,
            'delivery_enabled' => true,
            'reservation_settings' => [
                'enabled' => true,
                'mode' => Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER,
                'admin_locale' => 'hy',
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
                'cancellation_cutoff_hours' => 2,
            ],
            'superadmin_notification_restrictions' => [
                'permitted_mode' => Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER,
                'reservations_enabled' => true,
                'preorders_enabled' => true,
                'reservation_emails_enabled' => true,
                'preorder_emails_enabled' => true,
                'customer_reminders_enabled' => true,
                'restaurant_reminders_enabled' => true,
                'template_customization_allowed' => true,
            ],
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall',
            'slug' => 'main-hall',
            'table_count' => 10,
        ]);

        $this->owner = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Armen Sargsyan',
            'email' => 'armen@republiclounge.am',
            'role' => 'vendor_owner',
            'locale' => 'hy',
            'email_verified_at' => now(),
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@qrmenu.am',
            'role' => 'superadmin',
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-01',
            'display_name' => 'VIP Table 1',
            'capacity' => 4,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Courses',
            'slug' => 'main-courses',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Traditional Armenian Kebab',
            'slug' => 'armenian-kebab',
            'price' => 3500.00,
            'is_available' => true,
        ]);
    }

    /**
     * Test 1: A restaurant can operate in reservation-only mode.
     */
    public function test_restaurant_can_operate_in_reservation_only_mode(): void
    {
        $settings = $this->vendor->reservation_settings;
        $settings['mode'] = Vendor::RESERVATION_MODE_RESERVATION_ONLY;
        $this->vendor->update(['reservation_settings' => $settings]);

        $this->assertTrue($this->vendor->fresh()->isReservationFeatureEnabled());
        $this->assertFalse($this->vendor->fresh()->isPreOrderFeatureEnabled());

        // Storefront reservation form does not load pre-order category items
        $response = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertViewHas('categories', function ($categories) {
            return $categories->isEmpty();
        });

        // Booking a table without pre-order succeeds
        $date = Carbon::tomorrow()->format('Y-m-d');
        $submitResponse = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Karen Grigoryan',
            'customer_email' => 'karen@example.com',
            'customer_phone' => '+37491112233',
            'guest_count' => 2,
            'reservation_date' => $date,
            'reservation_time' => '14:00',
        ]);

        $submitResponse->assertStatus(201);
        $submitResponse->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('reservations', [
            'vendor_id' => $this->vendor->id,
            'customer_email' => 'karen@example.com',
        ]);

        // Attempting to attach pre-orders in reservation_only mode is rejected
        $failedPreOrderResponse = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Hovik Ghazaryan',
            'customer_email' => 'hovik@example.com',
            'guest_count' => 2,
            'reservation_date' => $date,
            'reservation_time' => '15:00',
            'pre_order_items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]);

        $failedPreOrderResponse->assertStatus(422);
        $failedPreOrderResponse->assertJsonValidationErrors(['pre_order']);
    }

    /**
     * Test 2: A restaurant can operate with reservations and pre-orders together.
     */
    public function test_restaurant_can_operate_with_reservations_and_preorders_together(): void
    {
        $settings = $this->vendor->reservation_settings;
        $settings['mode'] = Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER;
        $this->vendor->update(['reservation_settings' => $settings]);

        $this->assertTrue($this->vendor->fresh()->isReservationFeatureEnabled());
        $this->assertTrue($this->vendor->fresh()->isPreOrderFeatureEnabled());

        $response = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);
        $response->assertViewHas('categories', function ($categories) {
            return $categories->isNotEmpty();
        });

        $date = Carbon::tomorrow()->format('Y-m-d');
        $submitResponse = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Ani Melikyan',
            'customer_email' => 'ani@example.com',
            'guest_count' => 2,
            'reservation_date' => $date,
            'reservation_time' => '18:00',
            'pre_order_items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);

        $submitResponse->assertStatus(201);
        $reservation = Reservation::where('customer_email', 'ani@example.com')->first();
        $this->assertNotNull($reservation);
        $this->assertNotNull($reservation->preOrder);
        $this->assertEquals(7000.00, $reservation->preOrder->total_amount);
    }

    /**
     * Test 3: Both features can be disabled independently where dependencies permit.
     */
    public function test_both_features_can_be_disabled_independently_where_dependencies_permit(): void
    {
        // 1. Mode: disabled
        $this->vendor->update([
            'reservation_settings' => array_merge($this->vendor->reservation_settings, ['mode' => Vendor::RESERVATION_MODE_DISABLED]),
        ]);
        $this->assertFalse($this->vendor->fresh()->isReservationFeatureEnabled());
        $this->assertFalse($this->vendor->fresh()->isPreOrderFeatureEnabled());

        // 2. Mode: reservation_only
        $this->vendor->update([
            'reservation_settings' => array_merge($this->vendor->reservation_settings, ['mode' => Vendor::RESERVATION_MODE_RESERVATION_ONLY]),
        ]);
        $this->assertTrue($this->vendor->fresh()->isReservationFeatureEnabled());
        $this->assertFalse($this->vendor->fresh()->isPreOrderFeatureEnabled());

        // 3. Mode: reservation_and_preorder
        $this->vendor->update([
            'reservation_settings' => array_merge($this->vendor->reservation_settings, ['mode' => Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER]),
        ]);
        $this->assertTrue($this->vendor->fresh()->isReservationFeatureEnabled());
        $this->assertTrue($this->vendor->fresh()->isPreOrderFeatureEnabled());
    }

    /**
     * Test 4: A partner cannot override Super Admin restrictions.
     */
    public function test_partner_cannot_override_super_admin_restrictions(): void
    {
        // Super Admin disables reservations entirely
        $this->vendor->update([
            'superadmin_notification_restrictions' => array_merge($this->vendor->superadmin_notification_restrictions, [
                'permitted_mode' => Vendor::RESERVATION_MODE_DISABLED,
                'reservations_enabled' => false,
                'preorders_enabled' => false,
            ]),
        ]);

        $this->actingAs($this->owner);

        // Partner attempts to set mode to reservation_only
        $response = $this->post(route('admin.settings.reservations.update'), [
            'mode' => Vendor::RESERVATION_MODE_RESERVATION_ONLY,
            'default_duration_minutes' => 90,
            'buffer_minutes' => 15,
            'min_party_size' => 1,
            'max_party_size' => 10,
            'advance_booking_days' => 30,
            'booking_cutoff_minutes' => 60,
            'slot_interval_minutes' => 30,
            'opening_time' => '10:00',
            'closing_time' => '23:00',
            'pre_orders_mode' => 'disabled',
            'cancellation_cutoff_hours' => 2,
        ]);

        $response->assertSessionHasErrors(['mode']);

        // Super Admin permits only reservation_only; partner attempts to enable preorders
        $this->vendor->update([
            'superadmin_notification_restrictions' => array_merge($this->vendor->superadmin_notification_restrictions, [
                'permitted_mode' => Vendor::RESERVATION_MODE_RESERVATION_ONLY,
                'reservations_enabled' => true,
                'preorders_enabled' => false,
            ]),
        ]);

        $response2 = $this->post(route('admin.settings.reservations.update'), [
            'mode' => Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER,
            'default_duration_minutes' => 90,
            'buffer_minutes' => 15,
            'min_party_size' => 1,
            'max_party_size' => 10,
            'advance_booking_days' => 30,
            'booking_cutoff_minutes' => 60,
            'slot_interval_minutes' => 30,
            'opening_time' => '10:00',
            'closing_time' => '23:00',
            'pre_orders_mode' => 'optional',
            'cancellation_cutoff_hours' => 2,
        ]);

        $response2->assertSessionHasErrors(['mode']);
    }

    /**
     * Test 5: Disabling reservations prevents new reservations through both UI and API.
     */
    public function test_disabling_reservations_prevents_new_reservations_through_ui_and_api(): void
    {
        $this->vendor->update([
            'reservation_settings' => array_merge($this->vendor->reservation_settings, ['mode' => Vendor::RESERVATION_MODE_DISABLED]),
        ]);

        // UI route 404s
        $uiResponse = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug]));
        $uiResponse->assertStatus(404);

        // Availability API returns 404 error
        $availResponse = $this->getJson(route('client.reserve.availability', [
            'vendor_slug' => $this->vendor->slug,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'guest_count' => 2,
        ]));
        $availResponse->assertStatus(404);

        // Submit API returns 422 validation error
        $submitResponse = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Tigran Petrosyan',
            'customer_email' => 'tigran@example.com',
            'guest_count' => 2,
            'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
            'reservation_time' => '19:00',
        ]);
        $submitResponse->assertStatus(422);
        $submitResponse->assertJsonValidationErrors(['reservation']);
    }

    /**
     * Test 6: Disabling pre-orders does not disable otherwise permitted reservations.
     */
    public function test_disabling_preorders_does_not_disable_otherwise_permitted_reservations(): void
    {
        $this->vendor->update([
            'reservation_settings' => array_merge($this->vendor->reservation_settings, [
                'mode' => Vendor::RESERVATION_MODE_RESERVATION_ONLY,
                'pre_orders_mode' => 'disabled',
            ]),
        ]);

        $this->assertTrue($this->vendor->fresh()->isReservationFeatureEnabled());
        $this->assertFalse($this->vendor->fresh()->isPreOrderFeatureEnabled());

        $date = Carbon::tomorrow()->format('Y-m-d');
        $submitResponse = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Lilit Hakobyan',
            'customer_email' => 'lilit@example.com',
            'guest_count' => 3,
            'reservation_date' => $date,
            'reservation_time' => '17:00',
        ]);

        $submitResponse->assertStatus(201);
        $this->assertDatabaseHas('reservations', [
            'vendor_id' => $this->vendor->id,
            'customer_email' => 'lilit@example.com',
        ]);
    }

    /**
     * Test 7: Existing historical records are preserved after a feature is disabled.
     */
    public function test_existing_historical_records_are_preserved_after_feature_is_disabled(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'restaurant_table_id' => $this->table->id,
            'table_number' => $this->table->table_number,
            'customer_name' => 'Gagik Tsarukyan',
            'customer_email' => 'gagik@example.com',
            'guest_count' => 4,
            'reservation_date' => Carbon::yesterday()->format('Y-m-d'),
            'reservation_time' => '20:00',
            'duration_minutes' => 90,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $preOrder = ReservationPreOrder::create([
            'vendor_id' => $this->vendor->id,
            'reservation_id' => $reservation->id,
            'items' => [
                ['product_id' => $this->product->id, 'name' => $this->product->name, 'quantity' => 4, 'unit_price' => 3500.00, 'subtotal' => 14000.00],
            ],
            'subtotal' => 14000.00,
            'total_amount' => 14000.00,
            'currency' => 'AMD',
            'status' => ReservationPreOrder::STATUS_PENDING,
        ]);

        // Disable feature
        $this->vendor->update([
            'reservation_settings' => array_merge($this->vendor->reservation_settings, ['mode' => Vendor::RESERVATION_MODE_DISABLED]),
        ]);

        // Historical data is still in database and has not been cancelled or wiped
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => Reservation::STATUS_CONFIRMED]);
        $this->assertDatabaseHas('reservation_pre_orders', ['id' => $preOrder->id, 'total_amount' => 14000.00]);

        // Staff can still view historical reservation in dashboard
        $this->actingAs($this->owner);
        $response = $this->get(route('admin.reservations.index', ['date' => 'all', 'search' => 'Gagik']));
        $response->assertStatus(200);
        $response->assertSee('Gagik Tsarukyan');
    }

    /**
     * Test 8: Customer-facing strings use the selected menu locale.
     */
    public function test_customer_facing_strings_use_selected_menu_locale(): void
    {
        // 1. Russian locale via lang query
        $resRu = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug, 'lang' => 'ru']));
        $resRu->assertStatus(200);
        $this->assertEquals('ru', app()->getLocale());

        // 2. English locale via lang query
        $resEn = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug, 'lang' => 'en']));
        $resEn->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());

        // 3. Submitting in English records customer's locale as English
        $date = Carbon::tomorrow()->format('Y-m-d');
        $submit = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'guest_count' => 2,
            'reservation_date' => $date,
            'reservation_time' => '13:00',
            'locale' => 'en',
        ]);

        $submit->assertStatus(201);
        $reservation = Reservation::where('customer_email', 'john@example.com')->first();
        $this->assertEquals('en', $reservation->locale);

        // 4. Visiting status screen sets app locale to English
        $statusRes = $this->get(route('client.reservation.show', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $reservation->reference,
            'token' => $reservation->uuid,
        ]));
        $statusRes->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
    }

    /**
     * Test 9: Restaurant admin strings use the restaurant's configured admin locale.
     */
    public function test_restaurant_admin_strings_use_restaurant_configured_admin_locale(): void
    {
        // Set partner admin locale to Armenian
        $settings = $this->vendor->reservation_settings;
        $settings['admin_locale'] = 'hy';
        $this->vendor->update(['reservation_settings' => $settings]);

        $this->actingAs($this->owner);
        session()->forget('admin_locale');

        $response = $this->get(route('admin.reservations.index'));
        $response->assertStatus(200);
        $this->assertEquals('hy', app()->getLocale());

        // Switch partner admin locale to Russian
        $this->post(route('admin.settings.reservations.update'), [
            'mode' => Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER,
            'admin_locale' => 'ru',
            'default_duration_minutes' => 90,
            'buffer_minutes' => 15,
            'min_party_size' => 1,
            'max_party_size' => 10,
            'advance_booking_days' => 30,
            'booking_cutoff_minutes' => 60,
            'slot_interval_minutes' => 30,
            'opening_time' => '10:00',
            'closing_time' => '23:00',
            'pre_orders_mode' => 'optional',
            'cancellation_cutoff_hours' => 2,
        ]);

        $this->assertEquals('ru', $this->vendor->fresh()->admin_locale);

        $response2 = $this->get(route('admin.reservations.index'));
        $response2->assertStatus(200);
        $this->assertEquals('ru', app()->getLocale());
    }

    /**
     * Test 10: Customer notification locale remains independent of restaurant admin locale.
     */
    public function test_customer_notification_locale_remains_independent_of_restaurant_admin_locale(): void
    {
        Mail::fake();

        // Customer chooses English
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'restaurant_table_id' => $this->table->id,
            'table_number' => $this->table->table_number,
            'customer_name' => 'Alice Smith',
            'customer_email' => 'alice@example.com',
            'locale' => 'en',
            'guest_count' => 2,
            'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
            'reservation_time' => '19:00',
            'status' => Reservation::STATUS_PENDING,
        ]);

        // Restaurant admin locale is Armenian
        $settings = $this->vendor->reservation_settings;
        $settings['admin_locale'] = 'hy';
        $this->vendor->update(['reservation_settings' => $settings]);
        $this->assertEquals('hy', $this->vendor->fresh()->admin_locale);

        $service = app(ReservationNotificationService::class);

        // 1. Send customer confirmation
        $service->notifyCustomerReservation($reservation, 'confirmed');
        Mail::assertQueued(CustomerReservationMail::class, function ($mail) {
            return $mail->locale === 'en';
        });

        // 2. Send restaurant notification
        $service->notifyRestaurantReservation($reservation, 'received');
        Mail::assertQueued(RestaurantReservationMail::class, function ($mail) {
            return $mail->locale === 'hy';
        });
    }

    /**
     * Test 11: Missing translations follow the existing fallback policy.
     */
    public function test_missing_translations_follow_existing_fallback_policy(): void
    {
        // Un-translated key returns key itself
        $missing = __('completely_untranslated_key_123456');
        $this->assertEquals('completely_untranslated_key_123456', $missing);

        // Product with name fallback
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->product->category_id,
            'name' => 'Lavash Special',
            'slug' => 'lavash-special',
            'price' => 500,
        ]);

        $this->assertEquals('Lavash Special', $product->name);
    }

    /**
     * Test 12: Cross-tenant feature configuration access is rejected.
     */
    public function test_cross_tenant_feature_configuration_access_is_rejected(): void
    {
        $otherVendor = Vendor::create([
            'name' => 'Other Restaurant',
            'slug' => 'other-restaurant',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $otherOwner = User::factory()->create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Other Owner',
            'email' => 'other@restaurant.am',
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Regular partner attempting to access SuperAdmin feature control endpoint gets redirected/forbidden
        $this->actingAs($otherOwner);
        $response = $this->post(route('superadmin.vendors.notifications', $this->vendor->id), [
            'permitted_mode' => 'disabled',
        ]);
        $this->assertTrue(in_array($response->status(), [302, 403], true));

        // Authenticated SuperAdmin CAN update restrictions
        $this->actingAs($this->superAdmin);
        $saResponse = $this->post(route('superadmin.vendors.notifications', $this->vendor->id), [
            'permitted_mode' => 'reservation_only',
            'reservations_enabled' => 1,
            'preorders_enabled' => 0,
        ]);
        $saResponse->assertRedirect();
        $this->assertEquals('reservation_only', $this->vendor->fresh()->getSuperAdminPermittedMode());

        // Verify audit log recorded Super Admin change
        $this->assertDatabaseHas('security_audit_logs', [
            'vendor_id' => $this->vendor->id,
            'event' => SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
        ]);
    }

    /**
     * Test 13: Cached configuration and queued jobs respect feature changes.
     */
    public function test_cached_configuration_and_queued_jobs_respect_feature_changes(): void
    {
        // 1. Pre-warm cache
        TenantCache::put($this->vendor, 'config', ['status' => 'warm'], 3600);
        $this->assertNotNull(TenantCache::get($this->vendor, 'config'));

        // 2. Super Admin updates restrictions -> invalidates cache
        $this->actingAs($this->superAdmin);
        $this->post(route('superadmin.vendors.notifications', $this->vendor->id), [
            'permitted_mode' => 'disabled',
            'reservations_enabled' => 0,
            'preorders_enabled' => 0,
        ]);

        // Cache was flushed
        $this->assertNull(TenantCache::get($this->vendor, 'config'));

        // 3. Queued notification checks respect disabled feature immediately
        $this->assertFalse($this->vendor->fresh()->isReservationNotificationAllowed('request_received', 'customer'));
        $this->assertFalse($this->vendor->fresh()->isReservationNotificationAllowed('preorder_received', 'customer'));
    }

    /**
     * Test 14: Existing independent online ordering continues to function.
     */
    public function test_existing_independent_online_ordering_continues_to_function(): void
    {
        // Disable table reservations completely
        $this->vendor->update([
            'reservation_settings' => array_merge($this->vendor->reservation_settings, ['mode' => Vendor::RESERVATION_MODE_DISABLED]),
        ]);

        $this->assertFalse($this->vendor->fresh()->isReservationFeatureEnabled());

        // Independent takeaway and delivery ordering attributes remain intact
        $this->assertTrue((bool) $this->vendor->fresh()->takeaway_enabled);
        $this->assertTrue((bool) $this->vendor->fresh()->delivery_enabled);
        $this->assertTrue($this->vendor->fresh()->hasFeature('orders'));

        // Public menu view for regular browsing remains accessible (status 200)
        $menuResponse = $this->get("/m/{$this->vendor->slug}");
        $menuResponse->assertStatus(200);
    }
}
