<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorMembership;
use App\Services\RoleManagementService;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class EnterpriseAuthorizationSecurityAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected RoleManagementService $roleService;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected Location $locationA1;

    protected Location $locationA2;

    protected User $ownerA;

    protected User $managerA;

    protected User $waiterA;

    protected VendorMembership $waiterMembershipA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(EnterpriseAuthorizationSeeder::class);

        $this->roleService = app(RoleManagementService::class);

        $businessPlan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendorA = Vendor::create([
            'name' => 'Audit Security Alpha',
            'slug' => 'audit-security-alpha',
            'email' => 'alpha@audit.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Audit Security Beta',
            'slug' => 'audit-security-beta',
            'email' => 'beta@audit.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->locationA1 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Main Center',
            'slug' => 'main-center',
            'is_active' => true,
        ]);

        $this->locationA2 = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Sub Branch',
            'slug' => 'sub-branch',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Gagik Owner',
            'email' => 'gagik@audit.am',
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        $this->managerA = User::create([
            'name' => 'Hasmik Manager',
            'email' => 'hasmik@audit.am',
            'password' => bcrypt('password123'),
            'role' => 'manager',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        $this->waiterA = User::create([
            'name' => 'Karen Waiter',
            'email' => 'karen@audit.am',
            'password' => bcrypt('password123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        $this->waiterMembershipA = $this->waiterA->activeMembership($this->vendorA->id);
    }

    public function test_assign_roles_to_membership_audited_and_enforced(): void
    {
        $cashierRole = Role::whereNull('vendor_id')->where('slug', 'cashier')->firstOrFail();
        $waiterRole = Role::whereNull('vendor_id')->where('slug', 'waiter')->firstOrFail();

        $this->roleService->assignRolesToMembership(
            $this->waiterMembershipA,
            [$cashierRole->id, $waiterRole->id],
            $this->ownerA
        );

        $this->assertEquals(2, $this->waiterMembershipA->roles()->count());

        $this->assertDatabaseHas('authorization_audit_logs', [
            'vendor_id' => $this->vendorA->id,
            'actor_id' => $this->ownerA->id,
            'target_user_id' => $this->waiterA->id,
            'action' => 'user.roles_assigned',
        ]);
    }

    public function test_cross_tenant_role_assignment_is_strictly_blocked(): void
    {
        // Custom role belonging exclusively to vendor B
        $roleB = Role::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Sommelier',
            'slug' => 'beta-sommelier',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->expectException(AccessDeniedHttpException::class);

        // Attempt to assign vendor B's role to vendor A's employee
        $this->roleService->assignRolesToMembership(
            $this->waiterMembershipA,
            [$roleB->id],
            $this->ownerA
        );
    }

    public function test_location_access_change_is_audited_and_scoped(): void
    {
        $this->roleService->setMembershipLocations(
            $this->waiterMembershipA,
            false,
            [$this->locationA1->id],
            $this->ownerA
        );

        $this->assertFalse($this->waiterMembershipA->fresh()->all_locations);
        $this->assertTrue($this->waiterA->canAccessLocation($this->locationA1));
        $this->assertFalse($this->waiterA->canAccessLocation($this->locationA2));

        $this->assertDatabaseHas('authorization_audit_logs', [
            'vendor_id' => $this->vendorA->id,
            'actor_id' => $this->ownerA->id,
            'target_user_id' => $this->waiterA->id,
            'action' => 'user.locations_changed',
        ]);
    }

    public function test_membership_deactivation_revokes_sessions_and_audits(): void
    {
        // Insert dummy session
        DB::table('sessions')->insert([
            'id' => 'sess_audit_test_123',
            'user_id' => $this->waiterA->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'sess_audit_test_123']);

        $this->roleService->setMembershipStatus(
            $this->waiterMembershipA,
            'inactive',
            $this->ownerA
        );

        $this->assertEquals('inactive', $this->waiterMembershipA->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess_audit_test_123']);

        $this->assertDatabaseHas('authorization_audit_logs', [
            'vendor_id' => $this->vendorA->id,
            'action' => 'membership.deactivated',
            'target_user_id' => $this->waiterA->id,
        ]);

        $this->assertDatabaseHas('authorization_audit_logs', [
            'vendor_id' => $this->vendorA->id,
            'action' => 'user.sessions_revoked',
            'target_user_id' => $this->waiterA->id,
        ]);
    }

    public function test_unknown_permission_defaults_to_deny(): void
    {
        $this->assertFalse($this->waiterA->hasPermission('unknown.random_permission_key'));
    }
}
