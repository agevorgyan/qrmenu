<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentReconciliationService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ReconcileSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:reconcile
                            {--grace-days=3 : Days granted in grace period before full expiration}
                            {--provider : Also reconcile pending payments with payment providers}
                            {--dry-run : Preview reconciliation actions without mutating state}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actively reconcile expired subscriptions and elapsed trials across all tenants';

    /**
     * Execute the console command.
     */
    public function handle(
        SubscriptionService $subscriptionService,
        PaymentReconciliationService $paymentReconciliationService
    ): int {
        $graceDays = (int) $this->option('grace-days');
        $dryRun = (bool) $this->option('dry-run');
        $provider = (bool) $this->option('provider');

        $this->info("Starting subscription reconciliation (grace window: {$graceDays} days)...");
        if ($dryRun) {
            $this->warn('DRY-RUN MODE ENABLED: No database state will be mutated.');
        }

        $result = $subscriptionService->reconcileExpiredSubscriptions($graceDays);
        $this->info("Reconciliation complete. Checked: {$result['checked']}, Transitioned: {$result['transitioned']}");

        if ($provider) {
            $this->info('Reconciling pending payments with payment providers...');
            $providerResult = $paymentReconciliationService->reconcilePendingAttempts(dryRun: $dryRun);
            $this->info("Provider reconciliation complete. Resolved Paid: {$providerResult['resolved_paid']}, Resolved Failed: {$providerResult['resolved_failed']}, Discrepancies: {$providerResult['discrepancies']}");
        }

        return Command::SUCCESS;
    }
}
