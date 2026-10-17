<?php

namespace Tests\Feature;

use App\Models\AiWaiterSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationProductOverride;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\MenuManagementService;
use App\Services\TenantContext;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiTenantIsolationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected User $managerA;

    protected User $staffA;

    protected Location $locationA;

    protected Category $categoryA;

    protected Product $productA;

    protected Order $orderA;

    protected Customer $customerA;

    protected WaiterCall $waiterCallA;

    protected LocationProductOverride $overrideA;

    protected AiWaiterSession $sessionA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $managerB;

    protected User $staffB;

    protected Location $locationB;

    protected Category $categoryB;

    protected Product $productB;

    protected Order $orderB;

    protected Customer $customerB;

    protected WaiterCall $waiterCallB;

    protected LocationProductOverride $overrideB;

    protected AiWaiterSession $sessionB;

    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::where('slug', 'business')->first();

        // 1. Setup Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->managerA = User::create([
            'name' => 'Manager Alpha',
            'email' => 'manager@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        $this->staffA = User::create([
            'name' => 'Staff Alpha',
            'email' => 'staff@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Center Branch',
            'slug' => 'alpha-center',
            'is_active' => true,
        ]);

        $this->categoryA = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Mains',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Alpha Steak',
            'price' => 5000,
            'is_available' => true,
        ]);

        $this->customerA = Customer::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Customer Alpha',
            'phone' => '+37491111111',
            'marketing_opt_in' => true,
        ]);

        $this->orderA = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'customer_id' => $this->customerA->id,
            'order_number' => 'ORD-A-001',
            'status' => 'pending',
            'total_amount' => 5000,
            'payment_status' => 'paid',
        ]);

        $this->waiterCallA = WaiterCall::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => '5',
            'type' => 'call',
            'status' => 'pending',
        ]);

        $this->overrideA = LocationProductOverride::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'product_id' => $this->productA->id,
            'override_price' => 5500,
            'is_available' => true,
        ]);

        $this->sessionA = AiWaiterSession::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'session_token' => 'uuid-alpha-1234',
            'table_number' => '5',
            'language' => 'hy',
            'status' => 'started',
        ]);

        // 2. Setup Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Bistro Beta',
            'slug' => 'bistro-beta',
            'email' => 'beta@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->managerB = User::create([
            'name' => 'Manager Beta',
            'email' => 'manager@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        $this->staffB = User::create([
            'name' => 'Staff Beta',
            'email' => 'staff@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Branch',
            'slug' => 'beta-branch',
            'is_active' => true,
        ]);

        $this->categoryB = Category::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Drinks',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'vendor_id' => $this->vendorB->id,
            'category_id' => $this->categoryB->id,
            'name' => 'Beta Wine',
            'price' => 7000,
            'is_available' => true,
        ]);

        $this->customerB = Customer::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'name' => 'Customer Beta',
            'phone' => '+37492222222',
            'marketing_opt_in' => false,
        ]);

        $this->orderB = Order::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'customer_id' => $this->customerB->id,
            'order_number' => 'ORD-B-001',
            'status' => 'pending',
            'total_amount' => 7000,
            'payment_status' => 'paid',
        ]);

        $this->waiterCallB = WaiterCall::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'table_number' => '10',
            'type' => 'call',
            'status' => 'pending',
        ]);

        $this->overrideB = LocationProductOverride::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'product_id' => $this->productB->id,
            'override_price' => 7500,
            'is_available' => true,
        ]);

        $this->sessionB = AiWaiterSession::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'session_token' => 'uuid-beta-5678',
            'table_number' => '10',
            'language' => 'hy',
            'status' => 'started',
        ]);

        // 3. SuperAdmin
        $this->superadmin = User::create([
            'name' => 'Platform SuperAdmin',
            'email' => 'superadmin@elab.am',
            'password' => bcrypt('password123'),
            'role' => 'superadmin',
            'vendor_id' => null,
        ]);
    }

    // ==========================================
    // 1. PRODUCTS SECURITY TESTS
    // ==========================================

    public function test_public_user_cannot_access_product_management(): void
    {
        $this->get(route('admin.menu.index'))->assertRedirect(route('login'));
        $this->post(route('admin.menu.products.store'), [])->assertRedirect(route('login'));
        $this->post(route('admin.menu.products.update', $this->productA->id), [])->assertRedirect(route('login'));
        $this->delete(route('admin.menu.products.destroy', $this->productA->id))->assertRedirect(route('login'));
    }

    public function test_vendor_b_cannot_update_or_delete_vendor_a_product(): void
    {
        $updateRes = $this->actingAs($this->ownerB)->post(route('admin.menu.products.update', $this->productA->id), [
            'name' => 'Hacked Steak',
            'price' => 100,
            'category_id' => $this->categoryB->id,
        ]);
        $this->assertTrue(in_array($updateRes->status(), [403, 404]), "Expected 403 or 404 on update, got {$updateRes->status()}");
        $this->assertEquals('Alpha Steak', $this->productA->fresh()->name);

        $deleteRes = $this->actingAs($this->ownerB)->delete(route('admin.menu.products.destroy', $this->productA->id));
        $this->assertTrue(in_array($deleteRes->status(), [403, 404]), "Expected 403 or 404 on delete, got {$deleteRes->status()}");
        $this->assertNull($this->productA->fresh()->deleted_at);
    }

    public function test_vendor_b_cannot_toggle_availability_of_vendor_a_product(): void
    {
        $res = $this->actingAs($this->ownerB)->post(route('admin.menu.products.toggle', $this->productA->id));
        $this->assertTrue(in_array($res->status(), [403, 404]), "Expected 403 or 404 on toggle, got {$res->status()}");
        $this->assertTrue((bool) $this->productA->fresh()->is_available);
    }

    public function test_vendor_staff_cannot_delete_product(): void
    {
        $res = $this->actingAs($this->staffA)->delete(route('admin.menu.products.destroy', $this->productA->id));
        $this->assertTrue(in_array($res->status(), [403, 404]));
        $this->assertNull($this->productA->fresh()->deleted_at);
    }

    public function test_vendor_owner_can_manage_own_product(): void
    {
        $updateRes = $this->actingAs($this->ownerA)->post(route('admin.menu.products.update', $this->productA->id), [
            'name' => 'Alpha Prime Steak',
            'price' => 5800,
            'category_id' => $this->categoryA->id,
        ]);
        $updateRes->assertSessionHas('success');
        $this->assertEquals('Alpha Prime Steak', $this->productA->fresh()->name);
    }

    // ==========================================
    // 2. CATEGORIES SECURITY TESTS
    // ==========================================

    public function test_vendor_b_cannot_update_or_delete_vendor_a_category(): void
    {
        $updateRes = $this->actingAs($this->ownerB)->post(route('admin.menu.categories.update', $this->categoryA->id), [
            'name' => 'Hacked Category',
        ]);
        $this->assertTrue(in_array($updateRes->status(), [403, 404]));
        $this->assertEquals('Alpha Mains', $this->categoryA->fresh()->name);

        $deleteRes = $this->actingAs($this->ownerB)->delete(route('admin.menu.categories.destroy', $this->categoryA->id));
        $this->assertTrue(in_array($deleteRes->status(), [403, 404]));
        $this->assertDatabaseHas('categories', ['id' => $this->categoryA->id]);
    }

    // ==========================================
    // 3. ORDERS SECURITY TESTS
    // ==========================================

    public function test_public_user_cannot_access_admin_orders(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
        $this->post(route('admin.orders.status', $this->orderA->id), ['status' => 'completed'])->assertRedirect(route('login'));
    }

    public function test_vendor_b_cannot_view_or_update_vendor_a_orders(): void
    {
        $statusRes = $this->actingAs($this->ownerB)->post(route('admin.orders.status', $this->orderA->id), [
            'status' => 'completed',
        ]);
        $this->assertTrue(in_array($statusRes->status(), [403, 404]));
        $this->assertEquals('pending', $this->orderA->fresh()->status);

        $receiptRes = $this->actingAs($this->ownerB)->get(route('admin.orders.receipt_text', $this->orderA->id));
        $this->assertTrue(in_array($receiptRes->status(), [403, 404]));
    }

    public function test_vendor_staff_a_can_update_own_vendor_order_status(): void
    {
        $res = $this->actingAs($this->staffA)->post(route('admin.orders.status', $this->orderA->id), [
            'status' => 'preparing',
        ]);
        $res->assertSessionHas('success');
        $this->assertEquals('preparing', $this->orderA->fresh()->status);
    }

    // ==========================================
    // 4. CUSTOMERS CRM SECURITY TESTS
    // ==========================================

    public function test_vendor_b_cannot_view_update_or_delete_vendor_a_customers(): void
    {
        $viewRes = $this->actingAs($this->ownerB)->get(route('admin.customers.show', $this->customerA->id));
        $this->assertTrue(in_array($viewRes->status(), [403, 404]));

        $updateRes = $this->actingAs($this->ownerB)->post(route('admin.customers.update', $this->customerA->id), [
            'name' => 'Hacked Customer',
        ]);
        $this->assertTrue(in_array($updateRes->status(), [403, 404]));
        $this->assertEquals('Customer Alpha', $this->customerA->fresh()->name);

        $deleteRes = $this->actingAs($this->ownerB)->delete(route('admin.customers.destroy', $this->customerA->id));
        $this->assertTrue(in_array($deleteRes->status(), [403, 404]));
        $this->assertDatabaseHas('customers', ['id' => $this->customerA->id]);
    }

    public function test_customer_export_does_not_leak_cross_vendor_data(): void
    {
        $res = $this->actingAs($this->ownerB)->get(route('admin.customers.export'));
        $res->assertStatus(200);

        $content = $res->streamedContent();
        $this->assertStringContainsString('Customer Beta', $content);
        $this->assertStringNotContainsString('Customer Alpha', $content);
        $this->assertStringNotContainsString('+37491111111', $content);
    }

    // ==========================================
    // 5. WAITER CALLS SECURITY TESTS
    // ==========================================

    public function test_vendor_b_cannot_update_vendor_a_waiter_call(): void
    {
        $res = $this->actingAs($this->ownerB)->post(route('admin.waiter_calls.status', $this->waiterCallA->id), [
            'status' => 'attended',
        ]);
        $this->assertTrue(in_array($res->status(), [403, 404]));
        $this->assertEquals('pending', $this->waiterCallA->fresh()->status);
    }

    public function test_vendor_staff_a_can_attend_own_waiter_call(): void
    {
        $res = $this->actingAs($this->staffA)->post(route('admin.waiter_calls.status', $this->waiterCallA->id), [
            'status' => 'attended',
        ]);
        $res->assertSessionHas('success');
        $this->assertEquals('attended', $this->waiterCallA->fresh()->status);
    }

    // ==========================================
    // 6. AI WAITER SESSIONS SECURITY TESTS
    // ==========================================

    public function test_ai_waiter_endpoints_strictly_isolate_vendor_sessions(): void
    {
        // Calling submitAnswer for Vendor A with session from Vendor B must fail 404
        $res = $this->postJson("/api/m/{$this->vendorA->slug}/ai-waiter/session/{$this->sessionB->id}/answer", [
            'question_key' => 'taste',
            'answer_value' => 'spicy',
        ]);
        $res->assertStatus(404);
    }

    public function test_ai_waiter_rejects_cross_vendor_location_id(): void
    {
        $res = $this->postJson("/api/m/{$this->vendorA->slug}/ai-waiter/session", [
            'location_id' => $this->locationB->id, // belongs to Vendor B
            'table_number' => '12',
        ]);
        $res->assertStatus(200);

        $sessionId = $res->json('session_id');
        $session = AiWaiterSession::findOrFail($sessionId);

        // Location belonging to Vendor B must NOT be stored on Vendor A's session
        $this->assertNull($session->location_id);
    }

    // ==========================================
    // 7. SETTINGS & BILLING SECURITY TESTS
    // ==========================================

    public function test_vendor_b_cannot_access_or_update_vendor_a_settings(): void
    {
        // When logged in as Owner B, settings index displays Vendor B, not Vendor A
        $res = $this->actingAs($this->ownerB)->get(route('admin.settings.index'));
        $res->assertStatus(200);
        $res->assertSee($this->vendorB->name);
        $res->assertDontSee($this->vendorA->email);
    }

    public function test_staff_cannot_access_settings_or_billing(): void
    {
        $this->actingAs($this->staffA)->get(route('admin.settings.index'))->assertStatus(403);
        $this->actingAs($this->staffA)->get(route('admin.subscription'))->assertStatus(403);
        $this->actingAs($this->staffA)->post(route('admin.subscription.renew'), [])->assertStatus(403);
    }

    // ==========================================
    // 8. INDIRECT ACCESS ATTACKS DEFENSE TESTS
    // ==========================================

    public function test_cross_vendor_category_assignment_to_product_is_blocked(): void
    {
        // Attempting to assign Category B to Product in Vendor A via request is rejected (403 forbidden)
        $res = $this->actingAs($this->ownerA)->post(route('admin.menu.products.store'), [
            'category_id' => $this->categoryB->id,
            'name' => 'Cross Vendor Dish',
            'price' => 3000,
        ]);
        $this->assertTrue(in_array($res->status(), [403, 302]));
        if ($res->status() === 302) {
            $res->assertSessionHasErrors('category_id');
        }

        // Attempting direct Eloquent save across vendors must trigger Model lifecycle exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-vendor category assignment forbidden.');

        Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryB->id, // belongs to Vendor B!
            'name' => 'Sneaky Product',
            'price' => 1000,
        ]);
    }

    public function test_cross_vendor_location_assignment_to_order_is_blocked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-vendor location assigned to order.');

        Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationB->id, // belongs to Vendor B!
            'order_number' => 'ORD-ILLEGAL-01',
            'status' => 'pending',
            'total_amount' => 1000,
        ]);
    }

    public function test_cross_vendor_customer_attachment_to_order_is_blocked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-vendor customer attached to order.');

        Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'customer_id' => $this->customerB->id, // belongs to Vendor B!
            'order_number' => 'ORD-ILLEGAL-02',
            'status' => 'pending',
            'total_amount' => 1000,
        ]);
    }

    public function test_cross_vendor_location_product_override_is_strictly_blocked(): void
    {
        // 1. Through MenuManagementService
        $menuService = app(MenuManagementService::class);

        $caught = false;
        try {
            $menuService->saveLocationOverride($this->productA, [
                'location_id' => $this->locationB->id, // cross-vendor location!
                'override_price' => 4500,
                'is_available' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            $caught = true;
            $this->assertStringContainsString('Cross-vendor override attempt', $e->getMessage());
        }
        $this->assertTrue($caught, 'Expected InvalidArgumentException for cross-vendor override in service');

        // 2. Direct Eloquent create attempt
        $caughtModel = false;
        try {
            LocationProductOverride::create([
                'vendor_id' => $this->vendorA->id,
                'location_id' => $this->locationB->id,
                'product_id' => $this->productA->id,
                'override_price' => 4500,
                'is_available' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            $caughtModel = true;
            $this->assertStringContainsString('LocationProductOverride vendor_id', $e->getMessage());
        }
        $this->assertTrue($caughtModel, 'Expected InvalidArgumentException for cross-vendor override on model save');
    }

    public function test_override_table_guarantees_unique_vendor_location_product(): void
    {
        // A duplicate entry for the same (vendor_id, location_id, product_id) should update, not duplicate
        $menuService = app(MenuManagementService::class);

        $override = $menuService->saveLocationOverride($this->productA, [
            'location_id' => $this->locationA->id,
            'override_price' => 6000,
            'is_available' => false,
        ]);

        $this->assertEquals(1, LocationProductOverride::where('vendor_id', $this->vendorA->id)
            ->where('location_id', $this->locationA->id)
            ->where('product_id', $this->productA->id)
            ->count());
        $this->assertEquals(6000, (float) $override->override_price);
        $this->assertFalse((bool) $override->is_available);
    }

    public function test_cross_vendor_staff_location_assignment_is_blocked(): void
    {
        // Via HTTP Controller
        $res = $this->actingAs($this->ownerA)->post(route('admin.team.store'), [
            'name' => 'Rogue Staff',
            'email' => 'rogue@alpha.am',
            'role' => 'staff',
            'location_id' => $this->locationB->id, // belongs to Vendor B
            'password' => 'secret123',
        ]);
        $this->assertEquals(403, $res->status());

        // Direct Model save
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-vendor location assigned to user.');

        User::create([
            'name' => 'Sneaky User',
            'email' => 'sneaky@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationB->id,
            'role' => 'staff',
        ]);
    }

    public function test_cross_vendor_featured_dish_assignment_is_blocked(): void
    {
        $res = $this->actingAs($this->ownerA)->post(route('admin.settings.update'), [
            'service_fee_type' => 'percent',
            'service_fee_value' => 10,
            'delivery_fee' => 500,
            'delivery_min_amount' => 2000,
            'featured_dish_enabled' => true,
            'featured_product_id' => $this->productB->id, // belongs to Vendor B!
        ]);
        $res->assertSessionHasErrors('featured_product_id');
        $this->assertNotEquals($this->productB->id, $this->vendorA->fresh()->featured_product_id);
    }

    public function test_cross_vendor_media_deletion_is_prevented(): void
    {
        Storage::fake('public');

        // Create a legitimate file belonging to Vendor B
        Storage::disk('public')->put('branding/beta_secret_logo.png', 'SECRET_BETA');
        Storage::disk('public')->assertExists('branding/beta_secret_logo.png');

        // Vendor A sets product image maliciously targeting Vendor B's path
        $maliciousProduct = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Exploit Dish',
            'price' => 1000,
            'image' => '/storage/branding/beta_secret_logo.png',
        ]);

        // When product is deleted, deleteImageFile MUST NOT delete Vendor B's branding file
        $maliciousProduct->delete();

        Storage::disk('public')->assertExists('branding/beta_secret_logo.png');

        // Path traversal attack test: ../../something
        $traversalProduct = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Traversal Dish',
            'price' => 1000,
            'image' => '/storage/../../secret_config.json',
        ]);

        Storage::disk('public')->put('secret_config.json', 'TOP_SECRET');
        $traversalProduct->delete();
        Storage::disk('public')->assertExists('secret_config.json');
    }

    public function test_cross_tenant_creation_and_transfer_blocked_by_belongs_to_vendor(): void
    {
        // In the context of Vendor A:
        app(TenantContext::class)->runInTenantContext($this->vendorA->id, function () {
            // 1. Attempting to create an entity for Vendor B while in Vendor A context must throw
            $caught = false;
            try {
                Category::create([
                    'vendor_id' => $this->vendorB->id,
                    'name' => 'Injected Category',
                ]);
            } catch (\InvalidArgumentException $e) {
                $caught = true;
                $this->assertStringContainsString('Cross-tenant entity creation forbidden', $e->getMessage());
            }
            $this->assertTrue($caught, 'Expected InvalidArgumentException for cross-tenant creation');

            // 2. Attempting to transfer an existing entity to Vendor B must throw
            $caughtTransfer = false;
            try {
                $this->categoryA->update(['vendor_id' => $this->vendorB->id]);
            } catch (\InvalidArgumentException $e) {
                $caughtTransfer = true;
                $this->assertStringContainsString('Cross-tenant entity transfer forbidden', $e->getMessage());
            }
            $this->assertTrue($caughtTransfer, 'Expected InvalidArgumentException for cross-tenant transfer');
        });
    }

    // ==========================================
    // 9. TENANT SCOPE & BYPASS AUDIT TESTS
    // ==========================================

    public function test_tenant_scope_automatically_filters_all_queries(): void
    {
        // As authenticated Vendor A owner
        $this->actingAs($this->ownerA);

        $categories = Category::all();
        $this->assertTrue($categories->contains($this->categoryA));
        $this->assertFalse($categories->contains($this->categoryB));

        $products = Product::all();
        $this->assertTrue($products->contains($this->productA));
        $this->assertFalse($products->contains($this->productB));

        $orders = Order::all();
        $this->assertTrue($orders->contains($this->orderA));
        $this->assertFalse($orders->contains($this->orderB));

        $customers = Customer::all();
        $this->assertTrue($customers->contains($this->customerA));
        $this->assertFalse($customers->contains($this->customerB));

        $locations = Location::all();
        $this->assertTrue($locations->contains($this->locationA));
        $this->assertFalse($locations->contains($this->locationB));

        $overrides = LocationProductOverride::all();
        $this->assertTrue($overrides->contains($this->overrideA));
        $this->assertFalse($overrides->contains($this->overrideB));
    }

    public function test_superadmin_bypasses_tenant_scope_for_platform_oversight(): void
    {
        $this->actingAs($this->superadmin);

        $allCategories = Category::all();
        $this->assertTrue($allCategories->contains($this->categoryA));
        $this->assertTrue($allCategories->contains($this->categoryB));

        $allProducts = Product::all();
        $this->assertTrue($allProducts->contains($this->productA));
        $this->assertTrue($allProducts->contains($this->productB));
    }
}
