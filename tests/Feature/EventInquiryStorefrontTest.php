<?php

namespace Tests\Feature;

use App\Mail\EventInquiryCustomerAcknowledgmentMail;
use App\Mail\EventInquirySubmittedVendorMail;
use App\Models\EventInquiry;
use App\Models\Language;
use App\Models\Location;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventInquiryStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

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

        $this->vendor = Vendor::create([
            'name' => 'Verona Lounge',
            'slug' => 'verona-lounge',
            'email' => 'events@verona.am',
            'phone' => '+37410112233',
            'currency' => 'AMD',
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
            'subscription_plan' => 'pro',
            'reservation_settings' => [
                'enabled' => true,
                'mode' => 'reservation_and_preorder',
            ],
            'event_inquiry_settings' => [
                'enabled' => true,
                'min_guests' => 5,
                'max_guests' => 300,
                'contact_methods' => ['phone', 'whatsapp', 'email'],
                'email_notifications_enabled' => true,
                'notification_recipients' => ['manager@verona.am'],
                'customer_acknowledgment_enabled' => true,
            ],
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall',
            'slug' => 'main-hall',
            'address' => 'Northern Ave 10, Yerevan',
            'phone' => '+37410112233',
            'is_active' => true,
        ]);
    }

    public function test_customer_can_view_event_inquiry_form_when_enabled(): void
    {
        $response = $this->get(route('client.event_inquiry', ['vendor_slug' => $this->vendor->slug, 'lang' => 'hy']));

        $response->assertStatus(200);
        $response->assertSee('Verona Lounge');
        $response->assertSee('«Միջոցառման կազմակերպման հարցում»');
    }

    public function test_customer_cannot_view_form_when_event_inquiries_are_disabled(): void
    {
        $this->vendor->update([
            'event_inquiry_settings' => array_merge($this->vendor->event_inquiry_settings, ['enabled' => false]),
        ]);

        $response = $this->get(route('client.event_inquiry', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(404);
    }

    public function test_customer_can_submit_valid_event_inquiry(): void
    {
        Mail::fake();

        $eventDate = Carbon::now('Asia/Yerevan')->addDays(14)->format('Y-m-d');

        $payload = [
            'event_type' => 'birthday',
            'customer_name' => 'Anahit Sargsyan',
            'customer_phone' => '+37491123456',
            'customer_email' => 'anahit@example.com',
            'preferred_contact_method' => 'phone',
            'event_date' => $eventDate,
            'guest_count' => 25,
            'start_time' => '19:00',
            'end_time' => '23:30',
            'venue_arrangement' => 'private_room',
            'budget' => 250000,
            'additional_services' => ['decoration', 'cake'],
            'food_beverage_preferences' => 'Armenian barbecue and local wines',
            'comments' => 'We will bring our own photographer.',
            'location_id' => $this->location->id,
        ];

        $response = $this->postJson(
            route('client.event_inquiry.submit', ['vendor_slug' => $this->vendor->slug]),
            $payload
        );

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
        ]);
        $response->assertJsonStructure(['reference', 'message', 'redirect_url']);

        $reference = $response->json('reference');
        $this->assertNotEmpty($reference);

        // Verify Database insertion & strict tenant association
        $this->assertDatabaseHas('event_inquiries', [
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'reference' => $reference,
            'event_type' => 'birthday',
            'customer_name' => 'Anahit Sargsyan',
            'customer_phone' => '+37491123456',
            'customer_email' => 'anahit@example.com',
            'guest_count' => 25,
            'budget' => 250000,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        // Verify audit log creation
        $inquiry = EventInquiry::where('reference', $reference)->first();
        $this->assertNotNull($inquiry);
        $this->assertTrue($inquiry->activities()->where('action', 'created')->exists());

        // Verify notification mails queued
        Mail::assertQueued(EventInquirySubmittedVendorMail::class, function ($mail) {
            return $mail->hasTo('manager@verona.am');
        });

        Mail::assertQueued(EventInquiryCustomerAcknowledgmentMail::class, function ($mail) {
            return $mail->hasTo('anahit@example.com');
        });
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $response = $this->postJson(
            route('client.event_inquiry.submit', ['vendor_slug' => $this->vendor->slug]),
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'event_type',
            'customer_name',
            'customer_phone',
            'preferred_contact_method',
            'event_date',
            'guest_count',
        ]);
    }

    public function test_validation_fails_for_past_date(): void
    {
        $pastDate = Carbon::yesterday('Asia/Yerevan')->format('Y-m-d');

        $response = $this->postJson(
            route('client.event_inquiry.submit', ['vendor_slug' => $this->vendor->slug]),
            [
                'event_type' => 'wedding',
                'customer_name' => 'Armen',
                'customer_phone' => '+37499887766',
                'preferred_contact_method' => 'phone',
                'event_date' => $pastDate,
                'guest_count' => 50,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['event_date']);
    }

    public function test_validation_fails_when_guest_count_below_configured_minimum(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(5)->format('Y-m-d');

        $response = $this->postJson(
            route('client.event_inquiry.submit', ['vendor_slug' => $this->vendor->slug]),
            [
                'event_type' => 'wedding',
                'customer_name' => 'Armen',
                'customer_phone' => '+37499887766',
                'preferred_contact_method' => 'phone',
                'event_date' => $futureDate,
                'guest_count' => 2, // Vendor configured min_guests is 5
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['guest_count']);
    }

    public function test_validation_requires_email_when_preferred_contact_method_is_email(): void
    {
        $futureDate = Carbon::now('Asia/Yerevan')->addDays(5)->format('Y-m-d');

        $response = $this->postJson(
            route('client.event_inquiry.submit', ['vendor_slug' => $this->vendor->slug]),
            [
                'event_type' => 'corporate',
                'customer_name' => 'David',
                'customer_phone' => '+37499112233',
                'preferred_contact_method' => 'email',
                'customer_email' => null, // Required because method is email
                'event_date' => $futureDate,
                'guest_count' => 20,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_email']);
    }

    public function test_cannot_submit_when_feature_disabled(): void
    {
        $this->vendor->update([
            'event_inquiry_settings' => array_merge($this->vendor->event_inquiry_settings, ['enabled' => false]),
        ]);

        $futureDate = Carbon::now('Asia/Yerevan')->addDays(5)->format('Y-m-d');

        $response = $this->postJson(
            route('client.event_inquiry.submit', ['vendor_slug' => $this->vendor->slug]),
            [
                'event_type' => 'anniversary',
                'customer_name' => 'Karine',
                'customer_phone' => '+37477112233',
                'preferred_contact_method' => 'phone',
                'event_date' => $futureDate,
                'guest_count' => 15,
            ]
        );

        $response->assertStatus(403);
    }

    public function test_customer_can_view_inquiry_status_by_reference(): void
    {
        $inquiry = EventInquiry::create([
            'vendor_id' => $this->vendor->id,
            'event_type' => 'anniversary',
            'customer_name' => 'Tigran',
            'customer_phone' => '+37499123456',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'guest_count' => 12,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        $response = $this->get(route('client.event_inquiry.status', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $inquiry->reference,
        ]));

        $response->assertStatus(200);
        $response->assertSee($inquiry->reference);
        $response->assertSee('Tigran');
    }
}
