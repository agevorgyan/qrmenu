<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\MenuTemplate;
use App\Models\PaymentAttempt;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscriptionAddon;
use App\Services\SubscriptionService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorSubscriptionManagementAndAddonsTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $basicPlan;

    protected SubscriptionPlan $proPlan;

    protected SubscriptionPlan $advancedPlan;

    protected Vendor $vendor;

    protected User $vendorOwner;

    protected User $staffUser;

    protected Vendor $otherVendor;

    protected User $otherOwner;

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
        $this->advancedPlan = SubscriptionPlan::where('slug', 'business')->first()
            ?? SubscriptionPlan::where('slug', 'advanced')->first()
            ?? SubscriptionPlan::first();

        // Vendor A
        $this->vendor = Vendor::create([
            'name' => 'Gayane Kitchen Test',
            'slug' => 'gayane-kitchen-test',
            'type' => 'restaurant',
            'email' => 'gayane_'.uniqid().'@test.am',
            'phone' => '091000111',
            'subscription_plan' => $this->basicPlan->slug,
            'subscription_plan_id' => $this->basicPlan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->vendorOwner = User::create([
            'name' => 'Gayane Owner',
            'email' => 'owner_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->staffUser = User::create([
            'name' => 'Gayane Staff',
            'email' => 'staff_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Vendor B
        $this->otherVendor = Vendor::create([
            'name' => 'Other Bistro',
            'slug' => 'other-bistro',
            'type' => 'restaurant',
            'email' => 'other_'.uniqid().'@test.am',
            'subscription_plan' => $this->basicPlan->slug,
            'subscription_plan_id' => $this->basicPlan->id,
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'otherowner_'.uniqid().'@test.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->otherVendor->id,
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_vendor_owner_can_view_subscription_page_with_addons_and_plans(): void
    {
        $response = $this->actingAs($this->vendorOwner)->get(route('admin.subscription'));

        $response->assertStatus(200);
        $response->assertSee(__('Subscription & Billing'));
        $response->assertSee(__('Subscription Add-ons'));
        $response->assertSee(__('Available Plans'));
    }

    public function test_vendor_owner_can_change_subscription_plan_directly(): void
    {
        $this->assertEquals($this->basicPlan->id, $this->vendor->subscription_plan_id);

        $response = $this->actingAs($this->vendorOwner)->post(route('admin.subscription.change_plan'), [
            'subscription_plan_id' => $this->proPlan->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->vendor->refresh();
        $this->assertEquals($this->proPlan->id, $this->vendor->subscription_plan_id);
        $this->assertEquals($this->proPlan->slug, $this->vendor->subscription_plan);
    }

    public function test_vendor_owner_can_order_and_activate_subscription_addon(): void
    {
        $addon = SubscriptionAddon::firstOrCreate(
            ['code' => 'addon.ai_credits_1000'],
            [
                'name' => 'Additional AI Credits (+1,000)',
                'addon_type' => 'usage_pack',
                'monthly_price' => 0.00,
                'currency' => 'AMD',
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($this->vendorOwner)->post(route('admin.subscription.addons.store'), [
            'subscription_addon_id' => $addon->id,
            'quantity' => 2,
            'billing_period' => 'monthly',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_subscription_addons', [
            'vendor_id' => $this->vendor->id,
            'subscription_addon_id' => $addon->id,
            'quantity' => 2,
            'status' => 'active',
        ]);
    }

    public function test_vendor_owner_can_cancel_their_active_addon(): void
    {
        $addon = SubscriptionAddon::firstOrCreate(
            ['code' => 'addon.staff_accounts_5'],
            [
                'name' => 'Additional Staff Accounts (+5)',
                'addon_type' => 'limit_boost',
                'monthly_price' => 0.00,
                'currency' => 'AMD',
                'is_active' => true,
            ]
        );

        $vendorAddon = VendorSubscriptionAddon::create([
            'vendor_id' => $this->vendor->id,
            'subscription_addon_id' => $addon->id,
            'quantity' => 1,
            'billing_period' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $response = $this->actingAs($this->vendorOwner)->post(route('admin.subscription.addons.cancel', $vendorAddon));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $vendorAddon->refresh();
        $this->assertEquals('cancelled', $vendorAddon->status);
    }

    public function test_tenant_isolation_prevents_cancelling_other_vendor_addon(): void
    {
        $addon = SubscriptionAddon::firstOrCreate(
            ['code' => 'addon.locations_1'],
            [
                'name' => 'Additional Location (+1)',
                'addon_type' => 'limit_boost',
                'monthly_price' => 0.00,
                'currency' => 'AMD',
                'is_active' => true,
            ]
        );

        $otherVendorAddon = VendorSubscriptionAddon::create([
            'vendor_id' => $this->otherVendor->id,
            'subscription_addon_id' => $addon->id,
            'quantity' => 1,
            'billing_period' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // Vendor owner A tries to cancel Vendor B's addon
        $response = $this->actingAs($this->vendorOwner)->post(route('admin.subscription.addons.cancel', $otherVendorAddon));

        $response->assertStatus(403);
        $otherVendorAddon->refresh();
        $this->assertEquals('active', $otherVendorAddon->status);
    }

    public function test_non_owner_staff_cannot_change_plan_or_addons(): void
    {
        // Try plan change as staff
        $planResponse = $this->actingAs($this->staffUser)->post(route('admin.subscription.change_plan'), [
            'subscription_plan_id' => $this->proPlan->id,
        ]);
        $planResponse->assertStatus(403);

        // Try add-on addition as staff
        $addon = SubscriptionAddon::first();
        $addonResponse = $this->actingAs($this->staffUser)->post(route('admin.subscription.addons.store'), [
            'subscription_addon_id' => $addon->id,
            'quantity' => 1,
        ]);
        $addonResponse->assertStatus(403);
    }

    public function test_subscription_payment_callback_redirects_to_admin_subscription(): void
    {
        $subscriptionService = app(SubscriptionService::class);
        $subscription = $subscriptionService->getOrCreateForVendor($this->vendor);

        $subPayment = SubscriptionPayment::create([
            'vendor_id' => $this->vendor->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $this->proPlan->id,
            'amount' => 19900,
            'currency' => 'AMD',
            'payment_method' => 'arca',
            'invoice_number' => 'INV-TEST-001',
            'status' => 'pending',
        ]);

        $attempt = PaymentAttempt::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'subscription_id' => $subPayment->id,
            'gateway' => 'arca',
            'merchant_reference' => 'SUB-REF-'.Str::random(8),
            'amount' => 19900,
            'currency' => 'AMD',
            'status' => PaymentStatus::Paid,
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(60),
        ]);

        $response = $this->get(route('client.payment.callback', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $attempt->merchant_reference,
        ]));

        $response->assertRedirect(route('admin.subscription'));
        $response->assertSessionHas('success');
    }
}
