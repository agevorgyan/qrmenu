<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use App\Security\Permission;

class LocationPolicy
{
    /**
     * Superadmin checks explicit platform permission rather than uncontrolled bypass.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return $user->hasPermission(Permission::PLATFORM_VENDORS);
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::LOCATIONS_VIEW);
    }

    public function view(User $user, Location $location): bool
    {
        $vId = (int) $location->vendor_id;

        return $user->canAccessLocation($location, $vId)
            && $user->hasPermission(Permission::LOCATIONS_VIEW, $vId);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::LOCATIONS_MANAGE);
    }

    public function update(User $user, Location $location): bool
    {
        $vId = (int) $location->vendor_id;

        return $user->canAccessLocation($location, $vId)
            && $user->hasPermission(Permission::LOCATIONS_MANAGE, $vId);
    }

    public function delete(User $user, Location $location): bool
    {
        $vId = (int) $location->vendor_id;

        return $user->canAccessLocation($location, $vId)
            && $user->hasPermission(Permission::LOCATIONS_MANAGE, $vId);
    }
}
