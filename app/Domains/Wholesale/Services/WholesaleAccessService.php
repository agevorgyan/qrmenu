<?php

namespace App\Domains\Wholesale\Services;

use App\Models\TenantModuleAccess;
use App\Models\TenantModuleAccessLog;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;

class WholesaleAccessService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Resolve vendor instance.
     */
    public function resolveVendor(Vendor|int|null $vendor = null): ?Vendor
    {
        if ($vendor instanceof Vendor) {
            return $vendor;
        }

        if (is_int($vendor)) {
            return Vendor::withoutGlobalScopes()->find($vendor);
        }

        return $this->tenantContext->getTenant();
    }

    /**
     * Centralized security check: Is the vendor entitled to the Wholesale POS subsystem?
     * Strict rules:
     * - Must exist and be active
     * - Must have an entry in tenant_module_access with module_key = 'wholesale_pos'
     * - is_enabled must be true
     * - revoked_at must be null
     * - expires_at must be null or in the future
     */
    public static function isEntitled(Vendor|int|null $vendor = null): bool
    {
        $instance = app(self::class);
        $resolvedVendor = $instance->resolveVendor($vendor);

        if (! $resolvedVendor || ! $resolvedVendor->is_active) {
            return false;
        }

        $access = TenantModuleAccess::where('vendor_id', $resolvedVendor->id)
            ->where('module_key', TenantModuleAccess::MODULE_WHOLESALE_POS)
            ->first();

        if (! $access) {
            return false;
        }

        return $access->isValid();
    }

    /**
     * Assert entitlement or throw an authorization exception.
     */
    public static function assertEntitled(Vendor|int|null $vendor = null): void
    {
        if (! self::isEntitled($vendor)) {
            abort(403, __('Wholesale POS module is not enabled for this tenant. Access is strictly managed by platform Super Administrators.'));
        }
    }

    /**
     * Grant or re-activate access for a tenant.
     */
    public function grantAccess(Vendor $vendor, User $actor, ?Carbon $expiresAt = null, ?string $reason = null): TenantModuleAccess
    {
        $access = TenantModuleAccess::firstOrNew([
            'vendor_id' => $vendor->id,
            'module_key' => TenantModuleAccess::MODULE_WHOLESALE_POS,
        ]);

        $previousState = $access->exists ? $access->toArray() : null;

        $access->is_enabled = true;
        $access->enabled_at = Carbon::now();
        $access->expires_at = $expiresAt;
        $access->granted_by = $actor->id;
        $access->revoked_at = null;
        $access->revoked_by = null;
        $access->reason = $reason ?: $access->reason;
        $access->save();

        $this->recordLog($access, 'granted', $actor, $previousState, $access->fresh()->toArray(), $reason);

        return $access;
    }

    /**
     * Temporarily suspend access for a tenant.
     */
    public function suspendAccess(Vendor $vendor, User $actor, ?string $reason = null): TenantModuleAccess
    {
        $access = TenantModuleAccess::firstOrNew([
            'vendor_id' => $vendor->id,
            'module_key' => TenantModuleAccess::MODULE_WHOLESALE_POS,
        ]);

        $previousState = $access->toArray();

        $access->is_enabled = false;
        $access->reason = $reason ?: 'Suspended by Super Admin';
        $access->save();

        $this->recordLog($access, 'suspended', $actor, $previousState, $access->fresh()->toArray(), $reason);

        return $access;
    }

    /**
     * Permanently revoke access for a tenant.
     */
    public function revokeAccess(Vendor $vendor, User $actor, ?string $reason = null): TenantModuleAccess
    {
        $access = TenantModuleAccess::firstOrNew([
            'vendor_id' => $vendor->id,
            'module_key' => TenantModuleAccess::MODULE_WHOLESALE_POS,
        ]);

        $previousState = $access->toArray();

        $access->is_enabled = false;
        $access->revoked_at = Carbon::now();
        $access->revoked_by = $actor->id;
        $access->reason = $reason ?: 'Revoked by Super Admin';
        $access->save();

        $this->recordLog($access, 'revoked', $actor, $previousState, $access->fresh()->toArray(), $reason);

        return $access;
    }

    /**
     * Get detailed status payload for a vendor.
     *
     * @return array<string, mixed>
     */
    public function getAccessStatus(Vendor $vendor): array
    {
        $access = TenantModuleAccess::where('vendor_id', $vendor->id)
            ->where('module_key', TenantModuleAccess::MODULE_WHOLESALE_POS)
            ->with(['grantedByUser', 'revokedByUser'])
            ->first();

        if (! $access) {
            return [
                'is_entitled' => false,
                'status' => 'not_granted',
                'status_label' => __('Անհասանելի (Not Granted)'),
                'enabled_at' => null,
                'expires_at' => null,
                'granted_by' => null,
                'revoked_at' => null,
                'reason' => null,
            ];
        }

        $isValid = $access->isValid();
        $status = 'active';
        $label = __('Ակտիվ (Active)');

        if ($access->revoked_at !== null) {
            $status = 'revoked';
            $label = __('Չեղարկված (Revoked)');
        } elseif (! $access->is_enabled) {
            $status = 'suspended';
            $label = __('Կասեցված (Suspended)');
        } elseif ($access->expires_at !== null && Carbon::now()->greaterThanOrEqualTo($access->expires_at)) {
            $status = 'expired';
            $label = __('Ժամկետանց (Expired)');
        }

        return [
            'is_entitled' => $isValid,
            'status' => $status,
            'status_label' => $label,
            'enabled_at' => $access->enabled_at?->toIso8601String(),
            'expires_at' => $access->expires_at?->toIso8601String(),
            'granted_by' => $access->grantedByUser?->name,
            'revoked_at' => $access->revoked_at?->toIso8601String(),
            'revoked_by' => $access->revokedByUser?->name,
            'reason' => $access->reason,
        ];
    }

    /**
     * Paginated list of tenants with access details for Super Admin dashboard.
     */
    public function getTenantsForSuperAdmin(?string $search = null, ?string $statusFilter = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Vendor::withoutGlobalScopes()
            ->with(['moduleAccess' => function ($q) {
                $q->where('module_key', TenantModuleAccess::MODULE_WHOLESALE_POS);
            }])
            ->orderBy('id', 'desc');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($statusFilter === 'active') {
            $query->whereHas('moduleAccess', function ($q) {
                $q->where('module_key', TenantModuleAccess::MODULE_WHOLESALE_POS)
                    ->where('is_enabled', true)
                    ->whereNull('revoked_at')
                    ->where(function ($sq) {
                        $sq->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
                    });
            });
        } elseif ($statusFilter === 'disabled') {
            $query->where(function ($q) {
                $q->whereDoesntHave('moduleAccess', function ($sq) {
                    $sq->where('module_key', TenantModuleAccess::MODULE_WHOLESALE_POS);
                })->orWhereHas('moduleAccess', function ($sq) {
                    $sq->where('module_key', TenantModuleAccess::MODULE_WHOLESALE_POS)
                        ->where(function ($ssq) {
                            $ssq->where('is_enabled', false)
                                ->orWhereNotNull('revoked_at')
                                ->orWhere('expires_at', '<=', Carbon::now());
                        });
                });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get immutable audit history logs for a tenant.
     *
     * @return Collection<int, TenantModuleAccessLog>
     */
    public function getAuditLogs(Vendor $vendor): Collection
    {
        return TenantModuleAccessLog::where('vendor_id', $vendor->id)
            ->where('module_key', TenantModuleAccess::MODULE_WHOLESALE_POS)
            ->with('actedByUser')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Record an audit log entry.
     */
    protected function recordLog(
        TenantModuleAccess $access,
        string $action,
        User $actor,
        ?array $prevState,
        ?array $newState,
        ?string $reason = null
    ): TenantModuleAccessLog {
        return TenantModuleAccessLog::create([
            'tenant_module_access_id' => $access->id,
            'vendor_id' => $access->vendor_id,
            'module_key' => $access->module_key,
            'action' => $action,
            'acted_by' => $actor->id,
            'previous_state' => $prevState,
            'new_state' => $newState,
            'reason' => $reason,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => Carbon::now(),
        ]);
    }
}

