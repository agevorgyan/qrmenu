<?php

namespace App\Mail;

use App\Models\EmailConnection;
use App\Models\EmailSenderIdentity;
use App\Models\SystemSetting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $category = 'system_test';

    public function __construct(
        public string $recipientEmail,
        public EmailConnection $emailConnection,
        public ?EmailSenderIdentity $sender = null,
        public ?string $notes = null,
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: (app()->getLocale() ?: 'hy');
        $this->locale($targetLocale);
    }

    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: (app()->getLocale() ?: 'hy');
        $siteName = SystemSetting::getSiteName();
        $timestamp = now()->toDateTimeString();

        $subject = null;
        if (app()->bound(EmailTemplateService::class)) {
            $tpl = app(EmailTemplateService::class)->render('system_test', [
                'recipient_email' => $this->recipientEmail,
                'timestamp' => $timestamp,
                'connection_name' => $this->emailConnection->name,
                'site_name' => $siteName,
            ], $targetLocale);

            if (! empty($tpl['subject'])) {
                $subject = $tpl['subject'];
            }
        }

        if (empty($subject)) {
            $subject = __('Test Email — :connection (:time)', [
                'connection' => $this->emailConnection->name,
                'time' => $timestamp,
            ], $targetLocale);
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $targetLocale = $this->locale ?: (app()->getLocale() ?: 'hy');

        return new Content(
            view: 'emails.system_test',
            with: [
                'connection' => $this->emailConnection,
                'emailConnection' => $this->emailConnection,
                'recipientEmail' => $this->recipientEmail,
                'sender' => $this->sender,
                'notes' => $this->notes,
                'locale' => $targetLocale,
            ]
        );
    }
}
