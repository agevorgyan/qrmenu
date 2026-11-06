<?php

namespace Tests\Feature;

use App\Domains\Loyalty\Models\WalletPass;
use App\Domains\Loyalty\Services\WalletService;
use App\Domains\Loyalty\Wallet\AppleWalletService;
use App\Domains\Loyalty\Wallet\GoogleWalletService;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplePassKitAndGoogleWalletTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Customer $customer;

    protected WalletPass $pass;

    protected AppleWalletService $appleService;

    protected GoogleWalletService $googleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appleService = app(AppleWalletService::class);
        $this->googleService = app(GoogleWalletService::class);

        $this->vendor = Vendor::create([
            'name' => 'Wallet Bistro',
            'slug' => 'wallet-bistro',
            'email' => 'wallet@bistro.com',
            'password' => bcrypt('password'),
        ]);

        $this->customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Pass Holder',
            'phone' => '+15552223333',
            'loyalty_points' => 450,
            'loyalty_tier' => 'Silver',
        ]);

        $this->pass = app(WalletService::class)->getOrIssuePass($this->customer);
    }

    public function test_apple_wallet_generates_pkpass_bundle(): void
    {
        $pkpassBytes = $this->appleService->generatePkPass($this->pass);

        $this->assertNotEmpty($pkpassBytes);
        // Valid ZIP archive starts with magic bytes PK\x03\x04
        $this->assertStringStartsWith("PK\x03\x04", $pkpassBytes);
    }

    public function test_passkit_webservice_device_registration_lifecycle(): void
    {
        $deviceLibraryId = 'dev_lib_abc12345';
        $pushToken = 'apns_push_token_987654321';
        $passType = $this->pass->pass_type_identifier;
        $serial = $this->pass->serial_number;
        $authToken = $this->pass->authentication_token;

        // 1. Unauthorized request (missing or invalid header)
        $unauthResp = $this->postJson("/api/v1/devices/{$deviceLibraryId}/registrations/{$passType}/{$serial}", [
            'pushToken' => $pushToken,
        ]);
        $unauthResp->assertStatus(401);

        // 2. Authorized request creates registration (201)
        $authResp = $this->withHeaders([
            'Authorization' => "ApplePass {$authToken}",
        ])->postJson("/api/v1/devices/{$deviceLibraryId}/registrations/{$passType}/{$serial}", [
            'pushToken' => $pushToken,
        ]);
        $authResp->assertStatus(201);

        $this->assertDatabaseHas('wallet_device_registrations', [
            'pass_id' => $this->pass->id,
            'device_library_identifier' => $deviceLibraryId,
            'push_token' => $pushToken,
        ]);

        // 3. Repeated registration updates push token (200)
        $repeatResp = $this->withHeaders([
            'Authorization' => "ApplePass {$authToken}",
        ])->postJson("/api/v1/devices/{$deviceLibraryId}/registrations/{$passType}/{$serial}", [
            'pushToken' => 'new_apns_token_555',
        ]);
        $repeatResp->assertStatus(200);

        // 4. Delete registration
        $delResp = $this->withHeaders([
            'Authorization' => "ApplePass {$authToken}",
        ])->deleteJson("/api/v1/devices/{$deviceLibraryId}/registrations/{$passType}/{$serial}");
        $delResp->assertStatus(200);

        $this->assertDatabaseMissing('wallet_device_registrations', [
            'pass_id' => $this->pass->id,
            'device_library_identifier' => $deviceLibraryId,
        ]);
    }

    public function test_passkit_webservice_delivers_latest_pass(): void
    {
        $passType = $this->pass->pass_type_identifier;
        $serial = $this->pass->serial_number;
        $authToken = $this->pass->authentication_token;

        $response = $this->withHeaders([
            'Authorization' => "ApplePass {$authToken}",
        ])->get("/api/v1/passes/{$passType}/{$serial}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.apple.pkpass');
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_google_wallet_generates_valid_jwt_save_url(): void
    {
        $saveUrl = $this->googleService->generateSaveUrl($this->pass);

        $this->assertNotEmpty($saveUrl);
        $this->assertStringStartsWith('https://pay.google.com/gp/v/save/', $saveUrl);

        $jwt = str_replace('https://pay.google.com/gp/v/save/', '', $saveUrl);
        $parts = explode('.', $jwt);

        // JWT has 3 parts: header.payload.signature
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('payload', $payload);
        $this->assertArrayHasKey('loyaltyClasses', $payload['payload']);
        $this->assertArrayHasKey('loyaltyObjects', $payload['payload']);
    }
}
