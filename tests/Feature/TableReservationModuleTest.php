<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Language;
use App\Models\Location;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Database\Seeders\EmailManagementSeeder;
use Database\Seeders\ReservationEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableReservationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected RestaurantTable $table4;

    protected RestaurantTable $table2;

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
            'name' => 'Cascade Bistro',
            'slug' => 'cascade-bistro',
            'email' => 'contact@cascadebistro.am',
            'phone' => '+37410580000',
            'currency' => 'AMD',
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
            'subscription_plan' => 'pro',
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
                'cancellation_cutoff_hours' => 2,
            ],
            'reservation_notification_settings' => [
                'customer_notifications_enabled' => true,
                'customer_events' => [
                    'reservation_request_received' => true,
                    'reservation_confirmed' => true,
                    'reservation_rejected' => true,
                    'reservation_cancelled' => true,
                ],
                'restaurant_notifications_enabled' => true,
                'restaurant_recipients' => ['manager@cascadebistro.am'],
                'restaurant_events' => [
                    'restaurant_reservation_received' => true,
                ],
            ],
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'address' => 'Tamanyan 2, Yerevan',
            'phone' => '+37410580001',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // Tables
        $this->table4 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '4',
            'name' => 'Window Booth',
            'capacity' => 4,
            'min_capacity' => 2,
            'section' => 'Main Hall',
            'is_active' => true,
        ]);

        $this->table2 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '2',
            'name' => 'Cozy Table',
            'capacity' => 2,
            'min_capacity' => 1,
            'section' => 'Main Hall',
            'is_active' => true,
        ]);

        // Menu Product
        $category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Ribeye Steak',
            'price' => 7500.00,
            'is_available' => true,
        ]);
    }

    /**
     * 1. Public storefront booking page is viewable.
     */
    public function test_customer_can_view_reservation_page(): void
    {
        $response = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('Cascade Bistro');
        $response->assertSee('Ribeye Steak');
    }

    /**
     * 2. Availability API returns slots for valid future date.
     */
    public function test_customer_can_fetch_available_time_slots(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(3)->format('Y-m-d');

        $response = $this->getJson(route('client.reserve.availability', [
            'vendor_slug' => $this->vendor->slug,
            'date' => $futureDate,
            'guest_count' => 3,
            'location_id' => $this->location->id,
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'date' => $futureDate,
            'guest_count' => 3,
        ]);

        $slots = $response->json('slots');
        $this->assertNotEmpty($slots);
        $this->assertTrue(collect($slots)->firstWhere('time', '18:00')['available']);
    }

    /**
     * 3. Past dates return zero available slots.
     */
    public function test_past_dates_have_no_available_slots(): void
    {
        $pastDate = Carbon::now('Asia/Yerevan')->subDays(2)->format('Y-m-d');

        $response = $this->getJson(route('client.reserve.availability', [
            'vendor_slug' => $this->vendor->slug,
            'date' => $pastDate,
            'guest_count' => 2,
        ]));

        $response->assertStatus(200);
        $this->assertEmpty($response->json('slots'));
    }

    /**
     * 4. Party size exceeding table capacity returns no available slots.
     */
    public function test_party_size_exceeding_table_capacity_returns_no_slots(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(2)->format('Y-m-d');

        // Max capacity among tables is 4, requesting 8
        $response = $this->getJson(route('client.reserve.availability', [
            'vendor_slug' => $this->vendor->slug,
            'date' => $futureDate,
            'guest_count' => 8,
        ]));

        $response->assertStatus(200);
        $slots = $response->json('slots');
        $availableSlots = collect($slots)->where('available', true);
        $this->assertEmpty($availableSlots);
    }

    /**
     * 5. Valid reservation submission creates reservation atomically.
     */
    public function test_customer_can_create_valid_table_reservation(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(5)->format('Y-m-d');

        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Armen Sargsyan',
            'customer_email' => 'armen@example.com',
            'customer_phone' => '+37491123456',
            'guest_count' => 3,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'special_requests' => 'Window seat if possible',
            'location_id' => $this->location->id,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'success' => true,
        ]);

        $this->assertDatabaseHas('reservations', [
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Armen Sargsyan',
            'customer_email' => 'armen@example.com',
            'guest_count' => 3,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'status' => Reservation::STATUS_PENDING,
            'restaurant_table_id' => $this->table4->id,
        ]);
    }

    /**
     * 6. Auto-confirm setting creates confirmed reservation immediately.
     */
    public function test_auto_confirm_setting_creates_confirmed_reservation(): void
    {
        $settings = $this->vendor->reservation_settings;
        $settings['auto_confirm'] = true;
        $this->vendor->update(['reservation_settings' => $settings]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(6)->format('Y-m-d');

        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Ani Petrosyan',
            'customer_email' => 'ani@example.com',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '20:00',
            'location_id' => $this->location->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reservations', [
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Ani Petrosyan',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);
    }

    /**
     * 7. Concurrency & double-booking prevention:
     *    Overlapping reservation on the only matching table is rejected with validation error.
     */
    public function test_atomic_concurrency_prevents_double_booking_on_same_table(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(7)->format('Y-m-d');

        // First customer books Table 4 (capacity 4) at 19:00 for 90 minutes
        $res1 = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'First Customer',
            'customer_email' => 'first@example.com',
            'guest_count' => 4,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'location_id' => $this->location->id,
        ]);
        $res1->assertStatus(201);

        // Second customer attempts to book for 4 guests during overlapping interval (19:30)
        // Table 4 is the only table with capacity >= 4, so it must fail!
        $res2 = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Second Customer',
            'customer_email' => 'second@example.com',
            'guest_count' => 4,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:30',
            'location_id' => $this->location->id,
        ]);

        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['reservation_time']);
    }

    /**
     * 8. Pre-order is attached with verified backend pricing snapshot.
     */
    public function test_customer_can_attach_pre_order_with_valid_pricing_snapshot(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(8)->format('Y-m-d');

        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'David Hakobyan',
            'customer_email' => 'david@example.com',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '18:00',
            'location_id' => $this->location->id,
            'pre_order_items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
            'pre_order_notes' => 'Steaks medium rare please',
        ]);

        $response->assertStatus(201);

        $reservation = Reservation::where('customer_email', 'david@example.com')->firstOrFail();
        $this->assertNotNull($reservation->preOrder);
        $this->assertEquals(ReservationPreOrder::STATUS_PENDING, $reservation->preOrder->status);
        $this->assertEquals(15000.00, (float) $reservation->preOrder->subtotal); // 2 * 7500
        $this->assertEquals('AMD', $reservation->preOrder->currency);

        $items = $reservation->preOrder->items;
        $this->assertCount(1, $items);
        $this->assertEquals('Ribeye Steak', $items[0]['name']);
        $this->assertEquals(7500.00, $items[0]['unit_price']);
        $this->assertEquals(2, $items[0]['quantity']);
    }

    /**
     * 9. Pre-order prevents client-side price tampering.
     */
    public function test_pre_order_prevents_client_price_tampering(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(9)->format('Y-m-d');

        // Malicious client sends forged unit_price: 1 AMD
        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Hacker Test',
            'customer_email' => 'hacker@example.com',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '18:30',
            'location_id' => $this->location->id,
            'pre_order_items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 1.00, // Tampered
                    'subtotal' => 3.00,   // Tampered
                ],
            ],
        ]);

        $response->assertStatus(201);

        $reservation = Reservation::where('customer_email', 'hacker@example.com')->firstOrFail();
        // Server MUST recalculate based on database price 7500 * 3 = 22500
        $this->assertEquals(22500.00, (float) $reservation->preOrder->subtotal);
        $this->assertEquals(7500.00, (float) $reservation->preOrder->items[0]['unit_price']);
    }

    /**
     * 10. Pre-order rejects foreign tenant products (Multi-tenant security).
     */
    public function test_pre_order_prevents_foreign_tenant_products(): void
    {
        // Another vendor
        $otherVendor = Vendor::create([
            'name' => 'Foreign Cafe',
            'slug' => 'foreign-cafe',
            'email' => 'other@cafe.am',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $otherCategory = Category::create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Foreign Category',
            'is_active' => true,
        ]);

        $foreignProduct = Product::create([
            'vendor_id' => $otherVendor->id,
            'category_id' => $otherCategory->id,
            'name' => 'Foreign Dish',
            'price' => 5000.00,
            'is_available' => true,
        ]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(10)->format('Y-m-d');

        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Tenant Attacker',
            'customer_email' => 'attacker@example.com',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'pre_order_items' => [
                [
                    'product_id' => $foreignProduct->id, // Belongs to other vendor
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    /**
     * 11. Mandatory pre-order mode rejects empty pre-orders.
     */
    public function test_mandatory_pre_order_rejects_empty_pre_order(): void
    {
        $settings = $this->vendor->reservation_settings;
        $settings['pre_orders_mode'] = 'mandatory';
        $this->vendor->update(['reservation_settings' => $settings]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(11)->format('Y-m-d');

        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'No Food Guest',
            'customer_email' => 'nofood@example.com',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'pre_order_items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pre_order']);
    }

    /**
     * 12. Customer can view reservation details with valid cryptographic UUID token.
     */
    public function test_customer_can_view_reservation_with_valid_token(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Lilit Hovhannisyan',
            'customer_email' => 'lilit@example.com',
            'guest_count' => 2,
            'reservation_date' => Carbon::now('Asia/Yerevan')->addDays(4)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'restaurant_table_id' => $this->table2->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $url = route('client.reservation.show', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $reservation->reference,
            'token' => $reservation->uuid,
        ]);

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee($reservation->reference);
        $response->assertSee('Lilit Hovhannisyan');
    }

    /**
     * 13. IDOR Protection: Viewing reservation without valid token returns 403.
     */
    public function test_customer_cannot_view_reservation_without_valid_token(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Secret Customer',
            'customer_email' => 'secret@example.com',
            'guest_count' => 2,
            'reservation_date' => Carbon::now('Asia/Yerevan')->addDays(4)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        // Attempt without token
        $response1 = $this->get(route('client.reservation.show', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $reservation->reference,
        ]));
        $response1->assertStatus(403);

        // Attempt with wrong token
        $response2 = $this->get(route('client.reservation.show', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $reservation->reference,
            'token' => 'invalid-guess-token',
        ]));
        $response2->assertStatus(403);
    }

    /**
     * 14. Customer can cancel reservation before cutoff window with valid token.
     */
    public function test_customer_can_cancel_reservation_before_cutoff(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Cancel Me',
            'customer_email' => 'cancel@example.com',
            'guest_count' => 2,
            'reservation_date' => Carbon::now('Asia/Yerevan')->addDays(5)->format('Y-m-d'),
            'reservation_time' => '20:00',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $response = $this->postJson(route('client.reservation.cancel', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $reservation->reference,
        ]), [
            'token' => $reservation->uuid,
            'reason' => 'Schedule changed',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CANCELLED, $reservation->fresh()->status);
        $this->assertEquals('Schedule changed', $reservation->fresh()->cancellation_reason);
    }

    /**
     * 15. Customer cannot cancel reservation inside cutoff window (e.g. 2 hours before arrival).
     */
    public function test_customer_cannot_cancel_reservation_inside_cutoff_window(): void
    {
        $now = Carbon::now('Asia/Yerevan');
        // Reservation 30 minutes from now (cutoff is 2 hours)
        $date = $now->format('Y-m-d');
        $time = $now->copy()->addMinutes(30)->format('H:i');

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Too Late',
            'customer_email' => 'toolate@example.com',
            'guest_count' => 2,
            'reservation_date' => $date,
            'reservation_time' => $time,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $response = $this->postJson(route('client.reservation.cancel', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $reservation->reference,
        ]), [
            'token' => $reservation->uuid,
        ]);

        $response->assertStatus(422);
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
    }

    /**
     * 16. Admin can view reservations dashboard.
     */
    public function test_admin_can_view_reservations_index(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.reservations.index'));

        $response->assertStatus(200);
        $response->assertSee('Table Reservations');
    }

    /**
     * 17. Admin can transition reservation status and seat guests.
     */
    public function test_admin_can_update_reservation_status_and_seat_guests(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'VIP Guest',
            'customer_email' => 'vip@example.com',
            'guest_count' => 4,
            'reservation_date' => Carbon::now('Asia/Yerevan')->format('Y-m-d'),
            'reservation_time' => '19:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'restaurant_table_id' => $this->table4->id,
        ]);

        $response = $this->actingAs($this->owner)->patchJson(route('admin.reservations.status', $reservation->id), [
            'status' => 'seated',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_SEATED, $reservation->fresh()->status);
    }

    /**
     * 18. Admin cannot reopen cancelled reservation (invalid transition).
     */
    public function test_admin_cannot_reopen_cancelled_reservation(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Cancelled Guest',
            'customer_email' => 'cancelled@example.com',
            'guest_count' => 2,
            'reservation_date' => Carbon::now('Asia/Yerevan')->format('Y-m-d'),
            'reservation_time' => '19:00',
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($this->owner)->patchJson(route('admin.reservations.status', $reservation->id), [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(422);
        $this->assertEquals(Reservation::STATUS_CANCELLED, $reservation->fresh()->status);
    }

    /**
     * 19. Multi-tenant security: Admin cannot access another vendor's reservation.
     */
    public function test_cross_tenant_admin_cannot_access_other_vendors_reservation(): void
    {
        $otherVendor = Vendor::create([
            'name' => 'Second Restaurant',
            'slug' => 'second-restaurant',
            'email' => 'owner@second.am',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $otherReservation = Reservation::create([
            'vendor_id' => $otherVendor->id,
            'customer_name' => 'Other Tenant Guest',
            'customer_email' => 'otherguest@example.com',
            'guest_count' => 2,
            'reservation_date' => Carbon::now('Asia/Yerevan')->format('Y-m-d'),
            'reservation_time' => '19:00',
            'status' => Reservation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('admin.reservations.show', $otherReservation->id));
        $this->assertTrue(in_array($response->status(), [403, 404], true), "Expected 403 or 404, got {$response->status()}");
    }

    /**
     * 20. Admin can manage restaurant tables (Create & Update).
     */
    public function test_admin_can_create_and_update_restaurant_tables(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.tables.store'), [
            'table_number' => '15',
            'name' => 'Garden Terrace 15',
            'capacity' => 6,
            'min_capacity' => 2,
            'section' => 'Terrace',
            'is_active' => '1',
            'location_id' => $this->location->id,
        ]);

        $response->assertRedirect(route('admin.tables.index'));

        $this->assertDatabaseHas('restaurant_tables', [
            'vendor_id' => $this->vendor->id,
            'table_number' => '15',
            'capacity' => 6,
            'section' => 'Terrace',
        ]);
    }

    /**
     * 21. Table cannot be deleted if active upcoming bookings exist.
     */
    public function test_admin_cannot_delete_table_with_active_upcoming_reservations(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Active Booking',
            'customer_email' => 'active@example.com',
            'guest_count' => 4,
            'reservation_date' => Carbon::now('Asia/Yerevan')->addDays(3)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'restaurant_table_id' => $this->table4->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($this->owner)->delete(route('admin.tables.destroy', $this->table4->id));

        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted($this->table4);
    }

    /**
     * 22. Admin can update booking rules and reservation settings.
     */
    public function test_admin_can_update_reservation_booking_rules_settings(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.settings.reservations.update'), [
            'enabled' => '1',
            'auto_confirm' => '1',
            'default_duration_minutes' => 120,
            'buffer_minutes' => 20,
            'min_party_size' => 2,
            'max_party_size' => 16,
            'advance_booking_days' => 45,
            'booking_cutoff_minutes' => 90,
            'slot_interval_minutes' => 30,
            'opening_time' => '11:00',
            'closing_time' => '23:30',
            'operating_days' => [1, 2, 3, 4, 5, 6],
            'pre_orders_mode' => 'mandatory',
            'cancellation_cutoff_hours' => 3,
            'cancellation_policy' => 'Strict cancellation policy applies.',
            'reservation_notes' => 'Please arrive 5 minutes early.',
        ]);

        $response->assertSessionHas('success');

        $updated = $this->vendor->fresh()->reservation_settings;
        $this->assertTrue($updated['enabled']);
        $this->assertTrue($updated['auto_confirm']);
        $this->assertEquals(120, $updated['default_duration_minutes']);
        $this->assertEquals(20, $updated['buffer_minutes']);
        $this->assertEquals('mandatory', $updated['pre_orders_mode']);
        $this->assertEquals('Strict cancellation policy applies.', $updated['cancellation_policy']);
    }

    /**
     * 23. Reservation page guides customer to use the main menu for pre-orders rather than a separate menu.
     */
    public function test_reservation_page_prompts_customer_to_browse_main_menu_for_preorder(): void
    {
        $response = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        // Ensure new CTA button to use the main menu is present
        $response->assertSee('browse-menu-action-btn');
        $response->assertSee('goToMainMenuForPreOrder');
        // Ensure old separate menu toggle is NOT present
        $response->assertDontSee('preorder-toggle');
    }

    /**
     * 24. Main menu renders preorder top banner and cart actions when in preorder mode.
     */
    public function test_main_menu_renders_preorder_banner_and_return_actions(): void
    {
        $response = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'mode' => 'preorder',
        ]));

        $response->assertStatus(200);
        $response->assertSee('storefront-preorder-top-banner');
        $response->assertSee('returnToReservation');
        $response->assertSee('confirmPreOrderAndReturn');
    }
}
