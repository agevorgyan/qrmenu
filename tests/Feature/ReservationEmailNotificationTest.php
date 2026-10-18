<?php

namespace Tests\Feature;

use App\Mail\CustomerPreOrderMail;
use App\Mail\CustomerReservationMail;
use App\Mail\RestaurantPreOrderMail;
use App\Mail\RestaurantReservationMail;
use App\Models\EmailLog;
use App\Models\EmailRoutingRule;
use App\Models\Language;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReservationNotificationService;
use Carbon\Carbon;
use Database\Seeders\EmailManagementSeeder;
use Database\Seeders\ReservationEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected ReservationNotificationService $notificationService;

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
            'name' => 'Tavern Yerevan',
            'slug' => 'tavern-yerevan',
            'email' => 'admin@tavernyerevan.am',
            'phone' => '+37410582000',
            'currency' => 'AMD',
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'reservation_notification_settings' => [
                'customer_notifications_enabled' => true,
                'customer_events' => [
                    'reservation_request_received' => true,
                    'reservation_confirmed' => true,
                    'reservation_rejected' => true,
                    'reservation_modified' => true,
                    'reservation_cancelled' => true,
                    'reservation_reminder' => true,
                    'preorder_received' => true,
                    'preorder_confirmed' => true,
                    'preorder_status_changed' => true,
                    'preorder_cancelled' => true,
                ],
                'customer_reminder_intervals' => [1440, 180, 60],
                'customer_reminder_confirmed_only' => true,
                'restaurant_notifications_enabled' => true,
                'restaurant_recipients' => ['manager@tavernyerevan.am', 'reservations@tavernyerevan.am'],
                'restaurant_events' => [
                    'restaurant_reservation_received' => true,
                    'restaurant_reservation_awaiting_confirmation' => true,
                    'restaurant_reservation_action' => true,
                    'restaurant_reservation_modified' => true,
                    'restaurant_preorder_received' => true,
                    'restaurant_preorder_updated' => true,
                    'restaurant_reservation_reminder' => true,
                ],
                'restaurant_reminders_enabled' => true,
                'restaurant_reminder_timing' => 60,
                'restaurant_reminder_confirmed_only' => true,
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
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Amiryan Branch',
            'slug' => 'amiryan-branch',
            'address' => 'Amiryan 5, Yerevan',
            'phone' => '+37410582001',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'locale' => 'hy',
        ]);

        $this->notificationService = app(ReservationNotificationService::class);
    }

    /**
     * 1. Customer reservation confirmation emails.
     */
    public function test_customer_reservation_confirmation_email_is_queued(): void
    {
        Mail::fake();

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Levon Ter-Petrosyan',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '+37491123456',
            'guest_count' => 4,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '19:30:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        $result = $this->notificationService->sendCustomerNotification($reservation, 'reservation_confirmed');

        $this->assertTrue($result);
        Mail::assertQueued(CustomerReservationMail::class, function ($mail) use ($reservation) {
            return $mail->hasTo('customer@example.com')
                && $mail->event === 'reservation_confirmed'
                && $mail->locale === 'en'
                && $mail->reservation->id === $reservation->id;
        });
    }

    /**
     * 2. Restaurant new-reservation notifications to configured multiple recipients.
     */
    public function test_restaurant_new_reservation_notification_sent_to_configured_recipients(): void
    {
        Mail::fake();

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Aram Khachaturian',
            'customer_email' => 'aram@music.am',
            'customer_phone' => '+37493000000',
            'guest_count' => 2,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '20:00:00',
            'status' => Reservation::STATUS_PENDING,
            'locale' => 'en', // Customer chose English
        ]);

        $result = $this->notificationService->sendRestaurantNotification($reservation, 'restaurant_reservation_received');

        $this->assertTrue($result);
        // Configured recipients are: manager@tavernyerevan.am, reservations@tavernyerevan.am
        Mail::assertQueued(RestaurantReservationMail::class, function ($mail) {
            return $mail->hasTo('manager@tavernyerevan.am')
                && $mail->event === 'restaurant_reservation_received'
                && $mail->locale === 'hy';
        });
        Mail::assertQueued(RestaurantReservationMail::class, function ($mail) {
            return $mail->hasTo('reservations@tavernyerevan.am')
                && $mail->event === 'restaurant_reservation_received'
                && $mail->locale === 'hy';
        });
    }

    /**
     * 3. Reservation modification and cancellation notifications.
     */
    public function test_reservation_modification_and_cancellation_notifications(): void
    {
        Mail::fake();

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Sona Rubenyan',
            'customer_email' => 'sona@example.com',
            'customer_phone' => '+37494112233',
            'guest_count' => 3,
            'reservation_date' => '2026-11-22',
            'reservation_time' => '18:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'hy',
        ]);

        // Customer modification
        $this->notificationService->sendCustomerNotification($reservation, 'reservation_modified');
        Mail::assertQueued(CustomerReservationMail::class, fn ($m) => $m->event === 'reservation_modified' && $m->hasTo('sona@example.com'));

        // Restaurant cancellation alert
        $reservation->update(['status' => Reservation::STATUS_CANCELLED]);
        $this->notificationService->sendRestaurantNotification($reservation, 'restaurant_reservation_action', [
            'action_type' => 'Չեղարկված հաճախորդի կողմից',
        ]);
        Mail::assertQueued(RestaurantReservationMail::class, fn ($m) => $m->event === 'restaurant_reservation_action');
    }

    /**
     * 4. Pre-order notifications when enabled.
     */
    public function test_preorder_notifications_dispatched_for_customer_and_staff(): void
    {
        Mail::fake();

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Garik Papoyan',
            'customer_email' => 'garik@example.com',
            'customer_phone' => '+37495556677',
            'guest_count' => 2,
            'reservation_date' => '2026-11-25',
            'reservation_time' => '19:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'hy',
        ]);

        $preOrder = ReservationPreOrder::create([
            'reservation_id' => $reservation->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'confirmed',
            'items' => [
                ['name' => 'Khorovats Pork', 'quantity' => 2, 'unit_price' => 3500, 'total' => 7000],
                ['name' => 'Tan', 'quantity' => 2, 'unit_price' => 500, 'total' => 1000],
            ],
            'total_amount' => 8000,
            'special_instructions' => 'No onions please',
        ]);

        // Customer pre-order confirmation
        $this->notificationService->sendCustomerNotification($reservation, 'preorder_confirmed', [
            'preorder' => $preOrder,
        ]);
        Mail::assertQueued(CustomerPreOrderMail::class, function ($mail) use ($reservation) {
            return $mail->hasTo('garik@example.com')
                && in_array($mail->event, ['confirmed', 'preorder_confirmed'])
                && $mail->reservation->id === $reservation->id;
        });

        // Restaurant staff pre-order notification
        $this->notificationService->sendRestaurantNotification($reservation, 'restaurant_preorder_received', [
            'preorder' => $preOrder,
        ]);
        Mail::assertQueued(RestaurantPreOrderMail::class, function ($mail) use ($reservation) {
            return $mail->reservation->id === $reservation->id;
        });
    }

    /**
     * 5. Correct customer and restaurant email locales separation.
     */
    public function test_strict_locale_separation_between_customer_and_restaurant(): void
    {
        Mail::fake();

        // Russian customer
        $reservationRu = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Ivan Petrov',
            'customer_email' => 'ivan@example.ru',
            'customer_phone' => '+79991234567',
            'guest_count' => 2,
            'reservation_date' => '2026-11-21',
            'reservation_time' => '20:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'ru',
        ]);

        $this->notificationService->sendCustomerNotification($reservationRu, 'reservation_confirmed');
        Mail::assertQueued(CustomerReservationMail::class, function ($mail) {
            return $mail->hasTo('ivan@example.ru') && $mail->locale === 'ru';
        });

        // Restaurant operational mail for same reservation must be in Armenian
        $this->notificationService->sendRestaurantNotification($reservationRu, 'restaurant_reservation_received');
        Mail::assertQueued(RestaurantReservationMail::class, function ($mail) {
            return $mail->locale === 'hy';
        });
    }

    /**
     * 6. Reminder scheduling and delivery at configured intervals.
     */
    public function test_reminder_scheduler_sends_reminders_at_configured_interval(): void
    {
        Mail::fake();

        // In Asia/Yerevan timezone, set reservation in exactly 175 minutes (within the 180 min window +/- 15 min tolerance)
        $targetTime = Carbon::now('Asia/Yerevan')->addMinutes(175);

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Hayk Nahapet',
            'customer_email' => 'hayk@example.com',
            'customer_phone' => '+37491000001',
            'guest_count' => 4,
            'reservation_date' => $targetTime->format('Y-m-d'),
            'reservation_time' => $targetTime->format('H:i:s'),
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        $this->artisan('reservations:send-reminders')
            ->expectsOutputToContain('Reservation Reminders Dispatch Complete')
            ->assertExitCode(0);

        Mail::assertQueued(CustomerReservationMail::class, function ($mail) use ($reservation) {
            return $mail->hasTo('hayk@example.com')
                && in_array($mail->event, ['reminder', 'reservation_reminder'])
                && $mail->reservation->id === $reservation->id;
        });

        $reservation->refresh();
        $this->assertTrue($reservation->hasReminderBeenSent(180, 'customer'));
    }

    /**
     * 7. Multiple reminders per reservation (both 1440 and 60 minutes).
     */
    public function test_multiple_reminders_per_reservation(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Armen Sargsyan',
            'customer_email' => 'armen@example.com',
            'customer_phone' => '+37491000002',
            'guest_count' => 2,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '19:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        $this->assertFalse($reservation->hasReminderBeenSent(1440, 'customer'));
        $this->assertFalse($reservation->hasReminderBeenSent(60, 'customer'));

        $reservation->markReminderSent(1440, 'customer');
        $this->assertTrue($reservation->hasReminderBeenSent(1440, 'customer'));
        $this->assertFalse($reservation->hasReminderBeenSent(60, 'customer'));

        $reservation->markReminderSent(60, 'customer');
        $this->assertTrue($reservation->hasReminderBeenSent(1440, 'customer'));
        $this->assertTrue($reservation->hasReminderBeenSent(60, 'customer'));
    }

    /**
     * 8. Rescheduling after a reservation time change resets future reminders.
     */
    public function test_rescheduling_clears_future_reminders(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Lilit Hovhannisyan',
            'customer_email' => 'lilit@example.com',
            'customer_phone' => '+37491000003',
            'guest_count' => 2,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '14:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'hy',
        ]);

        $reservation->markReminderSent(1440, 'customer');
        $this->assertTrue($reservation->hasReminderBeenSent(1440, 'customer'));

        // Customer reschedules to a date 3 days later
        $reservation->update([
            'reservation_date' => '2026-11-23',
            'reservation_time' => '19:00:00',
        ]);
        $reservation->resetFutureReminders();

        $reservation->refresh();
        $this->assertFalse($reservation->hasReminderBeenSent(1440, 'customer'), 'Rescheduling must clear future sent reminders so they can re-trigger');
    }

    /**
     * 9. Cancelling pending reminders after reservation cancellation.
     */
    public function test_cancelling_reservation_prevents_scheduled_reminders(): void
    {
        Mail::fake();

        $targetTime = Carbon::now('Asia/Yerevan')->addMinutes(60);

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Tigran Mansuryan',
            'customer_email' => 'tigran@example.com',
            'customer_phone' => '+37491000004',
            'guest_count' => 2,
            'reservation_date' => $targetTime->format('Y-m-d'),
            'reservation_time' => $targetTime->format('H:i:s'),
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'hy',
        ]);

        // Customer cancels reservation
        $reservation->update(['status' => Reservation::STATUS_CANCELLED]);

        $this->artisan('reservations:send-reminders')->assertExitCode(0);

        // Reminders must not be sent for cancelled reservations
        Mail::assertNothingQueued();
    }

    /**
     * 10. Preventing duplicate notifications across repeated scheduler runs.
     */
    public function test_preventing_duplicate_reminders_on_repeated_runs(): void
    {
        Mail::fake();

        $targetTime = Carbon::now('Asia/Yerevan')->addMinutes(178);

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Vache Sharafyan',
            'customer_email' => 'vache@example.com',
            'customer_phone' => '+37491000005',
            'guest_count' => 2,
            'reservation_date' => $targetTime->format('Y-m-d'),
            'reservation_time' => $targetTime->format('H:i:s'),
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        // First run dispatches
        $this->artisan('reservations:send-reminders')->assertExitCode(0);
        Mail::assertQueued(CustomerReservationMail::class, 1);

        // Second run immediately after must NOT send a duplicate
        $this->artisan('reservations:send-reminders')->assertExitCode(0);
        Mail::assertQueued(CustomerReservationMail::class, 1);
    }

    /**
     * 11. Bounded retries and queue properties on mailables.
     */
    public function test_mailables_have_bounded_retries_and_exponential_backoff(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Test Retries',
            'customer_email' => 'retry@example.com',
            'guest_count' => 2,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '19:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        $mailable = new CustomerReservationMail($reservation, 'reservation_confirmed', 'en');
        $this->assertEquals(3, $mailable->tries, 'Customer reservation mail must have exactly 3 bounded retries');
        $this->assertEquals([30, 120, 300], $mailable->backoff, 'Must use exponential backoff delay intervals');
    }

    /**
     * 12. Invalid recipient addresses and permanent failures recorded gracefully.
     */
    public function test_invalid_recipient_address_logs_permanent_failure_without_exception(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Invalid Email Customer',
            'customer_email' => 'not-an-email',
            'customer_phone' => '+37491000006',
            'guest_count' => 2,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '19:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        // Attempt sending - must return false and record failed log, not throw unhandled exception
        $result = $this->notificationService->sendCustomerNotification($reservation, 'reservation_confirmed');
        $this->assertFalse($result);

        $log = EmailLog::where('related_id', $reservation->id)
            ->where('recipient_email', 'not-an-email')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(EmailLog::STATUS_FAILED, $log->status);
        $this->assertEquals('INVALID_RECIPIENT', $log->error_code);
    }

    /**
     * 13. Disabled partner notification settings prevent email dispatch.
     */
    public function test_disabled_notification_settings_suppress_dispatch(): void
    {
        Mail::fake();

        // Disable customer notifications for this vendor
        $settings = $this->vendor->reservation_notification_settings;
        $settings['customer_notifications_enabled'] = false;
        $this->vendor->update(['reservation_notification_settings' => $settings]);

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Suppressed Customer',
            'customer_email' => 'suppressed@example.com',
            'guest_count' => 2,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '19:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        $result = $this->notificationService->sendCustomerNotification($reservation, 'reservation_confirmed');
        $this->assertFalse($result);
        Mail::assertNothingQueued();
    }

    /**
     * 14. Super Admin restrictions take precedence and enforce tenant isolation.
     */
    public function test_superadmin_restrictions_take_precedence_over_partner_settings(): void
    {
        Mail::fake();

        // Partner has customer reminders enabled, but Super Admin disables reminders for this tenant
        $restrictions = $this->vendor->superadmin_notification_restrictions;
        $restrictions['customer_reminders_enabled'] = false;
        $restrictions['restaurant_reminders_enabled'] = false;
        $this->vendor->update(['superadmin_notification_restrictions' => $restrictions]);

        $targetTime = Carbon::now('Asia/Yerevan')->addMinutes(60);
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Restricted Tenant Customer',
            'customer_email' => 'restricted@example.com',
            'guest_count' => 2,
            'reservation_date' => $targetTime->format('Y-m-d'),
            'reservation_time' => $targetTime->format('H:i:s'),
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        $this->artisan('reservations:send-reminders')->assertExitCode(0);
        Mail::assertNothingQueued();
    }

    /**
     * 15. Template variable validation and safe rendering.
     */
    public function test_template_variables_interpolated_and_sanitized_safely(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => '<script>alert("XSS")</script>Artur',
            'customer_email' => 'artur@example.com',
            'customer_phone' => '+37499887766',
            'guest_count' => 5,
            'reservation_date' => '2026-12-31',
            'reservation_time' => '21:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'table_name' => 'VIP Booth 7',
            'locale' => 'en',
        ]);

        $mailable = new CustomerReservationMail($reservation, 'reservation_confirmed', 'en');
        $html = $mailable->render();

        $this->assertStringContainsString('Tavern Yerevan', $html);
        $this->assertStringContainsString('VIP Booth 7', $html);
        $this->assertStringContainsString('5', $html);
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $html, 'Untrusted content must be escaped or sanitized');
    }

    /**
     * 16. Missing template language fallback policy.
     */
    public function test_missing_template_language_falls_back_to_available_language(): void
    {
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Francois Dupont',
            'customer_email' => 'francois@example.fr',
            'guest_count' => 2,
            'reservation_date' => '2026-11-20',
            'reservation_time' => '19:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'fr', // French template does not exist
        ]);

        $mailable = new CustomerReservationMail($reservation, 'reservation_confirmed', 'fr');
        $envelope = $mailable->envelope();

        // Must fall back to English or default language without crashing
        $this->assertNotEmpty($envelope->subject);
        $this->assertStringContainsString('Tavern Yerevan', $envelope->subject);
    }

    /**
     * 17. Email category routing for reservations.
     */
    public function test_email_routing_maps_reservations_category(): void
    {
        $categories = EmailRoutingRule::categories();
        $this->assertArrayHasKey(EmailRoutingRule::CATEGORY_RESERVATIONS, $categories);
        $this->assertArrayHasKey(EmailRoutingRule::CATEGORY_PREORDERS, $categories);
    }

    /**
     * 18. Template preview and test email functionality.
     */
    public function test_partner_admin_template_preview_and_test_dispatch(): void
    {
        Mail::fake();

        $this->actingAs($this->owner);

        // Preview endpoint
        $previewResponse = $this->getJson(route('admin.settings.reservations.notifications.preview').'?key=reservation_confirmed&locale=en');
        $previewResponse->assertOk()
            ->assertJsonStructure(['success', 'template' => ['name', 'category', 'subject', 'intro', 'cta_text']]);

        // Test email endpoint
        $testResponse = $this->postJson(route('admin.settings.reservations.notifications.test'), [
            'event' => 'received',
            'recipient' => 'test-owner@example.com',
        ]);

        $testResponse->assertOk()->assertJson(['success' => true]);
        Mail::assertQueued(RestaurantReservationMail::class, function ($mail) {
            return $mail->hasTo('test-owner@example.com');
        });
    }

    /**
     * 19. Partner UI settings validation and persistence.
     */
    public function test_partner_admin_settings_view_and_update(): void
    {
        $this->actingAs($this->owner);

        // View settings page
        $viewResponse = $this->get(route('admin.settings.reservations.notifications'));
        $viewResponse->assertOk()
            ->assertSee('Tavern Yerevan')
            ->assertSee('manager@tavernyerevan.am');

        // Update settings with valid data
        $updateResponse = $this->post(route('admin.settings.reservations.notifications.update'), [
            'customer_notifications_enabled' => '1',
            'customer_events' => [
                'confirmed',
                'cancelled',
            ],
            'customer_reminder_intervals' => '1440, 120',
            'customer_reminder_only_confirmed' => '1',
            'restaurant_notifications_enabled' => '1',
            'restaurant_recipients' => 'new-chef@tavernyerevan.am, owner@tavernyerevan.am',
            'restaurant_events' => [
                'received',
            ],
            'restaurant_reminder_enabled' => '1',
            'restaurant_reminder_intervals' => '45',
            'restaurant_reminder_only_confirmed' => '1',
        ]);

        $updateResponse->assertRedirect()->assertSessionHas('success');

        $this->vendor->refresh();
        $this->assertEquals([1440, 120], $this->vendor->reservation_notification_settings['customer_reminder_intervals']);
        $this->assertEquals([45], $this->vendor->reservation_notification_settings['restaurant_reminder_intervals']);
        $this->assertContains('new-chef@tavernyerevan.am', $this->vendor->reservation_notification_settings['restaurant_recipients']);
        $this->assertContains('owner@tavernyerevan.am', $this->vendor->reservation_notification_settings['restaurant_recipients']);
    }

    /**
     * 20. Timezone correctness and skipping reservations in the past.
     */
    public function test_timezone_correctness_and_skipping_past_reservations(): void
    {
        Mail::fake();

        // A reservation from yesterday in Asia/Yerevan timezone
        $yesterday = Carbon::now('Asia/Yerevan')->subDay();

        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Past Customer',
            'customer_email' => 'past@example.com',
            'guest_count' => 2,
            'reservation_date' => $yesterday->format('Y-m-d'),
            'reservation_time' => '18:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'locale' => 'en',
        ]);

        $this->assertTrue($reservation->isPassed(), 'Reservation from yesterday must report as passed');

        $this->artisan('reservations:send-reminders')->assertExitCode(0);

        // Past reservations must not trigger reminders
        Mail::assertNothingQueued();
    }
}
