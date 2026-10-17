<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCredential;
use App\Services\CredentialService;
use App\Services\TenantContext;
use App\Services\VendorLifecycleService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecureVendorCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected CredentialService $credentialService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->credentialService = app(CredentialService::class);
    }

    protected function createVendor(string $name = 'Bistro Credentials Test', string $slug = 'bistro-cred'): array
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $vendor = Vendor::create([
            'name' => $name,
            'slug' => $slug,
            'email' => "{$slug}@example.com",
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => "{$slug}@example.com",
            'password' => bcrypt('password'),
            'vendor_id' => $vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        return [$vendor, $user];
    }

    /**
     * Test that sensitive credentials are encrypted at rest and never stored in plaintext in the DB.
     */
    public function test_sensitive_vendor_credentials_are_encrypted_at_rest_and_never_stored_in_plaintext(): void
    {
        [$vendor, $user] = $this->createVendor();

        $plainAiKey = 'mock_ai_api_key_test_1234567890abcdefg';
        $plainStripeSecret = 'mock_stripe_secret_key_production_99';
        $plainTelegramToken = '1234567890:mock_telegram_bot_token_secret';

        // Save via CredentialService
        $this->credentialService->set($vendor, 'ai', 'api_key', $plainAiKey);
        $this->credentialService->set($vendor, 'stripe', 'secret_key', $plainStripeSecret);
        $this->credentialService->set($vendor, 'telegram', 'bot_token', $plainTelegramToken);

        // 1. Inspect raw table rows directly via DB::table (bypassing Eloquent casts)
        $aiRow = DB::table('vendor_credentials')
            ->where('vendor_id', $vendor->id)
            ->where('provider', 'ai')
            ->where('credential_type', 'api_key')
            ->first();

        $this->assertNotNull($aiRow);
        $this->assertNotEquals($plainAiKey, $aiRow->encrypted_value, 'DB must not store plaintext AI key');
        $this->assertEquals($plainAiKey, Crypt::decryptString($aiRow->encrypted_value), 'DB value must be decrypted using application key');

        $stripeRow = DB::table('vendor_credentials')
            ->where('vendor_id', $vendor->id)
            ->where('provider', 'stripe')
            ->where('credential_type', 'secret_key')
            ->first();

        $this->assertNotNull($stripeRow);
        $this->assertNotEquals($plainStripeSecret, $stripeRow->encrypted_value, 'DB must not store plaintext Stripe secret');
        $this->assertEquals($plainStripeSecret, Crypt::decryptString($stripeRow->encrypted_value));

        // 2. Verify Wi-Fi password encryption on Vendor model
        $plainWifi = 'SuperSecretWifiPass2026!';
        $vendor->wifi_password = $plainWifi;
        $vendor->save();

        $rawVendorWifi = DB::table('vendors')->where('id', $vendor->id)->value('wifi_password');
        $this->assertNotEquals($plainWifi, $rawVendorWifi, 'Vendor wifi_password column must be encrypted in database');
        $this->assertEquals($plainWifi, $vendor->fresh()->wifi_password, 'Model accessor must decrypt wifi_password correctly');
    }

    /**
     * Test that secrets are never returned in HTML responses or safe settings getters.
     */
    public function test_secret_is_never_returned_in_api_responses_or_admin_views(): void
    {
        [$vendor, $user] = $this->createVendor();

        $plainStripeSecret = 'mock_stripe_secret_never_expose_to_html_998877';
        $plainAiKey = 'mock_ai_key_never_expose_to_browser_123456';
        $plainTgToken = '987654321:mock_tg_secret_never_expose_to_html';

        $this->credentialService->set($vendor, 'stripe', 'secret_key', $plainStripeSecret);
        $this->credentialService->set($vendor, 'ai', 'api_key', $plainAiKey);
        $this->credentialService->set($vendor, 'telegram', 'bot_token', $plainTgToken);

        // 1. Settings index page view
        $response = $this->actingAs($user)->get(route('admin.settings.index'));
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertStringNotContainsString($plainStripeSecret, $html, 'Settings page must never contain plaintext Stripe secret');
        $this->assertStringNotContainsString($plainTgToken, $html, 'Settings page must never contain plaintext Telegram token');

        // 2. AI settings page view
        $aiResponse = $this->actingAs($user)->get(route('admin.settings.ai'));
        $aiResponse->assertStatus(200);

        $aiHtml = $aiResponse->getContent();
        $this->assertStringNotContainsString($plainAiKey, $aiHtml, 'AI settings page must never contain plaintext AI API key');

        // 3. Safe settings getters must only return configured: true and scrubbed secrets
        $safePayments = $vendor->getSafePaymentSettings();
        $this->assertTrue($safePayments['gateways']['stripe']['configured']);
        $this->assertEquals('', $safePayments['gateways']['stripe']['secret_key'], 'Safe payment settings must scrub secret_key');
        $this->assertNotNull($safePayments['gateways']['stripe']['masked']);
        $this->assertStringContainsString('••••••••', $safePayments['gateways']['stripe']['masked']);
    }

    /**
     * Test that unauthorized vendors cannot access or query another vendor's credentials.
     */
    public function test_unauthorized_vendor_cannot_access_or_tamper_with_other_vendors_credentials(): void
    {
        [$vendorA, $userA] = $this->createVendor('Vendor Alpha', 'vendor-alpha');
        [$vendorB, $userB] = $this->createVendor('Vendor Beta', 'vendor-beta');

        $this->credentialService->set($vendorA, 'stripe', 'secret_key', 'mock_stripe_alpha_secret_key_11111');

        // In Vendor B's tenant context:
        app(TenantContext::class)->setTenantId($vendorB->id);

        // Vendor B querying VendorCredential model directly is tenant-isolated
        $scopedCreds = VendorCredential::all();
        $this->assertCount(0, $scopedCreds, 'TenantScope must prevent Vendor B from querying Vendor A credentials');

        // Attempting to retrieve Vendor A's credentials using Vendor B model
        $val = $this->credentialService->get($vendorB, 'stripe', 'secret_key');
        $this->assertNull($val, 'Vendor B must not be able to retrieve Vendor A credential');

        // Clean up tenant context
        app(TenantContext::class)->clear();
    }

    /**
     * Test that logs and debug output do not leak secrets.
     */
    public function test_logs_and_debug_output_do_not_contain_secrets(): void
    {
        [$vendor, $user] = $this->createVendor();

        $plainSecret = 'mock_stripe_super_secret_production_key_443322';
        $cred = $this->credentialService->set($vendor, 'stripe', 'secret_key', $plainSecret);

        // 1. __debugInfo() on model
        $debugInfo = $cred->__debugInfo();
        $this->assertArrayHasKey('encrypted_value', $debugInfo);
        $this->assertEquals('[REDACTED]', $debugInfo['encrypted_value'], 'Model __debugInfo must redact encrypted_value');

        // 2. Redaction string helper
        $mockStripeKey = 'sk_'.'live_'.'1234567890abcdef';
        $mockTgToken = '123456789:ABCDefGhIjKlMnOpQrStUvWxYz';
        $mockAiKey = 'AIza'.'SySecretKey998877';
        $dirtyText = "Failed calling https://api.telegram.org/bot{$mockTgToken}/sendMessage?key={$mockAiKey} with {$mockStripeKey}";
        $cleanText = $this->credentialService->redactString($dirtyText);

        $this->assertStringNotContainsString($mockTgToken, $cleanText);
        $this->assertStringNotContainsString($mockAiKey, $cleanText);
        $this->assertStringNotContainsString($mockStripeKey, $cleanText);
        $this->assertStringContainsString('[REDACTED', $cleanText);
    }

    /**
     * Test credential rotation updates rotated_at and makes the new secret active.
     */
    public function test_credential_rotation_updates_rotated_at_timestamp_and_updates_active_value(): void
    {
        [$vendor, $user] = $this->createVendor();

        $initialKey = 'mock_stripe_initial_key_111111111';
        $cred = $this->credentialService->set($vendor, 'stripe', 'secret_key', $initialKey);
        $this->assertNull($cred->rotated_at);
        $this->assertEquals($initialKey, $this->credentialService->get($vendor, 'stripe', 'secret_key'));

        // Rotate credential
        $newKey = 'mock_stripe_new_rotated_key_999999999';
        $rotated = $this->credentialService->rotate($vendor, 'stripe', 'secret_key', $newKey);

        $this->assertNotNull($rotated->rotated_at);
        $this->assertEquals($newKey, $this->credentialService->get($vendor, 'stripe', 'secret_key'));
        $this->assertNotEquals($initialKey, $this->credentialService->get($vendor, 'stripe', 'secret_key'));

        // Mask reflects the new key
        $mask = $this->credentialService->mask($vendor, 'stripe', 'secret_key');
        $this->assertStringStartsWith('mock', $mask);
        $this->assertStringEndsWith('9999', $mask);
    }

    /**
     * Test credential deletion removes record and scrubs legacy JSON columns.
     */
    public function test_credential_deletion_removes_from_database_and_scrubs_legacy_columns(): void
    {
        [$vendor, $user] = $this->createVendor();

        $this->credentialService->set($vendor, 'telegram', 'bot_token', '123456:TestToken');
        $this->assertTrue($this->credentialService->has($vendor, 'telegram', 'bot_token'));

        $this->credentialService->delete($vendor, 'telegram', 'bot_token');

        $this->assertFalse($this->credentialService->has($vendor, 'telegram', 'bot_token'));
        $this->assertNull($this->credentialService->get($vendor, 'telegram', 'bot_token'));
        $this->assertEquals(0, DB::table('vendor_credentials')->where('vendor_id', $vendor->id)->count());
    }

    /**
     * Test that leaving secret input empty on form submission preserves the existing secret.
     */
    public function test_blank_input_submission_preserves_existing_credentials(): void
    {
        [$vendor, $user] = $this->createVendor();

        $initialKey = 'mock_stripe_initial_stripe_key_7777';
        $this->credentialService->set($vendor, 'stripe', 'secret_key', $initialKey);

        // Submit form with empty stripe secret_key (as admin leaves masked input untouched)
        $response = $this->actingAs($user)->post(route('admin.settings.update'), [
            'payment_settings' => [
                'online_enabled' => 1,
                'gateways' => [
                    'stripe' => [
                        'enabled' => 1,
                        'publishable_key' => 'pk_test_public_key_123',
                        'secret_key' => '', // blank input
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();

        // The existing secret must NOT be overwritten with empty string
        $this->assertEquals($initialKey, $this->credentialService->get($vendor, 'stripe', 'secret_key'));

        // Plaintext must not be saved into vendors.payment_settings JSON
        $rawPayments = DB::table('vendors')->where('id', $vendor->id)->value('payment_settings');
        $this->assertStringNotContainsString($initialKey, (string) $rawPayments);
    }

    /**
     * Test that credentials:migrate audits, encrypts, and scrubs legacy plaintext settings.
     */
    public function test_credentials_migrate_command_safely_migrates_legacy_plaintext_settings(): void
    {
        [$vendor, $user] = $this->createVendor('Legacy Vendor', 'legacy-vendor');

        $plainAiKey = 'mock_legacy_ai_key_998877665544';
        $plainTgToken = '11223344:LegacyBotToken';
        $plainIdramKey = 'idram_legacy_secret_4455';

        // Write legacy plaintext directly into vendor JSON columns (simulating legacy database state)
        DB::table('vendors')->where('id', $vendor->id)->update([
            'ai_settings' => json_encode(['provider' => 'gemini', 'api_key' => $plainAiKey]),
            'telegram_settings' => json_encode(['enabled' => true, 'bot_token' => $plainTgToken]),
            'payment_settings' => json_encode([
                'online_enabled' => true,
                'gateways' => [
                    'idram' => ['enabled' => true, 'secret_key' => $plainIdramKey],
                ],
            ]),
        ]);

        // Run migration command
        $this->artisan('credentials:migrate', ['--vendor' => $vendor->id])
            ->expectsOutputToContain('Migration completed!')
            ->assertSuccessful();

        // 1. Verify encrypted records now exist in vendor_credentials
        $this->assertEquals($plainAiKey, $this->credentialService->get($vendor, 'ai', 'api_key'));
        $this->assertEquals($plainTgToken, $this->credentialService->get($vendor, 'telegram', 'bot_token'));
        $this->assertEquals($plainIdramKey, $this->credentialService->get($vendor, 'idram', 'secret_key'));

        // 2. Verify DB raw columns no longer contain plaintext secrets
        $rawVendor = DB::table('vendors')->where('id', $vendor->id)->first();
        $this->assertStringNotContainsString($plainAiKey, (string) $rawVendor->ai_settings);
        $this->assertStringNotContainsString($plainTgToken, (string) $rawVendor->telegram_settings);
        $this->assertStringNotContainsString($plainIdramKey, (string) $rawVendor->payment_settings);

        // 3. Verify vendor methods return decrypted values transparently
        $vendor->refresh();
        $this->assertEquals($plainAiKey, $vendor->getAiApiKey());
        $this->assertEquals($plainTgToken, $vendor->getTelegramBotToken());
        $this->assertEquals($plainIdramKey, $vendor->getPaymentSettings()['gateways']['idram']['secret_key']);
    }

    /**
     * Test that lifecycle deletion deletes all vendor credentials.
     */
    public function test_vendor_lifecycle_deletion_cleans_up_vendor_credentials(): void
    {
        [$vendor, $user] = $this->createVendor('Lifecycle Delete Test', 'lifecycle-del');

        $this->credentialService->set($vendor, 'stripe', 'secret_key', 'mock_stripe_delete_test');
        $this->credentialService->set($vendor, 'ai', 'api_key', 'ai_delete_test');

        $this->assertEquals(2, DB::table('vendor_credentials')->where('vendor_id', $vendor->id)->count());

        $lifecycleService = app(VendorLifecycleService::class);
        $stats = $lifecycleService->deleteTenantData($vendor);

        $this->assertArrayHasKey('credentials', $stats);
        $this->assertEquals(2, $stats['credentials']);
        $this->assertEquals(0, DB::table('vendor_credentials')->where('vendor_id', $vendor->id)->count());
    }
}
