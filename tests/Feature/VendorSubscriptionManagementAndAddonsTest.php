<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionDowngradedMail;
use App\Mail\SubscriptionDowngradeScheduledMail;
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
use Illuminate\Support\Facades\Mail;
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

    public function test_vendor_owner_cannot_upgrade_plan_directly_without_payment(): void
    {
        $this->assertEquals($this->basicPlan->id, $this->vendor->subscription_plan_id);

        $response = $this->actingAs($this->vendorOwner)->post(route('admin.subscription.change_plan'), [
            'subscription_plan_id' => $this->proPlan->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->vendor->refresh();
        $this->assertEquals($this->basicPlan->id, $this->vendor->subscription_plan_id);
    }

    public function test_vendor_owner_can_schedule_downgrade_effective_at_period_end_and_is_notified(): void
    {
        Mail::fake();

        // Put vendor on Pro plan with active period until 30 days in future
        $subService = app(SubscriptionService::class);
        $subscription = $subService->getOrCreateForVendor($this->vendor);
        $subscription->update([
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(30),
        ]);
        $subService->syncVendor($subscription);
        $this->vendor->refresh();
        $this->assertEquals($this->proPlan->id, $this->vendor->subscription_plan_id);

        // Vendor requests downgrade to Basic
        $response = $this->actingAs($this->vendorOwner)->post(route('admin.subscription.change_plan'), [
            'subscription_plan_id' => $this->basicPlan->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Current plan MUST remain Pro during the active period
        $this->vendor->refresh();
        $subscription->refresh();
        $this->assertEquals($this->proPlan->id, $this->vendor->subscription_plan_id);

        // Downgrade is scheduled in metadata
        $this->assertEquals($this->basicPlan->id, $subscription->metadata['pending_downgrade_plan_id'] ?? null);

        // Partner is notified via email
        Mail::assertQueued(SubscriptionDowngradeScheduledMail::class);
    }

    public function test_scheduled_downgrade_executes_automatically_when_period_expires(): void
    {
        Mail::fake();

        $subService = app(SubscriptionService::class);
        $subscription = $subService->getOrCreateForVendor($this->vendor);
        $subscription->update([
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->subDay(), // period expired yesterday!
            'metadata' => [
                'pending_downgrade_plan_id' => $this->basicPlan->id,
                'pending_downgrade_plan_name' => $this->basicPlan->name,
                'pending_downgrade_scheduled_at' => now()->subDays(5)->toIso8601String(),
                'pending_downgrade_effective_at' => now()->subDay()->toIso8601String(),
            ],
        ]);
        $subService->syncVendor($subscription);

        // Reconcile expired subscriptions
        $result = $subService->reconcileExpiredSubscriptions(graceDays: 3);
        $this->assertGreaterThan(0, $result['transitioned']);

        // Subscription has now automatically downgraded to Basic
        $subscription->refresh();
        $this->vendor->refresh();
        $this->assertEquals($this->basicPlan->id, $subscription->subscription_plan_id);
        $this->assertEquals($this->basicPlan->id, $this->vendor->subscription_plan_id);
        $this->assertNull($subscription->metadata['pending_downgrade_plan_id'] ?? null);

        // Partner is notified that downgrade executed
        Mail::assertQueued(SubscriptionDowngradedMail::class);
    }

    public function test_vendor_can_cancel_scheduled_downgrade(): void
    {
        $subService = app(SubscriptionService::class);
        $subscription = $subService->getOrCreateForVendor($this->vendor);
        $subscription->update([
            'subscription_plan_id' => $this->proPlan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
            'metadata' => [
                'pending_downgrade_plan_id' => $this->basicPlan->id,
            ],
        ]);

        $response = $this->actingAs($this->vendorOwner)->post(route('admin.subscription.cancel_downgrade'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $subscription->refresh();
        $this->assertNull($subscription->metadata['pending_downgrade_plan_id'] ?? null);
    }

    public function test_superadmin_can_confirm_offline_payment_and_upgrade_subscription(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $subService = app(SubscriptionService::class);
        $subscription = $subService->getOrCreateForVendor($this->vendor);
        $this->assertEquals($this->basicPlan->id, $this->vendor->subscription_plan_id);

        // Create pending offline payment for Pro plan
        $payment = SubscriptionPayment::create([
            'vendor_id' => $this->vendor->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $this->proPlan->id,
            'amount' => 19900,
            'currency' => 'AMD',
            'payment_method' => 'bank_transfer',
            'invoice_number' => 'INV-TEST-OFFLINE',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'status' => 'pending',
        ]);

        // SuperAdmin confirms offline payment
        $response = $this->actingAs($superadmin)->post(route('superadmin.subscriptions.payments.confirm', $payment->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals('completed', $payment->status);

        $subscription->refresh();
        $this->vendor->refresh();
        $this->assertEquals($this->proPlan->id, $this->vendor->subscription_plan_id);
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
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
