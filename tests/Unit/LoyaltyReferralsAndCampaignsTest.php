<?php

namespace Tests\Unit;

use App\Domains\Loyalty\Enums\CampaignTriggerType;
use App\Domains\Loyalty\Exceptions\ReferralValidationException;
use App\Domains\Loyalty\Models\LoyaltyCampaign;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Services\CampaignService;
use App\Domains\Loyalty\Services\ReferralService;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyReferralsAndCampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $referralService;

    protected CampaignService $campaignService;

    protected Vendor $vendor;

    protected LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->referralService = app(ReferralService::class);
        $this->campaignService = app(CampaignService::class);

        $this->vendor = Vendor::create([
            'name' => 'Ref Bistro',
            'slug' => 'ref-bistro',
            'email' => 'ref@bistro.com',
            'password' => bcrypt('password'),
        ]);

        $this->program = LoyaltyProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Ref Program',
            'currency' => 'USD',
            'points_enabled' => true,
            'referrals_enabled' => true,
            'settings' => [
                'referral' => [
                    'referrer_points' => 150,
                    'referred_points' => 75,
                    'min_order_spend' => 30.0,
                ],
            ],
        ]);
    }

    public function test_generates_and_registers_referral_successfully(): void
    {
        $referrer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Referrer Bob',
            'phone' => '+111222333',
        ]);

        $referee = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Referee Alice',
            'phone' => '+111222444',
        ]);

        $code = $this->referralService->getCustomerReferralCode($referrer);
        $this->assertNotEmpty($code);

        $referral = $this->referralService->registerReferral($referee, $code);

        $this->assertNotNull($referral);
        $this->assertEquals($referrer->id, $referral->referrer_customer_id);
        $this->assertEquals($referee->id, $referral->referred_customer_id);
        $this->assertEquals('registered', $referral->status);
    }

    public function test_self_referral_throws_exception(): void
    {
        $this->expectException(ReferralValidationException::class);

        $customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Solo Customer',
            'phone' => '+111222555',
        ]);

        $code = $this->referralService->getCustomerReferralCode($customer);
        $this->referralService->registerReferral($customer, $code);
    }

    public function test_circular_referral_throws_exception(): void
    {
        $custA = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Cust A',
            'phone' => '+111222666',
        ]);

        $custB = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Cust B',
            'phone' => '+111222777',
        ]);

        $codeA = $this->referralService->getCustomerReferralCode($custA);
        $this->referralService->registerReferral($custB, $codeA);

        // Now B tries to refer A -> Circular!
        $this->expectException(ReferralValidationException::class);
        $codeB = $this->referralService->getCustomerReferralCode($custB);
        $this->referralService->registerReferral($custA, $codeB);
    }

    public function test_referral_qualifies_on_order_above_threshold(): void
    {
        $referrer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Referrer Rick',
            'phone' => '+111222888',
        ]);

        $referee = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Referee Morty',
            'phone' => '+111222999',
        ]);

        $code = $this->referralService->getCustomerReferralCode($referrer);
        $this->referralService->registerReferral($referee, $code);

        $order = new Order([
            'vendor_id' => $this->vendor->id,
            'order_number' => 'ORD-REF-01',
            'total_amount' => 50.0,
            'paid_amount' => 50.0,
            'status' => 'completed',
        ]);

        $qualified = $this->referralService->qualifyReferralOnOrder($order, $referee);

        $this->assertNotNull($qualified);
        $this->assertEquals('qualified', $qualified->status);
        $this->assertEquals(150, $qualified->referrer_points_awarded);
        $this->assertEquals(75, $qualified->referred_points_awarded);

        // Verify points credited in referrer account
        $referrer->refresh();
        $this->assertEquals(150, $referrer->loyalty_points);
    }

    public function test_campaign_trigger_welcome_and_deduplication(): void
    {
        $campaign = LoyaltyCampaign::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Welcome 100 Points',
            'trigger_type' => CampaignTriggerType::Welcome,
            'reward_type' => 'points',
            'reward_value' => 100,
            'is_active' => true,
        ]);

        $newCustomer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'New User',
            'phone' => '+111333444',
            'loyalty_points' => 0,
        ]);

        // First trigger -> awards points
        $executed = $this->campaignService->triggerCampaigns($newCustomer, CampaignTriggerType::Welcome);
        $this->assertCount(1, $executed);

        $newCustomer->refresh();
        $this->assertEquals(100, $newCustomer->loyalty_points);

        // Second trigger -> Deduplication prevents awarding again
        $executedAgain = $this->campaignService->triggerCampaigns($newCustomer, CampaignTriggerType::Welcome);
        $this->assertCount(0, $executedAgain);
        $this->assertEquals(100, $newCustomer->loyalty_points);
    }
}
