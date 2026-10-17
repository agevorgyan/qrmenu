<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Events\WaiterCalled;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected Product $product;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Broadcast Test Bistro',
            'slug' => 'broadcast-test-bistro',
            'email' => 'broadcast@test.com',
            'password' => bcrypt('password123'),
        ]);

        $this->user = User::create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Central',
            'slug' => 'central',
            'address' => 'Freedom Sq',
        ]);

        $category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Courses',
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Steak',
            'price' => 7000,
        ]);
    }

    public function test_submitting_order_dispatches_order_created_broadcast_event(): void
    {
        Event::fake([OrderCreated::class]);

        $response = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'table_number' => 'Table 5',
            'type' => 'dine_in',
            'customer_name' => 'Tigran',
            'customer_phone' => '099112233',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) {
            $channels = $event->broadcastOn();
            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertSame('private-vendor.'.$this->vendor->id, $channels[0]->name);
            $this->assertSame($this->vendor->id, $event->order->vendor_id);
            $this->assertSame('Table 5', $event->order->table_number);

            return true;
        });
    }

    public function test_calling_waiter_dispatches_waiter_called_broadcast_event(): void
    {
        Event::fake([WaiterCalled::class]);

        $response = $this->postJson(route('client.waiter.call', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->location->id,
            'table_number' => 'Table 14',
            'type' => 'bill_card',
            'notes' => 'Please bring pos terminal',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(WaiterCalled::class, function (WaiterCalled $event) {
            $channels = $event->broadcastOn();
            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertSame('private-vendor.'.$this->vendor->id, $channels[0]->name);
            $this->assertSame('Table 14', $event->waiterCall->table_number);
            $this->assertSame('bill_card', $event->waiterCall->type);

            return true;
        });
    }

    public function test_updating_order_status_dispatches_order_status_updated_event(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-WS-99',
            'table_number' => 'T2',
            'type' => 'dine_in',
            'total_amount' => 7000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('admin.orders.status', ['order' => $order->id]), [
                'status' => 'preparing',
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order) {
            $channels = $event->broadcastOn();
            $this->assertCount(2, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertSame('private-vendor.'.$this->vendor->id, $channels[0]->name);
            $this->assertInstanceOf(Channel::class, $channels[1]);
            $this->assertSame('order.'.$order->order_number, $channels[1]->name);
            $this->assertSame('preparing', $event->order->status);

            return true;
        });
    }

    public function test_vendor_channel_authorization_enforces_tenant_isolation(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'testkey',
            'broadcasting.connections.reverb.secret' => 'testsecret',
            'broadcasting.connections.reverb.app_id' => 'testid',
        ]);

        // Register channel route definitions on newly active driver
        require base_path('routes/channels.php');

        $otherVendor = Vendor::create([
            'name' => 'Other Bistro',
            'slug' => 'other-bistro',
            'email' => 'other@bistro.com',
            'password' => bcrypt('password123'),
        ]);

        // User belongs to $this->vendor
        // Authorizing to own vendor channel should succeed
        $responseOwn = $this->actingAs($this->user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-vendor.'.$this->vendor->id,
                'socket_id' => '1234.5678',
            ]);
        $responseOwn->assertStatus(200);

        // Authorizing to another vendor's channel should be forbidden (403)
        $responseOther = $this->actingAs($this->user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-vendor.'.$otherVendor->id,
                'socket_id' => '1234.5678',
            ]);
        $responseOther->assertStatus(403);
    }
}
