<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseAccessControlAdminIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location1;

    protected Location $location2;

    protected User $owner;

    protected User $manager;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EnterpriseAuthorizationSeeder::class);

        $plan = SubscriptionPlan::create([
            'name' => 'Business',
            'slug' => 'business',
            'price' => 29000,
            'features' => ['team' => true, 'locations' => true, 'orders' => true],
            'is_active' => true,
        ]);

        $this->vendor = Vendor::create([
            'name' => 'Palace Bistro',
            'slug' => 'palace-bistro',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $this->location1 = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown',
            'address' => 'Northern Ave 1',
            'is_active' => true,
        ]);

        $this->location2 = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mall Branch',
            'slug' => 'mall',
            'address' => 'Dalma Mall 2',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Owner Arthur',
            'email' => 'owner@palacebistro.am',
            'password' => bcrypt('Secret123!'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->manager = User::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location1->id,
            'name' => 'Manager David',
            'email' => 'manager@palacebistro.am',
            'password' => bcrypt('Secret123!'),
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        $this->staff = User::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location1->id,
            'name' => 'Staff Ani',
            'email' => 'ani@palacebistro.am',
            'password' => bcrypt('Secret123!'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->seed(EnterpriseAuthorizationSeeder::class);
    }

    public function test_owner_can_access_access_control_dashboard_with_all_tabs(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.access_control.index'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.access_control.index');
        $response->assertSee('Team & Access Control');
        $response->assertSee('Owner Arthur');
        $response->assertSee('Manager David');
        $response->assertSee('Staff Ani');

        // Test team index route alias
        $teamResponse = $this->actingAs($this->owner)->get(route('admin.team.index'));
        $teamResponse->assertStatus(200);
        $teamResponse->assertViewIs('admin.access_control.index');
    }

    public function test_unauthorized_staff_cannot_access_access_control(): void
    {
        $response = $this->actingAs($this->staff)->get(route('admin.access_control.index'));
        $response->assertStatus(403);

        $teamResponse = $this->actingAs($this->staff)->get(route('admin.team.index'));
        $teamResponse->assertStatus(403);
    }

    public function test_owner_can_create_duplicate_update_and_delete_custom_role(): void
    {
        // 1. Create custom role
        $createResponse = $this->actingAs($this->owner)->post(route('admin.access_control.roles.store'), [
            'name' => 'Sommelier Lead',
            'description' => 'Dedicated fine wine steward and cellar manager.',
            'permissions' => ['menu.view', 'orders.view_own', 'tables.view_assigned'],
        ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('roles', [
            'vendor_id' => $this->vendor->id,
            'slug' => 'sommelier-lead',
            'is_system' => false,
        ]);

        $role = Role::where('vendor_id', $this->vendor->id)->where('slug', 'sommelier-lead')->firstOrFail();
        $this->assertCount(3, $role->permissions);

        // 2. Duplicate role
        $dupResponse = $this->actingAs($this->owner)->post(route('admin.access_control.roles.duplicate', $role), [
            'name' => 'Sommelier Assistant',
        ]);
        $dupResponse->assertRedirect();
        $this->assertDatabaseHas('roles', [
            'vendor_id' => $this->vendor->id,
            'slug' => 'sommelier-assistant',
            'is_system' => false,
        ]);

        // 3. Update custom role
        $updateResponse = $this->actingAs($this->owner)->put(route('admin.access_control.roles.update', $role), [
            'name' => 'Sommelier Master Lead',
            'description' => 'Updated steward role description.',
            'permissions' => ['menu.view', 'orders.view_own', 'tables.view_assigned', 'orders.create'],
        ]);
        $updateResponse->assertRedirect();
        $role->refresh();
        $this->assertEquals('Sommelier Master Lead', $role->name);
        $this->assertCount(4, $role->permissions);

        // 4. Delete custom role
        $delResponse = $this->actingAs($this->owner)->delete(route('admin.access_control.roles.destroy', $role));
        $delResponse->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_owner_can_update_user_roles_locations_and_status(): void
    {
        $waiterRole = Role::where('slug', 'waiter')->where('is_system', true)->firstOrFail();
        $cashierRole = Role::where('slug', 'cashier')->where('is_system', true)->firstOrFail();

        // 1. Update roles
        $rolesResponse = $this->actingAs($this->owner)->post(route('admin.access_control.users.roles.update', $this->staff), [
            'roles' => [$waiterRole->id, $cashierRole->id],
        ]);
        $rolesResponse->assertRedirect();

        $membership = $this->staff->activeMembership($this->vendor->id);
        $this->assertNotNull($membership);
        $this->assertEquals(2, $membership->roles()->count());

        // 2. Update locations
        $locResponse = $this->actingAs($this->owner)->post(route('admin.access_control.users.locations.update', $this->staff), [
            'all_locations' => 0,
            'locations' => [$this->location1->id, $this->location2->id],
        ]);
        $locResponse->assertRedirect();
        $this->assertEquals(2, $membership->locations()->count());

        // 3. Update status to inactive
        $statusResponse = $this->actingAs($this->owner)->post(route('admin.access_control.users.status.update', $this->staff), [
            'status' => 'inactive',
        ]);
        $statusResponse->assertRedirect();
        $membership->refresh();
        $this->assertEquals('inactive', $membership->status);
    }

    public function test_owner_can_manage_permission_overrides_and_fetch_effective_permissions(): void
    {
        // 1. Grant explicit deny override
        $overrideResponse = $this->actingAs($this->owner)->post(route('admin.access_control.users.overrides.store', $this->staff), [
            'permission_key' => 'orders.cancel',
            'effect' => 'deny',
            'reason' => 'Restricted by store owner pending compliance review',
        ]);
        $overrideResponse->assertRedirect();

        // 2. Fetch JSON effective permissions
        $jsonResponse = $this->actingAs($this->owner)->get(route('admin.access_control.users.effective_permissions', $this->staff));
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertJsonStructure([
            'success',
            'user' => ['id', 'name', 'email', 'roles', 'locations'],
            'effective_permissions',
            'denied_overrides',
            'allowed_overrides',
        ]);

        $data = $jsonResponse->json();
        $deniedKeys = collect($data['denied_overrides'])->pluck('key')->all();
        $this->assertContains('orders.cancel', $deniedKeys);

        // 3. Remove override
        $destroyResponse = $this->actingAs($this->owner)->delete(route('admin.access_control.users.overrides.destroy', [
            'user' => $this->staff->id,
            'permissionKey' => 'orders.cancel',
        ]));
        $destroyResponse->assertRedirect();

        $jsonAfterResponse = $this->actingAs($this->owner)->get(route('admin.access_control.users.effective_permissions', $this->staff));
        $afterData = $jsonAfterResponse->json();
        $afterDeniedKeys = collect($afterData['denied_overrides'])->pluck('key')->all();
        $this->assertNotContains('orders.cancel', $afterDeniedKeys);
    }

    public function test_owner_can_revoke_user_sessions(): void
    {
        $this->staff->update(['remember_token' => 'original-test-remember-token']);
        $oldToken = $this->staff->remember_token;

        $revokeResponse = $this->actingAs($this->owner)->post(route('admin.access_control.users.revoke_sessions', $this->staff));
        $revokeResponse->assertRedirect();

        $this->staff->refresh();
        $this->assertNotEquals($oldToken, $this->staff->remember_token);

        $this->assertDatabaseHas('authorization_audit_logs', [
            'vendor_id' => $this->vendor->id,
            'actor_id' => $this->owner->id,
            'target_user_id' => $this->staff->id,
            'action' => 'user.sessions_revoked',
        ]);
    }

    public function test_owner_can_export_audit_logs_as_csv(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.access_control.audit_logs.export'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="authorization_audit_logs_', $response->headers->get('content-disposition'));
    }
}
