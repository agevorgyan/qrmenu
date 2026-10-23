<?php

namespace App\Policies;

use App\Models\ReservationPreOrder;
use App\Models\User;
use App\Security\Permission;

class ReservationPreOrderPolicy
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

    public function view(User $user, ReservationPreOrder $preOrder): bool
    {
        return (int) $user->vendor_id === (int) $preOrder->vendor_id
            && $user->hasPermission(Permission::ORDERS_VIEW);
    }

    public function update(User $user, ReservationPreOrder $preOrder): bool
    {
        return (int) $user->vendor_id === (int) $preOrder->vendor_id
            && $user->hasPermission(Permission::ORDERS_UPDATE);
    }

    public function delete(User $user, ReservationPreOrder $preOrder): bool
    {
        return (int) $user->vendor_id === (int) $preOrder->vendor_id
            && ($user->hasPermission(Permission::ORDERS_CANCEL) || $user->hasPermission(Permission::ORDERS_UPDATE));
    }
}
