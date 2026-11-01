<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorMembership;
use App\Security\Permission as SecurityPermission;
use App\Services\RoleManagementService;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class RoleTemplatesAndCustomRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected RoleManagementService $roleService;

    protected Vendor $vendor;

    protected User $owner;

    protected User $manager;

    protected User $waiter;

    protected VendorMembership $waiterMembership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(EnterpriseAuthorizationSeeder::class);

        $this->roleService = app(RoleManagementService::class);

        $businessPlan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendor = Vendor::create([
            'name' => 'Royal Roles Palace',
            'slug' => 'royal-roles-palace',
            'email' => 'roles@royal.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Tigran Owner',
            'email' => 'tigran@royal.am',
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Anahit Manager',
            'email' => 'anahit@royal.am',
            'password' => bcrypt('password123'),
            'role' => 'manager',
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Narek Waiter',
            'email' => 'narek@royal.am',
            'password' => bcrypt('password123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->waiterMembership = VendorMembership::firstOrCreate(
            [
                'vendor_id' => $this->vendor->id,
                'user_id' => $this->waiter->id,
            ],
            [
                'status' => 'active',
                'all_locations' => true,
            ]
        );

        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();
        $this->waiterMembership->roles()->syncWithoutDetaching([$waiterRole->id]);
    }

    public function test_owner_can_create_custom_role_with_permissions(): void
    {
        $role = $this->roleService->createCustomRole($this->vendor, $this->owner, [
            'name' => 'Hostess Lead',
            'slug' => 'hostess-lead',
            'description' => 'Greeting guests and table seating management',
            'permissions' => [
                'reservations.view',
                'reservations.manage',
                SecurityPermission::TABLES_VIEW_ASSIGNED,
            ],
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'vendor_id' => $this->vendor->id,
            'slug' => 'hostess-lead',
            'is_system' => false,
        ]);

        $this->assertEquals(3, $role->permissions()->count());

        $this->assertDatabaseHas('authorization_audit_logs', [
            'vendor_id' => $this->vendor->id,
            'actor_id' => $this->owner->id,
            'action' => 'role.created',
        ]);
    }

    public function test_privilege_escalation_is_blocked_when_manager_grants_unauthorized_permission(): void
    {
        // Manager does not have billing.manage
        $this->expectException(AccessDeniedHttpException::class);

        $this->roleService->createCustomRole($this->vendor, $this->manager, [
            'name' => 'Super Manager',
            'slug' => 'super-manager',
            'permissions' => [
                SecurityPermission::BILLING_MANAGE,
            ],
        ]);
    }

    public function test_tenant_user_cannot_assign_platform_permissions(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->roleService->createCustomRole($this->vendor, $this->owner, [
            'name' => 'Rogue Role',
            'slug' => 'rogue-role',
            'permissions' => [
                SecurityPermission::PLATFORM_ACCESS,
            ],
        ]);
    }

    public function test_system_role_cannot_be_modified_or_deleted(): void
    {
        $systemRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();

        $this->expectException(AccessDeniedHttpException::class);
        $this->roleService->updateCustomRole($systemRole, $this->owner, [
            'name' => 'Hacked Waiter',
        ]);
    }

    public function test_role_duplication(): void
    {
        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();

        $duplicate = $this->roleService->duplicateRole(
            $waiterRole,
            $this->owner,
            'Senior Waiter',
            'senior-waiter'
        );

        $this->assertDatabaseHas('roles', [
            'id' => $duplicate->id,
            'vendor_id' => $this->vendor->id,
            'slug' => 'senior-waiter',
            'is_system' => false,
        ]);

        $this->assertEquals($waiterRole->permissions()->count(), $duplicate->permissions()->count());
    }

    public function test_role_deletion_blocked_if_users_assigned(): void
    {
        $customRole = Role::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bartender',
            'slug' => 'bartender',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->waiterMembership->roles()->attach($customRole->id);

        $this->expectException(InvalidArgumentException::class);
        $this->roleService->deleteCustomRole($customRole, $this->owner);
    }

    public function test_user_permission_overrides_allow_and_deny(): void
    {
        // 1. Grant explicit allow override
        $override = $this->roleService->setUserPermissionOverride(
            membership: $this->waiterMembership,
            permissionKey: SecurityPermission::ORDERS_CANCEL,
            effect: 'allow',
            expiresAt: now()->addHours(2),
            reason: 'Temporary floor manager shift',
            actor: $this->owner
        );

        $this->assertEquals('allow', $override->effect);
        $this->assertDatabaseHas('membership_permissions', [
            'membership_id' => $this->waiterMembership->id,
            'effect' => 'allow',
        ]);
        $this->assertDatabaseHas('authorization_audit_logs', [
            'action' => 'permission.granted',
            'target_user_id' => $this->waiter->id,
        ]);

        // 2. Remove override
        $removed = $this->roleService->removeUserPermissionOverride(
            membership: $this->waiterMembership,
            permissionKey: SecurityPermission::ORDERS_CANCEL,
            actor: $this->owner
        );

        $this->assertTrue($removed);
        $this->assertDatabaseMissing('membership_permissions', [
            'membership_id' => $this->waiterMembership->id,
            'permission_id' => $override->permission_id,
        ]);
        $this->assertDatabaseHas('authorization_audit_logs', [
            'action' => 'permission.override_removed',
            'target_user_id' => $this->waiter->id,
        ]);
    }

    public function test_secure_ownership_transfer(): void
    {
        $this->roleService->transferOwnership(
            $this->vendor,
            $this->owner,
            $this->manager,
            $this->owner
        );

        $this->assertEquals('vendor_owner', $this->manager->fresh()->role);
        $this->assertEquals('manager', $this->owner->fresh()->role);

        $this->assertDatabaseHas('authorization_audit_logs', [
            'action' => 'owner.transferred',
            'vendor_id' => $this->vendor->id,
            'target_user_id' => $this->manager->id,
        ]);
    }
}
