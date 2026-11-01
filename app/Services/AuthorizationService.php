<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorMembership;
use App\Security\Permission as SecurityPermission;
use Illuminate\Support\Facades\Cache;

class AuthorizationService
{
    /**
     * Cache duration in seconds (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Determine whether the given user has a specific permission.
     */
    public function hasPermission(User $user, string $permission, ?int $vendorId = null): bool
    {
        // 1. Authentication & Active account check
        if ($user->is_active === false) {
            return false;
        }

        // 2. Resolve target vendor ID
        $vId = $vendorId ?? $user->vendor_id;

        // 3. Resolve vendor membership (if vendor context is present)
        $membership = $vId ? $this->resolveMembership($user, $vId) : null;

        // 4. SuperAdmin handling
        if ($user->isSuperAdmin()) {
            // Check if SuperAdmin has an explicit DENY override
            if ($this->isExplicitlyDenied($user, $permission, $membership)) {
                return false;
            }

            // Platform permissions or standard abilities are granted
            return true;
        }

        // 5. Tenant isolation & Membership status
        if ($membership && ! $membership->isActive()) {
            return false;
        }

        if ($vId && ! $membership && ! empty($user->vendor_id) && (int) $user->vendor_id !== (int) $vId) {
            // Attempting to act on a foreign vendor without membership
            return false;
        }

        // 6. Explicit DENY always takes highest precedence
        if ($this->isExplicitlyDenied($user, $permission, $membership)) {
            return false;
        }

        // 7. Explicit ALLOW takes precedence over role defaults
        if ($this->isExplicitlyAllowed($user, $permission, $membership)) {
            return true;
        }

        // 8. Role-based permissions
        $effective = $this->getEffectivePermissions($user, $vId);
        if (in_array($permission, $effective, true)) {
            return true;
        }

        // 9. Safe hierarchical aliases (only if child is NOT explicitly denied)
        if ($this->matchesPermissionHierarchy($effective, $permission)) {
            return true;
        }

        // 10. Default DENY
        return false;
    }

    /**
     * Determine whether the user has any of the given permissions.
     */
    public function hasAnyPermission(User $user, array $permissions, ?int $vendorId = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission, $vendorId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user has all of the given permissions.
     */
    public function hasAllPermissions(User $user, array $permissions, ?int $vendorId = null): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($user, $permission, $vendorId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the list of all effective permissions for a user within a vendor context.
     *
     * @return array<int, string>
     */
    public function getEffectivePermissions(User $user, ?int $vendorId = null): array
    {
        if ($user->is_active === false) {
            return [];
        }

        $vId = $vendorId ?? $user->vendor_id;
        $userId = (int) ($user->id ?? 0);
        $cacheKey = $this->getCacheKey($userId, $vId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $vId) {
            return $this->resolveEffectivePermissionsFromDb($user, $vId);
        });
    }

    /**
     * Internal resolution of effective permissions from Database.
     *
     * @return array<int, string>
     */
    protected function resolveEffectivePermissionsFromDb(User $user, ?int $vendorId = null): array
    {
        $vId = $vendorId ?? $user->vendor_id;
        $membership = $vId ? $this->resolveMembership($user, $vId) : null;

        if ($membership && ! $membership->isActive()) {
            return [];
        }

        $rolePermissions = [];
        $allowedOverrides = [];
        $deniedOverrides = [];

        if ($membership) {
            // Load roles attached to membership
            $roles = $membership->roles()
                ->where('is_active', true)
                ->with(['permissions:id,key'])
                ->get();

            if ($roles->isNotEmpty()) {
                foreach ($roles as $role) {
                    foreach ($role->permissions as $p) {
                        $rolePermissions[] = $p->key;
                    }
                }
            } else {
                // If membership has no roles attached, fall back to user's assigned role
                $roleSlug = $user->role ?? '';
                $rolePermissions = SecurityPermission::forRole($roleSlug);
            }

            // Load membership permission overrides
            $overrides = $membership->permissions()
                ->with('permission:id,key')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->get();

            foreach ($overrides as $override) {
                if ($override->permission) {
                    if ($override->effect === 'allow') {
                        $allowedOverrides[] = $override->permission->key;
                    } elseif ($override->effect === 'deny') {
                        $deniedOverrides[] = $override->permission->key;
                    }
                }
            }
        } else {
            // Legacy fallback using user->role
            $roleSlug = $user->role ?? '';
            $rolePermissions = SecurityPermission::forRole($roleSlug);
        }

        // Legacy custom_permissions column on User (for backward compatibility)
        if (! empty($user->custom_permissions) && is_array($user->custom_permissions)) {
            $granted = $user->custom_permissions['granted'] ?? [];
            $denied = $user->custom_permissions['denied'] ?? [];

            $allowedOverrides = array_merge($allowedOverrides, $granted);
            $deniedOverrides = array_merge($deniedOverrides, $denied);
        }

        $effective = array_unique(array_merge($rolePermissions, $allowedOverrides));

        if (! empty($deniedOverrides)) {
            $effective = array_values(array_diff($effective, $deniedOverrides));
        }

        return $effective;
    }

    /**
     * Check if a specific permission is explicitly denied for the user.
     */
    protected function isExplicitlyDenied(User $user, string $permission, ?VendorMembership $membership): bool
    {
        // Check membership overrides
        if ($membership) {
            $hasDenyOverride = $membership->permissions()
                ->whereHas('permission', function ($q) use ($permission) {
                    $q->where('key', $permission);
                })
                ->where('effect', 'deny')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();

            if ($hasDenyOverride) {
                return true;
            }
        }

        // Check user custom_permissions
        if (! empty($user->custom_permissions['denied']) && is_array($user->custom_permissions['denied'])) {
            if (in_array($permission, $user->custom_permissions['denied'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a specific permission is explicitly granted as an individual override.
     */
    protected function isExplicitlyAllowed(User $user, string $permission, ?VendorMembership $membership): bool
    {
        if ($membership) {
            $hasAllowOverride = $membership->permissions()
                ->whereHas('permission', function ($q) use ($permission) {
                    $q->where('key', $permission);
                })
                ->where('effect', 'allow')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();

            if ($hasAllowOverride) {
                return true;
            }
        }

        if (! empty($user->custom_permissions['granted']) && is_array($user->custom_permissions['granted'])) {
            if (in_array($permission, $user->custom_permissions['granted'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check safe hierarchical aliases for permissions.
     */
    protected function matchesPermissionHierarchy(array $effectivePermissions, string $permission): bool
    {
        $supersets = [
            'orders.view_own' => ['orders.view', 'orders.view_all', 'pos.orders'],
            'orders.create' => ['pos.orders'],
            'orders.update_own' => ['orders.update', 'orders.manage'],
            'orders.view_details' => ['orders.view', 'orders.view_all', 'pos.orders'],
            'orders.search' => ['orders.view', 'orders.view_all', 'pos.orders'],
            'orders.view' => ['orders.view_all'],
            'payments.create' => ['pos.payments'],
            'payments.cash' => ['pos.payments'],
            'payments.card' => ['pos.payments'],
            'payments.split' => ['pos.payments'],
            'shifts.open' => ['pos.cash_drawer'],
            'shifts.close' => ['pos.cash_drawer'],
            'shifts.view_own' => ['pos.cash_drawer', 'pos.reports', 'shifts.view_all'],
            'shifts.view_all' => ['pos.reports'],
            'discounts.approve' => ['pos.discounts'],
            'refunds.approve' => ['pos.refunds'],
            'tips.view_own' => ['tips.view', 'pos.reports'],
            'employees.view' => ['team.view'],
            'employees.manage' => ['team.manage'],
            'tables.manage' => ['pos.settings'],
            'audit_logs.view' => ['pos.settings', 'platform.logs'],
            'profile.view_own' => ['pos.access'],
            'profile.update_own' => ['pos.access'],
            'password.change_own' => ['pos.access'],
            'pin.manage_own' => ['pos.access'],
        ];

        if (isset($supersets[$permission])) {
            foreach ($supersets[$permission] as $parent) {
                if (in_array($parent, $effectivePermissions, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve vendor membership for user (any status).
     */
    public function resolveMembership(User $user, int $vendorId): ?VendorMembership
    {
        if ($user->relationLoaded('memberships')) {
            return $user->memberships
                ->where('vendor_id', $vendorId)
                ->first();
        }

        return VendorMembership::where('user_id', $user->id)
            ->where('vendor_id', $vendorId)
            ->first();
    }

    /**
     * Resolve active vendor membership for user.
     */
    public function resolveActiveMembership(User $user, int $vendorId): ?VendorMembership
    {
        if ($user->relationLoaded('memberships')) {
            return $user->memberships
                ->where('vendor_id', $vendorId)
                ->where('status', 'active')
                ->first();
        }

        return VendorMembership::where('user_id', $user->id)
            ->where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Check if user is authorized to access a given Location model.
     */
    public function canAccessLocation(User $user, ?Location $location, ?int $vendorId = null): bool
    {
        if (! $location) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $vId = $vendorId ?? $user->vendor_id;
        if ((int) $location->vendor_id !== (int) $vId) {
            return false;
        }

        $membership = $vId ? $this->resolveActiveMembership($user, $vId) : null;
        if ($membership) {
            if ($membership->all_locations) {
                return true;
            }

            return $membership->locations()->where('locations.id', $location->id)->exists();
        }

        // Legacy single location_id fallback
        if ($user->location_id !== null) {
            return (int) $user->location_id === (int) $location->id;
        }

        return true;
    }

    /**
     * Check if user is authorized to access a given location ID.
     */
    public function canAccessLocationId(User $user, ?int $locationId, ?int $vendorId = null): bool
    {
        if ($locationId === null) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $vId = $vendorId ?? $user->vendor_id;
        $membership = $vId ? $this->resolveActiveMembership($user, $vId) : null;
        if ($membership) {
            if ($membership->all_locations) {
                return true;
            }

            return $membership->locations()->where('locations.id', $locationId)->exists();
        }

        if ($user->location_id !== null) {
            return (int) $user->location_id === (int) $locationId;
        }

        return true;
    }

    /**
     * Relationship-Based Access Control (ReBAC) check for Orders.
     */
    public function canAccessOrder(User $user, Order $order): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant isolation
        if ((int) $user->vendor_id !== (int) $order->vendor_id) {
            return false;
        }

        // Location isolation
        if (! $this->canAccessLocationId($user, $order->location_id)) {
            return false;
        }

        // Permission & ownership check
        if ($this->hasPermission($user, 'orders.view_all') || $this->hasPermission($user, 'orders.manage')) {
            return true;
        }

        if ($this->hasPermission($user, 'orders.view_own')) {
            return empty($order->waiter_id) || (int) $order->waiter_id === (int) $user->id || (int) ($order->user_id ?? 0) === (int) $user->id;
        }

        return $this->hasPermission($user, 'orders.view') || $this->hasPermission($user, 'pos.orders');
    }

    /**
     * Relationship-Based Access Control (ReBAC) check for Tables.
     */
    public function canAccessTable(User $user, RestaurantTable $table): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ((int) $user->vendor_id !== (int) $table->vendor_id) {
            return false;
        }

        if (! $this->canAccessLocationId($user, $table->location_id)) {
            return false;
        }

        if ($this->hasPermission($user, 'tables.manage') || $this->hasPermission($user, 'pos.settings')) {
            return true;
        }

        if ($this->hasPermission($user, 'tables.view_assigned')) {
            if ($user->relationLoaded('assignedTables')) {
                return $user->assignedTables->contains('id', $table->id);
            }

            return $user->assignedTables()->where('restaurant_tables.id', $table->id)->exists();
        }

        return true;
    }

    /**
     * Get all active roles assigned to a user in a vendor context.
     *
     * @return array<int, Role|string>
     */
    public function getUserRoles(User $user, ?int $vendorId = null): array
    {
        $vId = $vendorId ?? $user->vendor_id;
        $membership = $vId ? $this->resolveActiveMembership($user, $vId) : null;

        if ($membership) {
            return $membership->roles()->where('is_active', true)->get()->all();
        }

        if (! empty($user->role)) {
            return [$user->role];
        }

        return [];
    }

    /**
     * Invalidate cached permissions for a user or vendor.
     */
    public function invalidateCache(?int $userId = null, ?int $vendorId = null): void
    {
        if ($userId !== null) {
            if ($vendorId !== null) {
                Cache::forget($this->getCacheKey($userId, $vendorId));
            } else {
                // Clear default cache key
                Cache::forget("auth:perms:u{$userId}:v0");
                // Also clear for common vendor IDs
                if ($user = User::find($userId)) {
                    if ($user->vendor_id) {
                        Cache::forget($this->getCacheKey($userId, $user->vendor_id));
                    }
                    foreach ($user->memberships as $m) {
                        Cache::forget($this->getCacheKey($userId, $m->vendor_id));
                    }
                }
            }
        }
    }

    /**
     * Construct cache key.
     */
    protected function getCacheKey(int $userId, ?int $vendorId): string
    {
        $v = $vendorId ?? 0;

        return "auth:perms:u{$userId}:v{$v}";
    }
}
