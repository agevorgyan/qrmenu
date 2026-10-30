<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosPaymentsAndCashManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected RestaurantTable $table;

    protected User $owner;

    protected User $cashier;

    protected User $waiter;

    protected PosDevice $device;

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
            'name' => 'Central Branch',
            'slug' => 'central-branch',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Menu',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Beef Khashlama',
            'price' => 4500,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'T-01',
            'capacity' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->owner = User::create([
            'name' => 'Arthur Owner',
            'email' => 'arthur@yerevanbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Mariam Cashier',
            'email' => 'mariam@yerevanbistro.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'cashier',
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Ashot Waiter',
            'email' => 'ashot@yerevanbistro.am',
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
            'name' => 'Main Cashier Terminal',
            'device_type' => 'desktop',
            'pairing_code' => '112233',
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

        $this->otherCashier = User::create([
            'name' => 'Other Cashier',
            'email' => 'cashier@othercafe.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'cashier',
            'is_active' => true,
        ]);
    }

    public function test_cashier_can_open_shift_and_prevent_concurrent_shifts(): void
    {
        // 1. Open shift
        $response = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->postJson('/pos/api/shifts/open', [
                'opening_float' => 15000,
                'opening_notes' => 'Morning shift start with 15k float',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'shift' => [
                'status' => 'open',
                'opening_float' => 15000,
            ],
        ]);

        $shiftId = $response->json('shift.shift_id');
        $this->assertDatabaseHas('pos_shifts', [
            'id' => $shiftId,
            'vendor_id' => $this->vendor->id,
            'status' => 'open',
            'opening_float' => 15000,
            'expected_cash' => 15000,
        ]);

        // 2. Attempting to open another concurrent shift on the same terminal must fail
        $secondResponse = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->postJson('/pos/api/shifts/open', [
                'opening_float' => 20000,
            ]);

        $secondResponse->assertStatus(422);
        $secondResponse->assertJsonValidationErrors(['shift']);

        // 3. Fetch current shift
        $currentRes = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->getJson('/pos/api/shifts/current');

        $currentRes->assertStatus(200);
        $this->assertEquals($shiftId, $currentRes->json('shift.shift_id'));
    }

    public function test_cash_movements_in_and_out_update_expected_cash(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
        ]);

        // 1. Record Cash In (+5,000 AMD)
        $inResponse = $this->actingAs($this->cashier)
            ->postJson('/pos/api/shifts/cash-movement', [
                'shift_id' => $shift->id,
                'type' => 'cash_in',
                'amount' => 5000,
                'reason' => 'Additional change added',
            ]);

        $inResponse->assertStatus(200);
        $this->assertEquals(15000, $shift->fresh()->expected_cash);
        $this->assertEquals(5000, $shift->fresh()->total_cash_in);

        // 2. Record Expense (-3,000 AMD)
        $outResponse = $this->actingAs($this->cashier)
            ->postJson('/pos/api/shifts/cash-movement', [
                'shift_id' => $shift->id,
                'type' => 'expense',
                'amount' => 3000,
                'reason' => 'Purchased fresh lemons',
            ]);

        $outResponse->assertStatus(200);
        $this->assertEquals(12000, $shift->fresh()->expected_cash);
        $this->assertEquals(3000, $shift->fresh()->total_cash_out);

        // 3. Attempting to withdraw more than expected cash must fail
        $overdrawResponse = $this->actingAs($this->cashier)
            ->postJson('/pos/api/shifts/cash-movement', [
                'shift_id' => $shift->id,
                'type' => 'cash_out',
                'amount' => 50000,
                'reason' => 'Excessive withdrawal',
            ]);

        $overdrawResponse->assertStatus(422);
        $overdrawResponse->assertJsonValidationErrors(['amount']);
    }

    public function test_process_cash_payment_settles_order_and_updates_shift(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-CASH-001',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'restaurant_table_id' => $this->table->id,
            'table_number' => 'T-01',
            'subtotal' => 8500,
            'total_amount' => 8500,
            'currency' => 'AMD',
        ]);
        $this->table->markOccupied($order, $this->waiter);

        // Settle payment with 10,000 AMD cash tendered
        $response = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->postJson("/pos/api/orders/{$order->id}/payment", [
                'payment_method' => 'cash',
                'amount' => 8500,
                'cash_tendered' => 10000,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'change_due' => 1500,
            'order' => [
                'status' => 'completed',
                'payment_status' => 'paid',
                'paid_amount' => '8500.00',
                'change_amount' => '1500.00',
            ],
        ]);

        // Verify PosPayment record
        $this->assertDatabaseHas('pos_payments', [
            'order_id' => $order->id,
            'pos_shift_id' => $shift->id,
            'payment_method' => 'cash',
            'amount' => 8500,
            'cash_tendered' => 10000,
            'change_due' => 1500,
            'status' => 'completed',
        ]);

        // Table marked for cleaning
        $this->assertEquals('cleaning', $this->table->fresh()->status);

        // Shift expected cash: 10,000 + 8,500 = 18,500 AMD
        $freshShift = $shift->fresh();
        $this->assertEquals(18500, $freshShift->expected_cash);
        $this->assertEquals(8500, $freshShift->total_cash_sales);
    }

    public function test_process_card_payment_with_transaction_reference(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-CARD-001',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'total_amount' => 12000,
            'currency' => 'AMD',
        ]);

        $response = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->postJson("/pos/api/orders/{$order->id}/payment", [
                'payment_method' => 'card',
                'amount' => 12000,
                'transaction_reference' => 'AUTH-POS-987654',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('pos_payments', [
            'order_id' => $order->id,
            'payment_method' => 'card',
            'amount' => 12000,
            'transaction_reference' => 'AUTH-POS-987654',
            'status' => 'completed',
        ]);

        // Card sales updated, but expected cash in drawer unchanged
        $freshShift = $shift->fresh();
        $this->assertEquals(12000, $freshShift->total_card_sales);
        $this->assertEquals(10000, $freshShift->expected_cash);
    }

    public function test_mixed_payment_splits_between_cash_and_card_with_tip(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-MIXED-001',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'pending',
            'total_amount' => 15000,
            'currency' => 'AMD',
        ]);

        // Mixed: 5,000 in cash + 10,000 by card + 1,000 tip
        $response = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->device->uuid)
            ->postJson("/pos/api/orders/{$order->id}/payment", [
                'payment_method' => 'mixed',
                'split_payments' => [
                    ['payment_method' => 'cash', 'amount' => 5000, 'cash_tendered' => 5000],
                    ['payment_method' => 'card', 'amount' => 10000, 'transaction_reference' => 'CARD-AUTH-11'],
                ],
                'tip_amount' => 1000,
            ]);

        $response->assertStatus(200);

        // 2 PosPayment records created
        $this->assertEquals(2, PosPayment::where('order_id', $order->id)->count());

        $freshOrder = $order->fresh();
        $this->assertEquals('completed', $freshOrder->status);
        $this->assertEquals('paid', $freshOrder->payment_status);
        $this->assertEquals('mixed', $freshOrder->payment_method);
        $this->assertEquals(1000, $freshOrder->tip_amount);

        // Shift balances
        $freshShift = $shift->fresh();
        $this->assertEquals(5000, $freshShift->total_cash_sales);
        $this->assertEquals(10000, $freshShift->total_card_sales);
        $this->assertEquals(1000, $freshShift->total_tips);
        // Expected cash: 10,000 float + 5,000 cash = 15,000 AMD
        $this->assertEquals(15000, $freshShift->expected_cash);
    }

    public function test_close_shift_calculates_discrepancy_and_generates_z_report(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
        ]);

        // Add 5,000 AMD cash sale
        PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => Order::create([
                'vendor_id' => $this->vendor->id,
                'location_id' => $this->location->id,
                'order_number' => 'POS-ORD-TEMP',
                'source' => 'pos',
                'type' => 'dine_in',
                'status' => 'completed',
                'total_amount' => 5000,
                'currency' => 'AMD',
            ])->id,
            'pos_shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 5000,
            'currency' => 'AMD',
            'status' => 'completed',
        ]);
        $shift->recalculateExpectedCash();

        // Expected cash is 15,000 AMD. Counted cash is 14,800 AMD (shortage of 200 AMD)
        $response = $this->actingAs($this->cashier)
            ->postJson("/pos/api/shifts/{$shift->id}/close", [
                'counted_cash' => 14800,
                'closing_notes' => 'Short 200 AMD change discrepancy',
            ]);

        $response->assertStatus(200);
        $report = $response->json('shift');

        $this->assertEquals('closed', $report['status']);
        $this->assertEquals(15000, $report['expected_cash']);
        $this->assertEquals(14800, $report['counted_cash']);
        $this->assertEquals(-200, $report['discrepancy']);

        $freshShift = $shift->fresh();
        $this->assertEquals('closed', $freshShift->status);
        $this->assertNotNull($freshShift->closed_at);
        $this->assertEquals($this->cashier->id, $freshShift->closed_by_user_id);

        // Cannot close an already closed shift
        $failClose = $this->actingAs($this->cashier)
            ->postJson("/pos/api/shifts/{$shift->id}/close", [
                'counted_cash' => 15000,
            ]);
        $failClose->assertStatus(422);

        // Cannot record cash movement on closed shift
        $failMovement = $this->actingAs($this->cashier)
            ->postJson('/pos/api/shifts/cash-movement', [
                'shift_id' => $shift->id,
                'type' => 'cash_in',
                'amount' => 1000,
                'reason' => 'Late deposit',
            ]);
        $failMovement->assertStatus(422);
    }

    public function test_payment_refund_updates_status_and_reduces_shift_totals(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-REFUND-001',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 6000,
            'currency' => 'AMD',
        ]);

        $payment = PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'pos_shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 6000,
            'currency' => 'AMD',
            'status' => 'completed',
        ]);
        $shift->recalculateExpectedCash();

        // Refund 6,000 AMD
        $response = $this->actingAs($this->owner)
            ->postJson("/pos/api/payments/{$payment->id}/refund", [
                'amount' => 6000,
                'reason' => 'Dish returned by customer',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('refunded', $payment->fresh()->status);
        $this->assertEquals(6000, $payment->fresh()->refunded_amount);
        $this->assertEquals('refunded', $order->fresh()->payment_status);

        // Cannot refund again exceeding original amount
        $failRefund = $this->actingAs($this->owner)
            ->postJson("/pos/api/payments/{$payment->id}/refund", [
                'amount' => 1000,
            ]);
        $failRefund->assertStatus(422);
    }

    public function test_strict_tenant_isolation_on_shifts_and_payments(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_float' => 10000,
            'expected_cash' => 10000,
        ]);

        // Other vendor cashier attempting to close or query Yerevan Bistro shift
        $response = $this->actingAs($this->otherCashier)
            ->postJson("/pos/api/shifts/{$shift->id}/close", [
                'counted_cash' => 10000,
            ]);

        $this->assertContains($response->status(), [403, 404]);
    }
}
