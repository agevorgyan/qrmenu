<?php

namespace App\Domains\Loyalty\Listeners;

use App\Domains\Loyalty\Services\LoyaltyEngine;
use App\Events\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessOrderRefundLoyaltyListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected LoyaltyEngine $loyaltyEngine
    ) {}

    public function handle(OrderStatusUpdated $event): void
    {
        $order = $event->order;

        if ($order->status === 'cancelled' || in_array($order->payment_status, ['refunded', 'partially_refunded'], true)) {
            try {
                $refundRatio = 1.0;
                if ($order->payment_status === 'partially_refunded' && $order->paid_amount && (float) $order->total_amount > 0) {
                    $refundRatio = (float) $order->paid_amount / (float) $order->total_amount;
                }

                $this->loyaltyEngine->reverseOrderLoyalty(
                    $order,
                    $refundRatio,
                    "Order #{$order->order_number} marked as {$order->status} / {$order->payment_status}"
                );
            } catch (\Throwable $e) {
                Log::error("Failed to reverse loyalty for order #{$order->id}: ".$e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }
}
