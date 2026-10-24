<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ReconcileSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:reconcile {--grace-days=3 : Days granted in grace period before full expiration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actively reconcile expired subscriptions and elapsed trials across all tenants';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): int
    {
        $graceDays = (int) $this->option('grace-days');
        $this->info("Starting subscription reconciliation (grace window: {$graceDays} days)...");

        $result = $subscriptionService->reconcileExpiredSubscriptions($graceDays);

        $this->info("Reconciliation complete. Checked: {$result['checked']}, Transitioned: {$result['transitioned']}");

        return Command::SUCCESS;
    }
}
