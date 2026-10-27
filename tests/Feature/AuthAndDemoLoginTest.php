<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndDemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_page_renders_successfully_and_does_not_contain_superadmin_demo(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('QRMenu SaaS');
        $response->assertSee('/demo/login');
        $response->assertDontSee('fillCreds(\'admin@qrmenu.local\'', false);
        $response->assertDontSee('Super Admin Panel');
    }

    public function test_register_page_renders_successfully_with_demo_link(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Partner');
        $response->assertSee('/demo/login');
        $response->assertSee('14-Day Free Trial');
    }

    public function test_demo_login_page_renders_with_vendor_accounts_and_without_superadmin(): void
    {
        $response = $this->get('/demo/login');

        $response->assertStatus(200);
        $response->assertSee('Bistro Yerevan');
        $response->assertSee('owner@bistro.am');
        $response->assertSee('Cascades Branch');
        $response->assertSee('manager@bistro.am');
        $response->assertDontSee('admin@qrmenu.local');
        $response->assertDontSee('Super Admin');
    }

    public function test_demo_login_as_vendor_owner_authenticates_and_redirects(): void
    {
        $response = $this->post('/demo/login', [
            'role' => 'owner',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertEquals('owner@bistro.am', auth()->user()->email);
        $this->assertTrue(auth()->user()->isVendorOwner());
    }

    public function test_demo_login_as_branch_manager_authenticates_and_redirects(): void
    {
        $response = $this->post('/demo/login', [
            'role' => 'manager',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertEquals('manager@bistro.am', auth()->user()->email);
        $this->assertTrue(auth()->user()->isManager());
    }

    public function test_demo_login_cannot_be_used_to_authenticate_as_superadmin(): void
    {
        $response = $this->post('/demo/login', [
            'role' => 'superadmin',
        ]);

        // Role defaults back to owner, never superadmin
        $this->assertAuthenticated();
        $this->assertNotEquals('superadmin', auth()->user()->role);
        $this->assertFalse(auth()->user()->isSuperAdmin());
    }

    public function test_login_rate_limiting_redirects_back_with_friendly_message_instead_of_raw_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', [
                'email' => 'wrong@test.com',
                'password' => 'wrongpass',
            ]);
        }

        // The 11th request triggers rate limiting
        $response = $this->post('/login', [
            'email' => 'wrong@test.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }
}
