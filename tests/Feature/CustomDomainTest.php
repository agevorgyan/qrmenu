<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor1;

    protected Vendor $vendor2;

    protected User $owner1;

    protected User $owner2;

    protected Location $location1;

    protected function setUp(): void
    {
        parent::setUp();

        // Vendor 1: Parkside Rest with custom domain
        $this->vendor1 = Vendor::create([
            'name' => 'Parkside Restaurant',
            'slug' => 'parkside-rest',
            'email' => 'info@parkside.rest',
            'custom_domain' => 'menu.parkside.rest',
            'is_active' => true,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
        ]);

        $this->owner1 = User::create([
            'vendor_id' => $this->vendor1->id,
            'name' => 'Parkside Owner',
            'email' => 'owner@parkside.rest',
            'password' => Hash::make('secret123'),
            'role' => 'vendor_owner',
        ]);

        $this->location1 = Location::create([
            'vendor_id' => $this->vendor1->id,
            'name' => 'Downtown Branch',
            'slug' => 'downtown',
            'is_active' => true,
        ]);

        $cat1 = Category::create([
            'vendor_id' => $this->vendor1->id,
            'name' => 'Steaks & BBQ',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::create([
            'vendor_id' => $this->vendor1->id,
            'category_id' => $cat1->id,
            'name' => 'Ribeye Steak',
            'price' => 7500,
            'is_available' => true,
        ]);

        // Vendor 2: Bistro Yerevan without custom domain
        $this->vendor2 = Vendor::create([
            'name' => 'Bistro Yerevan',
            'slug' => 'bistro-yerevan',
            'email' => 'info@bistro.am',
            'custom_domain' => null,
            'is_active' => true,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
        ]);

        $this->owner2 = User::create([
            'vendor_id' => $this->vendor2->id,
            'name' => 'Bistro Owner',
            'email' => 'owner@bistro.am',
            'password' => Hash::make('secret123'),
            'role' => 'vendor_owner',
        ]);
    }

    public function test_vendor_url_helpers_with_and_without_custom_domain(): void
    {
        $this->assertTrue($this->vendor1->hasCustomDomain());
        $this->assertEquals('menu.parkside.rest', $this->vendor1->getCleanCustomDomain());
        $this->assertStringContainsString('menu.parkside.rest', $this->vendor1->getStorefrontUrl());
        $this->assertStringContainsString('menu.parkside.rest/downtown', $this->vendor1->getStorefrontUrl('downtown'));
        $this->assertStringContainsString('menu.parkside.rest/admin', $this->vendor1->getAdminUrl());

        $this->assertFalse($this->vendor2->hasCustomDomain());
        $this->assertNull($this->vendor2->getCleanCustomDomain());
        $this->assertStringContainsString('/m/bistro-yerevan', $this->vendor2->getStorefrontUrl());
        $this->assertStringContainsString('/admin/dashboard', $this->vendor2->getAdminUrl());
    }

    public function test_vendor_can_save_and_normalize_custom_domain(): void
    {
        $this->actingAs($this->owner2);

        $response = $this->post(route('admin.settings.update'), [
            'name' => 'Bistro Yerevan Updated',
            'custom_domain' => 'https://MENU.Bistro.AM/extra/',
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);

        $response->assertSessionHasNoErrors();
        $this->vendor2->refresh();
        $this->assertEquals('menu.bistro.am', $this->vendor2->custom_domain);
    }

    public function test_custom_domain_validation_rules(): void
    {
        $this->actingAs($this->owner2);

        // 1. Forbidden main domain
        $response = $this->post(route('admin.settings.update'), [
            'name' => 'Bistro Yerevan',
            'custom_domain' => 'menu.elab.am',
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);
        $response->assertSessionHasErrors('custom_domain');

        // 2. Duplicate domain from another vendor
        $response = $this->post(route('admin.settings.update'), [
            'name' => 'Bistro Yerevan',
            'custom_domain' => 'menu.parkside.rest',
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);
        $response->assertSessionHasErrors('custom_domain');

        // 3. Invalid format
        $response = $this->post(route('admin.settings.update'), [
            'name' => 'Bistro Yerevan',
            'custom_domain' => 'not-a-valid-domain',
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
        ]);
        $response->assertSessionHasErrors('custom_domain');
    }

    public function test_custom_domain_dns_check_endpoint(): void
    {
        $this->actingAs($this->owner1);

        $response = $this->postJson(route('admin.settings.domain.check'), [
            'domain' => 'menu.parkside.rest',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'domain',
            'server_ip',
            'message',
        ]);
        $this->assertEquals('menu.parkside.rest', $response->json('domain'));
    }

    public function test_custom_domain_storefront_menu_resolves_at_root(): void
    {
        // Visit root / with Host: menu.parkside.rest
        $response = $this->get('http://menu.parkside.rest/');

        $response->assertOk();
        $response->assertSee('Parkside Restaurant');
        $response->assertSee('Ribeye Steak');
    }

    public function test_custom_domain_location_resolves_cleanly(): void
    {
        // Visit /downtown on menu.parkside.rest
        $response = $this->get('http://menu.parkside.rest/downtown');

        $response->assertOk();
        $response->assertSee('Parkside Restaurant');
        $response->assertSee('Downtown Branch');

        // Nonexistent location on custom domain should return 404
        $notFound = $this->get('http://menu.parkside.rest/non-existent-branch');

        $notFound->assertNotFound();
    }

    public function test_custom_domain_login_renders_whitelabel_branding(): void
    {
        $response = $this->withSession(['app_locale' => 'hy', 'locale' => 'hy'])->get('http://menu.parkside.rest/login');

        $response->assertOk();
        $response->assertSee('Parkside Restaurant');
        $response->assertSee('Կառավարման Վահանակ');
        // Generic SaaS demo button should not be present on white-label login
        $response->assertDontSee('Quick Demo One-Click Login');
    }

    public function test_custom_domain_admin_rejects_users_from_other_vendors(): void
    {
        // Attempt login on menu.parkside.rest using Bistro Yerevan credentials
        $response = $this->post('http://menu.parkside.rest/login', [
            'email' => 'owner@bistro.am',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_custom_domain_admin_allows_its_own_vendor_owner(): void
    {
        // Attempt login on menu.parkside.rest using Parkside Owner credentials
        $response = $this->post('http://menu.parkside.rest/login', [
            'email' => 'owner@parkside.rest',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->owner1);

        // Access dashboard on custom domain
        $dashResponse = $this->get('http://menu.parkside.rest/admin/dashboard');

        $dashResponse->assertOk();
        $dashResponse->assertSee('Parkside Restaurant');
    }

    public function test_superadmin_access_on_custom_domain_redirects_to_platform_domain(): void
    {
        config(['app.url' => 'https://menu.elab.am']);

        $response = $this->get('http://menu.parkside.rest/superadmin/dashboard');

        $response->assertRedirect();
        $this->assertStringContainsString('menu.elab.am/superadmin/dashboard', $response->headers->get('Location'));
    }
}
