<?php

namespace App\Mail;

use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionDowngradedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
        public SubscriptionPlan $targetPlan,
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: ($vendor->owner?->locale ?: (app()->getLocale() ?: 'hy'));
        $this->locale($targetLocale);
    }

    public function envelope(): Envelope
    {
        $siteName = SystemSetting::getSiteName();
        $targetLocale = $this->locale ?: ($this->vendor->owner?->locale ?: (app()->getLocale() ?: 'hy'));

        return new Envelope(
            subject: __('Subscription Plan Downgraded — :site_name', ['site_name' => $siteName], $targetLocale),
        );
    }

    public function content(): Content
    {
        $targetLocale = $this->locale ?: ($this->vendor->owner?->locale ?: (app()->getLocale() ?: 'hy'));

        return new Content(
            view: 'emails.subscription_downgraded',
            with: [
                'vendor' => $this->vendor,
                'targetPlan' => $this->targetPlan,
                'locale' => $targetLocale,
            ],
        );
    }
}
