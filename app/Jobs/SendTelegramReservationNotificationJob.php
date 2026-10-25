<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\TelegramNotificationService;
use App\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendTelegramReservationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public Reservation $reservation,
        public string $event
    ) {}

    public function handle(TelegramNotificationService $telegramService, TenantContext $tenantContext): void
    {
        $tenantContext->runInTenantContext($this->reservation->vendor_id, function () use ($telegramService) {
            $lockKey = "tg_res_notify_{$this->reservation->id}_{$this->event}";
            if (! Cache::add($lockKey, true, 120)) {
                return;
            }

            try {
                $telegramService->sendReservationNotification($this->reservation, $this->event);
            } catch (\Throwable $e) {
                Log::warning('SendTelegramReservationNotificationJob failed: '.$e->getMessage(), [
                    'reservation_id' => $this->reservation->id,
                    'reference' => $this->reservation->reference,
                    'vendor_id' => $this->reservation->vendor_id,
                    'event' => $this->event,
                ]);

                throw $e;
            }
        });
    }
}
