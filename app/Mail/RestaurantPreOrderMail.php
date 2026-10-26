<?php

namespace App\Mail;

use App\Models\EmailRoutingRule;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\SystemSetting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RestaurantPreOrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $category = EmailRoutingRule::CATEGORY_PREORDERS;

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
        public ReservationPreOrder $preOrder,
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
            'updated', 'modified', 'cancelled' => 'restaurant_preorder_updated',
            default => 'restaurant_preorder_received',
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
                'table_name' => (string) ($this->reservation->table_number ?: __('Pending table assignment', [], $targetLocale)),
                'reservation_status' => ucfirst($this->reservation->status),
                'preorder_summary' => $this->preOrder->getFormattedSummary($targetLocale),
                'reservation_total' => number_format((float) $this->preOrder->total_amount).' '.($this->preOrder->currency ?: '֏'),
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
                'updated', 'modified', 'cancelled' => __('[Kitchen Update] Pre-order modified for reservation #:reference', ['reference' => $this->reservation->reference], $targetLocale),
                default => __('[New Pre-Order] Dishes pre-ordered for reservation #:reference (:customer)', ['reference' => $this->reservation->reference, 'customer' => $this->reservation->customer_name], $targetLocale),
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
            'updated', 'modified', 'cancelled' => 'restaurant_preorder_updated',
            default => 'restaurant_preorder_received',
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
                'table_name' => (string) ($this->reservation->table_number ?: __('Pending table assignment', [], $targetLocale)),
                'reservation_status' => ucfirst($this->reservation->status),
                'preorder_summary' => $this->preOrder->getFormattedSummary($targetLocale),
                'reservation_total' => number_format((float) $this->preOrder->total_amount).' '.($this->preOrder->currency ?: '֏'),
                'restaurant_address' => $this->reservation->location?->address ?? ($this->reservation->vendor?->operating_address ?? ''),
                'restaurant_phone' => $this->reservation->location?->phone ?? ($this->reservation->vendor?->phone ?? ''),
                'reservation_details_url' => url('/admin/floor-plan'),
                'cancellation_policy' => $this->reservation->cancellation_policy ?: '',
                'site_name' => SystemSetting::getSiteName(),
                'support_email' => 'support@menu.elab.am',
            ], $targetLocale);
        }

        return new Content(
            view: 'emails.preorder',
            with: [
                'reservation' => $this->reservation,
                'preOrder' => $this->preOrder,
                'event' => $this->event,
                'locale' => $targetLocale,
                'tpl' => $tpl,
                'isCustomer' => false,
            ],
        );
    }
}
