<?php

namespace App\Services;

use App\Mail\EventInquiryCustomerAcknowledgmentMail;
use App\Mail\EventInquirySubmittedVendorMail;
use App\Models\EventInquiry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EventInquiryNotificationService
{
    /**
     * Dispatch notifications for a new event planning inquiry.
     */
    public function notifyNewInquiry(EventInquiry $inquiry): void
    {
        $vendor = $inquiry->vendor;
        if (! $vendor) {
            return;
        }

        $settings = $vendor->event_inquiry_settings;

        // 1. Dispatch Vendor Notifications
        if (! empty($settings['email_notifications_enabled'])) {
            $recipients = $this->resolveVendorRecipients($settings, $vendor->email);

            foreach ($recipients as $recipient) {
                try {
                    $mailable = new EventInquirySubmittedVendorMail($inquiry, $vendor->admin_locale ?: 'hy');
                    Mail::to($recipient)->queue($mailable);
                } catch (\Throwable $e) {
                    Log::warning('Failed to dispatch vendor event inquiry notification email', [
                        'inquiry_id' => $inquiry->id,
                        'recipient' => $recipient,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 2. Dispatch Customer Acknowledgment
        if (! empty($settings['customer_acknowledgment_enabled']) && filled($inquiry->customer_email)) {
            $customerEmail = trim(strtolower((string) $inquiry->customer_email));

            if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $mailable = new EventInquiryCustomerAcknowledgmentMail($inquiry, $inquiry->locale ?: 'hy');
                    Mail::to($customerEmail)->queue($mailable);
                } catch (\Throwable $e) {
                    Log::warning('Failed to dispatch customer event inquiry acknowledgment email', [
                        'inquiry_id' => $inquiry->id,
                        'customer_email' => $customerEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Resolve unique valid recipient email addresses for vendor notifications.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    protected function resolveVendorRecipients(array $settings, ?string $fallbackEmail): array
    {
        $raw = $settings['notification_recipients'] ?? [];
        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }

        $valid = [];
        if (is_array($raw)) {
            foreach ($raw as $email) {
                $trimmed = strtolower(trim((string) $email));
                if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    $valid[] = $trimmed;
                }
            }
        }

        if (empty($valid) && filled($fallbackEmail) && filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
            $valid[] = strtolower(trim($fallbackEmail));
        }

        return array_values(array_unique($valid));
    }
}
