<?php

namespace App\Services;

use App\Models\AuthorizationAuditLog;
use App\Models\MembershipPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorMembership;
use App\Security\Permission as SecurityPermission;
use App\Security\PermissionCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RoleManagementService
{
    public function __construct(
        protected AuthorizationService $authService
    ) {}

    /**
     * Get all available roles for a vendor (System Roles + Custom Tenant Roles).
     *
     * @return Collection<int, Role>
     */
    public function getRolesForVendor(int $vendorId, ?string $search = null, ?bool $activeOnly = null): Collection
    {
        $query = Role::forVendor($vendorId)
            ->with(['permissions:id,key,module,action,risk_level,is_sensitive'])
            ->withCount(['memberships']);

        if ($activeOnly !== null) {
            $query->where('is_active', $activeOnly);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        return $query->orderBy('is_system', 'desc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Create a new custom role for a vendor.
     *
     * @param  array<int, string>  $permissionKeys
     */
    public function createCustomRole(Vendor $vendor, User $actor, array $data): Role
    {
        $this->ensureCanManageRoles($actor, $vendor->id);

        $name = trim($data['name']);
        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($name);
        $description = $data['description'] ?? null;
        $permissionKeys = $data['permissions'] ?? [];

        // Unique slug validation within tenant
        $exists = Role::where('vendor_id', $vendor->id)->where('slug', $slug)->exists();
        if ($exists) {
            throw new InvalidArgumentException(__('A role with this code already exists for this restaurant.'));
        }

        // Prevent tenant from assigning platform permissions
        $this->validateNoPlatformPermissions($permissionKeys);

        // Prevent privilege escalation: Actor cannot grant permissions they do not possess
        $this->validatePrivilegeEscalation($actor, $vendor->id, $permissionKeys);

        return DB::transaction(function () use ($vendor, $actor, $name, $slug, $description, $permissionKeys) {
            $role = Role::create([
                'vendor_id' => $vendor->id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_system' => false,
                'is_active' => true,
            ]);

            if (! empty($permissionKeys)) {
                if (Permission::whereIn('key', $permissionKeys)->count() < count($permissionKeys)) {
                    PermissionCatalog::syncToDatabase();
                }
                $permissionIds = Permission::whereIn('key', $permissionKeys)->pluck('id')->all();
                $role->permissions()->sync($permissionIds);
            }

            $this->logAudit(
                vendorId: $vendor->id,
                actor: $actor,
                action: 'role.created',
                resourceType: 'Role',
                resourceId: $role->id,
                newValues: [
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'permissions' => $permissionKeys,
                ]
            );

            return $role->load('permissions');
        });
    }

    /**
     * Update an existing custom role.
     *
     * @param  array<int, string>  $permissionKeys
     */
    public function updateCustomRole(Role $role, User $actor, array $data): Role
    {
        if ($role->isSystem()) {
            throw new AccessDeniedHttpException(__('System roles are protected and cannot be modified.'));
        }

        $this->ensureCanManageRoles($actor, $role->vendor_id);

        $name = trim($data['name'] ?? $role->name);
        $description = array_key_exists('description', $data) ? $data['description'] : $role->description;
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $role->is_active;
        $permissionKeys = $data['permissions'] ?? null;

        if ($permissionKeys !== null) {
            $this->validateNoPlatformPermissions($permissionKeys);
            $this->validatePrivilegeEscalation($actor, $role->vendor_id, $permissionKeys);
        }

        return DB::transaction(function () use ($role, $actor, $name, $description, $isActive, $permissionKeys) {
            $oldValues = [
                'name' => $role->name,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'permissions' => $role->permissions()->pluck('key')->all(),
            ];

            $role->update([
                'name' => $name,
                'description' => $description,
                'is_active' => $isActive,
            ]);

            if ($permissionKeys !== null) {
                if (! empty($permissionKeys) && Permission::whereIn('key', $permissionKeys)->count() < count($permissionKeys)) {
                    PermissionCatalog::syncToDatabase();
                }
                $permissionIds = Permission::whereIn('key', $permissionKeys)->pluck('id')->all();
                $role->permissions()->sync($permissionIds);
            }

            $newValues = [
                'name' => $role->name,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'permissions' => $role->permissions()->pluck('key')->all(),
            ];

            // Invalidate cache for all affected users
            $this->invalidateRoleUsersCache($role);

            $this->logAudit(
                vendorId: $role->vendor_id,
                actor: $actor,
                action: 'role.updated',
                resourceType: 'Role',
                resourceId: $role->id,
                oldValues: $oldValues,
                newValues: $newValues
            );

            return $role->load('permissions');
        });
    }

    /**
     * Duplicate an existing role into a new custom role.
     */
    public function duplicateRole(Role $sourceRole, User $actor, string $newName, ?string $newSlug = null): Role
    {
        $vendorId = $sourceRole->vendor_id ?? $actor->vendor_id;
        $vendor = Vendor::findOrFail($vendorId);

        $permissionKeys = $sourceRole->permissions()->pluck('key')->all();

        return $this->createCustomRole($vendor, $actor, [
            'name' => $newName,
            'slug' => $newSlug,
            'description' => __('Duplicated from :role', ['role' => $sourceRole->name]),
            'permissions' => $permissionKeys,
        ]);
    }

    /**
     * Delete a custom role.
     */
    public function deleteCustomRole(Role $role, User $actor): bool
    {
        if ($role->isSystem()) {
            throw new AccessDeniedHttpException(__('System roles are protected and cannot be deleted.'));
        }

        if (in_array($role->slug, ['owner', 'vendor_owner'], true)) {
            throw new AccessDeniedHttpException(__('The Owner role cannot be deleted.'));
        }

        $this->ensureCanManageRoles($actor, $role->vendor_id);

        // Check if users are currently assigned
        $assignedCount = $role->memberships()->count();
        if ($assignedCount > 0) {
            throw new InvalidArgumentException(
                __('Cannot delete role: :count employee(s) are currently assigned to this role. Please reassign them first.', ['count' => $assignedCount])
            );
        }

        return DB::transaction(function () use ($role, $actor) {
            $roleId = $role->id;
            $vendorId = $role->vendor_id;
            $roleName = $role->name;

            $this->invalidateRoleUsersCache($role);
            $role->permissions()->detach();
            $role->delete();

            $this->logAudit(
                vendorId: $vendorId,
                actor: $actor,
                action: 'role.deleted',
                resourceType: 'Role',
                resourceId: $roleId,
                oldValues: ['name' => $roleName]
            );

            return true;
        });
    }

    /**
     * Assign or update an individual permission override for a user membership.
     */
    public function setUserPermissionOverride(
        VendorMembership $membership,
        string $permissionKey,
        string $effect,
        ?CarbonInterface $expiresAt = null,
        ?string $reason = null,
        ?User $actor = null
    ): MembershipPermission {
        if ($actor) {
            $this->ensureCanManageRoles($actor, $membership->vendor_id);

            // Cannot grant platform permissions
            $this->validateNoPlatformPermissions([$permissionKey]);

            // Cannot grant permission if actor does not have it
            if ($effect === 'allow') {
                $this->validatePrivilegeEscalation($actor, $membership->vendor_id, [$permissionKey]);
            }
        }

        if (! in_array($effect, ['allow', 'deny'], true)) {
            throw new InvalidArgumentException(__('Invalid override effect: must be allow or deny.'));
        }

        $permission = Permission::where('key', $permissionKey)->first();
        if (! $permission) {
            PermissionCatalog::syncToDatabase();
            $permission = Permission::where('key', $permissionKey)->firstOrFail();
        }

        return DB::transaction(function () use ($membership, $permission, $effect, $expiresAt, $reason, $actor) {
            $override = MembershipPermission::updateOrCreate(
                [
                    'membership_id' => $membership->id,
                    'permission_id' => $permission->id,
                ],
                [
                    'effect' => $effect,
                    'expires_at' => $expiresAt,
                    'reason' => $reason,
                ]
            );

            $this->authService->invalidateCache($membership->user_id, $membership->vendor_id);

            if ($actor) {
                $this->logAudit(
                    vendorId: $membership->vendor_id,
                    actor: $actor,
                    targetUserId: $membership->user_id,
                    action: $effect === 'allow' ? 'permission.granted' : 'permission.denied',
                    resourceType: 'Permission',
                    resourceId: $permission->id,
                    newValues: [
                        'permission' => $permission->key,
                        'effect' => $effect,
                        'expires_at' => $expiresAt?->toIso8601String(),
                        'reason' => $reason,
                    ]
                );
            }

            return $override;
        });
    }

    /**
     * Remove an individual permission override.
     */
    public function removeUserPermissionOverride(
        VendorMembership $membership,
        string $permissionKey,
        User $actor
    ): bool {
        $this->ensureCanManageRoles($actor, $membership->vendor_id);

        $permission = Permission::where('key', $permissionKey)->first();
        if (! $permission) {
            PermissionCatalog::syncToDatabase();
            $permission = Permission::where('key', $permissionKey)->firstOrFail();
        }

        return DB::transaction(function () use ($membership, $permission, $actor) {
            $deleted = MembershipPermission::where('membership_id', $membership->id)
                ->where('permission_id', $permission->id)
                ->delete();

            $this->authService->invalidateCache($membership->user_id, $membership->vendor_id);

            $this->logAudit(
                vendorId: $membership->vendor_id,
                actor: $actor,
                targetUserId: $membership->user_id,
                action: 'permission.override_removed',
                resourceType: 'Permission',
                resourceId: $permission->id,
                oldValues: ['permission' => $permission->key]
            );

            return (bool) $deleted;
        });
    }

    /**
     * Safely transfer vendor ownership from current owner to a new user.
     */
    public function transferOwnership(Vendor $vendor, User $currentOwner, User $newOwner, User $actor): void
    {
        if ((int) $actor->id !== (int) $currentOwner->id && ! $actor->isSuperAdmin()) {
            throw new AccessDeniedHttpException(__('Only the current Owner or Platform SuperAdmin can transfer ownership.'));
        }

        if ((int) $newOwner->vendor_id !== (int) $vendor->id) {
            throw new InvalidArgumentException(__('New owner must belong to this restaurant.'));
        }

        DB::transaction(function () use ($vendor, $currentOwner, $newOwner, $actor) {
            $ownerRole = Role::whereNull('vendor_id')->where('slug', 'vendor_owner')->firstOrFail();
            $managerRole = Role::whereNull('vendor_id')->where('slug', 'general_manager')->first()
                ?? Role::whereNull('vendor_id')->where('slug', 'manager')->firstOrFail();

            $currentMembership = $this->authService->resolveActiveMembership($currentOwner, $vendor->id);
            $newMembership = $this->authService->resolveActiveMembership($newOwner, $vendor->id);

            // Reassign roles
            if ($newMembership) {
                $newMembership->roles()->syncWithoutDetaching([$ownerRole->id]);
            }
            $newOwner->update(['role' => 'vendor_owner']);

            // Demote previous owner to general manager
            if ($currentMembership) {
                $currentMembership->roles()->detach([$ownerRole->id]);
                $currentMembership->roles()->syncWithoutDetaching([$managerRole->id]);
            }
            $currentOwner->update(['role' => 'manager']);

            $this->authService->invalidateCache($currentOwner->id, $vendor->id);
            $this->authService->invalidateCache($newOwner->id, $vendor->id);

            $this->logAudit(
                vendorId: $vendor->id,
                actor: $actor,
                targetUserId: $newOwner->id,
                action: 'owner.transferred',
                resourceType: 'Vendor',
                resourceId: $vendor->id,
                oldValues: ['owner_id' => $currentOwner->id],
                newValues: ['owner_id' => $newOwner->id]
            );
        });
    }

    /**
     * Assign roles to a vendor membership with audit logging.
     *
     * @param  array<int, int>  $roleIds
     */
    public function assignRolesToMembership(VendorMembership $membership, array $roleIds, User $actor): void
    {
        $this->ensureCanManageRoles($actor, $membership->vendor_id);

        // Validate roles belong to this vendor or are system roles
        $roles = Role::whereIn('id', $roleIds)
            ->where(function ($q) use ($membership) {
                $q->whereNull('vendor_id')
                    ->orWhere('vendor_id', $membership->vendor_id);
            })
            ->get();

        if ($roles->count() !== count($roleIds)) {
            throw new AccessDeniedHttpException(__('Invalid role assignment: cross-restaurant roles prohibited.'));
        }

        // Prevent manager from assigning roles with permissions they don't possess
        if (! $actor->isSuperAdmin() && ! $actor->isVendorOwner()) {
            foreach ($roles as $role) {
                $permKeys = $role->permissions()->pluck('key')->all();
                $this->validatePrivilegeEscalation($actor, $membership->vendor_id, $permKeys);
            }
        }

        DB::transaction(function () use ($membership, $roles, $roleIds, $actor) {
            $oldRoles = $membership->roles()->pluck('name')->all();

            $membership->roles()->sync($roleIds);
            $newRoles = $roles->pluck('name')->all();

            $this->authService->invalidateCache($membership->user_id, $membership->vendor_id);

            $this->logAudit(
                vendorId: $membership->vendor_id,
                actor: $actor,
                targetUserId: $membership->user_id,
                action: 'user.roles_assigned',
                resourceType: 'VendorMembership',
                resourceId: $membership->id,
                oldValues: ['roles' => $oldRoles],
                newValues: ['roles' => $newRoles]
            );
        });
    }

    /**
     * Configure location access scopes for a membership with audit logging.
     *
     * @param  array<int, int>  $locationIds
     */
    public function setMembershipLocations(
        VendorMembership $membership,
        bool $allLocations,
        array $locationIds,
        User $actor
    ): void {
        $this->ensureCanManageRoles($actor, $membership->vendor_id);

        DB::transaction(function () use ($membership, $allLocations, $locationIds, $actor) {
            $oldLocations = [
                'all_locations' => $membership->all_locations,
                'location_ids' => $membership->locations()->pluck('locations.id')->all(),
            ];

            $membership->update(['all_locations' => $allLocations]);

            if (! $allLocations) {
                $membership->locations()->sync($locationIds);
            } else {
                $membership->locations()->detach();
            }

            $this->authService->invalidateCache($membership->user_id, $membership->vendor_id);

            $this->logAudit(
                vendorId: $membership->vendor_id,
                actor: $actor,
                targetUserId: $membership->user_id,
                action: 'user.locations_changed',
                resourceType: 'VendorMembership',
                resourceId: $membership->id,
                oldValues: $oldLocations,
                newValues: [
                    'all_locations' => $allLocations,
                    'location_ids' => $locationIds,
                ]
            );
        });
    }

    /**
     * Change membership status (active, inactive, suspended) with audit logging.
     */
    public function setMembershipStatus(VendorMembership $membership, string $status, User $actor): void
    {
        $this->ensureCanManageRoles($actor, $membership->vendor_id);

        if (! in_array($status, ['active', 'inactive', 'suspended'], true)) {
            throw new InvalidArgumentException(__('Invalid status value.'));
        }

        DB::transaction(function () use ($membership, $status, $actor) {
            $oldStatus = $membership->status;
            $membership->update(['status' => $status]);

            // If deactivated, revoke sessions and invalidate cache immediately
            if ($status !== 'active') {
                $targetUser = $membership->user;
                if ($targetUser) {
                    $this->revokeUserSessions($targetUser, $actor);
                }
            } else {
                $this->authService->invalidateCache($membership->user_id, $membership->vendor_id);
            }

            $this->logAudit(
                vendorId: $membership->vendor_id,
                actor: $actor,
                targetUserId: $membership->user_id,
                action: $status === 'active' ? 'membership.activated' : 'membership.deactivated',
                resourceType: 'VendorMembership',
                resourceId: $membership->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $status]
            );
        });
    }

    /**
     * Revoke all active sessions for a user immediately.
     */
    public function revokeUserSessions(User $user, ?User $actor = null): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        $this->authService->invalidateCache($user->id, $user->vendor_id);

        if ($actor) {
            $this->logAudit(
                vendorId: $user->vendor_id,
                actor: $actor,
                targetUserId: $user->id,
                action: 'user.sessions_revoked',
                resourceType: 'User',
                resourceId: $user->id
            );
        }
    }

    /**
     * Ensure actor has permission to manage roles for this vendor.
     */
    protected function ensureCanManageRoles(User $actor, ?int $vendorId): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($vendorId && (int) $actor->vendor_id !== (int) $vendorId) {
            throw new AccessDeniedHttpException(__('Cross-restaurant role management is prohibited.'));
        }

        if (! $this->authService->hasPermission($actor, 'roles.manage', $vendorId)
            && ! $this->authService->hasPermission($actor, SecurityPermission::TEAM_MANAGE, $vendorId)) {
            throw new AccessDeniedHttpException(__('You do not have permission to manage roles.'));
        }
    }

    /**
     * Prevent tenant roles from containing platform-level permissions.
     *
     * @param  array<int, string>  $permissionKeys
     */
    protected function validateNoPlatformPermissions(array $permissionKeys): void
    {
        $platformKeys = collect(PermissionCatalog::all())
            ->where('module', 'platform')
            ->pluck('key')
            ->all();

        $invalid = array_intersect($permissionKeys, $platformKeys);
        if (! empty($invalid)) {
            throw new AccessDeniedHttpException(__('Restaurant roles cannot be assigned platform permissions: :keys', [
                'keys' => implode(', ', $invalid),
            ]));
        }
    }

    /**
     * Privilege Escalation Guard: Actor cannot grant permissions they do not possess.
     *
     * @param  array<int, string>  $permissionKeys
     */
    protected function validatePrivilegeEscalation(User $actor, ?int $vendorId, array $permissionKeys): void
    {
        if ($actor->isSuperAdmin() || $actor->isVendorOwner()) {
            return;
        }

        $actorPermissions = $this->authService->getEffectivePermissions($actor, $vendorId);
        $missing = array_diff($permissionKeys, $actorPermissions);

        if (! empty($missing)) {
            throw new AccessDeniedHttpException(
                __('Privilege escalation prevented: You cannot grant permissions you do not possess (:missing).', [
                    'missing' => implode(', ', array_slice($missing, 0, 5)),
                ])
            );
        }
    }

    /**
     * Invalidate cached permissions for all users assigned to a role.
     */
    protected function invalidateRoleUsersCache(Role $role): void
    {
        $memberships = $role->memberships()->with('user:id,vendor_id')->get();
        foreach ($memberships as $membership) {
            $this->authService->invalidateCache($membership->user_id, $membership->vendor_id);
        }
    }

    /**
     * Append immutable entry to authorization audit log.
     */
    protected function logAudit(
        ?int $vendorId,
        User $actor,
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?int $targetUserId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): AuthorizationAuditLog {
        return AuthorizationAuditLog::create([
            'vendor_id' => $vendorId,
            'actor_id' => $actor->id,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
