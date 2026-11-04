<?php

namespace App\Services;

use App\Models\SubscriptionAddon;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscriptionAddon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionAddonService
{
    public function __construct(
        protected SubscriptionAuditService $auditService
    ) {}

    /**
     * Assign an add-on to a vendor.
     */
    public function assignAddon(
        Vendor $vendor,
        SubscriptionAddon $addon,
        int $quantity = 1,
        string $billingPeriod = 'monthly',
        ?Carbon $expiresAt = null,
        ?User $actor = null,
        ?string $notes = null
    ): VendorSubscriptionAddon {
        return DB::transaction(function () use ($vendor, $addon, $quantity, $billingPeriod, $expiresAt, $actor, $notes) {
            $unitPrice = match ($billingPeriod) {
                'yearly' => (float) $addon->yearly_price,
                'one_time' => (float) $addon->one_time_price,
                default => (float) $addon->monthly_price,
            };

            $vendorAddon = VendorSubscriptionAddon::create([
                'vendor_id' => $vendor->id,
                'subscription_id' => $vendor->subscription?->id,
                'subscription_addon_id' => $addon->id,
                'quantity' => max(1, $quantity),
                'unit_price' => $unitPrice,
                'billing_period' => $billingPeriod,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'used_quantity' => 0,
                'granted_by' => $actor?->id,
                'notes' => $notes,
            ]);

            app(EntitlementService::class)->clearCache($vendor);

            $this->auditService->record(
                action: 'addon.assigned',
                vendor: $vendor,
                subscription: $vendor->subscription,
                targetType: VendorSubscriptionAddon::class,
                targetId: $vendorAddon->id,
                newValues: [
                    'addon_code' => $addon->code,
                    'feature_code' => $addon->feature_code,
                    'quantity' => $quantity,
                    'expires_at' => $expiresAt?->toIso8601String(),
                ],
                reason: $notes ?: "Assigned add-on {$addon->name}",
                actor: $actor
            );

            return $vendorAddon;
        });
    }

    /**
     * Cancel an active vendor add-on.
     */
    public function cancelVendorAddon(VendorSubscriptionAddon $vendorAddon, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($vendorAddon, $actor, $reason) {
            $vendorAddon->update(['status' => 'cancelled']);

            app(EntitlementService::class)->clearCache($vendorAddon->vendor_id);

            $this->auditService->record(
                action: 'addon.cancelled',
                vendor: $vendorAddon->vendor,
                subscription: $vendorAddon->subscription,
                targetType: VendorSubscriptionAddon::class,
                targetId: $vendorAddon->id,
                previousValues: ['status' => 'active'],
                newValues: ['status' => 'cancelled'],
                reason: $reason ?: 'Cancelled vendor add-on',
                actor: $actor
            );
        });
    }

    protected static ?bool $hasAddonsTable = null;

    protected array $addonFeatureCache = [];

    /**
     * Check if a vendor has a boolean feature unlocked via an active add-on.
     */
    public function hasFeatureViaAddon(Vendor $vendor, string $featureCode): bool
    {
        if (self::$hasAddonsTable === null) {
            self::$hasAddonsTable = Schema::hasTable('vendor_subscription_addons');
        }

        if (! self::$hasAddonsTable) {
            return false;
        }

        $cacheKey = "{$vendor->id}:{$featureCode}";
        if (array_key_exists($cacheKey, $this->addonFeatureCache)) {
            return $this->addonFeatureCache[$cacheKey];
        }

        $codes = $this->resolveRelatedFeatureCodes($featureCode);

        $exists = VendorSubscriptionAddon::query()
            ->where('vendor_id', $vendor->id)
            ->active()
            ->whereHas('addon', function ($q) use ($codes) {
                $q->where('is_active', true)
                    ->whereIn('feature_code', $codes);
            })
            ->exists();

        return $this->addonFeatureCache[$cacheKey] = $exists;
    }

    /**
     * Calculate total additive limit or quota bonus from active add-ons for a vendor and feature/quota key.
     */
    public function getAdditiveBonus(Vendor $vendor, string $featureOrQuotaCode): int
    {
        if (self::$hasAddonsTable === null) {
            self::$hasAddonsTable = Schema::hasTable('vendor_subscription_addons');
        }

        if (! self::$hasAddonsTable) {
            return 0;
        }

        $codes = $this->resolveRelatedFeatureCodes($featureOrQuotaCode);

        $activeAddons = VendorSubscriptionAddon::query()
            ->with('addon')
            ->where('vendor_id', $vendor->id)
            ->active()
            ->whereHas('addon', function ($q) use ($codes) {
                $q->where('is_active', true)
                    ->whereIn('addon_type', [SubscriptionAddon::TYPE_LIMIT_BOOST, SubscriptionAddon::TYPE_USAGE_PACK])
                    ->whereIn('feature_code', $codes);
            })
            ->get();

        $bonus = 0;
        foreach ($activeAddons as $va) {
            $unitValue = (int) ($va->addon?->quantity_value ?? 0);
            $qty = max(1, (int) $va->quantity);
            $bonus += ($unitValue * $qty);
        }

        return $bonus;
    }

    /**
     * Map between QuotaCatalog keys and Feature Catalog codes.
     *
     * @return array<int, string>
     */
    protected function resolveRelatedFeatureCodes(string $code): array
    {
        $pairs = [
            'products.count' => ['products.count', 'menu.products.limit'],
            'menu.products.limit' => ['menu.products.limit', 'products.count'],
            'categories.count' => ['categories.count', 'menu.categories.limit'],
            'menu.categories.limit' => ['menu.categories.limit', 'categories.count'],
            'branches.count' => ['branches.count', 'locations.limit', 'locations.multi', 'branches.multiple'],
            'locations.limit' => ['locations.limit', 'branches.count'],
            'team_members.count' => ['team_members.count', 'staff.users.limit'],
            'staff.users.limit' => ['staff.users.limit', 'team_members.count'],
            'orders.period' => ['orders.period', 'orders.monthly_limit'],
            'orders.monthly_limit' => ['orders.monthly_limit', 'orders.period'],
            'ai.requests.period' => ['ai.requests.period', 'ai.monthly_credits'],
            'ai.monthly_credits' => ['ai.monthly_credits', 'ai.requests.period'],
            'languages.count' => ['languages.count', 'menu.languages.limit'],
            'menu.languages.limit' => ['menu.languages.limit', 'languages.count'],
            'delivery_zones.count' => ['delivery_zones.count', 'delivery.zones.limit'],
            'delivery.zones.limit' => ['delivery.zones.limit', 'delivery_zones.count'],
            'currencies.count' => ['currencies.count', 'currency.max_additional'],
            'currency.max_additional' => ['currency.max_additional', 'currencies.count'],
            'branding.custom_domain' => ['branding.custom_domain', 'integrations.custom_domain'],
            'integrations.custom_domain' => ['integrations.custom_domain', 'branding.custom_domain'],
            'orders.online' => ['orders.online'],
            'analytics.advanced' => ['analytics.advanced'],
        ];

        return $pairs[$code] ?? [$code];
    }
}
