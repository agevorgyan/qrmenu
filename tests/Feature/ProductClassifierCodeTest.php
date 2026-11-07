<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\DTOs\OrderItemDTO;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosPayment;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Fiscal\Adapters\ArmenianHdmFiscalAdapter;
use App\Services\Fiscal\Adapters\MockFiscalAdapter;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductClassifierCodeTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected Category $foodCategory;

    protected Category $barCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Armenian Grill House',
            'slug' => 'armenian-grill',
            'email' => 'grill@restaurant.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown-branch',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Restaurant Manager',
            'email' => 'manager@restaurant.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->foodCategory = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Hot Dishes',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->barCategory = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bar & Cocktails (խմիչքներ)',
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    public function test_all_products_automatically_receive_feacn_classifier_code_on_creation(): void
    {
        // 1. Food dish without specifying classifier code -> defaults to '56.10'
        $foodDish = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Lamb Khorovats',
            'price' => 4500,
        ]);

        $this->assertNotNull($foodDish->classifier_code);
        $this->assertSame('56.10', $foodDish->classifier_code);
        $this->assertSame('56.10', $foodDish->atgaa_code);
        $this->assertSame('56.10', $foodDish->adg_code);

        // 2. Bar/beverage dish without specifying classifier code -> defaults to '56.30'
        $barDrink = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->barCategory->id,
            'name' => 'Areni Red Wine',
            'price' => 3000,
        ]);

        $this->assertNotNull($barDrink->classifier_code);
        $this->assertSame('56.30', $barDrink->classifier_code);
        $this->assertSame('56.30', $barDrink->atgaa_code);

        // 3. Dish with explicit 8-digit commodity code
        $seafoodDish = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Lake Sevan Trout',
            'price' => 6000,
            'classifier_code' => '0302.11.00',
        ]);

        $this->assertSame('0302.11.00', $seafoodDish->classifier_code);
        $this->assertSame('0302.11.00', $seafoodDish->atgaa_code);
    }

    public function test_admin_can_store_product_with_custom_atgaa_code(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('admin.menu.products.store'), [
            'category_id' => $this->foodCategory->id,
            'name' => 'Kebab with Lavash',
            'price' => 2800,
            'classifier_code' => '2106.90.98',
            'is_available' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $product = Product::where('vendor_id', $this->vendor->id)->where('name', 'Kebab with Lavash')->first();
        $this->assertNotNull($product);
        $this->assertSame('2106.90.98', $product->classifier_code);
        $this->assertSame('2106.90.98', $product->atgaa_code);
    }

    public function test_admin_can_update_product_atgaa_code(): void
    {
        $this->actingAs($this->owner);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Gata Traditional',
            'price' => 1200,
            'classifier_code' => '56.10',
        ]);

        $response = $this->post(route('admin.menu.products.update', $product), [
            'category_id' => $this->foodCategory->id,
            'name' => 'Gata Traditional',
            'price' => 1400,
            'classifier_code' => '1905.90.90',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('1905.90.90', $product->classifier_code);
    }

    public function test_admin_can_inline_update_classifier_code(): void
    {
        $this->actingAs($this->owner);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Khachapuri',
            'price' => 2500,
        ]);

        $response = $this->postJson(route('admin.menu.products.inline_update', $product), [
            'field' => 'classifier_code',
            'value' => '1905.90.10',
        ]);

        $response->assertOk();
        $product->refresh();
        $this->assertSame('1905.90.10', $product->classifier_code);
    }

    public function test_admin_can_bulk_update_classifier_code_across_multiple_products(): void
    {
        $this->actingAs($this->owner);

        $p1 = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Dish 1',
            'price' => 1000,
        ]);

        $p2 = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Dish 2',
            'price' => 2000,
        ]);

        $response = $this->postJson(route('admin.menu.products.bulk_update'), [
            'product_ids' => [$p1->id, $p2->id],
            'classifier_code' => '56.10',
        ]);

        $response->assertOk();
        $p1->refresh();
        $p2->refresh();
        $this->assertSame('56.10', $p1->classifier_code);
        $this->assertSame('56.10', $p2->classifier_code);
    }

    public function test_order_items_inherit_product_feacn_classifier_code(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Beef Khash',
            'price' => 3500,
            'classifier_code' => '0202.30.90',
        ]);

        $action = app(CreateOrderAction::class);
        $dto = new CreateOrderDTO(
            locationId: $this->location->id,
            type: 'dine_in',
            items: [
                new OrderItemDTO(
                    productId: $product->id,
                    quantity: 2
                ),
            ],
            tableNumber: '5'
        );

        $result = $action->execute($this->vendor, $dto);
        $order = $result['order'];

        $item = $order->items->first();
        $this->assertNotNull($item);
        $this->assertSame('0202.30.90', $item->classifier_code);
        $this->assertSame('0202.30.90', $item->atgaa_code);
    }

    public function test_armenian_hdm_fiscal_adapter_receives_adg_codes(): void
    {
        Http::fake([
            'http://127.0.0.1:8585/api/fiscal/receipt' => Http::response([
                'receipt_number' => 'HDM-12345',
                'qr_data' => 'https://tax.src.am/crn/check?crn=00812948',
            ], 200),
        ]);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Chicken Tabaka',
            'price' => 4000,
            'classifier_code' => '0207.14.00',
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-1001',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 4000,
            'total_amount' => 4000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'classifier_code' => $product->classifier_code,
            'unit_price' => 4000,
            'quantity' => 1,
            'subtotal' => 4000,
        ]);

        $payment = PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'user_id' => $this->owner->id,
            'payment_method' => 'card',
            'amount' => 4000,
            'status' => 'completed',
        ]);

        $adapter = new ArmenianHdmFiscalAdapter(
            ipAddress: '127.0.0.1',
            port: 8585,
            crn: '00812948',
            taxNumber: '01524311'
        );

        $result = $adapter->printReceipt($order->load('items.product'), $payment);

        $this->assertTrue($result->success);
        $this->assertSame('HDM-12345', $result->receiptNumber);

        Http::assertSent(function ($request) {
            $data = $request->data();
            $item = $data['items'][0] ?? null;

            return $item !== null
                && $item['adg_code'] === '0207.14.00'
                && $item['classifier_code'] === '0207.14.00'
                && $item['name'] === 'Chicken Tabaka';
        });
    }

    public function test_mock_fiscal_adapter_includes_classifier_codes_in_payload(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->foodCategory->id,
            'name' => 'Lavash Bread',
            'price' => 500,
            'classifier_code' => '1905.90.90',
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-1002',
            'type' => 'dine_in',
            'status' => 'confirmed',
            'subtotal' => 500,
            'total_amount' => 500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'classifier_code' => $product->classifier_code,
            'unit_price' => 500,
            'quantity' => 1,
            'subtotal' => 500,
        ]);

        $payment = PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'user_id' => $this->owner->id,
            'payment_method' => 'cash',
            'amount' => 500,
            'cash_tendered' => 1000,
            'status' => 'completed',
        ]);

        $adapter = new MockFiscalAdapter;
        $result = $adapter->printReceipt($order->load('items.product'), $payment);

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->fiscalQrData);
        $this->assertIsArray($result->rawPayload['items']);
        $this->assertSame('1905.90.90', $result->rawPayload['items'][0]['adg_code']);
        $this->assertSame('1905.90.90', $result->rawPayload['items'][0]['classifier_code']);
    }
}
