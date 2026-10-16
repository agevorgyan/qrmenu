<?php

namespace Tests\Feature;

use App\Mail\SystemTestMail;
use App\Models\EmailConnection;
use App\Models\EmailLog;
use App\Models\EmailRoutingRule;
use App\Models\EmailSenderIdentity;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailQueueMonitorService;
use App\Services\EmailRoutingService;
use App\Services\EmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminEmailManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $vendorOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superadmin = User::where('role', 'superadmin')->first();
        $this->vendorOwner = User::where('role', 'vendor_owner')->first();
    }

    /**
     * 1. Authorization tests
     */
    public function test_guest_is_redirected_from_email_management(): void
    {
        $response = $this->get(route('superadmin.emails.overview'));
        $response->assertRedirect(route('login'));
    }

    public function test_vendor_owner_is_forbidden_from_email_management(): void
    {
        $this->actingAs($this->vendorOwner);

        $response = $this->get(route('superadmin.emails.overview'));
        $response->assertStatus(403);

        $postResponse = $this->post(route('superadmin.emails.senders.store'), [
            'name' => 'Hacker Sender',
            'email' => 'hacker@example.com',
        ]);
        $postResponse->assertStatus(403);
    }

    public function test_superadmin_can_access_all_email_management_views(): void
    {
        $this->actingAs($this->superadmin);

        $routes = [
            'superadmin.emails.overview',
            'superadmin.emails.senders.index',
            'superadmin.emails.connections.index',
            'superadmin.emails.routing.index',
            'superadmin.emails.templates.index',
            'superadmin.emails.logs.index',
            'superadmin.emails.queue.index',
            'superadmin.emails.failed.index',
            'superadmin.emails.reports.index',
            'superadmin.emails.settings.index',
        ];

        foreach ($routes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertStatus(200);
        }
    }

    /**
     * 2. Sender Identity Management & Safety Constraints
     */
    public function test_superadmin_can_create_and_update_sender_identity(): void
    {
        $this->actingAs($this->superadmin);

        $response = $this->from(route('superadmin.emails.senders.index'))
            ->post(route('superadmin.emails.senders.store'), [
                'name' => 'Marketing Updates',
                'email' => 'news@menu.elab.am',
                'reply_to_email' => 'support@menu.elab.am',
                'reply_to_name' => 'Support Desk',
                'description' => 'Promotional and customer news',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('superadmin.emails.senders.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('email_sender_identities', [
            'email' => 'news@menu.elab.am',
            'name' => 'Marketing Updates',
        ]);

        $sender = EmailSenderIdentity::where('email', 'news@menu.elab.am')->first();

        // Update sender
        $updateResponse = $this->from(route('superadmin.emails.senders.index'))
            ->put(route('superadmin.emails.senders.update', $sender->id), [
                'name' => 'Marketing & Promotions',
                'email' => 'news@menu.elab.am',
                'description' => 'Updated description',
                'is_active' => true,
            ]);

        $updateResponse->assertRedirect(route('superadmin.emails.senders.index'));
        $this->assertDatabaseHas('email_sender_identities', [
            'id' => $sender->id,
            'name' => 'Marketing & Promotions',
        ]);
    }

    public function test_sender_duplicate_email_validation(): void
    {
        $this->actingAs($this->superadmin);

        $existing = EmailSenderIdentity::first();

        $response = $this->post(route('superadmin.emails.senders.store'), [
            'name' => 'Duplicate Sender',
            'email' => $existing->email,
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_cannot_delete_default_sender_identity(): void
    {
        $this->actingAs($this->superadmin);

        $defaultSender = EmailSenderIdentity::where('is_default', true)->first();
        $this->assertNotNull($defaultSender);
        $this->assertFalse($defaultSender->canBeDeleted()['can']);

        $response = $this->from(route('superadmin.emails.senders.index'))
            ->delete(route('superadmin.emails.senders.destroy', $defaultSender->id));
        $response->assertRedirect(route('superadmin.emails.senders.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('email_sender_identities', ['id' => $defaultSender->id]);
    }

    public function test_cannot_delete_sender_identity_used_by_active_routing_rule(): void
    {
        $this->actingAs($this->superadmin);

        // Create a non-default sender
        $sender = EmailSenderIdentity::create([
            'name' => 'Billing Mailer',
            'email' => 'billing@menu.elab.am',
            'is_default' => false,
            'is_active' => true,
        ]);

        // Assign to a routing rule
        EmailRoutingRule::where('category', EmailRoutingRule::CATEGORY_ORDER_RECEIPT)
            ->update(['sender_identity_id' => $sender->id]);

        $this->assertFalse($sender->canBeDeleted()['can']);

        $response = $this->from(route('superadmin.emails.senders.index'))
            ->delete(route('superadmin.emails.senders.destroy', $sender->id));
        $response->assertRedirect(route('superadmin.emails.senders.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('email_sender_identities', ['id' => $sender->id]);
    }

    public function test_can_safely_delete_unreferenced_sender_identity(): void
    {
        $this->actingAs($this->superadmin);

        $sender = EmailSenderIdentity::create([
            'name' => 'Temporary Sender',
            'email' => 'temp-test@menu.elab.am',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->assertTrue($sender->canBeDeleted()['can']);

        $response = $this->from(route('superadmin.emails.senders.index'))
            ->delete(route('superadmin.emails.senders.destroy', $sender->id));
        $response->assertRedirect(route('superadmin.emails.senders.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('email_sender_identities', ['id' => $sender->id]);
    }

    /**
     * 3. SMTP Connection Management & Encryption
     */
    public function test_smtp_password_is_encrypted_at_rest_and_never_leaked(): void
    {
        $this->actingAs($this->superadmin);

        $plainPassword = 'super-secret-smtp-password-123';

        $connection = EmailConnection::create([
            'name' => 'Secure SMTP Test',
            'transport' => 'smtp',
            'host' => 'mail.secure-example.am',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mailer@secure-example.am',
            'password' => $plainPassword,
            'is_active' => true,
            'is_default' => false,
        ]);

        // Verify in database that raw column does NOT equal plain text
        $rawPassword = DB::table('email_connections')->where('id', $connection->id)->value('password');
        $this->assertNotEquals($plainPassword, $rawPassword);

        // Verify model access decrypts it correctly
        $this->assertEquals($plainPassword, $connection->password);

        // Verify masked representation hides it
        $summary = $connection->toMaskedSummary();
        $this->assertArrayNotHasKey('password', $summary);
        $this->assertTrue($summary['has_password']);
        $this->assertStringContainsString('••••••••', $summary['masked_password']);
    }

    public function test_updating_connection_without_password_preserves_existing_password(): void
    {
        $this->actingAs($this->superadmin);

        $connection = EmailConnection::create([
            'name' => 'Original Connection',
            'transport' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'orig-user',
            'password' => 'initial-secret',
            'timeout' => 15,
            'is_active' => true,
        ]);

        $response = $this->from(route('superadmin.emails.connections.index'))
            ->put(route('superadmin.emails.connections.update', $connection->id), [
                'name' => 'Renamed Connection',
                'transport' => 'smtp',
                'host' => 'smtp.example.com',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'orig-user',
                'password' => '', // Blank should keep existing
                'timeout' => 15,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('superadmin.emails.connections.index'));

        $connection->refresh();
        $this->assertEquals('Renamed Connection', $connection->name);
        $this->assertEquals('initial-secret', $connection->password);
    }

    public function test_send_test_email_diagnostic_action(): void
    {
        $this->actingAs($this->superadmin);
        Mail::fake();

        $connection = EmailConnection::where('is_default', true)->first();

        $response = $this->postJson(route('superadmin.emails.connections.send_test', $connection->id), [
            'recipient' => 'admin-test@menu.elab.am',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        Mail::assertSent(SystemTestMail::class, function ($mail) {
            return $mail->hasTo('admin-test@menu.elab.am');
        });
    }

    /**
     * 4. Email Routing Rules
     */
    public function test_email_routing_service_resolves_configured_sender_and_connection(): void
    {
        $service = app(EmailRoutingService::class);

        $defaultSender = EmailSenderIdentity::where('is_default', true)->first();
        $defaultConn = EmailConnection::where('is_default', true)->first();

        // Standard category resolution
        $resolved = $service->resolve(EmailRoutingRule::CATEGORY_ORDER_RECEIPT);
        $this->assertNotNull($resolved['sender']);
        $this->assertNotNull($resolved['connection']);
        $this->assertEquals($defaultSender->email, $resolved['sender']->email);
        $this->assertEquals($defaultConn->id, $resolved['connection']->id);

        // Unknown category falls back cleanly
        $fallback = $service->resolve('unknown_custom_category');
        $this->assertNotNull($fallback['sender']);
        $this->assertEquals($defaultSender->email, $fallback['sender']->email);
    }

    public function test_superadmin_can_update_routing_rule(): void
    {
        $this->actingAs($this->superadmin);

        $rule = EmailRoutingRule::where('category', EmailRoutingRule::CATEGORY_PASSWORD_RESET)->first();

        $response = $this->from(route('superadmin.emails.routing.index'))
            ->put(route('superadmin.emails.routing.update', $rule->id), [
                'name' => 'High Priority Security Reset',
                'sender_identity_id' => $rule->sender_identity_id,
                'email_connection_id' => $rule->email_connection_id,
                'priority' => 10,
                'is_active' => true,
                'fallback_behavior' => 'default_sender',
            ]);

        $response->assertRedirect(route('superadmin.emails.routing.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('email_routing_rules', [
            'id' => $rule->id,
            'name' => 'High Priority Security Reset',
            'priority' => 10,
        ]);
    }

    /**
     * 5. Email Logs, Filtering, and CSV Export
     */
    public function test_email_logs_can_be_filtered_and_searched(): void
    {
        $this->actingAs($this->superadmin);

        // Create distinct test logs
        EmailLog::create([
            'recipient_email' => 'filter-customer1@elab.am',
            'from_email' => 'no-reply@menu.elab.am',
            'category' => EmailRoutingRule::CATEGORY_ORDER_RECEIPT,
            'subject' => 'Your Order #1001',
            'status' => 'sent',
            'transport' => 'smtp',
        ]);

        EmailLog::create([
            'recipient_email' => 'filter-vendor2@elab.am',
            'from_email' => 'support@menu.elab.am',
            'category' => EmailRoutingRule::CATEGORY_SECURITY_ALERT,
            'subject' => 'Security Notice',
            'status' => 'failed',
            'error_message' => 'Connection timeout to MX',
            'transport' => 'smtp',
        ]);

        // Filter by category
        $response = $this->get(route('superadmin.emails.logs.index', [
            'category' => EmailRoutingRule::CATEGORY_ORDER_RECEIPT,
        ]));
        $response->assertStatus(200);
        $response->assertSee('filter-customer1@elab.am');
        $response->assertDontSee('filter-vendor2@elab.am');

        // Filter by status
        $failedResponse = $this->get(route('superadmin.emails.logs.index', [
            'status' => 'failed',
        ]));
        $failedResponse->assertStatus(200);
        $failedResponse->assertSee('filter-vendor2@elab.am');
        $failedResponse->assertDontSee('filter-customer1@elab.am');
    }

    public function test_email_logs_csv_export(): void
    {
        $this->actingAs($this->superadmin);

        EmailLog::create([
            'recipient_email' => 'csv-export@elab.am',
            'from_email' => 'no-reply@menu.elab.am',
            'category' => EmailRoutingRule::CATEGORY_SYSTEM_TEST,
            'subject' => 'CSV Test Subject',
            'status' => 'sent',
            'transport' => 'smtp',
        ]);

        $response = $this->get(route('superadmin.emails.logs.export', [
            'status' => 'sent',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('csv-export@elab.am', $response->streamedContent());
    }

    /**
     * 6. Queue Monitor & Safe Retries
     */
    public function test_queue_monitor_detects_email_jobs_and_rejects_non_email_jobs(): void
    {
        $this->actingAs($this->superadmin);
        $queueService = app(EmailQueueMonitorService::class);

        // Non-email payload
        $nonEmailPayload = json_encode([
            'displayName' => 'App\\Jobs\\ProcessVendorDeletionJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['command' => 'O:33:"App\\Jobs\\ProcessVendorDeletionJob":0:{}'],
        ]);

        $this->assertFalse($queueService->isEmailJobPayload($nonEmailPayload));

        // Email mailable payload
        $emailPayload = json_encode([
            'displayName' => 'App\\Mail\\OrderReceiptMail',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['command' => 'O:25:"App\\Mail\\OrderReceiptMail":0:{}'],
        ]);

        $this->assertTrue($queueService->isEmailJobPayload($emailPayload));

        // Test rejecting retry of non-email job via HTTP endpoint
        $jobUuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $jobUuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $nonEmailPayload,
            'exception' => 'Simulated critical error',
            'failed_at' => now(),
        ]);

        $retryResponse = $this->postJson(route('superadmin.emails.queue.retry', $jobUuid));
        $retryResponse->assertStatus(200);
        $retryResponse->assertJson(['success' => false]);
        $this->assertStringContainsString('Non-email jobs cannot be retried', $retryResponse->json('message'));
    }

    /**
     * 7. Provider Webhooks & Idempotency
     */
    public function test_provider_webhook_validates_signature_and_handles_events_idempotently(): void
    {
        $log = EmailLog::create([
            'recipient_email' => 'webhook-test@elab.am',
            'from_email' => 'no-reply@menu.elab.am',
            'category' => EmailRoutingRule::CATEGORY_ORDER_RECEIPT,
            'subject' => 'Webhook Delivery Test',
            'status' => 'sent',
            'transport' => 'smtp',
            'provider_message_id' => 'msg-resend-987654',
        ]);

        $secret = config('services.email_webhooks.resend_signing_secret', 'test-signing-secret');
        config(['services.email_webhooks.resend_signing_secret' => $secret]);

        $payload = [
            'type' => 'email.delivered',
            'data' => [
                'email_id' => 'msg-resend-987654',
                'to' => ['webhook-test@elab.am'],
                'created_at' => now()->toIso8601String(),
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, $secret);

        // 1. Invalid signature is rejected
        $badResponse = $this->postJson('/api/webhooks/email/resend', $payload, [
            'resend-signature' => 'invalid-signature-hash',
        ]);
        $badResponse->assertStatus(401);

        // 2. Valid signature is processed
        $goodResponse = $this->postJson('/api/webhooks/email/resend', $payload, [
            'resend-signature' => $signature,
        ]);
        $goodResponse->assertStatus(200);

        $log->refresh();
        $this->assertEquals('delivered', $log->status);
        $this->assertNotNull($log->delivered_at);

        // 3. Replay with identical event payload is idempotent
        $replayResponse = $this->postJson('/api/webhooks/email/resend', $payload, [
            'resend-signature' => $signature,
        ]);
        $replayResponse->assertStatus(200);
        $replayResponse->assertJson(['status' => 'ignored_duplicate']);
    }

    /**
     * 8. Email Templates Rendering & Injection Protection
     */
    public function test_email_template_renders_safely_without_code_execution(): void
    {
        $templateService = app(EmailTemplateService::class);

        // Update template to test interpolation
        EmailTemplate::where('key', 'welcome_verification')->update([
            'intro_text' => 'Hello {{ user_name }}, welcome to {{ site_name }}.',
            'allowed_variables' => ['user_name', 'site_name'],
            'is_active' => true,
        ]);

        $maliciousPayload = '<?php phpinfo(); ?> <script>alert(1)</script>';

        $rendered = $templateService->render('welcome_verification', [
            'user_name' => $maliciousPayload,
            'site_name' => 'QRMenu SaaS',
        ]);

        $this->assertNotNull($rendered);
        // The malicious string should be HTML-escaped to prevent code execution
        $this->assertStringContainsString('&lt;?php phpinfo(); ?&gt;', $rendered['intro']);
        $this->assertStringNotContainsString('<?php', $rendered['intro']);
        $this->assertStringContainsString('QRMenu SaaS', $rendered['intro']);
    }
}
