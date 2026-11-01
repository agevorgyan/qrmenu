<?php

namespace Tests\Feature;

use App\Models\AuthorizationAuditLog;
use App\Models\Location;
use App\Models\MembershipPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorMembership;
use App\Security\PermissionCatalog;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnterpriseAuthorizationDatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected Location $locationA1;

    protected Location $locationA2;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(EnterpriseAuthorizationSeeder::class);

        $businessPlan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@test.com',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Bistro Beta',
            'slug' => 'bistro-beta',
            'email' => 'beta@test.com',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->locationA1 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown',
            'is_active' => true,
        ]);

        $this->locationA2 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Uptown Branch',
            'slug' => 'uptown',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Armen Worker',
            'email' => 'armen@worker.am',
            'password' => bcrypt('password123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);
    }

    public function test_all_enterprise_authorization_tables_exist(): void
    {
        $tables = [
            'roles',
            'permissions',
            'role_permissions',
            'vendor_memberships',
            'membership_roles',
            'membership_locations',
            'membership_permissions',
            'authorization_audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} does not exist.");
        }
    }

    public function test_system_roles_and_permissions_are_seeded(): void
    {
        $this->assertGreaterThan(50, Permission::count());
        $this->assertGreaterThan(10, Role::where('is_system', true)->count());

        $ownerRole = Role::whereNull('vendor_id')->where('slug', 'vendor_owner')->first();
        $this->assertNotNull($ownerRole);
        $this->assertTrue($ownerRole->is_system);
        $this->assertGreaterThan(20, $ownerRole->permissions()->count());
    }

    public function test_custom_roles_belong_to_a_vendor_and_are_isolated(): void
    {
        $customRoleA = Role::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Senior Sommelier',
            'slug' => 'senior-sommelier',
            'is_system' => false,
            'is_active' => true,
        ]);

        $customRoleB = Role::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Senior Sommelier',
            'slug' => 'senior-sommelier',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->assertEquals($this->vendorA->id, $customRoleA->vendor_id);
        $this->assertEquals($this->vendorB->id, $customRoleB->vendor_id);

        $rolesForA = Role::forVendor($this->vendorA->id)->get();
        $this->assertTrue($rolesForA->contains('id', $customRoleA->id));
        $this->assertFalse($rolesForA->contains('id', $customRoleB->id));
    }

    public function test_user_can_have_memberships_across_different_vendors(): void
    {
        $membershipA = $this->user->activeMembership($this->vendorA->id);
        $this->assertNotNull($membershipA);

        $membershipB = VendorMembership::create([
            'vendor_id' => $this->vendorB->id,
            'user_id' => $this->user->id,
            'status' => 'active',
            'all_locations' => false,
        ]);

        $this->assertEquals(2, $this->user->memberships()->count());
        $this->assertEquals($membershipA->id, $this->user->activeMembership($this->vendorA->id)?->id);
        $this->assertEquals($membershipB->id, $this->user->activeMembership($this->vendorB->id)?->id);
    }

    public function test_membership_can_have_multiple_roles(): void
    {
        $membership = $this->user->activeMembership($this->vendorA->id);
        $membership->roles()->detach();

        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();
        $cashierRole = Role::whereNull('vendor_id')->where('slug', 'cashier')->firstOrFail();

        $membership->roles()->attach([$waiterRole->id, $cashierRole->id]);

        $this->assertEquals(2, $membership->roles()->count());
        $this->assertTrue($membership->roles->contains('slug', 'waiter'));
        $this->assertTrue($membership->roles->contains('slug', 'cashier'));
    }

    public function test_membership_can_have_scoped_locations_or_all_locations(): void
    {
        $membership = $this->user->activeMembership($this->vendorA->id);
        $membership->update(['all_locations' => false]);
        $membership->locations()->sync([$this->locationA1->id]);

        $this->assertTrue($membership->canAccessLocationId($this->locationA1->id));
        $this->assertFalse($membership->canAccessLocationId($this->locationA2->id));

        // When all_locations is true, any location of that vendor is accessible
        $membership->update(['all_locations' => true]);
        $this->assertTrue($membership->canAccessLocationId($this->locationA2->id));
    }

    public function test_membership_permission_overrides_with_expiry(): void
    {
        $membership = $this->user->activeMembership($this->vendorA->id);

        $perm = Permission::where('key', PermissionCatalog::all()[0]['key'])->firstOrFail();

        $override = MembershipPermission::create([
            'membership_id' => $membership->id,
            'permission_id' => $perm->id,
            'effect' => 'deny',
            'expires_at' => now()->addHour(),
            'reason' => 'Temporary restriction',
        ]);

        $this->assertTrue($override->isDeny());
        $this->assertFalse($override->isExpired());

        // Fast forward
        $override->update(['expires_at' => now()->subMinute()]);
        $this->assertTrue($override->isExpired());
    }

    public function test_authorization_audit_log_persists_immutable_events(): void
    {
        $log = AuthorizationAuditLog::create([
            'vendor_id' => $this->vendorA->id,
            'actor_id' => $this->user->id,
            'target_user_id' => $this->user->id,
            'action' => 'role.assigned',
            'resource_type' => 'Role',
            'resource_id' => 1,
            'old_values' => ['roles' => ['waiter']],
            'new_values' => ['roles' => ['waiter', 'cashier']],
            'metadata' => ['reason' => 'Promotion'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertDatabaseHas('authorization_audit_logs', [
            'id' => $log->id,
            'action' => 'role.assigned',
            'vendor_id' => $this->vendorA->id,
        ]);
        $this->assertEquals(['roles' => ['waiter', 'cashier']], $log->fresh()->new_values);
    }
}
