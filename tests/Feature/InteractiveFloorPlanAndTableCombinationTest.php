<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\ReservationAvailabilityService;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractiveFloorPlanAndTableCombinationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Location $location;

    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Armenian Table Bistro',
            'slug' => 'armenian-table-bistro',
            'email' => 'contact@tablebistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $this->plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'timezone' => 'Asia/Yerevan',
            'reservation_settings' => [
                'enabled' => true,
                'min_party_size' => 1,
                'max_party_size' => 20,
                'opening_time' => '10:00',
                'closing_time' => '23:00',
                'slot_interval_minutes' => 30,
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'advance_booking_days' => 30,
                'booking_cutoff_minutes' => 0,
                'operating_days' => [0, 1, 2, 3, 4, 5, 6],
            ],
        ]);

        $this->user = User::create([
            'name' => 'Bistro Manager',
            'email' => 'manager@tablebistro.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Yerevan',
            'slug' => 'downtown-yerevan',
            'address' => 'Abovyan 12',
            'is_active' => true,
            'table_count' => 8,
        ]);
    }

    public function test_floor_plan_uses_unified_restaurant_tables_as_single_source_of_truth(): void
    {
        // 1. Visit floor plan - auto-creates restaurant_tables if not existing
        $response = $this->actingAs($this->user)->get(route('admin.floor_plan.index'));
        $response->assertStatus(200);

        // Verify tables exist in PostgreSQL restaurant_tables table
        $tablesCount = RestaurantTable::where('vendor_id', $this->vendor->id)->count();
        $this->assertGreaterThan(0, $tablesCount);

        // 2. Query live status endpoint
        $liveResponse = $this->actingAs($this->user)->get(route('admin.floor_plan.live_status'));
        $liveResponse->assertStatus(200);
        $liveResponse->assertJson(['status' => 'success']);
        $this->assertCount($tablesCount, $liveResponse->json('tables'));
    }

    public function test_floor_plan_shows_in_place_real_time_order_and_reservation_status(): void
    {
        $table1 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '101',
            'capacity' => 4,
            'min_capacity' => 1,
            'section' => 'Main Hall',
            'shape' => 'round',
            'x' => 100,
            'y' => 100,
            'is_active' => true,
        ]);

        $table2 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '102',
            'capacity' => 6,
            'min_capacity' => 1,
            'section' => 'Main Hall',
            'shape' => 'rectangle',
            'x' => 300,
            'y' => 100,
            'is_active' => true,
        ]);

        $table3 = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '103',
            'capacity' => 2,
            'min_capacity' => 1,
            'section' => 'Main Hall',
            'shape' => 'square',
            'x' => 500,
            'y' => 100,
            'is_active' => true,
        ]);

        // Create an active order on Table 101
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '101',
            'order_number' => 'ORD-5501',
            'type' => 'dine_in',
            'status' => 'preparing',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 4500,
            'total_amount' => 4500,
            'customer_name' => 'Levon Ter-Petrosyan',
        ]);

        // Create an active reservation for today on Table 102
        $tz = $this->vendor->timezone ?: 'Asia/Yerevan';
        $resTime = Carbon::now($tz)->startOfDay()->addHours(19);
        $reservation = Reservation::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'restaurant_table_id' => $table2->id,
            'table_number' => '102',
            'customer_name' => 'Ani Grigoryan',
            'customer_email' => 'ani@grigoryan.am',
            'customer_phone' => '+37491123456',
            'guest_count' => 6,
            'reservation_date' => $resTime->format('Y-m-d'),
            'reservation_time' => $resTime->format('H:i'),
            'reservation_datetime' => $resTime->utc(),
            'duration_minutes' => 90,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        // Create a waiter call on Table 103
        WaiterCall::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '103',
            'type' => 'call_waiter',
            'status' => 'pending',
        ]);

        // Query live status
        $response = $this->actingAs($this->user)->getJson(route('admin.floor_plan.live_status'));
        $response->assertStatus(200);

        $tables = collect($response->json('tables'))->keyBy('number');

        // Table 101 must be 'occupied' with active order
        $t1 = $tables->get('101');
        $this->assertEquals('occupied', $t1['status']);
        $this->assertEquals('ORD-5501', $t1['order']['order_number']);
        $this->assertEquals(4500, $t1['order']['total_amount']);

        // Table 102 must be 'reserved' with reservation info
        $t2 = $tables->get('102');
        $this->assertEquals('reserved', $t2['status']);
        $this->assertEquals('Ani Grigoryan', $t2['reservation']['customer_name']);
        $this->assertEquals(6, $t2['reservation']['guest_count']);

        // Table 103 must be 'available' but has_waiter_call = true
        $t3 = $tables->get('103');
        $this->assertEquals('available', $t3['status']);
        $this->assertTrue($t3['has_waiter_call']);
    }

    public function test_vendor_can_add_edit_and_delete_tables_from_floor_plan(): void
    {
        // 1. Create table via floor-plan endpoint
        $storeResponse = $this->actingAs($this->user)->postJson(route('admin.floor_plan.tables.store'), [
            'table_number' => 'VIP-7',
            'name' => 'Presidential Table',
            'capacity' => 8,
            'min_capacity' => 4,
            'section' => 'VIP Lounge',
            'shape' => 'rectangle',
            'x' => 240,
            'y' => 180,
            'is_active' => true,
        ]);

        $storeResponse->assertStatus(200);
        $storeResponse->assertJson(['status' => 'success']);

        $table = RestaurantTable::where('vendor_id', $this->vendor->id)->where('table_number', 'VIP-7')->first();
        $this->assertNotNull($table);
        $this->assertEquals('rectangle', $table->shape);
        $this->assertEquals(8, $table->capacity);
        $this->assertEquals('VIP Lounge', $table->section);

        // 2. Edit table
        $updateResponse = $this->actingAs($this->user)->putJson(route('admin.floor_plan.tables.update', $table), [
            'table_number' => 'VIP-77',
            'name' => 'Royal Table',
            'capacity' => 12,
            'min_capacity' => 6,
            'section' => 'Royal Hall',
            'shape' => 'round',
            'is_active' => true,
        ]);

        $updateResponse->assertStatus(200);
        $table->refresh();
        $this->assertEquals('VIP-77', $table->table_number);
        $this->assertEquals(12, $table->capacity);
        $this->assertEquals('round', $table->shape);

        // 3. Delete table
        $deleteResponse = $this->actingAs($this->user)->deleteJson(route('admin.floor_plan.tables.destroy', $table));
        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('restaurant_tables', ['id' => $table->id]);
    }

    public function test_combining_and_uncombining_tables_for_large_reservations(): void
    {
        $tableA = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '3',
            'capacity' => 4,
            'min_capacity' => 1,
            'section' => 'Main Hall',
            'shape' => 'square',
            'x' => 100,
            'y' => 100,
            'is_active' => true,
        ]);

        $tableB = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'table_number' => '4',
            'capacity' => 6,
            'min_capacity' => 1,
            'section' => 'Main Hall',
            'shape' => 'square',
            'x' => 240,
            'y' => 100,
            'is_active' => true,
        ]);

        // Individual capacities: 4 and 6. Neither can seat a party of 10.
        $availabilityService = app(ReservationAvailabilityService::class);
        $targetDate = now()->addDays(2)->format('Y-m-d');

        $slotsBefore = $availabilityService->getAvailableTimeSlots($this->vendor, $targetDate, 10, $this->location);
        $hasSlotBefore = collect($slotsBefore)->contains(fn ($s) => $s['available'] === true);
        $this->assertFalse($hasSlotBefore, 'No table should fit 10 guests before merging.');

        // Combine Table 3 and Table 4
        $combineResponse = $this->actingAs($this->user)->postJson(route('admin.floor_plan.combine'), [
            'table_id_1' => $tableA->id,
            'table_id_2' => $tableB->id,
        ]);

        $combineResponse->assertStatus(200);
        $combineResponse->assertJson(['status' => 'success']);

        $tableA->refresh();
        $tableB->refresh();

        $this->assertTrue($tableA->isMerged());
        $this->assertTrue($tableB->isMerged());
        $this->assertEquals(10, $tableA->combined_capacity);
        $this->assertEquals(10, $tableB->combined_capacity);
        $this->assertEquals($tableB->id, $tableA->merged_with_id);

        // Now availability service MUST recognize the combined table for 10 guests!
        $slotsAfter = $availabilityService->getAvailableTimeSlots($this->vendor, $targetDate, 10, $this->location);
        $hasSlotAfter = collect($slotsAfter)->contains(fn ($s) => $s['available'] === true);
        $this->assertTrue($hasSlotAfter, 'Combined table must be available for 10 guests.');

        // Uncombine / separate tables
        $uncombineResponse = $this->actingAs($this->user)->postJson(route('admin.floor_plan.uncombine', $tableA));
        $uncombineResponse->assertStatus(200);

        $tableA->refresh();
        $tableB->refresh();
        $this->assertFalse($tableA->isMerged());
        $this->assertFalse($tableB->isMerged());
        $this->assertNull($tableA->combined_capacity);
    }

    public function test_vendor_can_manage_halls_add_rename_and_reassign(): void
    {
        $saveHallsResponse = $this->actingAs($this->user)->postJson(route('admin.floor_plan.halls.save'), [
            'halls' => [
                ['id' => 'patio', 'name' => 'Outdoor Patio', 'color' => '#10b981'],
                ['id' => 'wine-cellar', 'name' => 'Wine Cellar', 'color' => '#8b5cf6'],
            ],
        ]);

        $saveHallsResponse->assertStatus(200);
        $saveHallsResponse->assertJson(['status' => 'success']);

        $this->vendor->refresh();
        $floorPlan = $this->vendor->getFloorPlanData();
        $this->assertCount(2, $floorPlan['halls']);
        $this->assertEquals('Outdoor Patio', $floorPlan['halls'][0]['name']);
        $this->assertEquals('#10b981', $floorPlan['halls'][0]['color']);
    }

    public function test_floor_plan_translations_render_correctly(): void
    {
        // 1. Armenian locale
        session(['admin_locale' => 'hy']);
        $responseHy = $this->actingAs($this->user)->get(route('admin.floor_plan.index'));
        $responseHy->assertStatus(200);
        $responseHy->assertSee('Սեղանների Ինտերակտիվ Քարտեզ');
        $responseHy->assertSee('Միացնել սեղաններ');
        $responseHy->assertSee('Սրահներ');

        // 2. Russian locale
        session(['admin_locale' => 'ru']);
        $responseRu = $this->actingAs($this->user)->get(route('admin.floor_plan.index'));
        $responseRu->assertStatus(200);
        $responseRu->assertSee('Интерактивная Карта Зала');
        $responseRu->assertSee('Объединить столы');
        $responseRu->assertSee('Залы');
    }
}
