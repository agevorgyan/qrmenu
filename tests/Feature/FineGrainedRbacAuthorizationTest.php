<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FineGrainedRbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $locationA;

    protected Location $locationB;

    protected Category $category;

    protected Product $product;

    protected Order $orderA;

    protected Order $orderB;

    protected Customer $customerA;

    protected User $owner;

    protected User $manager;

    protected User $staff;

    protected User $chef;

    protected User $cashier;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $businessPlan = SubscriptionPlan::where('slug', 'business')->first()
            ?? SubscriptionPlan::create([
                'name' => 'Business',
                'slug' => 'business',
                'price' => 20000,
                'currency' => 'AMD',
                'billing_period' => 'monthly',
                'features' => ['orders', 'locations', 'team', 'customers', 'ai_waiter'],
                'is_active' => true,
            ]);

        $this->vendor = Vendor::create([
            'name' => 'RBAC Grand Bistro',
            'slug' => 'rbac-grand-bistro',
            'email' => 'contact@rbacbistro.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Center Branch (A)',
            'slug' => 'center-branch-a',
            'table_count' => 20,
            'is_active' => true,
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Cascades Branch (B)',
            'slug' => 'cascades-branch-b',
            'table_count' => 15,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Hot Dishes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Kebab Platter',
            'price' => 3500,
            'is_available' => true,
        ]);

        $this->orderA = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->locationA->id,
            'order_number' => 'ORD-1001',
            'total_amount' => 5000,
            'status' => 'pending',
            'table_number' => '5',
        ]);

        $this->orderB = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->locationB->id,
            'order_number' => 'ORD-2001',
            'total_amount' => 7500,
            'status' => 'pending',
            'table_number' => '12',
        ]);

        $this->customerA = Customer::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->locationA->id,
            'name' => 'Aram Vardanyan',
            'phone' => '+37491111222',
        ]);

        // Users representing the 6 distinct roles
        $this->owner = User::create([
            'name' => 'Vendor Owner',
            'email' => 'owner@rbacbistro.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'location_id' => null, // Access to all branches
            'email_verified_at' => now(),
        ]);

        $this->manager = User::create([
            'name' => 'General Manager',
            'email' => 'manager@rbacbistro.am',
            'password' => bcrypt('password'),
            'role' => 'manager',
            'vendor_id' => $this->vendor->id,
            'location_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->staff = User::create([
            'name' => 'Floor Staff A',
            'email' => 'staff@rbacbistro.am',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->locationA->id, // Bound to Location A
            'email_verified_at' => now(),
        ]);

        $this->chef = User::create([
            'name' => 'Head Chef A',
            'email' => 'chef@rbacbistro.am',
            'password' => bcrypt('password'),
            'role' => 'chef',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->locationA->id, // Bound to Location A
            'email_verified_at' => now(),
        ]);

        $this->cashier = User::create([
            'name' => 'Counter Cashier A',
            'email' => 'cashier@rbacbistro.am',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->locationA->id, // Bound to Location A
            'email_verified_at' => now(),
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@qrmenu.local',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
            'vendor_id' => null,
            'location_id' => null,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 1. Vendor Owner has full tenant authority across all modules.
     */
    public function test_vendor_owner_has_full_tenant_permissions(): void
    {
        $this->assertTrue($this->owner->hasPermission(Permission::MENU_VIEW));
        $this->assertTrue($this->owner->hasPermission(Permission::MENU_CREATE));
        $this->assertTrue($this->owner->hasPermission(Permission::MENU_UPDATE));
        $this->assertTrue($this->owner->hasPermission(Permission::MENU_DELETE));
        $this->assertTrue($this->owner->hasPermission(Permission::ORDERS_VIEW));
        $this->assertTrue($this->owner->hasPermission(Permission::ORDERS_UPDATE));
        $this->assertTrue($this->owner->hasPermission(Permission::ORDERS_CANCEL));
        $this->assertTrue($this->owner->hasPermission(Permission::ORDERS_REFUND));
        $this->assertTrue($this->owner->hasPermission(Permission::BILLING_VIEW));
        $this->assertTrue($this->owner->hasPermission(Permission::BILLING_MANAGE));
        $this->assertTrue($this->owner->hasPermission(Permission::AI_VIEW));
        $this->assertTrue($this->owner->hasPermission(Permission::AI_MANAGE));
        $this->assertTrue($this->owner->hasPermission(Permission::TEAM_VIEW));
        $this->assertTrue($this->owner->hasPermission(Permission::TEAM_MANAGE));
        $this->assertTrue($this->owner->hasPermission(Permission::SETTINGS_VIEW));
        $this->assertTrue($this->owner->hasPermission(Permission::SETTINGS_MANAGE));

        // Gate checks
        $this->assertTrue(Gate::forUser($this->owner)->allows(Permission::BILLING_MANAGE));
        $this->assertTrue(Gate::forUser($this->owner)->allows('manageBilling', $this->vendor));

        // Route checks
        $response = $this->actingAs($this->owner)->get(route('admin.subscription'));
        $response->assertStatus(200);

        $teamResponse = $this->actingAs($this->owner)->get(route('admin.team.index'));
        $teamResponse->assertStatus(200);
    }

    /**
     * 2. Manager has operational access but cannot modify billing or manage team members.
     */
    public function test_manager_has_operational_permissions_but_cannot_modify_billing_or_manage_team(): void
    {
        $this->assertTrue($this->manager->hasPermission(Permission::MENU_VIEW));
        $this->assertTrue($this->manager->hasPermission(Permission::MENU_UPDATE));
        $this->assertTrue($this->manager->hasPermission(Permission::ORDERS_VIEW));
        $this->assertTrue($this->manager->hasPermission(Permission::ORDERS_UPDATE));
        $this->assertTrue($this->manager->hasPermission(Permission::ORDERS_CANCEL));
        $this->assertTrue($this->manager->hasPermission(Permission::CUSTOMERS_VIEW));
        $this->assertTrue($this->manager->hasPermission(Permission::REPORTS_VIEW));
        $this->assertTrue($this->manager->hasPermission(Permission::TEAM_VIEW));

        // Manager CANNOT manage billing or team
        $this->assertFalse($this->manager->hasPermission(Permission::BILLING_VIEW));
        $this->assertFalse($this->manager->hasPermission(Permission::BILLING_MANAGE));
        $this->assertFalse($this->manager->hasPermission(Permission::TEAM_MANAGE));
        $this->assertFalse($this->manager->hasPermission(Permission::AI_MANAGE));
        $this->assertFalse($this->manager->hasPermission(Permission::SETTINGS_MANAGE));

        // Policy / Gate denials
        $this->assertFalse(Gate::forUser($this->manager)->allows(Permission::BILLING_MANAGE));
        $this->assertFalse(Gate::forUser($this->manager)->allows('manageBilling', $this->vendor));
        $this->assertFalse(Gate::forUser($this->manager)->allows(Permission::TEAM_MANAGE));

        // Attempting to access billing should be 403 Forbidden
        $response = $this->actingAs($this->manager)->get(route('admin.subscription'));
        $response->assertStatus(403);

        // Attempting to invite a team member as manager should be 403 Forbidden
        $inviteResponse = $this->actingAs($this->manager)->post(route('admin.team.store'), [
            'name' => 'Subordinate Staff',
            'email' => 'substaff@rbacbistro.am',
            'role' => 'staff',
            'password' => 'password123',
        ]);
        $inviteResponse->assertStatus(403);
    }

    /**
     * 3. Staff users cannot modify billing, AI credentials, security settings, or manage users.
     */
    public function test_staff_cannot_modify_billing_ai_credentials_security_or_team(): void
    {
        $this->assertFalse($this->staff->hasPermission(Permission::BILLING_VIEW));
        $this->assertFalse($this->staff->hasPermission(Permission::BILLING_MANAGE));
        $this->assertFalse($this->staff->hasPermission(Permission::AI_MANAGE));
        $this->assertFalse($this->staff->hasPermission(Permission::SETTINGS_MANAGE));
        $this->assertFalse($this->staff->hasPermission(Permission::TEAM_VIEW));
        $this->assertFalse($this->staff->hasPermission(Permission::TEAM_MANAGE));

        // 403 on billing
        $this->actingAs($this->staff)->get(route('admin.subscription'))->assertStatus(403);

        // 403 on team index
        $this->actingAs($this->staff)->get(route('admin.team.index'))->assertStatus(403);

        $this->actingAs($this->staff)
            ->post(route('admin.settings.update'), [
                'service_fee_percent' => 10,
                'service_fee_type' => 'percentage',
                'service_fee_value' => 10,
                'delivery_fee' => 500,
                'delivery_min_amount' => 2000,
            ])
            ->assertForbidden();

        // 403 on AI settings update
        $this->actingAs($this->staff)->post(route('admin.settings.ai.update'), [
            'ai_waiter_enabled' => 1,
        ])->assertStatus(403);

        // 403 on AI connection test
        $this->actingAs($this->staff)->postJson(route('admin.settings.ai.test'), [
            'ai_provider' => 'gemini',
        ])->assertStatus(403);
    }

    /**
     * 4. Staff and chef users cannot cancel or refund orders.
     */
    public function test_staff_and_chef_cannot_cancel_or_refund_orders(): void
    {
        $this->assertFalse($this->staff->hasPermission(Permission::ORDERS_CANCEL));
        $this->assertFalse($this->staff->hasPermission(Permission::ORDERS_REFUND));
        $this->assertFalse($this->chef->hasPermission(Permission::ORDERS_CANCEL));
        $this->assertFalse($this->chef->hasPermission(Permission::ORDERS_REFUND));

        // Staff attempting to cancel order
        $responseStaff = $this->actingAs($this->staff)->post(route('admin.orders.status', $this->orderA), [
            'status' => 'cancelled',
        ]);
        $responseStaff->assertStatus(403);

        // Chef attempting to refund order
        $responseChef = $this->actingAs($this->chef)->post(route('admin.orders.status', $this->orderA), [
            'status' => 'refunded',
        ]);
        $responseChef->assertStatus(403);
    }

    /**
     * 5. Cashier can view, update, cancel, and refund orders, but cannot manage menu or export customers.
     */
    public function test_cashier_permissions_and_boundaries(): void
    {
        $this->assertTrue($this->cashier->hasPermission(Permission::ORDERS_VIEW));
        $this->assertTrue($this->cashier->hasPermission(Permission::ORDERS_UPDATE));
        $this->assertTrue($this->cashier->hasPermission(Permission::ORDERS_CANCEL));
        $this->assertTrue($this->cashier->hasPermission(Permission::ORDERS_REFUND));
        $this->assertTrue($this->cashier->hasPermission(Permission::CUSTOMERS_VIEW));

        $this->assertFalse($this->cashier->hasPermission(Permission::CUSTOMERS_EXPORT));
        $this->assertFalse($this->cashier->hasPermission(Permission::MENU_CREATE));
        $this->assertFalse($this->cashier->hasPermission(Permission::MENU_UPDATE));
        $this->assertFalse($this->cashier->hasPermission(Permission::MENU_DELETE));
        $this->assertFalse($this->cashier->hasPermission(Permission::SETTINGS_MANAGE));

        // Cashier successfully cancels an order
        $response = $this->actingAs($this->cashier)->post(route('admin.orders.status', $this->orderA), [
            'status' => 'cancelled',
        ]);
        $response->assertRedirect();
        $this->assertEquals('cancelled', $this->orderA->fresh()->status);

        // Cashier is forbidden from exporting customers CRM
        $exportResponse = $this->actingAs($this->cashier)->get(route('admin.customers.export'));
        $exportResponse->assertStatus(403);

        // Cashier is forbidden from creating menu dishes
        $menuResponse = $this->actingAs($this->cashier)->post(route('admin.menu.products.store'), [
            'category_id' => $this->category->id,
            'name' => 'Forbidden Dish',
            'price' => 1000,
        ]);
        $menuResponse->assertStatus(403);
    }

    /**
     * 6. Chef can update order preparation status, but cannot access customers or settings.
     */
    public function test_chef_can_update_kitchen_order_status_but_cannot_access_customers_or_settings(): void
    {
        $this->assertTrue($this->chef->hasPermission(Permission::ORDERS_VIEW));
        $this->assertTrue($this->chef->hasPermission(Permission::ORDERS_UPDATE));
        $this->assertFalse($this->chef->hasPermission(Permission::CUSTOMERS_VIEW));
        $this->assertFalse($this->chef->hasPermission(Permission::SETTINGS_VIEW));

        // Chef updates preparation status
        $response = $this->actingAs($this->chef)->post(route('admin.orders.status', $this->orderA), [
            'status' => 'preparing',
        ]);
        $response->assertRedirect();
        $this->assertEquals('preparing', $this->orderA->fresh()->status);

        // Chef is forbidden from viewing customers CRM
        $this->actingAs($this->chef)->get(route('admin.customers.index'))->assertStatus(403);

        // Chef is forbidden from viewing settings
        $this->actingAs($this->chef)->get(route('admin.settings.index'))->assertStatus(403);
    }

    /**
     * 7. Location-level isolation: Staff assigned to Location A cannot access Location B orders.
     */
    public function test_location_isolation_prevents_cross_branch_order_access(): void
    {
        // Staff at Location A can view Order A
        $this->assertTrue($this->staff->canAccessLocationId($this->orderA->location_id));
        $this->assertTrue(Gate::forUser($this->staff)->allows('view', $this->orderA));

        // Staff at Location A CANNOT view or update Order B
        $this->assertFalse($this->staff->canAccessLocationId($this->orderB->location_id));
        $this->assertFalse(Gate::forUser($this->staff)->allows('view', $this->orderB));
        $this->assertFalse(Gate::forUser($this->staff)->allows('update', $this->orderB));

        // Attempting to update Order B status as Location A staff must be 403 Forbidden
        $response = $this->actingAs($this->staff)->post(route('admin.orders.status', $this->orderB), [
            'status' => 'ready',
        ]);
        $response->assertStatus(403);
    }

    /**
     * 8. Location parameter tampering: Branch-assigned staff cannot switch active location to Branch B.
     */
    public function test_branch_assigned_user_cannot_tamper_location_id_query_parameter(): void
    {
        // Staff at Location A requests orders with ?location_id=Location B
        $response = $this->actingAs($this->staff)->get(route('admin.orders.index', [
            'location_id' => $this->locationB->id,
        ]));
        $response->assertStatus(403);

        // Staff at Location A requests dashboard with ?location_id=Location B
        $dashResponse = $this->actingAs($this->staff)->get(route('admin.dashboard', [
            'location_id' => $this->locationB->id,
        ]));
        $dashResponse->assertStatus(403);
    }

    /**
     * 9. SuperAdmin requires explicit platform permissions rather than an uncontrolled bypass.
     */
    public function test_superadmin_requires_explicit_platform_permissions_rather_than_uncontrolled_bypass(): void
    {
        // Normal superadmin has platform permissions
        $this->assertTrue($this->superAdmin->hasPermission(Permission::PLATFORM_ACCESS));
        $this->assertTrue($this->superAdmin->hasPermission(Permission::PLATFORM_VENDORS));

        // If platform.vendors permission is revoked, superadmin policy checks reject access
        $restrictedAdmin = User::create([
            'name' => 'Restricted Super Admin',
            'email' => 'restricted_admin@qrmenu.local',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
            'custom_permissions' => [
                'granted' => [],
                'denied' => [Permission::PLATFORM_VENDORS, Permission::PLATFORM_ACCESS],
            ],
        ]);

        $this->assertFalse($restrictedAdmin->hasPermission(Permission::PLATFORM_VENDORS));
        $this->assertFalse($restrictedAdmin->hasPermission(Permission::PLATFORM_ACCESS));

        // Policy checks reject the restricted superadmin
        $this->assertFalse(Gate::forUser($restrictedAdmin)->allows('view', $this->vendor));
        $this->assertFalse(Gate::forUser($restrictedAdmin)->allows('view', $this->orderA));
    }

    /**
     * 10. Custom permission overrides can grant or revoke specific abilities.
     */
    public function test_custom_permissions_override_role_defaults(): void
    {
        // Initially, staff cannot cancel orders
        $this->assertFalse($this->staff->hasPermission(Permission::ORDERS_CANCEL));

        // Grant explicit custom permission
        $this->staff->givePermission(Permission::ORDERS_CANCEL);
        $this->staff->save();

        $this->assertTrue($this->staff->fresh()->hasPermission(Permission::ORDERS_CANCEL));
        $this->assertTrue(Gate::forUser($this->staff->fresh())->allows('cancel', $this->orderA));

        // Revoke the permission
        $this->staff->revokePermission(Permission::ORDERS_CANCEL);
        $this->staff->save();

        $this->assertFalse($this->staff->fresh()->hasPermission(Permission::ORDERS_CANCEL));
        $this->assertFalse(Gate::forUser($this->staff->fresh())->allows('cancel', $this->orderA));
    }

    /**
     * 11. Privilege escalation prevention: Tenant user cannot create superadmin or vendor_owner.
     */
    public function test_privilege_escalation_is_strictly_prevented_in_team_invitations(): void
    {
        // Vendor owner attempting to create a superadmin role
        $responseSuper = $this->actingAs($this->owner)->post(route('admin.team.store'), [
            'name' => 'Fake Super Admin',
            'email' => 'fake_admin@example.com',
            'role' => 'superadmin',
            'password' => 'secret123',
        ]);
        $responseSuper->assertSessionHasErrors(['role']);

        // Vendor owner attempting to create a vendor_owner role
        $responseOwner = $this->actingAs($this->owner)->post(route('admin.team.store'), [
            'name' => 'Second Owner',
            'email' => 'second_owner@example.com',
            'role' => 'vendor_owner',
            'password' => 'secret123',
        ]);
        $responseOwner->assertSessionHasErrors(['role']);

        // Valid creation of a cashier succeeds
        $responseCashier = $this->actingAs($this->owner)->post(route('admin.team.store'), [
            'name' => 'Valid Cashier',
            'email' => 'valid_cashier@example.com',
            'role' => 'cashier',
            'location_id' => $this->locationA->id,
            'password' => 'secret123',
        ]);
        $responseCashier->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'valid_cashier@example.com',
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->locationA->id,
        ]);
    }
}
