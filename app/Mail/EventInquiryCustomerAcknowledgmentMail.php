<?php

namespace App\Mail;

use App\Models\EmailRoutingRule;
use App\Models\EventInquiry;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventInquiryCustomerAcknowledgmentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $category = EmailRoutingRule::CATEGORY_RESERVATIONS;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public EventInquiry $inquiry,
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: ($inquiry->locale ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy')));
        $this->locale($targetLocale);
    }

    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: ($this->inquiry->locale ?: 'hy');
        $siteName = SystemSetting::getSiteName();
        $vendorName = $this->inquiry->vendor?->name ?? $siteName;

        $subject = __(':restaurant: Event Inquiry Received (:reference)', [
            'restaurant' => $vendorName,
            'reference' => $this->inquiry->reference,
        ], $targetLocale);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event_inquiry',
            with: [
                'inquiry' => $this->inquiry,
                'recipientType' => 'customer',
                'locale' => $this->locale,
                'subject' => $this->envelope()->subject,
            ],
        );
    }
}
