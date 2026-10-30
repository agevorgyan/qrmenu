<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosSetting;
use App\Models\PosShift;
use App\Models\PosSyncBatch;
use App\Models\PosSyncConflict;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantContext;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected RestaurantTable $table;

    protected User $owner;

    protected User $cashier;

    protected PosDevice $device;

    protected Vendor $otherVendor;

    protected User $otherCashier;

    protected PosDevice $otherDevice;

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
            'name' => 'Yerevan Bistro',
            'slug' => 'yerevan-bistro',
            'email' => 'pos@yerevanbistro.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Hot Dishes',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Lamb Kebab',
            'price' => 3500,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-01',
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
            'lock_version' => 1,
            'sort_order' => 1,
        ]);

        $this->owner = User::create([
            'name' => 'Arthur Owner',
            'email' => 'owner@yerevanbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Mariam Cashier',
            'email' => 'cashier@yerevanbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'cashier',
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $this->device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Main Cashier Terminal',
            'device_type' => 'desktop',
            'pairing_code' => '123456',
            'is_active' => true,
            'is_paired' => true,
            'registered_at' => now(),
        ]);

        PosSetting::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'allow_offline_orders' => true,
            'max_offline_hours' => 48,
            'max_offline_orders' => 10,
            'max_offline_amount' => 50000,
            'allow_offline_cash_payments' => true,
        ]);

        // Second Vendor for Tenant Isolation
        $this->otherVendor = Vendor::create([
            'name' => 'Other Cafe',
            'slug' => 'other-cafe',
            'email' => 'pos@othercafe.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->otherCashier = User::create([
            'name' => 'Other Cashier',
            'email' => 'cashier@othercafe.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'cashier',
            'is_active' => true,
        ]);

        $this->otherDevice = PosDevice::create([
            'vendor_id' => $this->otherVendor->id,
            'name' => 'Other POS Terminal',
            'device_type' => 'desktop',
            'pairing_code' => '654321',
            'is_active' => true,
            'is_paired' => true,
            'registered_at' => now(),
        ]);
    }

    public function test_offline_bootstrap_manifest_endpoint_returns_catalog_tables_staff_and_policy(): void
    {
        $response = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->getJson('/pos/api/offline/manifest');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $manifest = $response->json('manifest');
        $this->assertNotEmpty($manifest);
        $this->assertEquals('2.0', $manifest['manifest_version']);
        $this->assertEquals($this->device->uuid, $manifest['device']['uuid']);

        // Staff list
        $this->assertNotEmpty($manifest['staff']);
        $this->assertEquals('Mariam Cashier', $manifest['staff'][1]['name']);
        $this->assertTrue($manifest['staff'][1]['has_pin']);
        $this->assertNotNull($manifest['staff'][1]['offline_pin_hmac']);

        // Catalog & Hash
        $this->assertNotEmpty($manifest['catalog']['products']);
        $this->assertEquals('Lamb Kebab', $manifest['catalog']['products'][0]['name']);
        $this->assertNotEmpty($manifest['catalog_hash']);

        // Tables
        $this->assertNotEmpty($manifest['tables']);
        $this->assertEquals('T-01', $manifest['tables'][0]['table_number']);

        // Policy
        $this->assertTrue($manifest['policy']['allow_offline_orders']);
        $this->assertEquals(48, $manifest['policy']['max_offline_hours']);
    }

    public function test_offline_batch_synchronization_creates_orders_and_updates_tables(): void
    {
        $syncId = 'OFF-ORD-'.Str::random(10);
        $payload = [
            'batch_id' => 'BATCH-'.Str::random(8),
            'client_started_at' => now()->subMinutes(30)->toISOString(),
            'client_ended_at' => now()->toISOString(),
            'operations' => [
                [
                    'client_sync_id' => $syncId,
                    'entity_type' => 'order',
                    'action' => 'create',
                    'client_timestamp' => now()->subMinutes(25)->toISOString(),
                    'payload' => [
                        'order_type' => 'dine_in',
                        'table_number' => 'T-01',
                        'restaurant_table_id' => $this->table->id,
                        'guest_count' => 2,
                        'status' => 'confirmed',
                        'total_amount' => 7000,
                        'items' => [
                            [
                                'product_id' => $this->product->id,
                                'product_name' => $this->product->name,
                                'quantity' => 2,
                                'unit_price' => 3500,
                                'kitchen_station' => 'kitchen',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson('/pos/api/offline/sync', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'batch_status' => 'completed',
            'synced_count' => 1,
            'conflict_count' => 0,
        ]);

        // Verify order created in database
        $this->assertDatabaseHas('orders', [
            'vendor_id' => $this->vendor->id,
            'client_sync_id' => $syncId,
            'is_offline_created' => true,
            'status' => 'confirmed',
            'restaurant_table_id' => $this->table->id,
        ]);

        // Table status updated to occupied
        $this->assertEquals('occupied', $this->table->fresh()->status);
        $this->assertNotNull($this->table->fresh()->current_order_id);
    }

    public function test_offline_batch_synchronization_idempotency_prevents_duplicate_orders(): void
    {
        $syncId = 'OFF-ORD-IDEMPOTENT-'.Str::random(8);
        $payload = [
            'batch_id' => 'BATCH-'.Str::random(8),
            'operations' => [
                [
                    'client_sync_id' => $syncId,
                    'entity_type' => 'order',
                    'action' => 'create',
                    'client_timestamp' => now()->toISOString(),
                    'payload' => [
                        'order_type' => 'dine_in',
                        'table_number' => 'T-01',
                        'restaurant_table_id' => $this->table->id,
                        'guest_count' => 1,
                        'status' => 'confirmed',
                        'total_amount' => 3500,
                        'items' => [
                            [
                                'product_id' => $this->product->id,
                                'product_name' => $this->product->name,
                                'quantity' => 1,
                                'unit_price' => 3500,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // First Sync
        $res1 = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson('/pos/api/offline/sync', $payload);
        $res1->assertStatus(200);
        $res1->assertJson(['synced_count' => 1]);

        $orderCountBefore = Order::where('client_sync_id', $syncId)->count();
        $this->assertEquals(1, $orderCountBefore);

        // Second Sync with exact same client_sync_id
        $res2 = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson('/pos/api/offline/sync', $payload);
        $res2->assertStatus(200);

        // Assert operation was flagged as duplicate without creating a new order
        $ops = $res2->json('operations');
        $this->assertEquals('duplicate', $ops[0]['status']);
        $this->assertEquals(1, Order::where('client_sync_id', $syncId)->count());
    }

    public function test_offline_payment_synchronization_completes_order_and_updates_shift(): void
    {
        // Open shift for device
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->cashier->id,
            'pos_device_id' => $this->device->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
            'opened_at' => now()->subHours(2),
        ]);

        $orderSyncId = 'OFF-ORD-PAY-'.Str::random(8);
        $paymentSyncId = 'OFF-PAY-'.Str::random(8);

        // Batch containing order creation followed by cash payment
        $payload = [
            'batch_id' => 'BATCH-PAY-'.Str::random(8),
            'operations' => [
                [
                    'client_sync_id' => $orderSyncId,
                    'entity_type' => 'order',
                    'action' => 'create',
                    'client_timestamp' => now()->subMinutes(10)->toISOString(),
                    'payload' => [
                        'order_type' => 'dine_in',
                        'table_number' => 'T-01',
                        'restaurant_table_id' => $this->table->id,
                        'guest_count' => 1,
                        'status' => 'confirmed',
                        'total_amount' => 3500,
                        'items' => [
                            [
                                'product_id' => $this->product->id,
                                'product_name' => $this->product->name,
                                'quantity' => 1,
                                'unit_price' => 3500,
                            ],
                        ],
                    ],
                ],
                [
                    'client_sync_id' => $paymentSyncId,
                    'entity_type' => 'payment',
                    'action' => 'pay',
                    'client_timestamp' => now()->subMinutes(5)->toISOString(),
                    'payload' => [
                        'order_client_sync_id' => $orderSyncId,
                        'payment_method' => 'cash',
                        'amount' => 3500,
                        'cash_tendered' => 5000,
                        'tip_amount' => 500,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson('/pos/api/offline/sync', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'synced_count' => 2,
            'conflict_count' => 0,
        ]);

        $order = Order::where('client_sync_id', $orderSyncId)->first();
        $this->assertNotNull($order);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(3500, (float) $order->paid_amount);
        $this->assertEquals(1500, (float) $order->change_amount);
        $this->assertEquals(500, (float) $order->tip_amount);

        // Verify payment record
        $this->assertDatabaseHas('pos_payments', [
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'client_sync_id' => $paymentSyncId,
            'is_offline' => true,
            'amount' => 3500,
            'payment_method' => 'cash',
        ]);

        // Shift metrics incremented
        $shift->refresh();
        $this->assertEquals(3500, (float) $shift->total_cash_sales);
        $this->assertEquals(13500, (float) $shift->expected_cash);
        $this->assertEquals(500, (float) $shift->total_tips);
    }

    public function test_table_collision_is_flagged_as_conflict_without_dropping_offline_order(): void
    {
        // Table 1 was occupied online with an active order
        $onlineOrder = Order::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ONLINE-999',
            'order_type' => 'dine_in',
            'restaurant_table_id' => $this->table->id,
            'status' => 'preparing',
            'total_amount' => 5000,
            'subtotal' => 5000,
        ]);

        $this->table->update([
            'status' => 'occupied',
            'current_order_id' => $onlineOrder->id,
        ]);

        // Offline device also created an order on Table 1 while offline
        $offlineSyncId = 'OFF-COLLISION-'.Str::random(8);
        $payload = [
            'batch_id' => 'BATCH-COLLISION-'.Str::random(8),
            'operations' => [
                [
                    'client_sync_id' => $offlineSyncId,
                    'entity_type' => 'order',
                    'action' => 'create',
                    'client_timestamp' => now()->toISOString(),
                    'payload' => [
                        'order_type' => 'dine_in',
                        'table_number' => 'T-01',
                        'restaurant_table_id' => $this->table->id,
                        'total_amount' => 3500,
                        'items' => [
                            [
                                'product_id' => $this->product->id,
                                'product_name' => $this->product->name,
                                'quantity' => 1,
                                'unit_price' => 3500,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson('/pos/api/offline/sync', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'batch_status' => 'partially_failed',
            'conflict_count' => 1,
        ]);

        // The offline order MUST still be created and safely preserved
        $this->assertDatabaseHas('orders', [
            'vendor_id' => $this->vendor->id,
            'client_sync_id' => $offlineSyncId,
            'is_offline_created' => true,
        ]);

        // A conflict record must be stored for manager review
        $this->assertDatabaseHas('pos_sync_conflicts', [
            'vendor_id' => $this->vendor->id,
            'conflict_type' => 'table_collision',
            'client_sync_id' => $offlineSyncId,
            'is_resolved' => false,
        ]);
    }

    public function test_table_version_mismatch_conflict_preserves_newer_server_state(): void
    {
        // Table lock version on server is 5
        $this->table->update([
            'lock_version' => 5,
            'status' => 'ready',
        ]);

        // Client attempts to push table status using stale lock_version 2
        $tableSyncId = 'OFF-TABLE-MISMATCH-'.Str::random(8);
        $payload = [
            'batch_id' => 'BATCH-STALE-'.Str::random(8),
            'operations' => [
                [
                    'client_sync_id' => $tableSyncId,
                    'entity_type' => 'table_status',
                    'action' => 'status',
                    'client_timestamp' => now()->toISOString(),
                    'payload' => [
                        'table_id' => $this->table->id,
                        'status' => 'available',
                        'lock_version' => 2, // stale!
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson('/pos/api/offline/sync', $payload);

        $response->assertStatus(200);

        // Server status preserved
        $this->assertEquals('ready', $this->table->fresh()->status);
        $this->assertEquals(5, $this->table->fresh()->lock_version);

        // Conflict recorded
        $this->assertDatabaseHas('pos_sync_conflicts', [
            'vendor_id' => $this->vendor->id,
            'conflict_type' => 'version_mismatch',
            'resolution_strategy' => 'server_wins',
            'client_sync_id' => $tableSyncId,
        ]);
    }

    public function test_offline_safety_limits_exceeded_creates_conflict_record(): void
    {
        // Vendor setting max_offline_amount is 50,000 AMD. Client pushes an offline order of 80,000 AMD.
        $syncId = 'OFF-EXCESSIVE-'.Str::random(8);
        $payload = [
            'batch_id' => 'BATCH-EXCESSIVE-'.Str::random(8),
            'operations' => [
                [
                    'client_sync_id' => $syncId,
                    'entity_type' => 'payment',
                    'action' => 'pay',
                    'client_timestamp' => now()->toISOString(),
                    'payload' => [
                        'amount' => 80000,
                        'payment_method' => 'cash',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson('/pos/api/offline/sync', $payload);

        $response->assertStatus(200);

        // Conflict limit_exceeded is recorded
        $this->assertDatabaseHas('pos_sync_conflicts', [
            'vendor_id' => $this->vendor->id,
            'conflict_type' => 'limit_exceeded',
        ]);
    }

    public function test_offline_sync_conflicts_management_and_resolution_api(): void
    {
        $batch = PosSyncBatch::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'batch_id' => 'B-CONFLICTS-TEST',
            'status' => 'partially_failed',
        ]);

        $conflict = PosSyncConflict::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'pos_sync_batch_id' => $batch->id,
            'client_sync_id' => 'CONFLICT-ITEM-001',
            'entity_type' => 'order',
            'conflict_type' => 'table_collision',
            'client_data' => ['table' => 'T-01'],
            'is_resolved' => false,
        ]);

        // 1. Manager fetches list of unresolved conflicts
        $listRes = $this->actingAs($this->owner)->getJson('/pos/api/offline/conflicts');
        $listRes->assertStatus(200);
        $listRes->assertJson(['success' => true]);
        $this->assertCount(1, $listRes->json('conflicts'));

        // 2. Manager resolves conflict
        $resolveRes = $this->actingAs($this->owner)->postJson("/pos/api/offline/conflicts/{$conflict->id}/resolve", [
            'resolution_notes' => 'Table collision reviewed and accepted by manager.',
        ]);

        $resolveRes->assertStatus(200);
        $resolveRes->assertJson([
            'success' => true,
            'message' => 'Conflict resolved successfully.',
        ]);

        $conflict->refresh();
        $this->assertTrue($conflict->is_resolved);
        $this->assertEquals($this->owner->id, $conflict->resolved_by_user_id);
        $this->assertEquals('Table collision reviewed and accepted by manager.', $conflict->resolution_notes);
    }

    public function test_strict_tenant_isolation_on_offline_manifest_and_sync(): void
    {
        // Vendor B cashier trying to use Vendor A device UUID
        $resManifest = $this->actingAs($this->otherCashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->getJson('/pos/api/offline/manifest');

        // Device belonging to Vendor A is rejected for Vendor B user
        $resManifest->assertStatus(400);
        $resManifest->assertJson(['success' => false]);

        // Conflict belonging to Vendor A created by Vendor A owner
        app(TenantContext::class)->setTenant($this->vendor);
        $this->actingAs($this->owner);

        $batch = PosSyncBatch::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'batch_id' => 'B-ISOLATION-TEST',
            'status' => 'partially_failed',
        ]);

        $conflict = PosSyncConflict::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'pos_sync_batch_id' => $batch->id,
            'client_sync_id' => 'CONFLICT-TENANT-ISO',
            'entity_type' => 'order',
            'conflict_type' => 'table_collision',
            'client_data' => ['table' => 'T-01'],
            'is_resolved' => false,
        ]);

        $resResolve = $this->actingAs($this->otherCashier)->postJson("/pos/api/offline/conflicts/{$conflict->id}/resolve", [
            'resolution_notes' => 'Hack attempt',
        ]);
        $this->assertTrue(in_array($resResolve->status(), [403, 404], true));
    }
}
