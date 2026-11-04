<?php

use App\Models\SubscriptionPlan;
use App\Security\FeatureCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tiers = ['basic', 'pro', 'advanced', 'business', 'custom'];

        foreach ($tiers as $slug) {
            $canonicalCodes = FeatureCatalog::getDefaultFeaturesForPlan($slug);

            $plan = SubscriptionPlan::where('slug', $slug)->first();
            if ($plan) {
                $existingCodes = is_array($plan->feature_codes) ? $plan->feature_codes : [];
                $merged = array_values(array_unique(array_merge($existingCodes, $canonicalCodes)));

                $plan->updateQuietly([
                    'feature_codes' => $merged,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback: feature codes update is non-destructive
    }
};
