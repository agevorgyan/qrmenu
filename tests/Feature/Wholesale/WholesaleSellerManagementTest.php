<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleSellerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

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

        $this->superadmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@platform.am',
            'password' => bcrypt('password123'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        // Grant Wholesale POS entitlement to Vendor A and Vendor B for seller management tests
        app(WholesaleAccessService::class)->grantAccess($this->vendorA, $this->superadmin);
        app(WholesaleAccessService::class)->grantAccess($this->vendorB, $this->superadmin);
    }

    public function test_tenant_owner_can_create_seller_company_with_vat_regime(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.wholesale.sellers.store'), [
                'name' => 'Sales Plus Distribution',
                'legal_name' => '«ՍԵՅԼՍ ՊԼՅՈՒՍ» Սահմանափակ պատասխանատվությամբ ընկերություն (ՍՊԸ)',
                'tin' => '08293072',
                'vat_number' => '08293072/1',
                'tax_regime' => 'vat_payer',
                'address' => 'ԵՐԵՎԱՆ ԴԱՎԹԱՇԵՆ ԴԱՎԹԱՇԵՆ 1 ԹՂՄ. 38 7 ԲՆ.',
                'default_supply_location' => 'Հայաստան, ԿՈՏԱՅՔ, ԶՈՎՈՒՆԻ, ԶՈՎՈՒՆԻ, 30փ 2նրբ 31, 182տար',
                'pek_username' => 'salesplus25',
                'bank_name' => '«ԱՄԵՐԻԱԲԱՆԿ» ՓԲԸ',
                'account_number' => '1570077654886500',
                'currency' => 'AMD',
            ]);

        $response->assertRedirect();

        $seller = WholesaleSeller::where('tin', '08293072')->first();
        $this->assertNotNull($seller);
        $this->assertEquals((int) $this->vendorA->id, (int) $seller->vendor_id);
        $this->assertEquals(SellerTaxRegime::VAT_PAYER, $seller->tax_regime);
        $this->assertTrue($seller->isVatPayer());

        // Assert tax profile was automatically created
        $this->assertDatabaseHas('wholesale_seller_tax_profiles', [
            'seller_id' => $seller->id,
            'tax_regime' => 'vat_payer',
            'is_vat_payer' => true,
            'vat_rate' => 20.00,
            'document_series' => 'A',
        ]);

        // Assert default location and default bank account exist
        $this->assertDatabaseHas('wholesale_seller_locations', [
            'seller_id' => $seller->id,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('wholesale_seller_bank_accounts', [
            'seller_id' => $seller->id,
            'account_number' => '1570077654886500',
            'is_default' => true,
        ]);

        $this->assertNotNull($seller->default_bank_account_id);
    }

    public function test_seller_tin_must_be_strictly_eight_numeric_digits(): void
    {
        // 7 digits
        $response1 = $this->actingAs($this->ownerA)
            ->post(route('admin.wholesale.sellers.store'), [
                'name' => 'Invalid Seller',
                'legal_name' => 'Invalid LLC',
                'tin' => '1234567',
                'tax_regime' => 'vat_payer',
                'address' => 'Yerevan',
            ]);

        $response1->assertSessionHasErrors('tin');

        // Letters
        $response2 = $this->actingAs($this->ownerA)
            ->post(route('admin.wholesale.sellers.store'), [
                'name' => 'Invalid Seller',
                'legal_name' => 'Invalid LLC',
                'tin' => '1234567A',
                'tax_regime' => 'vat_payer',
                'address' => 'Yerevan',
            ]);

        $response2->assertSessionHasErrors('tin');
    }

    public function test_duplicate_tin_within_same_tenant_is_rejected(): void
    {
        WholesaleSeller::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'First Seller',
            'legal_name' => 'First Legal',
            'tin' => '08285927',
            'tax_regime' => SellerTaxRegime::TURNOVER_TAX,
            'address' => 'Yerevan',
        ]);

        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.wholesale.sellers.store'), [
                'name' => 'Second Seller With Same TIN',
                'legal_name' => 'Second Legal',
                'tin' => '08285927',
                'tax_regime' => 'turnover_tax',
                'address' => 'Yerevan',
            ]);

        $response->assertSessionHasErrors('tin');
    }

    public function test_updating_seller_tax_regime_creates_effective_tax_profile(): void
    {
        $seller = WholesaleSeller::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Gayane Kitchen',
            'legal_name' => '«ԳԱՅԱՆԵԻ ԽՈՀԱՆՈՑ» ՍՊԸ',
            'tin' => '08285927',
            'tax_regime' => SellerTaxRegime::TURNOVER_TAX,
            'address' => 'Yerevan',
        ]);

        $this->assertFalse($seller->isVatPayer());

        // Update to VAT payer
        $response = $this->actingAs($this->ownerA)
            ->put(route('admin.wholesale.sellers.update', $seller), [
                'name' => 'Gayane Kitchen LLC',
                'legal_name' => '«ԳԱՅԱՆԵԻ ԽՈՀԱՆՈՑ» ՍՊԸ',
                'tin' => '08285927',
                'tax_regime' => 'vat_payer',
                'address' => 'Yerevan',
            ]);

        $response->assertRedirect();
        $seller->refresh();

        $this->assertEquals(SellerTaxRegime::VAT_PAYER, $seller->tax_regime);
        $this->assertTrue($seller->isVatPayer());
        $this->assertCount(1, $seller->taxProfiles);
    }

    public function test_tenant_can_add_multiple_bank_accounts_and_locations(): void
    {
        $seller = WholesaleSeller::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Ameria Seller',
            'legal_name' => 'Ameria Seller LLC',
            'tin' => '01234567',
            'tax_regime' => SellerTaxRegime::VAT_PAYER,
            'address' => 'Yerevan',
        ]);

        // Add Bank Account 1 (Default)
        $this->actingAs($this->ownerA)
            ->post(route('admin.wholesale.sellers.bank_accounts.store', $seller), [
                'bank_name' => 'Ameriabank',
                'account_number' => '1570011122233300',
                'currency' => 'AMD',
                'is_default' => 1,
            ]);

        // Add Bank Account 2 (USD)
        $this->actingAs($this->ownerA)
            ->post(route('admin.wholesale.sellers.bank_accounts.store', $seller), [
                'bank_name' => 'Ardshinbank',
                'account_number' => '2470099988877700',
                'currency' => 'USD',
                'is_default' => 0,
            ]);

        $this->assertEquals(2, $seller->bankAccounts()->count());
        $this->assertEquals('Ameriabank', $seller->fresh()->defaultBankAccount->bank_name);

        // Add Supply Location
        $this->actingAs($this->ownerA)
            ->post(route('admin.wholesale.sellers.locations.store', $seller), [
                'name' => 'Secondary Cold Facility',
                'address' => 'Kotayk, Zovuni 5',
                'is_default' => 0,
            ]);

        $this->assertEquals(1, $seller->locations()->count());
    }

    public function test_cross_tenant_isolation_prevents_viewing_other_tenants_seller(): void
    {
        // Seller belonging to Vendor A
        $sellerA = WholesaleSeller::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Seller A',
            'legal_name' => 'Seller A LLC',
            'tin' => '11111111',
            'tax_regime' => SellerTaxRegime::VAT_PAYER,
            'address' => 'Address A',
        ]);

        // Owner B attempts to view Seller A -> 404 (due to TenantScope)
        $response = $this->actingAs($this->ownerB)
            ->get(route('admin.wholesale.sellers.show', $sellerA));

        $response->assertStatus(404);

        // Owner B attempts to update Seller A -> 404
        $updateResponse = $this->actingAs($this->ownerB)
            ->put(route('admin.wholesale.sellers.update', $sellerA), [
                'name' => 'Hacked Name',
                'legal_name' => 'Hacked LLC',
                'tin' => '11111111',
                'tax_regime' => 'vat_payer',
                'address' => 'Hacked Address',
            ]);

        $updateResponse->assertStatus(404);
        $this->assertEquals('Seller A', $sellerA->fresh()->name);
    }
}
