<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Security\Permission;

class CategoryPolicy
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

    public function view(User $user, Category $category): bool
    {
        return (int) $user->vendor_id === (int) $category->vendor_id
            && $user->hasPermission(Permission::MENU_VIEW);
    }

    public function create(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::MENU_CREATE);
    }

    public function update(User $user, Category $category): bool
    {
        return (int) $user->vendor_id === (int) $category->vendor_id
            && $user->hasPermission(Permission::MENU_UPDATE);
    }

    public function reorder(User $user): bool
    {
        return ! empty($user->vendor_id) && $user->hasPermission(Permission::MENU_UPDATE);
    }

    public function delete(User $user, Category $category): bool
    {
        return (int) $user->vendor_id === (int) $category->vendor_id
            && $user->hasPermission(Permission::MENU_DELETE);
    }
}
