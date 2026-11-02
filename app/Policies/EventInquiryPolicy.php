<?php

namespace App\Policies;

use App\Models\EventInquiry;
use App\Models\User;
use App\Security\Permission;

class EventInquiryPolicy
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

    public function view(User $user, EventInquiry $inquiry): bool
    {
        $vId = (int) $inquiry->vendor_id;

        return $user->canAccessLocationId($inquiry->location_id, $vId)
            && $user->hasPermission(Permission::ORDERS_VIEW, $vId);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::ORDERS_UPDATE);
    }

    public function update(User $user, EventInquiry $inquiry): bool
    {
        $vId = (int) $inquiry->vendor_id;

        return $user->canAccessLocationId($inquiry->location_id, $vId)
            && $user->hasPermission(Permission::ORDERS_UPDATE, $vId);
    }

    public function delete(User $user, EventInquiry $inquiry): bool
    {
        $vId = (int) $inquiry->vendor_id;

        return $user->canAccessLocationId($inquiry->location_id, $vId)
            && ($user->hasPermission(Permission::ORDERS_CANCEL, $vId) || $user->hasPermission(Permission::ORDERS_UPDATE, $vId));
    }
}
