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
        $vId = (int) $waiterCall->vendor_id;

        return $user->canAccessLocationId($waiterCall->location_id, $vId)
            && $user->hasPermission(Permission::ORDERS_VIEW, $vId);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::ORDERS_UPDATE);
    }

    public function update(User $user, WaiterCall $waiterCall): bool
    {
        $vId = (int) $waiterCall->vendor_id;

        return $user->canAccessLocationId($waiterCall->location_id, $vId)
            && ($user->hasPermission(Permission::ORDERS_UPDATE, $vId) || $user->hasPermission(Permission::ORDERS_UPDATE_OWN, $vId) || $user->hasPermission(Permission::POS_ORDERS, $vId) || $user->hasPermission(Permission::WAITERS_MANAGE, $vId));
    }

    public function delete(User $user, WaiterCall $waiterCall): bool
    {
        $vId = (int) $waiterCall->vendor_id;

        return $user->canAccessLocationId($waiterCall->location_id, $vId)
            && $user->hasPermission(Permission::ORDERS_CANCEL, $vId);
    }
}
