<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorMembership;
use App\Security\Permission as SecurityPermission;
use App\Security\PermissionCatalog;
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

        // 2. Permission Key Validation: Unknown permissions always return DENY
        if (! in_array($permission, PermissionCatalog::validKeys(), true)) {
            return false;
        }

        // 3. Resolve target vendor ID
        $vId = $vendorId ?? $user->vendor_id;

        // 4. Resolve vendor membership (if vendor context is present)
        $membership = $vId ? $this->resolveMembership($user, $vId) : null;

        $state = $this->getPermissionState($user, $vId);

        // 5. Explicit DENY always takes highest precedence (even for SuperAdmin)
        if (in_array($permission, $state['denied'], true)) {
            return false;
        }

        // 6. SuperAdmin handling: remove unrestricted grants, restrict to platform and superadmin permissions
        if ($user->isSuperAdmin()) {
            return str_starts_with($permission, 'platform.')
                || in_array($permission, SecurityPermission::forRole('superadmin'), true);
        }

        // 7. Tenant isolation & Membership status
        if ($membership && ! $membership->isActive()) {
            return false;
        }

        if ($vId && ! $membership && ! empty($user->vendor_id) && (int) $user->vendor_id !== (int) $vId) {
            // Foreign vendor access attempt without membership
            return false;
        }

        // 8. Explicit ALLOW takes precedence over role defaults
        if (in_array($permission, $state['allowed'], true)) {
            return true;
        }

        // 9. Role-based permissions
        if (in_array($permission, $state['effective'], true)) {
            return true;
        }

        // 10. Default DENY: No implicit hierarchy bypass
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
     * Get the full permission state (effective, allowed overrides, denied overrides) for a user.
     *
     * @return array{effective: array<int, string>, allowed: array<int, string>, denied: array<int, string>}
     */
    public function getPermissionState(User $user, ?int $vendorId = null): array
    {
        if ($user->is_active === false) {
            return ['effective' => [], 'allowed' => [], 'denied' => []];
        }

        $vId = $vendorId ?? $user->vendor_id;
        $userId = (int) ($user->id ?? 0);
        $cacheKey = $this->getCacheKey($userId, $vId);

        $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $vId) {
            return $this->resolveEffectivePermissionsFromDb($user, $vId);
        });

        // Ensure array shape compatibility
        if (is_array($cached) && isset($cached['effective'])) {
            return $cached;
        }

        return [
            'effective' => is_array($cached) ? $cached : [],
            'allowed' => [],
            'denied' => [],
        ];
    }

    /**
     * Get the list of all effective permissions for a user within a vendor context.
     *
     * @return array<int, string>
     */
    public function getEffectivePermissions(User $user, ?int $vendorId = null): array
    {
        return $this->getPermissionState($user, $vendorId)['effective'];
    }

    /**
     * Internal resolution of effective permissions from Database.
     *
     * @return array{effective: array<int, string>, allowed: array<int, string>, denied: array<int, string>}
     */
    protected function resolveEffectivePermissionsFromDb(User $user, ?int $vendorId = null): array
    {
        $vId = $vendorId ?? $user->vendor_id;
        $membership = $vId ? $this->resolveMembership($user, $vId) : null;

        if ($membership && ! $membership->isActive()) {
            return ['effective' => [], 'allowed' => [], 'denied' => []];
        }

        $rolePermissions = [];
        $allowedOverrides = [];
        $deniedOverrides = [];

        if ($membership) {
            // Load roles attached to membership
            $hasRoles = $membership->roles()->exists();
            if ($hasRoles) {
                // Inactive roles check: only active roles grant permissions
                $roles = $membership->roles()
                    ->where('is_active', true)
                    ->with(['permissions:id,key'])
                    ->get();

                foreach ($roles as $role) {
                    foreach ($role->permissions as $p) {
                        $rolePermissions[] = $p->key;
                    }
                }
            } else {
                // Legacy fallback only if NO roles are attached to the membership
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

        return [
            'effective' => array_values($effective),
            'allowed' => array_values(array_unique($allowedOverrides)),
            'denied' => array_values(array_unique($deniedOverrides)),
        ];
    }

    /**
     * Check if a specific permission is explicitly denied for the user.
     */
    protected function isExplicitlyDenied(User $user, string $permission, ?VendorMembership $membership): bool
    {
        $vId = $membership?->vendor_id ?? $user->vendor_id;
        $state = $this->getPermissionState($user, $vId);

        return in_array($permission, $state['denied'], true);
    }

    /**
     * Check if a specific permission is explicitly granted as an individual override.
     */
    protected function isExplicitlyAllowed(User $user, string $permission, ?VendorMembership $membership): bool
    {
        $vId = $membership?->vendor_id ?? $user->vendor_id;
        $state = $this->getPermissionState($user, $vId);

        return in_array($permission, $state['allowed'], true);
    }

    /**
     * Implicit permission inheritance is prohibited to prevent privilege escalation.
     */
    protected function matchesPermissionHierarchy(array $effectivePermissions, string $permission): bool
    {
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
        if ($user->is_active === false) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $vId = $vendorId ?? $user->vendor_id;

        // Null location handling: only accessible if user has all-locations access or is vendor owner
        if (! $location) {
            $membership = $vId ? $this->resolveActiveMembership($user, $vId) : null;
            if ($membership) {
                return (bool) $membership->all_locations;
            }

            return $user->location_id === null && (int) $user->vendor_id === (int) $vId;
        }

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

        // If user has a membership that is inactive, deny
        if ($vId && $this->resolveMembership($user, $vId)) {
            return false;
        }

        // Legacy fallback only if user belongs to vendor
        if ((int) $user->vendor_id === (int) $vId) {
            if ($user->location_id !== null) {
                return (int) $user->location_id === (int) $location->id;
            }

            return true;
        }

        return false;
    }

    /**
     * Check if user is authorized to access a given location ID.
     */
    public function canAccessLocationId(User $user, ?int $locationId, ?int $vendorId = null): bool
    {
        if ($user->is_active === false) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $vId = $vendorId ?? $user->vendor_id;

        if ($locationId === null) {
            $membership = $vId ? $this->resolveActiveMembership($user, $vId) : null;
            if ($membership) {
                return (bool) $membership->all_locations;
            }

            return $user->location_id === null && (int) $user->vendor_id === (int) $vId;
        }

        $location = Location::find($locationId);
        if (! $location || (int) $location->vendor_id !== (int) $vId) {
            return false;
        }

        return $this->canAccessLocation($user, $location, $vId);
    }

    /**
     * Relationship-Based Access Control (ReBAC) check for Orders.
     */
    public function canAccessOrder(User $user, Order $order): bool
    {
        if ($user->is_active === false) {
            return false;
        }

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

        // Permission & ownership check: broad view / manage permissions grant access
        if ($this->hasPermission($user, 'orders.view_all') || $this->hasPermission($user, 'orders.manage')) {
            return true;
        }

        if ($this->hasPermission($user, 'orders.view')) {
            return true;
        }

        // Fine-grained view_own: MUST verify assigned waiter / owner relationship.
        // Orders with missing/unverified ownership MUST be denied!
        if ($this->hasPermission($user, 'orders.view_own')) {
            $isAssignedWaiter = ! empty($order->waiter_id) && (int) $order->waiter_id === (int) $user->id;
            $isOrderCreator = ! empty($order->user_id) && (int) $order->user_id === (int) $user->id;

            return $isAssignedWaiter || $isOrderCreator;
        }

        // Default to DENY
        return false;
    }

    /**
     * Relationship-Based Access Control (ReBAC) check for Tables.
     */
    public function canAccessTable(User $user, RestaurantTable $table): bool
    {
        if ($user->is_active === false) {
            return false;
        }

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

        if ($this->hasPermission($user, 'tables.view') || $this->hasPermission($user, 'tables.view_all')) {
            return true;
        }

        // Assigned tables: user must be explicitly assigned to this table.
        // Unassigned tables must be denied unless explicitly authorized above.
        if ($this->hasPermission($user, 'tables.view_assigned')) {
            if ($user->relationLoaded('assignedTables')) {
                return $user->assignedTables->contains('id', $table->id);
            }

            return $user->assignedTables()->where('restaurant_tables.id', $table->id)->exists();
        }

        // Default to DENY: No implicit allow
        return false;
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
