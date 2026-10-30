<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosUiAndDesignTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $categoryKitchen;

    protected Category $categoryBar;

    protected Product $productSimple;

    protected Product $productWithVariations;

    protected RestaurantTable $table;

    protected User $owner;

    protected User $waiter;

    protected User $cashier;

    protected PosDevice $device;

    protected Vendor $otherVendor;

    protected User $otherVendorUser;

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
            'name' => 'Dilijan Mountain Tavern',
            'slug' => 'dilijan-mountain-tavern',
            'email' => 'pos@dilijantavern.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Dining Hall',
            'slug' => 'main-dining-hall',
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Table 4',
            'table_number' => 'Table 4',
            'capacity' => 6,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->categoryKitchen = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Hot Dishes',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->categoryBar = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bar Drinks',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->productSimple = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryKitchen->id,
            'name' => 'Pork Barbecue (Khorovats)',
            'price' => 4500,
            'is_available' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        $this->productWithVariations = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryKitchen->id,
            'name' => 'Armenian Gata Cake',
            'price' => 1500,
            'is_available' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        ProductVariation::create([
            'product_id' => $this->productWithVariations->id,
            'name' => 'Regular Slice',
            'price' => 1500,
        ]);

        ProductVariation::create([
            'product_id' => $this->productWithVariations->id,
            'name' => 'Whole Round (1kg)',
            'price' => 6000,
        ]);

        $this->owner = User::create([
            'name' => 'Manager Ruben',
            'email' => 'ruben@dilijantavern.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Waiter Anahit',
            'email' => 'anahit@dilijantavern.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'waiter',
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier Karen',
            'email' => 'karen@dilijantavern.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'cashier',
            'pin_code' => Hash::make('5678'),
            'is_active' => true,
        ]);

        $this->device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Main POS Terminal 1',
            'device_type' => 'tablet_ios',
            'pairing_code' => '998877',
            'is_active' => true,
            'is_paired' => true,
            'registered_at' => now(),
        ]);

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

        $this->otherVendorUser = User::create([
            'name' => 'Other Waiter',
            'email' => 'waiter@othercafe.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'waiter',
            'is_active' => true,
        ]);
    }

    public function test_pos_terminal_renders_for_authenticated_waiter(): void
    {
        $response = $this->actingAs($this->waiter)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->get('/pos');

        $response->assertStatus(200);
        $response->assertViewIs('pos.index');
        $response->assertSee('Dilijan Mountain Tavern');
        $response->assertSee('Waiter Anahit');
        $response->assertSee('bootstrapData:');
    }

    public function test_pos_terminal_switches_to_cashier_and_manager_modes(): void
    {
        $cashierResponse = $this->actingAs($this->cashier)
            ->get('/pos?mode=cashier');

        $cashierResponse->assertStatus(200);
        $cashierResponse->assertSee('Cashier Karen');

        $managerResponse = $this->actingAs($this->owner)
            ->get('/pos?mode=manager');

        $managerResponse->assertStatus(200);
        $managerResponse->assertSee('Manager Ruben');
    }

    public function test_menu_catalog_api_returns_categories_products_variations_and_favorites(): void
    {
        $response = $this->actingAs($this->waiter)
            ->getJson('/pos/api/menu');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $menu = $response->json('menu');
        $this->assertArrayHasKey('categories', $menu);
        $this->assertArrayHasKey('products', $menu);
        $this->assertArrayHasKey('favorites', $menu);

        $this->assertCount(2, $menu['categories']);
        $this->assertCount(2, $menu['products']);

        // Check product with variations
        $gata = collect($menu['products'])->firstWhere('id', $this->productWithVariations->id);
        $this->assertNotNull($gata);
        $this->assertCount(2, $gata['variations']);
        $this->assertEquals(1500, $gata['variations'][0]['price']);
        $this->assertEquals(6000, $gata['variations'][1]['price']);

        // Check favorites contains featured khorovats
        $favorites = $menu['favorites'];
        $this->assertNotEmpty($favorites);
        $this->assertEquals($this->productSimple->id, $favorites[0]['id']);
    }

    public function test_pos_order_creation_handles_variants_modifiers_and_guest_count(): void
    {
        $payload = [
            'location_id' => $this->location->id,
            'type' => 'dine_in',
            'table_number' => 'Table 4',
            'restaurant_table_id' => $this->table->id,
            'guest_count' => 4,
            'customer_name' => 'Mr. Hakobyan',
            'notes' => 'Table by the window',
            'discount_amount' => 500,
            'discount_type' => 'manual',
            'discount_reason' => 'VIP Customer',
            'items' => [
                [
                    'product_id' => $this->productSimple->id,
                    'quantity' => 2,
                    'notes' => 'Medium well',
                    'kitchen_station' => 'kitchen',
                    'modifiers' => [
                        ['id' => 'no_onion', 'name' => 'No Onion', 'price' => 0],
                        ['id' => 'extra_sauce', 'name' => 'Extra Sauce', 'price' => 200],
                    ],
                ],
                [
                    'product_id' => $this->productWithVariations->id,
                    'quantity' => 1,
                    'variation_id' => $this->productWithVariations->variations->last()->id,
                    'variation_name' => 'Whole Round (1kg)',
                    'kitchen_station' => 'kitchen',
                ],
            ],
        ];

        $response = $this->actingAs($this->waiter)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->postJson('/pos/api/orders', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        $orderData = $response->json('order');
        $this->assertEquals('pos', $orderData['source']);
        $this->assertEquals(4, $orderData['guest_count']);
        $this->assertEquals('Table 4', $orderData['table_number']);
        $this->assertEquals($this->waiter->id, $orderData['waiter_id']);
        $this->assertCount(2, $orderData['items']);

        // Verify items in database
        $this->assertDatabaseHas('orders', [
            'id' => $orderData['id'],
            'vendor_id' => $this->vendor->id,
            'guest_count' => 4,
            'source' => 'pos',
            'discount_amount' => 500,
        ]);

        $itemWithMods = OrderItem::where('order_id', $orderData['id'])
            ->where('product_id', $this->productSimple->id)
            ->first();
        $this->assertNotNull($itemWithMods);
        $this->assertNotNull($itemWithMods->modifiers);
        $this->assertEquals('Medium well', $itemWithMods->notes);
    }

    public function test_pos_cashier_payment_settlement_marks_order_completed(): void
    {
        // Create an unpaid order
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-TEST-101',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'table_number' => 'Table 4',
            'total_amount' => 5000,
            'subtotal' => 5000,
            'waiter_id' => $this->waiter->id,
            'currency' => 'AMD',
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson("/pos/api/orders/{$order->id}/status", [
                'status' => 'completed',
                'reason' => 'Paid in Cash',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'status' => 'completed',
            ],
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull($order->fresh()->closed_at);

        // Check operation log
        $this->assertDatabaseHas('pos_operation_logs', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->cashier->id,
            'action' => 'order_status_update',
        ]);
    }

    public function test_pos_order_hold_and_resume_workflow(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-HOLD-001',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'total_amount' => 2000,
            'currency' => 'AMD',
        ]);

        // Hold order
        $holdRes = $this->actingAs($this->waiter)
            ->postJson("/pos/api/orders/{$order->id}/hold", [
                'reason' => 'Guest stepped out',
            ]);

        $holdRes->assertStatus(200);
        $this->assertEquals('held', $order->fresh()->status);

        // Resume order
        $resumeRes = $this->actingAs($this->waiter)
            ->postJson("/pos/api/orders/{$order->id}/resume");

        $resumeRes->assertStatus(200);
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_pos_menu_and_orders_enforce_strict_tenant_isolation(): void
    {
        // Create order for Dilijan tavern first while no other user is logged in
        auth()->logout();
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-TEST-SECURE',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'total_amount' => 5000,
            'currency' => 'AMD',
        ]);

        // Other vendor user cannot see or access Dilijan tavern menu
        $menuRes = $this->actingAs($this->otherVendorUser)
            ->getJson('/pos/api/menu');

        $menuRes->assertStatus(200);
        $products = $menuRes->json('menu.products');
        // Other vendor has 0 products
        $this->assertEmpty($products);

        // Other vendor user cannot update Dilijan tavern order
        $forbiddenRes = $this->actingAs($this->otherVendorUser)
            ->postJson("/pos/api/orders/{$order->id}/status", [
                'status' => 'completed',
            ]);

        // Scoped binding or vendor check should block (403 or 404)
        $this->assertContains($forbiddenRes->status(), [403, 404]);
    }

    public function test_pos_active_orders_feed_filters_by_status_and_table(): void
    {
        Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-FILTER-1',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'table_number' => 'Table 1',
            'total_amount' => 1000,
            'currency' => 'AMD',
        ]);

        Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-FILTER-2',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'completed',
            'table_number' => 'Table 2',
            'total_amount' => 2000,
            'currency' => 'AMD',
        ]);

        // Filter by pending
        $res = $this->actingAs($this->cashier)
            ->getJson('/pos/api/orders?status=pending');

        $res->assertStatus(200);
        $items = $res->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals('POS-FILTER-1', $items[0]['order_number']);

        // Filter by table
        $resTable = $this->actingAs($this->cashier)
            ->getJson('/pos/api/orders?table_number=Table 2');

        $resTable->assertStatus(200);
        $itemsTable = $resTable->json('data');
        $this->assertCount(1, $itemsTable);
        $this->assertEquals('POS-FILTER-2', $itemsTable[0]['order_number']);
    }
}
