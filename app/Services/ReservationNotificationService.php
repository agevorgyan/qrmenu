<?php

namespace App\Services;

use App\Mail\CustomerPreOrderMail;
use App\Mail\CustomerReservationMail;
use App\Mail\RestaurantPreOrderMail;
use App\Mail\RestaurantReservationMail;
use App\Models\EmailLog;
use App\Models\EmailRoutingRule;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReservationNotificationService
{
    /**
     * Dispatch customer reservation notification for a given event.
     *
     * @param  array<string, mixed>  $options
     */
    public function notifyCustomerReservation(Reservation $reservation, string $event, array $options = []): ?EmailLog
    {
        $vendor = $reservation->vendor;
        if (! $vendor) {
            return null;
        }

        // Check Super Admin restrictions & Partner preferences
        if (! $vendor->isReservationNotificationAllowed($event, 'customer')) {
            return null;
        }

        $customerEmail = trim(strtolower((string) $reservation->customer_email));

        // Validate recipient address
        if (empty($customerEmail) || ! filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->logInvalidRecipient(
                $reservation,
                $customerEmail,
                $event,
                EmailRoutingRule::CATEGORY_RESERVATIONS,
                'Missing or invalid customer email address.'
            );
        }

        // Idempotency check: prevent duplicate send within 2 minutes for identical reservation & event
        if (! empty($options['check_duplicate'])) {
            $recentDuplicate = EmailLog::where('related_type', Reservation::class)
                ->where('related_id', $reservation->id)
                ->where('recipient_email', $customerEmail)
                ->where('metadata->event', $event)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($recentDuplicate) {
                return null;
            }
        }

        try {
            // Customer email strictly in their selected reservation locale
            $locale = $reservation->locale ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy'));
            $mailable = new CustomerReservationMail($reservation, $event, $locale);

            Mail::to($customerEmail)->queue($mailable);

            $log = EmailLog::where('related_type', Reservation::class)
                ->where('related_id', $reservation->id)
                ->where('recipient_email', $customerEmail)
                ->latest()
                ->first();

            return $log ?: new EmailLog([
                'status' => EmailLog::STATUS_QUEUED,
                'recipient_email' => $customerEmail,
                'related_type' => Reservation::class,
                'related_id' => $reservation->id,
                'vendor_id' => $reservation->vendor_id,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch customer reservation email ({$event}) for #{$reservation->reference}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Dispatch customer pre-order notification.
     *
     * @param  array<string, mixed>  $options
     */
    public function notifyCustomerPreOrder(Reservation $reservation, ReservationPreOrder $preOrder, string $event, array $options = []): ?EmailLog
    {
        $vendor = $reservation->vendor;
        if (! $vendor) {
            return null;
        }

        $fullEvent = "preorder_{$event}";
        if (! $vendor->isReservationNotificationAllowed($fullEvent, 'customer')) {
            return null;
        }

        $customerEmail = trim(strtolower((string) $reservation->customer_email));

        if (empty($customerEmail) || ! filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->logInvalidRecipient(
                $reservation,
                $customerEmail,
                $fullEvent,
                EmailRoutingRule::CATEGORY_PREORDERS,
                'Missing or invalid customer email address for pre-order notification.'
            );
        }

        try {
            $locale = $reservation->locale ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy'));
            $mailable = new CustomerPreOrderMail($reservation, $preOrder, $event, $locale);

            Mail::to($customerEmail)->queue($mailable);

            $log = EmailLog::where('related_type', Reservation::class)
                ->where('related_id', $reservation->id)
                ->where('recipient_email', $customerEmail)
                ->latest()
                ->first();

            return $log ?: new EmailLog([
                'status' => EmailLog::STATUS_QUEUED,
                'recipient_email' => $customerEmail,
                'related_type' => Reservation::class,
                'related_id' => $reservation->id,
                'vendor_id' => $reservation->vendor_id,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch customer pre-order email ({$event}) for #{$reservation->reference}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Dispatch restaurant notification to configured recipient addresses.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, EmailLog|null>
     */
    public function notifyRestaurantReservation(Reservation $reservation, string $event, array $options = []): array
    {
        $vendor = $reservation->vendor;
        if (! $vendor) {
            return [];
        }

        if (! $vendor->isReservationNotificationAllowed($event, 'restaurant')) {
            return [];
        }

        $recipients = $vendor->getNotificationRecipients();
        if (empty($recipients)) {
            return [];
        }

        // Restaurant operational emails use restaurant's administration language
        $adminLocale = $vendor->admin_locale ?? 'hy';
        $logs = [];

        foreach ($recipients as $recipientEmail) {
            try {
                $mailable = new RestaurantReservationMail($reservation, $event, $adminLocale);
                Mail::to($recipientEmail)->queue($mailable);

                $log = EmailLog::where('related_type', Reservation::class)
                    ->where('related_id', $reservation->id)
                    ->where('recipient_email', $recipientEmail)
                    ->latest()
                    ->first();

                $logs[] = $log ?: new EmailLog([
                    'status' => EmailLog::STATUS_QUEUED,
                    'recipient_email' => $recipientEmail,
                    'related_type' => Reservation::class,
                    'related_id' => $reservation->id,
                    'vendor_id' => $reservation->vendor_id,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed to dispatch restaurant reservation notification to {$recipientEmail}: ".$e->getMessage());
            }
        }

        return $logs;
    }

    /**
     * Dispatch restaurant notification for pre-orders.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, EmailLog|null>
     */
    public function notifyRestaurantPreOrder(Reservation $reservation, ReservationPreOrder $preOrder, string $event, array $options = []): array
    {
        $vendor = $reservation->vendor;
        if (! $vendor) {
            return [];
        }

        $fullEvent = "preorder_{$event}";
        if (! $vendor->isReservationNotificationAllowed($fullEvent, 'restaurant')) {
            return [];
        }

        $recipients = $vendor->getNotificationRecipients();
        if (empty($recipients)) {
            return [];
        }

        $adminLocale = $vendor->admin_locale ?? 'hy';
        $logs = [];

        foreach ($recipients as $recipientEmail) {
            try {
                $mailable = new RestaurantPreOrderMail($reservation, $preOrder, $event, $adminLocale);
                Mail::to($recipientEmail)->queue($mailable);

                $log = EmailLog::where('related_type', Reservation::class)
                    ->where('related_id', $reservation->id)
                    ->where('recipient_email', $recipientEmail)
                    ->latest()
                    ->first();

                $logs[] = $log ?: new EmailLog([
                    'status' => EmailLog::STATUS_QUEUED,
                    'recipient_email' => $recipientEmail,
                    'related_type' => Reservation::class,
                    'related_id' => $reservation->id,
                    'vendor_id' => $reservation->vendor_id,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed to dispatch restaurant pre-order notification to {$recipientEmail}: ".$e->getMessage());
            }
        }

        return $logs;
    }

    /**
     * Trigger reminder for an upcoming reservation.
     */
    public function dispatchReminder(Reservation $reservation, int $intervalMinutes, string $type = 'customer'): bool
    {
        $vendor = $reservation->vendor;
        if (! $vendor) {
            return false;
        }

        // Check if reminders are permitted
        if (! $vendor->isReminderAllowed($type)) {
            return false;
        }

        // Check confirmed-only rule
        $settings = $vendor->reservation_notification_settings;
        $onlyConfirmed = $type === 'customer'
            ? ($settings['customer_reminder_only_confirmed'] ?? true)
            : ($settings['restaurant_reminder_only_confirmed'] ?? true);

        if ($onlyConfirmed && $reservation->status !== Reservation::STATUS_CONFIRMED) {
            return false;
        }

        // Check if reservation is cancelled, rejected, completed, or already passed
        if (in_array($reservation->status, [Reservation::STATUS_CANCELLED, Reservation::STATUS_REJECTED, Reservation::STATUS_COMPLETED], true)) {
            return false;
        }

        if ($reservation->isPassed()) {
            return false;
        }

        // Check if already dispatched to prevent duplicate reminders across worker runs
        if ($reservation->hasReminderBeenSent($intervalMinutes, $type)) {
            return false;
        }

        // Mark sent atomically
        $reservation->markReminderSent($intervalMinutes, $type);

        if ($type === 'customer') {
            $this->notifyCustomerReservation($reservation, 'reminder');
        } else {
            $this->notifyRestaurantReservation($reservation, 'reminder');
        }

        return true;
    }

    /**
     * Convenience method to dispatch customer notification for any event.
     *
     * @param  array<string, mixed>  $options
     */
    public function sendCustomerNotification(Reservation $reservation, string $event, array $options = []): bool
    {
        if (str_starts_with($event, 'preorder_') && isset($options['preorder'])) {
            $preOrderEvent = substr($event, strlen('preorder_'));
            $log = $this->notifyCustomerPreOrder($reservation, $options['preorder'], $preOrderEvent, $options);

            return $log && $log->status !== EmailLog::STATUS_FAILED;
        }

        $log = $this->notifyCustomerReservation($reservation, $event, $options);

        return $log && $log->status !== EmailLog::STATUS_FAILED;
    }

    /**
     * Convenience method to dispatch restaurant staff notification for any event.
     *
     * @param  array<string, mixed>  $options
     */
    public function sendRestaurantNotification(Reservation $reservation, string $event, array $options = []): bool
    {
        if (str_starts_with($event, 'restaurant_preorder_') && isset($options['preorder'])) {
            $preOrderEvent = substr($event, strlen('restaurant_preorder_'));
            $logs = $this->notifyRestaurantPreOrder($reservation, $options['preorder'], $preOrderEvent, $options);
            $successful = array_filter($logs, fn ($l) => $l && $l->status !== EmailLog::STATUS_FAILED);

            return ! empty($successful);
        }

        $logs = $this->notifyRestaurantReservation($reservation, $event, $options);
        $successful = array_filter($logs, fn ($l) => $l && $l->status !== EmailLog::STATUS_FAILED);

        return ! empty($successful);
    }

    /**
     * Record failure in email_logs when a recipient email is invalid or missing without throwing.
     */
    protected function logInvalidRecipient(
        Reservation $reservation,
        string $email,
        string $event,
        string $category,
        string $reason
    ): EmailLog {
        return EmailLog::create([
            'uuid' => (string) Str::uuid(),
            'recipient_email' => $email ?: 'missing-email@invalid.local',
            'recipient_name' => $reservation->customer_name,
            'subject' => "Failed Notification: {$event}",
            'category' => $category,
            'mailable_class' => CustomerReservationMail::class,
            'from_email' => config('mail.from.address', 'no-reply@menu.elab.am'),
            'from_name' => config('mail.from.name', 'QRMenu'),
            'status' => EmailLog::STATUS_FAILED,
            'attempts' => 1,
            'related_type' => Reservation::class,
            'related_id' => $reservation->id,
            'vendor_id' => $reservation->vendor_id,
            'error_code' => 'INVALID_RECIPIENT',
            'error_message' => $reason,
            'metadata' => [
                'event' => $event,
                'locale' => $reservation->locale,
                'validation_error' => true,
            ],
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Convenience aliases for dispatching notifications.
     */
    public function sendReservationNotification(Reservation $reservation, string $event, array $options = []): ?EmailLog
    {
        return $this->notifyCustomerReservation($reservation, $event, $options);
    }

    public function sendRestaurantReservationNotification(Reservation $reservation, string $event, array $options = []): array
    {
        return $this->notifyRestaurantReservation($reservation, $event, $options);
    }

    public function sendCustomerPreOrderNotification(ReservationPreOrder $preOrder, string $event, array $options = []): ?EmailLog
    {
        $reservation = $preOrder->reservation ?: Reservation::find($preOrder->reservation_id);
        if (! $reservation) {
            return null;
        }

        return $this->notifyCustomerPreOrder($reservation, $preOrder, $event, $options);
    }

    public function sendRestaurantPreOrderNotification(ReservationPreOrder $preOrder, string $event, array $options = []): array
    {
        $reservation = $preOrder->reservation ?: Reservation::find($preOrder->reservation_id);
        if (! $reservation) {
            return [];
        }

        return $this->notifyRestaurantPreOrder($reservation, $preOrder, $event, $options);
    }
}
