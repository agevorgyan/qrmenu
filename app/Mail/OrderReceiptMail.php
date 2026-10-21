<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Order $order,
        ?string $locale = null
    ) {
        $targetLocale = $locale ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy'));
        $this->locale($targetLocale);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $targetLocale = $this->locale ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy'));
        $siteName = SystemSetting::getSiteName();

        $orderTypeLabel = $this->order->table_number
            ? __('Table: :number', ['number' => $this->order->table_number], $targetLocale)
            : ($this->order->type === 'delivery' ? __('Delivery', [], $targetLocale) : __('Takeaway', [], $targetLocale));

        $subject = null;
        try {
            $tpl = app(EmailTemplateService::class)->render('order_receipt', [
                'customer_name' => $this->order->customer_name ?? __('Customer', [], $targetLocale),
                'vendor_name' => $this->order->vendor?->name ?? 'QRMenu',
                'order_number' => $this->order->order_number,
                'order_type' => $orderTypeLabel,
                'total_amount' => number_format((float) $this->order->total_amount).' '.($this->order->vendor?->currency ?? 'AMD'),
                'support_email' => 'support@menu.elab.am',
                'site_name' => $siteName,
            ], $targetLocale);

            if (! empty($tpl['subject'])) {
                $subject = $tpl['subject'];
            }
        } catch (\Throwable $e) {
            // Fall back to default
        }

        if (empty($subject)) {
            if ($targetLocale === 'bilingual') {
                $subject = "Պատվերի կտրոն #{$this->order->order_number} • Order Receipt #{$this->order->order_number}";
            } elseif ($targetLocale === 'en') {
                $subject = "Order Receipt #{$this->order->order_number} - ".($this->order->vendor?->name ?? 'QRMenu');
            } else {
                $subject = "Պատվերի կտրոն #{$this->order->order_number} - ".($this->order->vendor?->name ?? 'QRMenu');
            }
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
        $targetLocale = $this->locale ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy'));

        return new Content(
            view: 'emails.receipt',
            with: [
                'order' => $this->order,
                'locale' => $targetLocale,
            ],
        );
    }
}
