<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Security\Permission;

class ReservationPolicy
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

    public function view(User $user, Reservation $reservation): bool
    {
        return (int) $user->vendor_id === (int) $reservation->vendor_id
            && $user->canAccessLocationId($reservation->location_id)
            && $user->hasPermission(Permission::ORDERS_VIEW);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::ORDERS_UPDATE);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return (int) $user->vendor_id === (int) $reservation->vendor_id
            && $user->canAccessLocationId($reservation->location_id)
            && $user->hasPermission(Permission::ORDERS_UPDATE);
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return (int) $user->vendor_id === (int) $reservation->vendor_id
            && $user->canAccessLocationId($reservation->location_id)
            && ($user->hasPermission(Permission::ORDERS_CANCEL) || $user->hasPermission(Permission::ORDERS_UPDATE));
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return (int) $user->vendor_id === (int) $reservation->vendor_id
            && $user->canAccessLocationId($reservation->location_id)
            && ($user->hasPermission(Permission::ORDERS_CANCEL) || $user->hasPermission(Permission::ORDERS_UPDATE));
    }
}
