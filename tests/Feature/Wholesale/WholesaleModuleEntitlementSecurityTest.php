<?php

namespace Tests\Feature\Wholesale;

use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Models\SubscriptionPlan;
use App\Models\TenantModuleAccess;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleModuleEntitlementSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::first();

        // Vendor A: Normal tenant (No wholesale access initially)
        $this->vendorA = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Vendor B: Another tenant
        $this->vendorB = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => $plan->slug,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Superadmin user
        $this->superadmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@platform.am',
            'password' => bcrypt('password123'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_tenant_without_access_is_denied_wholesale_web_access(): void
    {
        // Owner A attempts to access wholesale dashboard
        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.wholesale.dashboard'));

        $response->assertStatus(403);
    }

    public function test_tenant_without_access_is_denied_wholesale_api_access(): void
    {
        // JSON request from unentitled tenant
        $response = $this->actingAs($this->ownerA)
            ->getJson(route('admin.wholesale.dashboard'));

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'code' => 'WHOLESALE_NOT_ENTITLED',
        ]);
    }

    public function test_tenant_without_access_has_zero_wholesale_visibility_in_sidebar(): void
    {
        // View main admin dashboard
        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);

        // Assert 0% visibility: no menu item, no upgrade badge, no link
        $response->assertDontSee('B2B Wholesale & Distribution');
        $response->assertDontSee(route('admin.wholesale.dashboard'));
        $response->assertDontSee(route('admin.wholesale.sellers.index'));
    }

    public function test_tenant_admin_cannot_self_grant_wholesale_access(): void
    {
        // Tenant owner attempts to POST to Superadmin grant endpoint
        $response = $this->actingAs($this->ownerA)
            ->post(route('superadmin.wholesale.tenants.grant', $this->vendorA), [
                'reason' => 'I want wholesale access',
            ]);

        // Denied by role middleware (EnsureRole: superadmin)
        $response->assertStatus(403);
        $this->assertFalse(WholesaleAccessService::isEntitled($this->vendorA));
    }

    public function test_superadmin_can_view_wholesale_access_panel_and_grant_access(): void
    {
        // Superadmin views access management panel
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.wholesale.tenants.index'));

        $response->assertStatus(200);
        $response->assertSee('B2B Wholesale POS — Tenant Access Management');
        $response->assertSee('Bistro Alpha');

        // Superadmin grants access to Vendor A
        $grantResponse = $this->actingAs($this->superadmin)
            ->post(route('superadmin.wholesale.tenants.grant', $this->vendorA), [
                'expires_at' => now()->addYear()->format('Y-m-d'),
                'reason' => 'Annual wholesale distribution agreement signed',
            ]);

        $grantResponse->assertRedirect();
        $this->assertTrue(WholesaleAccessService::isEntitled($this->vendorA));

        // Audit log must exist
        $this->assertDatabaseHas('tenant_module_access_logs', [
            'vendor_id' => $this->vendorA->id,
            'module_key' => 'wholesale_pos',
            'action' => 'granted',
            'acted_by' => $this->superadmin->id,
        ]);
    }

    public function test_tenant_with_active_entitlement_can_access_wholesale_dashboard_and_see_sidebar(): void
    {
        // Grant access via service
        app(WholesaleAccessService::class)->grantAccess(
            $this->vendorA,
            $this->superadmin,
            now()->addMonths(6),
            'Test grant'
        );

        $this->assertTrue(WholesaleAccessService::isEntitled($this->vendorA));

        // Web access now succeeds
        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.wholesale.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('B2B Wholesale & Distribution');
        $response->assertSee('Registered Seller Companies');

        // Sidebar now renders the B2B Wholesale group
        $sidebarResponse = $this->actingAs($this->ownerA)
            ->get(route('admin.dashboard'));

        $sidebarResponse->assertStatus(200);
        $sidebarResponse->assertSee('B2B Wholesale & Distribution');
        $sidebarResponse->assertSee(route('admin.wholesale.sellers.index'));
    }

    public function test_revoked_entitlement_immediately_blocks_access(): void
    {
        $service = app(WholesaleAccessService::class);
        $service->grantAccess($this->vendorA, $this->superadmin, now()->addYear(), 'Initial grant');
        $this->assertTrue(WholesaleAccessService::isEntitled($this->vendorA));

        // Revoke access
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.wholesale.tenants.revoke', $this->vendorA), [
                'reason' => 'Contract terminated',
            ]);

        $this->assertFalse(WholesaleAccessService::isEntitled($this->vendorA));

        // Access now 403
        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.wholesale.dashboard'));

        $response->assertStatus(403);

        $this->assertDatabaseHas('tenant_module_access_logs', [
            'vendor_id' => $this->vendorA->id,
            'action' => 'revoked',
        ]);
    }

    public function test_suspended_entitlement_immediately_blocks_access(): void
    {
        $service = app(WholesaleAccessService::class);
        $service->grantAccess($this->vendorA, $this->superadmin, now()->addYear(), 'Initial grant');

        // Suspend access
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.wholesale.tenants.suspend', $this->vendorA), [
                'reason' => 'Payment overdue',
            ]);

        $this->assertFalse(WholesaleAccessService::isEntitled($this->vendorA));

        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.wholesale.dashboard'));

        $response->assertStatus(403);
    }

    public function test_expired_entitlement_is_automatically_blocked(): void
    {
        // Directly create an expired record
        TenantModuleAccess::create([
            'vendor_id' => $this->vendorA->id,
            'module_key' => 'wholesale_pos',
            'is_enabled' => true,
            'enabled_at' => now()->subMonths(2),
            'expires_at' => now()->subDay(), // Expired yesterday
            'granted_by' => $this->superadmin->id,
            'reason' => 'Trial license',
        ]);

        $this->assertFalse(WholesaleAccessService::isEntitled($this->vendorA));

        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.wholesale.dashboard'));

        $response->assertStatus(403);
    }

    public function test_vendor_b_remains_blocked_when_vendor_a_is_granted(): void
    {
        // Grant only to Vendor A
        app(WholesaleAccessService::class)->grantAccess($this->vendorA, $this->superadmin);

        $this->assertTrue(WholesaleAccessService::isEntitled($this->vendorA));
        $this->assertFalse(WholesaleAccessService::isEntitled($this->vendorB));

        // Vendor B owner receives 403
        $response = $this->actingAs($this->ownerB)
            ->get(route('admin.wholesale.dashboard'));

        $response->assertStatus(403);
    }
}
