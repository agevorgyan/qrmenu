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

    public function test_login_page_renders_successfully_and_does_not_contain_demo_links(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('QRMenu SaaS');
        $response->assertDontSee('/demo/login');
        $response->assertDontSee('Try Demo');
        $response->assertDontSee('fillCreds(\'admin@qrmenu.local\'', false);
        $response->assertDontSee('Super Admin Panel');
    }

    public function test_register_page_renders_successfully_without_demo_links(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Partner');
        $response->assertDontSee('/demo/login');
        $response->assertDontSee('Try Demo');
        $response->assertSee('14-Day Free Trial');
    }

    public function test_demo_login_endpoints_are_disabled(): void
    {
        $this->get('/demo/login')->assertStatus(404);
        $this->post('/demo/login', ['role' => 'owner'])->assertStatus(404);
    }

    public function test_vendor_owner_can_login_with_standard_email_and_password(): void
    {
        session(['captcha_answer' => '12']);

        $response = $this->post('/login', [
            'email' => 'owner@bistro.am',
            'password' => 'password',
            'captcha' => '12',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertEquals('owner@bistro.am', auth()->user()->email);
        $this->assertTrue(auth()->user()->isVendorOwner());
    }

    public function test_branch_manager_can_login_with_standard_email_and_password(): void
    {
        session(['captcha_answer' => '12']);

        $response = $this->post('/login', [
            'email' => 'manager@bistro.am',
            'password' => 'password',
            'captcha' => '12',
        ]);

        $response->assertRedirect('/pos?mode=manager');
        $this->assertAuthenticated();
        $this->assertEquals('manager@bistro.am', auth()->user()->email);
        $this->assertTrue(auth()->user()->isManager());
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
