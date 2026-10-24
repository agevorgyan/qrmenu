<?php

namespace Tests\Feature;

use App\Exceptions\QuotaExceededException;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TenantUsageEvent;
use App\Models\TenantUsageMeter;
use App\Models\User;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use App\Services\MenuManagementService;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuotaEnforcementAndMeteringTest extends TestCase
{
    use RefreshDatabase;

    protected QuotaService $quotaService;

    protected SubscriptionService $subscriptionService;

    protected MenuManagementService $menuService;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $businessPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quotaService = app(QuotaService::class);
        $this->subscriptionService = app(SubscriptionService::class);
        $this->menuService = app(MenuManagementService::class);

        // Seed plans with quota_limits
        $this->basicPlan = SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'price' => 9900,
            'currency' => 'AMD',
            'duration_days' => 30,
            'trial_days' => 14,
            'feature_codes' => ['menu.base', 'menu.translations', 'analytics.basic'],
            'quota_limits' => [
                QuotaCatalog::KEY_PRODUCTS_COUNT => 50,
                QuotaCatalog::KEY_CATEGORIES_COUNT => 10,
                QuotaCatalog::KEY_BRANCHES_COUNT => 1,
                QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => 2,
                QuotaCatalog::KEY_STORAGE_BYTES => 104857600,
                QuotaCatalog::KEY_ORDERS_PERIOD => 0,
                QuotaCatalog::KEY_RESERVATIONS_PERIOD => 0,
                QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 0,
                QuotaCatalog::KEY_API_REQUESTS_PERIOD => 0,
            ],
            'is_active' => true,
        ]);

        $this->proPlan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 19900,
            'currency' => 'AMD',
            'duration_days' => 30,
            'trial_days' => 14,
            'feature_codes' => [
                'menu.base',
                'menu.translations',
                'analytics.basic',
                'orders.online',
                'orders.delivery',
                'reservations.manage',
                'preorders.manage',
                'customers.crm',
                'ai.menu_builder',
                'ai.waiter',
                'branding.custom_domain',
            ],
            'quota_limits' => [
                QuotaCatalog::KEY_PRODUCTS_COUNT => 250,
                QuotaCatalog::KEY_CATEGORIES_COUNT => 30,
                QuotaCatalog::KEY_BRANCHES_COUNT => 1,
                QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => 5,
                QuotaCatalog::KEY_STORAGE_BYTES => 524288000,
                QuotaCatalog::KEY_ORDERS_PERIOD => 1000,
                QuotaCatalog::KEY_RESERVATIONS_PERIOD => 200,
                QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 150,
                QuotaCatalog::KEY_API_REQUESTS_PERIOD => 0,
            ],
            'is_active' => true,
        ]);

        $this->businessPlan = SubscriptionPlan::create([
            'name' => 'Business',
            'slug' => 'business',
            'price' => 39900,
            'currency' => 'AMD',
            'duration_days' => 30,
            'trial_days' => 14,
            'feature_codes' => [
                'menu.base',
                'menu.translations',
                'analytics.basic',
                'orders.online',
                'orders.delivery',
                'reservations.manage',
                'preorders.manage',
                'customers.crm',
                'branches.multiple',
                'team.manage',
                'analytics.advanced',
                'ai.menu_builder',
                'ai.waiter',
                'branding.custom_domain',
                'api.access',
            ],
            'quota_limits' => [
                QuotaCatalog::KEY_PRODUCTS_COUNT => QuotaCatalog::UNLIMITED,
                QuotaCatalog::KEY_CATEGORIES_COUNT => QuotaCatalog::UNLIMITED,
                QuotaCatalog::KEY_BRANCHES_COUNT => 10,
                QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => 25,
                QuotaCatalog::KEY_STORAGE_BYTES => 2147483648,
                QuotaCatalog::KEY_ORDERS_PERIOD => QuotaCatalog::UNLIMITED,
                QuotaCatalog::KEY_RESERVATIONS_PERIOD => QuotaCatalog::UNLIMITED,
                QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 1000,
                QuotaCatalog::KEY_API_REQUESTS_PERIOD => 10000,
            ],
            'is_active' => true,
        ]);
    }

    protected function createVendorWithPlan(SubscriptionPlan $plan, array $vendorAttrs = []): array
    {
        $vendor = Vendor::create(array_merge([
            'name' => 'Test Restaurant '.uniqid(),
            'slug' => 'test-vendor-'.uniqid(),
            'email' => 'vendor.'.uniqid().'@test.am',
            'is_active' => true,
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
        ], $vendorAttrs));

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Owner',
            'email' => 'owner.'.uniqid().'@test.am',
            'password' => Hash::make('secret123'),
            'role' => 'vendor_owner',
        ]);

        $sub = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $vendor->update(['current_subscription_id' => $sub->id]);

        return [$vendor, $user, $sub];
    }

    public function test_quota_catalog_keys_types_and_defaults(): void
    {
        $this->assertSame(QuotaCatalog::TYPE_RESOURCE, QuotaCatalog::getType(QuotaCatalog::KEY_PRODUCTS_COUNT));
        $this->assertSame(QuotaCatalog::TYPE_RESOURCE, QuotaCatalog::getType(QuotaCatalog::KEY_BRANCHES_COUNT));
        $this->assertSame(QuotaCatalog::TYPE_EVENT, QuotaCatalog::getType(QuotaCatalog::KEY_ORDERS_PERIOD));
        $this->assertSame(QuotaCatalog::TYPE_EVENT, QuotaCatalog::getType(QuotaCatalog::KEY_AI_REQUESTS_PERIOD));

        $this->assertSame(QuotaCatalog::KEY_PRODUCTS_COUNT, QuotaCatalog::normalize('products'));
        $this->assertSame(QuotaCatalog::KEY_BRANCHES_COUNT, QuotaCatalog::normalize('locations'));
        $this->assertSame(QuotaCatalog::KEY_ORDERS_PERIOD, QuotaCatalog::normalize('orders.monthly'));

        $basicDefaults = QuotaCatalog::getDefaultQuotasForPlan('basic');
        $this->assertSame(50, $basicDefaults[QuotaCatalog::KEY_PRODUCTS_COUNT]);
        $this->assertSame(1, $basicDefaults[QuotaCatalog::KEY_BRANCHES_COUNT]);

        $bizDefaults = QuotaCatalog::getDefaultQuotasForPlan('business');
        $this->assertSame(QuotaCatalog::UNLIMITED, $bizDefaults[QuotaCatalog::KEY_PRODUCTS_COUNT]);
        $this->assertSame(10, $bizDefaults[QuotaCatalog::KEY_BRANCHES_COUNT]);
    }

    public function test_requests_below_at_and_above_resource_limit_for_products(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);

        // Set quota override of 2 products for quick testing
        $this->quotaService->setOverride($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT, 2, 'Testing product quota');

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'Main Menu',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // 1st product creation: below limit -> SUCCESS
        $p1 = $this->menuService->createProduct($vendor, [
            'category_id' => $category->id,
            'name' => 'Dish 1',
            'price' => 2000,
        ]);
        $this->assertInstanceOf(Product::class, $p1);
        $status = $this->quotaService->getQuotaStatus($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT);
        $this->assertSame(1, $status->currentUsage);
        $this->assertSame(1, $status->remainingCapacity);
        $this->assertFalse($status->isExceeded);

        // 2nd product creation: at limit -> SUCCESS
        $p2 = $this->menuService->createProduct($vendor, [
            'category_id' => $category->id,
            'name' => 'Dish 2',
            'price' => 3000,
        ]);
        $this->assertInstanceOf(Product::class, $p2);
        $status = $this->quotaService->getQuotaStatus($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT);
        $this->assertSame(2, $status->currentUsage);
        $this->assertSame(0, $status->remainingCapacity);
        $this->assertTrue($status->isExceeded);

        // 3rd product creation: above limit -> THROWS QuotaExceededException
        $this->expectException(QuotaExceededException::class);
        $this->menuService->createProduct($vendor, [
            'category_id' => $category->id,
            'name' => 'Dish 3 (Exceeds Limit)',
            'price' => 4000,
        ]);
    }

    public function test_soft_deleted_products_free_capacity_and_allow_new_creation(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);
        $this->quotaService->setOverride($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT, 1, 'Limit to 1 product');

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'Starters',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $p1 = $this->menuService->createProduct($vendor, [
            'category_id' => $category->id,
            'name' => 'Dish 1',
            'price' => 1500,
        ]);

        $this->assertFalse($this->quotaService->can($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));

        // Soft delete the product
        $p1->delete();

        // Capacity is restored
        $this->assertTrue($this->quotaService->can($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));
        $this->assertSame(0, $this->quotaService->getCurrentUsage($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));

        // Creating a replacement product succeeds
        $p2 = $this->menuService->createProduct($vendor, [
            'category_id' => $category->id,
            'name' => 'Dish 2 (Replacement)',
            'price' => 1800,
        ]);
        $this->assertInstanceOf(Product::class, $p2);
        $this->assertSame(1, $this->quotaService->getCurrentUsage($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));
    }

    public function test_unlimited_quotas_allow_unrestricted_creation(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->businessPlan);

        $status = $this->quotaService->getQuotaStatus($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT);
        $this->assertTrue($status->isUnlimited);
        $this->assertSame(QuotaCatalog::UNLIMITED, $status->limit);
        $this->assertTrue($this->quotaService->can($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT, 1000));
    }

    public function test_branches_and_team_members_quota_enforcement(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->businessPlan);

        // Branch quota override to 1 branch
        $this->quotaService->setOverride($vendor, QuotaCatalog::KEY_BRANCHES_COUNT, 1, 'Limit to 1 location');

        Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Main Location',
            'slug' => 'main-loc',
            'table_count' => 10,
            'is_active' => true,
        ]);

        // Attempting to add a second location via VendorAdminController route is blocked by branch quota
        $response = $this->actingAs($user)->post(route('admin.locations.store'), [
            'name' => 'Second Location',
            'table_count' => 15,
        ]);

        // Fails with redirect to subscription with warning
        $response->assertRedirect(route('admin.subscription'));
        $response->assertSessionHas('warning');
        $this->assertSame(1, Location::where('vendor_id', $vendor->id)->count());

        // Team members quota: Business allows 25 members. Let's set override to 2 (including owner)
        $this->quotaService->setOverride($vendor, QuotaCatalog::KEY_TEAM_MEMBERS_COUNT, 2, 'Limit team to 2');

        // Create 2nd member -> SUCCESS
        $this->actingAs($user)->post(route('admin.team.store'), [
            'name' => 'Staff 1',
            'email' => 'staff1@test.am',
            'role' => 'staff',
            'password' => 'secret123',
        ]);
        $this->assertSame(2, User::where('vendor_id', $vendor->id)->count());

        // Create 3rd member -> BLOCKED BY QUOTA
        $responseTeam = $this->actingAs($user)->post(route('admin.team.store'), [
            'name' => 'Staff 2',
            'email' => 'staff2@test.am',
            'role' => 'staff',
            'password' => 'secret123',
        ]);
        $responseTeam->assertRedirect(route('admin.subscription'));
        $responseTeam->assertSessionHas('warning');
        $this->assertSame(2, User::where('vendor_id', $vendor->id)->count());
    }

    public function test_event_based_metering_with_idempotency_prevents_duplicate_counting(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->proPlan);

        $idempotencyKey = 'order_tx_'.uniqid();

        // 1st execution
        $status1 = $this->quotaService->recordUsage(
            vendor: $vendor,
            quotaKey: QuotaCatalog::KEY_ORDERS_PERIOD,
            quantity: 1,
            idempotencyKey: $idempotencyKey,
            metadata: ['order_id' => 101]
        );

        $this->assertSame(1, $status1->currentUsage);

        // 2nd execution with same idempotency key (e.g. retried webhook or duplicate submit)
        $status2 = $this->quotaService->recordUsage(
            vendor: $vendor,
            quotaKey: QuotaCatalog::KEY_ORDERS_PERIOD,
            quantity: 1,
            idempotencyKey: $idempotencyKey,
            metadata: ['order_id' => 101]
        );

        // Usage must NOT increment again!
        $this->assertSame(1, $status2->currentUsage);
        $this->assertSame(1, TenantUsageEvent::where('vendor_id', $vendor->id)->count());
        $this->assertSame(1, (int) TenantUsageMeter::where('vendor_id', $vendor->id)->value('used_count'));
    }

    public function test_orders_period_quota_exhaustion_blocks_storefront_orders(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->proPlan);

        $location = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Storefront Branch',
            'slug' => 'branch-1',
            'table_count' => 10,
            'is_active' => true,
        ]);

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'Burgers',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Classic Burger',
            'price' => 3500,
            'is_available' => true,
            'available_for_dine_in' => true,
        ]);

        // Set monthly orders quota to exactly 2 orders
        $this->quotaService->setOverride($vendor, QuotaCatalog::KEY_ORDERS_PERIOD, 2, '2 orders limit');

        // Order 1 -> 200 OK
        $res1 = $this->postJson("/api/m/{$vendor->slug}/order", [
            'location_id' => $location->id,
            'type' => 'dine_in',
            'table_number' => '5',
            'customer_name' => 'Alice',
            'customer_phone' => '+37491111111',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $res1->assertStatus(200);

        // Order 2 -> 200 OK
        $res2 = $this->postJson("/api/m/{$vendor->slug}/order", [
            'location_id' => $location->id,
            'type' => 'dine_in',
            'table_number' => '5',
            'customer_name' => 'Bob',
            'customer_phone' => '+37492222222',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $res2->assertStatus(200);

        // Order 3 -> Quota Exceeded 422 JSON
        $res3 = $this->postJson("/api/m/{$vendor->slug}/order", [
            'location_id' => $location->id,
            'type' => 'dine_in',
            'table_number' => '5',
            'customer_name' => 'Charlie',
            'customer_phone' => '+37493333333',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $res3->assertStatus(422);
        $res3->assertJson([
            'success' => false,
            'error' => 'quota_exceeded',
            'code' => 'QUOTA_EXCEEDED',
            'quota_key' => QuotaCatalog::KEY_ORDERS_PERIOD,
        ]);
    }

    public function test_reservations_period_quota_exhaustion_blocks_new_bookings(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->proPlan, [
            'reservation_mode' => Vendor::RESERVATION_MODE_RESERVATION_ONLY,
            'reservation_settings' => [
                'enabled' => true,
                'min_party_size' => 1,
                'max_party_size' => 10,
                'slot_interval_minutes' => 30,
                'default_duration_minutes' => 90,
                'buffer_minutes' => 15,
                'lead_time_hours' => 0,
                'auto_confirm' => true,
            ],
        ]);

        $location = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Bistro',
            'slug' => 'bistro',
            'table_count' => 5,
            'is_active' => true,
        ]);

        $table = RestaurantTable::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'table_number' => 'T1',
            'capacity' => 4,
            'is_active' => true,
            'status' => 'available',
        ]);

        // Limit reservations to 1 for this vendor
        $this->quotaService->setOverride($vendor, QuotaCatalog::KEY_RESERVATIONS_PERIOD, 1, '1 reservation max');

        // Reservation 1 -> 201 Created
        $res1 = $this->postJson("/api/m/{$vendor->slug}/reserve", [
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'guest_count' => 2,
            'customer_name' => 'Davit',
            'customer_email' => 'davit@test.am',
            'customer_phone' => '+37498112233',
        ]);
        $res1->assertStatus(201);

        // Reservation 2 -> 422 Quota Exceeded
        $res2 = $this->postJson("/api/m/{$vendor->slug}/reserve", [
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '21:00',
            'guest_count' => 2,
            'customer_name' => 'Karen',
            'customer_email' => 'karen@test.am',
            'customer_phone' => '+37498445566',
        ]);
        $res2->assertStatus(422);
        $res2->assertJson([
            'error' => 'quota_exceeded',
            'code' => 'QUOTA_EXCEEDED',
            'quota_key' => QuotaCatalog::KEY_RESERVATIONS_PERIOD,
        ]);
    }

    public function test_billing_period_reset_and_subscription_renewal(): void
    {
        [$vendor, $user, $subscription] = $this->createVendorWithPlan($this->proPlan);

        // Window 1: Previous period (e.g. Month 1)
        $p1Start = Carbon::parse('2026-01-01 00:00:00');
        $p1End = Carbon::parse('2026-01-31 23:59:59');

        $subscription->update([
            'current_period_start' => $p1Start,
            'current_period_end' => $p1End,
        ]);

        // Record 100 orders in Period 1
        TenantUsageMeter::create([
            'vendor_id' => $vendor->id,
            'subscription_id' => $subscription->id,
            'quota_key' => QuotaCatalog::KEY_ORDERS_PERIOD,
            'period_start' => $p1Start,
            'period_end' => $p1End,
            'used_count' => 100,
        ]);

        // At a date in Period 1: usage is 100
        $this->assertSame(100, $this->quotaService->getCurrentUsage($vendor, QuotaCatalog::KEY_ORDERS_PERIOD, $p1Start->copy()->addDays(5)));

        // Window 2: Renewal into Month 2
        $p2Start = Carbon::parse('2026-02-01 00:00:00');
        $p2End = Carbon::parse('2026-02-28 23:59:59');

        $subscription->update([
            'current_period_start' => $p2Start,
            'current_period_end' => $p2End,
        ]);

        // At a date in Period 2: usage automatically starts fresh at 0
        $this->assertSame(0, $this->quotaService->getCurrentUsage($vendor, QuotaCatalog::KEY_ORDERS_PERIOD, $p2Start->copy()->addDays(2)));

        // Historical meter for Period 1 is untouched
        $oldMeter = TenantUsageMeter::where('vendor_id', $vendor->id)
            ->where('period_start', $p1Start)
            ->first();
        $this->assertSame(100, $oldMeter->used_count);
    }

    public function test_plan_upgrade_and_downgrade_quota_semantics(): void
    {
        [$vendor, $user, $subscription] = $this->createVendorWithPlan($this->basicPlan);

        // Basic plan limit is 50 products
        $this->assertSame(50, $this->quotaService->getLimit($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));

        // Upgrade plan to Pro
        $this->subscriptionService->changePlan($subscription, $this->proPlan, null, 'Customer upgrade');
        $vendor->refresh();

        // Limit immediately reflects Pro (250 products)
        $this->assertSame(250, $this->quotaService->getLimit($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));

        // Upgrade to Business -> Unlimited (-1)
        $this->subscriptionService->changePlan($subscription, $this->businessPlan, null, 'Customer upgrade to Business');
        $vendor->refresh();
        $this->assertSame(QuotaCatalog::UNLIMITED, $this->quotaService->getLimit($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));
    }

    public function test_superadmin_can_set_and_remove_quota_overrides(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);

        $superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin.'.uniqid().'@test.am',
            'password' => Hash::make('secret123'),
            'role' => 'superadmin',
        ]);

        // Initial Basic product limit
        $this->assertSame(50, $this->quotaService->getLimit($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));

        // SuperAdmin grants custom quota of 150 products
        $response = $this->actingAs($superAdmin)->post(route('superadmin.subscriptions.quota_overrides.store', $vendor), [
            'quota_key' => QuotaCatalog::KEY_PRODUCTS_COUNT,
            'quota_limit' => 150,
            'reason' => 'Pilot partnership bonus',
            'expires_at' => now()->addDays(60)->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(150, $this->quotaService->getLimit($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));

        // SuperAdmin deletes override
        $delResponse = $this->actingAs($superAdmin)->delete(route('superadmin.subscriptions.quota_overrides.destroy', [
            'vendor' => $vendor,
            'quotaKey' => QuotaCatalog::KEY_PRODUCTS_COUNT,
        ]));

        $delResponse->assertSessionHas('success');
        // Reverts back to plan default (50)
        $this->assertSame(50, $this->quotaService->getLimit($vendor, QuotaCatalog::KEY_PRODUCTS_COUNT));
    }

    public function test_tenant_isolation_in_quotas(): void
    {
        [$vendorA] = $this->createVendorWithPlan($this->basicPlan);
        [$vendorB] = $this->createVendorWithPlan($this->basicPlan);

        // Vendor A is capped at 1 product
        $this->quotaService->setOverride($vendorA, QuotaCatalog::KEY_PRODUCTS_COUNT, 1, 'Cap Vendor A');

        $catA = Category::create(['vendor_id' => $vendorA->id, 'name' => 'Menu A', 'sort_order' => 1, 'is_active' => true]);
        $this->menuService->createProduct($vendorA, ['category_id' => $catA->id, 'name' => 'Dish A1', 'price' => 1000]);

        // Vendor A is now full
        $this->assertFalse($this->quotaService->can($vendorA, QuotaCatalog::KEY_PRODUCTS_COUNT));

        // Vendor B is unaffected and has full available quota
        $this->assertTrue($this->quotaService->can($vendorB, QuotaCatalog::KEY_PRODUCTS_COUNT));
        $this->assertSame(0, $this->quotaService->getCurrentUsage($vendorB, QuotaCatalog::KEY_PRODUCTS_COUNT));
        $this->assertSame(50, $this->quotaService->getLimit($vendorB, QuotaCatalog::KEY_PRODUCTS_COUNT));
    }

    public function test_failed_transactions_rollback_usage_meter(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->proPlan);

        $this->assertSame(0, $this->quotaService->getCurrentUsage($vendor, QuotaCatalog::KEY_ORDERS_PERIOD));

        try {
            DB::transaction(function () use ($vendor) {
                $this->quotaService->recordUsage($vendor, QuotaCatalog::KEY_ORDERS_PERIOD, 1, 'tx_rollback_test');
                throw new \RuntimeException('Intentional transaction abort');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Usage was rolled back in PostgreSQL
        $this->assertSame(0, $this->quotaService->getCurrentUsage($vendor, QuotaCatalog::KEY_ORDERS_PERIOD));
        $this->assertSame(0, TenantUsageEvent::where('vendor_id', $vendor->id)->count());
    }
}
