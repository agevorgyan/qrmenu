<?php

namespace App\Services;

use App\Mail\OrderReceiptMail;
use App\Mail\ResetPasswordMail;
use App\Mail\SystemTestMail;
use App\Mail\TwoFactorCodeMail;
use App\Mail\VendorWelcomeVerificationMail;
use App\Models\EmailConfigurationAudit;
use App\Models\EmailConnection;
use App\Models\EmailLog;
use App\Models\EmailRoutingRule;
use App\Models\EmailSenderIdentity;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailRoutingService
{
    /**
     * Resolve active sender identity and connection for a given category.
     *
     * @return array{sender: ?EmailSenderIdentity, connection: ?EmailConnection, rule: ?EmailRoutingRule}
     */
    public function resolve(string $category): array
    {
        return $this->resolveRouting($category);
    }

    /**
     * Resolve active sender identity and connection for a given category.
     *
     * @return array{sender: ?EmailSenderIdentity, connection: ?EmailConnection, rule: ?EmailRoutingRule}
     */
    public function resolveRouting(string $category): array
    {
        $rule = EmailRoutingRule::where('category', $category)
            ->where('is_active', true)
            ->with(['sender', 'connection'])
            ->first();

        $sender = null;
        $connection = null;

        if ($rule) {
            $sender = ($rule->sender && $rule->sender->is_active) ? $rule->sender : null;
            $connection = ($rule->connection && $rule->connection->is_active) ? $rule->connection : null;
        }

        // Fallback to active default sender identity if not specified or inactive
        if (! $sender) {
            $sender = EmailSenderIdentity::where('is_default', true)
                ->where('is_active', true)
                ->first()
                ?? EmailSenderIdentity::where('is_active', true)->first();
        }

        // Fallback to sender's assigned connection, or default connection
        if (! $connection && $sender && $sender->connection && $sender->connection->is_active) {
            $connection = $sender->connection;
        }

        if (! $connection) {
            $connection = EmailConnection::where('is_default', true)
                ->where('is_active', true)
                ->first()
                ?? EmailConnection::where('is_active', true)->first();
        }

        return [
            'sender' => $sender,
            'connection' => $connection,
            'rule' => $rule,
        ];
    }

    /**
     * Infer or detect the email category from a mailable instance, class name, or subject.
     */
    public function resolveCategoryForMailable(mixed $mailable, ?string $subject = null): string
    {
        if (is_object($mailable) && isset($mailable->category) && ! empty($mailable->category)) {
            return (string) $mailable->category;
        }

        $className = is_object($mailable) ? get_class($mailable) : (string) $mailable;

        return match ($className) {
            VendorWelcomeVerificationMail::class => EmailRoutingRule::CATEGORY_VENDOR_ONBOARDING,
            OrderReceiptMail::class => EmailRoutingRule::CATEGORY_ORDER_RECEIPT,
            ResetPasswordMail::class => EmailRoutingRule::CATEGORY_PASSWORD_RESET,
            TwoFactorCodeMail::class => EmailRoutingRule::CATEGORY_TWO_FACTOR,
            SystemTestMail::class => EmailRoutingRule::CATEGORY_SYSTEM_TEST,
            default => $this->detectCategoryFromSubject($subject),
        };
    }

    /**
     * Fallback heuristic based on subject line.
     */
    protected function detectCategoryFromSubject(?string $subject): string
    {
        if (empty($subject)) {
            return EmailRoutingRule::CATEGORY_ADMIN_ALERT;
        }

        $lower = mb_strtolower($subject);

        if (str_contains($lower, '2fa') || str_contains($lower, 'երկփուլային') || str_contains($lower, 'verification code')) {
            return EmailRoutingRule::CATEGORY_TWO_FACTOR;
        }
        if (str_contains($lower, 'password') || str_contains($lower, 'գաղտնաբառ')) {
            return EmailRoutingRule::CATEGORY_PASSWORD_RESET;
        }
        if (str_contains($lower, 'order') || str_contains($lower, 'պատվեր')) {
            return EmailRoutingRule::CATEGORY_ORDER_RECEIPT;
        }
        if (str_contains($lower, 'welcome') || str_contains($lower, 'բարի գալուստ') || str_contains($lower, 'verify')) {
            return EmailRoutingRule::CATEGORY_VERIFICATION;
        }

        return EmailRoutingRule::CATEGORY_ADMIN_ALERT;
    }

    /**
     * Dynamically apply the default active connection and sender identity to Laravel's mail config.
     */
    public function applyActiveMailConfiguration(): void
    {
        try {
            $connection = EmailConnection::where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($connection) {
                $mailerConfig = $connection->toMailerConfig();
                config(['mail.mailers.smtp' => $mailerConfig]);

                if ($connection->transport !== 'smtp') {
                    config(["mail.mailers.{$connection->transport}" => $mailerConfig]);
                    config(['mail.default' => $connection->transport]);
                } else {
                    config(['mail.default' => 'smtp']);
                }

                // Purge resolved mailers to force reload
                if (app()->bound('mail.manager')) {
                    app('mail.manager')->purge('smtp');
                    if ($connection->transport !== 'smtp') {
                        app('mail.manager')->purge($connection->transport);
                    }
                }
            }

            $sender = EmailSenderIdentity::where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($sender) {
                config([
                    'mail.from.address' => $sender->email,
                    'mail.from.name' => $sender->name,
                ]);
            }
        } catch (\Throwable $e) {
            // Silently allow fallback to .env configuration if DB is unreachable
            Log::warning('Could not apply database mail configuration: '.$e->getMessage());
        }
    }

    /**
     * Send a real diagnostic test email using the specified connection and sender.
     *
     * @return array{success: bool, message: string, log_id: ?int, duration_ms: int}
     */
    public function sendTestEmail(string $recipient, int $connectionId, ?int $senderId = null, ?string $notes = null): array
    {
        $connection = EmailConnection::findOrFail($connectionId);
        $sender = $senderId ? EmailSenderIdentity::find($senderId) : $connection->senders()->where('is_active', true)->first();

        if (! $sender) {
            $sender = EmailSenderIdentity::where('is_default', true)->first()
                ?? new EmailSenderIdentity([
                    'name' => config('mail.from.name', 'QRMenu Test'),
                    'email' => config('mail.from.address', 'test@menu.elab.am'),
                ]);
        }

        $mailerKey = "connection_{$connection->id}_test";
        config(["mail.mailers.{$mailerKey}" => $connection->toMailerConfig()]);

        if (app()->bound('mail.manager')) {
            app('mail.manager')->purge($mailerKey);
        }

        $mailable = new SystemTestMail($recipient, $connection, $sender, $notes);
        $mailable->mailer($mailerKey);

        $startTime = microtime(true);

        try {
            Mail::to($recipient)->send($mailable);
            $duration = (int) round((microtime(true) - $startTime) * 1000);

            EmailConfigurationAudit::log(
                'test_email_sent',
                'EmailConnection',
                $connection->id,
                $connection->name,
                ['recipient' => $recipient, 'duration_ms' => $duration]
            );

            return [
                'success' => true,
                'message' => "Test email successfully dispatched to {$recipient} via {$connection->name} ({$duration}ms).",
                'log_id' => EmailLog::where('recipient_email', $recipient)->latest()->value('id'),
                'duration_ms' => $duration,
            ];
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $startTime) * 1000);
            $cleanError = $connection->password ? str_replace($connection->password, '••••', $e->getMessage()) : $e->getMessage();

            return [
                'success' => false,
                'message' => "Failed to send test email: {$cleanError}",
                'log_id' => null,
                'duration_ms' => $duration,
            ];
        }
    }

    /**
     * Safely retry a failed outgoing email.
     *
     * @return array{success: bool, message: string}
     */
    public function retryFailedEmail(EmailLog $log): array
    {
        if (! $log->canRetry()) {
            return [
                'success' => false,
                'message' => 'This email is not eligible for safe retry.',
            ];
        }

        // Prevent duplicate order receipts if order was already notified
        if ($log->category === EmailRoutingRule::CATEGORY_ORDER_RECEIPT && $log->related_id) {
            $alreadySent = EmailLog::where('category', EmailRoutingRule::CATEGORY_ORDER_RECEIPT)
                ->where('related_id', $log->related_id)
                ->where('id', '!=', $log->id)
                ->whereIn('status', [EmailLog::STATUS_SENT, EmailLog::STATUS_DELIVERED])
                ->exists();

            if ($alreadySent) {
                return [
                    'success' => false,
                    'message' => 'Safeguard triggered: A receipt for this order was already successfully sent. Duplicate send prevented.',
                ];
            }
        }

        try {
            $mailableClass = $log->mailable_class;
            $newMailable = null;

            if ($mailableClass === OrderReceiptMail::class && $log->related_id) {
                $order = Order::find($log->related_id);
                if ($order) {
                    $newMailable = new OrderReceiptMail($order);
                }
            } elseif ($mailableClass === VendorWelcomeVerificationMail::class && $log->related_id) {
                $user = User::find($log->related_id);
                if ($user) {
                    $verificationUrl = route('verification.verify', [
                        'id' => $user->id,
                        'hash' => sha1($user->getEmailForVerification()),
                    ]);
                    $newMailable = new VendorWelcomeVerificationMail($user, $verificationUrl);
                }
            } elseif ($mailableClass === SystemTestMail::class) {
                $connection = $log->connection ?? EmailConnection::where('is_active', true)->first();
                if ($connection) {
                    $newMailable = new SystemTestMail($log->recipient_email, $connection, $log->senderIdentity);
                }
            }

            if (! $newMailable) {
                return [
                    'success' => false,
                    'message' => 'Underlying business record is no longer available or email type cannot be safely reconstructed.',
                ];
            }

            // Update log attempt count and set back to processing
            $log->update([
                'status' => EmailLog::STATUS_PROCESSING,
                'attempts' => $log->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            Mail::to($log->recipient_email)->send($newMailable);

            EmailConfigurationAudit::log(
                'email_retried',
                'EmailLog',
                $log->id,
                $log->recipient_email,
                ['category' => $log->category, 'previous_attempts' => $log->attempts]
            );

            return [
                'success' => true,
                'message' => "Email to {$log->recipient_email} successfully retried.",
            ];
        } catch (\Throwable $e) {
            $log->update([
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => "Retry attempt failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Safely cancel a queued or pending email.
     *
     * @return array{success: bool, message: string}
     */
    public function cancelQueuedEmail(EmailLog $log): array
    {
        if ($log->status !== EmailLog::STATUS_QUEUED) {
            return [
                'success' => false,
                'message' => 'Only queued emails can be cancelled.',
            ];
        }

        DB::transaction(function () use ($log) {
            $log->update([
                'status' => EmailLog::STATUS_CANCELLED,
                'error_message' => 'Cancelled by SuperAdmin operator.',
            ]);

            if ($log->queue_job_id) {
                DB::table('jobs')->where('id', $log->queue_job_id)->delete();
            }
        });

        EmailConfigurationAudit::log(
            'email_cancelled',
            'EmailLog',
            $log->id,
            $log->recipient_email,
            ['queue_job_id' => $log->queue_job_id]
        );

        return [
            'success' => true,
            'message' => "Queued email to {$log->recipient_email} has been cancelled.",
        ];
    }
}
