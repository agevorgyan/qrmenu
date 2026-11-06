<?php

namespace App\Domains\Loyalty\Listeners;

use App\Domains\Loyalty\Services\LoyaltyEngine;
use App\Events\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessOrderLoyaltyListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected LoyaltyEngine $loyaltyEngine
    ) {}

    public function handle(OrderStatusUpdated $event): void
    {
        $order = $event->order;

        // Only process when order is completed or paid, and not cancelled/refunded
        if (in_array($order->status, ['completed', 'served'], true) || $order->payment_status === 'paid') {
            if ($order->status !== 'cancelled' && $order->payment_status !== 'refunded') {
                try {
                    $this->loyaltyEngine->processOrder($order);
                } catch (\Throwable $e) {
                    Log::error("Failed to process loyalty for order #{$order->id}: ".$e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            }
        }
    }
}
