<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Domains\Wholesale\Services\WholesaleCustomerService;
use App\Domains\Wholesale\Services\WholesalePricingEngine;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesalePricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

    protected Unit $unitKg;

    protected WholesaleProduct $productPelmeni;

    protected WholesalePricingEngine $pricingEngine;

    protected WholesaleCustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::first();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Superadmin
        $this->superadmin = User::create([
            'name' => 'Master Super Admin',
            'email' => 'super@admin.am',
            'password' => bcrypt('password123'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        app(WholesaleAccessService::class)->grantAccess($this->vendorA, $this->superadmin, null, 'Entitlement A');

        $this->unitKg = Unit::create([
            'vendor_id' => null,
            'name' => 'Kilogram',
            'code' => 'kg',
            'symbol' => 'kg',
            'type' => 'mass',
            'is_active' => true,
        ]);

        $this->productPelmeni = WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Պելմենի Տավարի (1կգ)',
            'sku' => 'PELM-1KG',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 2000.00,
            'min_wholesale_price' => 1500.00,
            'is_vat_applicable' => true,
            'vat_rate' => 20.00,
        ]);

        $this->pricingEngine = app(WholesalePricingEngine::class);
        $this->customerService = app(WholesaleCustomerService::class);
    }

    public function test_can_create_customer_with_valid_8_digit_tin_and_credit_limit(): void
    {
        $payload = [
            'name' => 'Yerevan City Supermarkets',
            'legal_name' => 'City LLC',
            'tin' => '02555555',
            'tax_regime' => 'vat_payer',
            'credit_limit' => 5000000.00,
            'payment_term_days' => 30,
            'is_credit_allowed' => 1,
            'phone' => '+374 10 555555',
            'email' => 'procurement@city.am',
            'address_name' => 'Central Hub',
            'address_line1' => 'Tigran Mets 10',
            'city' => 'Yerevan',
            'contact_person' => 'Arman Hakobyan',
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.customers.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('wholesale_customers', [
            'vendor_id' => $this->vendorA->id,
            'name' => 'Yerevan City Supermarkets',
            'tin' => '02555555',
            'credit_limit' => 5000000.00,
            'payment_term_days' => 30,
            'is_credit_allowed' => true,
        ]);

        $customer = WholesaleCustomer::where('tin', '02555555')->first();
        $this->assertNotNull($customer);
        $this->assertEquals(1, $customer->addresses()->count());
        $this->assertEquals('Central Hub', $customer->defaultAddress->name);
    }

    public function test_customer_creation_fails_with_invalid_tin(): void
    {
        $payload = [
            'name' => 'Bad TIN Corp',
            'tin' => '1234', // Invalid: only 4 digits, must be exactly 8
            'tax_regime' => 'vat_payer',
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.customers.store'), $payload);

        $response->assertSessionHasErrors(['tin']);
    }

    public function test_pricing_engine_uses_base_price_when_no_rules_or_discounts_exist(): void
    {
        $result = $this->pricingEngine->resolvePrice($this->productPelmeni, null, 1.0);

        $this->assertEquals(2000.00, $result['base_price']);
        $this->assertEquals(2000.00, $result['resolved_unit_price']);
        $this->assertEquals('BASE_PRICE', $result['applied_rule']);
        $this->assertEquals(0.00, $result['discount_total']);
        $this->assertEquals(2000.00, $result['subtotal']);
        $this->assertEquals(400.00, $result['vat_amount']); // 20% VAT on 2000
        $this->assertEquals(2400.00, $result['total_amount']);
    }

    public function test_pricing_engine_applies_volume_tier_break_when_quantity_threshold_met(): void
    {
        // Add volume discount: for >= 10 units, 10% discount
        $this->customerService->addQuantityDiscount($this->productPelmeni, [
            'min_quantity' => 10,
            'discount_percent' => 10.0,
        ]);

        // When qty = 5 -> base price
        $res5 = $this->pricingEngine->resolvePrice($this->productPelmeni, null, 5.0);
        $this->assertEquals(2000.00, $res5['resolved_unit_price']);
        $this->assertEquals('BASE_PRICE', $res5['applied_rule']);

        // When qty = 15 -> 10% volume discount (2000 * 0.9 = 1800)
        $res15 = $this->pricingEngine->resolvePrice($this->productPelmeni, null, 15.0);
        $this->assertEquals(1800.00, $res15['resolved_unit_price']);
        $this->assertEquals('VOLUME_TIER_PERCENT', $res15['applied_rule']);
        $this->assertEquals(200.00, $res15['discount_unit_amount']);
        $this->assertEquals(3000.00, $res15['discount_total']);
        $this->assertEquals(27000.00, $res15['subtotal']); // 15 * 1800
        $this->assertEquals(5400.00, $res15['vat_amount']); // 20% on 27000
        $this->assertEquals(32400.00, $res15['total_amount']);
    }

    public function test_pricing_engine_applies_assigned_customer_price_list_over_volume_discount(): void
    {
        $customer = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'SAS Group',
            'tin' => '01000001',
            'tax_regime' => 'vat_payer',
        ]);

        // Create Tier 1 Price List with price = 1750 AMD
        $priceList = $this->customerService->createPriceList($this->vendorA, [
            'name' => 'VIP Chains Tier',
            'code' => 'VIP-CHAINS',
            'currency' => 'AMD',
        ]);

        $this->customerService->setPriceListItem($priceList, $this->productPelmeni, 1750.00, 1.0);

        // Assign price list to customer
        $this->customerService->setCustomerPricing($customer, [
            'price_list_id' => $priceList->id,
        ]);

        $result = $this->pricingEngine->resolvePrice($this->productPelmeni, $customer, 2.0);

        $this->assertEquals(1750.00, $result['resolved_unit_price']);
        $this->assertEquals('CUSTOMER_PRICE_LIST', $result['applied_rule']);
    }

    public function test_pricing_engine_contract_price_takes_highest_priority(): void
    {
        $customer = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Exclusive Partner',
            'tin' => '02000002',
            'tax_regime' => 'vat_payer',
        ]);

        // Explicit contract price = 1600 AMD
        $this->customerService->setCustomerPricing($customer, [
            'product_id' => $this->productPelmeni->id,
            'contract_price' => 1600.00,
        ]);

        $result = $this->pricingEngine->resolvePrice($this->productPelmeni, $customer, 1.0);

        $this->assertEquals(1600.00, $result['resolved_unit_price']);
        $this->assertEquals('CONTRACT_PRICE', $result['applied_rule']);
    }

    public function test_pricing_engine_enforces_hard_min_price_floor_constraint(): void
    {
        $customer = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Aggressive Negotiator',
            'tin' => '03000003',
            'tax_regime' => 'vat_payer',
        ]);

        // Product min_wholesale_price is 1500.00
        // Contract price attempts to sell at 1200.00 (below floor)
        $this->customerService->setCustomerPricing($customer, [
            'product_id' => $this->productPelmeni->id,
            'contract_price' => 1200.00,
        ]);

        // Without override permission -> Floor enforced at 1500.00
        $result = $this->pricingEngine->resolvePrice($this->productPelmeni, $customer, 1.0, false);
        $this->assertEquals(1500.00, $result['resolved_unit_price']);
        $this->assertTrue($result['floor_enforced']);

        // With explicit override permission -> Allows 1200.00
        $resultOverride = $this->pricingEngine->resolvePrice($this->productPelmeni, $customer, 1.0, true);
        $this->assertEquals(1200.00, $resultOverride['resolved_unit_price']);
        $this->assertFalse($resultOverride['floor_enforced']);
    }

    public function test_customer_credit_limits_and_affordability(): void
    {
        $customer = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Credit Client',
            'tin' => '04000004',
            'tax_regime' => 'vat_payer',
            'credit_limit' => 100000.00,
            'is_credit_allowed' => true,
            'current_balance' => 80000.00,
        ]);

        // Sale of 15,000 AMD: 80,000 + 15,000 = 95,000 <= 100,000 -> Allowed
        $this->assertTrue($customer->canAffordCredit(15000.00));

        // Sale of 25,000 AMD: 80,000 + 25,000 = 105,000 > 100,000 -> Rejected
        $this->assertFalse($customer->canAffordCredit(25000.00));

        // Customer with credit disallowed -> Always false
        $customer->is_credit_allowed = false;
        $customer->save();
        $this->assertFalse($customer->canAffordCredit(100.00));
    }

    public function test_cross_tenant_isolation_protects_customer_records(): void
    {
        app(WholesaleAccessService::class)->grantAccess($this->vendorB, $this->superadmin, null, 'Entitlement B');

        $customerA = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Customer A Confidential',
            'tin' => '05000005',
            'tax_regime' => 'vat_payer',
        ]);

        // Tenant B cannot view Customer A -> 404
        $response = $this->actingAs($this->ownerB)->get(route('admin.wholesale.customers.show', $customerA));
        $response->assertStatus(404);
    }
}
