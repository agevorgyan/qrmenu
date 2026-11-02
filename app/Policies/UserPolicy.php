<?php

namespace App\Policies;

use App\Models\User;
use App\Security\Permission;

class UserPolicy
{
    /**
     * Determine whether the user can view any team members.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::TEAM_VIEW);
    }

    /**
     * Determine whether the user can view the team member.
     */
    public function view(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return $user->hasPermission(Permission::PLATFORM_VENDORS);
        }

        $targetVendorId = (int) $target->vendor_id;
        if (! $targetVendorId) {
            return false;
        }

        return $user->hasPermission(Permission::TEAM_VIEW, $targetVendorId);
    }

    /**
     * Determine whether the user can create/invite a team member.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::TEAM_MANAGE);
    }

    /**
     * Determine whether the user can update the team member.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return $user->hasPermission(Permission::PLATFORM_VENDORS);
        }

        $targetVendorId = (int) $target->vendor_id;
        if (! $targetVendorId) {
            return false;
        }

        if (! $user->hasPermission(Permission::TEAM_MANAGE, $targetVendorId)) {
            return false;
        }

        // Only vendor owner can edit other owners
        if ($target->isVendorOwner($targetVendorId) && ! $user->isVendorOwner($targetVendorId)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the team member.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return $user->hasPermission(Permission::PLATFORM_VENDORS);
        }

        $targetVendorId = (int) $target->vendor_id;
        if (! $targetVendorId) {
            return false;
        }

        // Cannot delete oneself
        if ((int) $user->id === (int) $target->id) {
            return false;
        }

        if (! $user->hasPermission(Permission::TEAM_MANAGE, $targetVendorId)) {
            return false;
        }

        // Cannot delete vendor owner
        if ($target->isVendorOwner($targetVendorId)) {
            return false;
        }

        return true;
    }
}
