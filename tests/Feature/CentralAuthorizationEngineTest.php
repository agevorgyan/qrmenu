<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\MembershipPermission;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorMembership;
use App\Security\Permission as SecurityPermission;
use App\Services\AuthorizationService;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralAuthorizationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected AuthorizationService $authService;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected Location $locationA1;

    protected Location $locationA2;

    protected User $userA;

    protected VendorMembership $membershipA;

    protected Role $waiterRole;

    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(EnterpriseAuthorizationSeeder::class);

        $this->authService = app(AuthorizationService::class);

        $businessPlan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendorA = Vendor::create([
            'name' => 'Bistro Engine Alpha',
            'slug' => 'bistro-engine-alpha',
            'email' => 'engine.alpha@test.com',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Bistro Engine Beta',
            'slug' => 'bistro-engine-beta',
            'email' => 'engine.beta@test.com',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->locationA1 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Branch 1',
            'slug' => 'branch-1',
            'is_active' => true,
        ]);

        $this->locationA2 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Branch 2',
            'slug' => 'branch-2',
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'name' => 'Suren Employee',
            'email' => 'suren@engine.am',
            'password' => bcrypt('secret123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        $this->membershipA = $this->userA->activeMembership($this->vendorA->id);
        $this->membershipA->update(['all_locations' => false]);

        $this->waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();
        $this->cashierRole = Role::whereNull('vendor_id')->where('slug', 'cashier')->firstOrFail();

        $this->membershipA->roles()->sync([$this->waiterRole->id]);
        $this->membershipA->locations()->sync([$this->locationA1->id]);
    }

    public function test_inactive_user_account_is_denied_by_default(): void
    {
        $this->userA->update(['is_active' => false]);

        $this->assertFalse($this->authService->hasPermission($this->userA, SecurityPermission::ORDERS_CREATE));
        $this->assertEmpty($this->authService->getEffectivePermissions($this->userA));
    }

    public function test_inactive_vendor_membership_is_denied_access(): void
    {
        $this->membershipA->update(['status' => 'inactive']);
        $this->authService->invalidateCache($this->userA->id, $this->vendorA->id);

        $this->assertFalse($this->authService->hasPermission($this->userA, SecurityPermission::ORDERS_CREATE, $this->vendorA->id));
        $this->assertEmpty($this->authService->getEffectivePermissions($this->userA, $this->vendorA->id));
    }

    public function test_cross_tenant_isolation_denies_permission_checks_for_unauthorized_vendor(): void
    {
        // userA has no membership in vendorB
        $this->assertFalse($this->authService->hasPermission($this->userA, SecurityPermission::ORDERS_CREATE, $this->vendorB->id));
    }

    public function test_explicit_membership_deny_override_takes_precedence_over_role_permissions(): void
    {
        // Waiter role naturally grants orders.view_own
        $this->assertTrue($this->authService->hasPermission($this->userA, SecurityPermission::ORDERS_VIEW_OWN, $this->vendorA->id));

        $perm = Permission::where('key', SecurityPermission::ORDERS_VIEW_OWN)->firstOrFail();

        // Add explicit DENY override
        MembershipPermission::create([
            'membership_id' => $this->membershipA->id,
            'permission_id' => $perm->id,
            'effect' => 'deny',
        ]);
        $this->authService->invalidateCache($this->userA->id, $this->vendorA->id);

        $this->assertFalse($this->authService->hasPermission($this->userA, SecurityPermission::ORDERS_VIEW_OWN, $this->vendorA->id));
    }

    public function test_explicit_membership_allow_override_grants_permission(): void
    {
        // Waiter role does not have reports.view
        $this->assertFalse($this->authService->hasPermission($this->userA, SecurityPermission::REPORTS_VIEW, $this->vendorA->id));

        $perm = Permission::where('key', SecurityPermission::REPORTS_VIEW)->firstOrFail();

        MembershipPermission::create([
            'membership_id' => $this->membershipA->id,
            'permission_id' => $perm->id,
            'effect' => 'allow',
        ]);
        $this->authService->invalidateCache($this->userA->id, $this->vendorA->id);

        $this->assertTrue($this->authService->hasPermission($this->userA, SecurityPermission::REPORTS_VIEW, $this->vendorA->id));
    }

    public function test_multiple_assigned_roles_aggregate_permissions(): void
    {
        // Attach Cashier role to existing Waiter membership
        $this->membershipA->roles()->attach($this->cashierRole->id);
        $this->authService->invalidateCache($this->userA->id, $this->vendorA->id);

        // Has waiter permissions
        $this->assertTrue($this->authService->hasPermission($this->userA, SecurityPermission::ORDERS_SEND_TO_KITCHEN, $this->vendorA->id));
        // Also has cashier permissions
        $this->assertTrue($this->authService->hasPermission($this->userA, SecurityPermission::PAYMENTS_CASH, $this->vendorA->id));
    }

    public function test_location_and_branch_access_scoping(): void
    {
        // User assigned to locationA1 only
        $this->assertTrue($this->authService->canAccessLocation($this->userA, $this->locationA1, $this->vendorA->id));
        $this->assertFalse($this->authService->canAccessLocation($this->userA, $this->locationA2, $this->vendorA->id));

        // Switch to all_locations = true
        $this->membershipA->update(['all_locations' => true]);
        $this->assertTrue($this->authService->canAccessLocation($this->userA, $this->locationA2, $this->vendorA->id));
    }

    public function test_rebac_order_and_table_relationship_enforcement(): void
    {
        $otherUser = User::create([
            'name' => 'Other Waiter',
            'email' => 'other.waiter@engine.am',
            'password' => bcrypt('secret123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        $ownOrder = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA1->id,
            'waiter_id' => $this->userA->id,
            'order_number' => 'ORD-SUREN-1',
            'status' => 'confirmed',
            'total_amount' => 2000,
        ]);

        $otherOrder = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA1->id,
            'waiter_id' => $otherUser->id,
            'order_number' => 'ORD-OTHER-1',
            'status' => 'confirmed',
            'total_amount' => 4000,
        ]);

        // Waiter can access own order
        $this->assertTrue($this->authService->canAccessOrder($this->userA, $ownOrder));

        // Waiter without orders.view_all cannot access other waiter's order
        $this->assertFalse($this->authService->canAccessOrder($this->userA, $otherOrder));
    }

    public function test_cache_invalidation_updates_permissions_immediately(): void
    {
        $perm = Permission::where('key', SecurityPermission::MENU_DELETE)->firstOrFail();

        // Initially false
        $this->assertFalse($this->authService->hasPermission($this->userA, SecurityPermission::MENU_DELETE, $this->vendorA->id));

        // Grant override
        MembershipPermission::create([
            'membership_id' => $this->membershipA->id,
            'permission_id' => $perm->id,
            'effect' => 'allow',
        ]);

        // Invalidate cache
        $this->authService->invalidateCache($this->userA->id, $this->vendorA->id);

        // Immediately reflected
        $this->assertTrue($this->authService->hasPermission($this->userA, SecurityPermission::MENU_DELETE, $this->vendorA->id));
    }
}
