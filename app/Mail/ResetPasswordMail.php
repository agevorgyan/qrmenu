<?php

namespace App\Mail;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: ($user->locale ?: (app()->getLocale() ?: 'hy'));
        $this->locale($targetLocale);
    }

    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: ($this->user->locale ?: (app()->getLocale() ?: 'hy'));
        $siteName = SystemSetting::getSiteName();

        $subject = null;
        if (app()->bound(EmailTemplateService::class)) {
            $tpl = app(EmailTemplateService::class)->render('reset_password', [
                'user_name' => $this->user->name,
                'reset_url' => $this->resetUrl,
                'expire_minutes' => '60',
                'support_email' => 'support@menu.elab.am',
                'site_name' => $siteName,
            ], $targetLocale);

            if (! empty($tpl['subject'])) {
                $subject = $tpl['subject'];
            }
        }

        if (empty($subject)) {
            $subject = __('Password Reset Request — :site_name', ['site_name' => $siteName], $targetLocale);
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $targetLocale = $this->locale ?: ($this->user->locale ?: (app()->getLocale() ?: 'hy'));

        return new Content(
            view: 'emails.reset_password',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'locale' => $targetLocale,
            ],
        );
    }
}
