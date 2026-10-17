<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\TenantContext;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaiterCallTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor1;

    protected Location $location1;

    protected User $user1;

    protected Vendor $vendor2;

    protected Location $location2;

    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();

        // Vendor 1
        $this->vendor1 = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->location1 = Location::create([
            'vendor_id' => $this->vendor1->id,
            'name' => 'Alpha Center',
            'slug' => 'center',
            'is_active' => true,
        ]);

        $this->user1 = User::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'name' => 'Manager Alpha',
            'email' => 'manager@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        // Vendor 2
        $this->vendor2 = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->location2 = Location::create([
            'vendor_id' => $this->vendor2->id,
            'name' => 'Beta Branch',
            'slug' => 'branch',
            'is_active' => true,
        ]);

        $this->user2 = User::create([
            'vendor_id' => $this->vendor2->id,
            'location_id' => $this->location2->id,
            'name' => 'Manager Beta',
            'email' => 'manager@beta.am',
            'password' => bcrypt('password'),
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);
    }

    public function test_guest_can_call_waiter_from_storefront(): void
    {
        $response = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor1->slug]), [
            'location_id' => $this->location1->id,
            'table_number' => 'Table 4',
            'type' => 'call_waiter',
            'notes' => 'Please bring extra napkins',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'call' => [
                'table_number' => 'Table 4',
                'type' => 'call_waiter',
                'status' => 'pending',
            ],
        ]);

        $this->assertDatabaseHas('waiter_calls', [
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'table_number' => 'Table 4',
            'type' => 'call_waiter',
            'status' => 'pending',
            'notes' => 'Please bring extra napkins',
        ]);
    }

    public function test_guest_can_request_bill_with_cash_and_card(): void
    {
        // 1. Request Cash Bill
        $resCash = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor1->slug]), [
            'location_id' => $this->location1->id,
            'table_number' => 'Table 8',
            'type' => 'bill_cash',
        ]);

        $resCash->assertStatus(200);
        $this->assertDatabaseHas('waiter_calls', [
            'vendor_id' => $this->vendor1->id,
            'table_number' => 'Table 8',
            'type' => 'bill_cash',
            'status' => 'pending',
        ]);

        // 2. Request Card Bill
        $resCard = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor1->slug]), [
            'location_id' => $this->location1->id,
            'table_number' => 'Table 12',
            'type' => 'bill_card',
        ]);

        $resCard->assertStatus(200);
        $this->assertDatabaseHas('waiter_calls', [
            'vendor_id' => $this->vendor1->id,
            'table_number' => 'Table 12',
            'type' => 'bill_card',
            'status' => 'pending',
        ]);
    }

    public function test_storefront_hides_waiter_button_when_no_table_qr_scanned(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor1->slug]));
        $response->assertStatus(200);
        $response->assertDontSee('bottom-nav-waiter');
        $response->assertSee('isTableFixed: false', false);
    }

    public function test_storefront_shows_waiter_button_when_table_qr_scanned(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor1->slug, 'table' => '7']));
        $response->assertStatus(200);
        $response->assertSee('bottom-nav-waiter');
        $response->assertSee('isTableFixed: true', false);
    }

    public function test_cannot_call_waiter_for_another_table_than_scanned(): void
    {
        // Visit table 5 QR
        $this->get(route('client.menu', ['vendor_slug' => $this->vendor1->slug, 'table' => '5']));

        // Attempt to call waiter for Table 9
        $response = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor1->slug]), [
            'location_id' => $this->location1->id,
            'table_number' => 'Table 9',
            'type' => 'call_waiter',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Դուք չեք կարող կանչել մատուցող այլ սեղանի համար։',
        ]);
    }

    public function test_can_call_waiter_for_same_scanned_table(): void
    {
        // Visit table 5 QR
        $this->get(route('client.menu', ['vendor_slug' => $this->vendor1->slug, 'table' => '5']));

        // Call waiter for Table 5
        $response = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor1->slug]), [
            'location_id' => $this->location1->id,
            'table_number' => 'Table 5',
            'type' => 'call_waiter',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_call_waiter_validates_required_fields(): void
    {
        $response = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor1->slug]), [
            'table_number' => '',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_number', 'type']);
    }

    public function test_call_waiter_rejects_cross_tenant_location(): void
    {
        $response = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor1->slug]), [
            'location_id' => $this->location2->id, // Belongs to Vendor 2!
            'table_number' => 'Table 3',
            'type' => 'call_waiter',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['location_id']);
    }

    public function test_vendor_staff_can_view_waiter_calls_in_feed(): void
    {
        $call = WaiterCall::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'table_number' => 'Table 99',
            'type' => 'bill_card',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user1)->getJson(route('admin.orders.feed'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'waiter_calls_count' => 1,
        ]);
        $this->assertStringContainsString('Table 99', $response->json('waiter_calls_html'));
        $this->assertStringContainsString(__('Bill (Card)'), $response->json('waiter_calls_html'));
    }

    public function test_vendor_staff_can_mark_call_as_attended(): void
    {
        $call = WaiterCall::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'table_number' => 'Table 5',
            'type' => 'call_waiter',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user1)->postJson(route('admin.waiter_calls.status', $call->id), [
            'status' => 'attended',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'attended',
        ]);

        $this->assertEquals('attended', $call->fresh()->status);
    }

    public function test_vendor_cannot_update_waiter_call_of_another_vendor(): void
    {
        $call2 = WaiterCall::create([
            'vendor_id' => $this->vendor2->id,
            'location_id' => $this->location2->id,
            'table_number' => 'Table Beta 1',
            'type' => 'call_waiter',
            'status' => 'pending',
        ]);

        // User 1 attempts to update Vendor 2's waiter call
        $response = $this->actingAs($this->user1)->post(route('admin.waiter_calls.status', $call2->id), [
            'status' => 'attended',
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404]), "Expected 403 or 404, got {$response->status()}");
        $this->assertEquals('pending', $call2->fresh()->status);
    }

    public function test_waiter_call_uses_tenant_scope_isolation(): void
    {
        $tenantContext = app(TenantContext::class);

        $call1 = WaiterCall::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'table_number' => 'Table 1',
            'type' => 'call_waiter',
        ]);

        $call2 = WaiterCall::create([
            'vendor_id' => $this->vendor2->id,
            'location_id' => $this->location2->id,
            'table_number' => 'Table 2',
            'type' => 'bill_cash',
        ]);

        // Set context to Vendor 1
        $tenantContext->setTenantId($this->vendor1->id);

        $this->assertCount(1, WaiterCall::all());
        $this->assertEquals('Table 1', WaiterCall::first()->table_number);
        $this->assertNull(WaiterCall::find($call2->id));

        // Switch to Vendor 2
        $tenantContext->setTenantId($this->vendor2->id);
        $this->assertCount(1, WaiterCall::all());
        $this->assertEquals('Table 2', WaiterCall::first()->table_number);
        $this->assertNull(WaiterCall::find($call1->id));

        $tenantContext->clear();
    }
}
