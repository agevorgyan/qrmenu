<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission as SecurityPermission;
use App\Services\AuthorizationService;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWorkspacesOperationalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $manager;

    protected User $waiter;

    protected User $cashier;

    protected User $customWorker;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(EnterpriseAuthorizationSeeder::class);

        $businessPlan = SubscriptionPlan::where('slug', 'business')->first();

        $this->vendor = Vendor::create([
            'name' => 'Operational Access Bistro',
            'slug' => 'operational-access-bistro',
            'email' => 'operational@bistro.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan?->id,
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall',
            'slug' => 'main-hall',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Arman Owner',
            'email' => 'arman@bistro.am',
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Lusine Manager',
            'email' => 'lusine@bistro.am',
            'password' => bcrypt('password123'),
            'role' => 'manager',
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Vahan Waiter',
            'email' => 'vahan@bistro.am',
            'password' => bcrypt('password123'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Sona Cashier',
            'email' => 'sona@bistro.am',
            'password' => bcrypt('password123'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'is_active' => true,
        ]);

        $this->customWorker = User::create([
            'name' => 'Karen Specialist',
            'email' => 'karen@bistro.am',
            'password' => bcrypt('password123'),
            'role' => 'staff',
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'order_number' => 'ORD-OPS-1',
            'status' => 'confirmed',
            'total_amount' => 5000,
        ]);
    }

    public function test_custom_role_with_cashier_workspace_permission_can_access_cashier_workspace(): void
    {
        // Create custom role with cashier workspace permission
        $customRole = Role::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Evening Checkout Lead',
            'slug' => 'evening-checkout-lead',
            'is_system' => false,
            'is_active' => true,
        ]);

        $membership = $this->customWorker->activeMembership($this->vendor->id);
        $membership->roles()->sync([$customRole->id]);

        $perm = Permission::where('key', 'workspace.cashier.access')->firstOrFail();
        $posAccessPerm = Permission::where('key', SecurityPermission::POS_ACCESS)->firstOrFail();
        $customRole->permissions()->sync([$perm->id, $posAccessPerm->id]);

        app(AuthorizationService::class)->invalidateCache($this->customWorker->id, $this->vendor->id);

        $response = $this->actingAs($this->customWorker)->get('/pos?mode=cashier');
        $response->assertStatus(200);
        $response->assertViewHas('mode', 'cashier');
    }

    public function test_waiter_cannot_access_manager_workspace_via_url(): void
    {
        $response = $this->actingAs($this->waiter)->get('/pos?mode=manager');
        $response->assertStatus(403);
    }

    public function test_cashier_cannot_access_manager_workspace_via_url(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos?mode=manager');
        $response->assertStatus(403);
    }

    public function test_direct_api_access_to_manager_dashboard_blocked_for_waiter_and_cashier(): void
    {
        $this->actingAs($this->waiter)
            ->getJson('/pos/api/workspace/manager/dashboard')
            ->assertStatus(403);

        $this->actingAs($this->cashier)
            ->getJson('/pos/api/workspace/manager/dashboard')
            ->assertStatus(403);

        $this->actingAs($this->manager)
            ->getJson('/pos/api/workspace/manager/dashboard')
            ->assertStatus(200);
    }

    public function test_waiter_cannot_self_approve_order_cancellation(): void
    {
        // Waiter requests cancellation
        $this->actingAs($this->waiter)
            ->postJson("/pos/api/workspace/orders/{$this->order->id}/cancel-request", [
                'reason' => 'Guest changed mind',
            ])
            ->assertStatus(200);

        // Waiter attempts to approve cancellation directly
        $this->actingAs($this->waiter)
            ->postJson("/pos/api/workspace/orders/{$this->order->id}/approve-cancellation", [
                'reason' => 'Self-approving',
            ])
            ->assertStatus(403);

        // Manager approves cancellation
        $this->actingAs($this->manager)
            ->postJson("/pos/api/workspace/orders/{$this->order->id}/approve-cancellation", [
                'reason' => 'Approved by manager',
            ])
            ->assertStatus(200);

        $this->assertEquals('cancelled', $this->order->fresh()->status);
    }

    public function test_kds_workspace_direct_access_security(): void
    {
        $this->actingAs($this->waiter)
            ->get('/pos/kds')
            ->assertStatus(403);

        $this->actingAs($this->manager)
            ->get('/pos/kds')
            ->assertStatus(200);
    }
}
