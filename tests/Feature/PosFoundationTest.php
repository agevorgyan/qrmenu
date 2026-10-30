<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosDeviceSession;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected RestaurantTable $table;

    protected User $owner;

    protected User $waiter;

    protected User $cashier;

    protected User $otherVendorUser;

    protected Vendor $otherVendor;

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
            'name' => 'Armenian Heritage Bistro',
            'slug' => 'armenian-heritage-bistro',
            'email' => 'pos@heritagebistro.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'subscription_expires_at' => now()->addDays(30),
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Dining Hall',
            'slug' => 'main-dining-hall',
            'table_count' => 15,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Signature Dishes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Khorovats Pork Ribs',
            'price' => 4500,
            'is_available' => true,
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-10',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Karen Owner',
            'email' => 'karen@heritagebistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->waiter = User::create([
            'name' => 'Armen Waiter',
            'email' => 'armen@heritagebistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('5678'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->cashier = User::create([
            'name' => 'Anahit Cashier',
            'email' => 'anahit@heritagebistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('9999'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Second tenant for cross-vendor isolation tests
        $this->otherVendor = Vendor::create([
            'name' => 'Other Cafe',
            'slug' => 'other-cafe',
            'email' => 'info@othercafe.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'is_active' => true,
        ]);

        $this->otherVendorUser = User::create([
            'name' => 'Other User',
            'email' => 'other@othercafe.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->otherVendor->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_pos(): void
    {
        $response = $this->get('/pos');
        $response->assertRedirect(route('login'));

        $apiResponse = $this->getJson('/pos/api/bootstrap');
        $apiResponse->assertStatus(401);
    }

    public function test_user_without_pos_permission_is_rejected(): void
    {
        // Custom user without POS access permission
        $restrictedUser = User::create([
            'name' => 'Restricted Staff',
            'email' => 'restricted@heritagebistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff',
            'vendor_id' => $this->vendor->id,
            'custom_permissions' => ['denied' => [Permission::POS_ACCESS]],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($restrictedUser)->getJson('/pos/api/bootstrap');
        $response->assertStatus(403);
        $response->assertJson([
            'code' => 'PERMISSION_DENIED',
        ]);
    }

    public function test_authorized_owner_can_access_pos_screen_and_bootstrap(): void
    {
        $response = $this->actingAs($this->owner)->get('/pos');
        $response->assertStatus(200);
        $response->assertSee('Armenian Heritage Bistro');
        $response->assertSee('Waiter Mode');
        $response->assertSee('Cashier Mode');
        $response->assertSee('Manager Mode');

        $bootstrapResponse = $this->actingAs($this->owner)->getJson('/pos/api/bootstrap');
        $bootstrapResponse->assertStatus(200);
        $bootstrapResponse->assertJsonStructure([
            'success',
            'data' => [
                'vendor' => ['id', 'name', 'slug', 'currency'],
                'locations',
                'active_location_id',
                'user' => ['id', 'name', 'role', 'permissions'],
                'settings',
                'tables_summary' => ['total', 'active'],
            ],
        ]);
    }

    public function test_device_registration_pairing_heartbeat_and_revocation(): void
    {
        // 1. Register a new device
        $registerResponse = $this->actingAs($this->owner)->postJson('/pos/api/devices/register', [
            'name' => 'Counter Terminal #1',
            'device_type' => 'terminal',
            'location_id' => $this->location->id,
        ]);

        $registerResponse->assertStatus(201);
        $registerResponse->assertJson(['success' => true]);
        $deviceData = $registerResponse->json('device');
        $this->assertNotEmpty($deviceData['pairing_code']);
        $this->assertEquals('Counter Terminal #1', $deviceData['name']);

        $deviceId = $deviceData['id'];
        $pairingCode = $deviceData['pairing_code'];
        $deviceUuid = $deviceData['uuid'];

        // 2. Pair device using pairing code
        $pairResponse = $this->actingAs($this->owner)->postJson('/pos/api/devices/pair', [
            'pairing_code' => $pairingCode,
            'hardware_identifier' => 'MAC-AA-BB-CC-11-22',
        ]);

        $pairResponse->assertStatus(200);
        $this->assertTrue($pairResponse->json('device.is_paired'));

        // 3. Heartbeat
        $heartbeatResponse = $this->actingAs($this->owner)->postJson('/pos/api/devices/heartbeat', [
            'device_uuid' => $deviceUuid,
            'app_version' => '2.0.0',
            'synced' => true,
        ]);

        $heartbeatResponse->assertStatus(200);
        $this->assertTrue($heartbeatResponse->json('is_active'));
        $this->assertNotNull($heartbeatResponse->json('last_sync_at'));

        // 4. Revoke device
        $device = PosDevice::find($deviceId);
        $deleteResponse = $this->actingAs($this->owner)->deleteJson("/pos/api/devices/{$device->id}");
        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('pos_devices', ['id' => $deviceId]);
    }

    public function test_device_sessions_lock_and_unlock_with_pin(): void
    {
        $device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Bar Tablet',
            'device_type' => 'tablet',
            'is_active' => true,
            'is_paired' => true,
        ]);

        // Start session
        $sessionResponse = $this->actingAs($this->waiter)->postJson("/pos/api/devices/{$device->id}/session", [
            'mode' => 'waiter',
        ]);

        $sessionResponse->assertStatus(201);
        $sessionId = $sessionResponse->json('session.id');
        $this->assertNotNull($sessionId);

        // Lock session
        $lockResponse = $this->actingAs($this->waiter)->postJson("/pos/api/sessions/{$sessionId}/lock");
        $lockResponse->assertStatus(200);
        $session = PosDeviceSession::find($sessionId);
        $this->assertTrue($session->isLocked());

        // Unlock session with wrong PIN
        $wrongUnlockResponse = $this->actingAs($this->waiter)->postJson("/pos/api/sessions/{$sessionId}/unlock", [
            'pin' => '0000',
        ]);
        $wrongUnlockResponse->assertStatus(422);

        // Unlock session with correct PIN (waiter has '5678')
        $unlockResponse = $this->actingAs($this->waiter)->postJson("/pos/api/sessions/{$sessionId}/unlock", [
            'pin' => '5678',
        ]);
        $unlockResponse->assertStatus(200);
        $this->assertFalse(PosDeviceSession::find($sessionId)->isLocked());
    }

    public function test_pos_settings_retrieval_and_update(): void
    {
        $showResponse = $this->actingAs($this->owner)->getJson('/pos/api/settings');
        $showResponse->assertStatus(200);
        $showResponse->assertJson(['success' => true]);

        $updateResponse = $this->actingAs($this->owner)->postJson('/pos/api/settings', [
            'auto_lock_seconds' => 300,
            'max_discount_percent' => 25.00,
            'allow_manual_discounts' => true,
            'default_order_type' => 'dine_in',
        ]);

        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('pos_settings', [
            'vendor_id' => $this->vendor->id,
            'auto_lock_seconds' => 300,
            'max_discount_percent' => 25.00,
        ]);
    }

    public function test_pos_order_creation_integrates_with_existing_order_engine(): void
    {
        $device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Order Station',
            'device_type' => 'terminal',
            'is_active' => true,
        ]);

        $orderPayload = [
            'location_id' => $this->location->id,
            'type' => 'dine_in',
            'restaurant_table_id' => $this->table->id,
            'guest_count' => 3,
            'notes' => 'Crispy and medium spicy please',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'notes' => 'No onions',
                    'modifiers' => ['extra_sauce' => true],
                    'kitchen_station' => 'kitchen',
                ],
            ],
            'discount_amount' => 500.00,
            'discount_type' => 'fixed',
            'discount_reason' => 'Happy Hour VIP',
        ];

        $response = $this->actingAs($this->waiter)
            ->withHeaders(['X-POS-Device-UUID' => $device->uuid])
            ->postJson('/pos/api/orders', $orderPayload);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $orderId = $response->json('order.id');
        $this->assertNotNull($orderId);

        // Verify order saved with POS source, cashier/waiter attribution, table, discount
        $order = Order::with('items')->find($orderId);
        $this->assertEquals('pos', $order->source);
        $this->assertEquals('T-10', $order->table_number);
        $this->assertEquals(3, $order->guest_count);
        $this->assertEquals($this->waiter->id, $order->waiter_id);
        $this->assertEquals($device->id, $order->pos_device_id);
        $this->assertEquals(9000.00, (float) $order->subtotal); // 4500 * 2
        $this->assertEquals(500.00, (float) $order->discount_amount);
        $this->assertEquals(8500.00, (float) $order->total_amount); // 9000 - 500

        // Verify item metadata
        $item = $order->items->first();
        $this->assertEquals('No onions', $item->notes);
        $this->assertEquals('kitchen', $item->kitchen_station);
        $this->assertEquals(['extra_sauce' => true], $item->modifiers);

        // Test status transitions: preparing -> held -> pending
        $statusResponse = $this->actingAs($this->waiter)->postJson("/pos/api/orders/{$order->id}/status", [
            'status' => 'preparing',
        ]);
        $statusResponse->assertStatus(200);
        $this->assertEquals('preparing', $order->fresh()->status);

        $holdResponse = $this->actingAs($this->waiter)->postJson("/pos/api/orders/{$order->id}/hold", [
            'reason' => 'Guest stepped outside',
        ]);
        $holdResponse->assertStatus(200);
        $this->assertEquals('held', $order->fresh()->status);

        $resumeResponse = $this->actingAs($this->waiter)->postJson("/pos/api/orders/{$order->id}/resume");
        $resumeResponse->assertStatus(200);
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_qr_menu_storefront_order_submission_compatibility(): void
    {
        // Ensure standard QR menu order via CreateOrderAction still functions flawlessly
        $action = app(CreateOrderAction::class);

        $dto = CreateOrderDTO::fromArray([
            'location_id' => $this->location->id,
            'type' => 'dine_in',
            'table_number' => 'Table 5',
            'customer_name' => 'Armine Customer',
            'customer_phone' => '+37491000000',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $result = $action->execute($this->vendor, $dto);
        $order = $result['order'];

        $this->assertNotNull($order->id);
        $this->assertEquals('qr_menu', $order->source);
        $this->assertEquals('Table 5', $order->table_number);
        $this->assertEquals(4500.00, (float) $order->total_amount);
        $this->assertEquals('pending', $order->status);
    }

    public function test_cross_tenant_pos_isolation(): void
    {
        // Other vendor user cannot view or modify first vendor's order
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-ISOLATE-001',
            'source' => 'pos',
            'table_number' => 'T-1',
            'total_amount' => 5000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->otherVendorUser)->getJson("/pos/api/orders/{$order->id}");
        $this->assertTrue(in_array($response->status(), [403, 404], true));

        $statusResponse = $this->actingAs($this->otherVendorUser)->postJson("/pos/api/orders/{$order->id}/status", [
            'status' => 'completed',
        ]);
        $this->assertTrue(in_array($statusResponse->status(), [403, 404], true));
    }
}
