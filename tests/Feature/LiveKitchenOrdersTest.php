<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveKitchenOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_vendor_can_fetch_live_kitchen_feed(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'type' => 'restaurant',
            'email' => 'pizza@house.am',
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $loc = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Main',
            'slug' => 'main',
            'table_count' => 10,
            'is_active' => true,
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'location_id' => $loc->id,
            'name' => 'Chef Mario',
            'email' => 'mario@pizza.am',
            'password' => bcrypt('password'),
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'location_id' => $loc->id,
            'order_number' => 'ORD-999',
            'table_number' => 'Table 5',
            'type' => 'dine_in',
            'total_amount' => 5000,
            'status' => 'pending',
            'customer_name' => 'Arthur',
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.orders.feed', [
            'last_order_id' => 0,
        ]));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'latest_order_id' => $order->id,
            'pending_count' => 1,
            'has_new' => false,
        ]);
        $this->assertStringContainsString('ORD-999', $response->json('html'));
        $this->assertStringContainsString('Table 5', $response->json('html'));
    }

    public function test_vendor_cannot_update_order_of_another_vendor(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor1 = Vendor::create([
            'name' => 'Vendor 1',
            'slug' => 'vendor-1',
            'type' => 'restaurant',
            'email' => 'v1@test.am',
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $vendor2 = Vendor::create([
            'name' => 'Vendor 2',
            'slug' => 'vendor-2',
            'type' => 'restaurant',
            'email' => 'v2@test.am',
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $user1 = User::create([
            'vendor_id' => $vendor1->id,
            'name' => 'User 1',
            'email' => 'u1@test.am',
            'password' => bcrypt('password'),
            'role' => 'manager',
        ]);

        $loc2 = Location::create([
            'vendor_id' => $vendor2->id,
            'name' => 'Main 2',
            'slug' => 'main-2',
            'table_count' => 5,
            'is_active' => true,
        ]);

        $orderOfVendor2 = Order::create([
            'vendor_id' => $vendor2->id,
            'location_id' => $loc2->id,
            'order_number' => 'ORD-V2',
            'table_number' => 'Table 1',
            'type' => 'dine_in',
            'total_amount' => 3000,
            'status' => 'pending',
        ]);

        // Attempt IDOR update - with TenantScope route model binding returns 404, preventing cross-tenant leakage
        $response = $this->actingAs($user1)->postJson(route('admin.orders.status', $orderOfVendor2->id), [
            'status' => 'preparing',
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404]), "Expected 403 or 404, got {$response->status()}");
    }
}
