<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\ReservationNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReservationRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:send-reminders {--window=30 : Grace period in minutes for reminder window}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and dispatch scheduled customer and restaurant table reservation reminders';

    /**
     * Execute the console command.
     */
    public function handle(ReservationNotificationService $notificationService): int
    {
        $window = (int) $this->option('window');
        $nowUtc = Carbon::now('UTC');

        $this->info("Checking upcoming reservations for reminders at {$nowUtc->toDateTimeString()} UTC...");

        // Fetch upcoming active reservations that have not passed
        $reservations = Reservation::with(['vendor', 'location', 'preOrder'])
            ->where('reservation_datetime', '>', $nowUtc)
            ->whereNotIn('status', [
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_REJECTED,
                Reservation::STATUS_COMPLETED,
            ])
            ->get();

        $processedCount = 0;
        $customerRemindersSent = 0;
        $staffRemindersSent = 0;

        foreach ($reservations as $reservation) {
            $processedCount++;
            $vendor = $reservation->vendor;

            if (! $vendor) {
                continue;
            }

            $minutesRemaining = $reservation->minutesUntilArrival($nowUtc);

            // Skip if reservation is in the past
            if ($minutesRemaining <= 0) {
                continue;
            }

            $settings = $vendor->reservation_notification_settings;

            // 1. Process Customer Reminders
            if ($vendor->isReminderAllowed('customer')) {
                $intervals = $settings['customer_reminder_intervals'] ?? [1440, 180];
                foreach ($intervals as $interval) {
                    $interval = (int) $interval;
                    // Trigger if within interval window (from interval down to interval - window)
                    if ($minutesRemaining <= $interval && $minutesRemaining >= ($interval - $window)) {
                        if (! $reservation->hasReminderBeenSent($interval, 'customer')) {
                            $sent = $notificationService->dispatchReminder($reservation, $interval, 'customer');
                            if ($sent) {
                                $customerRemindersSent++;
                                $this->line("Dispatched customer reminder ({$interval}m) for reservation #{$reservation->reference}");
                            }
                        }
                    }
                }
            }

            // 2. Process Restaurant Staff Reminders
            if ($vendor->isReminderAllowed('restaurant')) {
                $intervals = $settings['restaurant_reminder_intervals'] ?? [180, 60];
                foreach ($intervals as $interval) {
                    $interval = (int) $interval;
                    if ($minutesRemaining <= $interval && $minutesRemaining >= ($interval - $window)) {
                        if (! $reservation->hasReminderBeenSent($interval, 'restaurant')) {
                            $sent = $notificationService->dispatchReminder($reservation, $interval, 'restaurant');
                            if ($sent) {
                                $staffRemindersSent++;
                                $this->line("Dispatched restaurant staff reminder ({$interval}m) for reservation #{$reservation->reference}");
                            }
                        }
                    }
                }
            }
        }

        $this->info("Reservation Reminders Dispatch Complete: Evaluated {$processedCount} reservations. Dispatched {$customerRemindersSent} customer reminders, {$staffRemindersSent} staff reminders.");

        return Command::SUCCESS;
    }
}
