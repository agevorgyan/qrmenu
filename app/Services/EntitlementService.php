<?php

namespace App\Services;

use App\DTOs\EntitlementResult;
use App\Exceptions\FeatureNotEntitledException;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanVersion;
use App\Models\TenantFeatureOverride;
use App\Models\User;
use App\Models\Vendor;
use App\Security\FeatureCatalog;
use App\Security\Permission;
use App\Security\QuotaCatalog;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EntitlementService
{
    protected const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Resolve trusted Vendor instance from various sources.
     */
    public function resolveVendor(Vendor|int|null $vendor = null): ?Vendor
    {
        if ($vendor instanceof Vendor) {
            return $vendor;
        }

        if (is_int($vendor)) {
            return Vendor::withoutGlobalScopes()->find($vendor);
        }

        return $this->tenantContext->getTenant() ?? Auth::user()?->vendor;
    }

    /**
     * Central Entitlement Engine API (Section 8): Check if vendor has a boolean/active feature.
     * Supports both static calls EntitlementService::hasFeature(...) and instance calls $service->hasFeature(...).
     */
    public static function hasFeature(Vendor|int|null $vendor, string $featureCode, array &$visited = []): bool
    {
        $instance = app(self::class);
        $resolvedVendor = $instance->resolveVendor($vendor);
        if (! $resolvedVendor || ! $resolvedVendor->is_active) {
            return false;
        }

        $rawCode = strtolower(trim($featureCode));
        $normalized = FeatureCatalog::normalize($rawCode);

        if (in_array($rawCode, $visited, true)) {
            return true;
        }
        $visited[] = $rawCode;

        // 1. Priority 1: Valid Vendor Override
        $override = TenantFeatureOverride::withoutGlobalScopes()
            ->where('vendor_id', $resolvedVendor->id)
            ->whereIn('feature_key', array_unique([$rawCode, $normalized]))
            ->valid()
            ->latest('id')
            ->first();

        if ($override !== null) {
            return (bool) $override->is_enabled;
        }

        // 2. Subscription State Check (menu.base and menu.qr remain viewable if vendor is active)
        if (! in_array($rawCode, [FeatureCatalog::MENU_BASE, FeatureCatalog::MENU_QR], true)
            && ! in_array($normalized, [FeatureCatalog::MENU_BASE, FeatureCatalog::MENU_QR], true)
            && ! $resolvedVendor->allowsAccess()) {
            return false;
        }

        // 3. Priority 2a: Active Vendor Add-on
        if (app(SubscriptionAddonService::class)->hasFeatureViaAddon($resolvedVendor, $rawCode)) {
            return true;
        }

        // 4. Priority 2b: Active Subscription Plan Version / PlanFeature rows
        $planFeatureData = $instance->resolvePlanFeatureConfig($resolvedVendor, $rawCode, $normalized);
        if ($planFeatureData !== null) {
            $enabled = (bool) ($planFeatureData['is_enabled'] ?? false);
            if (! $enabled) {
                return false;
            }

            // Enforce feature dependencies: if a required parent feature is disabled, dependent feature cannot be active
            $requiredParents = app(FeatureDependencyService::class)->getRequiredFeatureCodes($rawCode);
            foreach ($requiredParents as $parentCode) {
                if (! self::hasFeature($resolvedVendor, $parentCode, $visited)) {
                    return false;
                }
            }

            return true;
        }

        // 5. Fallback to legacy check
        return $instance->can($resolvedVendor, $featureCode);
    }

    /**
     * Central Entitlement Engine API (Section 8): Get feature configuration/limit/boolean value.
     */
    public static function getFeatureValue(Vendor|int|null $vendor, string $featureCode): mixed
    {
        $instance = app(self::class);
        $resolvedVendor = $instance->resolveVendor($vendor);
        if (! $resolvedVendor) {
            return null;
        }

        $rawCode = strtolower(trim($featureCode));
        $normalized = FeatureCatalog::normalize($rawCode);

        // 1. Check Vendor Override
        $override = TenantFeatureOverride::withoutGlobalScopes()
            ->where('vendor_id', $resolvedVendor->id)
            ->whereIn('feature_key', array_unique([$rawCode, $normalized]))
            ->valid()
            ->latest('id')
            ->first();

        if ($override !== null) {
            if (! $override->is_enabled) {
                return false;
            }
            if ($override->config_value !== null) {
                return is_array($override->config_value) && array_key_exists('value', $override->config_value)
                    ? $override->config_value['value']
                    : $override->config_value;
            }
            if ($override->limit_value !== null) {
                return (int) $override->limit_value;
            }
            if ($override->usage_quota !== null) {
                return (int) $override->usage_quota;
            }

            return (bool) $override->is_enabled;
        }

        // 2. Check Plan Version / PlanFeature
        $planFeatureData = $instance->resolvePlanFeatureConfig($resolvedVendor, $rawCode, $normalized);
        if ($planFeatureData !== null) {
            if ($planFeatureData['limit_value'] !== null || $planFeatureData['usage_quota'] !== null) {
                return self::getLimit($resolvedVendor, $rawCode);
            }
            if ($planFeatureData['config_value'] !== null) {
                $cfg = $planFeatureData['config_value'];

                return is_array($cfg) && array_key_exists('value', $cfg) ? $cfg['value'] : $cfg;
            }

            return self::hasFeature($resolvedVendor, $rawCode);
        }

        // 3. Check Platform Default from Feature catalog
        try {
            if (Schema::hasTable('features')) {
                $feature = Feature::where('code', $rawCode)->orWhere('code', $normalized)->first();
                if ($feature) {
                    if (in_array($feature->feature_type, [Feature::TYPE_LIMIT, Feature::TYPE_USAGE, Feature::TYPE_METERED], true)) {
                        return self::getLimit($resolvedVendor, $rawCode);
                    }

                    return $feature->resolveDefaultScalar();
                }
            }
        } catch (\Throwable) {
            // Ignore
        }

        return self::hasFeature($resolvedVendor, $rawCode);
    }

    /**
     * Central Entitlement Engine API (Section 8): Get numeric limit or quota (-1 = unlimited).
     */
    public static function getLimit(Vendor|int|null $vendor, string $featureCode): int
    {
        $instance = app(self::class);
        $resolvedVendor = $instance->resolveVendor($vendor);
        if (! $resolvedVendor) {
            return 0;
        }

        $rawCode = strtolower(trim($featureCode));

        // If recognized by QuotaCatalog, delegate to QuotaService (which includes overrides + plan version + add-ons)
        if (QuotaCatalog::isValid($rawCode)) {
            return app(QuotaService::class)->getLimit($resolvedVendor, $rawCode);
        }

        // Otherwise check TenantFeatureOverride -> PlanFeature + Add-ons
        $override = TenantFeatureOverride::withoutGlobalScopes()
            ->where('vendor_id', $resolvedVendor->id)
            ->where('feature_key', $rawCode)
            ->valid()
            ->latest('id')
            ->first();

        if ($override !== null) {
            if (! $override->is_enabled) {
                return 0;
            }
            if ($override->limit_value !== null) {
                return (int) $override->limit_value;
            }
            if ($override->usage_quota !== null) {
                return (int) $override->usage_quota;
            }
        }

        $planFeatureData = $instance->resolvePlanFeatureConfig($resolvedVendor, $rawCode, $rawCode);
        $baseLimit = 0;
        if ($planFeatureData !== null) {
            if (! ($planFeatureData['is_enabled'] ?? false)) {
                $baseLimit = 0;
            } elseif ($planFeatureData['limit_value'] !== null) {
                $baseLimit = (int) $planFeatureData['limit_value'];
            } elseif ($planFeatureData['usage_quota'] !== null) {
                $baseLimit = (int) $planFeatureData['usage_quota'];
            }
        }

        if ($baseLimit === QuotaCatalog::UNLIMITED) {
            return QuotaCatalog::UNLIMITED;
        }

        $bonus = app(SubscriptionAddonService::class)->getAdditiveBonus($resolvedVendor, $rawCode);

        return $baseLimit + $bonus;
    }

    /**
     * Central Entitlement Engine API (Section 8): Check if vendor can use a feature/quota by $amount.
     */
    public static function canUse(Vendor|int|null $vendor, string $featureCode, int $amount = 1): bool
    {
        $instance = app(self::class);
        $resolvedVendor = $instance->resolveVendor($vendor);
        if (! $resolvedVendor || ! $resolvedVendor->is_active) {
            return false;
        }

        $rawCode = strtolower(trim($featureCode));

        if (QuotaCatalog::isValid($rawCode)) {
            return app(QuotaService::class)->can($resolvedVendor, $rawCode, $amount);
        }

        $planFeatureData = $instance->resolvePlanFeatureConfig($resolvedVendor, $rawCode, FeatureCatalog::normalize($rawCode));
        if ($planFeatureData !== null && ($planFeatureData['limit_value'] !== null || $planFeatureData['usage_quota'] !== null)) {
            $remaining = self::getRemainingQuota($resolvedVendor, $rawCode);

            return $remaining === QuotaCatalog::UNLIMITED || $remaining >= $amount;
        }

        return self::hasFeature($resolvedVendor, $featureCode);
    }

    /**
     * Central Entitlement Engine API (Section 8): Get remaining quota (-1 = unlimited).
     */
    public static function getRemainingQuota(Vendor|int|null $vendor, string $featureCode): int
    {
        $instance = app(self::class);
        $resolvedVendor = $instance->resolveVendor($vendor);
        if (! $resolvedVendor) {
            return 0;
        }

        $limit = self::getLimit($resolvedVendor, $featureCode);
        if ($limit === QuotaCatalog::UNLIMITED) {
            return QuotaCatalog::UNLIMITED;
        }

        $used = app(QuotaService::class)->getCurrentUsage($resolvedVendor, $featureCode);

        return max(0, $limit - $used);
    }

    /**
     * Central Entitlement Engine API (Section 8): Get all resolved entitlements for a vendor.
     *
     * @return array<string, mixed>
     */
    public static function getAllEntitlements(Vendor|int|null $vendor): array
    {
        $instance = app(self::class);
        $resolvedVendor = $instance->resolveVendor($vendor);
        if (! $resolvedVendor) {
            return [];
        }

        $cacheKey = "vendor_{$resolvedVendor->id}_all_entitlements";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($resolvedVendor) {
            $featuresResult = [];
            $overrideCodes = [];
            try {
                $overrideCodes = TenantFeatureOverride::withoutGlobalScopes()
                    ->where('vendor_id', $resolvedVendor->id)
                    ->active()
                    ->pluck('feature_key')
                    ->map(fn ($k) => strtolower(trim((string) $k)))
                    ->toArray();
            } catch (\Throwable) {
            }

            try {
                if (Schema::hasTable('features')) {
                    $allFeatures = Feature::where('is_active', true)->where('is_archived', false)->orderBy('sort_order')->get();
                    foreach ($allFeatures as $f) {
                        $source = in_array(strtolower($f->code), $overrideCodes, true)
                            ? 'override'
                            : (app(SubscriptionAddonService::class)->hasFeatureViaAddon($resolvedVendor, $f->code) ? 'addon' : 'plan');

                        $featuresResult[$f->code] = [
                            'code' => $f->code,
                            'name' => $f->localizedName(),
                            'type' => $f->feature_type,
                            'enabled' => self::hasFeature($resolvedVendor, $f->code),
                            'value' => self::getFeatureValue($resolvedVendor, $f->code),
                            'limit' => in_array($f->feature_type, [Feature::TYPE_LIMIT, Feature::TYPE_USAGE, Feature::TYPE_METERED], true)
                                ? self::getLimit($resolvedVendor, $f->code)
                                : null,
                            'remaining' => in_array($f->feature_type, [Feature::TYPE_LIMIT, Feature::TYPE_USAGE, Feature::TYPE_METERED], true)
                                ? self::getRemainingQuota($resolvedVendor, $f->code)
                                : null,
                            'source' => $source,
                        ];
                    }
                }
            } catch (\Throwable) {
                // Fallback
            }

            foreach (FeatureCatalog::all() as $legacyCode) {
                if (! isset($featuresResult[$legacyCode])) {
                    $featuresResult[$legacyCode] = [
                        'code' => $legacyCode,
                        'name' => FeatureCatalog::getDisplayName($legacyCode),
                        'type' => 'BOOLEAN',
                        'enabled' => app(self::class)->can($resolvedVendor, $legacyCode),
                        'value' => app(self::class)->can($resolvedVendor, $legacyCode),
                        'limit' => null,
                        'remaining' => null,
                        'source' => in_array(strtolower($legacyCode), $overrideCodes, true) ? 'override' : 'plan',
                    ];
                }
            }

            return $featuresResult;
        });
    }

    /**
     * Central Entitlement Engine API (Section 8): Invalidate cached entitlements for a vendor.
     */
    public static function invalidateCache(Vendor|int $vendor): void
    {
        app(self::class)->clearCache($vendor);
    }

    /**
     * Resolve PlanFeature configuration from pinned SubscriptionPlanVersion or PlanFeature table.
     *
     * @return array{is_enabled: bool, limit_value: int|null, usage_quota: int|null, config_value: mixed}|null
     */
    protected function resolvePlanFeatureConfig(Vendor $vendor, string $rawCode, string $normalizedCode): ?array
    {
        try {
            if (! Schema::hasTable('plan_features')) {
                return null;
            }

            $subscription = $vendor->currentSubscription
                ?? $vendor->subscription
                ?? $vendor->subscriptions()->latest('id')->first();

            // 1. Check pinned SubscriptionPlanVersion snapshot if present
            if ($subscription && ! empty($subscription->plan_version_id) && Schema::hasTable('subscription_plan_versions')) {
                $version = SubscriptionPlanVersion::find($subscription->plan_version_id);
                if ($version && is_array($version->features_snapshot)) {
                    if (array_key_exists($rawCode, $version->features_snapshot)) {
                        return $version->features_snapshot[$rawCode];
                    }
                    if (array_key_exists($normalizedCode, $version->features_snapshot)) {
                        return $version->features_snapshot[$normalizedCode];
                    }
                }
            }

            // 2. Check PlanFeature table for active plan
            $planId = $subscription?->subscription_plan_id ?? $vendor->subscription_plan_id;
            if (! $planId && ! empty($vendor->subscription_plan)) {
                $planId = SubscriptionPlan::where('slug', $vendor->subscription_plan)->value('id');
            }

            if ($planId) {
                $pf = PlanFeature::where('subscription_plan_id', $planId)
                    ->whereIn('feature_code', array_unique([$rawCode, $normalizedCode]))
                    ->latest('id')
                    ->first();

                if ($pf) {
                    return [
                        'is_enabled' => (bool) $pf->is_enabled,
                        'limit_value' => $pf->limit_value,
                        'usage_quota' => $pf->usage_quota,
                        'config_value' => $pf->config_value,
                    ];
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Evaluate if a vendor is entitled to a feature (Boolean result).
     */
    public function can(Vendor|int|null $vendor, string $featureKey, ?User $user = null): bool
    {
        return $this->check($vendor, $featureKey, $user)->allowed;
    }

    /**
     * Assert that a vendor is entitled to a feature. Throws FeatureNotEntitledException if not.
     *
     * @throws FeatureNotEntitledException
     */
    public function assertEntitled(Vendor|int|null $vendor, string $featureKey, ?User $user = null): void
    {
        $result = $this->check($vendor, $featureKey, $user);

        if (! $result->allowed) {
            throw new FeatureNotEntitledException($result);
        }
    }

    /**
     * Comprehensive entitlement evaluation returning an EntitlementResult.
     */
    public function check(Vendor|int|null $vendorInput, string $featureKey, ?User $user = null): EntitlementResult
    {
        // 1. Resolve trusted Vendor model
        $vendor = $this->resolveVendor($vendorInput);
        if (! $vendor) {
            return EntitlementResult::deny(
                code: EntitlementResult::TENANT_NOT_FOUND,
                feature: $featureKey,
                featureName: $featureKey,
                planNeeded: 'Basic',
                message: 'Active restaurant tenant context not found.'
            );
        }

        if (! $vendor->is_active) {
            return EntitlementResult::deny(
                code: EntitlementResult::TENANT_INACTIVE,
                feature: $featureKey,
                featureName: $featureKey,
                planNeeded: 'Basic',
                message: 'This restaurant account is currently inactive.'
            );
        }

        // 2. Normalize feature key
        $rawCode = strtolower(trim($featureKey));
        $normalized = FeatureCatalog::normalize($featureKey);
        $displayName = FeatureCatalog::getDisplayName($normalized, app()->getLocale());
        $planNeeded = FeatureCatalog::getMinimumPlan($normalized);

        // 3. Prevent cross-tenant user access if user supplied
        if ($user && ! ($user->isSuperAdmin() && $user->hasPermission(Permission::PLATFORM_ACCESS))) {
            if ((int) $user->vendor_id !== (int) $vendor->id) {
                return EntitlementResult::deny(
                    code: EntitlementResult::UNAUTHORIZED_USER,
                    feature: $normalized,
                    featureName: $displayName,
                    planNeeded: $planNeeded,
                    message: 'Access denied: user does not belong to this restaurant tenant.'
                );
            }
        }

        // 4. Resolve plan context
        $planId = $vendor->subscription_plan_id;
        $plan = ($vendor->relationLoaded('plan') && $vendor->plan?->id === $planId)
            ? $vendor->plan
            : ($planId ? SubscriptionPlan::find($planId) : null);
        $planSlug = strtolower($plan?->slug ?? $vendor->subscription_plan ?? 'basic');
        $currentPlan = $plan?->name ?? ucfirst($planSlug);

        // 5. Evaluate Administrative Overrides
        $override = TenantFeatureOverride::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('feature_key', array_unique([$rawCode, $normalized]))
            ->valid()
            ->latest('id')
            ->first();

        if ($override) {
            if (! $override->is_enabled) {
                return EntitlementResult::deny(
                    code: EntitlementResult::FEATURE_OVERRIDE_DISABLED,
                    feature: $normalized,
                    featureName: $displayName,
                    planNeeded: $planNeeded,
                    message: "🔒 Ֆունկցիան ('{$displayName}') վարչականորեն անջատված է Ձեր ռեստորանի համար։",
                    currentPlan: $currentPlan
                );
            }

            return EntitlementResult::grant(
                feature: $normalized,
                featureName: $displayName,
                currentPlan: $currentPlan,
                code: EntitlementResult::FEATURE_OVERRIDE_ENABLED,
                message: "Ֆունկցիան ('{$displayName}') ակտիվացված է հատուկ կարգավիճակով։"
            );
        }

        // 6. Subscription State & Access Validity
        if (! in_array($normalized, [FeatureCatalog::MENU_BASE, FeatureCatalog::MENU_QR], true) && ! $vendor->allowsAccess()) {
            return EntitlementResult::deny(
                code: EntitlementResult::SUBSCRIPTION_INACTIVE,
                feature: $normalized,
                featureName: $displayName,
                planNeeded: $planNeeded,
                message: "🔒 Ձեր բաժանորդագրության ժամկետն ավարտվել է։ Խնդրում ենք երկարաձգել այն՝ '{$displayName}' ֆունկցիայից օգտվելու համար։",
                currentPlan: $currentPlan
            );
        }

        // 6b. Check Active Vendor Add-on
        if (app(SubscriptionAddonService::class)->hasFeatureViaAddon($vendor, $rawCode)
            || app(SubscriptionAddonService::class)->hasFeatureViaAddon($vendor, $normalized)) {
            return EntitlementResult::grant(
                feature: $normalized,
                featureName: $displayName,
                currentPlan: $currentPlan
            );
        }

        // 7. Plan Tier Entitlement Check
        $hasFeatureInPlan = false;

        // If checking a dynamic feature code (not one of the 18 legacy codes) or if a pinned plan version exists
        $isLegacyCoreCode = in_array($normalized, FeatureCatalog::all(), true) && $rawCode === $normalized;
        if (! $isLegacyCoreCode && in_array($rawCode, FeatureCatalog::dynamicCodes(), true)) {
            $pfConfig = $this->resolvePlanFeatureConfig($vendor, $rawCode, $normalized);
            if ($pfConfig !== null) {
                $hasFeatureInPlan = self::hasFeature($vendor, $rawCode);
            }
        }

        if (! $hasFeatureInPlan) {
            $featuresInPlan = ($plan && is_array($plan->feature_codes)) ? $plan->feature_codes : [];
            $defaultFeatures = FeatureCatalog::getDefaultFeaturesForPlan($planSlug);
            $allPlanFeatures = array_unique(array_merge($featuresInPlan, $defaultFeatures));

            $hasFeatureInPlan = in_array('*', $allPlanFeatures, true)
                || in_array($normalized, $allPlanFeatures, true)
                || in_array($rawCode, $allPlanFeatures, true);
        }

        if (! $hasFeatureInPlan) {
            return EntitlementResult::deny(
                code: EntitlementResult::PLAN_UPGRADE_REQUIRED,
                feature: $normalized,
                featureName: $displayName,
                planNeeded: $planNeeded,
                message: "🔒 {$displayName} ֆունկցիան հասանելի չէ Ձեր ընթացիկ ({$currentPlan}) փաթեթում։ Խնդրում ենք անցնել {$planNeeded} փաթեթի։",
                currentPlan: $currentPlan
            );
        }

        return EntitlementResult::grant(
            feature: $normalized,
            featureName: $displayName,
            currentPlan: $currentPlan
        );
    }

    /**
     * Separate Tenant Entitlement from User-level Authorization (RBAC).
     */
    public function canUserAccessFeature(
        User $user,
        string $featureKey,
        ?string $requiredUserPermission = null
    ): bool {
        if ($user->isSuperAdmin() && $user->hasPermission(Permission::PLATFORM_ACCESS)) {
            return true;
        }

        $vendor = $user->vendor;
        if (! $vendor) {
            return false;
        }

        // 1. Check Tenant Entitlement (Plan + Overrides + Subscription)
        if (! $this->can($vendor, $featureKey, $user)) {
            return false;
        }

        // 2. Check User-Level Role Permission (RBAC)
        if ($requiredUserPermission && ! $user->hasPermission($requiredUserPermission)) {
            return false;
        }

        return true;
    }

    /**
     * Create or update an administrative feature override.
     * Restricted strictly to Super Admins.
     *
     * @throws AuthorizationException
     */
    public function setOverride(
        Vendor $vendor,
        string $featureKey,
        bool $enabled,
        string $reason,
        string $scope = 'global',
        ?Carbon $expiresAt = null,
        ?User $actor = null,
        ?int $limitValue = null,
        ?int $usageQuota = null,
        mixed $configValue = null,
        ?string $valueType = null
    ): TenantFeatureOverride {
        if ($actor && ! ($actor->isSuperAdmin() && $actor->hasPermission(Permission::PLATFORM_ACCESS))) {
            throw new AuthorizationException('Only authorized Super Administrators may manage feature overrides.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A clear justification reason is required for administrative feature overrides.');
        }

        $normalized = FeatureCatalog::normalize($featureKey);

        return DB::transaction(function () use ($vendor, $normalized, $enabled, $reason, $scope, $expiresAt, $actor, $limitValue, $usageQuota, $configValue, $valueType) {
            $payload = [
                'is_enabled' => $enabled,
                'scope' => $scope,
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ];

            if (Schema::hasColumn('tenant_feature_overrides', 'value_type')) {
                $payload['value_type'] = $valueType ?? ($limitValue !== null ? 'LIMIT' : ($usageQuota !== null ? 'USAGE' : ($configValue !== null ? 'CONFIGURATION' : 'BOOLEAN')));
                $payload['limit_value'] = $limitValue;
                $payload['usage_quota'] = $usageQuota;
                $payload['config_value'] = is_array($configValue) ? $configValue : ($configValue !== null ? ['value' => $configValue] : null);
            }

            $override = TenantFeatureOverride::updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'feature_key' => $normalized,
                ],
                $payload
            );

            $this->clearCache($vendor);

            Log::info("Administrative feature override set for vendor {$vendor->id}: feature={$normalized}, enabled=".($enabled ? 'true' : 'false').', actor='.($actor?->id ?? 'system'));

            return $override;
        });
    }

    /**
     * Revoke an administrative feature override.
     *
     * @throws AuthorizationException
     */
    public function revokeOverride(Vendor $vendor, string $featureKey, ?User $actor = null): bool
    {
        if ($actor && ! ($actor->isSuperAdmin() && $actor->hasPermission(Permission::PLATFORM_ACCESS))) {
            throw new AuthorizationException('Only authorized Super Administrators may revoke feature overrides.');
        }

        $normalized = FeatureCatalog::normalize($featureKey);

        $deleted = DB::transaction(function () use ($vendor, $normalized, $featureKey, $actor) {
            $count = TenantFeatureOverride::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->whereIn('feature_key', array_unique([$normalized, strtolower(trim($featureKey))]))
                ->delete();

            $this->clearCache($vendor);

            Log::info("Administrative feature override revoked for vendor {$vendor->id}: feature={$normalized}, actor=".($actor?->id ?? 'system'));

            return $count > 0;
        });

        return $deleted;
    }

    /**
     * Return list of all currently entitled feature keys for a tenant.
     *
     * @return array<int, string>
     */
    public function getEntitledFeatures(Vendor $vendor): array
    {
        $cacheKey = "vendor_{$vendor->id}_entitled_features";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($vendor) {
            $entitled = [];
            foreach (FeatureCatalog::all() as $feature) {
                if ($this->can($vendor, $feature)) {
                    $entitled[] = $feature;
                }
            }
            foreach (FeatureCatalog::dynamicCodes() as $code) {
                if (! in_array($code, $entitled, true) && self::hasFeature($vendor, $code)) {
                    $entitled[] = $code;
                }
            }

            return $entitled;
        });
    }

    /**
     * Invalidate cached entitlements for a vendor.
     */
    public function clearCache(Vendor|int $vendor): void
    {
        $vendorId = $vendor instanceof Vendor ? $vendor->id : $vendor;
        Cache::forget("vendor_{$vendorId}_entitled_features");
        Cache::forget("vendor_{$vendorId}_all_entitlements");
    }
}
