<?php

namespace Tests\Feature;

use App\Domains\Loyalty\Actions\GenerateCustomerQrTokenAction;
use App\Domains\Loyalty\Enums\RewardType;
use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Domains\Loyalty\Services\WalletService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantLoyaltyIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected User $staffA;

    protected User $staffB;

    protected Customer $customerA;

    protected Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Alpha Trattoria',
            'slug' => 'alpha-trattoria',
            'email' => 'alpha@trattoria.com',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $locA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Location',
            'slug' => 'alpha-loc',
            'is_active' => true,
        ]);

        $this->staffA = User::create([
            'name' => 'Staff Alpha',
            'email' => 'staff@alpha.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $locA->id,
            'is_active' => true,
        ]);

        $this->customerA = Customer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Customer Alpha',
            'phone' => '+1111111111',
            'loyalty_points' => 100,
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Beta Steakhouse',
            'slug' => 'beta-steakhouse',
            'email' => 'beta@steakhouse.com',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $locB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Location',
            'slug' => 'beta-loc',
            'is_active' => true,
        ]);

        $this->staffB = User::create([
            'name' => 'Staff Beta',
            'email' => 'staff@beta.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendorB->id,
            'location_id' => $locB->id,
            'is_active' => true,
        ]);

        $this->customerB = Customer::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Customer Beta',
            'phone' => '+2222222222',
            'loyalty_points' => 500,
        ]);
    }

    public function test_staff_a_cannot_lookup_customer_b_via_ephemeral_token(): void
    {
        $qrAction = new GenerateCustomerQrTokenAction;
        $tokenB = $qrAction->execute($this->customerB)->token;

        // Staff A attempts lookup with Customer B's token
        $response = $this->actingAs($this->staffA)
            ->postJson(route('admin.loyalty.staff.lookup'), [
                'query' => $tokenB,
            ]);

        // MUST be 404 - cross-tenant token is completely isolated and unrecognized
        $response->assertStatus(404);
    }

    public function test_staff_a_cannot_adjust_or_redeem_points_of_customer_b(): void
    {
        // Staff A attempts to debit points from Customer B
        $response = $this->actingAs($this->staffA)
            ->postJson(route('admin.loyalty.staff.points.redeem'), [
                'customer_id' => $this->customerB->id,
                'points' => 50,
            ]);

        // Must 404 because Customer B is invisible in Vendor A's tenant scope
        $response->assertStatus(404);

        // Verify Customer B's points remain unchanged at 500
        $this->customerB->refresh();
        $this->assertEquals(500, $this->customerB->loyalty_points);
    }

    public function test_customer_a_cannot_claim_reward_belonging_to_vendor_b(): void
    {
        $rewardB = LoyaltyReward::create([
            'vendor_id' => $this->vendorB->id,
            'title' => 'Free Ribeye Steak',
            'reward_type' => RewardType::FreeProduct,
            'points_cost' => 50,
            'is_active' => true,
        ]);

        // Customer A logged in at Vendor A tries to claim Vendor B's reward
        $response = $this->withSession([
            'loyalty_customer_id' => $this->customerA->id,
            'loyalty_vendor_id' => $this->vendorA->id,
        ])->postJson(route('client.loyalty.rewards.claim', [
            'vendor_slug' => $this->vendorA->slug,
            'reward' => $rewardB->id,
        ]));

        // TenantScope prevents model binding cross-tenant -> 404
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_cross_tenant_wallet_passkit_webservice_isolation(): void
    {
        $walletService = app(WalletService::class);
        $passA = $walletService->getOrIssuePass($this->customerA);
        $passB = $walletService->getOrIssuePass($this->customerB);

        // Attempt to request Pass B using Pass A's auth token
        $response = $this->withHeaders([
            'Authorization' => "ApplePass {$passA->authentication_token}",
        ])->get("/api/v1/passes/{$passB->pass_type_identifier}/{$passB->serial_number}");

        $response->assertStatus(401);
    }
}
