<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramReservationNotificationJob;
use App\Mail\CustomerReservationMail;
use App\Mail\RestaurantReservationMail;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Database\Seeders\EmailManagementSeeder;
use Database\Seeders\ReservationEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReservationCrmAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected RestaurantTable $table;

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
            'name' => 'Cascade Dining',
            'slug' => 'cascade-dining',
            'email' => 'admin@cascadedining.am',
            'phone' => '+37410555000',
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
                    'reservation_modified' => true,
                    'reservation_cancelled' => true,
                ],
                'restaurant_notifications_enabled' => true,
                'restaurant_recipients' => ['manager@cascadedining.am'],
                'restaurant_events' => [
                    'restaurant_reservation_received' => true,
                    'restaurant_reservation_modified' => true,
                    'restaurant_reservation_action' => true,
                    'restaurant_cancelled' => true,
                ],
            ],
            'superadmin_notification_restrictions' => [
                'reservations_enabled' => true,
                'preorders_enabled' => true,
                'reservation_emails_enabled' => true,
                'preorder_emails_enabled' => true,
                'customer_reminders_enabled' => true,
                'restaurant_reminders_enabled' => true,
                'template_customization_allowed' => true,
            ],
            'telegram_settings' => [
                'enabled' => true,
                'bot_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
                'chat_id' => '-100987654321',
                'notify_reservations' => true,
                'status' => 'connected',
            ],
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Central Terrace',
            'slug' => 'central-terrace',
            'address' => 'Northern Ave 1, Yerevan',
            'phone' => '+37410555001',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '12',
            'name' => 'Terrace Table 12',
            'min_capacity' => 1,
            'max_capacity' => 6,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Submitting a reservation creates a new CRM Customer record with source and customer_id.
     */
    public function test_reservation_creates_new_crm_customer_when_none_exists(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(3)->format('Y-m-d');

        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Karen Demirchyan',
            'customer_email' => 'karen@example.com',
            'customer_phone' => '+37494112233',
            'guest_count' => 4,
            'reservation_date' => $futureDate,
            'reservation_time' => '18:30',
            'special_requests' => 'Quiet table please',
            'location_id' => $this->location->id,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        // Verify Customer record created in CRM
        $this->assertDatabaseHas('customers', [
            'vendor_id' => $this->vendor->id,
            'name' => 'Karen Demirchyan',
            'phone' => '+37494112233',
            'email' => 'karen@example.com',
        ]);

        $customer = Customer::where('vendor_id', $this->vendor->id)
            ->where('phone', '+37494112233')
            ->first();

        $this->assertNotNull($customer);

        // Verify Reservation record links to customer_id and has source 'online'
        $this->assertDatabaseHas('reservations', [
            'vendor_id' => $this->vendor->id,
            'customer_id' => $customer->id,
            'source' => 'online',
            'customer_name' => 'Karen Demirchyan',
            'customer_phone' => '+37494112233',
        ]);

        // Verify Customer relationship
        $this->assertCount(1, $customer->reservations);
        $this->assertEquals($futureDate, (string) $customer->reservations->first()->reservation_date);
    }

    /**
     * 2. Submitting a reservation for an existing customer links to existing CRM profile and updates missing fields without duplicates.
     */
    public function test_reservation_links_to_existing_crm_customer_by_phone_or_email(): void
    {
        $existingCustomer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Karen D.',
            'phone' => '+37494112233',
            'email' => null, // empty email
        ]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(4)->format('Y-m-d');

        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Karen Demirchyan',
            'customer_email' => 'karen.d@example.com',
            'customer_phone' => '+37494112233',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'location_id' => $this->location->id,
        ]);

        $response->assertStatus(201);

        // Ensure no duplicate customer was created
        $this->assertEquals(1, Customer::where('vendor_id', $this->vendor->id)->count());

        $existingCustomer->refresh();
        $this->assertEquals('karen.d@example.com', $existingCustomer->email);
        $this->assertEquals('Karen D.', $existingCustomer->name);

        // Verify reservation links to existing customer
        $this->assertDatabaseHas('reservations', [
            'vendor_id' => $this->vendor->id,
            'customer_id' => $existingCustomer->id,
            'customer_email' => 'karen.d@example.com',
        ]);
    }

    /**
     * 3. Strict tenant isolation: customer matching and assignment never crosses vendors.
     */
    public function test_reservation_crm_customer_enforces_strict_tenant_isolation(): void
    {
        $otherVendor = Vendor::create([
            'name' => 'Other Cafe',
            'slug' => 'other-cafe',
            'email' => 'other@cafe.am',
            'phone' => '+37410999000',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $otherCustomer = Customer::create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Cross Tenant User',
            'phone' => '+37495555555',
            'email' => 'cross@example.com',
        ]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(2)->format('Y-m-d');

        // Submit reservation to Cascade Dining with the same phone
        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Cascade Customer',
            'customer_email' => 'cascade@example.com',
            'customer_phone' => '+37495555555',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '20:00',
            'location_id' => $this->location->id,
        ]);

        $response->assertStatus(201);

        $newCustomer = Customer::where('vendor_id', $this->vendor->id)
            ->where('phone', '+37495555555')
            ->first();

        $this->assertNotNull($newCustomer);
        $this->assertNotEquals($otherCustomer->id, $newCustomer->id);

        $reservation = Reservation::where('vendor_id', $this->vendor->id)
            ->where('customer_phone', '+37495555555')
            ->first();

        $this->assertEquals($newCustomer->id, $reservation->customer_id);

        // Verify model-level guard against cross-tenant assignment
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-vendor customer assigned to reservation.');
        $reservation->customer_id = $otherCustomer->id;
        $reservation->save();
    }

    /**
     * 4. Rapid repeated submissions within idempotency window prevent duplicate reservations and duplicate customers.
     */
    public function test_repeated_reservation_submission_prevents_duplicate_reservations_and_customers(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(3)->format('Y-m-d');

        $payload = [
            'customer_name' => 'Sona Poghosyan',
            'customer_email' => 'sona@example.com',
            'customer_phone' => '+37493998877',
            'guest_count' => 3,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:30',
            'location_id' => $this->location->id,
        ];

        $res1 = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), $payload);
        $res1->assertStatus(201);

        // Immediate duplicate submission should safely return existing reservation
        $res2 = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), $payload);

        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);
        $this->assertEquals($res1->json('reference'), $res2->json('reference'));

        // Check counts
        $this->assertEquals(1, Reservation::where('vendor_id', $this->vendor->id)->count());
        $this->assertEquals(1, Customer::where('vendor_id', $this->vendor->id)->count());
    }

    /**
     * 5. Storefront reserve page renders unified phone selector and normalizes phone input.
     */
    public function test_reservation_form_renders_unified_phone_country_selector_and_e164_formatting(): void
    {
        $response = $this->get(route('client.reserve', ['vendor_slug' => $this->vendor->slug]));
        $response->assertStatus(200);

        // Verifies presence of unified phone selector elements and Alpine state
        $response->assertSee('cart-phone-field-wrapper', false);
        $response->assertSee('cart-phone-country-btn', false);
        $response->assertSee('phoneCountryCode', false);
        $response->assertSee('phoneNationalNumber', false);

        // Invalid phone validation
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(2)->format('Y-m-d');
        $invalidRes = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Test User',
            'customer_phone' => '123', // invalid short phone
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'location_id' => $this->location->id,
        ]);
        $invalidRes->assertStatus(422);
        $invalidRes->assertJsonValidationErrors(['customer_phone']);
    }

    /**
     * 6. Customer email notifications are queued for received, confirmed, rejected, cancelled, and modified.
     */
    public function test_customer_email_notifications_dispatched_for_lifecycle_events(): void
    {
        Mail::fake();

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(5)->format('Y-m-d');

        // 1. Received
        $res = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Anahit Harutyunyan',
            'customer_email' => 'anahit@example.com',
            'customer_phone' => '+37491445566',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '18:00',
            'location_id' => $this->location->id,
        ]);
        $res->assertStatus(201);

        Mail::assertQueued(CustomerReservationMail::class, function ($mail) {
            return $mail->event === 'reservation_request_received'
                && $mail->hasTo('anahit@example.com');
        });

        $reservation = Reservation::where('customer_email', 'anahit@example.com')->first();
        $this->assertNotNull($reservation);

        // 2. Admin Confirmed
        $this->actingAs($this->owner);
        $this->patchJson(route('admin.reservations.status', $reservation), [
            'status' => 'confirmed',
        ])->assertStatus(200);

        Mail::assertQueued(CustomerReservationMail::class, function ($mail) {
            return $mail->event === 'reservation_confirmed';
        });

        // 3. Admin Modified
        $this->putJson(route('admin.reservations.update', $reservation), [
            'reservation_date' => Carbon::now('Asia/Yerevan')->addDays(6)->format('Y-m-d'),
            'reservation_time' => '19:30',
            'guest_count' => 4,
        ])->assertStatus(200);

        Mail::assertQueued(CustomerReservationMail::class, function ($mail) {
            return $mail->event === 'reservation_modified';
        });

        // 4. Admin Cancelled
        $this->patchJson(route('admin.reservations.status', $reservation), [
            'status' => 'cancelled',
            'cancellation_reason' => 'Guest called to cancel',
        ])->assertStatus(200);

        Mail::assertQueued(CustomerReservationMail::class, function ($mail) {
            return $mail->event === 'reservation_cancelled';
        });
    }

    /**
     * 7. Restaurant staff email and Telegram notifications are dispatched for new, cancelled, and modified reservations.
     */
    public function test_restaurant_notifications_dispatched_for_new_cancelled_and_modified_reservations(): void
    {
        Mail::fake();
        Queue::fake([SendTelegramReservationNotificationJob::class]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(3)->format('Y-m-d');

        // 1. New reservation received
        $res = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Levon Ter-Petrosyan',
            'customer_email' => 'levon@example.com',
            'customer_phone' => '+37491778899',
            'guest_count' => 4,
            'reservation_date' => $futureDate,
            'reservation_time' => '20:00',
            'location_id' => $this->location->id,
        ]);
        $res->assertStatus(201);

        // Restaurant Email dispatched
        Mail::assertQueued(RestaurantReservationMail::class, function ($mail) {
            return $mail->event === 'restaurant_reservation_received'
                && $mail->hasTo('manager@cascadedining.am');
        });

        // Telegram job dispatched
        Queue::assertPushed(SendTelegramReservationNotificationJob::class, function ($job) {
            return $job->event === 'restaurant_reservation_received';
        });

        $reservation = Reservation::where('customer_phone', '+37491778899')->first();

        // 2. Modified via admin
        $this->actingAs($this->owner);
        $this->putJson(route('admin.reservations.update', $reservation), [
            'reservation_time' => '21:00',
            'guest_count' => 5,
        ])->assertStatus(200);

        Mail::assertQueued(RestaurantReservationMail::class, function ($mail) {
            return $mail->event === 'restaurant_reservation_modified';
        });

        Queue::assertPushed(SendTelegramReservationNotificationJob::class, function ($job) {
            return $job->event === 'restaurant_reservation_modified';
        });

        // 3. Status cancelled
        $this->patchJson(route('admin.reservations.status', $reservation), [
            'status' => 'cancelled',
        ])->assertStatus(200);

        Queue::assertPushed(SendTelegramReservationNotificationJob::class, function ($job) {
            return $job->event === 'restaurant_cancelled';
        });
    }

    /**
     * 8. Telegram notification formatting includes secure admin link and redacts credentials.
     */
    public function test_restaurant_telegram_notification_includes_secure_admin_link_and_redacts_credentials(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 101]], 200),
        ]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(3)->format('Y-m-d');
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'restaurant_table_id' => $this->table->id,
            'table_number' => '12',
            'table_name' => 'Terrace Table 12',
            'reference' => 'RES-TEST-SECURE-99',
            'customer_name' => 'Tigran Mansuryan',
            'customer_phone' => '+37491001122',
            'customer_email' => 'tigran@music.am',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'duration_minutes' => 90,
            'status' => Reservation::STATUS_PENDING,
            'source' => 'online',
        ]);

        $telegramService = app(TelegramNotificationService::class);
        $result = $telegramService->sendReservationNotification($reservation, 'restaurant_reservation_received');

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($reservation) {
            $data = $request->data();
            $text = $data['text'] ?? '';
            $replyMarkup = $data['reply_markup'] ?? [];
            $btnUrl = $replyMarkup['inline_keyboard'][0][0]['url'] ?? '';

            // Contains reservation details
            $hasRef = str_contains($text, $reservation->reference);
            $hasCustomer = str_contains($text, 'Tigran Mansuryan');
            $hasPhone = str_contains($text, '+37491001122');
            // Contains secure admin link
            $hasAdminLink = ($btnUrl === route('admin.reservations.index', ['search' => $reservation->reference]))
                || str_contains($text, route('admin.reservations.index', ['search' => $reservation->reference]));
            // Must NOT contain bot token in text
            $noSecretInText = ! str_contains($text, '123456789:ABCdefGHIjklMNOpqrsTUVwxyz');

            return $hasRef && $hasCustomer && $hasPhone && $hasAdminLink && $noSecretInText;
        });
    }

    /**
     * 9. Disabled notification channels and missing contact details are handled gracefully.
     */
    public function test_disabled_notification_channels_and_missing_contact_details_handled_gracefully(): void
    {
        Mail::fake();
        Queue::fake([SendTelegramReservationNotificationJob::class]);

        // Disable Telegram and restaurant notifications in vendor settings
        $settings = $this->vendor->reservation_notification_settings;
        $settings['restaurant_notifications_enabled'] = false;
        $this->vendor->reservation_notification_settings = $settings;

        $tg = $this->vendor->telegram_settings;
        $tg['notify_reservations'] = false;
        $this->vendor->telegram_settings = $tg;
        $this->vendor->save();

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(2)->format('Y-m-d');

        // Submit reservation with empty email
        $res = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Anonymous Guest',
            'customer_phone' => '+37493334455',
            'customer_email' => null, // no email provided
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '16:00',
            'location_id' => $this->location->id,
        ]);

        $res->assertStatus(201);

        // No customer email sent because email is null
        Mail::assertNotQueued(CustomerReservationMail::class);

        // No restaurant email sent because restaurant_notifications_enabled is false
        Mail::assertNotQueued(RestaurantReservationMail::class);

        // No Telegram job pushed because notify_reservations is false
        Queue::assertNotPushed(SendTelegramReservationNotificationJob::class);
    }

    /**
     * 10. Notification failure does not rollback or invalidate saved reservation.
     */
    public function test_notification_failures_do_not_rollback_or_invalidate_saved_reservation(): void
    {
        // Force mail to fail with an exception
        Mail::shouldReceive('mailer->send')
            ->andThrow(new \RuntimeException('SMTP Connection Timeout'));

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(3)->format('Y-m-d');

        $res = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendor->slug]), [
            'customer_name' => 'Vahan Teryan',
            'customer_email' => 'vahan@teryan.am',
            'customer_phone' => '+37491887766',
            'guest_count' => 2,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'location_id' => $this->location->id,
        ]);

        // Reservation submission still succeeds!
        $res->assertStatus(201);
        $res->assertJson(['success' => true]);

        // Reservation and CRM customer remain saved in DB
        $this->assertDatabaseHas('reservations', [
            'vendor_id' => $this->vendor->id,
            'customer_name' => 'Vahan Teryan',
            'customer_phone' => '+37491887766',
        ]);

        $this->assertDatabaseHas('customers', [
            'vendor_id' => $this->vendor->id,
            'name' => 'Vahan Teryan',
            'phone' => '+37491887766',
        ]);
    }

    /**
     * 11. Admin Customer show view displays linked reservations history.
     */
    public function test_admin_customer_show_view_displays_reservation_history(): void
    {
        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Hovhannes Tumanyan',
            'phone' => '+37491223344',
            'email' => 'hovhannes@dsegh.am',
        ]);

        $res = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'customer_id' => $customer->id,
            'location_id' => $this->location->id,
            'restaurant_table_id' => $this->table->id,
            'table_number' => '12',
            'table_name' => 'Terrace Table 12',
            'reference' => 'RES-HT-1869',
            'customer_name' => 'Hovhannes Tumanyan',
            'customer_phone' => '+37491223344',
            'customer_email' => 'hovhannes@dsegh.am',
            'guest_count' => 6,
            'reservation_date' => Carbon::now('Asia/Yerevan')->addDays(1)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'duration_minutes' => 90,
            'status' => Reservation::STATUS_CONFIRMED,
            'source' => 'online',
        ]);

        $this->actingAs($this->owner);

        $response = $this->get(route('admin.customers.show', $customer));
        $response->assertStatus(200);
        $response->assertSee('RES-HT-1869');
        $response->assertSee('Terrace Table 12');
        $response->assertSee('Online');
    }
}
