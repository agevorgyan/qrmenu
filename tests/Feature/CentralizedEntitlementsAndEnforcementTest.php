<?php

namespace Tests\Feature;

use App\DTOs\EntitlementResult;
use App\Enums\SubscriptionStatus;
use App\Models\MenuTemplate;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Security\FeatureCatalog;
use App\Security\Permission;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralizedEntitlementsAndEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $businessPlan;

    protected EntitlementService $entitlementService;

    protected SubscriptionService $subscriptionService;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        MenuTemplate::firstOrCreate(
            ['slug' => 'modern-minimal'],
            ['name' => 'Modern Minimal', 'is_active' => true]
        );

        $this->basicPlan = SubscriptionPlan::where('slug', 'basic')->firstOrFail();
        $this->proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $this->businessPlan = SubscriptionPlan::where('slug', 'business')->firstOrFail();

        $this->entitlementService = app(EntitlementService::class);
        $this->subscriptionService = app(SubscriptionService::class);

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createVendorWithPlan(SubscriptionPlan $plan, string $status = 'active'): array
    {
        $vendor = Vendor::create([
            'name' => 'Restaurant '.uniqid(),
            'slug' => 'restaurant-'.uniqid(),
            'type' => 'restaurant',
            'email' => 'vendor_'.uniqid().'@example.com',
            'phone' => '091'.rand(100000, 999999),
            'subscription_plan_id' => $plan->id,
            'subscription_status' => $status,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Owner',
            'email' => 'owner_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status === 'active' ? SubscriptionStatus::Active : SubscriptionStatus::tryFrom($status),
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => $status === 'active' ? now()->addMonth() : now()->subDay(),
            'price' => $plan->price,
            'currency' => 'AMD',
        ]);

        return [$vendor, $user, $subscription];
    }

    public function test_feature_catalog_normalizes_aliases_and_validates_canonical_keys(): void
    {
        $this->assertEquals(FeatureCatalog::ORDERS_ONLINE, FeatureCatalog::normalize('orders'));
        $this->assertEquals(FeatureCatalog::ORDERS_ONLINE, FeatureCatalog::normalize('online_orders'));
        $this->assertEquals(FeatureCatalog::BRANCHES_MULTIPLE, FeatureCatalog::normalize('locations'));
        $this->assertEquals(FeatureCatalog::BRANCHES_MULTIPLE, FeatureCatalog::normalize('multi_location'));
        $this->assertEquals(FeatureCatalog::TEAM_MANAGE, FeatureCatalog::normalize('team'));
        $this->assertEquals(FeatureCatalog::TEAM_MANAGE, FeatureCatalog::normalize('staff_roles'));
        $this->assertEquals(FeatureCatalog::CUSTOMERS_CRM, FeatureCatalog::normalize('customers'));
        $this->assertEquals(FeatureCatalog::RESERVATIONS_MANAGE, FeatureCatalog::normalize('reservations'));
        $this->assertEquals(FeatureCatalog::PREORDERS_MANAGE, FeatureCatalog::normalize('preorders'));
        $this->assertEquals(FeatureCatalog::AI_WAITER, FeatureCatalog::normalize('ai_waiter'));
        $this->assertEquals(FeatureCatalog::BRANDING_CUSTOM_DOMAIN, FeatureCatalog::normalize('custom_domain'));

        $this->assertTrue(FeatureCatalog::isValid('orders'));
        $this->assertTrue(FeatureCatalog::isValid(FeatureCatalog::AI_MENU_BUILDER));
        $this->assertFalse(FeatureCatalog::isValid('unreal_nonexistent_feature_123'));
    }

    public function test_basic_plan_cannot_access_orders_or_ai_tools(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);

        // Service level evaluation
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::MENU_BASE));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::MENU_TRANSLATIONS));
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::ORDERS_ONLINE));
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::AI_MENU_BUILDER));
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::AI_WAITER));

        // Model helper delegation
        $this->assertTrue($vendor->hasFeature('menu'));
        $this->assertFalse($vendor->hasFeature('orders'));
        $this->assertFalse($vendor->hasFeature('locations'));

        // Protected web routes redirect to subscription page with warning
        $ordersResponse = $this->actingAs($user)->get(route('admin.orders.index'));
        $ordersResponse->assertRedirect(route('admin.subscription'));
        $ordersResponse->assertSessionHas('warning');

        $aiResponse = $this->actingAs($user)->get(route('admin.ai.import'));
        $aiResponse->assertRedirect(route('admin.subscription'));
        $aiResponse->assertSessionHas('warning');
    }

    public function test_pro_plan_can_access_orders_and_custom_domain_but_not_multi_location_or_team(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->proPlan);

        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::ORDERS_ONLINE));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::RESERVATIONS_MANAGE));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::CUSTOMERS_CRM));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::AI_WAITER));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::BRANDING_CUSTOM_DOMAIN));

        // Business features denied
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::BRANCHES_MULTIPLE));
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::TEAM_MANAGE));
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::ANALYTICS_ADVANCED));
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::API_ACCESS));

        // Web route checks
        $ordersResponse = $this->actingAs($user)->get(route('admin.orders.index'));
        $ordersResponse->assertStatus(200);

        $locationsResponse = $this->actingAs($user)->get(route('admin.locations.index'));
        $locationsResponse->assertRedirect(route('admin.subscription'));

        $teamResponse = $this->actingAs($user)->get(route('admin.team.index'));
        $teamResponse->assertRedirect(route('admin.subscription'));
    }

    public function test_business_plan_can_access_all_features(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->businessPlan);

        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::ORDERS_ONLINE));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::BRANCHES_MULTIPLE));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::TEAM_MANAGE));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::ANALYTICS_ADVANCED));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::AI_MENU_BUILDER));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::AI_WAITER));
        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::BRANDING_CUSTOM_DOMAIN));

        $locationsResponse = $this->actingAs($user)->get(route('admin.locations.index'));
        $locationsResponse->assertStatus(200);

        $teamResponse = $this->actingAs($user)->get(route('admin.team.index'));
        $teamResponse->assertStatus(200);

        $aiResponse = $this->actingAs($user)->get(route('admin.ai.import'));
        $aiResponse->assertStatus(200);
    }

    public function test_missing_or_expired_subscription_blocks_paid_features_fails_closed(): void
    {
        [$vendor, $user, $subscription] = $this->createVendorWithPlan($this->proPlan, 'expired');

        $this->assertFalse($vendor->allowsAccess());
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::ORDERS_ONLINE));

        // Result object carries diagnostic info
        $check = $this->entitlementService->check($vendor, FeatureCatalog::ORDERS_ONLINE);
        $this->assertFalse($check->allowed);
        $this->assertEquals(EntitlementResult::SUBSCRIPTION_INACTIVE, $check->code);

        // Storefront API order submission returns 403 JSON
        $apiResponse = $this->postJson("/api/m/{$vendor->slug}/order", [
            'type' => 'dine_in',
            'table_number' => '1',
            'customer_name' => 'John',
            'customer_phone' => '091222222',
            'items' => [],
        ]);

        $apiResponse->assertStatus(403);
        $apiResponse->assertJson([
            'success' => false,
            'error' => 'FEATURE_NOT_ENTITLED',
        ]);
    }

    public function test_public_menu_remains_accessible_even_under_paid_feature_restrictions(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);

        // Public storefront menu viewing is allowed
        $menuResponse = $this->get("/m/{$vendor->slug}");
        $menuResponse->assertStatus(200);

        // AI Waiter config returns enabled: false for Basic plan
        $configResponse = $this->getJson("/api/m/{$vendor->slug}/ai-waiter/config");
        $configResponse->assertStatus(200);
        $configResponse->assertJson([
            'success' => true,
            'enabled' => false,
        ]);
    }

    public function test_feature_access_updates_immediately_upon_plan_change(): void
    {
        [$vendor, $user, $subscription] = $this->createVendorWithPlan($this->basicPlan);

        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::ORDERS_ONLINE));

        // Upgrade vendor to Pro
        $this->subscriptionService->changePlan($subscription, $this->proPlan, null, 'Customer upgraded');
        $vendor->refresh();

        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::ORDERS_ONLINE));
        $this->assertTrue($vendor->hasFeature('orders.online'));
    }

    public function test_direct_api_request_bypassing_frontend_is_blocked_with_json_403(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);

        // Direct call to AI Waiter session start on basic plan
        $response = $this->postJson("/api/m/{$vendor->slug}/ai-waiter/session", [
            'language' => 'en',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'FEATURE_NOT_ENTITLED',
            'feature' => FeatureCatalog::AI_WAITER,
        ]);
    }

    public function test_cross_tenant_request_fails_closed_and_denies_entitlement(): void
    {
        [$vendorA, $userA] = $this->createVendorWithPlan($this->businessPlan);
        [$vendorB, $userB] = $this->createVendorWithPlan($this->basicPlan);

        // User A trying to evaluate features on Tenant B context
        $result = $this->entitlementService->check($vendorB, FeatureCatalog::ORDERS_ONLINE, $userA);
        $this->assertFalse($result->allowed);
        $this->assertEquals(EntitlementResult::UNAUTHORIZED_USER, $result->code);
    }

    public function test_employee_role_authorization_separated_from_plan_entitlement(): void
    {
        [$vendor, $owner] = $this->createVendorWithPlan($this->businessPlan);

        // Staff employee belongs to Business tenant (which has team and menu features)
        $staffUser = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Waiter Bob',
            'email' => 'bob_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Staff has MENU_VIEW, but NOT TEAM_MANAGE
        $this->assertTrue($this->entitlementService->canUserAccessFeature($staffUser, FeatureCatalog::MENU_BASE, Permission::MENU_VIEW));
        $this->assertFalse($this->entitlementService->canUserAccessFeature($staffUser, FeatureCatalog::TEAM_MANAGE, Permission::TEAM_MANAGE));

        // Owner has both
        $this->assertTrue($this->entitlementService->canUserAccessFeature($owner, FeatureCatalog::TEAM_MANAGE, Permission::TEAM_MANAGE));

        // If tenant downgraded to Basic, even owner cannot access TEAM_MANAGE
        $vendor->update(['subscription_plan_id' => $this->basicPlan->id, 'subscription_plan' => 'basic']);
        $owner->unsetRelation('vendor');
        $this->entitlementService->clearCache($vendor);

        $this->assertFalse($this->entitlementService->canUserAccessFeature($owner, FeatureCatalog::TEAM_MANAGE, Permission::TEAM_MANAGE));
    }

    public function test_administrative_feature_overrides(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);

        // Basic plan does not have AI Waiter
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::AI_WAITER));

        // 1. SuperAdmin grants override
        $override = $this->entitlementService->setOverride(
            vendor: $vendor,
            featureKey: FeatureCatalog::AI_WAITER,
            enabled: true,
            reason: 'Beta tester trial grant',
            scope: 'beta',
            expiresAt: Carbon::now()->addDays(7),
            actor: $this->superAdmin
        );

        $this->assertTrue($this->entitlementService->can($vendor, FeatureCatalog::AI_WAITER));

        // 2. Override expiration
        $override->update(['expires_at' => Carbon::now()->subMinute()]);
        $this->entitlementService->clearCache($vendor);
        $this->assertFalse($this->entitlementService->can($vendor, FeatureCatalog::AI_WAITER));

        // 3. SuperAdmin explicitly disables an otherwise plan-included feature
        [$proVendor, $proUser] = $this->createVendorWithPlan($this->proPlan);
        $this->assertTrue($this->entitlementService->can($proVendor, FeatureCatalog::ORDERS_ONLINE));

        $this->entitlementService->setOverride(
            vendor: $proVendor,
            featureKey: FeatureCatalog::ORDERS_ONLINE,
            enabled: false,
            reason: 'Disabled due to unpaid service fees',
            actor: $this->superAdmin
        );

        $this->assertFalse($this->entitlementService->can($proVendor, FeatureCatalog::ORDERS_ONLINE));

        // 4. Non-SuperAdmin attempting setOverride throws AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->entitlementService->setOverride(
            vendor: $vendor,
            featureKey: FeatureCatalog::AI_WAITER,
            enabled: true,
            reason: 'Unauthorized attempt',
            actor: $user
        );
    }

    public function test_cache_invalidation_when_override_or_plan_updated(): void
    {
        [$vendor, $user] = $this->createVendorWithPlan($this->basicPlan);

        // Warm cache
        $features1 = $this->entitlementService->getEntitledFeatures($vendor);
        $this->assertNotContains(FeatureCatalog::AI_WAITER, $features1);

        // Set override
        $this->entitlementService->setOverride(
            vendor: $vendor,
            featureKey: FeatureCatalog::AI_WAITER,
            enabled: true,
            reason: 'Granted beta',
            actor: $this->superAdmin
        );

        $features2 = $this->entitlementService->getEntitledFeatures($vendor);
        $this->assertContains(FeatureCatalog::AI_WAITER, $features2);

        // Revoke override
        $this->entitlementService->revokeOverride($vendor, FeatureCatalog::AI_WAITER, $this->superAdmin);
        $features3 = $this->entitlementService->getEntitledFeatures($vendor);
        $this->assertNotContains(FeatureCatalog::AI_WAITER, $features3);
    }
}
