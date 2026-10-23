<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationAuthorizationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $businessPlan;

    // Tenant A
    protected Vendor $vendorA;

    protected Location $locationA;

    protected User $ownerA;

    protected User $managerA;

    protected User $staffA;

    protected User $chefA;

    protected User $cashierA;

    protected Product $productA;

    protected Category $categoryA;

    protected RestaurantTable $tableA;

    protected Reservation $reservationA;

    protected Order $orderA;

    protected Customer $customerA;

    // Tenant B
    protected Vendor $vendorB;

    protected Location $locationB;

    protected User $ownerB;

    protected RestaurantTable $tableB;

    protected Reservation $reservationB;

    protected Order $orderB;

    protected Customer $customerB;

    // SuperAdmin
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $this->businessPlan = SubscriptionPlan::where('slug', 'business')->firstOrFail();

        // 1. Create Tenant A
        $this->vendorA = Vendor::create([
            'name' => 'Restaurant Alpha',
            'slug' => 'restaurant-alpha',
            'type' => 'restaurant',
            'currency' => 'AMD',
            'is_active' => true,
            'subscription_plan_id' => $this->businessPlan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
            'reservation_settings' => [
                'enabled' => true,
                'mode' => Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER,
                'auto_confirm' => false,
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'min_party_size' => 1,
                'max_party_size' => 20,
                'advance_booking_days' => 30,
                'booking_cutoff_minutes' => 60,
                'slot_interval_minutes' => 30,
                'opening_time' => '10:00',
                'closing_time' => '23:00',
                'operating_days' => [0, 1, 2, 3, 4, 5, 6],
                'pre_orders_mode' => 'optional',
                'cancellation_cutoff_hours' => 2,
            ],
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Center',
            'slug' => 'alpha-center',
            'table_count' => 10,
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Owner Alpha',
            'email' => 'owner.alpha@test.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->managerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Manager Alpha',
            'email' => 'manager.alpha@test.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        $this->staffA = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Staff Alpha',
            'email' => 'staff.alpha@test.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->chefA = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Chef Alpha',
            'email' => 'chef.alpha@test.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'chef',
            'email_verified_at' => now(),
        ]);

        $this->cashierA = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Cashier Alpha',
            'email' => 'cashier.alpha@test.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'email_verified_at' => now(),
        ]);

        $this->categoryA = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Drinks',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Alpha Coffee',
            'price' => 1500,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $this->tableA = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => 'T-A1',
            'name' => 'Table Alpha 1',
            'capacity' => 4,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->reservationA = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-ALPHA-01',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'restaurant_table_id' => $this->tableA->id,
            'table_number' => $this->tableA->table_number,
            'customer_name' => 'Alice Customer',
            'customer_email' => 'alice@test.com',
            'customer_phone' => '+37499111222',
            'guest_count' => 2,
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'reservation_datetime' => now()->addDays(2)->setHour(19)->setMinute(0),
            'duration_minutes' => 90,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->orderA = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'order_number' => 'ORD-A-001',
            'tracking_token' => (string) Str::uuid(),
            'table_number' => 'T-A1',
            'type' => 'dine_in',
            'status' => 'pending',
            'subtotal' => 1500,
            'total_amount' => 1500,
            'currency' => 'AMD',
        ]);

        $this->customerA = Customer::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Alice Customer',
            'email' => 'alice@test.com',
            'phone' => '+37499111222',
        ]);

        // 2. Create Tenant B
        $this->vendorB = Vendor::create([
            'name' => 'Restaurant Beta',
            'slug' => 'restaurant-beta',
            'type' => 'restaurant',
            'currency' => 'AMD',
            'is_active' => true,
            'subscription_plan_id' => $this->businessPlan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Branch',
            'slug' => 'beta-branch',
            'table_count' => 5,
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Owner Beta',
            'email' => 'owner.beta@test.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->tableB = RestaurantTable::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'table_number' => 'T-B1',
            'name' => 'Table Beta 1',
            'capacity' => 6,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->reservationB = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-BETA-01',
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'restaurant_table_id' => $this->tableB->id,
            'table_number' => $this->tableB->table_number,
            'customer_name' => 'Bob Customer',
            'customer_email' => 'bob@test.com',
            'customer_phone' => '+37499333444',
            'guest_count' => 4,
            'reservation_date' => now()->addDays(3)->format('Y-m-d'),
            'reservation_time' => '20:00',
            'reservation_datetime' => now()->addDays(3)->setHour(20)->setMinute(0),
            'duration_minutes' => 90,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->orderB = Order::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'order_number' => 'ORD-B-001',
            'tracking_token' => (string) Str::uuid(),
            'table_number' => 'T-B1',
            'type' => 'dine_in',
            'status' => 'pending',
            'subtotal' => 3000,
            'total_amount' => 3000,
            'currency' => 'AMD',
        ]);

        $this->customerB = Customer::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'name' => 'Bob Customer',
            'email' => 'bob@test.com',
            'phone' => '+37499333444',
        ]);

        // 3. SuperAdmin
        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@qrmenu.am',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);
    }

    // =========================================================================
    // 1. Unauthenticated Protection
    // =========================================================================

    public function test_unauthenticated_users_cannot_access_protected_admin_routes(): void
    {
        $routes = [
            route('admin.dashboard'),
            route('admin.orders.index'),
            route('admin.menu.index'),
            route('admin.tables.index'),
            route('admin.reservations.index'),
            route('admin.translations.index'),
            route('admin.settings.index'),
            route('admin.customers.index'),
            route('superadmin.dashboard'),
        ];

        foreach ($routes as $url) {
            $response = $this->get($url);
            $response->assertRedirect(route('login'));
        }
    }

    public function test_unauthenticated_users_cannot_execute_protected_post_actions(): void
    {
        $this->postJson(route('admin.menu.categories.store'), ['name' => 'Hacked Category'])
            ->assertStatus(401);

        $this->postJson(route('admin.tables.store'), ['table_number' => 'HACK'])
            ->assertStatus(401);

        $this->assertDatabaseMissing('categories', ['name' => 'Hacked Category']);
        $this->assertDatabaseMissing('restaurant_tables', ['table_number' => 'HACK']);
    }

    // =========================================================================
    // 2. Role Boundaries & Hierarchy Isolation
    // =========================================================================

    public function test_vendor_owner_cannot_access_superadmin_panel(): void
    {
        $this->actingAs($this->ownerA)
            ->get(route('superadmin.dashboard'))
            ->assertStatus(403);

        $this->actingAs($this->ownerA)
            ->get(route('superadmin.vendors.index'))
            ->assertStatus(403);
    }

    public function test_staff_cannot_perform_owner_only_or_billing_actions(): void
    {
        // 1. Staff cannot view or renew subscription
        $this->actingAs($this->staffA)
            ->get(route('admin.subscription'))
            ->assertStatus(403);

        $this->actingAs($this->staffA)
            ->post(route('admin.subscription.renew'), [
                'subscription_plan_id' => $this->businessPlan->id,
                'period_months' => 1,
                'payment_method' => 'arca',
            ])
            ->assertStatus(403);

        // 2. Staff cannot manage team members
        $this->actingAs($this->staffA)
            ->get(route('admin.team.index'))
            ->assertStatus(403);

        $this->actingAs($this->staffA)
            ->post(route('admin.team.store'), [
                'name' => 'Rogue Staff',
                'email' => 'rogue@test.com',
                'role' => 'staff',
                'password' => 'secret123',
            ])
            ->assertStatus(403);

        // 3. Staff cannot sync floor plan tables
        $this->actingAs($this->staffA)
            ->post(route('admin.tables.sync_floor_plan'))
            ->assertStatus(403);

        // 4. Staff cannot view or manage translations
        $this->actingAs($this->staffA)
            ->get(route('admin.translations.index'))
            ->assertStatus(403);

        $this->actingAs($this->staffA)
            ->get(route('admin.translations.glossary.index'))
            ->assertStatus(403);
    }

    public function test_manager_cannot_invite_team_members_or_manage_billing(): void
    {
        // Managers cannot access billing
        $this->actingAs($this->managerA)
            ->get(route('admin.subscription'))
            ->assertStatus(403);

        // Managers cannot invite team members (only vendor_owner has team.manage)
        $this->actingAs($this->managerA)
            ->post(route('admin.team.store'), [
                'name' => 'Manager Invited Member',
                'email' => 'manager.invited@test.com',
                'role' => 'staff',
                'password' => 'secret123',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'manager.invited@test.com']);
    }

    public function test_staff_and_chef_cannot_cancel_or_refund_orders(): void
    {
        // 1. Staff cannot cancel order
        $this->actingAs($this->staffA)
            ->postJson(route('admin.orders.status', $this->orderA), ['status' => 'cancelled'])
            ->assertStatus(403);

        $this->assertEquals('pending', $this->orderA->fresh()->status);

        // 2. Chef cannot refund order
        $this->actingAs($this->chefA)
            ->postJson(route('admin.orders.status', $this->orderA), ['status' => 'refunded'])
            ->assertStatus(403);

        $this->assertEquals('pending', $this->orderA->fresh()->status);

        // 3. Chef CAN update order to cooking/prep status
        $this->actingAs($this->chefA)
            ->postJson(route('admin.orders.status', $this->orderA), ['status' => 'preparing'])
            ->assertStatus(200);

        $this->assertEquals('preparing', $this->orderA->fresh()->status);
    }

    // =========================================================================
    // 3. Cross-Tenant IDOR Protection
    // =========================================================================

    public function test_vendor_admin_cannot_access_or_modify_another_vendors_reservations(): void
    {
        // Owner A cannot view Reservation B
        $resShow = $this->actingAs($this->ownerA)
            ->getJson(route('admin.reservations.show', $this->reservationB));

        $this->assertTrue(in_array($resShow->status(), [403, 404]));

        // Owner A cannot update status of Reservation B
        $resUpdate = $this->actingAs($this->ownerA)
            ->postJson(route('admin.reservations.status', $this->reservationB), [
                'status' => 'confirmed',
            ]);

        $this->assertTrue(in_array($resUpdate->status(), [403, 404]));
        $this->assertEquals(Reservation::STATUS_PENDING, $this->reservationB->fresh()->status);
    }

    public function test_vendor_admin_cannot_modify_or_delete_another_vendors_tables(): void
    {
        // Owner A cannot update Table B
        $resUpdate = $this->actingAs($this->ownerA)
            ->put(route('admin.tables.update', $this->tableB), [
                'table_number' => 'HACKED-TABLE',
                'capacity' => 10,
                'min_capacity' => 1,
            ]);

        $this->assertTrue(in_array($resUpdate->status(), [403, 404]));
        $this->assertEquals('T-B1', $this->tableB->fresh()->table_number);

        // Owner A cannot delete Table B
        $resDelete = $this->actingAs($this->ownerA)
            ->delete(route('admin.tables.destroy', $this->tableB));

        $this->assertTrue(in_array($resDelete->status(), [403, 404]));
        $this->assertDatabaseHas('restaurant_tables', ['id' => $this->tableB->id]);
    }

    public function test_vendor_admin_cannot_access_or_modify_another_vendors_customers(): void
    {
        // Owner A cannot view Customer B
        $resShow = $this->actingAs($this->ownerA)
            ->get(route('admin.customers.show', $this->customerB));

        $this->assertTrue(in_array($resShow->status(), [403, 404]));

        // Owner A cannot delete Customer B
        $resDelete = $this->actingAs($this->ownerA)
            ->delete(route('admin.customers.destroy', $this->customerB));

        $this->assertTrue(in_array($resDelete->status(), [403, 404]));
        $this->assertDatabaseHas('customers', ['id' => $this->customerB->id]);
    }

    // =========================================================================
    // 4. Parameter Tampering & Privilege Escalation
    // =========================================================================

    public function test_forged_vendor_id_in_storefront_order_is_rejected(): void
    {
        $payload = [
            'vendor_id' => $this->vendorB->id, // Attacker sends vendor B id
            'location_id' => $this->locationA->id,
            'table_number' => 'T-A1',
            'type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'price' => 10, // Attacker attempts to forge price 10 AMD instead of 1500
                ],
            ],
        ];

        // Submit to Vendor A's storefront URL
        $response = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendorA->slug]), $payload);

        $response->assertStatus(200);
        $orderId = $response->json('order_id');
        $createdOrder = Order::findOrFail($orderId);

        // Server-side authoritative overrides:
        // 1. Order must belong strictly to Vendor A (not the forged vendorB id)
        $this->assertEquals($this->vendorA->id, $createdOrder->vendor_id);
        // 2. Total must be calculated using database product price (1500 AMD, not 10 AMD)
        $this->assertEquals(1500, (float) $createdOrder->total_amount);
    }

    public function test_cannot_assign_cross_vendor_location_when_creating_team_member(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.team.store'), [
                'name' => 'Evil Staff',
                'email' => 'evil.staff@test.com',
                'role' => 'staff',
                'password' => 'secret123',
                'location_id' => $this->locationB->id, // Location belongs to Vendor B!
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'evil.staff@test.com']);
    }

    public function test_cannot_escalate_role_to_superadmin_when_creating_team_member(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.team.store'), [
                'name' => 'Escalated Admin',
                'email' => 'escalated@test.com',
                'role' => 'superadmin', // Invalid escalation attempt
                'password' => 'secret123',
            ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'escalated@test.com']);
    }

    // =========================================================================
    // 5. Account Suspension Enforcement
    // =========================================================================

    public function test_suspended_vendor_cannot_access_admin_or_accept_public_orders_and_reservations(): void
    {
        // Deactivate Vendor A
        $this->vendorA->update(['is_active' => false]);
        $this->assertFalse($this->vendorA->fresh()->allowsAccess());

        // 1. Admin dashboard access is redirected to subscription status
        $this->actingAs($this->ownerA)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.subscription'));

        // 2. Storefront menu returns 404
        $this->get(route('client.menu', ['vendor_slug' => $this->vendorA->slug]))
            ->assertStatus(404);

        // 3. Storefront order submission returns 404
        $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendorA->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => 'T-A1',
            'type' => 'dine_in',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
        ])->assertStatus(404);

        // 4. Storefront reservation form and submission return 404
        $this->get(route('client.reserve', ['vendor_slug' => $this->vendorA->slug]))
            ->assertStatus(404);

        $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendorA->slug]), [
            'customer_name' => 'Test Suspended',
            'customer_email' => 'suspended@test.com',
            'guest_count' => 2,
            'reservation_date' => now()->addDay()->format('Y-m-d'),
            'reservation_time' => '18:00',
        ])->assertStatus(404);
    }

    // =========================================================================
    // 6. State Transitions & Cryptographic UUID Token Guards
    // =========================================================================

    public function test_cancelled_or_rejected_reservations_cannot_be_reopened(): void
    {
        $this->reservationA->update(['status' => Reservation::STATUS_CANCELLED]);

        // Attempt to reopen cancelled reservation to confirmed
        $response = $this->actingAs($this->ownerA)
            ->postJson(route('admin.reservations.status', $this->reservationA), [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(Reservation::STATUS_CANCELLED, $this->reservationA->fresh()->status);
    }

    public function test_customer_self_service_requires_matching_cryptographic_uuid_token(): void
    {
        // 1. Valid token allows viewing details
        $this->get(route('client.reservation.show', [
            'vendor_slug' => $this->vendorA->slug,
            'reference' => $this->reservationA->reference,
            'token' => $this->reservationA->uuid,
        ]))->assertStatus(200);

        // 2. Forged or missing token is rejected with 403
        $this->get(route('client.reservation.show', [
            'vendor_slug' => $this->vendorA->slug,
            'reference' => $this->reservationA->reference,
            'token' => 'invalid-uuid-token',
        ]))->assertStatus(403);

        // 3. Forged token on cancellation is rejected with 403
        $this->postJson(route('client.reservation.cancel', [
            'vendor_slug' => $this->vendorA->slug,
            'reference' => $this->reservationA->reference,
        ]), [
            'token' => 'forged-token',
        ])->assertStatus(403);

        $this->assertEquals(Reservation::STATUS_PENDING, $this->reservationA->fresh()->status);

        // 4. Valid token cancels reservation successfully
        $this->postJson(route('client.reservation.cancel', [
            'vendor_slug' => $this->vendorA->slug,
            'reference' => $this->reservationA->reference,
        ]), [
            'token' => $this->reservationA->uuid,
            'reason' => 'Change of plans',
        ])->assertStatus(200);

        $this->assertEquals(Reservation::STATUS_CANCELLED, $this->reservationA->fresh()->status);
    }
}
