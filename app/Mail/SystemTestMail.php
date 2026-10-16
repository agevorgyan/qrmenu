<?php

namespace App\Mail;

use App\Models\EmailConnection;
use App\Models\EmailSenderIdentity;
use App\Models\SystemSetting;
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
        public ?string $notes = null
    ) {}

    public function envelope(): Envelope
    {
        $siteName = SystemSetting::getSiteName();
        $timestamp = now()->toDateTimeString();

        return new Envelope(
            subject: "Թեստային Էլ․ Նամակ — {$this->emailConnection->name} ({$timestamp})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.system_test',
            with: [
                'connection' => $this->emailConnection,
                'emailConnection' => $this->emailConnection,
                'recipientEmail' => $this->recipientEmail,
                'sender' => $this->sender,
                'notes' => $this->notes,
            ]
        );
    }
}
