<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Models\WholesaleAtgaaClassification;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Models\Ingredient;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

    protected Unit $unitKg;

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

        // Entitle Vendor A
        $accessService = app(WholesaleAccessService::class);
        $accessService->grantAccess($this->vendorA, $this->superadmin, null, 'Test Entitlement');

        // Units
        $this->unitKg = Unit::create([
            'vendor_id' => null,
            'name' => 'Kilogram',
            'code' => 'kg',
            'symbol' => 'kg',
            'type' => 'mass',
            'is_active' => true,
        ]);

        // Ensure default ATGAA classifications exist
        WholesaleAtgaaClassification::firstOrCreate(
            ['code' => '1602'],
            ['name_hy' => 'Մսից, ենթամթերքներից կամ արյունից պատրաստված այլ պատրաստի կամ պահածոյացված մթերք', 'name_en' => 'Other prepared or preserved meat, meat offal or blood', 'is_active' => true]
        );
        WholesaleAtgaaClassification::firstOrCreate(
            ['code' => '1902'],
            ['name_hy' => 'Մակարոնեղեն', 'name_en' => 'Pasta', 'is_active' => true]
        );
    }

    public function test_entitled_tenant_can_view_wholesale_product_catalog_index(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.wholesale.products.index'));

        $response->assertStatus(200);
        $response->assertViewIs('wholesale.products.index');
        $response->assertSee(__('Wholesale Product Catalog'));
    }

    public function test_product_create_page_loads_with_required_form_fields(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.wholesale.products.create'));

        $response->assertStatus(200);
        $response->assertViewIs('wholesale.products.create');
        $response->assertSee('1602');
        $response->assertSee('Kilogram');
    }

    public function test_can_create_wholesale_product_with_valid_atgaa_and_pricing(): void
    {
        $payload = [
            'name' => 'Պելմենի Ֆիրմային (Տավարի)',
            'sku' => 'PELM-BEEF-001',
            'barcode' => '4850001122334',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 1800.00,
            'min_wholesale_price' => 1500.00,
            'cost_price' => 1100.00,
            'is_vat_applicable' => 1,
            'vat_rate' => 20.00,
            'min_order_quantity' => 5,
            'order_quantity_multiple' => 1,
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('wholesale_products', [
            'vendor_id' => $this->vendorA->id,
            'sku' => 'PELM-BEEF-001',
            'name' => 'Պելմենի Ֆիրմային (Տավարի)',
            'classifier_code' => '1602',
            'base_wholesale_price' => 1800.00,
            'min_wholesale_price' => 1500.00,
            'cost_price' => 1100.00,
            'is_vat_applicable' => true,
        ]);
    }

    public function test_cannot_create_wholesale_product_with_unrecognized_atgaa_code(): void
    {
        $payload = [
            'name' => 'Fake ATGAA Product',
            'sku' => 'FAKE-ATGAA-01',
            'classifier_code' => '99999999', // Non-existent
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 1000.00,
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), $payload);

        $response->assertSessionHasErrors(['classifier_code']);
        $this->assertDatabaseMissing('wholesale_products', [
            'sku' => 'FAKE-ATGAA-01',
        ]);
    }

    public function test_sku_must_be_unique_within_same_tenant_but_allowed_across_tenants(): void
    {
        // Give Vendor B access
        app(WholesaleAccessService::class)->grantAccess($this->vendorB, $this->superadmin, null, 'Entitlement B');

        // Create product in Vendor A
        $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), [
            'name' => 'Universal Flour',
            'sku' => 'FLOUR-50KG',
            'classifier_code' => '1902',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 15000.00,
        ])->assertRedirect();

        // Duplicate SKU in Vendor A must fail
        $duplicateResponse = $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), [
            'name' => 'Another Universal Flour',
            'sku' => 'FLOUR-50KG',
            'classifier_code' => '1902',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 16000.00,
        ]);
        $duplicateResponse->assertSessionHasErrors(['sku']);

        // Same SKU in Vendor B must succeed (Tenant Isolation)
        $vendorBResponse = $this->actingAs($this->ownerB)->post(route('admin.wholesale.products.store'), [
            'name' => 'Beta Vendor Flour',
            'sku' => 'FLOUR-50KG',
            'classifier_code' => '1902',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 14500.00,
        ]);
        $vendorBResponse->assertRedirect();

        $this->assertEquals(2, WholesaleProduct::withoutGlobalScopes()->where('sku', 'FLOUR-50KG')->count());
        $this->assertEquals(1, WholesaleProduct::withoutGlobalScopes()->where('sku', 'FLOUR-50KG')->where('vendor_id', $this->vendorA->id)->count());
        $this->assertEquals(1, WholesaleProduct::withoutGlobalScopes()->where('sku', 'FLOUR-50KG')->where('vendor_id', $this->vendorB->id)->count());
    }

    public function test_min_price_floor_cannot_exceed_base_wholesale_price(): void
    {
        $payload = [
            'name' => 'Cheese Mozzarella',
            'sku' => 'MOZZ-2KG',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 2000.00,
            'min_wholesale_price' => 2500.00, // Invalid: min > base
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), $payload);

        $response->assertSessionHasErrors(['min_wholesale_price']);
    }

    public function test_can_link_wholesale_product_to_core_inventory_ingredient(): void
    {
        $ingredient = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Raw Beef Filling',
            'sku' => 'ING-BEEF-RAW',
            'unit_id' => $this->unitKg->id,
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Frozen Dumplings',
            'sku' => 'DUMP-FROZEN',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 2200.00,
            'physical_stock_ingredient_id' => $ingredient->id,
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.products.store'), $payload);

        $response->assertRedirect();

        $product = WholesaleProduct::where('sku', 'DUMP-FROZEN')->first();
        $this->assertNotNull($product);
        $this->assertEquals($ingredient->id, $product->physical_stock_ingredient_id);
        $this->assertEquals('Raw Beef Filling', $product->physicalStockIngredient->name);
    }

    public function test_can_add_packaging_tier_to_wholesale_product(): void
    {
        $product = WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Boxed Pasta',
            'sku' => 'PASTA-BOX-01',
            'classifier_code' => '1902',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 500.00,
        ]);

        $response = $this->actingAs($this->ownerA)->post(
            route('admin.wholesale.products.packaging.store', $product),
            [
                'packaging_type' => 'Master Carton (24 Packs)',
                'units_count' => 24,
                'packaging_barcode' => '4850099887766',
                'gross_weight_kg' => 12.5,
                'dimensions' => '40x30x20',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('wholesale_product_packagings', [
            'product_id' => $product->id,
            'packaging_type' => 'Master Carton (24 Packs)',
            'units_count' => 24,
            'packaging_barcode' => '4850099887766',
        ]);
    }

    public function test_tenant_b_cannot_view_or_modify_tenant_a_products(): void
    {
        app(WholesaleAccessService::class)->grantAccess($this->vendorB, $this->superadmin, null, 'Entitlement B');

        $productA = WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Confidential Product Alpha',
            'sku' => 'ALPHA-SECRET-01',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 1000.00,
        ]);

        // Tenant B trying to view Tenant A's product directly
        $response = $this->actingAs($this->ownerB)->get(route('admin.wholesale.products.show', $productA));
        $response->assertStatus(404);

        // Tenant B trying to edit Tenant A's product
        $editResponse = $this->actingAs($this->ownerB)->get(route('admin.wholesale.products.edit', $productA));
        $editResponse->assertStatus(404);

        // Tenant B trying to update Tenant A's product
        $updateResponse = $this->actingAs($this->ownerB)->put(route('admin.wholesale.products.update', $productA), [
            'name' => 'Hacked Name',
            'sku' => 'ALPHA-SECRET-01',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 1.00,
        ]);
        $updateResponse->assertStatus(404);
    }
}
