<?php

namespace Tests\Feature;

use App\DTOs\EntitlementResult;
use App\Enums\SubscriptionStatus;
use App\Models\MenuTemplate;
use App\Models\SecurityAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use App\Services\SecurityAuditService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SubscriptionAdministrationAndCustomerExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $businessPlan;

    protected User $superAdmin;

    protected User $vendorUser;

    protected Vendor $vendor;

    protected Subscription $subscription;

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

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_test_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->vendor = Vendor::create([
            'name' => 'Test Restaurant Prime',
            'slug' => 'test-restaurant-prime',
            'type' => 'restaurant',
            'email' => 'prime_'.uniqid().'@example.com',
            'phone' => '091223344',
            'subscription_plan_id' => $this->proPlan->id,
            'subscription_status' => 'active',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->vendorUser = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Vendor Manager',
            'email' => 'manager_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->subscription = Subscription::create([
            'vendor_id' => $this->vendor->id,
            'subscription_plan_id' => $this->proPlan->id,
            'price' => $this->proPlan->price,
            'currency' => 'AMD',
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->addMonth(),
            'renewal_state' => 'active',
            'auto_renew' => true,
        ]);
    }

    public function test_super_admin_can_access_plans_and_subscriptions_consoles(): void
    {
        $this->actingAs($this->superAdmin);

        $this->get(route('superadmin.plans.index'))->assertStatus(200);
        $this->get(route('superadmin.subscriptions.index'))->assertStatus(200);
        $this->get(route('superadmin.subscriptions.payments.index'))->assertStatus(200);
        $this->get(route('superadmin.subscriptions.audit_logs'))->assertStatus(200);
    }

    public function test_non_superadmin_user_is_forbidden_from_superadmin_consoles(): void
    {
        $this->actingAs($this->vendorUser);

        $this->get(route('superadmin.plans.index'))->assertStatus(403);
        $this->get(route('superadmin.subscriptions.index'))->assertStatus(403);
        $this->get(route('superadmin.subscriptions.payments.index'))->assertStatus(403);
        $this->get(route('superadmin.subscriptions.audit_logs'))->assertStatus(403);
    }

    public function test_unauthenticated_guest_is_redirected_from_superadmin(): void
    {
        $this->get(route('superadmin.plans.index'))->assertRedirect(route('login'));
        $this->get(route('superadmin.subscriptions.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_create_plan_with_pricing_features_and_quotas(): void
    {
        $this->actingAs($this->superAdmin);

        $payload = [
            'name' => 'Enterprise Elite',
            'price' => 45000,
            'yearly_price' => 450000,
            'currency' => 'AMD',
            'billing_interval' => 'monthly',
            'duration_days' => 30,
            'trial_days' => 14,
            'description' => 'Flagship enterprise plan with comprehensive limits',
            'features' => "Full QR Menu\nPriority 24/7 Support\nDedicated Account Manager",
            'feature_codes' => [
                FeatureCatalog::ORDERS_ONLINE,
                FeatureCatalog::ORDERS_DELIVERY,
                FeatureCatalog::AI_WAITER,
                FeatureCatalog::BRANCHES_MULTIPLE,
            ],
            'quota_limits' => [
                QuotaCatalog::KEY_PRODUCTS_COUNT => 1000,
                QuotaCatalog::KEY_BRANCHES_COUNT => 10,
                QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 5000,
            ],
        ];

        $response = $this->post(route('superadmin.plans.store'), $payload);
        $response->assertSessionHas('success');

        $createdPlan = SubscriptionPlan::where('name', 'Enterprise Elite')->first();
        $this->assertNotNull($createdPlan);
        $this->assertEquals(45000, (float) $createdPlan->price);
        $this->assertContains(FeatureCatalog::AI_WAITER, $createdPlan->feature_codes);
        $this->assertEquals(1000, $createdPlan->quota_limits[QuotaCatalog::KEY_PRODUCTS_COUNT]);

        // Monthly and yearly price records created
        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $createdPlan->id,
            'billing_interval' => 'monthly',
            'price' => 45000,
        ]);
        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $createdPlan->id,
            'billing_interval' => 'yearly',
            'price' => 450000,
        ]);

        // Audit log created
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            'target_type' => SubscriptionPlan::class,
            'target_id' => $createdPlan->id,
        ]);
    }

    public function test_super_admin_can_update_plan_and_sync_prices(): void
    {
        $this->actingAs($this->superAdmin);

        $payload = [
            'name' => 'Pro Updated Tier',
            'price' => 22000,
            'yearly_price' => 220000,
            'currency' => 'AMD',
            'billing_interval' => 'monthly',
            'duration_days' => 30,
            'trial_days' => 7,
            'description' => 'Updated Pro description',
            'feature_codes' => [FeatureCatalog::ORDERS_ONLINE, FeatureCatalog::ANALYTICS_BASIC],
            'quota_limits' => [QuotaCatalog::KEY_PRODUCTS_COUNT => 300],
            'is_active' => '1',
        ];

        $response = $this->post(route('superadmin.plans.update', $this->proPlan->id), $payload);
        $response->assertSessionHas('success');

        $this->proPlan->refresh();
        $this->assertEquals('Pro Updated Tier', $this->proPlan->name);
        $this->assertEquals(22000, (float) $this->proPlan->price);
        $this->assertEquals(7, $this->proPlan->trial_days);

        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $this->proPlan->id,
            'billing_interval' => 'yearly',
            'price' => 220000,
        ]);
    }

    public function test_super_admin_can_toggle_plan_active_status(): void
    {
        $this->actingAs($this->superAdmin);

        $initialState = $this->proPlan->is_active;

        $response = $this->post(route('superadmin.plans.toggle', $this->proPlan->id));
        $response->assertSessionHas('success');

        $this->proPlan->refresh();
        $this->assertEquals(! $initialState, $this->proPlan->is_active);
    }

    public function test_super_admin_cannot_delete_plan_with_assigned_subscribers(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->delete(route('superadmin.plans.destroy', $this->proPlan->id));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('subscription_plans', ['id' => $this->proPlan->id]);
    }

    public function test_super_admin_can_search_subscribers_by_query_and_status(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get(route('superadmin.subscriptions.index', [
            'search' => 'Prime',
            'status' => 'active',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Test Restaurant Prime');

        $responseEmpty = $this->get(route('superadmin.subscriptions.index', [
            'search' => 'NonExistentRestaurant99999',
        ]));
        $responseEmpty->assertStatus(200);
        $responseEmpty->assertDontSee('Test Restaurant Prime');
    }

    public function test_super_admin_can_suspend_subscription_with_mandatory_reason(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post(route('superadmin.subscriptions.suspend', $this->vendor->id), [
            'reason' => 'Chargeback initiated and terms of service breach',
        ]);

        $response->assertSessionHas('success');

        $this->subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Suspended, $this->subscription->status);

        // Feature access revoked
        $entitlement = app(EntitlementService::class)->check($this->vendor, FeatureCatalog::ORDERS_ONLINE);
        $this->assertFalse($entitlement->allowed);
        $this->assertEquals(EntitlementResult::SUBSCRIPTION_INACTIVE, $entitlement->code);

        // Lifecycle event recorded
        $this->assertDatabaseHas('subscription_lifecycle_events', [
            'subscription_id' => $this->subscription->id,
            'event' => 'suspended',
            'to_status' => 'suspended',
        ]);

        // Security audit log recorded
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => SecurityAuditLog::EVENT_VENDOR_SUSPENDED,
            'vendor_id' => $this->vendor->id,
        ]);
    }

    public function test_suspension_requires_minimum_length_reason(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post(route('superadmin.subscriptions.suspend', $this->vendor->id), [
            'reason' => 'bad', // less than 5 characters
        ]);

        $response->assertSessionHasErrors(['reason']);
        $this->subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $this->subscription->status);
    }

    public function test_super_admin_can_restore_suspended_subscription_with_reason(): void
    {
        $this->actingAs($this->superAdmin);

        // First suspend
        $this->subscription->transitionTo(SubscriptionStatus::Suspended, reason: 'Fraud check');

        // Restore
        $response = $this->post(route('superadmin.subscriptions.restore', $this->vendor->id), [
            'reason' => 'Fraud investigation cleared by merchant operations team',
        ]);

        $response->assertSessionHas('success');

        $this->subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $this->subscription->status);

        // Feature access restored
        $entitlement = app(EntitlementService::class)->check($this->vendor, FeatureCatalog::MENU_TRANSLATIONS);
        $this->assertTrue($entitlement->allowed);

        // Lifecycle event recorded
        $this->assertDatabaseHas('subscription_lifecycle_events', [
            'subscription_id' => $this->subscription->id,
            'event' => 'restored',
            'to_status' => 'active',
        ]);
    }

    public function test_super_admin_can_view_vendor_lifecycle_history(): void
    {
        $this->actingAs($this->superAdmin);

        $this->subscription->recordLifecycleEvent(
            event: 'plan_changed',
            toStatus: SubscriptionStatus::Active,
            reason: 'Administrative upgrade verified'
        );

        $response = $this->get(route('superadmin.subscriptions.lifecycle', $this->vendor->id));
        $response->assertStatus(200);
        $response->assertSee('Administrative upgrade verified');
    }

    public function test_super_admin_can_view_vendor_usage_reporting_and_reset_meter(): void
    {
        $this->actingAs($this->superAdmin);

        $quotaService = app(QuotaService::class);
        $quotaService->recordUsage($this->vendor, QuotaCatalog::KEY_ORDERS_PERIOD, 15);

        // View reporting
        $response = $this->get(route('superadmin.subscriptions.usage', $this->vendor->id));
        $response->assertStatus(200);
        $response->assertSee('Real-Time Usage Meters');

        // Reset meter
        $resetResponse = $this->post(route('superadmin.subscriptions.usage_meters.reset', [
            $this->vendor->id,
            QuotaCatalog::KEY_ORDERS_PERIOD,
        ]));
        $resetResponse->assertSessionHas('success');

        $this->assertEquals(0, $quotaService->getCurrentUsage($this->vendor, QuotaCatalog::KEY_ORDERS_PERIOD));
    }

    public function test_super_admin_can_manage_temporary_feature_and_quota_overrides(): void
    {
        $this->actingAs($this->superAdmin);

        // Feature override
        $featResponse = $this->post(route('superadmin.subscriptions.overrides.store', $this->vendor->id), [
            'feature_key' => FeatureCatalog::AI_WAITER,
            'is_enabled' => 1,
            'reason' => 'VIP Beta Trial approved by CEO',
        ]);
        $featResponse->assertSessionHas('success');
        $this->assertDatabaseHas('tenant_feature_overrides', [
            'vendor_id' => $this->vendor->id,
            'feature_key' => FeatureCatalog::AI_WAITER,
            'is_enabled' => true,
        ]);

        // Revoke feature override
        $revokeFeat = $this->delete(route('superadmin.subscriptions.overrides.destroy', [
            $this->vendor->id,
            FeatureCatalog::AI_WAITER,
        ]));
        $revokeFeat->assertSessionHas('success');
        $this->assertDatabaseMissing('tenant_feature_overrides', [
            'vendor_id' => $this->vendor->id,
            'feature_key' => FeatureCatalog::AI_WAITER,
        ]);

        // Quota override
        $quotaResponse = $this->post(route('superadmin.subscriptions.quota_overrides.store', $this->vendor->id), [
            'quota_key' => QuotaCatalog::KEY_PRODUCTS_COUNT,
            'quota_limit' => 500,
            'reason' => 'Seasonal menu expansion allowance',
        ]);
        $quotaResponse->assertSessionHas('success');
        $this->assertDatabaseHas('tenant_quota_overrides', [
            'vendor_id' => $this->vendor->id,
            'quota_key' => QuotaCatalog::KEY_PRODUCTS_COUNT,
            'quota_limit' => 500,
        ]);

        // Remove quota override
        $removeQuota = $this->delete(route('superadmin.subscriptions.quota_overrides.destroy', [
            $this->vendor->id,
            QuotaCatalog::KEY_PRODUCTS_COUNT,
        ]));
        $removeQuota->assertSessionHas('success');
        $this->assertDatabaseMissing('tenant_quota_overrides', [
            'vendor_id' => $this->vendor->id,
            'quota_key' => QuotaCatalog::KEY_PRODUCTS_COUNT,
        ]);
    }

    public function test_super_admin_can_view_all_payments_and_filter_failed_payments(): void
    {
        $this->actingAs($this->superAdmin);

        SubscriptionPayment::create([
            'vendor_id' => $this->vendor->id,
            'subscription_id' => $this->subscription->id,
            'subscription_plan_id' => $this->proPlan->id,
            'amount' => 19900,
            'currency' => 'AMD',
            'payment_method' => 'stripe',
            'invoice_number' => 'INV-FAIL-999',
            'period_start' => now(),
            'period_end' => now()->addMonth(),
            'status' => 'failed',
            'notes' => 'Card declined: Insufficient funds',
        ]);

        $response = $this->get(route('superadmin.subscriptions.payments.index', ['status' => 'failed']));
        $response->assertStatus(200);
        $response->assertSee('INV-FAIL-999');
        $response->assertSee('Failed');
    }

    public function test_super_admin_can_view_subscription_audit_logs(): void
    {
        $this->actingAs($this->superAdmin);

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: 'SuperAdmin updated subscription plan parameters for VIP tier',
            actor: $this->superAdmin,
            vendor: $this->vendor
        );

        $response = $this->get(route('superadmin.subscriptions.audit_logs'));
        $response->assertStatus(200);
        $response->assertSee('SuperAdmin updated subscription plan parameters for VIP tier');
    }

    public function test_tenant_can_view_own_subscription_page_with_meters_and_features(): void
    {
        $this->actingAs($this->vendorUser);

        $response = $this->get(route('admin.subscription.index'));
        $response->assertStatus(200);
        $response->assertSee($this->proPlan->name);
        $response->assertSee('Feature Entitlements');
        $response->assertSee('Usage & Quota Limits');
    }

    public function test_tenant_can_cancel_subscription_at_period_end(): void
    {
        $this->actingAs($this->vendorUser);

        $response = $this->post(route('admin.subscription.cancel'), [
            'immediately' => false,
            'reason' => 'Seasonal restaurant closure for winter',
        ]);

        $response->assertSessionHas('success');

        $this->subscription->refresh();
        $this->assertTrue($this->subscription->cancel_at_period_end);
        $this->assertEquals(SubscriptionStatus::CancelAtPeriodEnd, $this->subscription->status);
        $this->assertTrue($this->subscription->status->allowsAccess());

        // On next subscription page view, cancellation notice banner is displayed
        $pageResponse = $this->get(route('admin.subscription.index'));
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('Subscription Scheduled for Cancellation');
        $pageResponse->assertSee('Resume Auto-Renewal');
    }

    public function test_tenant_can_resume_auto_renewal_before_period_end(): void
    {
        $this->actingAs($this->vendorUser);

        // Put in cancel at period end
        $this->subscription->update(['cancel_at_period_end' => true]);

        $response = $this->post(route('admin.subscription.resume'));
        $response->assertSessionHas('success');

        $this->subscription->refresh();
        $this->assertFalse($this->subscription->cancel_at_period_end);
    }

    public function test_tenant_cannot_view_another_tenants_invoice(): void
    {
        $otherVendor = Vendor::create([
            'name' => 'Competitor Bistro',
            'slug' => 'competitor-bistro',
            'type' => 'restaurant',
            'email' => 'competitor_'.uniqid().'@example.com',
            'phone' => '099887766',
            'is_active' => true,
        ]);

        $otherPayment = SubscriptionPayment::create([
            'vendor_id' => $otherVendor->id,
            'subscription_plan_id' => $this->businessPlan->id,
            'amount' => 39900,
            'currency' => 'AMD',
            'payment_method' => 'arca',
            'invoice_number' => 'INV-COMPETITOR-100',
            'period_start' => now(),
            'period_end' => now()->addMonth(),
            'status' => 'completed',
        ]);

        // Attempt cross-tenant access as vendorUser
        $this->actingAs($this->vendorUser);

        $response = $this->get(route('admin.subscription.invoices.show', $otherPayment->id));
        $response->assertStatus(403);
    }

    public function test_feature_gate_blade_component_renders_correctly(): void
    {
        $this->actingAs($this->vendorUser);

        // Feature included in Pro plan: orders.online
        $allowedHtml = Blade::render(
            '<x-feature-gate feature="orders.online"><div>LIVE_ORDERS_CONTENT</div></x-feature-gate>'
        );
        $this->assertStringContainsString('LIVE_ORDERS_CONTENT', $allowedHtml);

        // Feature NOT included in Pro plan (Business only): api.access
        $deniedHtml = Blade::render(
            '<x-feature-gate feature="api.access"><div>API_SECRET_CONTENT</div></x-feature-gate>'
        );
        $this->assertStringNotContainsString('API_SECRET_CONTENT', $deniedHtml);
        $this->assertStringContainsString('Upgrade Plan', $deniedHtml);
        $this->assertStringContainsString('Requires Business Plan', $deniedHtml);
    }

    public function test_unauthorized_feature_access_denial_returns_stable_error_code(): void
    {
        // Try accessing team management which requires Business plan while vendor is on Pro plan
        $this->actingAs($this->vendorUser);

        $response = $this->postJson(route('admin.team.store'), [
            'name' => 'New Cook',
            'email' => 'cook_'.uniqid().'@example.com',
            'role' => 'chef',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'FEATURE_NOT_ENTITLED',
            'code' => 'PLAN_UPGRADE_REQUIRED',
            'feature' => 'team.manage',
        ]);
    }
}
