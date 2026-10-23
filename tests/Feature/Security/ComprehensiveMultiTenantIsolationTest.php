<?php

namespace Tests\Feature\Security;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\DTOs\OrderItemDTO;
use App\Exceptions\InvalidStoragePathException;
use App\Jobs\TranslateMenuJob;
use App\Models\AnalyticsLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\SecurityAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AiMenuService;
use App\Services\StorageService;
use App\Services\TenantCache;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComprehensiveMultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected User $staffA;

    protected Location $locationA;

    protected Category $categoryA;

    protected Product $productA;

    protected RestaurantTable $tableA;

    protected Order $orderA;

    protected Customer $customerA;

    protected Reservation $reservationA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $staffB;

    protected Location $locationB;

    protected Category $categoryB;

    protected Product $productB;

    protected RestaurantTable $tableB;

    protected Order $orderB;

    protected Customer $customerB;

    protected Reservation $reservationB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $businessPlan = SubscriptionPlan::where('slug', 'business')->firstOrFail();

        // ---------------------------------------------------------
        // Synthetic Tenant A Setup
        // ---------------------------------------------------------
        $this->vendorA = Vendor::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Synthetic Tenant Alpha',
            'slug' => 'tenant-alpha-'.uniqid(),
            'email' => 'alpha_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'email_verified_at' => now(),
            'is_active' => true,
            'currency' => 'AMD',
            'reservation_settings' => [
                'enabled' => true,
                'mode' => 'reservation_and_preorder',
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'auto_confirm' => false,
                'pre_orders_mode' => 'optional',
            ],
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner_alpha_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->staffA = User::create([
            'name' => 'Staff Alpha',
            'email' => 'staff_alpha_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Main Hall',
            'slug' => 'alpha-main-'.uniqid(),
            'is_active' => true,
            'table_count' => 10,
        ]);

        $this->categoryA = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Starters',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Alpha Dish',
            'price' => 2500,
            'is_available' => true,
        ]);

        $this->tableA = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => 'A-101',
            'name' => 'Alpha Table 1',
            'capacity' => 4,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->customerA = Customer::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Alice Alpha',
            'email' => 'alice@alpha.test',
            'phone' => '+37491000001',
            'marketing_opt_in' => true,
            'total_spent' => 15000,
        ]);

        $this->orderA = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'customer_id' => $this->customerA->id,
            'order_number' => 'ORD-A-001',
            'table_number' => 'A-101',
            'type' => 'dine_in',
            'status' => 'pending',
            'total_amount' => 5000,
            'customer_name' => 'Alice Alpha',
            'customer_phone' => '+37491000001',
        ]);

        OrderItem::create([
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Alpha Dish',
            'unit_price' => 2500,
            'quantity' => 2,
            'subtotal' => 5000,
        ]);

        $this->reservationA = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-A-'.strtoupper(Str::random(6)),
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'restaurant_table_id' => $this->tableA->id,
            'customer_name' => 'Alice Alpha',
            'customer_email' => 'alice@alpha.test',
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'guest_count' => 2,
            'status' => Reservation::STATUS_PENDING,
        ]);

        // ---------------------------------------------------------
        // Synthetic Tenant B Setup
        // ---------------------------------------------------------
        $this->vendorB = Vendor::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Synthetic Tenant Beta',
            'slug' => 'tenant-beta-'.uniqid(),
            'email' => 'beta_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'email_verified_at' => now(),
            'is_active' => true,
            'currency' => 'AMD',
            'reservation_settings' => [
                'enabled' => true,
                'mode' => 'reservation_and_preorder',
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'auto_confirm' => false,
                'pre_orders_mode' => 'optional',
            ],
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner_beta_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->staffB = User::create([
            'name' => 'Staff Beta',
            'email' => 'staff_beta_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Garden Lounge',
            'slug' => 'beta-garden-'.uniqid(),
            'is_active' => true,
            'table_count' => 8,
        ]);

        $this->categoryB = Category::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Mains',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'vendor_id' => $this->vendorB->id,
            'category_id' => $this->categoryB->id,
            'name' => 'Beta Steak',
            'price' => 7500,
            'is_available' => true,
        ]);

        $this->tableB = RestaurantTable::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'table_number' => 'B-201',
            'name' => 'Beta Table 1',
            'capacity' => 6,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->customerB = Customer::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'name' => 'Bob Beta',
            'email' => 'bob@beta.test',
            'phone' => '+37491000002',
            'marketing_opt_in' => false,
            'total_spent' => 28000,
        ]);

        $this->orderB = Order::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'customer_id' => $this->customerB->id,
            'order_number' => 'ORD-B-001',
            'table_number' => 'B-201',
            'type' => 'dine_in',
            'status' => 'pending',
            'total_amount' => 7500,
            'customer_name' => 'Bob Beta',
            'customer_phone' => '+37491000002',
        ]);

        OrderItem::create([
            'order_id' => $this->orderB->id,
            'product_id' => $this->productB->id,
            'product_name' => 'Beta Steak',
            'unit_price' => 7500,
            'quantity' => 1,
            'subtotal' => 7500,
        ]);

        $this->reservationB = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-B-'.strtoupper(Str::random(6)),
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'restaurant_table_id' => $this->tableB->id,
            'customer_name' => 'Bob Beta',
            'customer_email' => 'bob@beta.test',
            'reservation_date' => now()->addDays(3)->format('Y-m-d'),
            'reservation_time' => '20:00',
            'guest_count' => 4,
            'status' => Reservation::STATUS_PENDING,
        ]);
    }

    /**
     * 1. Orders and Order Items List & Feed Isolation
     */
    public function test_read_and_list_isolation_for_orders_and_order_items(): void
    {
        // Owner A views orders feed
        $feedResponse = $this->actingAs($this->ownerA)->getJson(route('admin.orders.feed'));
        $feedResponse->assertStatus(200);

        // Feed must contain Vendor A's order, but NOT Vendor B's order
        $html = $feedResponse->json('html');
        $this->assertStringContainsString('ORD-A-001', $html);
        $this->assertStringNotContainsString('ORD-B-001', $html);

        // Owner A cannot update status of Order B
        $updateResponse = $this->actingAs($this->ownerA)->post(route('admin.orders.status', $this->orderB), [
            'status' => 'preparing',
        ]);
        $this->assertTrue(in_array($updateResponse->status(), [403, 404], true));

        // State of Order B remains unchanged
        $this->assertEquals('pending', $this->orderB->fresh()->status);
    }

    /**
     * 2. Customer List, Search, Filter & Pagination Isolation
     */
    public function test_customer_list_search_and_filter_isolation(): void
    {
        // Owner A views customers index
        $response = $this->actingAs($this->ownerA)->get(route('admin.customers.index'));
        $response->assertStatus(200);

        $customersList = $response->viewData('customers');
        $this->assertTrue($customersList->pluck('id')->contains($this->customerA->id));
        $this->assertFalse($customersList->pluck('id')->contains($this->customerB->id));

        // Owner A cannot view Customer B's profile page
        $showResponse = $this->actingAs($this->ownerA)->get(route('admin.customers.show', $this->customerB));
        $this->assertTrue(in_array($showResponse->status(), [403, 404], true));

        // Owner A cannot update Customer B
        $updateResponse = $this->actingAs($this->ownerA)->post(route('admin.customers.update', $this->customerB), [
            'name' => 'Tampered Bob',
        ]);
        $this->assertTrue(in_array($updateResponse->status(), [403, 404], true));
        $this->assertEquals('Bob Beta', $this->customerB->fresh()->name);
    }

    /**
     * 3. Customer Export Isolation (No cross-tenant data leakage in CSV)
     */
    public function test_customer_export_strictly_isolates_records(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.customers.export'));
        $response->assertStatus(200);

        $content = $response->streamedContent();

        // Must contain Vendor A's customer
        $this->assertStringContainsString('Alice Alpha', $content);
        $this->assertStringContainsString('alice@alpha.test', $content);

        // Must NEVER contain Vendor B's customer
        $this->assertStringNotContainsString('Bob Beta', $content);
        $this->assertStringNotContainsString('bob@beta.test', $content);
    }

    /**
     * 4. Cross-Tenant Product Injection in Order Submission is Rejected
     */
    public function test_cross_tenant_product_injection_in_storefront_order_is_rejected(): void
    {
        $action = app(CreateOrderAction::class);

        $dto = new CreateOrderDTO(
            locationId: $this->locationA->id,
            items: [
                new OrderItemDTO(
                    productId: $this->productB->id, // Belongs to Vendor B!
                    quantity: 1
                ),
            ],
            type: 'dine_in',
            tableNumber: 'A-101',
            customerName: 'Attacker'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Product #{$this->productB->id} does not belong to this vendor.");

        $action->execute($this->vendorA, $dto);

        // Verify no order was created in database
        $this->assertDatabaseMissing('orders', [
            'customer_name' => 'Attacker',
            'vendor_id' => $this->vendorA->id,
        ]);
    }

    /**
     * 5. Cross-Tenant Location Injection in Order Submission is Rejected
     */
    public function test_cross_tenant_location_injection_in_storefront_order_is_rejected(): void
    {
        $action = app(CreateOrderAction::class);

        $dto = new CreateOrderDTO(
            locationId: $this->locationB->id, // Belongs to Vendor B!
            items: [
                new OrderItemDTO(
                    productId: $this->productA->id,
                    quantity: 1
                ),
            ],
            type: 'dine_in',
            tableNumber: 'A-101',
            customerName: 'Attacker Location'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The selected location does not belong to this vendor.');

        $action->execute($this->vendorA, $dto);

        $this->assertDatabaseMissing('orders', [
            'customer_name' => 'Attacker Location',
            'vendor_id' => $this->vendorA->id,
        ]);
    }

    /**
     * 6. Cross-Tenant Product in Pre-Order Reservation is Rejected
     */
    public function test_cross_tenant_preorder_product_in_reservation_is_rejected(): void
    {
        $response = $this->postJson(route('client.reserve.submit', ['vendor_slug' => $this->vendorA->slug]), [
            'customer_name' => 'PreOrder Attacker',
            'customer_email' => 'attacker@test.com',
            'customer_phone' => '+37499112233',
            'guest_count' => 2,
            'reservation_date' => now()->addDays(5)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'location_id' => $this->locationA->id,
            'pre_order_items' => [
                [
                    'product_id' => $this->productB->id, // Belongs to Vendor B
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');

        // Verify reservation was NOT persisted
        $this->assertDatabaseMissing('reservations', [
            'customer_name' => 'PreOrder Attacker',
            'vendor_id' => $this->vendorA->id,
        ]);
    }

    /**
     * 7. Cross-Tenant Category Foreign Key Injection in Product Creation is Rejected
     */
    public function test_product_store_rejects_foreign_category_id_injection(): void
    {
        $response = $this->actingAs($this->ownerA)->post(route('admin.menu.products.store'), [
            'name' => 'Malicious Product',
            'category_id' => $this->categoryB->id, // Foreign category belonging to Vendor B
            'price' => 1000,
        ]);

        // FormRequest authorize() returns false => 403 Forbidden
        $response->assertStatus(403);

        $this->assertDatabaseMissing('products', [
            'name' => 'Malicious Product',
            'vendor_id' => $this->vendorA->id,
        ]);
    }

    /**
     * 8. Product Update and Deletion Isolation
     */
    public function test_product_update_and_delete_isolation_across_tenants(): void
    {
        // Owner A attempts to update Product B
        $updateResponse = $this->actingAs($this->ownerA)->post(route('admin.menu.products.update', $this->productB), [
            'name' => 'Hacked Steak',
            'price' => 10,
        ]);
        $this->assertTrue(in_array($updateResponse->status(), [403, 404], true));

        // Owner A attempts to toggle availability of Product B
        $toggleResponse = $this->actingAs($this->ownerA)->post(route('admin.menu.products.toggle', $this->productB));
        $this->assertTrue(in_array($toggleResponse->status(), [403, 404], true));

        // State remains unchanged
        $this->assertEquals('Beta Steak', $this->productB->fresh()->name);
        $this->assertEquals(7500, (int) $this->productB->fresh()->price);
    }

    /**
     * 9. TenantCache Key Partitioning & Value Isolation
     */
    public function test_tenant_cache_keys_and_values_are_strictly_isolated(): void
    {
        TenantCache::put($this->vendorA, 'settings_cache', ['tenant' => 'Alpha']);
        TenantCache::put($this->vendorB, 'settings_cache', ['tenant' => 'Beta']);

        $cachedA = TenantCache::get($this->vendorA, 'settings_cache');
        $cachedB = TenantCache::get($this->vendorB, 'settings_cache');

        $this->assertEquals('Alpha', $cachedA['tenant']);
        $this->assertEquals('Beta', $cachedB['tenant']);

        // Verify keys do not collide
        $keyA = TenantCache::key($this->vendorA, 'settings_cache');
        $keyB = TenantCache::key($this->vendorB, 'settings_cache');
        $this->assertNotEquals($keyA, $keyB);
        $this->assertStringContainsString($this->vendorA->uuid, $keyA);
        $this->assertStringContainsString($this->vendorB->uuid, $keyB);
    }

    /**
     * 10. File Storage Isolation & Cross-Vendor Storage Operation Prevention
     */
    public function test_storage_service_enforces_isolated_paths_and_blocks_cross_vendor_uploads(): void
    {
        $storageService = app(StorageService::class);

        // Upload as Vendor A
        $file = UploadedFile::fake()->image('dish.jpg', 200, 200);
        $vendorStorageFile = $storageService->store(
            file: $file,
            namespace: 'products',
            vendor: $this->vendorA
        );

        $this->assertEquals($this->vendorA->id, $vendorStorageFile->vendor_id);
        $this->assertStringContainsString("vendors/{$this->vendorA->uuid}/products", $vendorStorageFile->path);

        // Authenticated as Owner A, attempting to upload directly to Vendor B must be rejected
        $this->actingAs($this->ownerA);
        $this->expectException(InvalidStoragePathException::class);
        $this->expectExceptionMessage('Cross-vendor storage operation is strictly prohibited.');

        $storageService->store(
            file: $file,
            namespace: 'products',
            vendor: $this->vendorB
        );
    }

    /**
     * 11. Background Jobs Tenant Context Isolation
     */
    public function test_translate_menu_job_executes_within_isolated_tenant_context(): void
    {
        $aiMock = $this->createMock(AiMenuService::class);
        $aiMock->method('translateMenuBatch')->willReturn([
            "cat_{$this->categoryA->id}" => 'Alpha Starters (EN)',
            "prod_{$this->productA->id}" => 'Alpha Dish (EN)',
        ]);

        $job = new TranslateMenuJob($this->vendorA->id, 'en', true);
        $job->handle($aiMock);

        // Verify Category A was updated
        $this->categoryA->refresh();
        $this->assertArrayHasKey('en', $this->categoryA->name_translations ?? []);

        // Verify Category B belonging to Vendor B was NEVER touched
        $this->categoryB->refresh();
        $this->assertArrayNotHasKey('en', $this->categoryB->name_translations ?? []);
    }

    /**
     * 12. Analytics Logs Multi-Tenant Partitioning
     */
    public function test_analytics_metrics_remain_strictly_partitioned(): void
    {
        AnalyticsLog::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'channel' => 'dine_in',
            'visit_date' => now()->toDateString(),
            'ip_address' => '127.0.0.1',
        ]);

        AnalyticsLog::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'channel' => 'ordering',
            'visit_date' => now()->toDateString(),
            'ip_address' => '127.0.0.1',
        ]);

        // Dashboard for Owner A
        $dashboardResponse = $this->actingAs($this->ownerA)->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);

        // Dine-in count for Vendor A is 1, ordering visits is 0 (scoped by TenantScope)
        $this->assertEquals(1, AnalyticsLog::where('channel', 'dine_in')->count());
        $this->assertEquals(0, AnalyticsLog::where('channel', 'ordering')->count());

        // Vendor B's ordering visit does not leak into Vendor A, but exists in the DB
        $this->assertEquals(1, AnalyticsLog::withoutTenantQuery()->where('vendor_id', $this->vendorB->id)->where('channel', 'ordering')->count());
    }

    /**
     * 13. Team Member Invitation Location Isolation
     */
    public function test_team_member_invite_blocks_cross_tenant_location_assignment(): void
    {
        $response = $this->actingAs($this->ownerA)->post(route('admin.team.store'), [
            'name' => 'Charlie Staff',
            'email' => 'charlie@test.am',
            'role' => 'staff',
            'password' => 'secret123',
            'location_id' => $this->locationB->id, // Belonging to Vendor B!
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('users', [
            'email' => 'charlie@test.am',
        ]);
    }

    /**
     * 14. Suspension Isolation (Vendor A suspended does not impact Vendor B)
     */
    public function test_tenant_suspension_is_strictly_isolated(): void
    {
        // Suspend Vendor A
        $this->vendorA->update(['is_active' => false]);

        // Storefront for Vendor A returns 404
        $storefrontA = $this->get(route('client.menu', ['vendor_slug' => $this->vendorA->slug]));
        $storefrontA->assertStatus(404);

        // Storefront for Vendor B remains active and returns 200
        $storefrontB = $this->get(route('client.menu', ['vendor_slug' => $this->vendorB->slug]));
        $storefrontB->assertStatus(200);
    }

    /**
     * 15. Security Audit Log Scoping
     */
    public function test_security_audit_logs_are_partitioned_by_vendor_id(): void
    {
        SecurityAuditLog::create([
            'vendor_id' => $this->vendorA->id,
            'event' => 'settings_updated',
            'action' => 'update',
            'actor_type' => User::class,
            'actor_id' => $this->ownerA->id,
            'ip_address' => '127.0.0.1',
        ]);

        SecurityAuditLog::create([
            'vendor_id' => $this->vendorB->id,
            'event' => 'settings_updated',
            'action' => 'update',
            'actor_type' => User::class,
            'actor_id' => $this->ownerB->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertEquals(1, SecurityAuditLog::where('vendor_id', $this->vendorA->id)->count());
        $this->assertEquals(1, SecurityAuditLog::where('vendor_id', $this->vendorB->id)->count());
    }
}
