<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EventInquiry;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_admin_can_create_customer_with_birthdate_and_address(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Admin Vendor',
            'slug' => 'admin-vendor',
            'email' => 'admin@vendor.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'admin@vendor.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
        ]);

        $location = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Location 1',
            'slug' => 'location-1',
        ]);

        $response = $this->actingAs($user)->post(route('admin.customers.store'), [
            'name' => 'Aram Vardanyan',
            'location_id' => $location->id,
            'phone' => '091998877',
            'email' => 'aram@example.com',
            'birthdate' => '1990-11-25',
            'address' => 'Abovyan 22, Apt 14',
            'notes' => 'Preferred client',
            'marketing_opt_in' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'vendor_id' => $vendor->id,
            'name' => 'Aram Vardanyan',
            'phone' => '091998877',
            'email' => 'aram@example.com',
            'address' => 'Abovyan 22, Apt 14',
        ]);

        $createdCustomer = Customer::where('phone', '091998877')->first();
        $this->assertNotNull($createdCustomer);
        $this->assertEquals('1990-11-25', $createdCustomer->birthdate?->format('Y-m-d'));
    }

    public function test_admin_can_update_customer_birthdate_and_address(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Admin Vendor',
            'slug' => 'admin-vendor',
            'email' => 'admin@vendor.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'admin@vendor.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
        ]);

        $customer = Customer::create([
            'vendor_id' => $vendor->id,
            'name' => 'Suren Petrosyan',
            'phone' => '094112233',
        ]);

        $response = $this->actingAs($user)->post(route('admin.customers.update', $customer->id), [
            'name' => 'Suren Petrosyan',
            'phone' => '094112233',
            'birthdate' => '1988-04-12',
            'address' => 'Baghramyan 50, Apt 3',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'address' => 'Baghramyan 50, Apt 3',
        ]);

        $customer->refresh();
        $this->assertEquals('1988-04-12', $customer->birthdate?->format('Y-m-d'));
    }

    public function test_customer_show_page_displays_birthdate_and_address(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Admin Vendor',
            'slug' => 'admin-vendor',
            'email' => 'admin@vendor.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'admin@vendor.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
        ]);

        $customer = Customer::create([
            'vendor_id' => $vendor->id,
            'name' => 'Lilit Hovhannisyan',
            'phone' => '098776655',
            'email' => 'lilit@example.com',
            'birthdate' => '1992-06-15',
            'address' => 'Mashtots 40',
        ]);

        $response = $this->actingAs($user)->get(route('admin.customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('15 Jun 1992');
        $response->assertSee('Mashtots 40');
    }

    public function test_admin_can_search_customers(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Search Vendor',
            'slug' => 'search-vendor',
            'email' => 'search@vendor.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'search@vendor.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
        ]);

        Customer::create([
            'vendor_id' => $vendor->id,
            'name' => 'Armen Petrosyan',
            'phone' => '091112233',
            'email' => 'armen@example.com',
        ]);

        Customer::create([
            'vendor_id' => $vendor->id,
            'name' => 'Sona Sargsyan',
            'phone' => '093334455',
            'email' => 'sona@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('admin.customers.index', ['search' => 'Armen']));
        $response->assertStatus(200);
        $response->assertSee('Armen Petrosyan');
        $response->assertDontSee('Sona Sargsyan');

        $phoneResponse = $this->actingAs($user)->get(route('admin.customers.index', ['search' => '093334455']));
        $phoneResponse->assertStatus(200);
        $phoneResponse->assertSee('Sona Sargsyan');
        $phoneResponse->assertDontSee('Armen Petrosyan');
    }

    public function test_admin_can_view_customer_profile_with_loyalty_and_event_inquiries(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => 'Profile Vendor',
            'slug' => 'profile-vendor',
            'email' => 'profile@vendor.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'profile@vendor.com',
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
        ]);

        $customer = Customer::create([
            'vendor_id' => $vendor->id,
            'name' => 'David Hakobyan',
            'phone' => '094556677',
            'email' => 'david@example.com',
            'loyalty_points' => 350,
            'loyalty_tier' => 'Gold',
        ]);

        EventInquiry::create([
            'vendor_id' => $vendor->id,
            'customer_id' => $customer->id,
            'customer_name' => 'David Hakobyan',
            'customer_phone' => '094556677',
            'reference' => 'EVT-999',
            'status' => 'confirmed',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(3)->format('Y-m-d'),
            'guest_count' => 12,
            'budget' => 150000,
            'budget_currency' => 'AMD',
        ]);

        $response = $this->actingAs($user)->get(route('admin.customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('David Hakobyan');
        $response->assertSee('EVT-999');
        $response->assertSee('Gold');
    }
}
