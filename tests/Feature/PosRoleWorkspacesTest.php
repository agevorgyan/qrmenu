<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PinSecurityService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosRoleWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected RestaurantTable $table;

    protected User $owner;

    protected User $manager;

    protected User $waiter;

    protected User $otherWaiter;

    protected User $cashier;

    protected User $chef;

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
                'features' => ['orders', 'locations', 'team', 'pos', 'pos.access', 'orders.online'],
                'is_active' => true,
            ]);

        $this->vendor = Vendor::create([
            'name' => 'Role Workspaces Bistro',
            'slug' => 'role-workspaces-bistro',
            'email' => 'contact@workspacesbistro.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'subscription_expires_at' => now()->addDays(30),
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Dining',
            'slug' => 'main-dining',
            'table_count' => 10,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Appetizers',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Lavash Rolls',
            'price' => 1800,
            'is_available' => true,
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-1',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Artak Owner',
            'email' => 'artak@workspacesbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->manager = User::create([
            'name' => 'Mane Manager',
            'email' => 'mane@workspacesbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'manager',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('7821'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->waiter = User::create([
            'name' => 'Davit Waiter',
            'email' => 'davit@workspacesbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('6382'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->otherWaiter = User::create([
            'name' => 'Gor Waiter',
            'email' => 'gor@workspacesbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('9153'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->cashier = User::create([
            'name' => 'Lilit Cashier',
            'email' => 'lilit@workspacesbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('4819'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->chef = User::create([
            'name' => 'Aram Chef',
            'email' => 'aram@workspacesbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'chef',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 1. Authentication Redirection Tests
     */
    public function test_waiter_redirected_to_waiter_workspace_on_login(): void
    {
        $response = $this->post('/login', [
            'email' => $this->waiter->email,
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('pos.index', ['mode' => 'waiter']));
    }

    public function test_cashier_redirected_to_cashier_workspace_on_login(): void
    {
        $response = $this->post('/login', [
            'email' => $this->cashier->email,
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('pos.index', ['mode' => 'cashier']));
    }

    public function test_manager_redirected_to_manager_workspace_on_login(): void
    {
        $response = $this->post('/login', [
            'email' => $this->manager->email,
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('pos.index', ['mode' => 'manager']));
    }

    public function test_chef_redirected_to_kds_on_login(): void
    {
        $response = $this->post('/login', [
            'email' => $this->chef->email,
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('pos.kds'));
    }

    /**
     * 2. Backend Workspace Guarding Tests (PosController::index)
     */
    public function test_waiter_mode_access_allowed_for_waiter(): void
    {
        $response = $this->actingAs($this->waiter)->get('/pos?mode=waiter');
        $response->assertStatus(200);
        $response->assertViewHas('mode', 'waiter');
    }

    public function test_cashier_mode_blocked_for_waiter_with_403(): void
    {
        $response = $this->actingAs($this->waiter)->get('/pos?mode=cashier');
        $response->assertStatus(403);
    }

    public function test_manager_mode_blocked_for_waiter_with_403(): void
    {
        $response = $this->actingAs($this->waiter)->get('/pos?mode=manager');
        $response->assertStatus(403);
    }

    public function test_manager_mode_blocked_for_cashier_with_403(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos?mode=manager');
        $response->assertStatus(403);
    }

    public function test_manager_can_access_all_three_modes(): void
    {
        $this->actingAs($this->manager)->get('/pos?mode=manager')->assertStatus(200);
        $this->actingAs($this->manager)->get('/pos?mode=cashier')->assertStatus(200);
        $this->actingAs($this->manager)->get('/pos?mode=waiter')->assertStatus(200);
    }

    /**
     * 3. Waiter Scoped Orders Tests
     */
    public function test_waiter_orders_are_backend_scoped_to_own_orders(): void
    {
        $myOrder = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'order_number' => 'ORD-MY-1',
            'status' => 'confirmed',
            'subtotal' => 1800,
            'total_amount' => 1800,
            'guest_count' => 2,
        ]);

        $otherOrder = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->otherWaiter->id,
            'order_number' => 'ORD-OTHER-2',
            'status' => 'confirmed',
            'subtotal' => 3600,
            'total_amount' => 3600,
            'guest_count' => 4,
        ]);

        // Attempt requesting with no waiter filter
        $response = $this->actingAs($this->waiter)->getJson('/pos/api/orders');
        $response->assertStatus(200);
        $data = $response->json('data');

        $orderNumbers = collect($data)->pluck('order_number')->all();
        $this->assertContains('ORD-MY-1', $orderNumbers);
        $this->assertNotContains('ORD-OTHER-2', $orderNumbers);

        // Attempt spoofing ?waiter_id={otherWaiter->id}
        $spoofedResponse = $this->actingAs($this->waiter)->getJson('/pos/api/orders?waiter_id='.$this->otherWaiter->id);
        $spoofedResponse->assertStatus(200);
        $spoofedData = $spoofedResponse->json('data');
        $spoofedNumbers = collect($spoofedData)->pluck('order_number')->all();

        // Backend RBAC must enforce waiter's own id
        $this->assertNotContains('ORD-OTHER-2', $spoofedNumbers);
    }

    /**
     * 4. Waiter Cancellation Requests and Manager Approvals
     */
    public function test_waiter_can_request_cancellation_for_own_order(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'order_number' => 'ORD-CANCEL-1',
            'status' => 'confirmed',
            'subtotal' => 1800,
            'total_amount' => 1800,
            'guest_count' => 2,
        ]);

        $response = $this->actingAs($this->waiter)->postJson("/pos/api/workspace/orders/{$order->id}/cancel-request", [
            'reason' => 'Guest left the table unexpectedly',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $order->refresh();
        $this->assertEquals('pending_approval', $order->cancellation_status);
        $this->assertEquals('Guest left the table unexpectedly', $order->cancellation_reason);
    }

    public function test_waiter_cannot_request_cancellation_for_another_waiters_order(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->otherWaiter->id,
            'order_number' => 'ORD-OTHER-CANCEL',
            'status' => 'confirmed',
            'subtotal' => 1800,
            'total_amount' => 1800,
        ]);

        $response = $this->actingAs($this->waiter)->postJson("/pos/api/workspace/orders/{$order->id}/cancel-request", [
            'reason' => 'Unauthorized attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_approve_cancellation_request(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'restaurant_table_id' => $this->table->id,
            'order_number' => 'ORD-APPROVE-1',
            'status' => 'confirmed',
            'subtotal' => 1800,
            'total_amount' => 1800,
            'cancellation_status' => 'pending_approval',
            'cancellation_reason' => 'Duplicate item entered',
        ]);

        $this->table->update(['status' => 'occupied']);

        $response = $this->actingAs($this->manager)->postJson("/pos/api/workspace/orders/{$order->id}/approve-cancellation");
        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('approved', $order->cancellation_status);
        $this->assertEquals('available', $this->table->fresh()->status);
    }

    public function test_manager_can_reject_cancellation_request(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'order_number' => 'ORD-REJECT-1',
            'status' => 'confirmed',
            'subtotal' => 1800,
            'total_amount' => 1800,
            'cancellation_status' => 'pending_approval',
            'cancellation_reason' => 'Guest changed mind',
        ]);

        $response = $this->actingAs($this->manager)->postJson("/pos/api/workspace/orders/{$order->id}/reject-cancellation", [
            'reason' => 'Food is already plated and served',
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals('rejected', $order->cancellation_status);
    }

    /**
     * 5. PIN Management, Trivial Check & Lockout Tests
     */
    public function test_pin_management_rejects_trivial_pins(): void
    {
        $trivialPins = ['0000', '1111', '1234', '4321', '12345', '123456'];

        foreach ($trivialPins as $pin) {
            $response = $this->actingAs($this->waiter)->postJson('/pos/api/workspace/pin', [
                'current_auth' => 'Secret123!',
                'pin' => $pin,
                'pin_confirmation' => $pin,
            ]);

            $response->assertStatus(422);
            $this->assertFalse($response->json('success'));
        }
    }

    public function test_pin_management_rejects_incorrect_current_auth(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/pos/api/workspace/pin', [
            'current_auth' => 'WrongPassword!',
            'pin' => '7392',
            'pin_confirmation' => '7392',
        ]);

        $response->assertStatus(422);
    }

    public function test_pin_management_successfully_updates_valid_pin(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/pos/api/workspace/pin', [
            'current_auth' => 'Secret123!',
            'pin' => '8492',
            'pin_confirmation' => '8492',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertTrue($this->waiter->fresh()->verifyPin('8492'));
    }

    public function test_pin_lockout_triggers_after_max_failed_attempts(): void
    {
        $pinService = app(PinSecurityService::class);
        $user = $this->waiter;
        $pinService->clearFailedAttempts($user);

        // First 4 failed attempts should return false without locking out
        for ($i = 1; $i <= 4; $i++) {
            $this->assertFalse($pinService->verifyPin($user, '0987'));
            $this->assertFalse($pinService->isLockedOut($user));
        }

        // 5th failed attempt should trigger lockout and throw ValidationException
        $this->expectException(ValidationException::class);
        $pinService->verifyPin($user, '0987');
    }

    /**
     * 6. Profile & Security Session Tests
     */
    public function test_workspace_profile_update(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/pos/api/workspace/profile', [
            'name' => 'Davit Updated',
            'display_name' => 'Davit U.',
            'phone' => '+374 99 778899',
        ]);

        $response->assertStatus(200);
        $this->waiter->refresh();
        $this->assertEquals('Davit Updated', $this->waiter->name);
        $this->assertEquals('Davit U.', $this->waiter->display_name);
    }

    public function test_workspace_change_password(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/pos/api/workspace/password', [
            'current_password' => 'Secret123!',
            'password' => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('NewSecret456!', $this->waiter->fresh()->password));
    }

    public function test_workspace_logout_other_devices(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/pos/api/workspace/logout-other-devices', [
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /**
     * 7. Cashier and Manager Workspace Dashboards
     */
    public function test_cashier_dashboard_returns_unpaid_orders_and_shift(): void
    {
        PosShift::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->cashier->id,
            'location_id' => $this->location->id,
            'opening_float' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-UNPAID-1',
            'status' => 'served',
            'subtotal' => 2500,
            'total_amount' => 2500,
        ]);

        $response = $this->actingAs($this->cashier)->getJson('/pos/api/workspace/cashier/dashboard');
        $response->assertStatus(200);
        $this->assertEquals('open', $response->json('summary.shift_status'));
        $this->assertEquals(1, $response->json('summary.unpaid_orders_count'));
    }

    public function test_manager_dashboard_returns_operational_overview(): void
    {
        $response = $this->actingAs($this->manager)->getJson('/pos/api/workspace/manager/dashboard');
        $response->assertStatus(200);
        $this->assertArrayHasKey('metrics', $response->json());
        $this->assertArrayHasKey('pending_exceptions', $response->json());
    }

    public function test_waiter_cannot_access_manager_dashboard(): void
    {
        $response = $this->actingAs($this->waiter)->getJson('/pos/api/workspace/manager/dashboard');
        $response->assertStatus(403);
    }

    public function test_waiter_cannot_access_cashier_dashboard(): void
    {
        $response = $this->actingAs($this->waiter)->getJson('/pos/api/workspace/cashier/dashboard');
        $response->assertStatus(403);
    }

    public function test_waiter_cannot_access_pos_reports(): void
    {
        $response = $this->actingAs($this->waiter)->getJson('/pos/api/reports/sales');
        $response->assertStatus(403);

        $responseDash = $this->actingAs($this->waiter)->getJson('/pos/api/reports/dashboard');
        $responseDash->assertStatus(403);
    }

    public function test_cashier_cannot_access_pos_reports(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/pos/api/reports/sales');
        $response->assertStatus(403);

        $responseCash = $this->actingAs($this->cashier)->getJson('/pos/api/reports/cash');
        $responseCash->assertStatus(403);

        $responseDash = $this->actingAs($this->cashier)->getJson('/pos/api/reports/dashboard');
        $responseDash->assertStatus(403);
    }

    public function test_waiter_cannot_access_admin_panel(): void
    {
        $response = $this->actingAs($this->waiter)->get(route('admin.dashboard'));
        $response->assertStatus(403);

        $apiResponse = $this->actingAs($this->waiter)->getJson(route('admin.dashboard'));
        $apiResponse->assertStatus(403);
    }

    public function test_cashier_cannot_access_admin_panel(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('admin.dashboard'));
        $response->assertStatus(403);

        $apiResponse = $this->actingAs($this->cashier)->getJson(route('admin.dashboard'));
        $apiResponse->assertStatus(403);
    }

    public function test_cashier_cannot_view_another_cashiers_shift_report(): void
    {
        $otherCashier = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Other Cashier',
            'email' => 'other_cashier@test.am',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $otherShift = PosShift::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'user_id' => $otherCashier->id,
            'location_id' => $this->location->id,
            'opening_float' => 5000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($this->cashier)->getJson("/pos/api/shifts/{$otherShift->id}/report");
        $response->assertStatus(403);
    }

    public function test_manager_can_access_pos_reports_and_dashboard(): void
    {
        $response = $this->actingAs($this->manager)->getJson('/pos/api/reports/sales');
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $dashRes = $this->actingAs($this->manager)->getJson('/pos/api/reports/dashboard');
        $dashRes->assertStatus(200);
        $this->assertTrue($dashRes->json('success'));
    }
}
