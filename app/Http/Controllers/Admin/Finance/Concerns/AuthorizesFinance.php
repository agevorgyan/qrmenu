<?php

namespace App\Http\Controllers\Admin\Finance\Concerns;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;

trait AuthorizesFinance
{
    /**
     * Authorize user against a required permission.
     */
    protected function authorizePermission(string $permission): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'Unauthorized financial action.');
        }
    }

    /**
     * Get the active vendor for the authenticated user.
     */
    protected function getActiveVendor(): Vendor
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user || ! $user->vendor) {
            abort(403, 'User is not associated with an active vendor organization.');
        }

        return $user->vendor;
    }
}
