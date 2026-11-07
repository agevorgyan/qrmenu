<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleOrder;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Domains\Wholesale\Services\WholesaleOrderService;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesalePosOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

    protected Unit $unitKg;

    protected WholesaleSeller $sellerA;

    protected WholesaleCustomer $customerA;

    protected WholesaleProduct $productPelmeni;

    protected WholesaleOrderService $orderService;

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

        // Seller for Vendor A
        $this->sellerA = WholesaleSeller::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Wholesale Foods LLC',
            'legal_name' => 'Alpha Wholesale Foods LLC',
            'tin' => '12345678',
            'tax_regime' => SellerTaxRegime::VAT_PAYER,
            'address' => 'Yerevan, Arshakunyats 15',
            'is_active' => true,
        ]);

        // Customer for Vendor A
        $this->customerA = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'SAS Supermarkets',
            'legal_name' => 'SAS Group LLC',
            'tin' => '02555555',
            'tax_regime' => 'vat_payer',
            'credit_limit' => 200000.00,
            'payment_term_days' => 14,
            'is_credit_allowed' => true,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        // Product
        $this->productPelmeni = WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Պելմենի Ֆիրմային (1կգ)',
            'sku' => 'PELM-FIRM-1000',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 2000.00,
            'min_wholesale_price' => 1600.00,
            'is_vat_applicable' => true,
            'vat_rate' => 20.00,
            'is_active' => true,
        ]);

        $this->orderService = app(WholesaleOrderService::class);
    }

    public function test_wholesale_pos_terminal_loads_successfully_with_sellers_and_products(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.wholesale.pos.index'));

        $response->assertStatus(200);
        $response->assertViewIs('wholesale.pos.index');
        $response->assertSee('Alpha Wholesale Foods LLC');
        $response->assertSee('SAS Supermarkets');
        $response->assertSee('PELM-FIRM-1000');
    }

    public function test_pos_can_dynamically_calculate_pricing_line_via_api(): void
    {
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.wholesale.pos.calculate_line'), [
            'product_id' => $this->productPelmeni->id,
            'customer_id' => $this->customerA->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'pricing' => [
                'product_id' => $this->productPelmeni->id,
                'resolved_unit_price' => 2000.00,
                'subtotal' => 20000.00,
                'vat_amount' => 4000.00,
                'total_amount' => 24000.00,
            ],
        ]);
    }

    public function test_can_draft_wholesale_order_from_pos(): void
    {
        $payload = [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'order_type' => 'STANDARD',
            'action_type' => 'CREATE_ORDER',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'notes' => 'Deliver before 10 AM',
            'items' => [
                [
                    'product_id' => $this->productPelmeni->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.pos.checkout'), $payload);

        $response->assertRedirect();

        $order = WholesaleOrder::first();
        $this->assertNotNull($order);
        $this->assertEquals('DRAFT', $order->status);
        $this->assertStringStartsWith('WO-'.now()->format('Ymd'), $order->order_number);
        $this->assertEquals(40000.00, $order->subtotal);
        $this->assertEquals(8000.00, $order->tax_total); // 20% on 40,000
        $this->assertEquals(48000.00, $order->total_amount);

        // Verify item snapshot
        $item = $order->items->first();
        $this->assertEquals('1602', $item->classifier_code);
        $this->assertEquals('PELM-FIRM-1000', $item->sku);
        $this->assertEquals(20.0000, $item->quantity_ordered);
    }

    public function test_can_confirm_draft_order(): void
    {
        $order = $this->orderService->createOrder($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 10],
            ],
        ], $this->ownerA);

        $this->assertEquals('DRAFT', $order->status);

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.orders.confirm', $order));

        $response->assertRedirect();
        $this->assertEquals('CONFIRMED', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->confirmed_at);
    }

    public function test_confirming_order_fails_if_customer_credit_limit_is_exceeded(): void
    {
        // Set customer balance already at 190,000 (limit is 200,000)
        $this->customerA->update(['current_balance' => 190000.00]);

        // Order total is 24,000 (10 * 2000 + 4000 VAT). 190,000 + 24,000 = 214,000 > 200,000 limit
        $order = $this->orderService->createOrder($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 10],
            ],
        ], $this->ownerA);

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.orders.confirm', $order));

        $response->assertSessionHasErrors(['credit_limit']);
        $this->assertEquals('DRAFT', $order->fresh()->status);
    }

    public function test_pos_instant_commercial_sale_creates_invoice_and_updates_ar_balance(): void
    {
        $payload = [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'order_type' => 'STANDARD',
            'action_type' => 'CONFIRM_AND_SALE',
            'payment_method' => 'BANK_TRANSFER',
            'paid_amount' => 0, // Unpaid on delivery -> updates AR
            'items' => [
                [
                    'product_id' => $this->productPelmeni->id,
                    'quantity' => 15,
                ],
            ],
        ];

        $response = $this->actingAs($this->ownerA)->post(route('admin.wholesale.pos.checkout'), $payload);

        $response->assertRedirect();

        $sale = WholesaleSale::first();
        $this->assertNotNull($sale);
        $this->assertStringStartsWith('WS-'.now()->format('Ymd'), $sale->invoice_number);
        $this->assertEquals('ISSUED', $sale->status);
        $this->assertEquals(30000.00, $sale->subtotal); // 15 * 2000
        $this->assertEquals(6000.00, $sale->tax_total); // 20% on 30,000
        $this->assertEquals(36000.00, $sale->total_amount);

        // Verify customer AR balance incremented by 36,000
        $this->assertEquals(36000.00, (float) $this->customerA->fresh()->current_balance);
    }

    public function test_printable_wholesale_commercial_invoice_renders_with_official_requisites(): void
    {
        $sale = $this->orderService->createSale($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 5],
            ],
        ], null, $this->ownerA);

        $response = $this->actingAs($this->ownerA)->get(route('admin.wholesale.sales.print', $sale));

        $response->assertStatus(200);
        $response->assertSee('ՀԱՇԻՎ-ԱՊՐԱՆՔԱԳԻՐ');
        $response->assertSee('12345678'); // Supplier TIN
        $response->assertSee('02555555'); // Customer TIN
        $response->assertSee('1602'); // Classifier Code
        $response->assertSee($sale->invoice_number);
    }

    public function test_cross_tenant_isolation_protects_orders_and_sales(): void
    {
        app(WholesaleAccessService::class)->grantAccess($this->vendorB, $this->superadmin, null, 'Entitlement B');

        $orderA = $this->orderService->createOrder($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 2],
            ],
        ], $this->ownerA);

        // Tenant B cannot view Tenant A's order -> 404
        $response = $this->actingAs($this->ownerB)->get(route('admin.wholesale.orders.show', $orderA));
        $response->assertStatus(404);

        // Tenant B cannot confirm Tenant A's order -> 404
        $confirmResponse = $this->actingAs($this->ownerB)->post(route('admin.wholesale.orders.confirm', $orderA));
        $confirmResponse->assertStatus(404);
    }
}
