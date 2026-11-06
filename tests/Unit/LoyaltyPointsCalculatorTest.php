<?php

namespace Tests\Unit;

use App\Domains\Loyalty\Models\LoyaltyEarningRule;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Domains\Loyalty\Services\PointsCalculator;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyPointsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected PointsCalculator $calculator;

    protected Vendor $vendor;

    protected LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PointsCalculator;

        $this->vendor = Vendor::create([
            'name' => 'Pizza Bistro',
            'slug' => 'pizza-bistro',
            'email' => 'loyalty@bistro.com',
            'password' => bcrypt('password'),
        ]);

        $this->program = LoyaltyProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bistro Club',
            'currency' => 'USD',
            'default_points_ratio' => 1.0, // 1 point per $1
            'default_cashback_percent' => 5.0,
            'points_enabled' => true,
            'cashback_enabled' => true,
        ]);
    }

    public function test_calculates_points_with_default_ratio(): void
    {
        $order = new Order([
            'vendor_id' => $this->vendor->id,
            'subtotal' => 100.0,
            'discount_amount' => 10.0,
            'status' => 'completed',
        ]);

        $result = $this->calculator->calculateForOrder($order, $this->program);

        // Net spend is 100 - 10 = 90. At 1.0 ratio, points should be 90.
        $this->assertEquals(90, $result->pointsEarned);
        $this->assertEquals(1.0, $result->multiplier);
    }

    public function test_calculates_points_with_tier_multiplier(): void
    {
        LoyaltyTier::create([
            'vendor_id' => $this->vendor->id,
            'program_id' => $this->program->id,
            'name' => 'Gold',
            'level' => 3,
            'min_points' => 1000,
            'min_spend' => 500,
            'points_multiplier' => 2.0, // 2x points
            'cashback_percent' => 3.0,
            'order_discount_percent' => 5.0,
            'color_hex' => '#f59e0b',
        ]);

        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Alice Gold',
            'phone' => '+1234567890',
            'loyalty_tier' => 'Gold',
        ]);

        $order = new Order([
            'vendor_id' => $this->vendor->id,
            'subtotal' => 50.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $result = $this->calculator->calculateForOrder($order, $this->program, $customer);

        // Base points = 50. Gold multiplier = 2.0x -> 100 points.
        $this->assertEquals(100, $result->pointsEarned);
        $this->assertEquals(2.0, $result->multiplier);
        // Plus 3% tier cashback on $50 = $1.50
        $this->assertEquals(1.50, $result->cashbackEarned);
    }

    public function test_applies_earning_rules_with_priority_and_caps(): void
    {
        LoyaltyEarningRule::create([
            'vendor_id' => $this->vendor->id,
            'program_id' => $this->program->id,
            'name' => 'Weekend Big Spender Bonus',
            'type' => 'fixed',
            'reward_type' => 'points',
            'rate_value' => 200, // 200 bonus points
            'min_order_spend' => 80.0,
            'max_points_per_order' => 200,
            'priority' => 10,
            'is_active' => true,
        ]);

        $orderLow = new Order([
            'vendor_id' => $this->vendor->id,
            'subtotal' => 50.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $resultLow = $this->calculator->calculateForOrder($orderLow, $this->program);
        // Did not meet $80 min spend -> only default 50 points
        $this->assertEquals(50, $resultLow->pointsEarned);

        $orderHigh = new Order([
            'vendor_id' => $this->vendor->id,
            'subtotal' => 100.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $resultHigh = $this->calculator->calculateForOrder($orderHigh, $this->program);
        // Meets $80 -> 100 default points + 200 bonus rule = 300 points
        $this->assertEquals(300, $resultHigh->pointsEarned);
    }

    public function test_zero_spend_order_earns_zero_rewards(): void
    {
        $order = new Order([
            'vendor_id' => $this->vendor->id,
            'subtotal' => 0.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $result = $this->calculator->calculateForOrder($order, $this->program);
        $this->assertEquals(0, $result->pointsEarned);
        $this->assertEquals(0.0, $result->cashbackEarned);
    }
}
