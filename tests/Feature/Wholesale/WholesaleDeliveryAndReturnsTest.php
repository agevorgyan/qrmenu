<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Domains\Wholesale\Services\WholesaleDeliveryService;
use App\Domains\Wholesale\Services\WholesaleOrderService;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleDeliveryAndReturnsTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

    protected Unit $unitKg;

    protected Warehouse $warehouseA;

    protected Ingredient $ingredientBeef;

    protected WholesaleSeller $sellerA;

    protected WholesaleCustomer $customerA;

    protected WholesaleProduct $productPelmeni;

    protected WholesaleOrderService $orderService;

    protected WholesaleDeliveryService $deliveryService;

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

        // Warehouse for Vendor A
        $this->warehouseA = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Central Cold Storage',
            'code' => 'WH-COLD-01',
            'address' => 'Yerevan, Sevan 12',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Ingredient in core inventory
        $this->ingredientBeef = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Frozen Pelmeni Bulk',
            'sku' => 'ING-PELM-BULK',
            'unit_id' => $this->unitKg->id,
            'current_cost' => 1100.00,
            'is_active' => true,
        ]);

        // Seed initial physical stock of 100 kg in the warehouse
        app(InventoryLedgerService::class)->recordReceipt(
            warehouse: $this->warehouseA,
            items: [
                [
                    'ingredient_id' => $this->ingredientBeef->id,
                    'quantity' => 100.0,
                    'unit_id' => $this->unitKg->id,
                    'unit_cost' => 1100.00,
                ],
            ],
            notes: 'Initial Wholesale Stocking'
        );

        // Seller
        $this->sellerA = WholesaleSeller::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Wholesale Foods LLC',
            'legal_name' => 'Alpha Wholesale Foods LLC',
            'tin' => '12345678',
            'tax_regime' => SellerTaxRegime::VAT_PAYER,
            'address' => 'Yerevan, Arshakunyats 15',
            'is_active' => true,
        ]);

        // Customer
        $this->customerA = WholesaleCustomer::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'SAS Supermarkets',
            'tin' => '02555555',
            'tax_regime' => 'vat_payer',
            'credit_limit' => 500000.00,
            'is_credit_allowed' => true,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        // Wholesale Product mapped explicitly to the core Ingredient
        $this->productPelmeni = WholesaleProduct::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Պելմենի Ֆիրմային (1կգ)',
            'sku' => 'PELM-FIRM-1000',
            'classifier_code' => '1602',
            'unit_id' => $this->unitKg->id,
            'base_wholesale_price' => 2000.00,
            'min_wholesale_price' => 1600.00,
            'cost_price' => 1100.00,
            'physical_stock_ingredient_id' => $this->ingredientBeef->id,
            'is_vat_applicable' => true,
            'vat_rate' => 20.00,
            'is_active' => true,
        ]);

        $this->orderService = app(WholesaleOrderService::class);
        $this->deliveryService = app(WholesaleDeliveryService::class);
    }

    public function test_can_plan_delivery_for_confirmed_order(): void
    {
        $order = $this->orderService->createOrder($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 25],
            ],
        ], $this->ownerA);

        $this->orderService->confirmOrder($order);

        $delivery = $this->deliveryService->planDelivery($order, [
            'warehouse_id' => $this->warehouseA->id,
            'driver_name' => 'Arthur Vardanyan',
            'driver_phone' => '+374 77 998877',
            'vehicle_number' => '11 AA 111',
            'notes' => 'Early morning delivery route #4',
        ], $this->ownerA);

        $this->assertEquals('PLANNED', $delivery->status);
        $this->assertStringStartsWith('WD-'.now()->format('Ymd'), $delivery->delivery_number);
        $this->assertEquals(1, $delivery->items->count());
        $this->assertEquals(25.0000, $delivery->items->first()->quantity_planned);
    }

    public function test_delivery_dispatch_and_confirmation_depletes_core_physical_inventory(): void
    {
        // Initial inventory balance is 100 kg
        $initialBalance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $this->ingredientBeef->id)
            ->first();
        $this->assertEquals(100.0000, (float) $initialBalance->quantity_on_hand);

        // Create and confirm order for 20 kg
        $order = $this->orderService->createOrder($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 20],
            ],
        ], $this->ownerA);
        $this->orderService->confirmOrder($order);

        // Plan and dispatch delivery
        $delivery = $this->deliveryService->planDelivery($order, [
            'warehouse_id' => $this->warehouseA->id,
            'driver_name' => 'Arthur Vardanyan',
        ], $this->ownerA);

        $this->deliveryService->dispatchDelivery($delivery);
        $this->assertEquals('DISPATCHED', $delivery->fresh()->status);

        // Confirm delivery with proof of delivery
        $this->deliveryService->confirmDelivery($delivery, [
            'received_by_name' => 'Store Manager Karen',
            'proof_of_delivery_note' => 'Goods received intact at Komitas SAS',
        ], $this->ownerA);

        $this->assertEquals('DELIVERED', $delivery->fresh()->status);
        $this->assertEquals('DELIVERED', $order->fresh()->status);
        $this->assertEquals(20.0000, (float) $order->fresh()->items->first()->quantity_delivered);

        // Verify Core Physical Inventory Depletion in Warehouse:
        // 100 kg - 20 kg = 80 kg on hand
        $updatedBalance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $this->ingredientBeef->id)
            ->first();

        $this->assertEquals(80.0000, (float) $updatedBalance->quantity_on_hand);

        // Verify Core Inventory Transaction recorded
        $tx = InventoryTransaction::where('vendor_id', $this->vendorA->id)
            ->where('reference_type', 'wholesale_delivery')
            ->where('reference_id', $delivery->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(InventoryTransaction::TYPE_ISSUE, $tx->type);
    }

    public function test_customer_return_restocks_physical_inventory_and_credits_ar(): void
    {
        // Starting balance is 100 kg
        // Set customer AR balance at 50,000 AMD
        $this->customerA->update(['current_balance' => 50000.00]);

        // Customer returns 10 kg of Pelmeni at 2,000 AMD/unit = 20,000 AMD credit
        $return = $this->deliveryService->processReturn($this->vendorA, [
            'customer_id' => $this->customerA->id,
            'warehouse_id' => $this->warehouseA->id,
            'reason' => 'Customer over-ordered',
            'action_type' => 'RESTOCK',
            'items' => [
                [
                    'product_id' => $this->productPelmeni->id,
                    'quantity' => 10,
                    'unit_price' => 2000.00,
                    'reason_detail' => 'Unopened master cartons in cold chain',
                ],
            ],
        ], $this->ownerA);

        $this->assertEquals('COMPLETED', $return->status);
        $this->assertStringStartsWith('WR-'.now()->format('Ymd'), $return->return_number);
        $this->assertEquals(20000.00, $return->total_return_amount);

        // Core inventory balance must increase: 100 kg + 10 kg = 110 kg
        $restockedBalance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $this->ingredientBeef->id)
            ->first();

        $this->assertEquals(110.0000, (float) $restockedBalance->quantity_on_hand);

        // Customer AR balance must be credited: 50,000 - 20,000 = 30,000 AMD
        $this->assertEquals(30000.00, (float) $this->customerA->fresh()->current_balance);
    }

    public function test_cross_tenant_isolation_protects_deliveries_and_returns(): void
    {
        app(WholesaleAccessService::class)->grantAccess($this->vendorB, $this->superadmin, null, 'Entitlement B');

        $orderA = $this->orderService->createOrder($this->vendorA, [
            'seller_id' => $this->sellerA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productPelmeni->id, 'quantity' => 5],
            ],
        ], $this->ownerA);
        $this->orderService->confirmOrder($orderA);

        $deliveryA = $this->deliveryService->planDelivery($orderA, [
            'warehouse_id' => $this->warehouseA->id,
        ], $this->ownerA);

        // Tenant B cannot view Tenant A's delivery -> 404
        $response = $this->actingAs($this->ownerB)->get(route('admin.wholesale.deliveries.show', $deliveryA));
        $response->assertStatus(404);

        // Tenant B cannot dispatch Tenant A's delivery -> 404
        $dispatchResponse = $this->actingAs($this->ownerB)->post(route('admin.wholesale.deliveries.dispatch', $deliveryA));
        $dispatchResponse->assertStatus(404);
    }
}
