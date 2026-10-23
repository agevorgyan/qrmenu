<?php

namespace Tests\Feature\Security;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidFileException;
use App\Exceptions\InvalidStoragePathException;
use App\Exceptions\StorageQuotaExceededException;
use App\Exceptions\TenantContextException;
use App\Jobs\Concerns\TenantAwareJob;
use App\Jobs\Contracts\TenantJobInterface;
use App\Jobs\Middleware\TenantJobMiddleware;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Models\Vendor;
use App\Services\MenuExtractorService;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Security\SsrfProtectionService;
use App\Services\StorageService;
use App\Services\TenantCache;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class StorageCacheQueueIntegrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected User $ownerA;

    protected User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->vendorA = Vendor::create([
            'name' => 'Sec Vendor A',
            'slug' => 'sec-vendor-a',
            'email' => 'sec-a@example.com',
            'is_active' => true,
            'storage_limit_bytes' => 1048576, // 1 MB limit
            'storage_used_bytes' => 0,
            'storage_files_count' => 0,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'owner-a@example.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorA->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->vendorB = Vendor::create([
            'name' => 'Sec Vendor B',
            'slug' => 'sec-vendor-b',
            'email' => 'sec-b@example.com',
            'is_active' => true,
            'storage_limit_bytes' => 1048576,
            'storage_used_bytes' => 0,
            'storage_files_count' => 0,
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'owner-b@example.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendorB->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);
    }

    // ==========================================
    // 1. FILE STORAGE SECURITY
    // ==========================================

    public function test_storage_service_rejects_cross_tenant_file_deletion(): void
    {
        $storage = app(StorageService::class);
        $file = UploadedFile::fake()->image('productA.jpg', 100, 100);
        $storedA = $storage->store($file, 'products', $this->vendorA);

        $this->expectException(InvalidStoragePathException::class);

        // Vendor B attempts to delete Vendor A's file
        $storage->delete($storedA, $this->vendorB);
    }

    public function test_storage_service_rejects_path_traversal_in_clean_path(): void
    {
        $storage = app(StorageService::class);

        $this->expectException(InvalidStoragePathException::class);
        $storage->cleanPath('../../etc/passwd');
    }

    public function test_storage_service_rejects_null_byte_path_traversal(): void
    {
        $storage = app(StorageService::class);

        $this->expectException(InvalidStoragePathException::class);
        $storage->cleanPath("vendors/{$this->vendorA->uuid}/products/test.png\0.php");
    }

    public function test_storage_service_rejects_disallowed_php_executable_extension(): void
    {
        $storage = app(StorageService::class);
        $phpFile = UploadedFile::fake()->create('shell.php', 50, 'text/x-php');

        $this->expectException(InvalidFileException::class);
        $storage->store($phpFile, 'documents', $this->vendorA);
    }

    public function test_storage_service_rejects_mime_spoofed_script_in_image(): void
    {
        $storage = app(StorageService::class);
        $maliciousContent = '<?php echo system($_GET["cmd"]); ?>';
        $fakeImg = UploadedFile::fake()->createWithContent('innocent.png', $maliciousContent);

        $this->expectException(InvalidFileException::class);
        $storage->store($fakeImg, 'products', $this->vendorA);
    }

    public function test_storage_service_enforces_storage_quota_limit(): void
    {
        $storage = app(StorageService::class);
        // Attempt to upload 2 MB file when vendor limit is 1 MB (1048576 bytes)
        $largeFile = UploadedFile::fake()->image('large.jpg', 2000, 2000);

        // Set artificially high used bytes to trigger quota
        $this->vendorA->update(['storage_used_bytes' => 1048500]);

        $this->expectException(StorageQuotaExceededException::class);
        $storage->store($largeFile, 'products', $this->vendorA);
    }

    // ==========================================
    // 2. CACHE & QUEUE SECURITY
    // ==========================================

    public function test_tenant_cache_generates_isolated_keys_per_vendor(): void
    {
        $keyA = TenantCache::key($this->vendorA, 'menu');
        $keyB = TenantCache::key($this->vendorB, 'menu');

        $this->assertNotEquals($keyA, $keyB);
        $this->assertStringContainsString($this->vendorA->uuid, $keyA);
        $this->assertStringContainsString($this->vendorB->uuid, $keyB);

        TenantCache::put($this->vendorA, 'settings', 'VENDOR_A_SECRET');
        $this->assertEquals('VENDOR_A_SECRET', TenantCache::get($this->vendorA, 'settings'));
        $this->assertNull(TenantCache::get($this->vendorB, 'settings'));
    }

    public function test_tenant_job_middleware_rejects_job_without_vendor_id(): void
    {
        $middleware = new TenantJobMiddleware;
        $invalidJob = new class implements TenantJobInterface
        {
            public function getVendorId(): int
            {
                return 0;
            }

            public function getIdempotencyKey(): ?string
            {
                return null;
            }
        };

        $this->expectException(TenantContextException::class);
        $middleware->handle($invalidJob, fn ($j) => true);
    }

    public function test_tenant_job_middleware_cleans_up_context_after_execution(): void
    {
        $middleware = new TenantJobMiddleware;
        $tenantContext = app(TenantContext::class);

        $validJob = new class($this->vendorA->id) implements TenantJobInterface
        {
            public function __construct(public int $vendorId) {}

            public function getVendorId(): int
            {
                return $this->vendorId;
            }

            public function getIdempotencyKey(): ?string
            {
                return null;
            }
        };

        $executed = false;
        $middleware->handle($validJob, function ($j) use (&$executed, $tenantContext) {
            $executed = true;
            // Inside execution, context is set
            $this->assertEquals($this->vendorA->id, $tenantContext->getVendorId());
        });

        $this->assertTrue($executed);
        // After execution (finally block), context must be cleared to prevent worker leakage
        $this->assertNull($tenantContext->getVendorId());
    }

    public function test_tenant_aware_job_redacts_sensitive_payload_properties(): void
    {
        $dummyJob = new class($this->vendorA->id)
        {
            use TenantAwareJob;

            public string $api_key = 'sk-live-secret-key-12345';

            public string $password = 'superSecretPassword!';

            public string $safe_property = 'ordinary_value';

            public function __construct(int $vId)
            {
                $this->vendorId = $vId;
            }
        };

        $sanitized = $dummyJob->getSanitizedPayload();

        $this->assertEquals('[REDACTED]', $sanitized['api_key']);
        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('ordinary_value', $sanitized['safe_property']);
    }

    // ==========================================
    // 3. INTEGRATIONS & SSRF SECURITY
    // ==========================================

    public function test_ssrf_protection_service_blocks_loopback_and_metadata(): void
    {
        $ssrf = app(SsrfProtectionService::class);

        // Loopback
        $this->assertFalse($ssrf->isSafeUrl('http://127.0.0.1:8000/api'));
        $this->assertFalse($ssrf->isSafeUrl('http://127.0.0.1/admin'));
        $this->assertFalse($ssrf->isSafeUrl('http://localhost:3000/'));

        // Cloud Metadata
        $this->assertFalse($ssrf->isSafeUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($ssrf->isSafeUrl('http://metadata.google.internal/computeMetadata/v1/'));

        // Private ranges
        $this->assertFalse($ssrf->isSafeUrl('http://10.0.0.5:8080/'));
        $this->assertFalse($ssrf->isSafeUrl('http://192.168.1.1/router'));
        $this->assertFalse($ssrf->isSafeUrl('http://172.16.0.1/internal'));

        // Blocked ports
        $this->assertFalse($ssrf->isSafeUrl('http://example.com:5432/'));
        $this->assertFalse($ssrf->isSafeUrl('http://example.com:6379/'));
        $this->assertFalse($ssrf->isSafeUrl('http://example.com:22/'));
    }

    public function test_vendor_settings_rejects_ssrf_in_ai_base_url(): void
    {
        $response = $this->actingAs($this->ownerA)->postJson(route('admin.settings.ai.test'), [
            'ai_provider' => 'custom',
            'ai_model' => 'llama-3',
            'ai_api_key' => 'test-key',
            'ai_base_url' => 'http://169.254.169.254/latest/meta-data/',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'SSRF Security Error: Prohibited or unsafe AI base URL destination.',
        ]);
    }

    public function test_menu_extractor_rejects_ssrf_target_urls(): void
    {
        $extractor = app(MenuExtractorService::class);

        $this->expectException(InvalidArgumentException::class);
        $extractor->extractStructuredOrTextFromUrl('http://127.0.0.1:8000/secret-menu');
    }

    // ==========================================
    // 4. SUBSCRIPTIONS & BILLING SECURITY
    // ==========================================

    public function test_payment_verification_rejects_replayed_provider_transaction_id(): void
    {
        $locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Loc A',
            'slug' => 'loc-a',
            'is_active' => true,
        ]);

        $orderA1 = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $locationA->id,
            'order_number' => 'ORD-101',
            'total_amount' => 5000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $orderA2 = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $locationA->id,
            'order_number' => 'ORD-102',
            'total_amount' => 5000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        // Attempt 1: Already paid with provider transaction ID "TXN_REPLAY_TEST_1"
        PaymentAttempt::create([
            'vendor_id' => $this->vendorA->id,
            'order_id' => $orderA1->id,
            'gateway' => 'stripe',
            'merchant_reference' => 'REF-101',
            'provider_transaction_id' => 'TXN_REPLAY_TEST_1',
            'amount' => 5000,
            'currency' => 'AMD',
            'status' => PaymentStatus::Paid,
            'verified_at' => now(),
        ]);

        // Attempt 2: Pending attempt that malicious callback tries to finalize with same provider transaction ID
        $attempt2 = PaymentAttempt::create([
            'vendor_id' => $this->vendorA->id,
            'order_id' => $orderA2->id,
            'gateway' => 'stripe',
            'merchant_reference' => 'REF-102',
            'amount' => 5000,
            'currency' => 'AMD',
            'status' => PaymentStatus::Pending,
        ]);

        config(['services.stripe.secret' => 'sk_test_mock_secret_key']);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/sess_replay_test' => Http::response([
                'payment_status' => 'paid',
                'amount_total' => 500000, // 5000 AMD in cents
                'currency' => 'amd',
                'payment_intent' => 'TXN_REPLAY_TEST_1', // Duplicate transaction ID
            ], 200),
        ]);

        $verificationService = app(PaymentVerificationService::class);

        // Attempt to verify attempt 2 using the session that returns the replayed transaction ID
        $result = $verificationService->verifyAndFinalizeAttempt($attempt2, [
            'session_id' => 'sess_replay_test',
        ]);

        $this->assertEquals(PaymentStatus::Failed, $result->status);
        $this->assertStringContainsString('already been used', $result->errorMessage);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt2->id,
            'status' => PaymentStatus::Failed->value,
        ]);
    }

    public function test_payment_verification_rejects_amount_tampering(): void
    {
        $locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Loc A',
            'slug' => 'loc-a-tamper',
            'is_active' => true,
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $locationA->id,
            'order_number' => 'ORD-TAMPER-01',
            'total_amount' => 10000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $attempt = PaymentAttempt::create([
            'vendor_id' => $this->vendorA->id,
            'order_id' => $order->id,
            'gateway' => 'stripe',
            'merchant_reference' => 'REF-TAMPER-01',
            'amount' => 10000,
            'currency' => 'AMD',
            'status' => PaymentStatus::Pending,
        ]);

        config(['services.stripe.secret' => 'sk_test_mock_secret_key']);

        // Attacker pays only 100 AMD (10000 cents) instead of 10000 AMD (1000000 cents)
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/sess_tamper' => Http::response([
                'payment_status' => 'paid',
                'amount_total' => 10000, // 100 AMD
                'currency' => 'amd',
                'payment_intent' => 'TXN_TAMPER_01',
            ], 200),
        ]);

        $verificationService = app(PaymentVerificationService::class);

        $result = $verificationService->verifyAndFinalizeAttempt($attempt, [
            'session_id' => 'sess_tamper',
        ]);

        $this->assertEquals(PaymentStatus::Failed, $result->status);
        $this->assertStringContainsString('Amount mismatch', $result->errorMessage);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'failed',
        ]);
    }
}
