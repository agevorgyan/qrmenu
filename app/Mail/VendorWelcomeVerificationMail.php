<?php

namespace App\Mail;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorWelcomeVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $verificationUrl,
        public ?string $otpCode = null,
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: ($user->locale ?: (app()->getLocale() ?: 'hy'));
        $this->locale($targetLocale);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: ($this->user->locale ?: (app()->getLocale() ?: 'hy'));
        $siteName = SystemSetting::getSiteName();

        $subject = null;
        if (app()->bound(EmailTemplateService::class)) {
            $tpl = app(EmailTemplateService::class)->render('welcome_verification', [
                'user_name' => $this->user->name,
                'vendor_name' => $this->user->vendor?->name ?? 'QRMenu',
                'verification_url' => $this->verificationUrl,
                'support_email' => 'support@menu.elab.am',
                'site_name' => $siteName,
            ], $targetLocale);

            if (! empty($tpl['subject'])) {
                $subject = $tpl['subject'];
            }
        }

        if (empty($subject)) {
            $subject = __('Welcome to :site_name — Verify your email', ['site_name' => $siteName], $targetLocale);
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
        $targetLocale = $this->locale ?: ($this->user->locale ?: (app()->getLocale() ?: 'hy'));

        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
                'otpCode' => $this->otpCode,
                'locale' => $targetLocale,
            ],
        );
    }
}
