<?php

namespace Tests\Feature;

use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Services\LoyaltyEngine;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyOrderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected LoyaltyEngine $engine;

    protected Vendor $vendor;

    protected Location $location;

    protected LoyaltyProgram $program;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(LoyaltyEngine::class);

        $this->vendor = Vendor::create([
            'name' => 'Order Integration Bistro',
            'slug' => 'order-bistro',
            'email' => 'orders@bistro.com',
            'password' => bcrypt('password'),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown-branch',
        ]);

        $this->program = LoyaltyProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bistro Loyalty',
            'currency' => 'USD',
            'default_points_ratio' => 1.0, // 1 pt per $1
            'points_enabled' => true,
            'cashback_enabled' => true,
            'settings' => [
                'default_cashback_percent' => 5.0, // 5% cashback
            ],
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Order Customer',
            'phone' => '+15553334444',
            'loyalty_points' => 0,
        ]);
    }

    public function test_completed_order_awards_points_and_cashback(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-1001',
            'subtotal' => 100.0,
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $result = $this->engine->processOrder($order);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(100, $result['results']['points_earned']);
        $this->assertEquals(5.0, $result['results']['cashback_earned']);

        $this->customer->refresh();
        $this->assertEquals(100, $this->customer->loyalty_points);
    }

    public function test_duplicate_order_event_is_idempotent(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-1002',
            'subtotal' => 80.0,
            'total_amount' => 80.0,
            'paid_amount' => 80.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        // First process
        $result1 = $this->engine->processOrder($order);
        $this->assertEquals('success', $result1['status']);
        $this->customer->refresh();
        $this->assertEquals(80, $this->customer->loyalty_points);

        // Immediate duplicate call (e.g. repeated webhook or status update event)
        $result2 = $this->engine->processOrder($order);
        $this->assertEquals('skipped', $result2['status']);

        // Balance must remain 80, not 160
        $this->customer->refresh();
        $this->assertEquals(80, $this->customer->loyalty_points);
    }

    public function test_order_cancellation_or_full_refund_reverses_points_and_cashback(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-1003',
            'subtotal' => 100.0,
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $this->engine->processOrder($order);
        $this->customer->refresh();
        $this->assertEquals(100, $this->customer->loyalty_points);

        // Full refund reversal
        $rev = $this->engine->reverseOrderLoyalty($order, 1.0, 'Order fully cancelled');
        $this->assertEquals('reversed', $rev['status']);
        $this->assertEquals(100, $rev['points_reversed']);
        $this->assertEquals(5.0, $rev['cashback_reversed']);

        $this->customer->refresh();
        $this->assertEquals(0, $this->customer->loyalty_points);
    }

    public function test_partial_refund_reverses_proportional_points(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-1004',
            'subtotal' => 100.0,
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $this->engine->processOrder($order);
        $this->customer->refresh();
        $this->assertEquals(100, $this->customer->loyalty_points);

        // 50% partial refund
        $rev = $this->engine->reverseOrderLoyalty($order, 0.5, '50% partial refund');
        $this->assertEquals('reversed', $rev['status']);
        $this->assertEquals(50, $rev['points_reversed']);
        $this->assertEquals(2.5, $rev['cashback_reversed']);

        $this->customer->refresh();
        $this->assertEquals(50, $this->customer->loyalty_points);
    }
}
