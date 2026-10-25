<?php

namespace Tests\Feature;

use App\Models\EventInquiry;
use App\Models\EventInquiryActivity;
use App\Models\Language;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventInquiryAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $staffA;

    protected User $managerA;

    protected Vendor $vendorB;

    protected User $staffB;

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

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Verona Lounge',
            'slug' => 'verona-lounge',
            'email' => 'events@verona.am',
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
            'subscription_plan' => 'pro',
            'reservation_settings' => ['enabled' => true, 'mode' => 'reservation_and_preorder'],
            'event_inquiry_settings' => ['enabled' => true],
        ]);

        $this->staffA = User::create([
            'name' => 'Armen Staff A',
            'email' => 'staff_a@verona.am',
            'password' => bcrypt('password'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'custom_permissions' => [
                'granted' => [
                    Permission::ORDERS_VIEW,
                    Permission::ORDERS_UPDATE,
                    Permission::ORDERS_CANCEL,
                    Permission::SETTINGS_VIEW,
                    Permission::SETTINGS_MANAGE,
                ],
            ],
        ]);

        $this->managerA = User::create([
            'name' => 'Nare Manager A',
            'email' => 'nare@verona.am',
            'password' => bcrypt('password'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'manager',
            'custom_permissions' => [
                Permission::ORDERS_VIEW,
                Permission::ORDERS_UPDATE,
            ],
        ]);

        // Vendor B (Attacker / Unrelated Tenant)
        $this->vendorB = Vendor::create([
            'name' => 'Bistro Yerevan',
            'slug' => 'bistro-yerevan',
            'email' => 'contact@bistro.am',
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
            'subscription_plan' => 'pro',
            'reservation_settings' => ['enabled' => true, 'mode' => 'reservation_and_preorder'],
            'event_inquiry_settings' => ['enabled' => true],
        ]);

        $this->staffB = User::create([
            'name' => 'Tigran Staff B',
            'email' => 'staff_b@bistro.am',
            'password' => bcrypt('password'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'staff',
            'custom_permissions' => [
                Permission::ORDERS_VIEW,
                Permission::ORDERS_UPDATE,
                Permission::ORDERS_CANCEL,
            ],
        ]);
    }

    public function test_authorized_staff_can_view_event_inquiries_index(): void
    {
        EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'birthday',
            'customer_name' => 'Arthur Vardanyan',
            'customer_phone' => '+37491998877',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'guest_count' => 30,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        $response = $this->actingAs($this->staffA)->get(route('admin.event_inquiries.index'));

        $response->assertStatus(200);
        $response->assertSee('Arthur Vardanyan');
        $response->assertSee('Verona Lounge');
    }

    public function test_strict_multi_tenant_isolation_prevents_viewing_other_vendor_inquiries(): void
    {
        $inquiryA = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'wedding',
            'customer_name' => 'Private Client A',
            'customer_phone' => '+37491111111',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
            'guest_count' => 100,
            'status' => EventInquiry::STATUS_CONFIRMED,
        ]);

        // Staff B from Vendor B tries to view inquiry of Vendor A
        $response = $this->actingAs($this->staffB)->get(route('admin.event_inquiries.show', $inquiryA));

        // Must be rejected by BelongsToVendor scope (404) or EventInquiryPolicy (403)
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_strict_multi_tenant_isolation_prevents_updating_other_vendor_inquiry_status(): void
    {
        $inquiryA = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'corporate',
            'customer_name' => 'Client A',
            'customer_phone' => '+37491111111',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'guest_count' => 40,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        $response = $this->actingAs($this->staffB)->patch(
            route('admin.event_inquiries.status', $inquiryA),
            ['status' => EventInquiry::STATUS_CONFIRMED]
        );

        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $this->assertEquals(EventInquiry::STATUS_NEW, $inquiryA->fresh()->status);
    }

    public function test_staff_can_update_status_and_audit_activity_is_logged(): void
    {
        $inquiry = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'birthday',
            'customer_name' => 'Karen',
            'customer_phone' => '+37494112233',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'guest_count' => 15,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        $response = $this->actingAs($this->staffA)->patch(
            route('admin.event_inquiries.status', $inquiry),
            [
                'status' => EventInquiry::STATUS_CONTACTED,
            ]
        );

        $response->assertRedirect();
        $this->assertEquals(EventInquiry::STATUS_CONTACTED, $inquiry->fresh()->status);

        // Check audit activity
        $this->assertTrue(
            EventInquiryActivity::where('event_inquiry_id', $inquiry->id)
                ->where('action', 'status_updated')
                ->where('metadata->new_status', EventInquiry::STATUS_CONTACTED)
                ->exists()
        );
    }

    public function test_declined_status_records_cancellation_reason(): void
    {
        $inquiry = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'wedding',
            'customer_name' => 'Gevorg',
            'customer_phone' => '+37494556677',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            'guest_count' => 150,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        $response = $this->actingAs($this->staffA)->patch(
            route('admin.event_inquiries.status', $inquiry),
            [
                'status' => EventInquiry::STATUS_DECLINED,
                'cancellation_reason' => 'Hall already booked by another corporate party',
            ]
        );

        $response->assertRedirect();
        $fresh = $inquiry->fresh();
        $this->assertEquals(EventInquiry::STATUS_DECLINED, $fresh->status);
        $this->assertEquals('Hall already booked by another corporate party', $fresh->cancellation_reason);
    }

    public function test_staff_can_add_internal_note(): void
    {
        $inquiry = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'anniversary',
            'customer_name' => 'Lilit',
            'customer_phone' => '+37493112233',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(8)->format('Y-m-d'),
            'guest_count' => 20,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        $response = $this->actingAs($this->staffA)->post(
            route('admin.event_inquiries.notes.store', $inquiry),
            ['note' => 'Spoke on the phone. Customer requested Armenian live music quote.']
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('event_inquiry_notes', [
            'vendor_id' => $this->vendorA->id,
            'event_inquiry_id' => $inquiry->id,
            'user_id' => $this->staffA->id,
            'note' => 'Spoke on the phone. Customer requested Armenian live music quote.',
        ]);

        $this->assertTrue(
            EventInquiryActivity::where('event_inquiry_id', $inquiry->id)
                ->where('action', 'note_added')
                ->exists()
        );
    }

    public function test_staff_can_assign_inquiry_to_vendor_team_member(): void
    {
        $inquiry = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'corporate',
            'customer_name' => 'Enterprise Client',
            'customer_phone' => '+37410998877',
            'preferred_contact_method' => 'email',
            'event_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'guest_count' => 60,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        $response = $this->actingAs($this->staffA)->patch(
            route('admin.event_inquiries.assign', $inquiry),
            ['assigned_user_id' => $this->managerA->id]
        );

        $response->assertRedirect();
        $this->assertEquals($this->managerA->id, $inquiry->fresh()->assigned_user_id);
    }

    public function test_cannot_assign_inquiry_to_user_from_different_tenant(): void
    {
        $inquiry = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'corporate',
            'customer_name' => 'Enterprise Client',
            'customer_phone' => '+37410998877',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'guest_count' => 60,
            'status' => EventInquiry::STATUS_NEW,
        ]);

        // Attempt to assign user from Vendor B to inquiry in Vendor A
        $response = $this->actingAs($this->staffA)->patch(
            route('admin.event_inquiries.assign', $inquiry),
            ['assigned_user_id' => $this->staffB->id]
        );

        $response->assertSessionHasErrors(['assigned_user_id']);
        $this->assertNull($inquiry->fresh()->assigned_user_id);
    }

    public function test_staff_can_update_proposed_quotation(): void
    {
        $inquiry = EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'wedding',
            'customer_name' => 'Vahram',
            'customer_phone' => '+37491778899',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(25)->format('Y-m-d'),
            'guest_count' => 80,
            'status' => EventInquiry::STATUS_PREPARING_OFFER,
        ]);

        $response = $this->actingAs($this->staffA)->patch(
            route('admin.event_inquiries.quote', $inquiry),
            ['quoted_price' => 850000]
        );

        $response->assertRedirect();
        $this->assertEquals(850000, (float) $inquiry->fresh()->quoted_price);
    }

    public function test_filtering_and_metrics_calculation(): void
    {
        // 2 new, 1 confirmed, 1 declined
        EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'birthday',
            'customer_name' => 'User 1',
            'customer_phone' => '+37491000001',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'guest_count' => 10,
            'status' => EventInquiry::STATUS_NEW,
        ]);
        EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'wedding',
            'customer_name' => 'User 2',
            'customer_phone' => '+37491000002',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
            'guest_count' => 50,
            'status' => EventInquiry::STATUS_CONTACTED,
        ]);
        EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'corporate',
            'customer_name' => 'User 3',
            'customer_phone' => '+37491000003',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'guest_count' => 20,
            'status' => EventInquiry::STATUS_CONFIRMED,
        ]);
        EventInquiry::create([
            'vendor_id' => $this->vendorA->id,
            'event_type' => 'birthday',
            'customer_name' => 'User 4',
            'customer_phone' => '+37491000004',
            'preferred_contact_method' => 'phone',
            'event_date' => Carbon::now()->addDays(6)->format('Y-m-d'),
            'guest_count' => 15,
            'status' => EventInquiry::STATUS_DECLINED,
        ]);

        $response = $this->actingAs($this->staffA)->get(route('admin.event_inquiries.index'));
        $response->assertStatus(200);

        $metrics = $response->viewData('metrics');
        $this->assertEquals(4, $metrics['total']);
        $this->assertEquals(2, $metrics['pending']);
        $this->assertEquals(1, $metrics['confirmed']);
        $this->assertEquals(1, $metrics['declined']);
        $this->assertEquals(25.0, $metrics['conversion_rate']); // 1 / 4 = 25%

        // Filter by status=confirmed
        $filterResponse = $this->actingAs($this->staffA)->get(route('admin.event_inquiries.index', ['status' => 'confirmed']));
        $filterResponse->assertStatus(200);
        $filterResponse->assertSee('User 3');
        $filterResponse->assertDontSee('User 1');
    }

    public function test_vendor_can_update_event_inquiry_settings(): void
    {
        $payload = [
            'enabled' => 1,
            'form_title' => 'Custom Celebration Request',
            'form_description' => 'We organize your best moments.',
            'min_guests' => 10,
            'max_guests' => 400,
            'contact_methods' => ['phone', 'whatsapp'],
            'email_notifications_enabled' => 1,
            'notification_recipients' => 'events@verona.am, gm@verona.am',
            'customer_acknowledgment_enabled' => 1,
            'confirmation_message' => 'Your request is in good hands.',
            'optional_fields' => [
                'email' => 1,
                'budget' => 1,
                'comments' => 1,
            ],
        ];

        $response = $this->actingAs($this->staffA)->post(
            route('admin.settings.reservations.events.update'),
            $payload
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->vendorA->refresh();
        $settings = $this->vendorA->event_inquiry_settings;

        $this->assertTrue($settings['enabled']);
        $this->assertEquals('Custom Celebration Request', $settings['form_title']);
        $this->assertEquals(10, $settings['min_guests']);
        $this->assertEquals(400, $settings['max_guests']);
        $this->assertEquals(['phone', 'whatsapp'], $settings['contact_methods']);
        $this->assertEquals(['events@verona.am', 'gm@verona.am'], $settings['notification_recipients']);
        $this->assertEquals('Your request is in good hands.', $settings['confirmation_message']);
    }

    public function test_settings_validation_rejects_min_guests_greater_than_max_guests(): void
    {
        $payload = [
            'enabled' => 1,
            'min_guests' => 100,
            'max_guests' => 20, // Invalid: min > max
        ];

        $response = $this->actingAs($this->staffA)->post(
            route('admin.settings.reservations.events.update'),
            $payload
        );

        $response->assertSessionHasErrors(['max_guests']);
    }
}
