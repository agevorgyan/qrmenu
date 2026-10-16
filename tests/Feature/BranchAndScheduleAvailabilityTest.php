<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAndScheduleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private User $user;

    private Location $locationA;

    private Location $locationB;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Multi-Branch Bistro',
            'slug' => 'multi-branch-bistro',
            'email' => 'bistro@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $this->user = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Kentron Branch',
            'slug' => 'kentron-branch',
            'address' => 'Amiryan 1',
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Arabkir Branch',
            'slug' => 'arabkir-branch',
            'address' => 'Komitas 15',
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Courses',
        ]);
    }

    public function test_stock_toggle_in_one_branch_does_not_affect_other_branch(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Club Sandwich',
            'price' => 2500,
            'is_available' => true,
        ]);

        // Initially available in both branches
        $this->assertTrue($product->isAvailableAtLocation($this->locationA->id));
        $this->assertTrue($product->isAvailableAtLocation($this->locationB->id));

        // Toggle stock status in Location A to out of stock
        $response = $this->actingAs($this->user)
            ->post(route('admin.menu.products.toggle', $product), [
                'location_id' => $this->locationA->id,
            ]);

        $response->assertRedirect();

        $product->refresh();

        // Location A must now be OUT OF STOCK
        $this->assertFalse($product->isAvailableAtLocation($this->locationA->id));

        // Location B must still be IN STOCK (not affected!)
        $this->assertTrue($product->isAvailableAtLocation($this->locationB->id));

        // Attempting to order at Location A should fail
        $orderResA = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '5',
            'type' => 'dine_in',
            'customer_name' => 'Anna',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $orderResA->assertStatus(422);
        $orderResA->assertJsonValidationErrors(['items']);

        // Ordering at Location B should succeed
        $orderResB = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationB->id,
            'table_number' => '10',
            'type' => 'dine_in',
            'customer_name' => 'Anna',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $orderResB->assertStatus(200);
        $this->assertTrue($orderResB->json('success'));
    }

    public function test_time_based_availability_schedule_enforcement(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Business Lunch',
            'price' => 1800,
            'is_available' => true,
            'available_start_time' => '11:00',
            'available_end_time' => '14:00',
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);

        // Case 1: Monday at 12:30 (inside lunch window)
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:30:00', 'Asia/Yerevan')); // Monday
        $this->assertTrue($product->isTimeAvailable());

        $validRes = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '1',
            'type' => 'dine_in',
            'customer_name' => 'Karen',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $validRes->assertStatus(200);
        $this->assertTrue($validRes->json('success'));

        // Case 2: Monday at 14:15 (past 14:00 lunch window)
        Carbon::setTestNow(Carbon::parse('2026-09-28 14:15:00', 'Asia/Yerevan')); // Monday afternoon
        $this->assertFalse($product->isTimeAvailable());

        $expiredRes = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '1',
            'type' => 'dine_in',
            'customer_name' => 'Karen',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $expiredRes->assertStatus(422);
        $expiredRes->assertJsonValidationErrors(['items']);

        // Case 3: Sunday at 12:30 (excluded day)
        Carbon::setTestNow(Carbon::parse('2026-09-27 12:30:00', 'Asia/Yerevan')); // Sunday
        $this->assertFalse($product->isTimeAvailable());

        Carbon::setTestNow(); // reset
    }

    public function test_order_channel_availability_enforcement(): void
    {
        // Dish restricted to Dine-in ONLY
        $dineInProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Hot Sizzling Fajita',
            'price' => 4500,
            'available_for_dine_in' => true,
            'available_for_takeaway' => false,
            'available_for_delivery' => false,
        ]);

        // Attempt delivery order with dine-in only dish -> fails
        $deliveryFail = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'type' => 'delivery',
            'delivery_address' => 'Baghramyan 24, apt 12',
            'customer_name' => 'Sona',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $dineInProduct->id, 'quantity' => 1],
            ],
        ]);
        $deliveryFail->assertStatus(422);
        $deliveryFail->assertJsonValidationErrors(['items']);

        // Dine-in order with dine-in only dish -> succeeds
        $dineInSuccess = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '7',
            'type' => 'dine_in',
            'customer_name' => 'Sona',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $dineInProduct->id, 'quantity' => 1],
            ],
        ]);
        $dineInSuccess->assertStatus(200);

        // Dish restricted to Delivery ONLY
        $deliveryProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Family Delivery Combo',
            'price' => 9000,
            'available_for_dine_in' => false,
            'available_for_takeaway' => true,
            'available_for_delivery' => true,
        ]);

        // Dine-in order with delivery combo -> fails
        $dineInComboFail = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '7',
            'type' => 'dine_in',
            'customer_name' => 'Sona',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $deliveryProduct->id, 'quantity' => 1],
            ],
        ]);
        $dineInComboFail->assertStatus(422);
        $dineInComboFail->assertJsonValidationErrors(['items']);
    }

    public function test_product_creation_and_update_with_branch_and_schedule_settings(): void
    {
        // Vendor creates a product assigned only to Location A
        $createRes = $this->actingAs($this->user)->post(route('admin.menu.products.store'), [
            'category_id' => $this->category->id,
            'name' => 'Branch Specific Burger',
            'price' => 3000,
            'is_available' => true,
            'available_for_dine_in' => true,
            'available_for_takeaway' => true,
            'available_for_delivery' => false,
            'available_start_time' => '12:00',
            'available_end_time' => '23:00',
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
            'locations' => [$this->locationA->id], // only Location A
        ]);

        $createRes->assertRedirect();

        $product = Product::where('name', 'Branch Specific Burger')->first();
        $this->assertNotNull($product);
        $this->assertEquals('12:00', $product->available_start_time);
        $this->assertEquals('23:00', $product->available_end_time);
        $this->assertFalse($product->available_for_delivery);
        $this->assertTrue($product->available_for_dine_in);

        // Location A is available, Location B is not available
        $this->assertTrue($product->isAvailableAtLocation($this->locationA->id));
        $this->assertFalse($product->isAvailableAtLocation($this->locationB->id));

        // Now update product to make it available in both locations
        $updateRes = $this->actingAs($this->user)->post(route('admin.menu.products.update', $product), [
            'category_id' => $this->category->id,
            'name' => 'Branch Specific Burger (Updated)',
            'price' => 3200,
            'is_available' => true,
            'available_for_dine_in' => true,
            'available_for_takeaway' => true,
            'available_for_delivery' => true,
            'locations' => [$this->locationA->id, $this->locationB->id],
        ]);

        $updateRes->assertRedirect();

        $product->refresh();
        $this->assertEquals(3200, (int) $product->price);
        $this->assertTrue($product->isAvailableAtLocation($this->locationA->id));
        $this->assertTrue($product->isAvailableAtLocation($this->locationB->id));
    }

    public function test_kitchen_operating_schedule_prevents_order_after_closing_time(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Late Night Steak',
            'price' => 5000,
            'is_available' => true,
        ]);

        // Configure vendor kitchen operating schedule: 10:00 to 23:00
        $this->vendor->update([
            'dine_in_schedule_enabled' => true,
            'dine_in_start_time' => '10:00',
            'dine_in_end_time' => '23:00',
            'dine_in_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'closing_warning_enabled' => true,
            'closing_warning_minutes' => 30,
            'closing_warning_message' => 'Խոհանոցը փակվում է 30 րոպեից',
        ]);

        // Case 1: At 22:15 (Kitchen is OPEN, closing in 45m -> no warning yet if warning threshold is 30m)
        Carbon::setTestNow(Carbon::parse('2026-09-28 22:15:00', 'Asia/Yerevan'));
        $this->assertTrue($this->vendor->isChannelOpen('dine_in', $this->locationA));

        $openRes = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '4',
            'type' => 'dine_in',
            'customer_name' => 'Armen',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $openRes->assertStatus(200);
        $this->assertTrue($openRes->json('success'));

        // Case 2: At 22:40 (Kitchen is OPEN, closing in 20m -> warning active)
        Carbon::setTestNow(Carbon::parse('2026-09-28 22:40:00', 'Asia/Yerevan'));
        $notice = $this->vendor->getClosingNotice('dine_in', $this->locationA);
        $this->assertTrue($notice['enabled']);
        $this->assertTrue($notice['closing_soon']);
        $this->assertEquals(20, $notice['minutes_left']);
        $this->assertFalse($notice['is_closed']);

        // Order is still accepted before 23:00
        $warningPeriodRes = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '4',
            'type' => 'dine_in',
            'customer_name' => 'Armen',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $warningPeriodRes->assertStatus(200);

        // Case 3: At 23:01 (Kitchen is CLOSED -> orders rejected)
        Carbon::setTestNow(Carbon::parse('2026-09-28 23:01:00', 'Asia/Yerevan'));
        $this->assertFalse($this->vendor->isChannelOpen('dine_in', $this->locationA));

        $closedNotice = $this->vendor->getClosingNotice('dine_in', $this->locationA);
        $this->assertTrue($closedNotice['is_closed']);

        $closedRes = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '4',
            'type' => 'dine_in',
            'customer_name' => 'Armen',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $closedRes->assertStatus(422);
        $closedRes->assertJsonValidationErrors(['type']);

        Carbon::setTestNow();
    }

    public function test_delivery_and_takeaway_independent_operating_schedules(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Pizza Margherita',
            'price' => 3500,
            'is_available' => true,
        ]);

        $this->vendor->update([
            'dine_in_schedule_enabled' => false,
            'delivery_schedule_enabled' => true,
            'delivery_start_time' => '11:00',
            'delivery_end_time' => '21:00',
            'delivery_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'takeaway_schedule_enabled' => true,
            'takeaway_start_time' => '10:00',
            'takeaway_end_time' => '22:00',
            'takeaway_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
        ]);

        // At 21:30 Monday: Delivery is CLOSED, but Takeaway and Dine-in are OPEN
        Carbon::setTestNow(Carbon::parse('2026-09-28 21:30:00', 'Asia/Yerevan'));
        $this->assertFalse($this->vendor->isChannelOpen('delivery', $this->locationA));
        $this->assertTrue($this->vendor->isChannelOpen('takeaway', $this->locationA));
        $this->assertTrue($this->vendor->isChannelOpen('dine_in', $this->locationA));

        // Delivery fails
        $delivRes = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'type' => 'delivery',
            'delivery_address' => 'Tumanyan 10',
            'customer_name' => 'Davit',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $delivRes->assertStatus(422);
        $delivRes->assertJsonValidationErrors(['type']);

        // Takeaway succeeds
        $takeawayRes = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'type' => 'takeaway',
            'customer_name' => 'Davit',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $takeawayRes->assertStatus(200);

        Carbon::setTestNow();
    }

    public function test_admin_settings_updates_operating_schedules(): void
    {
        $res = $this->actingAs($this->user)->post(route('admin.settings.update'), [
            'location_id' => $this->locationA->id,
            'name' => 'Updated Multi-Branch Bistro',
            'email' => 'bistro@example.com',
            'currency' => 'AMD',
            'service_fee_type' => 'percent',
            'service_fee_value' => '10',
            'delivery_fee' => '500',
            'delivery_min_amount' => '2000',
            'dine_in_schedule_enabled' => '1',
            'dine_in_start_time' => '09:30',
            'dine_in_end_time' => '23:30',
            'dine_in_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
            'delivery_schedule_enabled' => '1',
            'delivery_start_time' => '11:00',
            'delivery_end_time' => '22:00',
            'delivery_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'takeaway_schedule_enabled' => '1',
            'takeaway_start_time' => '10:00',
            'takeaway_end_time' => '23:00',
            'takeaway_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'closing_warning_enabled' => '1',
            'closing_warning_minutes' => 45,
            'closing_warning_message' => 'Խոհանոցը փակվում է 45 րոպեից',
        ]);

        $res->assertRedirect();

        $this->locationA->refresh();
        $this->assertTrue((bool) $this->locationA->dine_in_schedule_enabled);
        $this->assertEquals('09:30', $this->locationA->dine_in_start_time);
        $this->assertEquals('23:30', $this->locationA->dine_in_end_time);
        $this->assertEquals(45, (int) $this->locationA->closing_warning_minutes);
        $this->assertEquals('Խոհանոցը փակվում է 45 րոպեից', $this->locationA->closing_warning_message);

        // Branch B must NOT be affected!
        $this->locationB->refresh();
        $this->assertFalse((bool) $this->locationB->dine_in_schedule_enabled);
    }

    public function test_branch_specific_operating_schedules_are_completely_isolated(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Signature Burger',
            'price' => 3000,
            'is_available' => true,
        ]);

        // Branch A: Kitchen closes at 22:00
        $this->locationA->update([
            'dine_in_schedule_enabled' => true,
            'dine_in_start_time' => '10:00',
            'dine_in_end_time' => '22:00',
            'dine_in_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
        ]);

        // Branch B: Kitchen closes late at 02:00
        $this->locationB->update([
            'dine_in_schedule_enabled' => true,
            'dine_in_start_time' => '12:00',
            'dine_in_end_time' => '02:00',
            'dine_in_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
        ]);

        // Test at 22:30 Monday: Branch A is CLOSED, Branch B is OPEN
        Carbon::setTestNow(Carbon::parse('2026-09-28 22:30:00', 'Asia/Yerevan'));

        $this->assertFalse($this->locationA->isChannelOpen('dine_in'));
        $this->assertTrue($this->locationB->isChannelOpen('dine_in'));

        // Ordering at Branch A fails
        $orderResA = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '1',
            'type' => 'dine_in',
            'customer_name' => 'Armen',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $orderResA->assertStatus(422);
        $orderResA->assertJsonValidationErrors(['type']);

        // Ordering at Branch B succeeds
        $orderResB = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationB->id,
            'table_number' => '5',
            'type' => 'dine_in',
            'customer_name' => 'Armen',
            'customer_phone' => '091223344',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $orderResB->assertStatus(200);
        $this->assertTrue($orderResB->json('success'));

        Carbon::setTestNow();
    }

    public function test_product_schedule_availability_renders_in_menu_with_ordering_disabled_outside_hours(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Breakfast Special',
            'price' => 2000,
            'is_available' => true,
            'available_start_time' => '10:00',
            'available_end_time' => '14:00',
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);

        // 1. Outside hours: Monday at 16:00
        Carbon::setTestNow(Carbon::parse('2026-09-28 16:00:00', 'Asia/Yerevan'));
        $this->assertFalse($product->isTimeAvailable());

        $storefrontResponse = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'lang' => 'hy',
        ]));

        $storefrontResponse->assertStatus(200);
        // Product MUST still be visible on page
        $storefrontResponse->assertSee('Breakfast Special');
        // Schedule notice MUST be visible
        $storefrontResponse->assertSee('10:00 - 14:00');
        $storefrontResponse->assertSee('Կարող եք պատվիրել միայն');

        // Ordering at 16:00 is rejected
        $orderReject = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '2',
            'type' => 'dine_in',
            'customer_name' => 'Tigran',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $orderReject->assertStatus(422);
        $orderReject->assertJsonValidationErrors(['items']);

        // 2. Inside hours: Monday at 11:30
        Carbon::setTestNow(Carbon::parse('2026-09-28 11:30:00', 'Asia/Yerevan'));
        $this->assertTrue($product->isTimeAvailable());

        $orderSuccess = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor->slug]), [
            'location_id' => $this->locationA->id,
            'table_number' => '2',
            'type' => 'dine_in',
            'customer_name' => 'Tigran',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $orderSuccess->assertStatus(200);

        Carbon::setTestNow();
    }
}
