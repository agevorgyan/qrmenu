<?php

namespace App\Policies;

use App\Models\RestaurantTable;
use App\Models\User;
use App\Security\Permission;

class RestaurantTablePolicy
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
        return ! empty($user->vendor_id)
            && ($user->hasPermission(Permission::LOCATIONS_VIEW) || $user->hasPermission(Permission::ORDERS_VIEW) || $user->hasPermission(Permission::MENU_VIEW));
    }

    public function view(User $user, RestaurantTable $table): bool
    {
        return (int) $user->vendor_id === (int) $table->vendor_id
            && $user->canAccessLocationId($table->location_id)
            && ($user->hasPermission(Permission::LOCATIONS_VIEW) || $user->hasPermission(Permission::ORDERS_VIEW) || $user->hasPermission(Permission::MENU_VIEW));
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id)
            && ($user->hasPermission(Permission::LOCATIONS_MANAGE) || $user->hasPermission(Permission::MENU_CREATE) || $user->hasPermission(Permission::SETTINGS_MANAGE));
    }

    public function update(User $user, RestaurantTable $table): bool
    {
        return (int) $user->vendor_id === (int) $table->vendor_id
            && $user->canAccessLocationId($table->location_id)
            && ($user->hasPermission(Permission::LOCATIONS_MANAGE) || $user->hasPermission(Permission::MENU_UPDATE) || $user->hasPermission(Permission::SETTINGS_MANAGE));
    }

    public function delete(User $user, RestaurantTable $table): bool
    {
        return (int) $user->vendor_id === (int) $table->vendor_id
            && $user->canAccessLocationId($table->location_id)
            && ($user->hasPermission(Permission::LOCATIONS_MANAGE) || $user->hasPermission(Permission::MENU_DELETE) || $user->hasPermission(Permission::SETTINGS_MANAGE));
    }
}
