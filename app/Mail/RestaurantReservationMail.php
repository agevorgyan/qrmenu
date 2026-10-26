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

class RestaurantReservationMail extends Mailable implements ShouldQueue
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
        public string $event = 'received',
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: (session('admin_locale') ?: ($reservation->vendor?->admin_locale ?? 'hy'));
        $this->locale($targetLocale);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: ($this->reservation->vendor?->admin_locale ?? 'hy');
        $siteName = SystemSetting::getSiteName();
        $vendorName = $this->reservation->vendor?->name ?? $siteName;

        $templateKey = match ($this->event) {
            'awaiting_confirmation' => 'restaurant_reservation_awaiting_confirmation',
            'action', 'cancelled_by_customer', 'confirmed_by_customer' => 'restaurant_reservation_action',
            'modified' => 'restaurant_reservation_modified',
            'reminder' => 'restaurant_reservation_reminder',
            default => 'restaurant_reservation_received',
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
                'reservation_total' => $this->reservation->preOrder ? number_format((float) $this->reservation->preOrder->total_amount).' '.($this->reservation->vendor?->currency ?? '֏') : '0 '.($this->reservation->vendor?->currency ?? '֏'),
                'restaurant_address' => $this->reservation->location?->address ?? ($this->reservation->vendor?->operating_address ?? ''),
                'restaurant_phone' => $this->reservation->location?->phone ?? ($this->reservation->vendor?->phone ?? ''),
                'reservation_details_url' => url('/admin/floor-plan'),
                'cancellation_policy' => $this->reservation->cancellation_policy ?: '',
                'site_name' => $siteName,
                'support_email' => 'support@menu.elab.am',
            ], $targetLocale);

            if (! empty($tpl['subject'])) {
                $subject = $tpl['subject'];
            }
        }

        if (empty($subject)) {
            $subject = match ($this->event) {
                'awaiting_confirmation' => __('[Pending Action] Reservation #:reference awaiting confirmation - :customer', ['reference' => $this->reservation->reference, 'customer' => $this->reservation->customer_name], $targetLocale),
                'action', 'cancelled_by_customer' => __('[Reservation Update] Customer action on reservation #:reference', ['reference' => $this->reservation->reference], $targetLocale),
                'modified' => __('[Reservation Modified] Date/time changed for reservation #:reference', ['reference' => $this->reservation->reference], $targetLocale),
                'reminder' => __('[Staff Reminder] Upcoming reservation today #:reference (:time)', ['reference' => $this->reservation->reference, 'time' => $this->reservation->getFormattedTime()], $targetLocale),
                default => __('[New Reservation] #:reference - :customer (:guests guests, :time)', [
                    'reference' => $this->reservation->reference,
                    'customer' => $this->reservation->customer_name,
                    'guests' => $this->reservation->guest_count,
                    'time' => $this->reservation->getFormattedTime(),
                ], $targetLocale),
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
        $targetLocale = $this->locale ?: ($this->reservation->vendor?->admin_locale ?? 'hy');

        $templateKey = match ($this->event) {
            'awaiting_confirmation' => 'restaurant_reservation_awaiting_confirmation',
            'action', 'cancelled_by_customer', 'confirmed_by_customer' => 'restaurant_reservation_action',
            'modified' => 'restaurant_reservation_modified',
            'reminder' => 'restaurant_reservation_reminder',
            default => 'restaurant_reservation_received',
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
                'reservation_total' => $this->reservation->preOrder ? number_format((float) $this->reservation->preOrder->total_amount).' '.($this->reservation->vendor?->currency ?? '֏') : '0 '.($this->reservation->vendor?->currency ?? '֏'),
                'restaurant_address' => $this->reservation->location?->address ?? ($this->reservation->vendor?->operating_address ?? ''),
                'restaurant_phone' => $this->reservation->location?->phone ?? ($this->reservation->vendor?->phone ?? ''),
                'reservation_details_url' => url('/admin/floor-plan'),
                'cancellation_policy' => $this->reservation->cancellation_policy ?: '',
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
                'isCustomer' => false,
            ],
        );
    }
}
