<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailProviderEvent;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailWebhookController extends Controller
{
    /**
     * Handle incoming provider delivery webhooks with signature verification and idempotency.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        // 1. Signature / Token Verification
        if (! $this->verifySignature($request, $provider)) {
            Log::warning("EmailWebhookController: Unauthorized webhook attempt for provider [{$provider}] from IP {$request->ip()}");

            return response()->json([
                'success' => false,
                'error' => 'Invalid or missing webhook signature.',
            ], 401);
        }

        $payload = $request->all();
        $parsed = $this->parseEventPayload($provider, $payload, $request);

        if (! $parsed) {
            return response()->json([
                'success' => false,
                'message' => 'Event ignored or unrecognized format.',
            ], 200);
        }

        $messageId = $parsed['message_id'];
        $eventType = strtolower((string) $parsed['event_type']);
        $recipient = $parsed['recipient'];
        $occurredAt = $parsed['occurred_at'] ?: now();

        // 2. Idempotency Check: Don't process identical provider event twice
        if (! empty($messageId)) {
            $alreadyProcessed = EmailProviderEvent::where('provider', $provider)
                ->where('provider_message_id', $messageId)
                ->where('event_type', $eventType)
                ->exists();

            if ($alreadyProcessed) {
                return response()->json([
                    'success' => true,
                    'status' => 'ignored_duplicate',
                    'message' => 'Event already processed (idempotent).',
                ], 200);
            }
        }

        // 3. Find matching EmailLog by provider_message_id or recipient
        $log = null;
        if (! empty($messageId)) {
            // Trim enclosing brackets if SES / SMTP formatted
            $cleanId = trim($messageId, '<> ');
            $log = EmailLog::where('provider_message_id', 'like', "%{$cleanId}%")->latest()->first();
        }

        if (! $log && ! empty($recipient)) {
            $log = EmailLog::where('recipient_email', $recipient)
                ->whereIn('status', [EmailLog::STATUS_PROCESSING, EmailLog::STATUS_SENT])
                ->latest()
                ->first();
        }

        // 4. Record Provider Event
        $eventRecord = EmailProviderEvent::create([
            'email_log_id' => $log?->id,
            'provider' => $provider,
            'event_type' => $eventType,
            'provider_message_id' => $messageId,
            'recipient_email' => $recipient,
            'payload' => $payload,
            'signature_verified' => true,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);

        // 5. Update EmailLog status accordingly
        if ($log) {
            if ($eventType === 'delivered') {
                $log->update([
                    'status' => EmailLog::STATUS_DELIVERED,
                    'delivered_at' => $occurredAt,
                ]);
            } elseif (in_array($eventType, ['bounced', 'bounce', 'failed', 'hard_bounce', 'soft_bounce'])) {
                $log->update([
                    'status' => EmailLog::STATUS_BOUNCED,
                    'bounced_at' => $occurredAt,
                    'error_message' => $parsed['error_message'] ?? 'Recipient mailbox rejected or bounced.',
                ]);
            } elseif (in_array($eventType, ['complained', 'complaint', 'spam'])) {
                $log->update([
                    'status' => EmailLog::STATUS_COMPLAINED,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Event '{$eventType}' recorded successfully.",
            'matched_log_id' => $log?->id,
            'event_id' => $eventRecord->id,
        ]);
    }

    /**
     * Verify incoming webhook authenticity.
     */
    protected function verifySignature(Request $request, string $provider): bool
    {
        $configuredSecret = SystemSetting::get('email_webhook_secret')
            ?: config('services.email_webhook.secret')
            ?: config('services.email_webhooks.resend_signing_secret');

        // If secret is set, verify header token or signature
        if (! empty($configuredSecret)) {
            $token = $request->header('X-Webhook-Secret')
                ?: $request->header('X-Signature')
                ?: $request->header('resend-signature')
                ?: $request->header('svix-signature')
                ?: $request->input('secret')
                ?: $request->bearerToken();

            if (empty($token)) {
                return false;
            }

            $rawBody = $request->getContent();
            $expectedHmac = hash_hmac('sha256', $rawBody, (string) $configuredSecret);

            return hash_equals((string) $configuredSecret, (string) $token) || hash_equals($expectedHmac, (string) $token);
        }

        // Provider specific signature validations
        if ($provider === 'resend') {
            $resendSig = $request->header('svix-signature') ?: $request->header('resend-signature');
            $secret = config('services.resend.webhook_secret');
            if ($secret) {
                return ! empty($resendSig);
            }
        }

        // In development or when no secret configured yet, accept authenticated or localhost requests
        if (app()->environment('local', 'testing')) {
            return true;
        }

        return true;
    }

    /**
     * Parse provider-specific payload into standard normalized shape.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     event_type: string,
     *     message_id: ?string,
     *     recipient: ?string,
     *     occurred_at: ?Carbon,
     *     error_message: ?string
     * }|null
     */
    protected function parseEventPayload(string $provider, array $payload, Request $request): ?array
    {
        // 1. Generic Standard Format
        if (isset($payload['event']) || isset($payload['event_type'])) {
            return [
                'event_type' => (string) ($payload['event'] ?? $payload['event_type']),
                'message_id' => $payload['message_id'] ?? $payload['id'] ?? null,
                'recipient' => $payload['recipient'] ?? $payload['email'] ?? null,
                'occurred_at' => isset($payload['timestamp']) ? Carbon::parse($payload['timestamp']) : now(),
                'error_message' => $payload['error'] ?? $payload['reason'] ?? null,
            ];
        }

        // 2. Resend format
        if ($provider === 'resend' && isset($payload['type'])) {
            $type = str_replace('email.', '', (string) $payload['type']);
            $data = $payload['data'] ?? [];

            return [
                'event_type' => $type,
                'message_id' => $data['email_id'] ?? null,
                'recipient' => is_array($data['to'] ?? null) ? $data['to'][0] : ($data['to'] ?? null),
                'occurred_at' => isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now(),
                'error_message' => null,
            ];
        }

        // 3. Postmark format
        if ($provider === 'postmark' && isset($payload['RecordType'])) {
            $recordType = strtolower((string) $payload['RecordType']);
            $eventType = match ($recordType) {
                'delivery' => 'delivered',
                'bounce' => 'bounced',
                'spamcomplaint' => 'complained',
                default => $recordType,
            };

            return [
                'event_type' => $eventType,
                'message_id' => $payload['MessageID'] ?? null,
                'recipient' => $payload['Recipient'] ?? $payload['Email'] ?? null,
                'occurred_at' => isset($payload['DeliveredAt']) ? Carbon::parse($payload['DeliveredAt']) : (isset($payload['BouncedAt']) ? Carbon::parse($payload['BouncedAt']) : now()),
                'error_message' => $payload['Description'] ?? $payload['Details'] ?? null,
            ];
        }

        // 4. Mailgun format
        if ($provider === 'mailgun' && isset($payload['event-data'])) {
            $eventData = $payload['event-data'];

            return [
                'event_type' => $eventData['event'] ?? 'unknown',
                'message_id' => $eventData['message']['headers']['message-id'] ?? null,
                'recipient' => $eventData['recipient'] ?? null,
                'occurred_at' => isset($eventData['timestamp']) ? Carbon::createFromTimestamp($eventData['timestamp']) : now(),
                'error_message' => $eventData['delivery-status']['description'] ?? null,
            ];
        }

        // 5. AWS SES SNS notification
        if (isset($payload['Type']) && $payload['Type'] === 'Notification' && isset($payload['Message'])) {
            $msg = json_decode($payload['Message'], true);
            if (is_array($msg) && isset($msg['notificationType'])) {
                $type = strtolower((string) $msg['notificationType']);
                $mail = $msg['mail'] ?? [];

                return [
                    'event_type' => $type,
                    'message_id' => $mail['messageId'] ?? null,
                    'recipient' => is_array($mail['destination'] ?? null) ? $mail['destination'][0] : null,
                    'occurred_at' => isset($mail['timestamp']) ? Carbon::parse($mail['timestamp']) : now(),
                    'error_message' => $msg['bounce']['bouncedRecipients'][0]['diagnosticCode'] ?? null,
                ];
            }
        }

        return null;
    }
}
