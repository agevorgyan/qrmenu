<?php

namespace App\Console\Commands;

use App\Domains\Loyalty\Models\WalletIssuer;
use App\Domains\Loyalty\Services\CampaignService;
use App\Domains\Loyalty\Services\PointsLedgerService;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessLoyaltyAutomationsCommand extends Command
{
    protected $signature = 'loyalty:process-automations';

    protected $description = 'Process automated loyalty tasks (lot expirations, birthday campaigns, certificate monitoring)';

    public function handle(
        PointsLedgerService $pointsLedgerService,
        CampaignService $campaignService
    ): int {
        $this->info('Starting automated loyalty processing...');

        // 1. Expire outdated point lots
        $this->info('Checking for expired point lots...');
        $expiredPoints = $pointsLedgerService->expirePointLots();
        $this->info("Expired {$expiredPoints} points across all tenants.");

        // 2. Process Birthday campaigns per active vendor
        $vendors = Vendor::where('is_active', true)->get();
        $birthdayEventsTotal = 0;

        foreach ($vendors as $vendor) {
            $eventsCount = $campaignService->processDailyBirthdayCampaigns($vendor->id);
            $birthdayEventsTotal += $eventsCount;

            // 3. Monitor Apple Certificate Expiry
            $issuer = WalletIssuer::where('vendor_id', $vendor->id)->first();
            if ($issuer && $issuer->apple_cert_expires_at) {
                $daysRemaining = now()->diffInDays($issuer->apple_cert_expires_at, false);
                if ($daysRemaining <= 30 && $daysRemaining >= 0) {
                    Log::warning("Vendor #{$vendor->id} ({$vendor->name}) Apple Pass certificate expires in {$daysRemaining} days!");
                }
            }
        }

        $this->info("Dispatched {$birthdayEventsTotal} automated birthday rewards.");
        $this->info('Loyalty automations processing complete.');

        return Command::SUCCESS;
    }
}
