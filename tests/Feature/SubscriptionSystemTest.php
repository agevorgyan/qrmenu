<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_new_vendor_registration_assigns_14_day_trial_and_plan(): void
    {
        $response = $this->post('/register', [
            'type' => 'restaurant',
            'name' => 'Trial Bistro',
            'expected_locations_count' => 1,
            'operating_address' => 'Yerevan Street 1',
            'legal_name' => 'Trial LLC',
            'legal_address' => 'Yerevan Street 1',
            'tax_id' => '12345678',
            'director_name' => 'Arman Armanyan',
            'contact_person_name' => 'Arman Armanyan',
            'phone' => '091999999',
            'email' => 'arman@trialbistro.am',
            'subscription_plan' => 'pro',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/email/verify');

        $vendor = Vendor::where('email', 'arman@trialbistro.am')->first();
        $this->assertNotNull($vendor);
        $this->assertEquals('pro', $vendor->subscription_plan);
        $this->assertEquals('trialing', $vendor->subscription_status);
        $this->assertTrue($vendor->isTrialing());
        $this->assertFalse($vendor->isExpired());
        $this->assertEquals(14, $vendor->daysLeft());
    }

    public function test_superadmin_can_create_and_update_subscription_plans(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@qrmenu.local',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        $response = $this->actingAs($superadmin)->post(route('superadmin.plans.store'), [
            'name' => 'VIP Enterprise',
            'price' => 50000,
            'billing_interval' => 'monthly',
            'duration_days' => 30,
            'trial_days' => 14,
            'description' => 'Enterprise plan for networks',
            'features' => "Custom Domain\nDedicated IP\n24/7 Phone Support",
        ]);

        $response->assertRedirect();

        $plan = SubscriptionPlan::where('slug', 'vip-enterprise')->first();
        $this->assertNotNull($plan);
        $this->assertEquals(50000, $plan->price);
        $this->assertCount(3, $plan->features);
    }

    public function test_expired_vendor_is_redirected_to_subscription_page(): void
    {
        $vendor = Vendor::create([
            'name' => 'Expired Restaurant',
            'slug' => 'expired-restaurant',
            'type' => 'restaurant',
            'email' => 'expired@restaurant.am',
            'subscription_plan' => 'basic',
            'subscription_status' => 'expired',
            'is_active' => false,
            'trial_ends_at' => now()->subDays(5),
            'subscription_expires_at' => now()->subDays(5),
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Expired Owner',
            'email' => 'expired@restaurant.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.menu.index'));
        $response->assertRedirect(route('admin.subscription'));

        // Accessing subscription page itself should be allowed
        $subResponse = $this->actingAs($user)->get(route('admin.subscription'));
        $subResponse->assertStatus(200);
    }

    public function test_basic_plan_vendor_cannot_access_orders_or_customers(): void
    {
        $basicPlan = SubscriptionPlan::where('slug', 'basic')->first();

        $vendor = Vendor::create([
            'name' => 'Basic Cafe',
            'slug' => 'basic-cafe',
            'type' => 'cafe',
            'email' => 'info@basiccafe.am',
            'subscription_plan' => 'basic',
            'subscription_plan_id' => $basicPlan->id,
            'subscription_status' => 'active',
            'is_active' => true,
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Basic Owner',
            'email' => 'info@basiccafe.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        // Menu builder should be accessible for Basic vendor
        $menuRes = $this->actingAs($user)->get(route('admin.menu.index'));
        $menuRes->assertStatus(200);

        // Orders page should be blocked & redirected for Basic vendor
        $ordersRes = $this->actingAs($user)->get(route('admin.orders.index'));
        $ordersRes->assertRedirect(route('admin.subscription'));

        // Customers page should be blocked & redirected for Basic vendor
        $customersRes = $this->actingAs($user)->get(route('admin.customers.index'));
        $customersRes->assertRedirect(route('admin.subscription'));
    }
}
