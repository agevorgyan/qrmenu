<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\PosDevice;
use App\Models\PosLanSyncDispatch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PosLanService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosLanModeTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $cashier;

    protected User $waiter;

    protected PosDevice $desktopTerminal;

    protected PosDevice $tabletDevice;

    protected Vendor $otherVendor;

    protected User $otherCashier;

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
                'features' => ['orders', 'locations', 'team', 'pos', 'pos.access', 'orders.online'],
                'is_active' => true,
            ]);

        $this->vendor = Vendor::create([
            'name' => 'Ani Restaurant',
            'slug' => 'ani-restaurant',
            'email' => 'pos@anirestaurant.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Downtown Hall',
            'slug' => 'downtown-hall',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Arthur Owner',
            'email' => 'owner@anirestaurant.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'manager',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'David Cashier',
            'email' => 'cashier@anirestaurant.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'cashier',
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $this->waiter = User::create([
            'name' => 'Karen Waiter',
            'email' => 'karen@anirestaurant.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'waiter',
            'vendor_id' => $this->vendor->id,
            'pos_mode' => 'waiter',
            'pin_code' => Hash::make('5678'),
            'is_active' => true,
        ]);

        $this->desktopTerminal = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Cashier Counter Desktop',
            'device_type' => 'desktop',
            'pairing_code' => '111222',
            'lan_ip_address' => '192.168.1.50',
            'lan_port' => 8080,
            'lan_sync_priority' => 100,
            'is_active' => true,
            'is_paired' => true,
            'last_lan_heartbeat_at' => now(),
        ]);

        $this->tabletDevice = PosDevice::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Waiter Tablet 1',
            'device_type' => 'tablet',
            'pairing_code' => '333444',
            'lan_ip_address' => '192.168.1.75',
            'lan_port' => 8080,
            'lan_sync_priority' => 10,
            'is_active' => true,
            'is_paired' => true,
            'last_lan_heartbeat_at' => now(),
        ]);

        // Other Vendor
        $this->otherVendor = Vendor::create([
            'name' => 'Other Cafe',
            'slug' => 'other-cafe',
            'email' => 'pos@othercafe.am',
            'subscription_plan' => 'business',
            'subscription_plan_id' => $businessPlan->id,
            'currency' => 'AMD',
            'currency_symbol' => '֏',
            'is_active' => true,
        ]);

        $this->otherCashier = User::create([
            'name' => 'Other Cashier',
            'email' => 'cashier@othercafe.am',
            'password' => Hash::make('Secret123!'),
            'role' => 'cashier',
            'vendor_id' => $this->otherVendor->id,
            'pos_mode' => 'cashier',
            'is_active' => true,
        ]);

        $this->otherDevice = PosDevice::create([
            'vendor_id' => $this->otherVendor->id,
            'name' => 'Other POS Terminal',
            'device_type' => 'desktop',
            'pairing_code' => '999888',
            'lan_ip_address' => '192.168.2.10',
            'is_active' => true,
            'is_paired' => true,
        ]);
    }

    public function test_get_lan_peers_returns_devices_and_auto_elects_hub_if_none_exists(): void
    {
        $response = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->desktopTerminal->uuid])
            ->getJson("/pos/api/lan/peers?location_id={$this->location->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $lan = $response->json('lan');
        $this->assertNotNull($lan['hub']);
        // Desktop device with priority 100 should be auto-elected as Hub
        $this->assertEquals($this->desktopTerminal->uuid, $lan['hub']['device_uuid']);
        $this->assertEquals('192.168.1.50', $lan['hub']['ip_address']);
        $this->assertNotEmpty($lan['hub']['auth_token']);

        // Check peers list
        $this->assertCount(2, $lan['peers']);
        $uuids = collect($lan['peers'])->pluck('device_uuid')->all();
        $this->assertContains($this->desktopTerminal->uuid, $uuids);
        $this->assertContains($this->tabletDevice->uuid, $uuids);
    }

    public function test_lan_heartbeat_registers_peer_ip_port_and_role(): void
    {
        $response = $this->actingAs($this->waiter)
            ->withHeaders(['X-POS-Device-UUID' => $this->tabletDevice->uuid])
            ->postJson('/pos/api/lan/heartbeat', [
                'lan_ip' => '192.168.1.80',
                'lan_port' => 8085,
                'role' => 'satellite',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_hub' => false,
        ]);

        $this->assertDatabaseHas('pos_lan_peers', [
            'vendor_id' => $this->vendor->id,
            'pos_device_id' => $this->tabletDevice->id,
            'ip_address' => '192.168.1.80',
            'port' => 8085,
            'role' => 'satellite',
            'status' => 'active',
        ]);

        $this->tabletDevice->refresh();
        $this->assertEquals('192.168.1.80', $this->tabletDevice->lan_ip_address);
        $this->assertEquals(8085, $this->tabletDevice->lan_port);
    }

    public function test_manual_lan_hub_election_by_manager(): void
    {
        // 1. Waiter cannot elect hub (forbidden)
        $waiterRes = $this->actingAs($this->waiter)
            ->withHeaders(['X-POS-Device-UUID' => $this->tabletDevice->uuid])
            ->postJson('/pos/api/lan/hub/elect', [
                'device_id' => $this->tabletDevice->id,
            ]);
        $waiterRes->assertStatus(403);

        // 2. Manager/Owner elevates Tablet to Hub
        $managerRes = $this->actingAs($this->owner)
            ->withHeaders(['X-POS-Device-UUID' => $this->tabletDevice->uuid])
            ->postJson('/pos/api/lan/hub/elect', [
                'device_id' => $this->tabletDevice->id,
            ]);

        $managerRes->assertStatus(200);
        $managerRes->assertJson(['success' => true]);

        $this->tabletDevice->refresh();
        $this->desktopTerminal->refresh();

        $this->assertTrue($this->tabletDevice->is_lan_hub);
        $this->assertFalse($this->desktopTerminal->is_lan_hub);
        $this->assertNotEmpty($this->tabletDevice->lan_auth_token);
    }

    public function test_lan_event_dispatch_and_peer_acknowledgement(): void
    {
        // 1. Tablet dispatches 'order_created' event across LAN
        $dispatchRes = $this->actingAs($this->waiter)
            ->withHeaders(['X-POS-Device-UUID' => $this->tabletDevice->uuid])
            ->postJson('/pos/api/lan/dispatch', [
                'event_type' => 'order_created',
                'payload' => [
                    'order_number' => 'LAN-ORD-101',
                    'table_number' => 'T-01',
                    'total_amount' => 5000,
                ],
            ]);

        $dispatchRes->assertStatus(200);
        $dispatchRes->assertJson([
            'success' => true,
            'status' => 'queued',
        ]);
        $dispatchId = $dispatchRes->json('dispatch_id');

        // 2. Desktop terminal polls for pending LAN dispatches
        $pendingRes = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->desktopTerminal->uuid])
            ->getJson('/pos/api/lan/dispatches/pending');

        $pendingRes->assertStatus(200);
        $pendingRes->assertJson(['success' => true]);
        $dispatches = $pendingRes->json('dispatches');
        $this->assertCount(1, $dispatches);
        $this->assertEquals($dispatchId, $dispatches[0]['id']);
        $this->assertEquals('order_created', $dispatches[0]['event_type']);

        // 3. Desktop terminal acknowledges the received dispatch
        $ackRes = $this->actingAs($this->cashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->desktopTerminal->uuid])
            ->postJson("/pos/api/lan/dispatches/{$dispatchId}/ack");

        $ackRes->assertStatus(200);
        $ackRes->assertJson(['success' => true]);

        $this->assertDatabaseHas('pos_lan_sync_dispatches', [
            'id' => $dispatchId,
            'status' => 'acknowledged',
        ]);
    }

    public function test_stale_hub_detection_auto_re_elects_fallback_hub(): void
    {
        // Elect desktop as hub, but set heartbeat to 15 minutes ago (stale!)
        $this->desktopTerminal->update([
            'is_lan_hub' => true,
            'last_lan_heartbeat_at' => Carbon::now()->subMinutes(15),
        ]);

        $this->tabletDevice->update([
            'is_lan_hub' => false,
            'last_lan_heartbeat_at' => Carbon::now(), // Active!
        ]);

        $lanService = app(PosLanService::class);
        $data = $lanService->getLanPeers($this->vendor, $this->location->id);

        // Previous desktop hub was stepped down due to stale heartbeat
        $this->desktopTerminal->refresh();
        $this->assertFalse($this->desktopTerminal->is_lan_hub);

        // Active candidate was elected
        $this->tabletDevice->refresh();
        $this->assertTrue($this->tabletDevice->is_lan_hub);
        $this->assertEquals($this->tabletDevice->uuid, $data['hub']['device_uuid']);
    }

    public function test_strict_tenant_isolation_on_lan_peers_and_dispatches(): void
    {
        // 1. Other cashier from Vendor B cannot view Vendor A peers
        $resPeers = $this->actingAs($this->otherCashier)
            ->withHeaders(['X-POS-Device-UUID' => $this->otherDevice->uuid])
            ->getJson("/pos/api/lan/peers?location_id={$this->location->id}");

        $resPeers->assertStatus(200);
        // Only returns Vendor B devices (otherDevice)
        $this->assertCount(1, $resPeers->json('lan.peers'));
        $this->assertEquals($this->otherDevice->uuid, $resPeers->json('lan.peers.0.device_uuid'));

        // 2. Vendor A dispatch cannot be acknowledged by Vendor B cashier
        app(TenantContext::class)->setTenant($this->vendor);
        $dispatch = PosLanSyncDispatch::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'source_device_id' => $this->desktopTerminal->id,
            'event_type' => 'order_created',
            'payload' => ['order' => 'test'],
            'status' => 'queued',
        ]);

        $resAck = $this->actingAs($this->otherCashier)->postJson("/pos/api/lan/dispatches/{$dispatch->id}/ack");
        $this->assertTrue(in_array($resAck->status(), [403, 404], true));
    }
}
