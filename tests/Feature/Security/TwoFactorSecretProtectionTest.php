<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Vendor;
use App\Services\QrCodeService;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwoFactorSecretProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => '2FA Vendor',
            'slug' => '2fa-vendor',
            'email' => '2fa@vendor.am',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Security User',
            'email' => 'user@security.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
            'two_factor_secret' => TwoFactorAuthService::generateSecretKey(),
        ]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@platform.am',
            'password' => bcrypt('password123'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
            'two_factor_secret' => TwoFactorAuthService::generateSecretKey(),
        ]);
    }

    public function test_qr_code_service_generates_valid_svg_without_network_calls(): void
    {
        Http::preventStrayRequests();

        $service = app(QrCodeService::class);
        $uri = TwoFactorAuthService::getOtpAuthUri($this->user, $this->user->two_factor_secret);

        $dataUri = $service->generateDataUri($uri, 200);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        $svg = base64_decode(substr($dataUri, 26));
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_profile_security_view_does_not_leak_totp_secret_to_external_api(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs($this->user)->get(route('admin.profile'));

        $response->assertOk();
        $response->assertViewHas('qrCodeUrl', function (string $url) {
            return str_starts_with($url, 'data:image/svg+xml;base64,')
                && ! str_contains($url, 'api.qrserver.com');
        });
    }

    public function test_get_secret_key_json_does_not_leak_totp_secret_to_external_api(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs($this->user)->getJson(route('security.2fa.secret'));

        $response->assertOk();
        $response->assertJsonStructure(['secret', 'otpauth_uri', 'qr_code_url']);

        $qrUrl = $response->json('qr_code_url');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qrUrl);
        $this->assertStringNotContainsString('api.qrserver.com', $qrUrl);
    }

    public function test_superadmin_settings_view_does_not_leak_totp_secret_to_external_api(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs($this->superAdmin)->get(route('superadmin.settings.index'));

        $response->assertOk();
        $response->assertViewHas('qrCodeUrl', function (string $url) {
            return str_starts_with($url, 'data:image/svg+xml;base64,')
                && ! str_contains($url, 'api.qrserver.com');
        });
    }
}
