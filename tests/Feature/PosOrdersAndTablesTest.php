<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\DTOs\OrderItemDTO;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosOrdersAndTablesTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $productA;

    protected Product $productB;

    protected RestaurantTable $table1;

    protected RestaurantTable $table2;

    protected RestaurantTable $table3;

    protected User $owner;

    protected User $waiter1;

    protected User $waiter2;

    protected PosDevice $device;

    protected Vendor $otherVendor;

    protected User $otherVendorUser;

    protected RestaurantTable $otherTable;

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
            'name' => 'Tavush Alpine Lodge',
            'slug' => 'tavush-alpine-lodge',
            'email' => 'pos@tavushlodge.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Alpine Terrace',
            'slug' => 'alpine-terrace',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Grill & Steaks',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Lamb Chops',
            'price' => 5000,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $this->productB = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Grilled Trout',
            'price' => 3500,
            'is_available' => true,
            'sort_order' => 2,
        ]);

        $this->table1 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-01',
            'name' => 'Terrace 1',
            'capacity' => 4,
            'section' => 'Terrace',
            'dining_area' => 'Outdoor Terrace',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->table2 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-02',
            'name' => 'Terrace 2',
            'capacity' => 6,
            'section' => 'Terrace',
            'dining_area' => 'Outdoor Terrace',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->table3 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-03',
            'name' => 'Terrace 3',
            'capacity' => 2,
            'section' => 'Terrace',
            'dining_area' => 'Outdoor Terrace',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->owner = User::create([
            'name' => 'Gevorg Manager',
            'email' => 'gevorg@tavushlodge.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);

        $this->waiter1 = User::create([
            'name' => 'Vahan Waiter',
            'email' => 'vahan@tavushlodge.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'waiter',
            'pin_code' => Hash::make('1111'),
            'is_active' => true,
        ]);

        $this->waiter2 = User::create([
            'name' => 'Lusine Waiter',
            'email' => 'lusine@tavushlodge.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'waiter',
            'pin_code' => Hash::make('2222'),
            'is_active' => true,
        ]);

        $this->device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Terrace POS Tablet',
            'device_type' => 'tablet_android',
            'pairing_code' => '554433',
            'is_active' => true,
            'is_paired' => true,
            'registered_at' => now(),
        ]);

        $this->otherVendor = Vendor::create([
            'name' => 'Other Resort',
            'slug' => 'other-resort',
            'email' => 'pos@otherresort.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->otherVendorUser = User::create([
            'name' => 'Other Staff',
            'email' => 'staff@otherresort.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'waiter',
            'is_active' => true,
        ]);

        $otherLocation = Location::create([
            'vendor_id' => $this->otherVendor->id,
            'name' => 'Other Hall',
            'slug' => 'other-hall',
            'is_active' => true,
        ]);

        $this->otherTable = RestaurantTable::create([
            'vendor_id' => $this->otherVendor->id,
            'location_id' => $otherLocation->id,
            'table_number' => 'O-01',
            'capacity' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_get_tables_api_returns_enriched_tables_with_sections_and_statuses(): void
    {
        $response = $this->actingAs($this->waiter1)
            ->getJson('/pos/api/tables');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('tables', $data);
        $this->assertArrayHasKey('sections', $data);
        $this->assertArrayHasKey('summary', $data);

        $this->assertCount(3, $data['tables']);
        $this->assertContains('Terrace', $data['sections']);
        $this->assertEquals(3, $data['summary']['available']);

        $firstTable = $data['tables'][0];
        $this->assertEquals('T-01', $firstTable['table_number']);
        $this->assertEquals('available', $firstTable['status']);
        $this->assertEquals(4, $firstTable['capacity']);
        $this->assertEquals(1, $firstTable['lock_version']);
    }

    public function test_pos_order_creation_automatically_occupies_restaurant_table(): void
    {
        $payload = [
            'location_id' => $this->location->id,
            'type' => 'dine_in',
            'table_number' => 'T-01',
            'restaurant_table_id' => $this->table1->id,
            'guest_count' => 3,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->waiter1)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->postJson('/pos/api/orders', $payload);

        $response->assertStatus(201);
        $orderId = $response->json('order.id');

        // Check table 1 is occupied
        $freshTable = $this->table1->fresh();
        $this->assertEquals('occupied', $freshTable->status);
        $this->assertEquals($orderId, $freshTable->current_order_id);
        $this->assertEquals($this->waiter1->id, $freshTable->current_waiter_id);
        $this->assertNotNull($freshTable->occupied_at);

        // Verify API reflects occupied status
        $tablesRes = $this->actingAs($this->waiter1)->getJson('/pos/api/tables');
        $t1Data = collect($tablesRes->json('data.tables'))->firstWhere('id', $this->table1->id);
        $this->assertEquals('occupied', $t1Data['status']);
        $this->assertNotNull($t1Data['order']);
        $this->assertEquals('T-01', $t1Data['table_number']);
    }

    public function test_update_table_status_directly(): void
    {
        $response = $this->actingAs($this->waiter1)
            ->postJson("/pos/api/tables/{$this->table1->id}/status", [
                'status' => 'cleaning',
                'lock_version' => 1,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('cleaning', $this->table1->fresh()->status);
        $this->assertEquals(2, $this->table1->fresh()->lock_version);

        // Transition back to available
        $resAvailable = $this->actingAs($this->waiter1)
            ->postJson("/pos/api/tables/{$this->table1->id}/status", [
                'status' => 'available',
                'lock_version' => 2,
            ]);

        $resAvailable->assertStatus(200);
        $this->assertEquals('available', $this->table1->fresh()->status);
    }

    public function test_transfer_order_to_another_table(): void
    {
        // 1. Create order on Table 1
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-TRF-001',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table1->id,
            'table_number' => 'T-01',
            'waiter_id' => $this->waiter1->id,
            'subtotal' => 5000,
            'total_amount' => 5000,
            'currency' => 'AMD',
        ]);
        $this->table1->markOccupied($order, $this->waiter1);

        // 2. Transfer order to Table 2
        $response = $this->actingAs($this->waiter1)
            ->postJson("/pos/api/orders/{$order->id}/transfer-table", [
                'destination_table_id' => $this->table2->id,
                'reason' => 'Guest requested terrace view',
                'lock_version' => 1,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $freshOrder = $order->fresh();
        $this->assertEquals($this->table2->id, $freshOrder->restaurant_table_id);
        $this->assertEquals('T-02', $freshOrder->table_number);
        $this->assertEquals(2, $freshOrder->lock_version);

        // Table 1 should now be cleaning
        $this->assertEquals('cleaning', $this->table1->fresh()->status);

        // Table 2 should be occupied with this order
        $this->assertEquals('occupied', $this->table2->fresh()->status);
        $this->assertEquals($order->id, $this->table2->fresh()->current_order_id);

        // Check audit log
        $this->assertDatabaseHas('pos_operation_logs', [
            'vendor_id' => $this->vendor->id,
            'action' => 'pos_table_transfer',
        ]);
    }

    public function test_transfer_fails_if_destination_table_is_already_occupied(): void
    {
        $order1 = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-OCC-1',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table1->id,
            'table_number' => 'T-01',
            'total_amount' => 5000,
            'currency' => 'AMD',
        ]);
        $this->table1->markOccupied($order1, $this->waiter1);

        $order2 = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-OCC-2',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table2->id,
            'table_number' => 'T-02',
            'total_amount' => 4000,
            'currency' => 'AMD',
        ]);
        $this->table2->markOccupied($order2, $this->waiter2);

        // Attempt transfer of order 1 to occupied Table 2
        $response = $this->actingAs($this->waiter1)
            ->postJson("/pos/api/orders/{$order1->id}/transfer-table", [
                'destination_table_id' => $this->table2->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['destination_table']);
    }

    public function test_merge_orders_combines_items_and_recalculates_totals(): void
    {
        // Order 1 on Table 1
        $order1 = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-MRG-1',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table1->id,
            'table_number' => 'T-01',
            'subtotal' => 5000,
            'total_amount' => 5000,
            'guest_count' => 2,
            'currency' => 'AMD',
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'unit_price' => 5000,
            'quantity' => 1,
            'subtotal' => 5000,
            'status' => 'pending',
        ]);
        $this->table1->markOccupied($order1, $this->waiter1);

        // Order 2 on Table 2
        $order2 = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-MRG-2',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table2->id,
            'table_number' => 'T-02',
            'subtotal' => 7000,
            'total_amount' => 7000,
            'guest_count' => 3,
            'currency' => 'AMD',
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $this->productB->id,
            'product_name' => $this->productB->name,
            'unit_price' => 3500,
            'quantity' => 2,
            'subtotal' => 7000,
            'status' => 'pending',
        ]);
        $this->table2->markOccupied($order2, $this->waiter2);

        // Merge Order 1 into Order 2
        $response = $this->actingAs($this->waiter1)
            ->postJson('/pos/api/orders/merge', [
                'source_order_id' => $order1->id,
                'target_order_id' => $order2->id,
                'reason' => 'Guests joined same table',
            ]);

        $response->assertStatus(200);

        // Target Order 2 has combined total 5000 + 7000 = 12000 and 5 guests
        $freshTarget = $order2->fresh();
        $this->assertEquals(12000, $freshTarget->total_amount);
        $this->assertEquals(12000, $freshTarget->subtotal);
        $this->assertEquals(5, $freshTarget->guest_count);
        $this->assertCount(2, $freshTarget->items);

        // Source Order 1 is cancelled and linked
        $freshSource = $order1->fresh();
        $this->assertEquals('cancelled', $freshSource->status);
        $this->assertEquals($order2->id, $freshSource->parent_order_id);

        // Table 1 is freed
        $this->assertEquals('available', $this->table1->fresh()->status);
    }

    public function test_split_order_creates_child_order_and_recalculates(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-SPLIT-SRC',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table1->id,
            'table_number' => 'T-01',
            'subtotal' => 12000,
            'total_amount' => 12000,
            'currency' => 'AMD',
        ]);

        $item1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'unit_price' => 5000,
            'quantity' => 2,
            'subtotal' => 10000,
            'status' => 'pending',
        ]);

        $item2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productB->id,
            'product_name' => $this->productB->name,
            'unit_price' => 2000,
            'quantity' => 1,
            'subtotal' => 2000,
            'status' => 'pending',
        ]);

        // Split 1 quantity of item1 and all of item2 to Table 3
        $response = $this->actingAs($this->waiter1)
            ->postJson("/pos/api/orders/{$order->id}/split", [
                'target_table_id' => $this->table3->id,
                'items' => [
                    ['order_item_id' => $item1->id, 'quantity' => 1],
                    ['order_item_id' => $item2->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(200);
        $splitData = $response->json();

        // Source order now has remaining 1 x item1 = 5000 AMD
        $freshSource = $order->fresh();
        $this->assertEquals(5000, $freshSource->subtotal);
        $this->assertEquals(5000, $freshSource->total_amount);

        // Split child order has 1 x item1 (5000) + 1 x item2 (2000) = 7000 AMD
        $childOrder = Order::where('parent_order_id', $order->id)->first();
        $this->assertNotNull($childOrder);
        $this->assertEquals(7000, $childOrder->subtotal);
        $this->assertEquals('by_item', $childOrder->split_type);
        $this->assertEquals($this->table3->id, $childOrder->restaurant_table_id);

        // Destination table 3 is now occupied with child order
        $this->assertEquals('occupied', $this->table3->fresh()->status);
        $this->assertEquals($childOrder->id, $this->table3->fresh()->current_order_id);
    }

    public function test_assign_waiter_updates_order_and_table_waiter(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-WAITER-001',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table1->id,
            'table_number' => 'T-01',
            'waiter_id' => $this->waiter1->id,
            'total_amount' => 5000,
            'currency' => 'AMD',
        ]);
        $this->table1->markOccupied($order, $this->waiter1);

        // Reassign to waiter 2
        $response = $this->actingAs($this->owner)
            ->postJson("/pos/api/orders/{$order->id}/assign-waiter", [
                'waiter_id' => $this->waiter2->id,
                'reason' => 'Shift handover',
            ]);

        $response->assertStatus(200);
        $this->assertEquals($this->waiter2->id, $order->fresh()->waiter_id);
        $this->assertEquals($this->waiter2->id, $this->table1->fresh()->current_waiter_id);
    }

    public function test_optimistic_concurrency_lock_prevents_stale_overwrites(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-LOCK-TEST',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table1->id,
            'table_number' => 'T-01',
            'total_amount' => 5000,
            'lock_version' => 3, // Current version is 3
            'currency' => 'AMD',
        ]);

        // Client terminal sends request with stale lock_version = 1
        $response = $this->actingAs($this->waiter1)
            ->postJson("/pos/api/orders/{$order->id}/transfer-table", [
                'destination_table_id' => $this->table2->id,
                'lock_version' => 1,
            ]);

        // Expect 409 Conflict
        $response->assertStatus(409);
        $response->assertJsonValidationErrors(['concurrency']);
    }

    public function test_qr_menu_order_coexists_seamlessly_with_pos_tables(): void
    {
        // Simulate a guest submitting a QR menu order on Table 1
        $createOrderAction = app(CreateOrderAction::class);
        $dto = new CreateOrderDTO(
            locationId: $this->location->id,
            type: 'dine_in',
            tableNumber: 'T-01',
            restaurantTableId: $this->table1->id,
            guestCount: 2,
            customerName: 'Guest Armen',
            customerPhone: '+37499123456',
            source: 'qr_menu',
            items: [
                new OrderItemDTO(
                    productId: $this->productA->id,
                    quantity: 1,
                ),
            ],
            notes: 'Ordered via table QR code',
        );

        $result = $createOrderAction->execute($this->vendor, $dto);
        $qrOrder = $result['order'];

        $this->assertEquals('qr_menu', $qrOrder->source);
        $this->assertEquals($this->table1->id, $qrOrder->restaurant_table_id);

        // Table 1 must be marked occupied
        $freshTable = $this->table1->fresh();
        $this->assertEquals('occupied', $freshTable->status);
        $this->assertEquals($qrOrder->id, $freshTable->current_order_id);

        // POS retrieves tables and sees QR menu order
        $posResponse = $this->actingAs($this->waiter1)->getJson('/pos/api/tables');
        $posResponse->assertStatus(200);

        $tablePayload = collect($posResponse->json('data.tables'))->firstWhere('id', $this->table1->id);
        $this->assertNotNull($tablePayload);
        $this->assertEquals('occupied', $tablePayload['status']);
        $this->assertNotNull($tablePayload['order']);
        $this->assertEquals('qr_menu', $tablePayload['order']['source']);
        $this->assertEquals($qrOrder->order_number, $tablePayload['order']['order_number']);
    }

    public function test_strict_tenant_isolation_on_table_and_order_operations(): void
    {
        // Order belonging to Tavush Lodge
        auth()->logout();
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-ISOLATION-01',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table1->id,
            'table_number' => 'T-01',
            'total_amount' => 5000,
            'currency' => 'AMD',
        ]);

        // Attempting to transfer to a table belonging to another vendor
        $response = $this->actingAs($this->waiter1)
            ->postJson("/pos/api/orders/{$order->id}/transfer-table", [
                'destination_table_id' => $this->otherTable->id,
            ]);

        // Should be rejected (403 or 404)
        $this->assertContains($response->status(), [403, 404]);

        // Other vendor user attempting to access Tavush lodge tables
        $tablesRes = $this->actingAs($this->otherVendorUser)
            ->getJson('/pos/api/tables');

        $tablesRes->assertStatus(200);
        $otherTables = $tablesRes->json('data.tables');
        $this->assertCount(1, $otherTables);
        $this->assertEquals('O-01', $otherTables[0]['table_number']);
    }
}
