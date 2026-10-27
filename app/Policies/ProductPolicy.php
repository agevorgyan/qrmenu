<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Security\Permission;

class ProductPolicy
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

    public function view(User $user, Product $product): bool
    {
        return (int) $user->vendor_id === (int) $product->vendor_id
            && $user->hasPermission(Permission::MENU_VIEW);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::MENU_CREATE);
    }

    public function update(User $user, Product $product): bool
    {
        return (int) $user->vendor_id === (int) $product->vendor_id
            && $user->hasPermission(Permission::MENU_UPDATE);
    }

    public function delete(User $user, Product $product): bool
    {
        return (int) $user->vendor_id === (int) $product->vendor_id
            && $user->hasPermission(Permission::MENU_DELETE);
    }

    public function bulkUpdate(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::MENU_UPDATE);
    }

    public function bulkDelete(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::MENU_DELETE);
    }

    public function export(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::REPORTS_EXPORT);
    }
}
