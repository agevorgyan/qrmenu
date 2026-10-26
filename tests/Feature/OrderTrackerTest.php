<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackerTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Location $location;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Bistro Test',
            'slug' => 'bistro-test',
            'email' => 'bistro@test.com',
            'is_active' => true,
            'subscription_plan' => 'pro',
            'currency' => 'AMD',
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown',
            'slug' => 'downtown',
            'address' => '10 Abovyan St',
            'phone' => '+374 10 112233',
            'whatsapp_number' => '37491112233',
            'is_active' => true,
        ]);

        $category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Pizza',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Margherita Pizza',
            'price' => 3500,
            'is_available' => true,
        ]);
    }

    public function test_order_status_returns_progress_and_milestones(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-TEST-1001',
            'table_number' => 'Table 5',
            'type' => 'dine_in',
            'total_amount' => 3500,
            'status' => 'preparing',
            'customer_name' => 'Aram',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Margherita Pizza',
            'quantity' => 1,
            'unit_price' => 3500,
            'subtotal' => 3500,
        ]);

        $response = $this->getJson(route('client.order.status', [
            'vendor_slug' => $this->vendor->slug,
            'order_number' => $order->order_number,
            'token' => $order->tracking_token,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'order_number' => 'ORD-TEST-1001',
                    'table_number' => 'Table 5',
                    'status' => 'preparing',
                    'status_step' => 3,
                    'status_percent' => 75,
                    'total_amount' => 3500,
                    'currency' => '֏',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'order' => [
                    'id',
                    'order_number',
                    'table_number',
                    'type',
                    'status',
                    'status_step',
                    'status_percent',
                    'status_label',
                    'status_desc',
                    'status_icon',
                    'subtotal',
                    'service_fee',
                    'delivery_fee',
                    'total_amount',
                    'currency',
                    'created_at_human',
                    'created_at_time',
                    'updated_at_timestamp',
                    'items' => [
                        '*' => ['id', 'name', 'quantity', 'price', 'subtotal'],
                    ],
                ],
            ]);
    }

    public function test_order_status_returns_404_for_unknown_order(): void
    {
        $response = $this->getJson(route('client.order.status', [
            'vendor_slug' => $this->vendor->slug,
            'order_number' => 'NON-EXISTENT-999',
        ]));

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_order_status_isolated_from_other_vendors(): void
    {
        $otherVendor = Vendor::create([
            'name' => 'Other Cafe',
            'slug' => 'other-cafe',
            'email' => 'other@cafe.com',
            'is_active' => true,
        ]);

        $otherLocation = Location::create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch',
            'address' => '456 Other St',
        ]);

        $order = Order::create([
            'vendor_id' => $otherVendor->id,
            'location_id' => $otherLocation->id,
            'order_number' => 'ORD-OTHER-555',
            'type' => 'dine_in',
            'total_amount' => 1200,
            'status' => 'pending',
        ]);

        // Attempting to query other vendor's order through bistro-test slug must return 404
        $response = $this->getJson(route('client.order.status', [
            'vendor_slug' => $this->vendor->slug,
            'order_number' => $order->order_number,
        ]));

        $response->assertStatus(404);
    }

    public function test_submit_order_response_returns_tracking_metadata(): void
    {
        $response = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'table_number' => 'Table 7',
            'type' => 'dine_in',
            'customer_name' => 'Ani',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'pending',
                'total_amount' => 3500,
            ])
            ->assertJsonStructure([
                'success',
                'order_number',
                'order_id',
                'type',
                'table_number',
                'status',
                'status_label',
                'subtotal',
                'service_fee',
                'delivery_fee',
                'total_amount',
                'whatsapp_url',
            ]);
    }

    public function test_storefront_menu_renders_bottom_navigation_bar_and_tracker(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertStatus(200);
        $response->assertSee('storefront-bottom-nav', false);
        $response->assertSee('showOrderTracker', false);
        $response->assertSee('showInfoModal', false);
        $response->assertSee('tracker-step', false);
        $response->assertSee('has-active-order-pill', false);
        $response->assertSee('scheduleCompletedOrderDismissal', false);
        $response->assertSee('getActiveOrderServiceFee', false);
        $response->assertSee('getActiveOrderDeliveryFee', false);
    }

    public function test_order_status_returns_service_fee_and_delivery_fee_breakdown(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-FEES-101',
            'table_number' => 'Table 3',
            'type' => 'dine_in',
            'subtotal' => 7000,
            'service_fee' => 700,
            'delivery_fee' => 0,
            'total_amount' => 7700,
            'status' => 'completed',
            'customer_name' => 'Avetis',
        ]);

        $response = $this->getJson(route('client.order.status', [
            'vendor_slug' => $this->vendor->slug,
            'order_number' => $order->order_number,
            'token' => $order->tracking_token,
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'order_number' => 'ORD-FEES-101',
                    'status' => 'completed',
                    'subtotal' => 7000,
                    'service_fee' => 700,
                    'delivery_fee' => 0,
                    'total_amount' => 7700,
                ],
            ]);
    }

    public function test_branding_customizer_renders_split_screen_with_phone_mockup(): void
    {
        $user = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.branding.index'));

        $response->assertStatus(200);
        $response->assertSee('Split-Screen Live Customizer');
        $response->assertSee('phone-mockup-frame', false);
        $response->assertSee('id="previewIframe"', false);
        $response->assertSee('btnDeviceMobile', false);
    }
}
