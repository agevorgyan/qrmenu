<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\SubscriptionPlanVersion;
use App\Models\User;
use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanVersionService
{
    public function __construct(
        protected SubscriptionAuditService $auditService
    ) {}

    /**
     * Create a new immutable version of a subscription plan.
     *
     * @param  array<string, array{is_enabled?: bool, enabled?: bool, limit_value?: int|null, limit?: int|null, usage_quota?: int|null, quota?: int|null, config_value?: mixed, config?: mixed}>  $featureMatrix
     */
    public function createVersion(
        SubscriptionPlan $plan,
        array $featureMatrix = [],
        ?string $changeSummary = null,
        ?User $actor = null,
        bool $migrateExistingSubscribers = false,
        array $featureConfigs = []
    ): SubscriptionPlanVersion {
        if (empty($featureMatrix) && ! empty($featureConfigs)) {
            $featureMatrix = $featureConfigs;
        }

        return DB::transaction(function () use ($plan, $featureMatrix, $changeSummary, $actor, $migrateExistingSubscribers) {
            $previousVersion = SubscriptionPlanVersion::where('subscription_plan_id', $plan->id)
                ->where('is_current', true)
                ->latest('version_number')
                ->first();

            $nextVersionNumber = ((int) SubscriptionPlanVersion::where('subscription_plan_id', $plan->id)->max('version_number')) + 1;

            // If we are not migrating existing subscribers, pin any unpinned subscriptions to the previous version first
            if (! $migrateExistingSubscribers && $previousVersion) {
                Subscription::withoutGlobalScopes()
                    ->where('subscription_plan_id', $plan->id)
                    ->whereNull('plan_version_id')
                    ->update([
                        'plan_version_id' => $previousVersion->id,
                        'plan_version_number' => $previousVersion->version_number,
                    ]);
            }

            SubscriptionPlanVersion::where('subscription_plan_id', $plan->id)
                ->update(['is_current' => false]);

            $allFeatures = Feature::orderBy('sort_order', 'asc')->get()->keyBy('code');
            $featuresSnapshot = [];
            $limitsSnapshot = [];
            $enabledCodes = [];

            foreach ($featureMatrix as $code => $cfg) {
                $isEnabled = (bool) ($cfg['is_enabled'] ?? $cfg['enabled'] ?? false);
                $limitVal = array_key_exists('limit_value', $cfg) ? $cfg['limit_value'] : ($cfg['limit'] ?? null);
                $quotaVal = array_key_exists('usage_quota', $cfg) ? $cfg['usage_quota'] : ($cfg['quota'] ?? null);
                $configVal = array_key_exists('config_value', $cfg) ? $cfg['config_value'] : ($cfg['config'] ?? null);

                if ($limitVal !== null && $limitVal !== '') {
                    $limitVal = (int) $limitVal;
                } else {
                    $limitVal = null;
                }

                if ($quotaVal !== null && $quotaVal !== '') {
                    $quotaVal = (int) $quotaVal;
                } else {
                    $quotaVal = null;
                }

                $featuresSnapshot[$code] = [
                    'is_enabled' => $isEnabled,
                    'limit_value' => $limitVal,
                    'usage_quota' => $quotaVal,
                    'config_value' => $configVal,
                ];

                if ($isEnabled) {
                    $enabledCodes[] = $code;
                }
                if ($limitVal !== null) {
                    $limitsSnapshot[$code] = $limitVal;
                } elseif ($quotaVal !== null) {
                    $limitsSnapshot[$code] = $quotaVal;
                }
            }

            $version = SubscriptionPlanVersion::create([
                'subscription_plan_id' => $plan->id,
                'version_number' => $nextVersionNumber,
                'name' => $plan->name,
                'name_translations' => $plan->name_translations,
                'description' => $plan->description,
                'description_translations' => $plan->description_translations,
                'monthly_price' => $plan->price,
                'yearly_price' => $plan->yearly_price ?? $plan->resolvePrice('yearly', 'AMD'),
                'currency' => $plan->getRawOriginal('currency') ?: 'AMD',
                'trial_days' => $plan->trial_days ?? 14,
                'features_snapshot' => $featuresSnapshot,
                'limits_snapshot' => $limitsSnapshot,
                'change_summary' => $changeSummary ?: "Version {$nextVersionNumber}",
                'is_current' => true,
                'created_by' => $actor?->id,
            ]);

            $sortOrder = 1;
            foreach ($featuresSnapshot as $code => $snap) {
                $feature = $allFeatures[$code] ?? Feature::where('code', $code)->first();
                if (! $feature) {
                    continue;
                }

                PlanFeature::updateOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'plan_version_id' => $version->id,
                        'feature_id' => $feature->id,
                    ],
                    [
                        'feature_code' => $code,
                        'is_enabled' => $snap['is_enabled'],
                        'limit_value' => $snap['limit_value'],
                        'usage_quota' => $snap['usage_quota'],
                        'config_value' => $snap['config_value'],
                        'sort_order' => $sortOrder++,
                    ]
                );
            }

            // Map dynamic limits to QuotaCatalog keys on the plan
            $quotaLimits = $plan->quota_limits ?? [];
            $limitMapping = [
                'menu.products.limit' => QuotaCatalog::KEY_PRODUCTS_COUNT,
                'menu.categories.limit' => QuotaCatalog::KEY_CATEGORIES_COUNT,
                'locations.limit' => QuotaCatalog::KEY_BRANCHES_COUNT,
                'staff.users.limit' => QuotaCatalog::KEY_TEAM_MEMBERS_COUNT,
                'orders.monthly_limit' => QuotaCatalog::KEY_ORDERS_PERIOD,
                'ai.monthly_credits' => QuotaCatalog::KEY_AI_REQUESTS_PERIOD,
                'menu.languages.limit' => QuotaCatalog::KEY_LANGUAGES_COUNT,
                'delivery.zones.limit' => QuotaCatalog::KEY_DELIVERY_ZONES_COUNT,
                'currency.max_additional' => QuotaCatalog::KEY_CURRENCIES_COUNT,
            ];

            foreach ($limitMapping as $featureCode => $quotaKey) {
                if (array_key_exists($featureCode, $limitsSnapshot)) {
                    $quotaLimits[$quotaKey] = (int) $limitsSnapshot[$featureCode];
                }
            }

            // Merge legacy feature codes with enabled dynamic codes
            $mergedFeatureCodes = array_values(array_unique(array_merge(
                $this->mapDynamicToLegacyCodes($enabledCodes),
                $enabledCodes
            )));

            $plan->update([
                'current_version_id' => $version->id,
                'current_version_number' => $version->version_number,
                'feature_codes' => $mergedFeatureCodes,
                'quota_limits' => $quotaLimits,
            ]);

            if ($migrateExistingSubscribers) {
                $this->migrateSubscriptionsToVersion($plan, $version, $actor);
            }

            $this->auditService->record(
                action: 'plan.version.created',
                targetType: SubscriptionPlan::class,
                targetId: $plan->id,
                previousValues: $previousVersion ? [
                    'version_number' => $previousVersion->version_number,
                    'monthly_price' => $previousVersion->monthly_price,
                    'limits' => $previousVersion->limits_snapshot,
                ] : null,
                newValues: [
                    'version_number' => $version->version_number,
                    'monthly_price' => $version->monthly_price,
                    'limits' => $version->limits_snapshot,
                    'migrated_subscribers' => $migrateExistingSubscribers,
                ],
                reason: $changeSummary,
                actor: $actor
            );

            return $version;
        });
    }

    /**
     * Migrate all active/trialing subscriptions of a plan to a specific plan version.
     */
    public function migrateSubscriptionsToVersion(
        SubscriptionPlan $plan,
        SubscriptionPlanVersion $version,
        ?User $actor = null
    ): int {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->where('subscription_plan_id', $plan->id)
            ->get();

        $entitlementService = app(EntitlementService::class);
        $count = 0;

        foreach ($subscriptions as $sub) {
            $sub->update([
                'plan_version_id' => $version->id,
                'plan_version_number' => $version->version_number,
            ]);
            $entitlementService->clearCache($sub->vendor_id);
            $count++;
        }

        $this->auditService->record(
            action: 'plan.version.migrated',
            targetType: SubscriptionPlanVersion::class,
            targetId: $version->id,
            newValues: [
                'plan_id' => $plan->id,
                'version_number' => $version->version_number,
                'migrated_count' => $count,
            ],
            reason: "Migrated {$count} subscriptions to {$plan->name} v{$version->version_number}",
            actor: $actor
        );

        return $count;
    }

    /**
     * Duplicate an existing subscription plan with all its feature configurations.
     */
    public function duplicatePlan(
        SubscriptionPlan $sourcePlan,
        string $newName,
        ?string $newSlug = null,
        ?User $actor = null
    ): SubscriptionPlan {
        return DB::transaction(function () use ($sourcePlan, $newName, $newSlug, $actor) {
            $baseSlug = $newSlug ?: Str::slug($newName);
            $slug = $baseSlug;
            $counter = 1;
            while (SubscriptionPlan::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            $duplicate = SubscriptionPlan::create([
                'name' => $newName,
                'name_translations' => [
                    'hy' => $newName,
                    'en' => $newName,
                    'ru' => $newName,
                ],
                'slug' => $slug,
                'price' => $sourcePlan->price,
                'yearly_price' => $sourcePlan->yearly_price,
                'currency' => $sourcePlan->getRawOriginal('currency') ?: 'AMD',
                'billing_interval' => $sourcePlan->billing_interval,
                'duration_days' => $sourcePlan->duration_days,
                'trial_days' => $sourcePlan->trial_days,
                'grace_period_days' => $sourcePlan->grace_period_days ?? 3,
                'warning_threshold_percent' => $sourcePlan->warning_threshold_percent ?? 80,
                'features' => $sourcePlan->features,
                'feature_codes' => $sourcePlan->feature_codes,
                'quota_limits' => $sourcePlan->quota_limits,
                'description' => $sourcePlan->description,
                'description_translations' => $sourcePlan->description_translations,
                'is_active' => true,
                'is_custom' => $sourcePlan->is_custom,
                'is_archived' => false,
                'sort_order' => ((int) SubscriptionPlan::max('sort_order')) + 1,
            ]);

            foreach ($sourcePlan->prices as $price) {
                SubscriptionPlanPrice::create([
                    'subscription_plan_id' => $duplicate->id,
                    'billing_interval' => $price->billing_interval,
                    'currency' => $price->getRawOriginal('currency') ?: 'AMD',
                    'price' => $price->price,
                    'is_active' => $price->is_active,
                    'description' => $price->description,
                ]);
            }

            $sourceVersion = $sourcePlan->currentVersion
                ?? $sourcePlan->versions()->latest('version_number')->first();

            $matrix = $sourceVersion?->features_snapshot ?? [];
            $this->createVersion(
                plan: $duplicate,
                featureMatrix: $matrix,
                changeSummary: "Duplicated from {$sourcePlan->name}",
                actor: $actor,
                migrateExistingSubscribers: true
            );

            $this->auditService->record(
                action: 'plan.duplicated',
                targetType: SubscriptionPlan::class,
                targetId: $duplicate->id,
                previousValues: ['source_plan_id' => $sourcePlan->id, 'source_slug' => $sourcePlan->slug],
                newValues: ['new_plan_id' => $duplicate->id, 'new_slug' => $duplicate->slug],
                reason: "Duplicated plan {$sourcePlan->name} as {$newName}",
                actor: $actor
            );

            return $duplicate;
        });
    }

    /**
     * Archive a subscription plan (soft retire without breaking existing subscribers).
     */
    public function archivePlan(SubscriptionPlan $plan, ?User $actor = null): SubscriptionPlan
    {
        $plan->update([
            'is_archived' => true,
            'archived_at' => now(),
            'is_active' => false,
        ]);

        $this->auditService->record(
            action: 'plan.archived',
            targetType: SubscriptionPlan::class,
            targetId: $plan->id,
            newValues: ['is_archived' => true, 'is_active' => false],
            reason: "Archived plan {$plan->name}",
            actor: $actor
        );

        return $plan;
    }

    /**
     * Compute differences between two plan versions.
     *
     * @return array<string, mixed>
     */
    public function diffVersions(SubscriptionPlanVersion $from, SubscriptionPlanVersion $to): array
    {
        $fromFeatures = $from->features_snapshot ?? [];
        $toFeatures = $to->features_snapshot ?? [];
        $allKeys = array_unique(array_merge(array_keys($fromFeatures), array_keys($toFeatures)));

        $changes = [];
        foreach ($allKeys as $code) {
            $a = $fromFeatures[$code] ?? ['is_enabled' => false, 'limit_value' => null, 'usage_quota' => null];
            $b = $toFeatures[$code] ?? ['is_enabled' => false, 'limit_value' => null, 'usage_quota' => null];

            if (($a['is_enabled'] ?? false) !== ($b['is_enabled'] ?? false)
                || ($a['limit_value'] ?? null) !== ($b['limit_value'] ?? null)
                || ($a['usage_quota'] ?? null) !== ($b['usage_quota'] ?? null)) {
                $changes[$code] = [
                    'from' => $a,
                    'to' => $b,
                ];
            }
        }

        return [
            'from_version' => $from->version_number,
            'to_version' => $to->version_number,
            'price_changed' => (float) $from->monthly_price !== (float) $to->monthly_price,
            'from_price' => (float) $from->monthly_price,
            'to_price' => (float) $to->monthly_price,
            'feature_changes' => $changes,
        ];
    }

    /**
     * Map dynamic feature codes to legacy FeatureCatalog codes so middleware & legacy checks stay in sync.
     *
     * @param  array<int, string>  $enabledCodes
     * @return array<int, string>
     */
    protected function mapDynamicToLegacyCodes(array $enabledCodes): array
    {
        $legacy = [];
        $map = [
            'menu.qr' => FeatureCatalog::MENU_BASE,
            'languages.multilingual' => FeatureCatalog::MENU_TRANSLATIONS,
            'orders.online' => FeatureCatalog::ORDERS_ONLINE,
            'delivery.enabled' => FeatureCatalog::ORDERS_DELIVERY,
            'reservations.enabled' => FeatureCatalog::RESERVATIONS_MANAGE,
            'preorders.enabled' => FeatureCatalog::PREORDERS_MANAGE,
            'marketing.customers' => FeatureCatalog::CUSTOMERS_CRM,
            'locations.multi' => FeatureCatalog::BRANCHES_MULTIPLE,
            'team.manage' => FeatureCatalog::TEAM_MANAGE,
            'analytics.basic' => FeatureCatalog::ANALYTICS_BASIC,
            'analytics.advanced' => FeatureCatalog::ANALYTICS_ADVANCED,
            'ai.translation' => FeatureCatalog::AI_MENU_BUILDER,
            'ai.waiter' => FeatureCatalog::AI_WAITER,
            'integrations.custom_domain' => FeatureCatalog::BRANDING_CUSTOM_DOMAIN,
            'integrations.api' => FeatureCatalog::API_ACCESS,
            'pos.enabled' => FeatureCatalog::POS_ACCESS,
        ];

        foreach ($enabledCodes as $code) {
            if (isset($map[$code])) {
                $legacy[] = $map[$code];
            }
        }

        return array_values(array_unique($legacy));
    }
}
