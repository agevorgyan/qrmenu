<?php

namespace App\Listeners;

use App\Models\EmailAttempt;
use App\Models\EmailLog;
use App\Services\AuditSanitizer;
use App\Services\EmailRoutingService;
use Illuminate\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

class EmailTrackingSubscriber
{
    /**
     * Cache start timestamps for active outgoing message UUIDs.
     *
     * @var array<string, float>
     */
    protected static array $startTimes = [];

    public function __construct(
        protected EmailRoutingService $routingService,
        protected AuditSanitizer $sanitizer
    ) {}

    /**
     * Handle MessageSending event: Apply routing headers and persist initial log.
     */
    public function handleMessageSending(MessageSending $event): void
    {
        try {
            $message = $event->message;

            // 1. Extract recipient details
            $toAddresses = $message->getTo();
            if (empty($toAddresses)) {
                return;
            }

            $primaryTo = $toAddresses[0];
            $recipientEmail = $primaryTo->getAddress();
            $recipientName = $primaryTo->getName() ?: null;
            $subject = (string) $message->getSubject();

            // 2. Resolve Category & Mailable
            $mailable = $event->data['__mailable'] ?? null;
            $mailableClass = $mailable ? get_class($mailable) : null;
            $category = $this->routingService->resolveCategoryForMailable($mailable, $subject);

            // 3. Resolve category routing (sender identity and transport connection)
            $routing = $this->routingService->resolveRouting($category);
            $sender = $routing['sender'];
            $connection = $routing['connection'];

            // 4. Dynamically configure From & Reply-To headers from the assigned Sender Identity
            if ($sender && $sender->is_active) {
                $message->from(new Address($sender->email, $sender->name));

                if (! empty($sender->reply_to_email)) {
                    $message->replyTo(new Address($sender->reply_to_email, $sender->reply_to_name ?: $sender->name));
                }
            }

            // 5. Generate and attach unique tracking UUID
            $uuid = (string) Str::uuid();
            $message->getHeaders()->addTextHeader('X-QRMenu-Log-Uuid', $uuid);
            $message->getHeaders()->addTextHeader('X-QRMenu-Category', $category);

            self::$startTimes[$uuid] = microtime(true);

            // 6. Extract sender from message
            $fromAddresses = $message->getFrom();
            $fromEmail = ! empty($fromAddresses) ? $fromAddresses[0]->getAddress() : config('mail.from.address');
            $fromName = ! empty($fromAddresses) ? $fromAddresses[0]->getName() : config('mail.from.name');

            $replyToAddresses = $message->getReplyTo();
            $replyToEmail = ! empty($replyToAddresses) ? $replyToAddresses[0]->getAddress() : null;

            // Extract related business model metadata if present
            $relatedType = null;
            $relatedId = null;
            $vendorId = null;

            if ($mailable && isset($mailable->order) && is_object($mailable->order)) {
                $relatedType = 'App\Models\Order';
                $relatedId = $mailable->order->id ?? null;
                $vendorId = $mailable->order->vendor_id ?? null;
            } elseif ($mailable && isset($mailable->user) && is_object($mailable->user)) {
                $relatedType = 'App\Models\User';
                $relatedId = $mailable->user->id ?? null;
                $vendorId = $mailable->user->vendor_id ?? null;
            }

            // 7. Persist to email_logs with STATUS_PROCESSING
            EmailLog::create([
                'uuid' => $uuid,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => $subject,
                'category' => $category,
                'mailable_class' => $mailableClass,
                'sender_identity_id' => $sender?->id,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'reply_to_email' => $replyToEmail,
                'email_connection_id' => $connection?->id,
                'connection_name' => $connection?->name ?? 'Default SMTP',
                'transport' => $connection?->transport ?? config('mail.default', 'smtp'),
                'status' => EmailLog::STATUS_PROCESSING,
                'attempts' => 1,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'vendor_id' => $vendorId,
                'last_attempt_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('EmailTrackingSubscriber: Failed to track MessageSending: '.$e->getMessage());
        }
    }

    /**
     * Handle MessageSent event: Update log to accepted/sent and record attempt.
     */
    public function handleMessageSent(MessageSent $event): void
    {
        try {
            $sentMessage = $event->sent->getSymfonySentMessage();
            $originalMessage = $sentMessage->getOriginalMessage();
            $messageId = $sentMessage->getMessageId();

            $uuid = null;
            if ($originalMessage && $originalMessage->getHeaders()->has('X-QRMenu-Log-Uuid')) {
                $uuid = $originalMessage->getHeaders()->getHeaderBody('X-QRMenu-Log-Uuid');
            }

            $log = null;
            if ($uuid) {
                $log = EmailLog::where('uuid', $uuid)->first();
            }

            // Fallback match by latest recipient and subject if UUID was stripped by transport
            if (! $log) {
                $toAddresses = $event->message->getTo();
                $recipientEmail = ! empty($toAddresses) ? $toAddresses[0]->getAddress() : null;
                $subject = (string) $event->message->getSubject();

                if ($recipientEmail) {
                    $log = EmailLog::where('recipient_email', $recipientEmail)
                        ->where('subject', $subject)
                        ->where('status', EmailLog::STATUS_PROCESSING)
                        ->latest()
                        ->first();
                }
            }

            if (! $log) {
                return;
            }

            $startTime = $uuid && isset(self::$startTimes[$uuid]) ? self::$startTimes[$uuid] : null;
            $durationMs = $startTime ? (int) round((microtime(true) - $startTime) * 1000) : null;
            if ($uuid) {
                unset(self::$startTimes[$uuid]);
            }

            // Mark as sent (accepted by transport)
            $log->update([
                'status' => EmailLog::STATUS_SENT,
                'provider_message_id' => $messageId,
                'duration_ms' => $durationMs,
                'error_code' => null,
                'error_message' => null,
                'last_attempt_at' => now(),
            ]);

            // Persist successful attempt
            EmailAttempt::create([
                'email_log_id' => $log->id,
                'attempt_number' => $log->attempts ?: 1,
                'status' => 'sent',
                'connection_name' => $log->connection_name,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('EmailTrackingSubscriber: Failed to track MessageSent: '.$e->getMessage());
        }
    }

    /**
     * Handle JobQueued event: If mailable is queued, log as STATUS_QUEUED.
     */
    public function handleJobQueued(JobQueued $event): void
    {
        try {
            $job = $event->job;
            $displayName = is_object($job) && method_exists($job, 'displayName') ? $job->displayName() : null;

            if (! $displayName || (! str_starts_with($displayName, 'App\\Mail\\') && ! str_contains($displayName, 'SendQueuedMailable'))) {
                return;
            }

            // If payload contains email details, record initial queued status
            $jobId = $event->id ?? null;
            if ($jobId) {
                // If a log was created with this job, attach queue_job_id
                $latestLog = EmailLog::where('status', EmailLog::STATUS_PROCESSING)
                    ->whereNull('queue_job_id')
                    ->latest()
                    ->first();

                if ($latestLog) {
                    $latestLog->update([
                        'queue_job_id' => (string) $jobId,
                        'status' => EmailLog::STATUS_QUEUED,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore queue event tracking errors
        }
    }

    /**
     * Handle JobFailed event: If an email job fails, update status to failed with sanitized error.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        try {
            $displayName = $event->job->resolveName();

            if (! str_starts_with($displayName, 'App\\Mail\\') && ! str_contains($displayName, 'SendQueuedMailable')) {
                return;
            }

            $exception = $event->exception;
            $cleanError = $this->sanitizer->sanitizeString($exception->getMessage());

            // Find matching log by queue_job_id or recent processing log
            $jobId = (string) $event->job->getJobId();
            $log = EmailLog::where('queue_job_id', $jobId)->first()
                ?? EmailLog::where('mailable_class', $displayName)->where('status', EmailLog::STATUS_PROCESSING)->latest()->first();

            if ($log) {
                $log->update([
                    'status' => EmailLog::STATUS_FAILED,
                    'error_code' => (string) $exception->getCode(),
                    'error_message' => $cleanError,
                    'last_attempt_at' => now(),
                ]);

                EmailAttempt::create([
                    'email_log_id' => $log->id,
                    'attempt_number' => $log->attempts ?: 1,
                    'status' => 'failed',
                    'connection_name' => $log->connection_name,
                    'error_code' => (string) $exception->getCode(),
                    'error_message' => $cleanError,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('EmailTrackingSubscriber: Failed to track JobFailed: '.$e->getMessage());
        }
    }

    /**
     * Register listeners with the Laravel Event Dispatcher.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageSending::class => 'handleMessageSending',
            MessageSent::class => 'handleMessageSent',
            JobQueued::class => 'handleJobQueued',
            JobFailed::class => 'handleJobFailed',
        ];
    }
}
