<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaiterCall;
use App\Security\Permission;

class WaiterCallPolicy
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
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::ORDERS_VIEW);
    }

    public function view(User $user, WaiterCall $waiterCall): bool
    {
        return (int) $user->vendor_id === (int) $waiterCall->vendor_id
            && $user->canAccessLocationId($waiterCall->location_id)
            && $user->hasPermission(Permission::ORDERS_VIEW);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::ORDERS_UPDATE);
    }

    public function update(User $user, WaiterCall $waiterCall): bool
    {
        return (int) $user->vendor_id === (int) $waiterCall->vendor_id
            && $user->canAccessLocationId($waiterCall->location_id)
            && ($user->hasPermission(Permission::ORDERS_UPDATE) || $user->hasPermission(Permission::ORDERS_UPDATE_OWN) || $user->hasPermission(Permission::POS_ORDERS) || $user->hasPermission(Permission::WAITERS_MANAGE));
    }

    public function delete(User $user, WaiterCall $waiterCall): bool
    {
        return (int) $user->vendor_id === (int) $waiterCall->vendor_id
            && $user->canAccessLocationId($waiterCall->location_id)
            && $user->hasPermission(Permission::ORDERS_CANCEL);
    }
}
