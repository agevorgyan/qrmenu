<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\PosPayment;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantContext;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosSecurityAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Location $locationA;

    protected User $managerA;

    protected User $cashierA;

    protected User $waiterA;

    protected User $chefA;

    protected PosDevice $deviceA;

    protected RestaurantTable $tableA;

    protected Product $productA;

    protected Vendor $vendorB;

    protected User $managerB;

    protected Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $proPlan = SubscriptionPlan::where('name', 'Pro')->first() ?? SubscriptionPlan::first();

        // Vendor A setup
        $this->vendorA = Vendor::create([
            'name' => 'Armenian Kitchen Cascade',
            'slug' => 'armenian-kitchen-cascade',
            'subscription_plan_id' => $proPlan->id,
            'status' => 'active',
            'currency' => 'AMD',
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Downtown Cascade',
            'slug' => 'downtown-cascade',
            'address' => '10 Tamanyan St, Yerevan',
            'is_active' => true,
        ]);

        // Manager A (with PIN 1234)
        $this->managerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Aram Manager',
            'email' => 'manager.a@example.com',
            'password' => Hash::make('password123'),
            'role' => 'manager',
            'pin_code' => Hash::make('1234'),
            'pos_mode' => 'manager',
        ]);

        // Cashier A (with PIN 5678)
        $this->cashierA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Anahit Cashier',
            'email' => 'cashier.a@example.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'pin_code' => Hash::make('5678'),
            'pos_mode' => 'cashier',
        ]);

        // Waiter A (with PIN 9999)
        $this->waiterA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Davit Waiter',
            'email' => 'waiter.a@example.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'pin_code' => Hash::make('9999'),
            'pos_mode' => 'waiter',
        ]);

        // Chef A
        $this->chefA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Karen Chef',
            'email' => 'chef.a@example.com',
            'password' => Hash::make('password123'),
            'role' => 'chef',
            'pos_mode' => 'kds',
        ]);

        $this->deviceA = PosDevice::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Main Terminal 01',
            'device_type' => 'tablet',
            'pairing_code' => 'TEST01',
            'is_active' => true,
            'is_paired' => true,
        ]);

        $this->tableA = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => 'Table 10',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $category = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Main Courses',
            'slug' => 'main-courses',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $category->id,
            'name' => 'Tjvjik Special',
            'slug' => 'tjvjik-special',
            'price' => 3500.00,
            'is_available' => true,
        ]);

        // Vendor B setup (Foreign tenant)
        auth()->logout();
        app(TenantContext::class)->setTenant(null);

        $this->vendorB = Vendor::create([
            'name' => 'Sevan Seafood Grill',
            'slug' => 'sevan-seafood-grill',
            'subscription_plan_id' => $proPlan->id,
            'status' => 'active',
            'currency' => 'AMD',
        ]);

        $locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Sevan Beachside',
            'slug' => 'sevan-beachside',
            'address' => 'Sevan Peninsula',
            'is_active' => true,
        ]);

        $this->managerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Sevan Manager',
            'email' => 'manager.b@example.com',
            'password' => Hash::make('password123'),
            'role' => 'manager',
        ]);

        $this->orderB = Order::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $locationB->id,
            'order_number' => 'POS-SEVAN-999',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 12000,
            'total_amount' => 12000,
        ]);

        // Restore context to Vendor A
        auth()->logout();
        app(TenantContext::class)->setTenant($this->vendorA);
    }

    /**
     * 1. Fine-Grained Permissions: Waiters cannot open or close cash shifts.
     */
    public function test_waiter_role_denied_from_cash_shifts(): void
    {
        // Waiter attempts to open shift -> 403 Forbidden
        $response = $this->actingAs($this->waiterA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson('/pos/api/shifts/open', [
                'opening_float' => 20000,
                'opening_notes' => 'Attempt by waiter',
            ]);

        $response->assertStatus(403);

        // Waiter attempts to get current shift -> 403 Forbidden
        $shiftResponse = $this->actingAs($this->waiterA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->getJson('/pos/api/shifts/current');

        $shiftResponse->assertStatus(403);
    }

    /**
     * 2. Payment authorization: Waiter without payment permission is denied without manager approval,
     * but succeeds when authorized by manager PIN.
     */
    public function test_waiter_payment_requires_manager_approval(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'restaurant_table_id' => $this->tableA->id,
            'order_number' => 'POS-SEC-01',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 3500,
            'total_amount' => 3500,
        ]);

        // Attempt without manager PIN -> 403
        $response = $this->actingAs($this->waiterA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson("/pos/api/orders/{$order->id}/payment", [
                'payment_method' => 'cash',
                'amount' => 3500,
                'cash_tendered' => 4000,
            ]);

        $response->assertStatus(403);

        // Attempt with INVALID manager PIN -> 403
        $invalidPinResponse = $this->actingAs($this->waiterA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson("/pos/api/orders/{$order->id}/payment", [
                'payment_method' => 'cash',
                'amount' => 3500,
                'cash_tendered' => 4000,
                'manager_pin' => '0000',
            ]);

        $invalidPinResponse->assertStatus(403);

        // Attempt with VALID manager PIN (1234) -> 200 OK
        $validResponse = $this->actingAs($this->waiterA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson("/pos/api/orders/{$order->id}/payment", [
                'payment_method' => 'cash',
                'amount' => 3500,
                'cash_tendered' => 4000,
                'manager_pin' => '1234',
            ]);

        $validResponse->assertStatus(200);
        $validResponse->assertJson(['success' => true]);

        // Verify manager approval was audited in pos_operation_logs
        $this->assertDatabaseHas('pos_operation_logs', [
            'vendor_id' => $this->vendorA->id,
            'user_id' => $this->managerA->id,
            'action' => 'manager_approval',
        ]);
    }

    /**
     * 3. Refund supervision: Cashier cannot refund without manager PIN approval.
     */
    public function test_cashier_refund_requires_manager_approval(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'order_number' => 'POS-REF-01',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'completed',
            'subtotal' => 7000,
            'total_amount' => 7000,
            'paid_amount' => 7000,
        ]);

        $payment = PosPayment::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendorA->id,
            'order_id' => $order->id,
            'user_id' => $this->cashierA->id,
            'payment_method' => 'card',
            'amount' => 7000,
            'status' => 'completed',
            'currency' => 'AMD',
        ]);

        // Cashier attempts refund without PIN -> 403
        $response = $this->actingAs($this->cashierA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson("/pos/api/payments/{$payment->id}/refund", [
                'amount' => 7000,
                'reason' => 'Customer dissatisfaction',
            ]);

        $response->assertStatus(403);

        // Cashier attempts refund with valid manager PIN -> 200 OK
        $approvedResponse = $this->actingAs($this->cashierA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson("/pos/api/payments/{$payment->id}/refund", [
                'amount' => 7000,
                'reason' => 'Customer dissatisfaction',
                'manager_pin' => '1234',
            ]);

        $approvedResponse->assertStatus(200);
        $approvedResponse->assertJson(['success' => true]);
        $this->assertEquals(7000, $payment->fresh()->refunded_amount);
    }

    /**
     * 4. Manager override discounts: Require manager approval.
     */
    public function test_manager_override_discount_requires_manager_approval(): void
    {
        $payload = [
            'location_id' => $this->locationA->id,
            'type' => 'dine_in',
            'table_number' => 'Table 10',
            'restaurant_table_id' => $this->tableA->id,
            'guest_count' => 2,
            'discount_amount' => 1000,
            'discount_type' => 'manager_override',
            'discount_reason' => 'VIP table override',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'kitchen_station' => 'kitchen',
                ],
            ],
        ];

        // Waiter attempts without manager PIN -> 403
        $response = $this->actingAs($this->waiterA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson('/pos/api/orders', $payload);

        $response->assertStatus(403);

        // Waiter attempts with manager PIN -> 201 Created
        $payload['manager_pin'] = '1234';
        $approvedResponse = $this->actingAs($this->waiterA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson('/pos/api/orders', $payload);

        $approvedResponse->assertStatus(201);
        $approvedResponse->assertJson(['success' => true]);
    }

    /**
     * 5. Kitchen Display System Access: Unauthorized role (waiter) denied from KDS views and tickets.
     */
    public function test_kds_access_control(): void
    {
        // Waiter denied from KDS tickets -> 403
        $waiterResponse = $this->actingAs($this->waiterA)
            ->getJson('/pos/api/kds/tickets');
        $waiterResponse->assertStatus(403);

        // Chef permitted -> 200
        $chefResponse = $this->actingAs($this->chefA)
            ->getJson('/pos/api/kds/tickets');
        $chefResponse->assertStatus(200);

        // Manager permitted -> 200
        $managerResponse = $this->actingAs($this->managerA)
            ->getJson('/pos/api/kds/tickets');
        $managerResponse->assertStatus(200);
    }

    /**
     * 6. Strict Multi-Tenant Isolation: Vendor A staff cannot access or mutate Vendor B orders.
     */
    public function test_multi_tenant_isolation_boundary(): void
    {
        // Vendor A manager attempts to view Vendor B order -> 403 or 404 (scoped out)
        $showResponse = $this->actingAs($this->managerA)
            ->getJson("/pos/api/orders/{$this->orderB->id}");
        $this->assertTrue(in_array($showResponse->status(), [403, 404], true));

        // Vendor A cashier attempts to pay Vendor B order -> 403 or 404
        $payResponse = $this->actingAs($this->cashierA)
            ->postJson("/pos/api/orders/{$this->orderB->id}/payment", [
                'payment_method' => 'cash',
                'amount' => 12000,
                'cash_tendered' => 12000,
            ]);
        $this->assertTrue(in_array($payResponse->status(), [403, 404], true));

        // Vendor A manager attempts to cancel Vendor B order -> 403 or 404
        $statusResponse = $this->actingAs($this->managerA)
            ->postJson("/pos/api/orders/{$this->orderB->id}/status", [
                'status' => 'cancelled',
            ]);
        $this->assertTrue(in_array($statusResponse->status(), [403, 404], true));
    }

    /**
     * 7. Concurrency Protection: Terminals submitting outdated lock_version receive HTTP 409 Conflict.
     */
    public function test_optimistic_concurrency_conflict_handling(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'order_number' => 'POS-LOCK-01',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 3500,
            'total_amount' => 3500,
            'lock_version' => 1,
        ]);

        // Terminal 1 updates order status with expected lock_version = 1 -> succeeds, increments lock_version to 2
        $response1 = $this->actingAs($this->managerA)
            ->postJson("/pos/api/orders/{$order->id}/status", [
                'status' => 'preparing',
                'lock_version' => 1,
            ]);

        $response1->assertStatus(200);
        $this->assertEquals(2, $order->fresh()->lock_version);

        // Terminal 2 attempts update with stale lock_version = 1 -> receives 409 Conflict
        $response2 = $this->actingAs($this->managerA)
            ->postJson("/pos/api/orders/{$order->id}/status", [
                'status' => 'cancelled',
                'lock_version' => 1,
            ]);

        $response2->assertStatus(409);
        $response2->assertJson([
            'success' => false,
            'code' => 'CONCURRENCY_CONFLICT',
        ]);
    }

    /**
     * 8. Offline Sync Replay & Idempotency: Re-syncing an existing client_sync_id does not duplicate operations.
     */
    public function test_offline_sync_idempotency_and_replay_protection(): void
    {
        $clientSyncId = 'SYNC-OP-UUID-'.Str::random(10);

        $syncPayload = [
            'batch_id' => 'BATCH-TEST-'.Str::random(6),
            'operations' => [
                [
                    'client_sync_id' => $clientSyncId,
                    'entity_type' => 'table_status',
                    'action' => 'update',
                    'client_timestamp' => now()->toIso8601String(),
                    'payload' => [
                        'table_id' => $this->tableA->id,
                        'status' => 'cleaning',
                    ],
                ],
            ],
        ];

        // First synchronization attempt -> 200 OK (synced: 1)
        $firstSync = $this->actingAs($this->managerA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson('/pos/api/offline/sync', $syncPayload);

        $firstSync->assertStatus(200);
        $firstSync->assertJson([
            'success' => true,
            'synced_count' => 1,
        ]);

        // Replay of the exact same batch -> 200 OK, recognized as duplicate without error or duplicate record
        $replaySync = $this->actingAs($this->managerA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->postJson('/pos/api/offline/sync', $syncPayload);

        $replaySync->assertStatus(200);
        $operations = $replaySync->json('operations');
        $this->assertEquals('duplicate', $operations[0]['status']);
    }

    /**
     * 9. Performance & N+1 Prevention: Order listing endpoint uses eager loading with minimal constant queries.
     */
    public function test_order_listing_query_performance(): void
    {
        // Seed 10 sample orders
        for ($i = 1; $i <= 10; $i++) {
            $o = Order::create([
                'vendor_id' => $this->vendorA->id,
                'location_id' => $this->locationA->id,
                'restaurant_table_id' => $this->tableA->id,
                'cashier_id' => $this->cashierA->id,
                'waiter_id' => $this->waiterA->id,
                'order_number' => "POS-PERF-{$i}",
                'source' => 'pos',
                'type' => 'dine_in',
                'status' => 'completed',
                'subtotal' => 3500,
                'total_amount' => 3500,
            ]);

            OrderItem::create([
                'order_id' => $o->id,
                'product_id' => $this->productA->id,
                'product_name' => $this->productA->name,
                'unit_price' => $this->productA->price,
                'quantity' => 2,
                'subtotal' => 7000,
                'status' => 'ready',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->managerA)
            ->withHeader('X-POS-Device-UUID', $this->deviceA->uuid)
            ->getJson('/pos/api/orders');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));

        // Query count must be bounded and small (not 10 + 10*N queries)
        $this->assertLessThanOrEqual(12, $queryCount, "Orders index executed {$queryCount} queries, indicating an N+1 problem.");
    }
}
