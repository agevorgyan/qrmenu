<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Security\Permission;

class OrderPolicy
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
        return ! empty($user->vendor_id) && (
            $user->hasPermission(Permission::ORDERS_VIEW)
            || $user->hasPermission(Permission::ORDERS_VIEW_ALL)
            || $user->hasPermission(Permission::ORDERS_VIEW_OWN)
            || $user->hasPermission(Permission::POS_ORDERS)
        );
    }

    public function view(User $user, Order $order): bool
    {
        return $user->canAccessOrder($order);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && ($user->hasPermission(Permission::ORDERS_UPDATE) || $user->hasPermission(Permission::ORDERS_CREATE) || $user->hasPermission(Permission::POS_ORDERS));
    }

    public function update(User $user, Order $order): bool
    {
        if ((int) $user->vendor_id !== (int) $order->vendor_id || ! $user->canAccessLocationId($order->location_id)) {
            return false;
        }

        if ($user->hasPermission(Permission::ORDERS_UPDATE) || $user->hasPermission(Permission::ORDERS_MANAGE) || $user->hasPermission(Permission::POS_ORDERS)) {
            return true;
        }

        if ($user->hasPermission(Permission::ORDERS_UPDATE_OWN)) {
            $isAssignedWaiter = ! empty($order->waiter_id) && (int) $order->waiter_id === (int) $user->id;
            $isOrderCreator = ! empty($order->user_id) && (int) $order->user_id === (int) $user->id;

            return $isAssignedWaiter || $isOrderCreator;
        }

        return false;
    }

    public function cancel(User $user, Order $order): bool
    {
        return (int) $user->vendor_id === (int) $order->vendor_id
            && $user->canAccessLocationId($order->location_id)
            && $user->hasPermission(Permission::ORDERS_CANCEL);
    }

    public function refund(User $user, Order $order): bool
    {
        return (int) $user->vendor_id === (int) $order->vendor_id
            && $user->canAccessLocationId($order->location_id)
            && $user->hasPermission(Permission::ORDERS_REFUND);
    }

    public function delete(User $user, Order $order): bool
    {
        return (int) $user->vendor_id === (int) $order->vendor_id
            && $user->canAccessLocationId($order->location_id)
            && $user->hasPermission(Permission::ORDERS_CANCEL);
    }

    public function export(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::REPORTS_EXPORT);
    }
}
