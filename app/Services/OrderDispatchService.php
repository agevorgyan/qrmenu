<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Mail\OrderReceiptMail;
use App\Models\Location;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderDispatchService
{
    /**
     * Broadcast real-time order events and queue receipt email.
     */
    public function dispatchNotifications(Order $order, bool $isAppended = false): void
    {
        try {
            event(new OrderCreated($order));
        } catch (\Throwable $e) {
            Log::warning('OrderCreated broadcast failed: '.$e->getMessage());
        }

        if ($isAppended) {
            try {
                event(new OrderStatusUpdated($order));
            } catch (\Throwable $e) {
                Log::warning('OrderStatusUpdated broadcast failed: '.$e->getMessage());
            }
        }

        if (! empty($order->customer_email)) {
            try {
                $targetLocale = session('app_locale') ?: (app()->getLocale() ?: 'hy');
                Mail::to($order->customer_email)->queue(
                    new OrderReceiptMail($order, $targetLocale)
                );
            } catch (\Throwable $e) {
                // Silently ignore mail failure in queue dispatch
            }
        }
    }

    /**
     * Build WhatsApp dispatch URL if branch has configured phone number.
     */
    public function buildWhatsAppUrl(Order $order, Vendor $vendor, int $locationId, bool $isAppended = false): ?string
    {
        $location = ($order->relationLoaded('location') && $order->location && (int) $order->location->id === (int) $locationId)
            ? $order->location
            : (($vendor->relationLoaded('locations') ? $vendor->locations->firstWhere('id', (int) $locationId) : null) ?? Location::find($locationId));
        if (! $location || empty($location->whatsapp_number)) {
            return null;
        }

        $headerTitle = $isAppended
            ? "🧾 *Հավելյալ Պատվեր / Order Update #{$order->order_number}*"
            : "🧾 *New Order #{$order->order_number}*";

        $msg = "{$headerTitle}\n";
        if ($order->type === 'delivery') {
            $msg .= "🛵 *Տեսակը՝ ԱՌԱՔՈՒՄ (Delivery)*\n";
            $msg .= "📍 *Առաքման հասցե՝* {$order->delivery_address}\n";
        } elseif ($order->type === 'takeaway') {
            $msg .= "🛍️ *Տեսակը՝ ՏԵՂՈՒՄ ՎԵՐՑՆԵԼ (Takeaway)*\n";
            $msg .= "📍 *Մասնաճյուղ՝ {$location->name}*\n";
        } else {
            $msg .= "🍽️ *Տեսակը՝ ՌԵՍՏՈՐԱՆՈՒՄ (Dine-in)*\n";
            $msg .= "📍 *{$location->name}* ".($order->table_number ? "({$order->table_number})" : '')."\n";
        }
        $msg .= "👤 Name: {$order->customer_name}\n";
        if (! empty($order->customer_phone)) {
            $msg .= "📞 Phone: {$order->customer_phone}\n";
        }
        $msg .= "--------------------\n";

        foreach ($order->items as $i) {
            $msg .= "• {$i->quantity}x {$i->product_name} ({$i->variation_name}) - ".number_format($i->subtotal)." {$vendor->currency}\n";
        }

        if (! empty($order->notes)) {
            $msg .= "\n📝 *Notes:* {$order->notes}\n";
        }

        $msg .= "--------------------\n";
        $msg .= '🛒 *Ենթագումար (Subtotal): '.number_format($order->subtotal)." {$vendor->currency}*\n";

        if ($order->service_fee > 0) {
            $feeLabel = $vendor->service_fee_type === 'percent' ? " ({$vendor->service_fee_value}%)" : '';
            $msg .= "🛎️ *Սպասարկման վճար{$feeLabel}: ".number_format($order->service_fee)." {$vendor->currency}*\n";
        }

        if ($order->type === 'delivery') {
            if ($order->delivery_fee > 0) {
                $msg .= '🛵 *Առաքման վճար: '.number_format($order->delivery_fee)." {$vendor->currency}*\n";
            } else {
                $msg .= "🛵 *Առաքում: ԱՆՎՃԱՐ (Free)*\n";
            }
        }

        $msg .= '💰 *Ընդամենը (Total): '.number_format($order->total_amount)." {$vendor->currency}*";

        $cleanPhone = preg_replace('/[^0-9]/', '', $location->whatsapp_number);

        return "https://wa.me/{$cleanPhone}?text=".urlencode($msg);
    }
}
