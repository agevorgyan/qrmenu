<?php

namespace App\Services;

use App\DTOs\EntitlementResult;
use App\Exceptions\FeatureNotEntitledException;
use App\Models\SubscriptionPlan;
use App\Models\TenantFeatureOverride;
use App\Models\User;
use App\Models\Vendor;
use App\Security\FeatureCatalog;
use App\Security\Permission;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Contextual fallback: TenantContext first, then authenticated user's vendor
        return $this->tenantContext->getTenant() ?? Auth::user()?->vendor;
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
            ->where('feature_key', $normalized)
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
        // Basic public menu features (menu.base) remain viewable unless tenant itself is inactive
        if ($normalized !== FeatureCatalog::MENU_BASE && ! $vendor->allowsAccess()) {
            return EntitlementResult::deny(
                code: EntitlementResult::SUBSCRIPTION_INACTIVE,
                feature: $normalized,
                featureName: $displayName,
                planNeeded: $planNeeded,
                message: "🔒 Ձեր բաժանորդագրության ժամկետն ավարտվել է։ Խնդրում ենք երկարաձգել այն՝ '{$displayName}' ֆունկցիայից օգտվելու համար։",
                currentPlan: $currentPlan
            );
        }

        // 7. Plan Tier Entitlement Check
        $hasFeatureInPlan = false;
        if ($plan && is_array($plan->feature_codes) && ! empty($plan->feature_codes)) {
            $hasFeatureInPlan = in_array('*', $plan->feature_codes, true)
                || in_array($normalized, $plan->feature_codes, true);
        } else {
            $defaultFeatures = FeatureCatalog::getDefaultFeaturesForPlan($planSlug);
            $hasFeatureInPlan = in_array($normalized, $defaultFeatures, true);
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
        // Super admin with platform access bypasses tenant boundary
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
        ?User $actor = null
    ): TenantFeatureOverride {
        if ($actor && ! ($actor->isSuperAdmin() && $actor->hasPermission(Permission::PLATFORM_ACCESS))) {
            throw new AuthorizationException('Only authorized Super Administrators may manage feature overrides.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A clear justification reason is required for administrative feature overrides.');
        }

        $normalized = FeatureCatalog::normalize($featureKey);

        return DB::transaction(function () use ($vendor, $normalized, $enabled, $reason, $scope, $expiresAt, $actor) {
            $override = TenantFeatureOverride::updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'feature_key' => $normalized,
                ],
                [
                    'is_enabled' => $enabled,
                    'scope' => $scope,
                    'reason' => $reason,
                    'expires_at' => $expiresAt,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]
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

        $deleted = DB::transaction(function () use ($vendor, $normalized, $actor) {
            $count = TenantFeatureOverride::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('feature_key', $normalized)
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
    }
}
