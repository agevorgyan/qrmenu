<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryCurrencyAndTimezoneTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test vendor currency and timezone helpers and Carbon macro.
     */
    public function test_vendor_currency_and_timezone_helpers(): void
    {
        $vendor = Vendor::create([
            'name' => 'Armenia Grill',
            'slug' => 'arm-grill-'.uniqid(),
            'email' => 'grill-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
        ]);

        $this->assertEquals('֏', $vendor->currency);
        $this->assertEquals('Asia/Yerevan', $vendor->getTimezone());

        // Test formatCurrency helper
        $this->assertEquals('1,250.50 ֏', $vendor->formatCurrency(1250.5));
        $this->assertEquals('0.00 ֏', $vendor->formatCurrency(0));

        // Test Carbon macro toVendorTimezone
        $utcDate = Carbon::create(2026, 11, 6, 12, 0, 0, 'UTC'); // 12:00 UTC = 16:00 Yerevan (UTC+4)
        $localDate = $utcDate->toVendorTimezone($vendor);

        $this->assertEquals('Asia/Yerevan', $localDate->getTimezone()->getName());
        $this->assertEquals('16:00', $localDate->format('H:i'));

        // Test vendor formatDateTime and formatDate helpers
        $this->assertEquals('Nov 06, 2026 16:00', $vendor->formatDateTime($utcDate));
        $this->assertEquals('Nov 06, 2026', $vendor->formatDate($utcDate));
    }

    /**
     * Test that all inventory blade views have zero hardcoded dollar currency symbols.
     */
    public function test_all_inventory_views_contain_no_hardcoded_dollar_signs(): void
    {
        $files = array_merge(
            glob(resource_path('views/admin/inventory/**/*.blade.php')),
            glob(resource_path('views/admin/inventory/*.blade.php'))
        );

        $this->assertNotEmpty($files, 'Inventory view files should exist.');

        $violations = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $i => $line) {
                $lineNo = $i + 1;
                $trimmed = trim($line);

                if (str_contains($trimmed, '($)')) {
                    $violations[] = basename($file).":{$lineNo} -> contains ($): {$trimmed}";
                } elseif (preg_match('/>\s*\$\s*[\d{]/', $trimmed) || preg_match('/\$\s*\{\{\s*number_format/', $trimmed)) {
                    $violations[] = basename($file).":{$lineNo} -> contains hardcoded dollar before amount: {$trimmed}";
                } elseif (preg_match('/\$\d+(\.\d+)?/', $trimmed) && ! preg_match('/\$[a-zA-Z_]/', $trimmed)) {
                    $violations[] = basename($file).":{$lineNo} -> contains literal dollar amount: {$trimmed}";
                }
            }
        }

        $this->assertEmpty($violations, "Found hardcoded dollar currency in inventory views:\n".implode("\n", $violations));
    }

    /**
     * Test that all inventory views format dates using vendor timezone.
     */
    public function test_all_inventory_views_use_vendor_timezone_for_formatting_dates(): void
    {
        $files = array_merge(
            glob(resource_path('views/admin/inventory/**/*.blade.php')),
            glob(resource_path('views/admin/inventory/*.blade.php'))
        );

        $this->assertNotEmpty($files);

        $unconvertedDates = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $i => $line) {
                $lineNo = $i + 1;
                $trimmed = trim($line);

                if (str_contains($trimmed, '->format(') && ! str_contains($trimmed, 'toVendorTimezone') && ! str_contains($trimmed, 'getLocalTime')) {
                    $unconvertedDates[] = basename($file).":{$lineNo} -> {$trimmed}";
                }
            }
        }

        $this->assertEmpty($unconvertedDates, "Found unconverted date formatting in views:\n".implode("\n", $unconvertedDates));
    }

    /**
     * Test inventory dashboard and suppliers views render configured vendor currency symbol.
     */
    public function test_inventory_views_render_configured_vendor_currency(): void
    {
        $vendor = Vendor::create([
            'name' => 'Armenia Grill',
            'slug' => 'arm-grill-'.uniqid(),
            'email' => 'grill-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Owner Alpha',
            'email' => 'owner-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('admin.inventory.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('֏');
        $response->assertDontSee('$0.00');

        $suppliersResponse = $this->get(route('admin.inventory.suppliers.index'));
        $suppliersResponse->assertStatus(200);
        $suppliersResponse->assertSee('֏');
        $suppliersResponse->assertDontSee('$0.00');
    }
}
