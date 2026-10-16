<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderChannelProductFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Category $category;

    protected Product $dineInOnlyProduct;

    protected Product $deliveryOnlyProduct;

    protected Product $takeawayOnlyProduct;

    protected Product $omnichannelProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Gourmet House',
            'slug' => 'gourmet-house',
            'email' => 'gourmet@example.com',
            'currency' => 'AMD',
            'subscription_plan' => 'pro',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'dine_in_enabled' => true,
            'takeaway_enabled' => true,
            'delivery_enabled' => true,
            'delivery_fee' => 1200.00,
            'takeaway_min_amount' => 0,
        ]);

        User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'email' => 'owner@gourmet.am',
            'role' => 'vendor_owner',
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown',
            'address' => 'Amiryan 4',
            'phone' => '+37410000000',
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Specialties',
        ]);

        // 1. Dine-In Only
        $this->dineInOnlyProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Flaming Steak (Dine-In Only)',
            'price' => 7500,
            'is_active' => true,
            'available_for_dine_in' => true,
            'available_for_takeaway' => false,
            'available_for_delivery' => false,
        ]);

        // 2. Delivery Only
        $this->deliveryOnlyProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Family Party Box (Delivery Only)',
            'price' => 12000,
            'is_active' => true,
            'available_for_dine_in' => false,
            'available_for_takeaway' => false,
            'available_for_delivery' => true,
        ]);

        // 3. Takeaway Only
        $this->takeawayOnlyProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Espresso To-Go (Takeaway Only)',
            'price' => 1000,
            'is_active' => true,
            'available_for_dine_in' => false,
            'available_for_takeaway' => true,
            'available_for_delivery' => false,
        ]);

        // 4. Omnichannel (All channels)
        $this->omnichannelProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Signature Burger',
            'price' => 3500,
            'is_active' => true,
            'available_for_dine_in' => true,
            'available_for_takeaway' => true,
            'available_for_delivery' => true,
        ]);
    }

    public function test_direct_storefront_visit_renders_selection_modal_and_channel_toggle(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('id="order-type-modal"', false);
        $response->assertSee('id="storefront-order-type-toggle"', false);
        $response->assertSee('isTableFixed: false', false);

        // Verify catalog data sent to frontend includes the 3 availability flags
        $response->assertSee('available_for_dine_in');
        $response->assertSee('available_for_takeaway');
        $response->assertSee('available_for_delivery');
    }

    public function test_qr_scan_table_visit_locks_to_dine_in_mode_and_displays_table_badge(): void
    {
        $response = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'table' => '12',
        ]));

        $response->assertStatus(200);
        $response->assertSee('isTableFixed: true', false);
        $response->assertSee("orderType: 'dine_in'", false);
        $response->assertSee('Table 12');
    }

    public function test_direct_visit_clears_stale_table_from_session(): void
    {
        $tableSessionKey = 'storefront_table_'.$this->vendor->slug;

        // First visit scanning table 5
        $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'table' => '5',
        ]))->assertSessionHas($tableSessionKey, '5');

        // Subsequent direct visit without ?table=
        $response = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSessionMissing($tableSessionKey);
        $response->assertSee('isTableFixed: false', false);
    }

    public function test_submitting_delivery_order_rejects_products_unavailable_for_delivery(): void
    {
        // 1. Trying to order dine-in only product for delivery
        $responseDineInOnly = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'type' => 'delivery',
            'customer_name' => 'Anna',
            'customer_phone' => '091000001',
            'delivery_address' => 'Baghramyan 24',
            'items' => [
                ['product_id' => $this->dineInOnlyProduct->id, 'quantity' => 1],
            ],
        ]);

        $responseDineInOnly->assertStatus(422);
        $responseDineInOnly->assertJsonValidationErrors(['items']);

        // 2. Ordering delivery-eligible products succeeds
        $responseValid = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'type' => 'delivery',
            'customer_name' => 'Anna',
            'customer_phone' => '091000001',
            'delivery_address' => 'Baghramyan 24',
            'items' => [
                ['product_id' => $this->deliveryOnlyProduct->id, 'quantity' => 1],
                ['product_id' => $this->omnichannelProduct->id, 'quantity' => 1],
            ],
        ]);

        $responseValid->assertStatus(200);
    }

    public function test_submitting_takeaway_order_rejects_products_unavailable_for_takeaway(): void
    {
        // 1. Trying to order delivery-only product for takeaway
        $responseDeliveryOnly = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'type' => 'takeaway',
            'customer_name' => 'Tigran',
            'customer_phone' => '091000002',
            'items' => [
                ['product_id' => $this->deliveryOnlyProduct->id, 'quantity' => 1],
            ],
        ]);

        $responseDeliveryOnly->assertStatus(422);
        $responseDeliveryOnly->assertJsonValidationErrors(['items']);

        // 2. Ordering takeaway-eligible products succeeds
        $responseValid = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'type' => 'takeaway',
            'customer_name' => 'Tigran',
            'customer_phone' => '091000002',
            'items' => [
                ['product_id' => $this->takeawayOnlyProduct->id, 'quantity' => 1],
                ['product_id' => $this->omnichannelProduct->id, 'quantity' => 1],
            ],
        ]);

        $responseValid->assertStatus(200);
    }

    public function test_submitting_dine_in_order_rejects_products_unavailable_for_dine_in(): void
    {
        // 1. Trying to order takeaway-only or delivery-only product for dine-in
        $responseTakeawayOnly = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'type' => 'dine_in',
            'table_number' => '4',
            'customer_name' => 'Karen',
            'items' => [
                ['product_id' => $this->takeawayOnlyProduct->id, 'quantity' => 1],
            ],
        ]);

        $responseTakeawayOnly->assertStatus(422);
        $responseTakeawayOnly->assertJsonValidationErrors(['items']);

        // 2. Ordering dine-in eligible products succeeds
        $responseValid = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'type' => 'dine_in',
            'table_number' => '4',
            'customer_name' => 'Karen',
            'items' => [
                ['product_id' => $this->dineInOnlyProduct->id, 'quantity' => 1],
                ['product_id' => $this->omnichannelProduct->id, 'quantity' => 1],
            ],
        ]);

        $responseValid->assertStatus(200);
    }

    public function test_all_themes_render_order_type_modal_and_filtering_logic(): void
    {
        $themeNames = [
            'modern-bistro' => 'Modern Bistro',
            'luxury-dark' => 'Luxury Dark',
            'vibrant-glass' => 'Vibrant Glass',
            'minimalist-light' => 'Minimalist Light',
        ];

        foreach ($themeNames as $slug => $name) {
            $tmpl = MenuTemplate::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true]
            );

            $this->vendor->update(['menu_template_id' => $tmpl->id]);

            $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

            $response->assertStatus(200);
            $response->assertSee('id="order-type-modal"', false);
            $response->assertSee('isCategoryVisible', false);
            $content = $response->getContent();
            $this->assertTrue(
                str_contains($content, 'isProductAvailableForCurrentOrderType') || str_contains($content, 'isDishVisible'),
                "Theme {$slug} must contain product channel visibility check"
            );
        }
    }
}
