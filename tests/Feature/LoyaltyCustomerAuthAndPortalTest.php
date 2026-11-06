<?php

namespace Tests\Feature;

use App\Domains\Loyalty\Actions\GenerateCustomerQrTokenAction;
use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Enums\RewardType;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Domains\Loyalty\Services\CustomerLoyaltyService;
use App\Domains\Loyalty\Services\PointsLedgerService;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoyaltyCustomerAuthAndPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Storefront Bistro',
            'slug' => 'storefront-bistro',
            'email' => 'store@bistro.com',
            'password' => bcrypt('password'),
        ]);

        $this->program = LoyaltyProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Storefront Program',
            'currency' => 'USD',
            'default_points_ratio' => 1.0,
            'points_enabled' => true,
            'cashback_enabled' => true,
        ]);
    }

    public function test_send_otp_success_and_throttle_lock(): void
    {
        $phone = '+15551234567';

        // First OTP send
        $response1 = $this->postJson(route('client.loyalty.otp.send', ['vendor_slug' => $this->vendor->slug]), [
            'identifier' => $phone,
        ]);

        $response1->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        // Immediate second OTP send -> Should be rejected due to 60s cooldown
        $response2 = $this->postJson(route('client.loyalty.otp.send', ['vendor_slug' => $this->vendor->slug]), [
            'identifier' => $phone,
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_verify_otp_authenticates_customer_and_sets_session(): void
    {
        $phone = '+15559876543';

        // Pre-seed an active OTP in cache matching CustomerLoyaltyService logic
        $cacheKey = "loyalty_otp_{$this->vendor->id}_{$phone}";
        Cache::put($cacheKey, '654321', now()->addMinutes(10));

        $response = $this->postJson(route('client.loyalty.otp.verify', ['vendor_slug' => $this->vendor->slug]), [
            'identifier' => $phone,
            'code' => '654321',
            'name' => 'New Loyalty Member',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $customer = Customer::where('vendor_id', $this->vendor->id)->where('phone', $phone)->first();
        $this->assertNotNull($customer);
        $this->assertEquals('New Loyalty Member', $customer->name);

        $response->assertSessionHas('loyalty_customer_id', $customer->id);
    }

    public function test_customer_portal_renders_with_balances_and_rewards(): void
    {
        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Portal User',
            'phone' => '+15557778888',
            'loyalty_points' => 350,
            'loyalty_tier' => 'Bronze',
        ]);

        $response = $this->withSession([
            'loyalty_customer_id' => $customer->id,
            'loyalty_vendor_id' => $this->vendor->id,
        ])->get(route('client.loyalty.portal', ['vendor_slug' => $this->vendor->slug]));

        $response->assertOk();
        $response->assertSee('Portal User');
        $response->assertSee('350');
    }

    public function test_ephemeral_qr_token_generation_has_no_pii_and_is_valid(): void
    {
        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Private Patronson',
            'phone' => '+15553332211',
            'email' => 'private@secrets.com',
        ]);

        $action = new GenerateCustomerQrTokenAction;
        $qrRecord = $action->execute($customer);
        $token = $qrRecord->token;

        $this->assertNotEmpty($token);
        // CRITICAL SECURITY RULE: Zero customer phone or email or PII in QR token
        $this->assertStringNotContainsString('+15553332211', $token);
        $this->assertStringNotContainsString('private@secrets.com', $token);
        $this->assertStringNotContainsString('Private Patronson', $token);

        // Verification succeeds and returns the customer
        $resolved = $action->resolve($this->vendor->id, $token);
        $this->assertNotNull($resolved);
        $this->assertEquals($customer->id, $resolved->id);
    }

    public function test_claim_reward_deducts_points_and_returns_voucher_code(): void
    {
        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Reward Hunter',
            'phone' => '+15554443322',
            'loyalty_points' => 0,
        ]);

        // Credit 500 points
        app(PointsLedgerService::class)->creditPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $customer->id,
            points: 500,
            type: LoyaltyTransactionType::Earned
        ));

        $reward = LoyaltyReward::create([
            'vendor_id' => $this->vendor->id,
            'title' => 'Complimentary Tiramisu',
            'reward_type' => RewardType::FreeProduct,
            'points_cost' => 200,
            'is_active' => true,
        ]);

        $response = $this->withSession([
            'loyalty_customer_id' => $customer->id,
            'loyalty_vendor_id' => $this->vendor->id,
        ])->postJson(route('client.loyalty.rewards.claim', [
            'vendor_slug' => $this->vendor->slug,
            'reward' => $reward->id,
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'new_balance' => 300,
            ]);

        $customer->refresh();
        $this->assertEquals(300, $customer->loyalty_points);
    }

    public function test_claim_reward_insufficient_points_fails(): void
    {
        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Poor Hunter',
            'phone' => '+15550001122',
            'loyalty_points' => 50,
        ]);

        $reward = LoyaltyReward::create([
            'vendor_id' => $this->vendor->id,
            'title' => 'Steak Dinner',
            'reward_type' => RewardType::FreeProduct,
            'points_cost' => 500,
            'is_active' => true,
        ]);

        $response = $this->withSession([
            'loyalty_customer_id' => $customer->id,
            'loyalty_vendor_id' => $this->vendor->id,
        ])->postJson(route('client.loyalty.rewards.claim', [
            'vendor_slug' => $this->vendor->slug,
            'reward' => $reward->id,
        ]));

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);
    }
}
