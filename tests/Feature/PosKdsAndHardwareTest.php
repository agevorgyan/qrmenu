<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceiptPrinterService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosKdsAndHardwareTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $foodCategory;

    protected Category $barCategory;

    protected Product $burger;

    protected Product $cocktail;

    protected RestaurantTable $table;

    protected User $owner;

    protected User $chef;

    protected User $waiter;

    protected PosDevice $device;

    protected Vendor $otherVendor;

    protected User $otherChef;

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
            'name' => 'Cascade Grill',
            'slug' => 'cascade-grill',
            'email' => 'pos@cascadegrill.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown',
            'slug' => 'downtown',
            'is_active' => true,
        ]);

        $this->foodCategory = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Burgers & Grill',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->barCategory = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bar Drinks',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->burger = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Classic Cheeseburger',
            'price' => 3200,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $this->cocktail = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->barCategory->id,
            'name' => 'Mojito',
            'price' => 2500,
            'is_available' => true,
            'sort_order' => 2,
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-05',
            'name' => 'Table 5',
            'capacity' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->owner = User::create([
            'name' => 'Arthur Owner',
            'email' => 'owner@cascadegrill.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);

        $this->chef = User::create([
            'name' => 'Gevorg Head Chef',
            'email' => 'chef@cascadegrill.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'kitchen',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'pin_code' => Hash::make('4321'),
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Ani Waiter',
            'email' => 'ani@cascadegrill.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'waiter',
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $this->device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Kitchen Screen Terminal',
            'device_type' => 'desktop',
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

        $this->otherChef = User::create([
            'name' => 'Other Chef',
            'email' => 'otherchef@othercafe.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'kitchen',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);
    }

    protected function createTestOrder(string $status = 'confirmed'): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'restaurant_table_id' => $this->table->id,
            'waiter_id' => $this->waiter->id,
            'order_number' => 'ORD-'.rand(1000, 9999),
            'order_type' => 'dine_in',
            'status' => $status,
            'subtotal' => 5700,
            'total_amount' => 5700,
            'currency' => 'AMD',
            'notes' => 'Gluten allergy on table',
        ]);

        // Kitchen item
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->burger->id,
            'product_name' => $this->burger->name,
            'kitchen_station' => 'kitchen',
            'quantity' => 1,
            'unit_price' => 3200,
            'subtotal' => 3200,
            'status' => 'pending',
            'modifiers' => [['name' => 'No Onion']],
            'notes' => 'Well done',
        ]);

        // Bar item
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->cocktail->id,
            'product_name' => $this->cocktail->name,
            'kitchen_station' => 'bar',
            'quantity' => 1,
            'unit_price' => 2500,
            'subtotal' => 2500,
            'status' => 'pending',
            'modifiers' => [['name' => 'Less Ice']],
        ]);

        return $order->fresh(['items', 'restaurantTable', 'waiter']);
    }

    public function test_kds_screen_is_accessible_to_authorized_pos_users(): void
    {
        $response = $this->actingAs($this->chef)->get('/pos/kds');
        $response->assertStatus(200);
        $response->assertSee('Kitchen Display System');
        $response->assertSee('Cascade Grill');
    }

    public function test_kds_stations_seeding_and_retrieval(): void
    {
        $response = $this->actingAs($this->chef)->getJson('/pos/api/kds/stations');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $stations = $response->json('stations');
        $this->assertNotEmpty($stations);
        $slugs = collect($stations)->pluck('slug')->all();
        $this->assertContains('kitchen', $slugs);
        $this->assertContains('bar', $slugs);
        $this->assertContains('coffee', $slugs);
        $this->assertContains('dessert', $slugs);
    }

    public function test_kds_tickets_retrieval_and_station_filtering(): void
    {
        $order = $this->createTestOrder('confirmed');

        // All stations
        $allRes = $this->actingAs($this->chef)->getJson('/pos/api/kds/tickets?station=all');
        $allRes->assertStatus(200);
        $allRes->assertJson(['success' => true]);
        $tickets = $allRes->json('tickets');
        $this->assertCount(1, $tickets);
        $this->assertCount(2, $tickets[0]['items']);
        $this->assertEquals($order->order_number, $tickets[0]['order_number']);
        $this->assertEquals('Table 5', $tickets[0]['table_name']);

        // Kitchen station only
        $kitchenRes = $this->actingAs($this->chef)->getJson('/pos/api/kds/tickets?station=kitchen');
        $kitchenRes->assertStatus(200);
        $kitchenTickets = $kitchenRes->json('tickets');
        $this->assertCount(1, $kitchenTickets);
        $this->assertCount(1, $kitchenTickets[0]['items']);
        $this->assertEquals('Classic Cheeseburger', $kitchenTickets[0]['items'][0]['product_name']);

        // Bar station only
        $barRes = $this->actingAs($this->chef)->getJson('/pos/api/kds/tickets?station=bar');
        $barRes->assertStatus(200);
        $barTickets = $barRes->json('tickets');
        $this->assertCount(1, $barTickets);
        $this->assertCount(1, $barTickets[0]['items']);
        $this->assertEquals('Mojito', $barTickets[0]['items'][0]['product_name']);

        // Dessert station (order has no dessert items -> excluded)
        $dessertRes = $this->actingAs($this->chef)->getJson('/pos/api/kds/tickets?station=dessert');
        $dessertRes->assertStatus(200);
        $this->assertCount(0, $dessertRes->json('tickets'));
    }

    public function test_item_preparation_status_transitions_and_parent_order_synchronization(): void
    {
        $order = $this->createTestOrder('confirmed');
        $items = $order->items;
        $burgerItem = $items->firstWhere('kitchen_station', 'kitchen');
        $barItem = $items->firstWhere('kitchen_station', 'bar');

        // 1. Mark burger item as 'preparing'
        $res1 = $this->actingAs($this->chef)->postJson("/pos/api/kds/items/{$burgerItem->id}/status", [
            'status' => 'preparing',
        ]);
        $res1->assertStatus(200);
        $res1->assertJson([
            'success' => true,
            'order_status' => 'preparing',
        ]);
        $this->assertNotNull($burgerItem->fresh()->prepared_at);
        $this->assertEquals('preparing', $order->fresh()->status);

        // 2. Mark burger item as 'ready' (bar item still pending -> order becomes partially_ready)
        $res2 = $this->actingAs($this->chef)->postJson("/pos/api/kds/items/{$burgerItem->id}/status", [
            'status' => 'ready',
        ]);
        $res2->assertStatus(200);
        $res2->assertJson([
            'success' => true,
            'order_status' => 'partially_ready',
        ]);
        $this->assertEquals('partially_ready', $order->fresh()->status);
        $this->assertNotNull($burgerItem->fresh()->ready_at);

        // 3. Mark bar item as 'ready' (all items ready -> order becomes ready)
        $res3 = $this->actingAs($this->chef)->postJson("/pos/api/kds/items/{$barItem->id}/status", [
            'status' => 'ready',
        ]);
        $res3->assertStatus(200);
        $res3->assertJson([
            'success' => true,
            'order_status' => 'ready',
        ]);
        $this->assertEquals('ready', $order->fresh()->status);
        $this->assertEquals('ready', $this->table->fresh()->status);

        // 4. Mark all items as 'served' (all items served -> order becomes served)
        $this->actingAs($this->chef)->postJson("/pos/api/kds/items/{$burgerItem->id}/status", ['status' => 'served']);
        $res4 = $this->actingAs($this->chef)->postJson("/pos/api/kds/items/{$barItem->id}/status", ['status' => 'served']);
        $res4->assertStatus(200);
        $res4->assertJson([
            'success' => true,
            'order_status' => 'served',
        ]);
        $this->assertEquals('served', $order->fresh()->status);
    }

    public function test_kds_bump_order_bulk_updates_items_and_order(): void
    {
        $order = $this->createTestOrder('confirmed');

        // Bump all items to ready
        $response = $this->actingAs($this->chef)->postJson("/pos/api/kds/orders/{$order->id}/bump", [
            'status' => 'ready',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order_status' => 'ready',
        ]);

        $order->refresh();
        $this->assertEquals('ready', $order->status);
        foreach ($order->items as $item) {
            $this->assertEquals('ready', $item->status);
            $this->assertNotNull($item->ready_at);
        }
    }

    public function test_escpos_and_text_receipt_generation_for_customer(): void
    {
        $order = $this->createTestOrder('confirmed');
        $printer = app(ReceiptPrinterService::class);

        // 80mm ESC/POS
        $escpos80 = $printer->buildCustomerReceiptEscpos($order, null, $this->vendor, '80mm');
        $this->assertNotEmpty($escpos80);
        $this->assertStringContainsString('Cascade Grill', $escpos80);
        $this->assertStringContainsString('ORDER: #'.$order->order_number, $escpos80);
        $this->assertStringContainsString('TOTAL:', $escpos80);
        // Cut command byte
        $this->assertStringContainsString(ReceiptPrinterService::CMD_CUT_PARTIAL, $escpos80);

        // 58mm ESC/POS
        $escpos58 = $printer->buildCustomerReceiptEscpos($order, null, $this->vendor, '58mm');
        $this->assertNotEmpty($escpos58);
        $this->assertStringContainsString('Cascade Grill', $escpos58);

        // Plain Text Receipt
        $text = $printer->buildCustomerReceiptText($order, null, $this->vendor, '80mm');
        $this->assertStringContainsString('Cascade Grill', $text);
        $this->assertStringContainsString('Classic Cheeseburger', $text);
        $this->assertStringContainsString('Mojito', $text);
        $this->assertStringContainsString('TOTAL:', $text);

        // HTML Receipt Preview
        $html = $printer->buildHtmlReceipt($order, null, $this->vendor);
        $this->assertStringContainsString('Receipt #'.$order->order_number, $html);
        $this->assertStringContainsString('Classic Cheeseburger', $html);
    }

    public function test_escpos_and_text_kitchen_ticket_generation_per_station(): void
    {
        $order = $this->createTestOrder('confirmed');
        $printer = app(ReceiptPrinterService::class);

        // Kitchen ticket ESC/POS
        $escpos = $printer->buildKitchenTicketEscpos($order, 'kitchen', [], '80mm');
        $this->assertStringContainsString('*** KITCHEN TICKET ***', $escpos);
        $this->assertStringContainsString('Classic Cheeseburger', $escpos);
        $this->assertStringContainsString('NO ONION', $escpos);
        $this->assertStringContainsString('Gluten allergy on table', $escpos);

        // Plain text ticket
        $text = $printer->buildKitchenTicketText($order, 'bar', [], '80mm');
        $this->assertStringContainsString('*** BAR TICKET ***', $text);
        $this->assertStringContainsString('Mojito', $text);
        $this->assertStringContainsString('LESS ICE', $text);
    }

    public function test_shift_report_z_report_printing_generation(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->owner->id,
            'pos_device_id' => $this->device->id,
            'status' => 'closed',
            'opening_float' => 20000,
            'total_cash_sales' => 50000,
            'total_card_sales' => 30000,
            'total_cash_in' => 5000,
            'total_cash_out' => 2000,
            'expected_cash' => 73000,
            'counted_cash' => 73000,
            'discrepancy' => 0,
            'total_tips' => 4500,
            'total_refunds' => 0,
            'orders_count' => 12,
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
        ]);

        $printer = app(ReceiptPrinterService::class);
        $zReportEscpos = $printer->buildShiftReportEscpos($shift, 'Z', '80mm');
        $this->assertStringContainsString('END OF SHIFT REPORT (Z-REPORT)', $zReportEscpos);
        $this->assertStringContainsString('50,000.00 AMD', $zReportEscpos);
        $this->assertStringContainsString('Expected Cash:', $zReportEscpos);

        $zReportText = $printer->buildShiftReportText($shift, 'Z', '80mm');
        $this->assertStringContainsString('END OF SHIFT REPORT (Z-REPORT)', $zReportText);
        $this->assertStringContainsString('Expected Cash:', $zReportText);
    }

    public function test_print_job_spooling_via_api(): void
    {
        $order = $this->createTestOrder('confirmed');

        // Print kitchen ticket
        $printRes = $this->actingAs($this->chef)->postJson("/pos/api/kds/orders/{$order->id}/print", [
            'station' => 'kitchen',
        ]);
        $printRes->assertStatus(200);
        $printRes->assertJson(['success' => true]);
        $this->assertDatabaseHas('pos_print_jobs', [
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'ticket_type' => 'kitchen_ticket',
            'status' => 'pending',
        ]);

        // Print customer receipt
        $receiptRes = $this->actingAs($this->chef)->postJson("/pos/api/kds/orders/{$order->id}/receipt");
        $receiptRes->assertStatus(200);
        $receiptRes->assertJson(['success' => true]);
        $this->assertDatabaseHas('pos_print_jobs', [
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'ticket_type' => 'receipt',
            'status' => 'pending',
        ]);
    }

    public function test_strict_tenant_isolation_on_kds_and_hardware_operations(): void
    {
        $order = $this->createTestOrder('confirmed');
        $burgerItem = $order->items->first();

        // Other vendor chef cannot modify order item status
        $resItem = $this->actingAs($this->otherChef)->postJson("/pos/api/kds/items/{$burgerItem->id}/status", [
            'status' => 'ready',
        ]);
        $this->assertTrue(in_array($resItem->status(), [403, 404], true));

        // Other vendor chef cannot bump order
        $resBump = $this->actingAs($this->otherChef)->postJson("/pos/api/kds/orders/{$order->id}/bump", [
            'status' => 'ready',
        ]);
        $this->assertTrue(in_array($resBump->status(), [403, 404], true));

        // Other vendor chef cannot spool print job
        $resPrint = $this->actingAs($this->otherChef)->postJson("/pos/api/kds/orders/{$order->id}/print", [
            'station' => 'kitchen',
        ]);
        $this->assertTrue(in_array($resPrint->status(), [403, 404], true));

        // Other vendor chef does not see this vendor's tickets
        $resTickets = $this->actingAs($this->otherChef)->getJson('/pos/api/kds/tickets');
        $resTickets->assertStatus(200);
        $this->assertCount(0, $resTickets->json('tickets'));
    }
}
