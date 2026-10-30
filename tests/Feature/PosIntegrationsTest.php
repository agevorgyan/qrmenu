<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\PosPayment;
use App\Models\PosSetting;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\Tip;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\ReceiptPrinterService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $cashier;

    protected User $waiter;

    protected PosDevice $device;

    protected Category $category;

    protected Product $saladProduct;

    protected Product $steakProduct;

    protected RestaurantTable $table1;

    protected Vendor $otherVendor;

    protected User $otherCashier;

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
            'name' => 'Armenia Bistro',
            'slug' => 'armenia-bistro',
            'email' => 'pos@armeniabistro.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Yerevan Center',
            'slug' => 'yerevan-center',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Armen Cashier',
            'email' => 'armen@armeniabistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'cashier',
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Suren Waiter',
            'email' => 'suren@armeniabistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'waiter',
            'pin_code' => Hash::make('5678'),
            'is_active' => true,
        ]);

        $this->device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Main POS Terminal',
            'device_type' => 'desktop',
            'pairing_code' => '555666',
            'is_active' => true,
            'is_paired' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'is_active' => true,
        ]);

        // Salad: tracks inventory with stock 10
        $this->saladProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Greek Salad',
            'price' => 2500,
            'is_available' => true,
            'tracks_inventory' => true,
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'allow_out_of_stock_sale' => false,
        ]);

        // Steak: does not track inventory
        $this->steakProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Ribeye Steak',
            'price' => 9000,
            'is_available' => true,
            'tracks_inventory' => false,
        ]);

        $this->table1 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Table 1',
            'table_number' => '1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        PosSetting::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'fiscal_enabled' => true,
            'fiscal_adapter' => 'mock',
            'fiscal_crn' => '00812948',
            'fiscal_tax_number' => '01524311',
        ]);

        // Setup other vendor for tenant isolation tests
        $this->otherVendor = Vendor::create([
            'name' => 'Other Diner',
            'slug' => 'other-diner',
            'email' => 'pos@otherdiner.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->otherCashier = User::create([
            'name' => 'Other Cashier',
            'email' => 'cashier@otherdiner.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'cashier',
            'is_active' => true,
        ]);
    }

    public function test_incoming_external_orders_retrieval_and_acceptance_lifecycle(): void
    {
        // 1. Create incoming QR order
        $qrOrder = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'QR-1001',
            'source' => 'qr',
            'type' => 'dine_in',
            'status' => 'pending',
            'table_number' => '1',
            'restaurant_table_id' => $this->table1->id,
            'customer_name' => 'Aram Guest',
            'subtotal' => 2500,
            'total_amount' => 2500,
        ]);

        OrderItem::create([
            'order_id' => $qrOrder->id,
            'product_id' => $this->saladProduct->id,
            'product_name' => $this->saladProduct->name,
            'quantity' => 2,
            'unit_price' => 2500,
            'subtotal' => 5000,
            'status' => 'pending',
        ]);

        // Retrieve incoming orders
        $res = $this->actingAs($this->cashier)
            ->getJson("/pos/api/integrations/incoming-orders?location_id={$this->location->id}");

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('count', 1);
        $this->assertEquals('QR-1001', $res->json('orders.0.order_number'));

        // Accept order
        $resAccept = $this->actingAs($this->cashier)
            ->postJson("/pos/api/integrations/orders/{$qrOrder->id}/accept", [
                'prep_time_minutes' => 15,
            ]);

        $resAccept->assertStatus(200);
        $resAccept->assertJsonPath('success', true);
        $this->assertEquals('confirmed', $qrOrder->fresh()->status);

        // Verify stock was deducted (from 10 down to 8)
        $this->saladProduct->refresh();
        $this->assertEquals(8, (float) $this->saladProduct->stock_quantity);

        // Verify inventory movement record exists
        $this->assertDatabaseHas('pos_inventory_movements', [
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->saladProduct->id,
            'order_id' => $qrOrder->id,
            'type' => 'sale',
            'quantity_change' => -2,
        ]);
    }

    public function test_reject_online_order_restocks_inventory(): void
    {
        $onlineOrder = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'WEB-2002',
            'source' => 'delivery',
            'type' => 'delivery',
            'status' => 'pending',
            'customer_name' => 'Lilit Online',
            'subtotal' => 2500,
            'total_amount' => 2500,
        ]);

        OrderItem::create([
            'order_id' => $onlineOrder->id,
            'product_id' => $this->saladProduct->id,
            'product_name' => $this->saladProduct->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'subtotal' => 2500,
            'status' => 'pending',
        ]);

        $resReject = $this->actingAs($this->cashier)
            ->postJson("/pos/api/integrations/orders/{$onlineOrder->id}/reject", [
                'reason' => 'Kitchen closing early',
            ]);

        $resReject->assertStatus(200);
        $this->assertEquals('cancelled', $onlineOrder->fresh()->status);
        $this->assertStringContainsString('Kitchen closing early', $onlineOrder->fresh()->notes);
    }

    public function test_reservations_retrieval_and_seating_converts_preorder_to_pos_order(): void
    {
        $today = Carbon::today()->toDateString();
        $reservation = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-998877',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_name' => 'Tigran VIP',
            'customer_phone' => '+37499112233',
            'customer_email' => 'tigran@vip.am',
            'locale' => 'hy',
            'guest_count' => 3,
            'table_number' => '1',
            'reservation_date' => $today,
            'reservation_time' => '19:30',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $preOrder = ReservationPreOrder::create([
            'reservation_id' => $reservation->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'confirmed',
            'subtotal' => 11500,
            'total_amount' => 11500,
            'items' => [
                [
                    'product_id' => $this->saladProduct->id,
                    'product_name' => $this->saladProduct->name,
                    'quantity' => 1,
                    'unit_price' => 2500,
                    'subtotal' => 2500,
                ],
                [
                    'product_id' => $this->steakProduct->id,
                    'product_name' => $this->steakProduct->name,
                    'quantity' => 1,
                    'unit_price' => 9000,
                    'subtotal' => 9000,
                ],
            ],
        ]);

        // 1. Fetch active reservations
        $resList = $this->actingAs($this->waiter)
            ->getJson("/pos/api/integrations/reservations?location_id={$this->location->id}&date={$today}");

        $resList->assertStatus(200);
        $resList->assertJsonPath('count', 1);
        $this->assertEquals('Tigran VIP', $resList->json('reservations.0.customer_name'));

        // 2. Seat reservation at Table 1
        $resSeat = $this->actingAs($this->waiter)
            ->postJson("/pos/api/integrations/reservations/{$reservation->id}/seat", [
                'table_id' => $this->table1->id,
            ]);

        $resSeat->assertStatus(200);
        $resSeat->assertJsonPath('success', true);

        // Reservation is marked seated
        $this->assertEquals(Reservation::STATUS_SEATED, $reservation->fresh()->status);

        // Table is occupied
        $this->assertEquals('occupied', $this->table1->fresh()->status);

        // Order was created from pre-order
        $createdOrder = Order::where('customer_phone', '+37499112233')->first();
        $this->assertNotNull($createdOrder);
        $this->assertEquals(11500, (float) $createdOrder->total_amount);
        $this->assertEquals(2, $createdOrder->items()->count());

        // Stock was deducted for Greek Salad (10 - 1 = 9)
        $this->saladProduct->refresh();
        $this->assertEquals(9, (float) $this->saladProduct->stock_quantity);
    }

    public function test_waiter_calls_retrieval_and_resolution(): void
    {
        $call = WaiterCall::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '1',
            'type' => 'call_waiter',
            'status' => 'pending',
            'notes' => 'Customer needs extra napkins',
        ]);

        // 1. Fetch active calls
        $res = $this->actingAs($this->waiter)
            ->getJson("/pos/api/integrations/waiter-calls?location_id={$this->location->id}");

        $res->assertStatus(200);
        $res->assertJsonPath('count', 1);

        // 2. Resolve call
        $resResolve = $this->actingAs($this->waiter)
            ->withHeaders(['X-POS-Device-UUID' => $this->device->uuid])
            ->postJson("/pos/api/integrations/waiter-calls/{$call->id}/resolve");

        $resResolve->assertStatus(200);
        $this->assertEquals('completed', $call->fresh()->status);
        $this->assertEquals($this->waiter->id, $call->fresh()->resolved_by_user_id);
        $this->assertEquals($this->device->id, $call->fresh()->pos_device_id);
        $this->assertNotNull($call->fresh()->resolved_at);
    }

    public function test_customer_search_loyalty_earning_and_redemption(): void
    {
        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Armine Vardanyan',
            'phone' => '+37491554433',
            'email' => 'armine@vardanyan.am',
            'loyalty_points' => 500,
            'loyalty_tier' => 'silver',
        ]);

        // 1. Search customer by phone
        $resSearch = $this->actingAs($this->cashier)
            ->getJson('/pos/api/integrations/customers/search?query=554433');

        $resSearch->assertStatus(200);
        $this->assertEquals('Armine Vardanyan', $resSearch->json('customers.0.name'));
        $this->assertEquals(500, $resSearch->json('customers.0.loyalty_points'));

        // 2. Create order for customer
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-LOY-1',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'customer_id' => $customer->id,
            'subtotal' => 5000,
            'total_amount' => 5000,
        ]);

        // 3. Redeem 200 points (200 AMD discount)
        $resRedeem = $this->actingAs($this->cashier)
            ->postJson("/pos/api/integrations/customers/{$customer->id}/loyalty/redeem", [
                'order_id' => $order->id,
                'points' => 200,
            ]);

        $resRedeem->assertStatus(200);
        $this->assertEquals(4800, (float) $order->fresh()->total_amount);
        $this->assertEquals(200, (float) $order->fresh()->discount_amount);
        $this->assertEquals(300, $customer->fresh()->loyalty_points);

        // Verify transaction logged
        $this->assertDatabaseHas('pos_customer_loyalty_transactions', [
            'vendor_id' => $this->vendor->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'redeemed',
            'points' => -200,
            'balance_after' => 300,
        ]);

        // 4. Settle payment and verify loyalty points are awarded for paid amount
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'opening_float' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $resPay = $this->actingAs($this->cashier)
            ->postJson("/pos/api/orders/{$order->id}/payment", [
                'payment_method' => 'cash',
                'amount' => 4800,
                'cash_tendered' => 5000,
                'tip_amount' => 200,
            ]);

        $resPay->assertStatus(200);

        // 4800 AMD paid on bronze tier (1.0x) = floor(4800 / 100 * 1.0) = 48 points earned
        // New balance: 300 + 48 = 348 points
        $this->assertEquals(348, $customer->fresh()->loyalty_points);

        // 5. Verify Tip was automatically recorded and mapped to waiter/shift
        $this->assertDatabaseHas('tips', [
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'amount' => 200,
            'payment_method' => 'cash',
        ]);
    }

    public function test_armenian_hdm_fiscal_adapter_and_thermal_receipt_formatting(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-FISC-88',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'completed',
            'subtotal' => 6000,
            'total_amount' => 6000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->saladProduct->id,
            'product_name' => $this->saladProduct->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'subtotal' => 2500,
        ]);

        $payment = PosPayment::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'user_id' => $this->cashier->id,
            'payment_method' => 'card',
            'amount' => 6000,
            'status' => 'completed',
            'currency' => 'AMD',
        ]);

        // Trigger fiscal receipt printing
        $resFiscal = $this->actingAs($this->cashier)
            ->postJson("/pos/api/integrations/orders/{$order->id}/fiscal-receipt");

        $resFiscal->assertStatus(200);
        $resFiscal->assertJsonPath('success', true);

        $payment->refresh();
        $this->assertEquals('00812948', $payment->fiscal_crn);
        $this->assertNotNull($payment->fiscal_receipt_number);
        $this->assertStringContainsString('ARM-HDM-00812948', $payment->fiscal_receipt_number);
        $this->assertStringContainsString('https://tax.src.am/crn/check', $payment->fiscal_qr_data);
        $this->assertEquals('printed', $payment->fiscal_status);

        // Verify Thermal Printer formatting includes Armenian HDM fiscal headers
        $printerService = app(ReceiptPrinterService::class);
        $receiptText = $printerService->buildCustomerReceiptText($order, $payment, $this->vendor, '80mm');

        $this->assertStringContainsString('ARMENIA TAX SERVICE (ՀԴՄ)', $receiptText);
        $this->assertStringContainsString('CRN (ԳՀ):', $receiptText);
        $this->assertStringContainsString('00812948', $receiptText);
        $this->assertStringContainsString('Receipt #:', $receiptText);
    }

    public function test_initiate_on_screen_qr_payment(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-QR-77',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'subtotal' => 4500,
            'total_amount' => 4500,
        ]);

        $res = $this->actingAs($this->cashier)
            ->postJson("/pos/api/integrations/orders/{$order->id}/qr-payment", [
                'gateway' => 'idram',
            ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $this->assertEquals('idram', $res->json('payment.gateway'));
        $this->assertStringContainsString('idram://pay?receiver=EDELAM', $res->json('payment.qr_data'));
    }

    public function test_strict_tenant_isolation_on_all_integrations(): void
    {
        // 1. Order belonging to Vendor A
        $orderA = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-A-1',
            'source' => 'online',
            'type' => 'takeaway',
            'status' => 'pending',
            'subtotal' => 3000,
            'total_amount' => 3000,
        ]);

        // Other cashier cannot accept Vendor A's order
        $resAccept = $this->actingAs($this->otherCashier)
            ->postJson("/pos/api/integrations/orders/{$orderA->id}/accept");
        $this->assertTrue(in_array($resAccept->status(), [403, 404, 422], true));

        // 2. Waiter call belonging to Vendor A
        auth()->logout();
        app(TenantContext::class)->setTenant($this->vendor);
        $callA = WaiterCall::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '1',
            'type' => 'call_waiter',
            'status' => 'pending',
        ]);

        $resCall = $this->actingAs($this->otherCashier)
            ->postJson("/pos/api/integrations/waiter-calls/{$callA->id}/resolve");
        $this->assertTrue(in_array($resCall->status(), [403, 404, 422], true));

        // 3. Customer belonging to Vendor A
        auth()->logout();
        app(TenantContext::class)->setTenant($this->vendor);
        $customerA = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Secret Customer',
            'phone' => '+37477112233',
            'loyalty_points' => 1000,
        ]);

        // Other cashier cannot redeem Vendor A customer's points
        $resRedeem = $this->actingAs($this->otherCashier)
            ->postJson("/pos/api/integrations/customers/{$customerA->id}/loyalty/redeem", [
                'order_id' => $orderA->id,
                'points' => 100,
            ]);
        $this->assertTrue(in_array($resRedeem->status(), [403, 404, 422], true));
    }
}
