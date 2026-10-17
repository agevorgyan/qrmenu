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

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public string $action = 'login',
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: ($user->locale ?: (app()->getLocale() ?: 'hy'));
        $this->locale($targetLocale);
    }

    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: ($this->user->locale ?: (app()->getLocale() ?: 'hy'));
        $siteName = SystemSetting::getSiteName();

        $actionDesc = match ($this->action) {
            'password' => __('password change', [], $targetLocale),
            'email' => __('email address change', [], $targetLocale),
            'setup' => __('two-factor authentication setup', [], $targetLocale),
            default => __('account login', [], $targetLocale),
        };

        $subject = null;
        if (app()->bound(EmailTemplateService::class)) {
            $tpl = app(EmailTemplateService::class)->render('two_factor_code', [
                'user_name' => $this->user->name,
                'code' => $this->code,
                'action_description' => $actionDesc,
                'expire_minutes' => '10',
                'support_email' => 'support@menu.elab.am',
                'site_name' => $siteName,
            ], $targetLocale);

            if (! empty($tpl['subject'])) {
                $subject = $tpl['subject'];
            }
        }

        if (empty($subject)) {
            $subject = match ($this->action) {
                'password' => __('Password Change 2FA Code — :site_name', ['site_name' => $siteName], $targetLocale),
                'email' => __('Email Change 2FA Code — :site_name', ['site_name' => $siteName], $targetLocale),
                'setup' => __('2FA Activation Verification Code — :site_name', ['site_name' => $siteName], $targetLocale),
                default => __('Your Two-Factor Authentication (2FA) Code — :site_name', ['site_name' => $siteName], $targetLocale),
            };
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $targetLocale = $this->locale ?: ($this->user->locale ?: (app()->getLocale() ?: 'hy'));

        return new Content(
            view: 'emails.two_factor_code',
            with: [
                'user' => $this->user,
                'code' => $this->code,
                'action' => $this->action,
                'locale' => $targetLocale,
            ],
        );
    }
}
