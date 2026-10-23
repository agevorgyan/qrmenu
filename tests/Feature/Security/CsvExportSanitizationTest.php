<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $owner;

    protected Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'CSV Test Vendor',
            'slug' => 'csv-vendor',
            'email' => 'csv@vendor.am',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'CSV Owner',
            'email' => 'owner@csv.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Location',
            'slug' => 'main-loc',
            'is_active' => true,
        ]);
    }

    public function test_customer_export_sanitizes_formula_injection_characters(): void
    {
        // 1. Create customers with malicious formula injection prefixes
        Customer::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => "=cmd|' /C calc'!A0",
            'phone' => '+37499111222',
            'email' => '@malicious@evil.com',
            'notes' => "-SUM(1+1)*cmd|' /C calc'!A0",
        ]);

        // 2. Request CSV export
        $response = $this->actingAs($this->owner)->get(route('admin.customers.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // 3. Verify that cells beginning with =, +, -, @ are escaped with leading single quote
        $this->assertStringContainsString("'=cmd|' /C calc'!A0", $content);
        $this->assertStringContainsString("'+37499111222", $content);
        $this->assertStringContainsString("'@malicious@evil.com", $content);
        $this->assertStringContainsString("'-SUM(1+1)*cmd|' /C calc'!A0", $content);
    }
}
