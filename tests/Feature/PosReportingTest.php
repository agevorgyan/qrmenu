<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\Tip;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosReportingTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $manager;

    protected User $cashier;

    protected User $waiter;

    protected PosDevice $device;

    protected Category $categoryMains;

    protected Category $categoryDrinks;

    protected Product $saladProduct;

    protected Product $steakProduct;

    protected Product $wineProduct;

    protected RestaurantTable $table1;

    protected Vendor $otherVendor;

    protected User $otherManager;

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
            'name' => 'Sevan Seafood',
            'slug' => 'sevan-seafood',
            'email' => 'pos@sevanseafood.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Shoreline Pavilion',
            'slug' => 'shoreline-pavilion',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Gagik Manager',
            'email' => 'gagik@sevanseafood.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'manager',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'pin_code' => Hash::make('1111'),
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Vardan Cashier',
            'email' => 'vardan@sevanseafood.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'cashier',
            'pin_code' => Hash::make('2222'),
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Gor Waiter',
            'email' => 'gor@sevanseafood.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'waiter',
            'pin_code' => Hash::make('3333'),
            'is_active' => true,
        ]);

        $this->device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Bar Counter Terminal',
            'device_type' => 'desktop',
            'pairing_code' => '444555',
            'is_active' => true,
            'is_paired' => true,
        ]);

        $this->categoryMains = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Seafood Mains',
            'is_active' => true,
        ]);

        $this->categoryDrinks = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Armenian Wines',
            'is_active' => true,
        ]);

        $this->saladProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryMains->id,
            'name' => 'Sevan Trout Fillet',
            'price' => 4500,
            'is_available' => true,
            'tracks_inventory' => true,
            'stock_quantity' => 15,
            'low_stock_threshold' => 5,
        ]);

        $this->steakProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryMains->id,
            'name' => 'Crawfish Kebab',
            'price' => 6000,
            'is_available' => true,
            'tracks_inventory' => true,
            'stock_quantity' => 3, // Low stock alert! (<= 5)
            'low_stock_threshold' => 5,
        ]);

        $this->wineProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryDrinks->id,
            'name' => 'Karas Reserve Red',
            'price' => 9500,
            'is_available' => true,
            'tracks_inventory' => false,
        ]);

        $this->table1 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Lakeside 1',
            'table_number' => 'L1',
            'capacity' => 6,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        // Other vendor for tenant isolation
        $this->otherVendor = Vendor::create([
            'name' => 'Other Tavern',
            'slug' => 'other-tavern',
            'email' => 'pos@othertavern.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->otherManager = User::create([
            'name' => 'Other Manager',
            'email' => 'manager@othertavern.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'manager',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);
    }

    public function test_sales_analytics_aggregates_gross_net_taxes_discounts_tenders_and_sources(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'opening_float' => 15000,
            'status' => 'open',
            'opened_at' => Carbon::now()->subHours(4),
        ]);

        // Order 1: Cash sale with discount and tip (POS source)
        $order1 = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'pos_shift_id' => $shift->id,
            'cashier_id' => $this->cashier->id,
            'waiter_id' => $this->waiter->id,
            'order_number' => 'POS-REP-1',
            'source' => 'pos',
            'type' => 'dine_in',
            'status' => 'completed',
            'guest_count' => 2,
            'subtotal' => 10500,
            'discount_amount' => 500,
            'discount_type' => 'loyalty',
            'total_amount' => 10000,
            'paid_amount' => 10000,
            'tip_amount' => 1000,
            'opened_at' => Carbon::now()->subHours(2),
            'closed_at' => Carbon::now()->subHour(),
        ]);

        PosPayment::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'order_id' => $order1->id,
            'pos_shift_id' => $shift->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 10000,
            'cash_tendered' => 10000,
            'tip_amount' => 1000,
            'status' => 'completed',
            'currency' => 'AMD',
        ]);

        // Order 2: Card sale (QR menu source)
        $order2 = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'pos_shift_id' => $shift->id,
            'cashier_id' => $this->cashier->id,
            'waiter_id' => $this->waiter->id,
            'order_number' => 'QR-REP-2',
            'source' => 'qr',
            'type' => 'dine_in',
            'status' => 'completed',
            'guest_count' => 3,
            'subtotal' => 9500,
            'discount_amount' => 0,
            'total_amount' => 9500,
            'paid_amount' => 9500,
            'tip_amount' => 500,
            'opened_at' => Carbon::now()->subMinutes(50),
            'closed_at' => Carbon::now()->subMinutes(20),
        ]);

        PosPayment::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'order_id' => $order2->id,
            'pos_shift_id' => $shift->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'payment_method' => 'card',
            'amount' => 9500,
            'tip_amount' => 500,
            'status' => 'completed',
            'currency' => 'AMD',
        ]);

        $res = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/sales?location_id={$this->location->id}");

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);

        $summary = $res->json('report.summary');
        // Gross sales: 10500 + 9500 = 20000
        $this->assertEquals(20000, (float) $summary['gross_sales']);
        // Net sales: 10000 + 9500 = 19500
        $this->assertEquals(19500, (float) $summary['net_sales']);
        // Total discount: 500
        $this->assertEquals(500, (float) $summary['discount_total']);
        // Total tips: 1000 + 500 = 1500
        $this->assertEquals(1500, (float) $summary['tip_total']);
        // Orders count: 2
        $this->assertEquals(2, $summary['orders_count']);
        // Total guests: 5
        $this->assertEquals(5, $summary['total_guests']);
        // AOV: 19500 / 2 = 9750
        $this->assertEquals(9750, (float) $summary['average_order_value']);
        // Avg guest spend: 19500 / 5 = 3900
        $this->assertEquals(3900, (float) $summary['average_guest_spend']);

        // Tenders
        $tenders = collect($res->json('report.by_payment_method'))->keyBy('method');
        $this->assertEquals(10000, (float) $tenders['cash']['total']);
        $this->assertEquals(9500, (float) $tenders['card']['total']);

        // Sources
        $sources = collect($res->json('report.by_source'))->keyBy('source');
        $this->assertEquals(10000, (float) $sources['pos']['total']);
        $this->assertEquals(9500, (float) $sources['qr']['total']);
    }

    public function test_employee_performance_reports_track_waiters_and_cashiers(): void
    {
        $today = Carbon::today()->startOfDay();

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'cashier_id' => $this->cashier->id,
            'order_number' => 'POS-EMP-1',
            'status' => 'completed',
            'guest_count' => 4,
            'subtotal' => 12000,
            'total_amount' => 12000,
            'tip_amount' => 1200,
            'closed_at' => Carbon::now(),
        ]);

        $res = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/employees?location_id={$this->location->id}");

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);

        // Waiter check
        $waiters = collect($res->json('report.waiters'))->keyBy('user_id');
        $this->assertTrue($waiters->has($this->waiter->id));
        $this->assertEquals(1, $waiters[$this->waiter->id]['orders_count']);
        $this->assertEquals(12000, (float) $waiters[$this->waiter->id]['total_sales']);
        $this->assertEquals(1200, (float) $waiters[$this->waiter->id]['total_tips']);
        $this->assertEquals(4, $waiters[$this->waiter->id]['total_guests']);

        // Cashier check
        $cashiers = collect($res->json('report.cashiers'))->keyBy('user_id');
        $this->assertTrue($cashiers->has($this->cashier->id));
        $this->assertEquals(12000, (float) $cashiers[$this->cashier->id]['total_sales']);
    }

    public function test_product_and_category_reports_calculate_volume_revenue_and_percentages(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-PROD-1',
            'status' => 'completed',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'closed_at' => Carbon::now(),
        ]);

        // 2x Trout Fillet = 9000
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->saladProduct->id,
            'product_name' => $this->saladProduct->name,
            'quantity' => 2,
            'unit_price' => 4500,
            'subtotal' => 9000,
        ]);

        // 1x Karas Wine = 9500
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->wineProduct->id,
            'product_name' => $this->wineProduct->name,
            'quantity' => 1,
            'unit_price' => 9500,
            'subtotal' => 9500,
        ]);

        // 1. Product Report
        $resProd = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/products?location_id={$this->location->id}");

        $resProd->assertStatus(200);
        $products = collect($resProd->json('report.products'))->keyBy('product_id');

        $this->assertEquals(2, $products[$this->saladProduct->id]['quantity_sold']);
        $this->assertEquals(9000, (float) $products[$this->saladProduct->id]['revenue']);
        $this->assertEquals(9500, (float) $products[$this->wineProduct->id]['revenue']);

        // 2. Category Report
        $resCat = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/categories?location_id={$this->location->id}");

        $resCat->assertStatus(200);
        $categories = collect($resCat->json('report.categories'))->keyBy('category_id');

        $this->assertEquals(9000, (float) $categories[$this->categoryMains->id]['revenue']);
        $this->assertEquals(9500, (float) $categories[$this->categoryDrinks->id]['revenue']);
        // 9500 / 18500 = 51.4%
        $this->assertGreaterThan(50, (float) $categories[$this->categoryDrinks->id]['percentage']);
    }

    public function test_cash_and_shift_reconciliation_reports(): void
    {
        $shift = PosShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pos_device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'closed_by_user_id' => $this->manager->id,
            'opening_float' => 10000,
            'total_cash_sales' => 25000,
            'total_card_sales' => 18000,
            'total_cash_in' => 5000,
            'total_cash_out' => 2000,
            'expected_cash' => 38000, // 10000 + 25000 + 5000 - 2000
            'counted_cash' => 37800,  // -200 discrepancy
            'discrepancy' => -200,
            'status' => 'closed',
            'opened_at' => Carbon::now()->subHours(8),
            'closed_at' => Carbon::now()->subMinute(),
        ]);

        $res = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/cash?location_id={$this->location->id}");

        $res->assertStatus(200);
        $summary = $res->json('report.summary');

        $this->assertEquals(1, $summary['shifts_count']);
        $this->assertEquals(25000, (float) $summary['total_cash_sales']);
        $this->assertEquals(5000, (float) $summary['total_cash_in']);
        $this->assertEquals(2000, (float) $summary['total_cash_out']);
        $this->assertEquals(28000, (float) $summary['net_cash_flow']);
        $this->assertEquals(-200, (float) $summary['total_discrepancy']);

        $shiftReport = $res->json('report.shifts.0');
        $this->assertEquals(38000, (float) $shiftReport['expected_cash']);
        $this->assertEquals(37800, (float) $shiftReport['counted_cash']);
        $this->assertEquals(-200, (float) $shiftReport['discrepancy']);
    }

    public function test_tip_reports_breakdown_by_waiter_and_tender(): void
    {
        // 1. Cash tip
        Tip::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'amount' => 1500,
            'currency' => 'AMD',
            'is_cash' => true,
            'payment_method' => 'cash',
            'payment_status' => 'completed',
        ]);

        // 2. Card tip
        Tip::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'waiter_id' => $this->waiter->id,
            'amount' => 2500,
            'currency' => 'AMD',
            'is_cash' => false,
            'payment_method' => 'card',
            'payment_status' => 'completed',
        ]);

        $res = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/tips?location_id={$this->location->id}");

        $res->assertStatus(200);
        $summary = $res->json('report.summary');

        $this->assertEquals(4000, (float) $summary['total_tips']);
        $this->assertEquals(1500, (float) $summary['cash_tips']);
        $this->assertEquals(2500, (float) $summary['card_tips']);
        $this->assertEquals(2, $summary['transactions_count']);

        $waiterStats = $res->json('report.waiters.0');
        $this->assertEquals('Gor Waiter', $waiterStats['waiter_name']);
        $this->assertEquals(4000, (float) $waiterStats['total_amount']);
    }

    public function test_discounts_and_refunds_supervision_reports(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-DISC-1',
            'status' => 'completed',
            'discount_amount' => 1200,
            'discount_type' => 'manager_override',
            'subtotal' => 10000,
            'total_amount' => 8800,
        ]);

        $payment = PosPayment::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'user_id' => $this->manager->id,
            'payment_method' => 'card',
            'amount' => 8800,
            'refunded_amount' => 3000, // Partial refund
            'status' => 'completed',
            'currency' => 'AMD',
        ]);

        $res = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/discounts-refunds?location_id={$this->location->id}");

        $res->assertStatus(200);
        $this->assertEquals(1200, (float) $res->json('report.discounts.total_discount_amount'));
        $this->assertEquals(1, $res->json('report.discounts.discounted_orders_count'));
        $this->assertEquals('manager_override', $res->json('report.discounts.breakdown.0.type'));

        $this->assertEquals(3000, (float) $res->json('report.refunds.total_refund_amount'));
        $this->assertEquals(1, $res->json('report.refunds.refund_transactions_count'));
    }

    public function test_manager_operational_dashboard_snapshot(): void
    {
        PosOperationLog::log(
            vendor: $this->vendor,
            action: 'pos_shift_opened',
            user: $this->cashier,
            device: $this->device,
            details: ['opening_float' => 10000]
        );

        $res = $this->actingAs($this->manager)
            ->getJson("/pos/api/reports/dashboard?location_id={$this->location->id}");

        $res->assertStatus(200);
        $dashboard = $res->json('dashboard');

        // Low stock alerts include Crawfish Kebab (stock=3, threshold=5)
        $lowStockNames = collect($dashboard['low_stock_alerts'])->pluck('name')->toArray();
        $this->assertContains('Crawfish Kebab', $lowStockNames);

        // Occupied tables count = 1
        $this->assertEquals(1, $dashboard['tables']['occupied']);

        // Recent logs
        $actions = collect($dashboard['recent_logs'])->pluck('action')->toArray();
        $this->assertContains('pos_shift_opened', $actions);
    }

    public function test_strict_tenant_isolation_on_all_reports(): void
    {
        // Populate Vendor A report data
        $orderA = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'POS-ISOL-A',
            'status' => 'completed',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'closed_at' => Carbon::now(),
        ]);

        // Other manager from Vendor B queries reports
        $res = $this->actingAs($this->otherManager)
            ->getJson('/pos/api/reports/sales');

        $res->assertStatus(200);
        // Only returns Vendor B data (0 sales, 0 orders)
        $this->assertEquals(0, (float) $res->json('report.summary.gross_sales'));
        $this->assertEquals(0, $res->json('report.summary.orders_count'));
    }
}
