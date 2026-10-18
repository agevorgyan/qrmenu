<?php

namespace App\Mail;

use App\Models\EmailRoutingRule;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerReservationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $category = EmailRoutingRule::CATEGORY_RESERVATIONS;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Reservation $reservation,
        public string $event = 'confirmed',
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: ($reservation->locale ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy')));
        $this->locale($targetLocale);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: ($this->reservation->locale ?: 'hy');
        $siteName = SystemSetting::getSiteName();
        $vendorName = $this->reservation->vendor?->name ?? $siteName;

        $templateKey = match ($this->event) {
            'request_received', 'received' => 'reservation_request_received',
            'rejected' => 'reservation_rejected',
            'modified' => 'reservation_modified',
            'cancelled' => 'reservation_cancelled',
            'reminder' => 'reservation_reminder',
            default => 'reservation_confirmed',
        };

        $subject = null;
        if (app()->bound(EmailTemplateService::class)) {
            $tpl = app(EmailTemplateService::class)->render($templateKey, [
                'customer_name' => $this->reservation->customer_name,
                'restaurant_name' => $vendorName,
                'reservation_reference' => $this->reservation->reference,
                'reservation_date' => $this->reservation->getFormattedDate($targetLocale),
                'reservation_time' => $this->reservation->getFormattedTime(),
                'guest_count' => (string) $this->reservation->guest_count,
                'table_name' => (string) ($this->reservation->table_name ?: ($this->reservation->table_number ?: __('Pending table assignment', [], $targetLocale))),
                'reservation_status' => ucfirst($this->reservation->status),
                'preorder_summary' => $this->reservation->preOrder ? $this->reservation->preOrder->getFormattedSummary($targetLocale) : __('None', [], $targetLocale),
                'reservation_total' => $this->reservation->preOrder ? number_format((float) $this->reservation->preOrder->total_amount).' '.($this->reservation->vendor?->currency ?? 'AMD') : '0 '.($this->reservation->vendor?->currency ?? 'AMD'),
                'restaurant_address' => $this->reservation->location?->address ?? ($this->reservation->vendor?->operating_address ?? ''),
                'restaurant_phone' => $this->reservation->location?->phone ?? ($this->reservation->vendor?->phone ?? ''),
                'reservation_details_url' => $this->reservation->getPublicDetailsUrl(),
                'cancellation_policy' => $this->reservation->cancellation_policy ?: __('Cancellations accepted up to 2 hours before arrival.', [], $targetLocale),
                'site_name' => $siteName,
                'support_email' => 'support@menu.elab.am',
            ], $targetLocale);

            if (! empty($tpl['subject'])) {
                $subject = $tpl['subject'];
            }
        }

        if (empty($subject)) {
            $subject = match ($this->event) {
                'request_received', 'received' => __(':restaurant: Reservation request received (:reference)', ['restaurant' => $vendorName, 'reference' => $this->reservation->reference], $targetLocale),
                'rejected' => __(':restaurant: Reservation request update (:reference)', ['restaurant' => $vendorName, 'reference' => $this->reservation->reference], $targetLocale),
                'modified' => __(':restaurant: Reservation time/details updated (:reference)', ['restaurant' => $vendorName, 'reference' => $this->reservation->reference], $targetLocale),
                'cancelled' => __(':restaurant: Reservation cancelled (:reference)', ['restaurant' => $vendorName, 'reference' => $this->reservation->reference], $targetLocale),
                'reminder' => __(':restaurant: Reminder of your upcoming reservation today (:reference)', ['restaurant' => $vendorName, 'reference' => $this->reservation->reference], $targetLocale),
                default => __(':restaurant: Table reservation confirmed (:reference)', ['restaurant' => $vendorName, 'reference' => $this->reservation->reference], $targetLocale),
            };
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $targetLocale = $this->locale ?: ($this->reservation->locale ?: 'hy');

        $templateKey = match ($this->event) {
            'request_received', 'received' => 'reservation_request_received',
            'rejected' => 'reservation_rejected',
            'modified' => 'reservation_modified',
            'cancelled' => 'reservation_cancelled',
            'reminder' => 'reservation_reminder',
            default => 'reservation_confirmed',
        };

        $tpl = null;
        if (app()->bound(EmailTemplateService::class)) {
            $tpl = app(EmailTemplateService::class)->render($templateKey, [
                'customer_name' => $this->reservation->customer_name,
                'restaurant_name' => $this->reservation->vendor?->name ?? 'QRMenu',
                'reservation_reference' => $this->reservation->reference,
                'reservation_date' => $this->reservation->getFormattedDate($targetLocale),
                'reservation_time' => $this->reservation->getFormattedTime(),
                'guest_count' => (string) $this->reservation->guest_count,
                'table_name' => (string) ($this->reservation->table_name ?: ($this->reservation->table_number ?: __('Pending table assignment', [], $targetLocale))),
                'reservation_status' => ucfirst($this->reservation->status),
                'preorder_summary' => $this->reservation->preOrder ? $this->reservation->preOrder->getFormattedSummary($targetLocale) : __('None', [], $targetLocale),
                'reservation_total' => $this->reservation->preOrder ? number_format((float) $this->reservation->preOrder->total_amount).' '.($this->reservation->vendor?->currency ?? 'AMD') : '0 '.($this->reservation->vendor?->currency ?? 'AMD'),
                'restaurant_address' => $this->reservation->location?->address ?? ($this->reservation->vendor?->operating_address ?? ''),
                'restaurant_phone' => $this->reservation->location?->phone ?? ($this->reservation->vendor?->phone ?? ''),
                'reservation_details_url' => $this->reservation->getPublicDetailsUrl(),
                'cancellation_policy' => $this->reservation->cancellation_policy ?: __('Cancellations accepted up to 2 hours before arrival.', [], $targetLocale),
                'site_name' => SystemSetting::getSiteName(),
                'support_email' => 'support@menu.elab.am',
            ], $targetLocale);
        }

        return new Content(
            view: 'emails.reservation',
            with: [
                'reservation' => $this->reservation,
                'event' => $this->event,
                'locale' => $targetLocale,
                'tpl' => $tpl,
                'isCustomer' => true,
            ],
        );
    }
}
