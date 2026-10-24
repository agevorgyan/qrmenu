<?php

use App\Models\Vendor;
use App\Models\VendorDeletionJob;
use App\Services\TenantContext;
use App\Services\VendorLifecycleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Actively reconcile tenant subscription statuses and trials daily.
 */
Schedule::command('subscriptions:reconcile')->daily()->name('tenant-subscription-reconcile');

/**
 * Process vendor deletions whose retention period has passed.
 */
Schedule::call(function () {
    $tenantContext = app(TenantContext::class);
    $lifecycleService = app(VendorLifecycleService::class);

    $pendingJobs = VendorDeletionJob::where('status', 'retention_pending')
        ->where('retention_ends_at', '<=', now())
        ->get();

    foreach ($pendingJobs as $job) {
        $tenantContext->runInTenantContext($job->vendor_id, function () use ($job, $lifecycleService) {
            try {
                $lifecycleService->executeDeletion($job, true);
            } catch (Throwable $e) {
                Log::error("Scheduled vendor deletion failed for job #{$job->id}: ".$e->getMessage());
            }
        });
    }
})->hourly()->name('tenant-retention-cleanup');

/**
 * Enforce documented data retention periods and IP anonymization daily.
 */
Schedule::command('privacy:prune')->daily()->name('privacy-retention-prune');

/**
 * Dispatch upcoming customer and restaurant reservation reminders every 5 minutes.
 */
Schedule::command('reservations:send-reminders')->everyFiveMinutes()->name('reservations-send-reminders');
