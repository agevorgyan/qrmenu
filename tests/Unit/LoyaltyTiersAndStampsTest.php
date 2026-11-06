<?php

namespace Tests\Unit;

use App\Domains\Loyalty\DTOs\StampAwardDTO;
use App\Domains\Loyalty\Enums\StampStatus;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyStampProgram;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Domains\Loyalty\Models\LoyaltyTierHistory;
use App\Domains\Loyalty\Services\StampCardService;
use App\Domains\Loyalty\Services\TierService;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyTiersAndStampsTest extends TestCase
{
    use RefreshDatabase;

    protected TierService $tierService;

    protected StampCardService $stampService;

    protected Vendor $vendor;

    protected LoyaltyProgram $program;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tierService = app(TierService::class);
        $this->stampService = app(StampCardService::class);

        $this->vendor = Vendor::create([
            'name' => 'Tiers Bistro',
            'slug' => 'tiers-bistro',
            'email' => 'tiers@bistro.com',
            'password' => bcrypt('password'),
        ]);

        $this->program = LoyaltyProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Tier Bistro Program',
            'currency' => 'USD',
            'default_points_ratio' => 1.0,
            'points_enabled' => true,
            'cashback_enabled' => true,
        ]);

        $this->customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bob Tier',
            'phone' => '+1555666777',
            'loyalty_points' => 0,
            'loyalty_tier' => 'Bronze',
        ]);
    }

    public function test_default_tiers_are_seeded_correctly(): void
    {
        $this->tierService->ensureDefaultTiers($this->vendor, $this->program);

        $tiers = LoyaltyTier::where('vendor_id', $this->vendor->id)
            ->orderBy('level', 'asc')
            ->get();

        $this->assertCount(4, $tiers);
        $this->assertEquals('Bronze', $tiers[0]->name);
        $this->assertEquals(1, $tiers[0]->level);
        $this->assertEquals(1.0, $tiers[0]->points_multiplier);

        $this->assertEquals('Silver', $tiers[1]->name);
        $this->assertEquals(2, $tiers[1]->level);
        $this->assertEquals(1.25, $tiers[1]->points_multiplier);

        $this->assertEquals('Gold', $tiers[2]->name);
        $this->assertEquals(3, $tiers[2]->level);
        $this->assertEquals(1.50, $tiers[2]->points_multiplier);

        $this->assertEquals('Platinum', $tiers[3]->name);
        $this->assertEquals(4, $tiers[3]->level);
        $this->assertEquals(2.0, $tiers[3]->points_multiplier);
    }

    public function test_customer_tier_auto_upgrades_on_points_threshold(): void
    {
        $this->tierService->ensureDefaultTiers($this->vendor, $this->program);

        // Update customer points to 1600 (Gold threshold is 1500)
        $this->customer->update(['loyalty_points' => 1600]);

        $upgradedTier = $this->tierService->evaluateCustomerTier($this->customer);

        $this->assertNotNull($upgradedTier);
        $this->assertEquals('Gold', $upgradedTier->name);

        $this->customer->refresh();
        $this->assertEquals('Gold', $this->customer->loyalty_tier);

        $history = LoyaltyTierHistory::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals($upgradedTier->id, $history->tier_id);
    }

    public function test_stamp_card_progression_and_completion(): void
    {
        $stampProg = LoyaltyStampProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Coffee Stamp Card',
            'stamps_required' => 3,
            'reward_description' => 'Free Espresso',
            'min_order_spend' => 5.0,
            'is_active' => true,
        ]);

        // Award 1st stamp
        $card1 = $this->stampService->awardStamps(new StampAwardDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            programId: $stampProg->id,
            stamps: 1
        ));

        $this->assertEquals(1, $card1->stamps_collected);
        $this->assertEquals(StampStatus::Active, $card1->status);

        // Award 2nd stamp
        $card2 = $this->stampService->awardStamps(new StampAwardDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            programId: $stampProg->id,
            stamps: 1
        ));

        $this->assertEquals(2, $card2->stamps_collected);
        $this->assertEquals(StampStatus::Active, $card2->status);

        // Award 3rd stamp -> Reaches target of 3!
        $card3 = $this->stampService->awardStamps(new StampAwardDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            programId: $stampProg->id,
            stamps: 1
        ));

        $this->assertEquals(3, $card3->stamps_collected);
        $this->assertEquals(StampStatus::Completed, $card3->status);
        $this->assertNotNull($card3->completed_at);
    }

    public function test_order_min_spend_gate_for_stamps(): void
    {
        $stampProg = LoyaltyStampProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Burger Card',
            'stamps_required' => 5,
            'reward_description' => 'Free Fries',
            'min_order_spend' => 20.0,
            'is_active' => true,
        ]);

        $orderUnder = new Order([
            'vendor_id' => $this->vendor->id,
            'order_number' => 'ORD-101',
            'subtotal' => 15.0,
            'total_amount' => 15.0,
            'paid_amount' => 15.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $resultUnder = $this->stampService->awardStampsForOrder($orderUnder, $this->customer);
        $this->assertNull($resultUnder);

        $orderOver = new Order([
            'vendor_id' => $this->vendor->id,
            'order_number' => 'ORD-102',
            'subtotal' => 25.0,
            'total_amount' => 25.0,
            'paid_amount' => 25.0,
            'discount_amount' => 0.0,
            'status' => 'completed',
        ]);

        $resultOver = $this->stampService->awardStampsForOrder($orderOver, $this->customer);
        $this->assertNotNull($resultOver);
        $this->assertEquals(1, $resultOver->stamps_collected);
    }
}
