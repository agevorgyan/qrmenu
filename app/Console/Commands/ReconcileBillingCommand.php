<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcileBillingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:reconcile
                            {--minutes=15 : Reconcile pending attempts older than X minutes}
                            {--gateway= : Filter reconciliation to a specific gateway}
                            {--vendor= : Filter reconciliation to a specific vendor ID}
                            {--dry-run : Preview reconciliation actions without mutating state}
                            {--full : Also reconcile vendor-subscription record drift}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Operationally safe reconciliation of pending payment attempts with payment providers';

    /**
     * Execute the console command.
     */
    public function handle(PaymentReconciliationService $reconciliationService): int
    {
        $minutes = (int) $this->option('minutes');
        $gateway = $this->option('gateway') ? (string) $this->option('gateway') : null;
        $vendorId = $this->option('vendor') ? (int) $this->option('vendor') : null;
        $dryRun = (bool) $this->option('dry-run');
        $full = (bool) $this->option('full');

        $this->info('Starting billing and payment provider reconciliation...');
        if ($dryRun) {
            $this->warn('DRY-RUN MODE ENABLED: No database state will be mutated.');
        }

        $result = $reconciliationService->reconcilePendingAttempts(
            olderThanMinutes: $minutes,
            dryRun: $dryRun,
            gatewayFilter: $gateway,
            vendorId: $vendorId
        );

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Attempts Checked', $result['total_checked']],
                ['Resolved Paid', $result['resolved_paid']],
                ['Resolved Failed / Expired', $result['resolved_failed']],
                ['Discrepancies Flagged', $result['discrepancies']],
                ['Still Pending', $result['still_pending']],
                ['Dry Run', $result['dry_run'] ? 'Yes' : 'No'],
            ]
        );

        if ($full) {
            $this->info('Running full subscription cache reconciliation...');
            $drift = $reconciliationService->reconcileSubscriptionDiscrepancies(dryRun: $dryRun);
            $this->info("Subscription drift reconciliation complete. Checked: {$drift['checked']}, Synchronized: {$drift['synchronized']}");
        }

        if ($result['discrepancies'] > 0) {
            $this->error("Warning: {$result['discrepancies']} security discrepancy/discrepancies detected and blocked from automatic activation!");
        }

        $this->info('Reconciliation process finished successfully.');

        return Command::SUCCESS;
    }
}
