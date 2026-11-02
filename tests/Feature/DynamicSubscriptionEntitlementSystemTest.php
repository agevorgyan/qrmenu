<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Exceptions\QuotaExceededException;
use App\Models\Category;
use App\Models\Feature;
use App\Models\FeatureCategory;
use App\Models\Language;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanVersion;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuthorizationService;
use App\Services\EntitlementService;
use App\Services\FeatureDependencyService;
use App\Services\MenuManagementService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicSubscriptionEntitlementSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $advancedPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);

        $this->superAdmin = User::factory()->create([
            'role' => 'superadmin',
            'email' => 'superadmin@elab.menu',
        ]);

        $this->basicPlan = SubscriptionPlan::where('slug', 'basic')->firstOrFail();
        $this->proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $this->advancedPlan = SubscriptionPlan::where('slug', 'advanced')->firstOrFail();
    }

    protected function createVendorWithPlan(SubscriptionPlan $plan, string $status = 'active'): Vendor
    {
        $vendor = Vendor::create([
            'name' => 'Test Restaurant '.uniqid(),
            'slug' => 'test-rest-'.uniqid(),
            'type' => 'restaurant',
            'email' => 'owner-'.uniqid().'@example.com',
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => $status,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $currentVersion = $plan->currentVersion;

        Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $plan->id,
            'plan_version_id' => $currentVersion?->id,
            'plan_version_number' => $currentVersion?->version_number ?? 1,
            'status' => SubscriptionStatus::tryFrom($status) ?? SubscriptionStatus::Active,
            'billing_status' => 'current',
            'entitlement_status' => 'active',
            'billing_interval' => 'monthly',
            'currency' => 'AMD',
            'amount' => $plan->price,
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(30),
        ]);

        return $vendor->fresh();
    }

    public function test_initial_three_plans_and_fifteen_categories_are_seeded_dynamically(): void
    {
        // Only Basic, Pro, Advanced are seeded initially
        $plans = SubscriptionPlan::orderBy('sort_order')->pluck('slug')->toArray();
        $this->assertSame(['basic', 'pro', 'advanced'], $plans);

        // 15 Feature Categories
        $this->assertSame(15, FeatureCategory::count());

        // All 6 Feature Types exist in catalog
        $types = Feature::distinct()->pluck('feature_type')->sort()->values()->toArray();
        $this->assertEqualsCanonicalizing(
            ['BOOLEAN', 'LIMIT', 'USAGE', 'CONFIGURATION', 'ENUM', 'METERED'],
            $types
        );
    }

    public function test_basic_pro_and_advanced_entitlement_matrix_matches_section_three(): void
    {
        $basicVendor = $this->createVendorWithPlan($this->basicPlan);
        $proVendor = $this->createVendorWithPlan($this->proPlan);
        $advancedVendor = $this->createVendorWithPlan($this->advancedPlan);

        // 3.1 BASIC
        $this->assertTrue(EntitlementService::hasFeature($basicVendor, 'menu.qr'));
        $this->assertSame(-1, EntitlementService::getLimit($basicVendor, 'menu.categories.limit'));
        $this->assertSame(100, EntitlementService::getLimit($basicVendor, 'menu.products.limit'));
        $this->assertTrue(EntitlementService::hasFeature($basicVendor, 'menu.product_images'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'menu.videos'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'menu.modifiers'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'menu.variants'));
        $this->assertSame(2, EntitlementService::getLimit($basicVendor, 'menu.languages.limit'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'ai.translation'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'orders.online'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'pos.enabled'));
        $this->assertSame(1, EntitlementService::getLimit($basicVendor, 'staff.users.limit'));
        $this->assertTrue(EntitlementService::hasFeature($basicVendor, 'reviews.google'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'reviews.tripadvisor'));
        $this->assertSame(0, EntitlementService::getLimit($basicVendor, 'ai.monthly_credits'));

        // 3.2 PRO
        $this->assertSame(1000, EntitlementService::getLimit($proVendor, 'menu.products.limit'));
        $this->assertTrue(EntitlementService::hasFeature($proVendor, 'menu.videos'));
        $this->assertTrue(EntitlementService::hasFeature($proVendor, 'menu.modifiers'));
        $this->assertSame(5, EntitlementService::getLimit($proVendor, 'menu.languages.limit'));
        $this->assertTrue(EntitlementService::hasFeature($proVendor, 'ai.translation'));
        $this->assertSame(3, EntitlementService::getLimit($proVendor, 'currency.max_additional'));
        $this->assertTrue(EntitlementService::hasFeature($proVendor, 'orders.online'));
        $this->assertSame(5, EntitlementService::getLimit($proVendor, 'delivery.zones.limit'));
        $this->assertTrue(EntitlementService::hasFeature($proVendor, 'reservations.enabled'));
        $this->assertTrue(EntitlementService::hasFeature($proVendor, 'kds.enabled'));
        $this->assertFalse(EntitlementService::hasFeature($proVendor, 'pos.enabled'));
        $this->assertSame(10, EntitlementService::getLimit($proVendor, 'staff.users.limit'));
        $this->assertSame(1000, EntitlementService::getLimit($proVendor, 'ai.monthly_credits'));
        $this->assertFalse(EntitlementService::hasFeature($proVendor, 'integrations.api'));

        // 3.3 ADVANCED
        $this->assertSame(-1, EntitlementService::getLimit($advancedVendor, 'menu.products.limit'));
        $this->assertSame(-1, EntitlementService::getLimit($advancedVendor, 'menu.languages.limit'));
        $this->assertSame(-1, EntitlementService::getLimit($advancedVendor, 'currency.max_additional'));
        $this->assertSame(-1, EntitlementService::getLimit($advancedVendor, 'delivery.zones.limit'));
        $this->assertTrue(EntitlementService::hasFeature($advancedVendor, 'pos.enabled'));
        $this->assertTrue(EntitlementService::hasFeature($advancedVendor, 'pos.manager_mode'));
        $this->assertTrue(EntitlementService::hasFeature($advancedVendor, 'pos.cashier_mode'));
        $this->assertTrue(EntitlementService::hasFeature($advancedVendor, 'pos.waiter_mode'));
        $this->assertSame(-1, EntitlementService::getLimit($advancedVendor, 'staff.users.limit'));
        $this->assertSame(10000, EntitlementService::getLimit($advancedVendor, 'ai.monthly_credits'));
        $this->assertTrue(EntitlementService::hasFeature($advancedVendor, 'integrations.api'));
        $this->assertTrue(EntitlementService::hasFeature($advancedVendor, 'branding.white_label'));
        $this->assertSame(10, EntitlementService::getLimit($advancedVendor, 'locations.limit'));
    }

    public function test_feature_catalog_code_immutability_and_active_subscription_deletion_protection(): void
    {
        $this->createVendorWithPlan($this->basicPlan);
        $qrFeature = Feature::where('code', 'menu.qr')->firstOrFail();

        // Feature code is immutable once created (silently preserved on update)
        $qrFeature->update(['code' => 'menu.qr_renamed']);
        $this->assertSame('menu.qr', $qrFeature->fresh()->code);
    }

    public function test_feature_used_by_active_subscription_cannot_be_deleted_but_can_be_archived(): void
    {
        $this->createVendorWithPlan($this->basicPlan);
        $qrFeature = Feature::where('code', 'menu.qr')->firstOrFail();

        $this->assertTrue($qrFeature->isUsedByActiveSubscriptions());

        // Attempting to delete via SuperAdmin controller is blocked
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.features.destroy', $qrFeature->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('features', ['id' => $qrFeature->id]);

        // Archiving succeeds
        $archiveResponse = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.features.archive', $qrFeature->id));

        $archiveResponse->assertSessionHas('success');
        $this->assertTrue($qrFeature->fresh()->is_archived);
    }

    public function test_feature_dependency_circular_detection_and_validation(): void
    {
        $depService = app(FeatureDependencyService::class);

        // delivery.enabled -> orders.online -> orders.management is already seeded
        $this->assertTrue($depService->wouldCreateCycle('orders.management', 'delivery.enabled'));

        $this->expectException(\InvalidArgumentException::class);
        $depService->addDependency('orders.management', 'delivery.enabled');
    }

    public function test_plan_builder_creates_new_immutable_version_and_respects_subscriber_version_pinning(): void
    {
        $basicVendorPinned = $this->createVendorWithPlan($this->basicPlan);
        $this->assertSame(100, EntitlementService::getLimit($basicVendorPinned, 'menu.products.limit'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendorPinned, 'pos.enabled'));

        // Super Admin edits Basic plan via Visual Plan Builder WITHOUT migrating existing subscribers
        $planFeaturesPayload = [];
        foreach ($this->basicPlan->planFeatures as $pf) {
            $planFeaturesPayload[$pf->feature_code] = [
                'is_enabled' => $pf->is_enabled,
                'limit_value' => $pf->limit_value,
                'is_unlimited' => $pf->is_unlimited,
                'usage_quota' => $pf->usage_quota,
                'config_value' => $pf->config_value,
            ];
        }
        $planFeaturesPayload['menu.products.limit']['limit_value'] = 250;
        $planFeaturesPayload['pos.enabled']['is_enabled'] = true;

        $response = $this->actingAs($this->superAdmin)->post(
            route('superadmin.plans.builder.save', $this->basicPlan->id),
            [
                'name' => 'Basic',
                'price' => 9900,
                'trial_days' => 14,
                'duration_days' => 30,
                'change_summary' => 'Increased products to 250 and enabled POS for v2',
                'migrate_subscribers' => false,
                'plan_features' => $planFeaturesPayload,
            ]
        );

        $response->assertSessionHas('success');
        $this->basicPlan->refresh();
        $this->assertSame(2, (int) $this->basicPlan->current_version_number);

        // Existing subscriber pinned to v1 keeps v1 entitlements (100 products, POS false)
        EntitlementService::invalidateCache($basicVendorPinned);
        $this->assertSame(1, (int) $basicVendorPinned->subscription->fresh()->plan_version_number);
        $this->assertSame(100, EntitlementService::getLimit($basicVendorPinned, 'menu.products.limit'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendorPinned, 'pos.enabled'));

        // New subscriber gets v2 automatically (250 products, POS true)
        $newBasicVendor = $this->createVendorWithPlan($this->basicPlan);
        $this->assertSame(2, (int) $newBasicVendor->subscription->plan_version_number);
        $this->assertSame(250, EntitlementService::getLimit($newBasicVendor, 'menu.products.limit'));
        $this->assertTrue(EntitlementService::hasFeature($newBasicVendor, 'pos.enabled'));

        // Now Super Admin explicitly migrates v1 subscribers to v2
        $v2 = SubscriptionPlanVersion::where('subscription_plan_id', $this->basicPlan->id)
            ->where('version_number', 2)
            ->firstOrFail();

        $this->actingAs($this->superAdmin)->post(
            route('superadmin.plans.versions.migrate', [$this->basicPlan->id, $v2->id])
        )->assertSessionHas('success');

        EntitlementService::invalidateCache($basicVendorPinned->fresh());
        $this->assertSame(2, (int) $basicVendorPinned->subscription->fresh()->plan_version_number);
        $this->assertSame(250, EntitlementService::getLimit($basicVendorPinned->fresh(), 'menu.products.limit'));
        $this->assertTrue(EntitlementService::hasFeature($basicVendorPinned->fresh(), 'pos.enabled'));
    }

    public function test_plan_duplication_and_archiving(): void
    {
        $this->actingAs($this->superAdmin)->post(
            route('superadmin.plans.duplicate', $this->proPlan->id),
            [
                'new_name' => 'Pro Seasonal',
                'new_slug' => 'pro-seasonal',
            ]
        )->assertSessionHas('success');

        $duplicated = SubscriptionPlan::where('slug', 'pro-seasonal')->first();
        $this->assertNotNull($duplicated);
        $this->assertGreaterThan(20, $duplicated->planFeatures()->count());

        // Archive duplicated plan
        $this->actingAs($this->superAdmin)->post(
            route('superadmin.plans.archive', $duplicated->id)
        )->assertSessionHas('success');

        $this->assertTrue($duplicated->fresh()->is_archived);
        $this->assertFalse($duplicated->fresh()->is_active);
    }

    public function test_vendor_individual_overrides_take_highest_priority_and_expire_cleanly(): void
    {
        $basicVendor = $this->createVendorWithPlan($this->basicPlan);
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'pos.enabled'));
        $this->assertSame(100, EntitlementService::getLimit($basicVendor, 'menu.products.limit'));

        // Grant POS boolean override and custom product limit override (350) via SuperAdmin endpoint
        $this->actingAs($this->superAdmin)->post(
            route('superadmin.subscriptions.overrides.store', $basicVendor->id),
            [
                'feature_key' => 'pos.enabled',
                'is_enabled' => true,
                'reason' => '30-day VIP POS pilot',
                'expires_at' => now()->addDays(30)->toDateString(),
            ]
        )->assertSessionHas('success');

        $this->actingAs($this->superAdmin)->post(
            route('superadmin.subscriptions.overrides.store', $basicVendor->id),
            [
                'feature_key' => 'menu.products.limit',
                'is_enabled' => true,
                'value_type' => 'LIMIT',
                'limit_value' => 350,
                'reason' => 'Seasonal menu expansion override',
                'expires_at' => now()->addDays(30)->toDateString(),
            ]
        )->assertSessionHas('success');

        EntitlementService::invalidateCache($basicVendor);
        $this->assertTrue(EntitlementService::hasFeature($basicVendor, 'pos.enabled'));
        $this->assertSame(350, EntitlementService::getLimit($basicVendor, 'menu.products.limit'));

        $allEntitlements = EntitlementService::getAllEntitlements($basicVendor);
        $this->assertSame('override', $allEntitlements['pos.enabled']['source']);
        $this->assertSame('override', $allEntitlements['menu.products.limit']['source']);
    }

    public function test_subscription_addons_provide_additive_limits_and_boolean_feature_unlocks(): void
    {
        $basicVendor = $this->createVendorWithPlan($this->basicPlan);
        $this->assertSame(1, EntitlementService::getLimit($basicVendor, 'staff.users.limit'));
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'orders.online'));

        $staffAddon = SubscriptionAddon::where('code', 'addon.staff_accounts_5')->firstOrFail();
        $orderingAddon = SubscriptionAddon::where('code', 'addon.online_ordering')->firstOrFail();

        // Assign 2x (+5 staff accounts) = +10 staff accounts (total: 1 + 10 = 11)
        $this->actingAs($this->superAdmin)->post(
            route('superadmin.subscriptions.addons.assign', $basicVendor->id),
            [
                'subscription_addon_id' => $staffAddon->id,
                'quantity' => 2,
                'billing_period' => 'monthly',
            ]
        )->assertSessionHas('success');

        // Assign online ordering feature add-on
        $this->actingAs($this->superAdmin)->post(
            route('superadmin.subscriptions.addons.assign', $basicVendor->id),
            [
                'subscription_addon_id' => $orderingAddon->id,
                'quantity' => 1,
                'billing_period' => 'monthly',
            ]
        )->assertSessionHas('success');

        EntitlementService::invalidateCache($basicVendor);
        $this->assertSame(11, EntitlementService::getLimit($basicVendor, 'staff.users.limit'));
        $this->assertTrue(EntitlementService::hasFeature($basicVendor, 'orders.online'));

        // Cancel the online ordering add-on
        $vendorAddon = $basicVendor->activeSubscriptionAddons()->where('subscription_addon_id', $orderingAddon->id)->firstOrFail();
        $this->actingAs($this->superAdmin)->delete(
            route('superadmin.subscriptions.addons.cancel', [$basicVendor->id, $vendorAddon->id])
        )->assertSessionHas('success');

        EntitlementService::invalidateCache($basicVendor);
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'orders.online'));
    }

    public function test_non_destructive_downgrade_preserves_existing_products_and_blocks_new_creation(): void
    {
        $proVendor = $this->createVendorWithPlan($this->proPlan);
        $category = Category::create([
            'vendor_id' => $proVendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'sort_order' => 1,
        ]);

        // Set Basic product limit to 2 for fast verification
        $this->basicPlan->planFeatures()->where('feature_code', 'menu.products.limit')->update([
            'limit_value' => 2,
        ]);
        $this->basicPlan->versions()->update([
            'limits_snapshot' => array_merge($this->basicPlan->currentVersion?->limits_snapshot ?? [], ['menu.products.limit' => 2]),
        ]);
        $this->basicPlan->update(['quota_limits' => array_merge($this->basicPlan->quota_limits ?? [], ['products.count' => 2])]);

        // Create 3 products while on Pro plan
        for ($i = 1; $i <= 3; $i++) {
            Product::create([
                'vendor_id' => $proVendor->id,
                'category_id' => $category->id,
                'name' => "Dish {$i}",
                'price' => 2500,
                'is_available' => true,
            ]);
        }
        $this->assertSame(3, Product::where('vendor_id', $proVendor->id)->count());

        // Downgrade vendor to Basic (limit = 2)
        $proVendor->update([
            'subscription_plan' => 'basic',
            'subscription_plan_id' => $this->basicPlan->id,
        ]);
        $proVendor->subscription->update([
            'subscription_plan_id' => $this->basicPlan->id,
            'plan_version_id' => $this->basicPlan->currentVersion?->id,
        ]);
        EntitlementService::invalidateCache($proVendor->fresh());

        // Existing 3 products are preserved!
        $this->assertSame(3, Product::where('vendor_id', $proVendor->id)->count());
        $this->assertFalse(EntitlementService::canUse($proVendor->fresh(), 'menu.products.limit', 1));

        // Creating a 4th product is blocked by QuotaService
        $this->expectException(QuotaExceededException::class);
        app(MenuManagementService::class)->createProduct($proVendor->fresh(), [
            'category_id' => $category->id,
            'name' => 'Dish 4 Blocked',
            'price' => 3000,
        ]);
    }

    public function test_language_limit_and_ai_translation_entitlement_enforcement(): void
    {
        foreach (['hy' => 'Armenian', 'en' => 'English', 'ru' => 'Russian'] as $code => $name) {
            Language::firstOrCreate(['code' => $code], ['name' => $name, 'native_name' => $name, 'is_active' => true]);
        }

        $basicVendor = $this->createVendorWithPlan($this->basicPlan);
        $owner = User::factory()->create([
            'vendor_id' => $basicVendor->id,
            'role' => 'vendor_owner',
        ]);

        // Basic plan allows max 2 languages: attempting to enable 3 languages is blocked
        $langResponse = $this->actingAs($owner)->post(route('admin.translations.languages.sync'), [
            'active_locales' => ['hy', 'en', 'ru'],
            'default_locale' => 'hy',
        ]);
        $langResponse->assertSessionHas('error');

        // Basic plan has ai.translation DISABLED: batch AI translate is blocked
        $aiResponse = $this->actingAs($owner)->post(route('admin.translations.batch_ai'), [
            'target_locale' => 'en',
        ]);
        $aiResponse->assertSessionHas('error');
    }

    public function test_rbac_permission_and_subscription_entitlement_operate_independently(): void
    {
        $basicVendor = $this->createVendorWithPlan($this->basicPlan);
        $waiter = User::factory()->create([
            'vendor_id' => $basicVendor->id,
            'role' => 'waiter',
        ]);
        $owner = User::factory()->create([
            'vendor_id' => $basicVendor->id,
            'role' => 'vendor_owner',
        ]);

        $authService = app(AuthorizationService::class);

        // Waiter does not have billing.manage permission even if plan has features
        $this->assertFalse($authService->hasPermission($waiter, 'billing.manage'));
        $this->assertTrue($authService->hasPermission($owner, 'billing.manage'));

        // Owner has POS permission by role, but Basic subscription plan does NOT have pos.enabled entitlement
        $this->assertFalse(EntitlementService::hasFeature($basicVendor, 'pos.enabled'));

        // Effective access = User Permission AND Subscription Entitlement
        $effectiveOwnerPosAccess = $authService->hasPermission($owner, 'orders.manage')
            && EntitlementService::hasFeature($basicVendor, 'pos.enabled');
        $this->assertFalse($effectiveOwnerPosAccess);
    }

    public function test_superadmin_and_vendor_subscription_interfaces_render_and_record_audit_logs(): void
    {
        $vendor = $this->createVendorWithPlan($this->proPlan);
        $owner = User::factory()->create([
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
        ]);

        // SuperAdmin Plans & Plan Builder UI
        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.plans.index'))
            ->assertOk()
            ->assertSee('Visual Subscription Plan Builder')
            ->assertSee('Feature Catalog');

        // SuperAdmin Vendor Subscriptions UI
        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.subscriptions.index'))
            ->assertOk()
            ->assertSee($vendor->name);

        // Vendor Subscription & Billing UI
        $this->actingAs($owner)
            ->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Pro');

        // Verify Audit Logs were recorded during earlier test actions / settings updates
        $this->actingAs($this->superAdmin)->post(route('superadmin.subscriptions.settings.update'), [
            'default_trial_days' => 14,
            'grace_period_days' => 5,
            'warning_threshold_percent' => 85,
            'downgrade_behavior' => 'preserve_data_restrict_new',
        ])->assertSessionHas('success');

        $this->assertGreaterThan(0, SubscriptionAuditLog::count());
    }
}
