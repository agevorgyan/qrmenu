<?php

namespace App\Http\Controllers;

use App\Models\EmailConfigurationAudit;
use App\Models\EmailConnection;
use App\Models\EmailLog;
use App\Models\EmailRoutingRule;
use App\Models\EmailSenderIdentity;
use App\Models\EmailTemplate;
use App\Models\SystemSetting;
use App\Models\Vendor;
use App\Services\EmailAnalyticsService;
use App\Services\EmailLogService;
use App\Services\EmailQueueMonitorService;
use App\Services\EmailRoutingService;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminEmailController extends Controller
{
    public function __construct(
        protected EmailAnalyticsService $analyticsService,
        protected EmailLogService $logService,
        protected EmailRoutingService $routingService,
        protected EmailQueueMonitorService $queueMonitorService,
        protected EmailTemplateService $templateService
    ) {}

    /**
     * 1. Overview Dashboard.
     */
    public function overview(Request $request): View
    {
        $filters = $request->only(['range', 'from_date', 'to_date', 'category', 'sender_id', 'connection_id']);
        $metrics = $this->analyticsService->getOverviewMetrics($filters);
        $volumeChart = $this->analyticsService->getVolumeByDay($filters, 14);
        $recentLogs = $this->logService->getFilteredLogs($filters, 8);
        $queueStats = $this->queueMonitorService->getQueueStats();
        $connections = EmailConnection::where('is_active', true)->get();
        $senders = EmailSenderIdentity::where('is_active', true)->get();

        return view('superadmin.emails.overview', compact(
            'metrics',
            'volumeChart',
            'recentLogs',
            'queueStats',
            'connections',
            'senders',
            'filters'
        ));
    }

    /**
     * 2. Sender Identities Index & Management.
     */
    public function sendersIndex(): View
    {
        $senders = EmailSenderIdentity::with(['connection', 'creator'])
            ->withCount(['routingRules', 'logs'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $connections = EmailConnection::where('is_active', true)->get();
        $categories = EmailRoutingRule::categories();

        return view('superadmin.emails.senders', compact('senders', 'connections', 'categories'));
    }

    /**
     * Store new sender identity.
     */
    public function storeSender(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:email_sender_identities,email,NULL,id,deleted_at,NULL',
            'description' => 'nullable|string|max:500',
            'reply_to_email' => 'nullable|email|max:255',
            'reply_to_name' => 'nullable|string|max:255',
            'email_connection_id' => 'nullable|exists:email_connections,id',
            'assigned_categories' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($validated, $isDefault) {
            if ($isDefault) {
                EmailSenderIdentity::where('is_default', true)->update(['is_default' => false]);
            }

            $sender = EmailSenderIdentity::create([
                'name' => $validated['name'],
                'email' => strtolower(trim($validated['email'])),
                'description' => $validated['description'] ?? null,
                'reply_to_email' => ! empty($validated['reply_to_email']) ? strtolower(trim($validated['reply_to_email'])) : null,
                'reply_to_name' => $validated['reply_to_name'] ?? null,
                'email_connection_id' => $validated['email_connection_id'] ?? null,
                'assigned_categories' => $validated['assigned_categories'] ?? [],
                'is_default' => $isDefault,
                'is_active' => true,
                'verification_status' => 'verified',
                'created_by_user_id' => auth()->id(),
            ]);

            EmailConfigurationAudit::log(
                'sender_created',
                'EmailSenderIdentity',
                $sender->id,
                $sender->email,
                ['name' => $sender->name, 'is_default' => $sender->is_default]
            );
        });

        return back()->with('success', 'Sender identity created successfully.');
    }

    /**
     * Update sender identity.
     */
    public function updateSender(Request $request, EmailSenderIdentity $sender): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('email_sender_identities')->ignore($sender->id)->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:500',
            'reply_to_email' => 'nullable|email|max:255',
            'reply_to_name' => 'nullable|string|max:255',
            'email_connection_id' => 'nullable|exists:email_connections,id',
            'assigned_categories' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($sender, $validated, $isDefault) {
            if ($isDefault && ! $sender->is_default) {
                EmailSenderIdentity::where('is_default', true)->update(['is_default' => false]);
            }

            $sender->update([
                'name' => $validated['name'],
                'email' => strtolower(trim($validated['email'])),
                'description' => $validated['description'] ?? null,
                'reply_to_email' => ! empty($validated['reply_to_email']) ? strtolower(trim($validated['reply_to_email'])) : null,
                'reply_to_name' => $validated['reply_to_name'] ?? null,
                'email_connection_id' => $validated['email_connection_id'] ?? null,
                'assigned_categories' => $validated['assigned_categories'] ?? [],
                'is_default' => $isDefault ? true : $sender->is_default,
                'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $sender->is_active,
            ]);

            EmailConfigurationAudit::log(
                'sender_updated',
                'EmailSenderIdentity',
                $sender->id,
                $sender->email,
                $validated
            );
        });

        return back()->with('success', "Sender identity '{$sender->name}' updated successfully.");
    }

    /**
     * Toggle sender active state.
     */
    public function toggleSender(EmailSenderIdentity $sender): RedirectResponse
    {
        if ($sender->is_default && $sender->is_active) {
            return back()->with('error', 'The active default sender cannot be deactivated. Select a new default sender first.');
        }

        $sender->update(['is_active' => ! $sender->is_active]);

        EmailConfigurationAudit::log(
            'sender_toggled',
            'EmailSenderIdentity',
            $sender->id,
            $sender->email,
            ['is_active' => $sender->is_active]
        );

        $state = $sender->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Sender identity '{$sender->name}' {$state}.");
    }

    /**
     * Safely delete sender identity.
     */
    public function destroySender(EmailSenderIdentity $sender): RedirectResponse
    {
        $check = $sender->canBeDeleted();
        if (! $check['can']) {
            return back()->with('error', $check['reason']);
        }

        $name = $sender->name;
        $sender->delete();

        EmailConfigurationAudit::log(
            'sender_deleted',
            'EmailSenderIdentity',
            $sender->id,
            $name,
            ['email' => $sender->email]
        );

        return back()->with('success', "Sender identity '{$name}' safely removed.");
    }

    /**
     * 3. SMTP Connections Index & Management.
     */
    public function connectionsIndex(): View
    {
        $connections = EmailConnection::withCount(['senders', 'routingRules', 'logs'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('superadmin.emails.connections', compact('connections'));
    }

    /**
     * Store new connection.
     */
    public function storeConnection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'transport' => 'required|string|in:smtp,sendmail,log,array',
            'host' => 'nullable|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'nullable|string|in:tls,ssl,starttls,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:500',
            'timeout' => 'required|integer|min:2|max:120',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($validated, $isDefault) {
            if ($isDefault) {
                EmailConnection::where('is_default', true)->update(['is_default' => false]);
            }

            $conn = EmailConnection::create([
                'name' => $validated['name'],
                'transport' => $validated['transport'],
                'host' => $validated['host'] ?? null,
                'port' => $validated['port'],
                'encryption' => $validated['encryption'] ?? 'tls',
                'username' => $validated['username'] ?? null,
                'password' => $validated['password'] ?? null,
                'timeout' => $validated['timeout'],
                'is_active' => true,
                'is_default' => $isDefault,
            ]);

            EmailConfigurationAudit::log(
                'connection_created',
                'EmailConnection',
                $conn->id,
                $conn->name,
                ['transport' => $conn->transport, 'host' => $conn->host, 'port' => $conn->port]
            );
        });

        return back()->with('success', 'SMTP connection configuration created.');
    }

    /**
     * Update connection.
     */
    public function updateConnection(Request $request, EmailConnection $connection): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'transport' => 'required|string|in:smtp,sendmail,log,array',
            'host' => 'nullable|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'nullable|string|in:tls,ssl,starttls,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:500',
            'timeout' => 'required|integer|min:2|max:120',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($connection, $validated, $isDefault) {
            if ($isDefault && ! $connection->is_default) {
                EmailConnection::where('is_default', true)->update(['is_default' => false]);
            }

            $data = [
                'name' => $validated['name'],
                'transport' => $validated['transport'],
                'host' => $validated['host'] ?? null,
                'port' => $validated['port'],
                'encryption' => $validated['encryption'] ?? 'tls',
                'username' => $validated['username'] ?? null,
                'timeout' => $validated['timeout'],
                'is_default' => $isDefault ? true : $connection->is_default,
                'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $connection->is_active,
            ];

            // Only update password if a new value is explicitly provided
            if (filled($validated['password'])) {
                $data['password'] = $validated['password'];
            }

            $connection->update($data);

            EmailConfigurationAudit::log(
                'connection_updated',
                'EmailConnection',
                $connection->id,
                $connection->name,
                ['transport' => $connection->transport, 'host' => $connection->host, 'port' => $connection->port]
            );
        });

        return back()->with('success', "Connection '{$connection->name}' updated successfully.");
    }

    /**
     * Test connection socket & authentication without sending email.
     */
    public function testConnection(EmailConnection $connection): JsonResponse
    {
        $result = $connection->testConnection();

        EmailConfigurationAudit::log(
            'connection_tested',
            'EmailConnection',
            $connection->id,
            $connection->name,
            ['success' => $result['success'], 'duration_ms' => $result['duration_ms']]
        );

        return response()->json($result);
    }

    /**
     * Send live diagnostic test email to explicit recipient.
     */
    public function sendTestEmail(Request $request, EmailConnection $connection): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => 'required|email|max:255',
            'sender_id' => 'nullable|exists:email_sender_identities,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $result = $this->routingService->sendTestEmail(
            recipient: strtolower(trim($validated['recipient'])),
            connectionId: $connection->id,
            senderId: $validated['sender_id'] ?? null,
            notes: $validated['notes'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Set connection as active default.
     */
    public function setDefaultConnection(EmailConnection $connection): RedirectResponse
    {
        DB::transaction(function () use ($connection) {
            EmailConnection::where('is_default', true)->update(['is_default' => false]);
            $connection->update(['is_default' => true, 'is_active' => true]);

            EmailConfigurationAudit::log(
                'default_connection_changed',
                'EmailConnection',
                $connection->id,
                $connection->name
            );
        });

        // Immediately update runtime configuration
        $this->routingService->applyActiveMailConfiguration();

        return back()->with('success', "'{$connection->name}' is now the primary active default mail connection.");
    }

    /**
     * Safe delete connection.
     */
    public function destroyConnection(EmailConnection $connection): RedirectResponse
    {
        if ($connection->is_default) {
            return back()->with('error', 'Cannot delete the active default connection. Set another connection as default first.');
        }

        $activeSenders = $connection->senders()->where('is_active', true)->count();
        if ($activeSenders > 0) {
            return back()->with('error', "Cannot delete connection: assigned to {$activeSenders} active sender identities.");
        }

        $name = $connection->name;
        $connection->delete();

        EmailConfigurationAudit::log(
            'connection_deleted',
            'EmailConnection',
            $connection->id,
            $name
        );

        return back()->with('success', "Connection '{$name}' safely removed.");
    }

    /**
     * 4. Email Routing Rules.
     */
    public function routingIndex(): View
    {
        $rules = EmailRoutingRule::with(['sender', 'connection'])
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        $senders = EmailSenderIdentity::where('is_active', true)->get();
        $connections = EmailConnection::where('is_active', true)->get();
        $categories = EmailRoutingRule::categories();

        return view('superadmin.emails.routing', compact('rules', 'senders', 'connections', 'categories'));
    }

    /**
     * Update routing rule.
     */
    public function updateRouting(Request $request, EmailRoutingRule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'priority' => 'nullable|integer',
            'sender_identity_id' => 'nullable|exists:email_sender_identities,id',
            'email_connection_id' => 'nullable|exists:email_connections,id',
            'fallback_behavior' => 'required|string|in:default_sender,system_fallback,fail',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [
            'sender_identity_id' => $validated['sender_identity_id'] ?? null,
            'email_connection_id' => $validated['email_connection_id'] ?? null,
            'fallback_behavior' => $validated['fallback_behavior'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['priority'])) {
            $updateData['priority'] = (int) $validated['priority'];
        }

        $rule->update($updateData);

        EmailConfigurationAudit::log(
            'routing_rule_updated',
            'EmailRoutingRule',
            $rule->id,
            $rule->name,
            $validated
        );

        return back()->with('success', "Routing rule for '{$rule->name}' updated.");
    }

    /**
     * 5. Email Templates Management.
     */
    public function templatesIndex(): View
    {
        $templates = $this->templateService->getAllTemplates();

        return view('superadmin.emails.templates', compact('templates'));
    }

    /**
     * Update template structured fields.
     */
    public function updateTemplate(Request $request, EmailTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'intro_text' => 'nullable|string|max:2000',
            'cta_text' => 'nullable|string|max:100',
            'cta_url_template' => 'nullable|string|max:500',
            'footer_text' => 'nullable|string|max:1000',
            'support_contact' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $this->templateService->updateTemplate($template, $validated);

        return back()->with('success', "Email template '{$template->name}' updated successfully (v{$template->version}).");
    }

    /**
     * Safe preview of email template with mock test data.
     */
    public function previewTemplate(EmailTemplate $template): JsonResponse
    {
        $sampleData = $template->getPreviewSampleData();
        $rendered = $this->templateService->render($template->key, $sampleData);

        return response()->json([
            'template' => $template,
            'preview' => $rendered,
            'sample_data' => $sampleData,
        ]);
    }

    /**
     * 6. Searchable & Auditable Logs.
     */
    public function logsIndex(Request $request): View
    {
        $filters = $request->only(['search', 'category', 'status', 'sender_id', 'connection_id', 'vendor_id', 'range', 'from_date', 'to_date']);
        $logs = $this->logService->getFilteredLogs($filters, 25);
        $senders = EmailSenderIdentity::all();
        $connections = EmailConnection::all();
        $categories = EmailRoutingRule::categories();
        $vendors = Vendor::select('id', 'name')->orderBy('name')->take(100)->get();

        return view('superadmin.emails.logs', compact('logs', 'senders', 'connections', 'categories', 'vendors', 'filters'));
    }

    /**
     * View log details (attempts history and provider events).
     */
    public function showLog(EmailLog $log): JsonResponse
    {
        $log->load(['senderIdentity', 'connection', 'vendor', 'attemptsList', 'providerEvents']);

        return response()->json([
            'log' => $log,
            'attempts' => $log->attemptsList,
            'events' => $log->providerEvents,
        ]);
    }

    /**
     * Export logs to CSV.
     */
    public function exportLogs(Request $request): StreamedResponse
    {
        $filters = $request->only(['search', 'category', 'status', 'sender_id', 'connection_id', 'vendor_id', 'range', 'from_date', 'to_date']);

        EmailConfigurationAudit::log('logs_exported_csv', 'EmailLog', null, 'CSV Export', $filters);

        return $this->logService->exportLogsCsv($filters);
    }

    /**
     * 7. Queue Monitor.
     */
    public function queueIndex(): View
    {
        $stats = $this->queueMonitorService->getQueueStats();
        $pendingJobs = $this->queueMonitorService->getPendingJobs(30);
        $failedJobs = $this->queueMonitorService->getFailedJobs(30);

        return view('superadmin.emails.queue', compact('stats', 'pendingJobs', 'failedJobs'));
    }

    /**
     * Retry failed job safely.
     */
    public function retryJob(string $uuid): JsonResponse
    {
        $result = $this->queueMonitorService->retryFailedJob($uuid);

        return response()->json($result);
    }

    /**
     * Cancel pending queue job.
     */
    public function cancelJob(int $id): JsonResponse
    {
        $result = $this->queueMonitorService->cancelPendingJob($id);

        return response()->json($result);
    }

    /**
     * 8. Failed Emails Dedicated Screen.
     */
    public function failedIndex(Request $request): View
    {
        $filters = array_merge($request->only(['search', 'category', 'range']), ['status' => 'failed']);
        $failedLogs = $this->logService->getFilteredLogs($filters, 25);
        $commonReasons = $this->analyticsService->getCommonFailureReasons(8);

        return view('superadmin.emails.failed', compact('failedLogs', 'commonReasons', 'filters'));
    }

    /**
     * Retry failed email log.
     */
    public function retryEmail(EmailLog $log): JsonResponse
    {
        $result = $this->routingService->retryFailedEmail($log);

        return response()->json($result);
    }

    /**
     * Cancel eligible queued email log.
     */
    public function cancelEmail(EmailLog $log): JsonResponse
    {
        $result = $this->routingService->cancelQueuedEmail($log);

        return response()->json($result);
    }

    /**
     * 9. Reports & Analytics.
     */
    public function reportsIndex(Request $request): View
    {
        $filters = $request->only(['range', 'from_date', 'to_date', 'category', 'connection_id', 'sender_id']);
        $metrics = $this->analyticsService->getOverviewMetrics($filters);
        $volumeByDay = $this->analyticsService->getVolumeByDay($filters, 30);
        $byCategory = $this->analyticsService->getVolumeByCategory($filters);
        $bySender = $this->analyticsService->getVolumeBySender($filters);
        $byConnection = $this->analyticsService->getVolumeByConnection($filters);
        $commonReasons = $this->analyticsService->getCommonFailureReasons(10);

        return view('superadmin.emails.reports', compact(
            'metrics',
            'volumeByDay',
            'byCategory',
            'bySender',
            'byConnection',
            'commonReasons',
            'filters'
        ));
    }

    /**
     * Export reports CSV.
     */
    public function exportReport(Request $request): StreamedResponse
    {
        $filters = $request->only(['range', 'from_date', 'to_date', 'category', 'connection_id', 'sender_id']);

        EmailConfigurationAudit::log('report_exported_csv', 'EmailReport', null, 'Report Export', $filters);

        return $this->analyticsService->exportReportCsv($filters);
    }

    /**
     * 10. Global Email Settings.
     */
    public function settingsIndex(): View
    {
        $settings = [
            'email_retention_days' => SystemSetting::get('email_retention_days', 90),
            'email_webhook_secret' => SystemSetting::get('email_webhook_secret', ''),
            'email_rate_limit_per_min' => SystemSetting::get('email_rate_limit_per_min', 60),
            'email_alert_on_failure' => SystemSetting::get('email_alert_on_failure', false),
            'email_admin_alert_recipient' => SystemSetting::get('email_admin_alert_recipient', 'admin@menu.elab.am'),
        ];

        $recentAudits = EmailConfigurationAudit::with('user')->latest('created_at')->take(20)->get();

        return view('superadmin.emails.settings', compact('settings', 'recentAudits'));
    }

    /**
     * Update global email settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_retention_days' => 'required|integer|min:7|max:730',
            'email_webhook_secret' => 'nullable|string|max:255',
            'email_rate_limit_per_min' => 'required|integer|min:5|max:1000',
            'email_alert_on_failure' => 'nullable|boolean',
            'email_admin_alert_recipient' => 'required|email|max:255',
        ]);

        foreach ($validated as $k => $v) {
            SystemSetting::set($k, $v, 'email');
        }

        EmailConfigurationAudit::log('settings_updated', 'SystemSetting', null, 'Email Settings', $validated);

        return back()->with('success', 'Email management platform settings updated successfully.');
    }
}
