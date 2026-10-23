<?php

namespace Tests\Feature\Security;

use App\Models\Location;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantContext;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class TenantIsolationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected User $staffA;

    protected Location $locationA;

    protected RestaurantTable $tableA;

    protected Reservation $reservationA;

    protected ReservationPreOrder $preOrderA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $staffB;

    protected Location $locationB;

    protected RestaurantTable $tableB;

    protected Reservation $reservationB;

    protected ReservationPreOrder $preOrderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::where('slug', 'business')->first();

        // 1. Setup Tenant A
        $this->vendorA = Vendor::create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'email' => 'alpha@tenant.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'reservation_settings' => [
                'enabled' => true,
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'auto_confirm' => false,
                'pre_orders_mode' => 'optional',
            ],
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->staffA = User::create([
            'name' => 'Staff Alpha',
            'email' => 'staff@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Branch',
            'slug' => 'alpha-branch',
            'is_active' => true,
        ]);

        $this->tableA = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => 'T1',
            'name' => 'Table One',
            'capacity' => 4,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->reservationA = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-A-001',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'restaurant_table_id' => $this->tableA->id,
            'customer_name' => 'Customer A',
            'customer_email' => 'customerA@test.com',
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'guest_count' => 2,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->preOrderA = ReservationPreOrder::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $this->reservationA->id,
            'vendor_id' => $this->vendorA->id,
            'status' => ReservationPreOrder::STATUS_PENDING,
            'subtotal' => 5000,
            'total_amount' => 5000,
            'currency' => 'AMD',
        ]);

        // 2. Setup Tenant B
        $this->vendorB = Vendor::create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'email' => 'beta@tenant.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'reservation_settings' => [
                'enabled' => true,
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'auto_confirm' => false,
                'pre_orders_mode' => 'optional',
            ],
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->staffB = User::create([
            'name' => 'Staff Beta',
            'email' => 'staff@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Branch',
            'slug' => 'beta-branch',
            'is_active' => true,
        ]);

        $this->tableB = RestaurantTable::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'table_number' => 'T2',
            'name' => 'Table Two',
            'capacity' => 6,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->reservationB = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-B-001',
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'restaurant_table_id' => $this->tableB->id,
            'customer_name' => 'Customer B',
            'customer_email' => 'customerB@test.com',
            'reservation_date' => now()->addDays(3)->format('Y-m-d'),
            'reservation_time' => '20:00',
            'guest_count' => 4,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->preOrderB = ReservationPreOrder::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $this->reservationB->id,
            'vendor_id' => $this->vendorB->id,
            'status' => ReservationPreOrder::STATUS_PENDING,
            'subtotal' => 8000,
            'total_amount' => 8000,
            'currency' => 'AMD',
        ]);
    }

    public function test_tenant_scope_automatically_isolates_reservations(): void
    {
        app(TenantContext::class)->setTenantId($this->vendorA->id);

        $reservations = Reservation::all();

        $this->assertTrue($reservations->contains('id', $this->reservationA->id));
        $this->assertFalse($reservations->contains('id', $this->reservationB->id));

        app(TenantContext::class)->clear();
    }

    public function test_tenant_scope_automatically_isolates_reservation_preorders(): void
    {
        app(TenantContext::class)->setTenantId($this->vendorA->id);

        $preorders = ReservationPreOrder::all();

        $this->assertTrue($preorders->contains('id', $this->preOrderA->id));
        $this->assertFalse($preorders->contains('id', $this->preOrderB->id));

        app(TenantContext::class)->clear();
    }

    public function test_cross_tenant_reservation_creation_is_blocked_by_model_lifecycle(): void
    {
        app(TenantContext::class)->setTenantId($this->vendorA->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-tenant entity creation forbidden.');

        Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-MALICIOUS',
            'vendor_id' => $this->vendorB->id, // Mismatched vendor
            'customer_name' => 'Eve',
            'customer_email' => 'eve@evil.com',
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '18:00',
            'guest_count' => 2,
        ]);

        app(TenantContext::class)->clear();
    }

    public function test_cross_tenant_reservation_transfer_is_blocked_by_model_lifecycle(): void
    {
        app(TenantContext::class)->setTenantId($this->vendorA->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-tenant entity transfer forbidden.');

        $this->reservationA->vendor_id = $this->vendorB->id;
        $this->reservationA->save();

        app(TenantContext::class)->clear();
    }

    public function test_tenant_cannot_view_another_tenants_reservation_via_http(): void
    {
        $response = $this->actingAs($this->ownerA)->getJson(route('admin.reservations.show', $this->reservationB));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_tenant_cannot_update_status_of_another_tenants_reservation(): void
    {
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.reservations.status', $this->reservationB), [
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));

        $this->assertDatabaseHas('reservations', [
            'id' => $this->reservationB->id,
            'status' => Reservation::STATUS_PENDING,
        ]);
    }

    public function test_table_store_blocks_foreign_location_id_injection(): void
    {
        $response = $this->actingAs($this->ownerA)->post(route('admin.tables.store'), [
            'table_number' => 'T99',
            'capacity' => 4,
            'min_capacity' => 1,
            'location_id' => $this->locationB->id, // Foreign location from Vendor B
        ]);

        $response->assertSessionHasErrors('location_id');

        $this->assertDatabaseMissing('restaurant_tables', [
            'table_number' => 'T99',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationB->id,
        ]);
    }

    public function test_table_update_blocks_foreign_location_id_injection(): void
    {
        $response = $this->actingAs($this->ownerA)->put(route('admin.tables.update', $this->tableA), [
            'table_number' => 'T1-Updated',
            'capacity' => 4,
            'min_capacity' => 1,
            'location_id' => $this->locationB->id, // Foreign location from Vendor B
        ]);

        $response->assertSessionHasErrors('location_id');

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $this->tableA->id,
            'location_id' => $this->locationA->id,
        ]);
    }

    public function test_tenant_cannot_modify_or_delete_another_tenants_table(): void
    {
        $toggleResponse = $this->actingAs($this->ownerA)->postJson(route('admin.tables.toggle', $this->tableB));
        $this->assertTrue(in_array($toggleResponse->status(), [403, 404], true));

        $deleteResponse = $this->actingAs($this->ownerA)->delete(route('admin.tables.destroy', $this->tableB));
        $this->assertTrue(in_array($deleteResponse->status(), [403, 404], true));

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $this->tableB->id,
            'deleted_at' => null,
        ]);
    }
}
