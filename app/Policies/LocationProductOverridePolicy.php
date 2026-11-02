<?php

namespace App\Policies;

use App\Models\LocationProductOverride;
use App\Models\User;
use App\Security\Permission;

class LocationProductOverridePolicy
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
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::MENU_VIEW);
    }

    public function view(User $user, LocationProductOverride $override): bool
    {
        $vId = (int) $override->vendor_id;

        return $user->canAccessLocationId($override->location_id, $vId)
            && $user->hasPermission(Permission::MENU_VIEW, $vId);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::MENU_UPDATE);
    }

    public function update(User $user, LocationProductOverride $override): bool
    {
        $vId = (int) $override->vendor_id;

        return $user->canAccessLocationId($override->location_id, $vId)
            && $user->hasPermission(Permission::MENU_UPDATE, $vId);
    }

    public function delete(User $user, LocationProductOverride $override): bool
    {
        $vId = (int) $override->vendor_id;

        return $user->canAccessLocationId($override->location_id, $vId)
            && $user->hasPermission(Permission::MENU_UPDATE, $vId);
    }
}
