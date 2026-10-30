<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosKitchenStation;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosProductionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $manager;

    protected User $cashier;

    protected User $waiter;

    protected User $chef;

    protected PosDevice $terminal;

    protected RestaurantTable $table;

    protected Product $foodProduct;

    protected Product $drinkProduct;

    protected PosKitchenStation $kitchenStation;

    protected PosKitchenStation $barStation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $proPlan = SubscriptionPlan::where('name', 'Pro')->first() ?? SubscriptionPlan::first();

        $this->vendor = Vendor::create([
            'name' => 'Lavash Restaurant Yerevan',
            'slug' => 'lavash-restaurant-yerevan',
            'subscription_plan_id' => $proPlan->id,
            'status' => 'active',
            'currency' => 'AMD',
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Tumanyan Branch',
            'slug' => 'tumanyan-branch',
            'address' => '21 Tumanyan St, Yerevan',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Armen Manager',
            'email' => 'manager@lavash.am',
            'password' => Hash::make('secret123'),
            'role' => 'manager',
            'pin_code' => Hash::make('1111'),
            'pos_mode' => 'manager',
        ]);

        $this->cashier = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Sona Cashier',
            'email' => 'cashier@lavash.am',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
            'pin_code' => Hash::make('2222'),
            'pos_mode' => 'cashier',
        ]);

        $this->waiter = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Gor Waiter',
            'email' => 'waiter@lavash.am',
            'password' => Hash::make('secret123'),
            'role' => 'waiter',
            'pin_code' => Hash::make('3333'),
            'pos_mode' => 'waiter',
        ]);

        $this->chef = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Tigran Chef',
            'email' => 'chef@lavash.am',
            'password' => Hash::make('secret123'),
            'role' => 'chef',
            'pos_mode' => 'kds',
        ]);

        $this->terminal = PosDevice::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Main POS Register 01',
            'device_type' => 'terminal',
            'pairing_code' => 'YVN01',
            'is_active' => true,
            'is_paired' => true,
        ]);

        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => 'Table 12',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $catFood = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Hot Dishes',
            'slug' => 'hot-dishes',
            'is_active' => true,
        ]);

        $catDrinks = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $this->kitchenStation = PosKitchenStation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Main Kitchen',
            'slug' => 'kitchen',
            'is_active' => true,
        ]);

        $this->barStation = PosKitchenStation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Cocktail Bar',
            'slug' => 'bar',
            'is_active' => true,
        ]);

        $this->foodProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $catFood->id,
            'name' => 'Khorovats Pork Ribs',
            'slug' => 'khorovats-pork-ribs',
            'price' => 4500.00,
            'is_available' => true,
        ]);

        $this->drinkProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $catDrinks->id,
            'name' => 'Homemade Tan Mint',
            'slug' => 'homemade-tan-mint',
            'price' => 800.00,
            'is_available' => true,
        ]);
    }

    /**
     * Complete Full-Day Operational POS Lifecycle Test.
     */
    public function test_complete_end_to_end_operational_lifecycle(): void
    {
        // -------------------------------------------------------------
        // STEP 1: Morning Cash Shift Opening by Cashier
        // -------------------------------------------------------------
        $shiftOpenResponse = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson('/pos/api/shifts/open', [
                'opening_float' => 20000.00,
                'opening_notes' => 'Morning shift open by Sona with 20,000 AMD float.',
                'location_id' => $this->location->id,
            ]);

        $shiftOpenResponse->assertStatus(201);
        $shiftOpenResponse->assertJson(['success' => true]);
        $shiftId = $shiftOpenResponse->json('shift.shift_id');
        $this->assertNotNull($shiftId);

        $shift = PosShift::find($shiftId);
        $this->assertEquals(20000.00, (float) $shift->opening_float);
        $this->assertEquals('open', $shift->status);

        // -------------------------------------------------------------
        // STEP 2: Waiter Authenticates & Quick Switch
        // -------------------------------------------------------------
        $quickSwitchResponse = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson('/pos/api/auth/quick-switch', [
                'target_user_id' => $this->waiter->id,
                'pin' => '3333',
                'device_uuid' => $this->terminal->uuid,
            ]);

        $quickSwitchResponse->assertStatus(200);
        $quickSwitchResponse->assertJson(['success' => true]);
        $this->assertEquals($this->waiter->id, $quickSwitchResponse->json('user.id'));

        // -------------------------------------------------------------
        // STEP 3: Waiter Takes Order at Table 12 (Multi-Station Items)
        // -------------------------------------------------------------
        $orderPayload = [
            'location_id' => $this->location->id,
            'type' => 'dine_in',
            'table_number' => 'Table 12',
            'restaurant_table_id' => $this->table->id,
            'guest_count' => 4,
            'customer_name' => 'Levon Ter-Petrosyan',
            'notes' => 'VIP Guests, celebrate birthday.',
            'items' => [
                [
                    'product_id' => $this->foodProduct->id,
                    'quantity' => 2,
                    'notes' => 'Well-done crispy ribs',
                    'kitchen_station' => 'kitchen',
                    'modifiers' => [
                        ['id' => 'extra_sauce', 'name' => 'Extra BBQ Sauce', 'price' => 300],
                    ],
                ],
                [
                    'product_id' => $this->drinkProduct->id,
                    'quantity' => 4,
                    'notes' => 'Ice cold with fresh mint',
                    'kitchen_station' => 'bar',
                ],
            ],
        ];

        $orderCreateResponse = $this->actingAs($this->waiter)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson('/pos/api/orders', $orderPayload);

        $orderCreateResponse->assertStatus(201);
        $orderCreateResponse->assertJson(['success' => true]);

        $orderId = $orderCreateResponse->json('order.id');
        $this->assertNotNull($orderId);

        $order = Order::find($orderId);
        $this->assertEquals('pos', $order->source);
        $this->assertEquals(4, $order->guest_count);
        $this->assertEquals($this->table->id, $order->restaurant_table_id);
        $this->assertCount(2, $order->items);

        // Verify table marked as ordering/occupied
        $this->assertTrue(in_array($this->table->fresh()->status, ['occupied', 'ordering', 'preparing'], true));

        // -------------------------------------------------------------
        // STEP 4: Kitchen Staff Views Tickets and Prepares Order on KDS
        // -------------------------------------------------------------
        $kdsResponse = $this->actingAs($this->chef)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->getJson('/pos/api/kds/tickets?station=all');

        $kdsResponse->assertStatus(200);
        $tickets = $kdsResponse->json('tickets');
        $this->assertNotEmpty($tickets);

        // Bump the order to 'ready'
        $bumpResponse = $this->actingAs($this->chef)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson("/pos/api/kds/orders/{$order->id}/bump", [
                'status' => 'ready',
            ]);

        $bumpResponse->assertStatus(200);
        $this->assertEquals('ready', $order->fresh()->status);

        // -------------------------------------------------------------
        // STEP 5: Cashier Processes Mixed Payment (Cash + Card + Tip)
        // -------------------------------------------------------------
        // Total subtotal: (4500*2) + (800*4) = 9000 + 3200 = 12200 AMD + tip 1000 = 13200 AMD
        $orderTotal = (float) $order->fresh()->total_amount;

        $paymentPayload = [
            'payment_method' => 'mixed',
            'amount' => $orderTotal,
            'tip_amount' => 1000.00,
            'split_payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 7200.00,
                    'cash_tendered' => 8000.00, // 800 change due
                    'tip_amount' => 1000.00,
                ],
                [
                    'payment_method' => 'card',
                    'amount' => 5000.00,
                    'transaction_reference' => 'ARCA-AUTH-991283',
                ],
            ],
        ];

        $paymentResponse = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson("/pos/api/orders/{$order->id}/payment", $paymentPayload);

        $paymentResponse->assertStatus(200);
        $paymentResponse->assertJson(['success' => true]);
        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals(800.00, (float) $paymentResponse->json('change_due'));

        // -------------------------------------------------------------
        // STEP 6: Fiscal HDM Electronic Cash Register Receipt Generation
        // -------------------------------------------------------------
        $fiscalResponse = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson("/pos/api/integrations/orders/{$order->id}/fiscal-receipt");

        $fiscalResponse->assertStatus(200);
        $fiscalResponse->assertJson(['success' => true]);
        $this->assertNotEmpty($fiscalResponse->json('fiscal.crn'));
        $this->assertNotEmpty($fiscalResponse->json('fiscal.fiscal_qr_data'));
        $this->assertTrue($fiscalResponse->json('fiscal.success'));

        // -------------------------------------------------------------
        // STEP 7: Safe Drop & Cash Drawer Reconciliation
        // -------------------------------------------------------------
        $cashDropResponse = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson('/pos/api/shifts/cash-movement', [
                'shift_id' => $shift->id,
                'type' => 'safe_drop',
                'amount' => 10000.00,
                'reason' => 'Midday safe drop to manager vault',
            ]);

        $cashDropResponse->assertStatus(200);
        $cashDropResponse->assertJson(['success' => true]);

        // -------------------------------------------------------------
        // STEP 8: Cash Shift Closing & Z-Report
        // -------------------------------------------------------------
        // Expected cash: Opening float (20000) + Cash Sales (7200) - Safe drop (10000) = 17200 AMD
        $shift->refresh();
        $expectedCash = (float) $shift->expected_cash;

        $closeResponse = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Device-UUID', $this->terminal->uuid)
            ->postJson("/pos/api/shifts/{$shift->id}/close", [
                'counted_cash' => $expectedCash,
                'closing_notes' => 'Perfect cash reconciliation at shift close.',
            ]);

        $closeResponse->assertStatus(200);
        $closeResponse->assertJson(['success' => true]);
        $this->assertEquals('closed', $shift->fresh()->status);
        $this->assertEquals(0.00, (float) $shift->fresh()->discrepancy);

        // -------------------------------------------------------------
        // STEP 9: Manager Operational Reports Verification
        // -------------------------------------------------------------
        $salesReportResponse = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/sales?location_id={$this->location->id}");

        $salesReportResponse->assertStatus(200);
        $salesReportResponse->assertJson(['success' => true]);
        $this->assertGreaterThan(0, $salesReportResponse->json('report.summary.gross_sales'));

        $tipsReportResponse = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/tips?location_id={$this->location->id}");

        $tipsReportResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(1000, $tipsReportResponse->json('report.summary.total_tips'));
    }
}
