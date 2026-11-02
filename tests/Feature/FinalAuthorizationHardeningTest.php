<?php

namespace Tests\Feature;

use App\Models\AuthorizationAuditLog;
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
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class FinalAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected AuthorizationService $authService;

    protected RoleManagementService $roleService;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected Vendor $vendorC;

    protected Location $locationA;

    protected Location $locationB1;

    protected Location $locationB2;

    protected Location $locationC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EnterpriseAuthorizationSeeder::class);

        $this->authService = app(AuthorizationService::class);
        $this->roleService = app(RoleManagementService::class);

        $plan = SubscriptionPlan::updateOrCreate(
            ['slug' => 'business'],
            [
                'name' => 'Business',
                'price' => 50000,
                'features' => ['team' => true, 'locations' => true, 'orders' => true, 'pos.access' => true],
                'feature_codes' => ['*'],
                'is_active' => true,
            ]
        );

        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'admin@bistro-alpha.am',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Bistro Beta',
            'slug' => 'bistro-beta',
            'email' => 'admin@bistro-beta.am',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->vendorC = Vendor::create([
            'name' => 'Bistro Gamma',
            'slug' => 'bistro-gamma',
            'email' => 'admin@bistro-gamma.am',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Main',
            'slug' => 'alpha-main',
            'is_active' => true,
        ]);

        $this->locationB1 = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Downtown',
            'slug' => 'beta-downtown',
            'is_active' => true,
        ]);

        $this->locationB2 = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Uptown',
            'slug' => 'beta-uptown',
            'is_active' => true,
        ]);

        $this->locationC = Location::create([
            'vendor_id' => $this->vendorC->id,
            'name' => 'Gamma Center',
            'slug' => 'gamma-center',
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create a user and assign a specific system role.
     */
    protected function createUserWithRole(Vendor $vendor, string $roleSlug): array
    {
        $user = User::create([
            'name' => ucfirst($roleSlug).' User',
            'email' => "{$roleSlug}_".uniqid().'@test.com',
            'password' => bcrypt('password123'),
            'role' => $roleSlug,
            'vendor_id' => $vendor->id,
            'is_active' => true,
        ]);

        $membership = $user->activeMembership($vendor->id);
        $roleModel = Role::whereNull('vendor_id')->where('slug', $roleSlug)->firstOrFail();
        $membership->roles()->sync([$roleModel->id]);
        $membership->update(['roles_migrated' => true]);
        $this->authService->invalidateCache($user->id, $vendor->id);

        return [$user, $membership, $roleModel];
    }

    /**
     * 1. Removing all roles from vendor_owner, manager, waiter, and cashier users
     *    results in ZERO role permissions and does NOT restore legacy users.role permissions.
     */
    public function test_removing_all_roles_strips_permissions_without_legacy_fallback(): void
    {
        $roleSlugs = ['vendor_owner', 'manager', 'waiter', 'cashier'];

        foreach ($roleSlugs as $roleSlug) {
            [$user, $membership] = $this->createUserWithRole($this->vendorA, $roleSlug);

            // Initially has permissions for that role
            $this->assertNotEmpty(
                $this->authService->getEffectivePermissions($user, $this->vendorA->id),
                "User with role {$roleSlug} should initially possess permissions."
            );

            // Act: Remove ALL roles from membership
            $membership->roles()->sync([]);
            $this->authService->invalidateCache($user->id, $this->vendorA->id);

            // Assert: Effective permissions must be EMPTY
            $effective = $this->authService->getEffectivePermissions($user, $this->vendorA->id);
            $this->assertEmpty(
                $effective,
                "Removing all roles from {$roleSlug} user must leave effective permissions empty."
            );

            // Specific permission checks must all return false
            $this->assertFalse(
                $this->authService->hasPermission($user, SecurityPermission::ORDERS_VIEW, $this->vendorA->id),
                "Role-less {$roleSlug} must not have orders.view"
            );
            $this->assertFalse(
                $this->authService->hasPermission($user, SecurityPermission::TEAM_MANAGE, $this->vendorA->id),
                "Role-less {$roleSlug} must not have team.manage"
            );
            $this->assertFalse(
                $this->authService->hasPermission($user, SecurityPermission::POS_ACCESS, $this->vendorA->id),
                "Role-less {$roleSlug} must not have pos.access"
            );

            // Model helper checks must reflect absence of the role
            if ($roleSlug === 'vendor_owner') {
                $this->assertFalse($user->isVendorOwner($this->vendorA->id));
            } elseif ($roleSlug === 'manager') {
                $this->assertFalse($user->isManager($this->vendorA->id));
            } elseif ($roleSlug === 'waiter') {
                $this->assertFalse($user->isWaiter($this->vendorA->id));
            } elseif ($roleSlug === 'cashier') {
                $this->assertFalse($user->isCashier($this->vendorA->id));
            }
        }
    }

    /**
     * 2. Explicit membership overrides are preserved when all roles are removed.
     */
    public function test_explicit_membership_overrides_preserved_when_all_roles_removed(): void
    {
        [$waiter, $membership] = $this->createUserWithRole($this->vendorA, 'waiter');

        // Grant an explicit override allow for menu.view
        $menuViewPerm = Permission::where('key', SecurityPermission::MENU_VIEW)->firstOrFail();
        MembershipPermission::create([
            'membership_id' => $membership->id,
            'permission_id' => $menuViewPerm->id,
            'effect' => 'allow',
        ]);

        // Explicit override deny for orders.view
        $ordersViewPerm = Permission::where('key', SecurityPermission::ORDERS_VIEW)->firstOrFail();
        MembershipPermission::create([
            'membership_id' => $membership->id,
            'permission_id' => $ordersViewPerm->id,
            'effect' => 'deny',
        ]);

        // Remove all roles
        $membership->roles()->sync([]);
        $this->authService->invalidateCache($waiter->id, $this->vendorA->id);

        $state = $this->authService->getPermissionState($waiter, $this->vendorA->id);

        // Explicit allow is preserved
        $this->assertContains(SecurityPermission::MENU_VIEW, $state['effective']);
        $this->assertTrue($this->authService->hasPermission($waiter, SecurityPermission::MENU_VIEW, $this->vendorA->id));

        // Explicit deny is preserved
        $this->assertContains(SecurityPermission::ORDERS_VIEW, $state['denied']);
        $this->assertFalse($this->authService->hasPermission($waiter, SecurityPermission::ORDERS_VIEW, $this->vendorA->id));

        // Other role permissions are completely gone
        $this->assertFalse($this->authService->hasPermission($waiter, SecurityPermission::POS_ACCESS, $this->vendorA->id));
    }

    /**
     * 3. Multi-Vendor Resource Authorization:
     *    A user whose primary vendor differs from secondary membership can access
     *    secondary vendor resources according to secondary membership roles and location scopes.
     */
    public function test_user_with_secondary_vendor_membership_can_access_authorized_resources(): void
    {
        // User primary vendor is Vendor A
        $user = User::create([
            'name' => 'Multi Tenant Waiter',
            'email' => 'multi@test.com',
            'password' => bcrypt('password123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        // Primary membership in Vendor A has 0 roles
        $memA = $user->activeMembership($this->vendorA->id);
        $memA->roles()->sync([]);

        // Create secondary valid active membership in Vendor B
        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();
        $memB = VendorMembership::create([
            'vendor_id' => $this->vendorB->id,
            'user_id' => $user->id,
            'status' => 'active',
            'all_locations' => false,
            'roles_migrated' => true,
            'joined_at' => now(),
        ]);
        $memB->roles()->sync([$waiterRole->id]);
        $memB->locations()->sync([$this->locationB1->id]); // Scoped only to B1 (Downtown)

        $this->authService->invalidateCache($user->id, $this->vendorB->id);

        // Create Order 1 in Vendor B at Location B1 (Downtown) assigned to user
        $orderB1 = Order::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB1->id,
            'waiter_id' => $user->id,
            'order_number' => 'ORD-B1-001',
            'status' => 'pending',
            'total_amount' => 1000,
        ]);

        // Create Order 2 in Vendor B at Location B2 (Uptown) - out of location scope
        $orderB2 = Order::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB2->id,
            'waiter_id' => $user->id,
            'order_number' => 'ORD-B2-002',
            'status' => 'pending',
            'total_amount' => 1500,
        ]);

        // Create Order 3 in Foreign Vendor C
        $orderC = Order::create([
            'vendor_id' => $this->vendorC->id,
            'location_id' => $this->locationC->id,
            'waiter_id' => null,
            'order_number' => 'ORD-C-003',
            'status' => 'pending',
            'total_amount' => 2000,
        ]);

        // Assert Order access
        $this->assertTrue($this->authService->canAccessOrder($user, $orderB1), 'User must access assigned order in secondary Vendor B at Location B1.');
        $this->assertFalse($this->authService->canAccessOrder($user, $orderB2), 'User must be denied access to order in secondary Vendor B at Location B2 (out of scope).');
        $this->assertFalse($this->authService->canAccessOrder($user, $orderC), 'User must be denied access to order in foreign Vendor C where no membership exists.');

        // Test Tables
        $tableB1 = RestaurantTable::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB1->id,
            'table_number' => 'T-B1-01',
            'is_active' => true,
        ]);
        $tableB2 = RestaurantTable::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB2->id,
            'table_number' => 'T-B2-01',
            'is_active' => true,
        ]);
        $tableC = RestaurantTable::create([
            'vendor_id' => $this->vendorC->id,
            'location_id' => $this->locationC->id,
            'table_number' => 'T-C-01',
            'is_active' => true,
        ]);

        $user->assignedTables()->attach($tableB1->id, ['vendor_id' => $this->vendorB->id]);
        $user->assignedTables()->attach($tableB2->id, ['vendor_id' => $this->vendorB->id]);
        $user->assignedTables()->attach($tableC->id, ['vendor_id' => $this->vendorC->id]);

        $this->assertTrue($this->authService->canAccessTable($user, $tableB1), 'User can access assigned table in Vendor B at B1.');
        $this->assertFalse($this->authService->canAccessTable($user, $tableB2), 'User denied access to table in Vendor B at B2 (location mismatch).');
        $this->assertFalse($this->authService->canAccessTable($user, $tableC), 'User denied access to table in Vendor C (no membership).');

        // Test when secondary membership is inactive
        $memB->update(['status' => 'inactive']);
        $this->authService->invalidateCache($user->id, $this->vendorB->id);
        $this->assertFalse($this->authService->canAccessOrder($user, $orderB1), 'User denied access when secondary membership is inactive.');
        $this->assertFalse($this->authService->canAccessTable($user, $tableB1), 'User denied access to table when secondary membership is inactive.');
    }

    /**
     * 4. Legacy vendor_owner role cannot bypass privilege escalation or membership restrictions.
     */
    public function test_legacy_vendor_owner_cannot_bypass_privilege_escalation(): void
    {
        // User has legacy users.role = 'vendor_owner', but membership in Vendor B only has 'waiter' role
        $user = User::create([
            'name' => 'Former Owner',
            'email' => 'former_owner@test.com',
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner', // Legacy column says vendor_owner
            'vendor_id' => $this->vendorB->id,
            'is_active' => true,
        ]);

        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();
        $membership = $user->activeMembership($this->vendorB->id);
        $membership->roles()->sync([$waiterRole->id]);
        $membership->update(['roles_migrated' => true]);
        $this->authService->invalidateCache($user->id, $this->vendorB->id);

        // Assert isVendorOwner in Vendor B is false
        $this->assertFalse($user->isVendorOwner($this->vendorB->id));

        // Create a target membership to assign roles
        $targetUser = User::create([
            'name' => 'Target User',
            'email' => 'target@test.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
            'vendor_id' => $this->vendorB->id,
            'is_active' => true,
        ]);
        $targetMem = $targetUser->activeMembership($this->vendorB->id);

        // The user tries to assign manager role (which possesses permissions the waiter lacks)
        $managerRole = Role::whereNull('vendor_id')->where('slug', 'manager')->firstOrFail();

        $this->expectException(AccessDeniedHttpException::class);
        $this->roleService->assignRolesToMembership($targetMem, [$managerRole->id], $user);
    }

    /**
     * 5. GET requests perform ZERO database mutations.
     */
    public function test_get_requests_do_not_mutate_authorization_tables(): void
    {
        [$owner, $membership] = $this->createUserWithRole($this->vendorA, 'vendor_owner');

        // Record exact counts before GET requests
        $permissionsCountBefore = DB::table('permissions')->count();
        $rolesCountBefore = DB::table('roles')->count();
        $membershipsCountBefore = DB::table('vendor_memberships')->count();
        $membershipRolesCountBefore = DB::table('membership_roles')->count();
        $membershipPermsCountBefore = DB::table('membership_permissions')->count();
        $auditLogsCountBefore = DB::table('authorization_audit_logs')->count();

        // Perform GET /admin/access-control
        $response1 = $this->actingAs($owner)->get(route('admin.access_control.index'));
        $response1->assertStatus(200);

        // Perform GET /admin/team
        $response2 = $this->actingAs($owner)->get(route('admin.team.index'));
        $response2->assertStatus(200);

        // Perform GET /admin/access-control/users/{user}/effective-permissions
        $response3 = $this->actingAs($owner)->getJson(
            route('admin.access_control.users.effective_permissions', $owner)
        );
        $response3->assertStatus(200);

        // Assert exact counts unchanged
        $this->assertSame($permissionsCountBefore, DB::table('permissions')->count(), 'permissions table count must not change on GET');
        $this->assertSame($rolesCountBefore, DB::table('roles')->count(), 'roles table count must not change on GET');
        $this->assertSame($membershipsCountBefore, DB::table('vendor_memberships')->count(), 'vendor_memberships table count must not change on GET');
        $this->assertSame($membershipRolesCountBefore, DB::table('membership_roles')->count(), 'membership_roles table count must not change on GET');
        $this->assertSame($membershipPermsCountBefore, DB::table('membership_permissions')->count(), 'membership_permissions table count must not change on GET');
        $this->assertSame($auditLogsCountBefore, DB::table('authorization_audit_logs')->count(), 'audit_logs table count must not change on GET');
    }

    /**
     * 6. Audit log immutability and metadata sanitization.
     */
    public function test_audit_logs_are_immutable_and_sanitize_sensitive_data(): void
    {
        [$owner] = $this->createUserWithRole($this->vendorA, 'vendor_owner');

        // Trigger an audit log via RoleManagementService with sensitive metadata
        $customRole = $this->roleService->createCustomRole(
            vendor: $this->vendorA,
            actor: $owner,
            data: [
                'name' => 'Audit Test Role',
                'slug' => 'audit-test-role',
                'description' => 'Role for audit log testing',
                'permissions' => [SecurityPermission::ORDERS_VIEW],
            ]
        );

        $log = AuthorizationAuditLog::where('resource_id', $customRole->id)
            ->where('action', 'role.created')
            ->firstOrFail();

        // 6a. Attempt to update via Eloquent throws exception
        try {
            $log->update(['action' => 'tampered.action']);
            $this->fail('Updating audit log via Eloquent must throw RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // 6b. Attempt to delete via Eloquent throws exception
        try {
            $log->delete();
            $this->fail('Deleting audit log via Eloquent must throw RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // 6c. Attempt direct SQL update/delete when pgsql trigger is present
        if (DB::getDriverName() === 'pgsql') {
            DB::beginTransaction();
            try {
                DB::table('authorization_audit_logs')
                    ->where('id', $log->id)
                    ->update(['action' => 'tampered.sql']);
                $this->fail('Updating audit log via direct SQL must throw trigger exception.');
            } catch (\Exception $e) {
                $this->assertStringContainsString('authorization_audit_logs is append-only', $e->getMessage());
            } finally {
                DB::rollBack();
            }

            DB::beginTransaction();
            try {
                DB::table('authorization_audit_logs')
                    ->where('id', $log->id)
                    ->delete();
                $this->fail('Deleting audit log via direct SQL must throw trigger exception.');
            } catch (\Exception $e) {
                $this->assertStringContainsString('authorization_audit_logs is append-only', $e->getMessage());
            } finally {
                DB::rollBack();
            }
        }

        // 6d. Sanitization test: sensitive keys are redacted
        $override = $this->roleService->setUserPermissionOverride(
            membership: $owner->activeMembership($this->vendorA->id),
            permissionKey: SecurityPermission::ORDERS_VIEW,
            effect: 'allow',
            actor: $owner
        );

        // Log manual audit with sensitive parameters to test sanitizeAuditData
        $auditEntry = AuthorizationAuditLog::create([
            'vendor_id' => $this->vendorA->id,
            'actor_id' => $owner->id,
            'action' => 'security.test',
            'metadata' => [
                'password' => 'secretpassword123',
                'pin_code' => '9999',
                'token' => 'bearer-abc-123',
                'safe_field' => 'visible_value',
            ],
            'created_at' => now(),
        ]);

        // Model booted will allow creation, and roleService sanitizes. Let's verify RoleManagementService sanitization
        $reflection = new \ReflectionClass($this->roleService);
        $sanitizeMethod = $reflection->getMethod('sanitizeAuditData');
        $sanitizeMethod->setAccessible(true);

        $sanitized = $sanitizeMethod->invoke($this->roleService, [
            'password' => 'plaintext_password',
            'pin' => '1234',
            'secret' => 'super_secret',
            'normal_key' => 'normal_value',
        ]);

        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['pin']);
        $this->assertSame('[REDACTED]', $sanitized['secret']);
        $this->assertSame('normal_value', $sanitized['normal_key']);
    }

    /**
     * 7. Deleting a user preserves audit log history without violating database integrity.
     */
    public function test_deleting_user_preserves_audit_log_history_and_metadata(): void
    {
        [$owner] = $this->createUserWithRole($this->vendorA, 'vendor_owner');

        $staffUser = User::create([
            'name' => 'To Be Deleted Staff',
            'email' => 'delete_me_'.uniqid().'@test.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        $membership = $staffUser->activeMembership($this->vendorA->id);
        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();

        // Assign role which creates audit log with target user
        $this->roleService->assignRolesToMembership($membership, [$waiterRole->id], $owner);

        $log = AuthorizationAuditLog::where('target_user_id', $staffUser->id)->firstOrFail();
        $logId = $log->id;
        $originalEmail = $staffUser->email;

        // Verify metadata captured the user identity before deletion
        $this->assertSame($originalEmail, $log->metadata['target_user_email'] ?? null);

        // Delete the user
        $staffUser->delete();

        // Audit log still exists
        $refreshedLog = AuthorizationAuditLog::find($logId);
        $this->assertNotNull($refreshedLog, 'Audit log must persist after user is deleted.');
        $this->assertNull($refreshedLog->target_user_id, 'Foreign key target_user_id set to null.');
        $this->assertSame($originalEmail, $refreshedLog->metadata['target_user_email']);
    }
}
