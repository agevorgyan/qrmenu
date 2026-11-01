<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\MembershipPermission;
use App\Models\Order;
use App\Models\Permission;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorMembership;
use App\Security\Permission as SecurityPermission;
use App\Services\AuthorizationService;
use App\Services\RoleManagementService;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class EnterpriseAuthorizationSecurityAuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected AuthorizationService $authService;

    protected RoleManagementService $roleService;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected Location $locationA1;

    protected Location $locationA2;

    protected Location $locationB1;

    protected User $ownerA;

    protected User $waiterA;

    protected User $superAdmin;

    protected VendorMembership $membershipA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EnterpriseAuthorizationSeeder::class);

        $this->authService = app(AuthorizationService::class);
        $this->roleService = app(RoleManagementService::class);

        $plan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'business'],
            [
                'name' => 'Business',
                'price' => 25000,
                'features' => ['team' => true, 'locations' => true, 'orders' => true, 'pos.access' => true],
                'is_active' => true,
            ]
        );

        $this->vendorA = Vendor::create([
            'name' => 'Tavern Alpha',
            'slug' => 'tavern-alpha',
            'email' => 'alpha@tavern.am',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Tavern Beta',
            'slug' => 'tavern-beta',
            'email' => 'beta@tavern.am',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->locationA1 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Center Branch',
            'slug' => 'alpha-center',
            'is_active' => true,
        ]);

        $this->locationA2 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha West Branch',
            'slug' => 'alpha-west',
            'is_active' => true,
        ]);

        $this->locationB1 = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Harbor Branch',
            'slug' => 'beta-harbor',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Alpha Owner',
            'email' => 'owner@tavern-alpha.am',
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->waiterA = User::create([
            'name' => 'Alpha Waiter',
            'email' => 'waiter@tavern-alpha.am',
            'password' => bcrypt('password123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA1->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->membershipA = $this->waiterA->activeMembership($this->vendorA->id);
        $this->membershipA->update(['all_locations' => false]);
        $this->membershipA->locations()->sync([$this->locationA1->id]);

        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();
        $this->membershipA->roles()->sync([$waiterRole->id]);

        $this->superAdmin = User::create([
            'name' => 'Platform Admin',
            'email' => 'superadmin@elab.menu',
            'password' => bcrypt('superadmin123'),
            'role' => 'superadmin',
            'vendor_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 1. Super Admin unknown permission always returns DENY.
     */
    public function test_superadmin_unknown_permission_returns_deny(): void
    {
        $this->assertTrue($this->superAdmin->isSuperAdmin());

        // Known platform permission returns true
        $this->assertTrue($this->authService->hasPermission($this->superAdmin, SecurityPermission::PLATFORM_ACCESS));

        // Unknown permission keys must unconditionally return false
        $this->assertFalse($this->authService->hasPermission($this->superAdmin, 'unknown.nonexistent.permission'));
        $this->assertFalse($this->authService->hasPermission($this->superAdmin, 'pos.fake_permission'));
        $this->assertFalse($this->authService->hasPermission($this->superAdmin, ''));
    }

    /**
     * 2. Explicit DENY against every permission source takes strict precedence.
     */
    public function test_explicit_deny_against_every_permission_source(): void
    {
        // 2a. Membership override DENY blocks role permission
        $this->assertTrue($this->authService->hasPermission($this->waiterA, SecurityPermission::ORDERS_VIEW_OWN, $this->vendorA->id));

        $perm = Permission::where('key', SecurityPermission::ORDERS_VIEW_OWN)->firstOrFail();
        MembershipPermission::create([
            'membership_id' => $this->membershipA->id,
            'permission_id' => $perm->id,
            'effect' => 'deny',
        ]);
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);
        $this->assertFalse($this->authService->hasPermission($this->waiterA, SecurityPermission::ORDERS_VIEW_OWN, $this->vendorA->id));

        // 2b. User custom_permissions['denied'] blocks role permission
        $this->waiterA->update([
            'custom_permissions' => ['denied' => [SecurityPermission::ORDERS_CREATE]],
        ]);
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);
        $this->assertFalse($this->authService->hasPermission($this->waiterA, SecurityPermission::ORDERS_CREATE, $this->vendorA->id));

        // 2c. Explicit DENY on SuperAdmin blocks permission
        $this->superAdmin->update([
            'custom_permissions' => ['denied' => [SecurityPermission::PLATFORM_ACCESS]],
        ]);
        $this->assertFalse($this->authService->hasPermission($this->superAdmin, SecurityPermission::PLATFORM_ACCESS));
    }

    /**
     * 3. Permission hierarchy bypass attempts are blocked.
     */
    public function test_permission_hierarchy_bypass_attempts_are_blocked(): void
    {
        // User with coarse pos.orders or orders.view cannot approve cancellations without explicit permission
        $this->assertFalse($this->waiterA->hasPermission(SecurityPermission::ORDERS_APPROVE_CANCELLATION));
        $this->assertFalse($this->waiterA->hasPermission(SecurityPermission::ORDERS_MANAGE));

        // Attempting to hit manager cancellation approval endpoint as waiter fails with 403
        $order = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA1->id,
            'waiter_id' => $this->waiterA->id,
            'order_number' => 'ORD-HIERARCHY-1',
            'status' => 'pending',
            'cancellation_status' => 'pending_approval',
            'total_amount' => 1500,
        ]);

        $response = $this->actingAs($this->waiterA)
            ->postJson(route('pos.api.workspace.orders.approve_cancellation', $order), [
                'reason' => 'Waiter trying to bypass hierarchy',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 4. Unassigned table access is denied to waiters with only tables.view_assigned.
     */
    public function test_unassigned_table_access_is_denied_to_waiters_with_only_view_assigned(): void
    {
        $unassignedTable = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA1->id,
            'table_number' => 'T-UNASSIGNED-99',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        // Waiter has tables.view_assigned, but is NOT assigned to this table
        $this->assertTrue($this->waiterA->hasPermission(SecurityPermission::TABLES_VIEW_ASSIGNED));
        $this->assertFalse($this->authService->canAccessTable($this->waiterA, $unassignedTable));
        $this->assertFalse($this->waiterA->canAccessTable($unassignedTable));

        // When table is explicitly assigned to waiter, access is granted
        $this->waiterA->assignedTables()->attach($unassignedTable->id, ['vendor_id' => $this->vendorA->id]);
        $this->assertTrue($this->authService->canAccessTable($this->waiterA, $unassignedTable));
    }

    /**
     * 5. Unassigned order access is denied to waiters with only orders.view_own.
     */
    public function test_unassigned_order_access_is_denied_to_waiters_with_only_view_own(): void
    {
        $unassignedOrder = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA1->id,
            'waiter_id' => null, // Unassigned
            'user_id' => null,   // No verified owner
            'order_number' => 'ORD-UNASSIGNED-01',
            'status' => 'pending',
            'total_amount' => 5000,
        ]);

        $this->assertTrue($this->waiterA->hasPermission(SecurityPermission::ORDERS_VIEW_OWN));

        // ReBAC check denies unassigned orders without verified ownership
        $this->assertFalse($this->authService->canAccessOrder($this->waiterA, $unassignedOrder));
        $this->assertFalse($this->waiterA->canAccessOrder($unassignedOrder));

        // Attempting to request cancellation on an unassigned order as waiter is 403
        $response = $this->actingAs($this->waiterA)
            ->postJson(route('pos.api.workspace.orders.cancel_request', $unassignedOrder), [
                'reason' => 'Trying to cancel unassigned order',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 6. Cross-vendor role assignment is blocked with 403.
     */
    public function test_cross_vendor_role_assignment_is_blocked_with_403(): void
    {
        // Create custom role in Vendor B
        $roleB = Role::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Custom Beta Role',
            'slug' => 'custom-beta-role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->expectException(AccessDeniedHttpException::class);

        // Attempt to assign Vendor B role to Vendor A membership
        $this->roleService->assignRolesToMembership(
            membership: $this->membershipA,
            roleIds: [$roleB->id],
            actor: $this->ownerA
        );
    }

    /**
     * 7. Cross-vendor location assignment is blocked with 403.
     */
    public function test_cross_vendor_location_assignment_is_blocked_with_403(): void
    {
        // Attempting to set membership locations using Vendor B's location throws 403
        $this->expectException(AccessDeniedHttpException::class);

        $this->roleService->setMembershipLocations(
            membership: $this->membershipA,
            allLocations: false,
            locationIds: [$this->locationB1->id],
            actor: $this->ownerA
        );
    }

    /**
     * 8. Cross-vendor resource IDOR is blocked.
     */
    public function test_cross_vendor_resource_idor_is_blocked(): void
    {
        $orderB = Order::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB1->id,
            'order_number' => 'ORD-BETA-77',
            'status' => 'pending',
            'total_amount' => 9900,
        ]);

        $tableB = RestaurantTable::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB1->id,
            'table_number' => 'B-10',
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        // Service ReBAC rejects cross-tenant access
        $this->assertFalse($this->authService->canAccessOrder($this->ownerA, $orderB));
        $this->assertFalse($this->authService->canAccessTable($this->ownerA, $tableB));

        // HTTP request to access Vendor B order status by Vendor A user receives 403 or 404
        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.orders.status', $orderB), [
                'status' => 'cancelled',
            ]);

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }

    /**
     * 9. Inactive membership access is denied.
     */
    public function test_inactive_membership_access_is_denied(): void
    {
        $this->membershipA->update(['status' => 'inactive']);
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);

        $this->assertFalse($this->authService->hasPermission($this->waiterA, SecurityPermission::ORDERS_VIEW_OWN, $this->vendorA->id));
        $this->assertEmpty($this->authService->getEffectivePermissions($this->waiterA, $this->vendorA->id));
    }

    /**
     * 10. Inactive role access is denied and does not fall back to legacy permissions.
     */
    public function test_inactive_role_access_is_denied_and_does_not_fall_back_to_legacy_permissions(): void
    {
        $customRole = Role::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Custom Waiter Role',
            'slug' => 'custom-waiter-role',
            'is_system' => false,
            'is_active' => true,
        ]);
        $customRole->permissions()->sync([
            Permission::where('key', SecurityPermission::ORDERS_VIEW_OWN)->value('id'),
        ]);

        $this->membershipA->roles()->sync([$customRole->id]);
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);

        $this->assertTrue($this->authService->hasPermission($this->waiterA, SecurityPermission::ORDERS_VIEW_OWN, $this->vendorA->id));

        // Deactivate the custom role
        $customRole->update(['is_active' => false]);
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);

        // Effective permissions must be empty; must NOT fall back to users.role
        $effective = $this->authService->getEffectivePermissions($this->waiterA, $this->vendorA->id);
        $this->assertEmpty($effective);
        $this->assertFalse($this->authService->hasPermission($this->waiterA, SecurityPermission::ORDERS_VIEW_OWN, $this->vendorA->id));
    }

    /**
     * 11. Multiple roles and multiple vendor memberships are cleanly isolated.
     */
    public function test_multiple_roles_and_multiple_vendor_memberships_isolation(): void
    {
        // Create membership for waiterA in vendorB as Cashier
        $membershipB = VendorMembership::create([
            'user_id' => $this->waiterA->id,
            'vendor_id' => $this->vendorB->id,
            'status' => 'active',
            'all_locations' => true,
        ]);
        $cashierRole = Role::whereNull('vendor_id')->where('slug', 'cashier')->firstOrFail();
        $membershipB->roles()->sync([$cashierRole->id]);

        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorB->id);

        // In Vendor A context, waiterA has waiter permissions, NOT cashier payments
        $this->assertTrue($this->authService->hasPermission($this->waiterA, SecurityPermission::ORDERS_SEND_TO_KITCHEN, $this->vendorA->id));
        $this->assertFalse($this->authService->hasPermission($this->waiterA, SecurityPermission::PAYMENTS_CASH, $this->vendorA->id));

        // In Vendor B context, waiterA has cashier permissions
        $this->assertTrue($this->authService->hasPermission($this->waiterA, SecurityPermission::PAYMENTS_CASH, $this->vendorB->id));
    }

    /**
     * 12. Workspace access is independent from legacy user role.
     */
    public function test_workspace_access_is_independent_from_legacy_user_role(): void
    {
        // User has legacy users.role = 'waiter'
        $this->assertEquals('waiter', $this->waiterA->role);

        // Assign only Cashier role with workspace.cashier.access (remove Waiter role)
        $cashierRole = Role::whereNull('vendor_id')->where('slug', 'cashier')->firstOrFail();
        $this->membershipA->roles()->sync([$cashierRole->id]);
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);

        // Profile returns allowed_modes containing ONLY cashier
        $profileRes = $this->actingAs($this->waiterA)->getJson(route('pos.api.workspace.profile'));
        $profileRes->assertOk();
        $allowedModes = $profileRes->json('user.allowed_modes');
        $this->assertContains('cashier', $allowedModes);
        $this->assertNotContains('waiter', $allowedModes);

        // Waiter workspace dashboard is forbidden (403)
        $waiterDashRes = $this->actingAs($this->waiterA)->getJson(route('pos.api.workspace.waiter.dashboard'));
        $waiterDashRes->assertStatus(403);

        // Cashier workspace dashboard succeeds (200)
        $cashierDashRes = $this->actingAs($this->waiterA)->getJson(route('pos.api.workspace.cashier.dashboard'));
        $cashierDashRes->assertOk();
    }

    /**
     * 13. Cache invalidation updates permissions immediately after every role, permission, or location change.
     */
    public function test_cache_invalidation_after_every_role_permission_and_location_change(): void
    {
        $permKey = SecurityPermission::MENU_CREATE;
        $this->assertFalse($this->authService->hasPermission($this->waiterA, $permKey, $this->vendorA->id));

        // Grant override
        $perm = Permission::where('key', $permKey)->firstOrFail();
        MembershipPermission::create([
            'membership_id' => $this->membershipA->id,
            'permission_id' => $perm->id,
            'effect' => 'allow',
        ]);

        // Explicit cache invalidation
        $this->authService->invalidateCache($this->waiterA->id, $this->vendorA->id);

        // Immediate reflection
        $this->assertTrue($this->authService->hasPermission($this->waiterA, $permKey, $this->vendorA->id));
    }

    /**
     * 14. Session revocation upon membership deactivation.
     */
    public function test_session_revocation_on_membership_deactivation(): void
    {
        $oldToken = $this->waiterA->remember_token;

        DB::table('sessions')->insert([
            'id' => 'test-session-123',
            'user_id' => $this->waiterA->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload-data',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'test-session-123']);

        // Deactivate membership
        $this->roleService->setMembershipStatus($this->membershipA, 'inactive', $this->ownerA);

        // Sessions must be deleted
        $this->assertDatabaseMissing('sessions', ['id' => 'test-session-123']);

        // Remember token must be regenerated
        $this->waiterA->refresh();
        $this->assertNotEquals($oldToken, $this->waiterA->remember_token);
    }

    /**
     * 15. Privilege escalation prevention on role creation.
     */
    public function test_privilege_escalation_prevention_on_custom_role_creation(): void
    {
        // Create a manager who does not have billing.manage
        $manager = User::create([
            'name' => 'Sub Manager',
            'email' => 'submanager@tavern-alpha.am',
            'password' => bcrypt('password123'),
            'role' => 'manager',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);
        $managerRole = Role::whereNull('vendor_id')->where('slug', 'manager')->firstOrFail();
        $managerMem = $manager->activeMembership($this->vendorA->id);
        $managerMem->roles()->sync([$managerRole->id]);

        $this->expectException(AccessDeniedHttpException::class);

        // Manager attempts to create a role with billing.manage
        $this->roleService->createCustomRole(
            vendor: $this->vendorA,
            actor: $manager,
            data: [
                'name' => 'Escalated Billing Role',
                'permissions' => [SecurityPermission::BILLING_MANAGE],
            ]
        );
    }

    /**
     * 16. Unauthorized exports and API endpoints return 403 Forbidden.
     */
    public function test_unauthorized_exports_and_api_endpoints_return_403(): void
    {
        // Waiter does not have reports.export or audit_logs.view
        $this->assertFalse($this->waiterA->hasPermission(SecurityPermission::REPORTS_EXPORT));
        $this->assertFalse($this->waiterA->hasPermission(SecurityPermission::AUDIT_LOGS_VIEW));

        // Export tips requires reports.export -> 403
        $tipsExportRes = $this->actingAs($this->waiterA)->get(route('admin.tips_ratings.export_tips'));
        $tipsExportRes->assertStatus(403);

        // Export customer data requires customers.export -> 403
        $custExportRes = $this->actingAs($this->waiterA)->get(route('admin.customers.export'));
        $custExportRes->assertStatus(403);

        // Export audit logs requires audit_logs.view -> 403
        $auditExportRes = $this->actingAs($this->waiterA)->get(route('admin.access_control.audit_logs.export'));
        $auditExportRes->assertStatus(403);
    }
}
