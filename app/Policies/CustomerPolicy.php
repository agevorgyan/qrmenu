<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Security\Permission;

class CustomerPolicy
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
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::CUSTOMERS_VIEW);
    }

    public function view(User $user, Customer $customer): bool
    {
        $vId = (int) $customer->vendor_id;

        return $user->canAccessLocationId($customer->location_id, $vId)
            && $user->hasPermission(Permission::CUSTOMERS_VIEW, $vId);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::CUSTOMERS_VIEW);
    }

    public function update(User $user, Customer $customer): bool
    {
        $vId = (int) $customer->vendor_id;

        return $user->canAccessLocationId($customer->location_id, $vId)
            && $user->hasPermission(Permission::CUSTOMERS_VIEW, $vId);
    }

    public function delete(User $user, Customer $customer): bool
    {
        $vId = (int) $customer->vendor_id;

        return $user->hasPermission(Permission::CUSTOMERS_EXPORT, $vId);
    }

    public function export(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::CUSTOMERS_EXPORT);
    }
}
