<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\PosDevice;
use App\Models\PosDeviceSession;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosAuthenticationAndDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $waiter;

    protected User $cashier;

    protected PosDevice $device;

    protected Vendor $otherVendor;

    protected User $otherVendorUser;

    protected PosDevice $otherDevice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $businessPlan = SubscriptionPlan::where('slug', 'business')->first()
            ?? SubscriptionPlan::create([
                'name' => 'Business',
                'slug' => 'business',
                'price' => 20000,
                'currency' => 'AMD',
                'billing_period' => 'monthly',
                'features' => ['orders', 'locations', 'team', 'pos', 'pos.access'],
                'is_active' => true,
            ]);

        $this->vendor = Vendor::create([
            'name' => 'Sevan Seafood Grill',
            'slug' => 'sevan-seafood-grill',
            'email' => 'pos@sevanseafood.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Lake Pier Branch',
            'slug' => 'lake-pier-branch',
            'table_count' => 20,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Armen Owner',
            'email' => 'armen@sevanseafood.am',
            'password' => Hash::make('Password123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('1111'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->waiter = User::create([
            'name' => 'Vahan Waiter',
            'display_name' => 'Vahan W.',
            'email' => 'vahan@sevanseafood.am',
            'password' => Hash::make('Password123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('2222'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->cashier = User::create([
            'name' => 'Lilit Cashier',
            'display_name' => 'Lilit C.',
            'email' => 'lilit@sevanseafood.am',
            'password' => Hash::make('Password123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'pin_code' => Hash::make('3333'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->device = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Main POS Station',
            'device_type' => 'terminal',
            'pairing_code' => 'TEST-1234',
            'is_active' => true,
            'is_paired' => true,
        ]);

        // Second tenant
        $this->otherVendor = Vendor::create([
            'name' => 'Dilijan Forest Cafe',
            'slug' => 'dilijan-forest-cafe',
            'email' => 'info@dilijancafe.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'is_active' => true,
        ]);

        $this->otherVendorUser = User::create([
            'name' => 'Suren Other',
            'email' => 'suren@dilijancafe.am',
            'password' => Hash::make('Password123!'),
            'role' => 'waiter',
            'vendor_id' => $this->otherVendor->id,
            'pin_code' => Hash::make('9999'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->otherDevice = PosDevice::create([
            'vendor_id' => $this->otherVendor->id,
            'name' => 'Dilijan Terminal',
            'device_type' => 'terminal',
            'is_active' => true,
            'is_paired' => true,
        ]);
    }

    public function test_login_with_valid_credentials_creates_device_session_and_authenticates(): void
    {
        $response = $this->postJson('/pos/api/auth/login', [
            'email' => 'armen@sevanseafood.am',
            'password' => 'Password123!',
            'device_uuid' => $this->device->uuid,
            'mode' => 'manager',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertAuthenticatedAs($this->owner);

        $sessionToken = $response->json('session.session_token');
        $this->assertNotEmpty($sessionToken);
        $this->assertDatabaseHas('pos_device_sessions', [
            'device_id' => $this->device->id,
            'user_id' => $this->owner->id,
            'session_token' => $sessionToken,
            'mode' => 'manager',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('pos_operation_logs', [
            'vendor_id' => $this->vendor->id,
            'action' => 'session_login',
            'user_id' => $this->owner->id,
            'device_id' => $this->device->id,
        ]);
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        $response = $this->postJson('/pos/api/auth/login', [
            'email' => 'armen@sevanseafood.am',
            'password' => 'WrongPassword',
            'device_uuid' => $this->device->uuid,
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_pin_login_on_registered_device_succeeds(): void
    {
        $response = $this->postJson('/pos/api/auth/pin-login', [
            'user_id' => $this->waiter->id,
            'pin' => '2222',
            'device_uuid' => $this->device->uuid,
            'mode' => 'waiter',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertAuthenticatedAs($this->waiter);

        $this->assertDatabaseHas('pos_device_sessions', [
            'device_id' => $this->device->id,
            'user_id' => $this->waiter->id,
            'mode' => 'waiter',
            'is_active' => true,
        ]);
    }

    public function test_pin_login_with_invalid_pin_fails(): void
    {
        $response = $this->postJson('/pos/api/auth/pin-login', [
            'user_id' => $this->waiter->id,
            'pin' => '0000',
            'device_uuid' => $this->device->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pin']);
        $this->assertGuest();

        $this->assertDatabaseHas('pos_operation_logs', [
            'vendor_id' => $this->vendor->id,
            'action' => 'pin_login_failed',
            'user_id' => $this->waiter->id,
        ]);
    }

    public function test_quick_switch_employee_switches_active_user_and_session(): void
    {
        // 1. Initial login as Waiter
        $this->actingAs($this->waiter);
        $initialSession = PosDeviceSession::create([
            'device_id' => $this->device->id,
            'user_id' => $this->waiter->id,
            'vendor_id' => $this->vendor->id,
            'session_token' => 'init_tok_123456',
            'mode' => 'waiter',
            'is_active' => true,
            'expires_at' => now()->addHours(12),
        ]);

        // 2. Quick switch to Cashier using Cashier's PIN ('3333')
        $response = $this->postJson('/pos/api/auth/quick-switch', [
            'target_user_id' => $this->cashier->id,
            'pin' => '3333',
            'device_uuid' => $this->device->uuid,
            'mode' => 'cashier',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals($this->cashier->id, $response->json('user.id'));
        $this->assertAuthenticatedAs($this->cashier);

        // Previous session must be deactivated
        $this->assertFalse($initialSession->fresh()->is_active);

        // New session must be active
        $this->assertDatabaseHas('pos_device_sessions', [
            'device_id' => $this->device->id,
            'user_id' => $this->cashier->id,
            'mode' => 'cashier',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('pos_operation_logs', [
            'vendor_id' => $this->vendor->id,
            'action' => 'quick_switch',
            'user_id' => $this->cashier->id,
        ]);
    }

    public function test_lock_and_unlock_session(): void
    {
        $this->actingAs($this->waiter);
        $session = PosDeviceSession::create([
            'device_id' => $this->device->id,
            'user_id' => $this->waiter->id,
            'vendor_id' => $this->vendor->id,
            'session_token' => 'lock_tok_998877',
            'mode' => 'waiter',
            'is_active' => true,
            'expires_at' => now()->addHours(12),
        ]);

        // Lock
        $lockRes = $this->withHeaders(['X-POS-Session-Token' => $session->session_token])
            ->postJson('/pos/api/auth/lock');
        $lockRes->assertStatus(200);
        $this->assertTrue($session->fresh()->isLocked());

        // Unlock with PIN
        $unlockRes = $this->withHeaders(['X-POS-Session-Token' => $session->session_token])
            ->postJson('/pos/api/auth/unlock', [
                'pin' => '2222',
            ]);
        $unlockRes->assertStatus(200);
        $this->assertFalse($session->fresh()->isLocked());
    }

    public function test_setting_and_changing_pin(): void
    {
        $this->actingAs($this->waiter);

        $response = $this->postJson('/pos/api/auth/pin', [
            'pin' => '7890',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($this->waiter->fresh()->verifyPin('7890'));

        $this->assertDatabaseHas('pos_operation_logs', [
            'vendor_id' => $this->vendor->id,
            'action' => 'pin_change',
            'user_id' => $this->waiter->id,
        ]);
    }

    public function test_get_employees_returns_active_vendor_staff(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/pos/api/auth/employees');
        $response->assertStatus(200);

        $employees = $response->json('employees');
        $this->assertCount(3, $employees); // Owner, Waiter, Cashier
        $this->assertTrue(collect($employees)->contains('display_name', 'Vahan W.'));
        $this->assertTrue(collect($employees)->contains('display_name', 'Lilit C.'));
    }

    public function test_device_pairing_code_regeneration(): void
    {
        $oldCode = $this->device->pairing_code;

        $response = $this->actingAs($this->owner)->postJson("/pos/api/devices/{$this->device->id}/regenerate-code");
        $response->assertStatus(200);

        $newCode = $response->json('pairing_code');
        $this->assertNotEquals($oldCode, $newCode);
        $this->assertEquals($newCode, $this->device->fresh()->pairing_code);
        $this->assertFalse($this->device->fresh()->is_paired);
    }

    public function test_cross_tenant_pin_login_and_device_isolation(): void
    {
        // 1. Other vendor employee attempts PIN login on first vendor's device
        $response = $this->postJson('/pos/api/auth/pin-login', [
            'user_id' => $this->otherVendorUser->id,
            'pin' => '9999',
            'device_uuid' => $this->device->uuid,
        ]);

        $response->assertStatus(422);
        $this->assertGuest();

        // 2. First vendor waiter attempts quick switch on second vendor's device
        $switchResponse = $this->actingAs($this->waiter)->postJson('/pos/api/auth/quick-switch', [
            'target_user_id' => $this->otherVendorUser->id,
            'pin' => '9999',
            'device_uuid' => $this->device->uuid,
        ]);

        $switchResponse->assertStatus(422);

        // 3. First vendor cannot regenerate second vendor's device code
        $regenResponse = $this->actingAs($this->owner)->postJson("/pos/api/devices/{$this->otherDevice->id}/regenerate-code");
        $this->assertTrue(in_array($regenResponse->status(), [403, 404], true));
    }
}
