<?php

namespace Tests\Feature\Security;

use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ThermalPrinterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SsrfPrinterProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $owner;

    protected Order $order;

    protected ThermalPrinterService $printerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Printer Test Vendor',
            'slug' => 'printer-vendor',
            'email' => 'printer@test.am',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner Printer',
            'email' => 'owner@printer.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Printer Test Branch',
            'slug' => 'printer-test-branch',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $location->id,
            'order_number' => 'ORD-PRINT-001',
            'total_amount' => 1200,
            'status' => 'pending',
        ]);

        $this->printerService = app(ThermalPrinterService::class);
    }

    public function test_is_safe_printer_address_blocks_loopback_ips(): void
    {
        $this->assertFalse($this->printerService->isSafePrinterAddress('127.0.0.1', 9100));
        $this->assertFalse($this->printerService->isSafePrinterAddress('127.0.1.1', 9100));
        $this->assertFalse($this->printerService->isSafePrinterAddress('::1', 9100));
    }

    public function test_is_safe_printer_address_blocks_cloud_metadata_service(): void
    {
        // AWS / GCP / Azure / DigitalOcean metadata service
        $this->assertFalse($this->printerService->isSafePrinterAddress('169.254.169.254', 80));
        $this->assertFalse($this->printerService->isSafePrinterAddress('169.254.169.254', 9100));
        $this->assertFalse($this->printerService->isSafePrinterAddress('169.254.1.5', 9100));
        $this->assertFalse($this->printerService->isSafePrinterAddress('fe80::1', 9100));
    }

    public function test_is_safe_printer_address_blocks_unspecified_and_hostnames(): void
    {
        $this->assertFalse($this->printerService->isSafePrinterAddress('0.0.0.0', 9100));
        $this->assertFalse($this->printerService->isSafePrinterAddress('::', 9100));
        $this->assertFalse($this->printerService->isSafePrinterAddress('localhost', 9100));
        $this->assertFalse($this->printerService->isSafePrinterAddress('metadata.google.internal', 9100));
    }

    public function test_is_safe_printer_address_blocks_sensitive_infrastructure_ports(): void
    {
        $safeLanIp = '192.168.1.200';

        // PostgreSQL port
        $this->assertFalse($this->printerService->isSafePrinterAddress($safeLanIp, 5432));
        // Redis port
        $this->assertFalse($this->printerService->isSafePrinterAddress($safeLanIp, 6379));
        // SSH / HTTP / HTTPS ports
        $this->assertFalse($this->printerService->isSafePrinterAddress($safeLanIp, 22));
        $this->assertFalse($this->printerService->isSafePrinterAddress($safeLanIp, 80));
        $this->assertFalse($this->printerService->isSafePrinterAddress($safeLanIp, 443));
        // MySQL / Memcached ports
        $this->assertFalse($this->printerService->isSafePrinterAddress($safeLanIp, 3306));
        $this->assertFalse($this->printerService->isSafePrinterAddress($safeLanIp, 11211));
    }

    public function test_is_safe_printer_address_permits_legitimate_lan_printer(): void
    {
        $this->assertTrue($this->printerService->isSafePrinterAddress('192.168.1.150', 9100));
        $this->assertTrue($this->printerService->isSafePrinterAddress('10.0.0.55', 9100));
    }

    public function test_print_to_network_printer_rejects_ssrf_without_opening_socket(): void
    {
        $result = $this->printerService->printToNetworkPrinter('127.0.0.1', 5432, 'TEST');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('արգելափակված է', $result['message']);
    }

    public function test_order_network_print_endpoint_rejects_loopback_ip(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('admin.orders.print_network', $this->order), [
            'ip' => '127.0.0.1',
            'port' => 9100,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_printer_test_connection_endpoint_rejects_metadata_ip(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('admin.settings.printer.test'), [
            'ip' => '169.254.169.254',
            'port' => 80,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
