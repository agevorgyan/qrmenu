<?php

namespace App\Domains\Wholesale\Http\Middleware;

use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Models\Vendor;
use App\Security\Permission;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWholesaleAccess
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        // 1. Must be authenticated
        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'UNAUTHENTICATED',
                    'message' => __('Authentication is required to access wholesale services.'),
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        // SuperAdmin with explicit platform access bypasses tenant entitlement check only for superadmin routes
        if ($user->isSuperAdmin() && $request->is('superadmin*')) {
            return $next($request);
        }

        // 2. Resolve tenant: user must belong to a vendor
        $vendor = $user->vendor ?? $this->tenantContext->getTenant();
        if (! $vendor) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'NO_TENANT',
                    'message' => __('No active tenant context associated with this user account.'),
                ], 403);
            }

            abort(403, __('No active tenant context associated with this user account.'));
        }

        // Ensure TenantContext is populated
        $this->tenantContext->setTenant($vendor);

        // 3. Centralized Super Admin Entitlement Gate: tenant must have valid, active, non-expired wholesale_pos entitlement
        if (! WholesaleAccessService::isEntitled($vendor)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'WHOLESALE_NOT_ENTITLED',
                    'message' => __('Wholesale POS & Distribution module is not enabled for this restaurant. Access must be granted by a platform Super Administrator.'),
                ], 403);
            }

            abort(403, __('Wholesale POS & Distribution module is not enabled for this restaurant. Access must be granted by a platform Super Administrator.'));
        }

        // 4. Role & Permission check
        if (! empty($permission)) {
            if (! $user->hasPermission($permission) && ! $user->isSuperAdmin()) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'code' => 'PERMISSION_DENIED',
                        'message' => __('Your staff role does not have permission for this wholesale operation (:perm).', ['perm' => $permission]),
                    ], 403);
                }

                abort(403, __('Your staff role does not have permission for this wholesale operation (:perm).', ['perm' => $permission]));
            }
        } else {
            // General wholesale access requirement
            $hasAnyWholesalePermission = $user->isSuperAdmin()
                || $user->hasPermission(Permission::WHOLESALE_POS_ACCESS)
                || $user->hasPermission(Permission::WHOLESALE_ORDERS_VIEW)
                || $user->hasPermission(Permission::WHOLESALE_SELLERS_MANAGE)
                || $user->hasPermission(Permission::WHOLESALE_CATALOG_VIEW)
                || $user->role === 'vendor_owner';

            if (! $hasAnyWholesalePermission) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'code' => 'PERMISSION_DENIED',
                        'message' => __('You do not have permission to access the Wholesale subsystem.'),
                    ], 403);
                }

                abort(403, __('You do not have permission to access the Wholesale subsystem.'));
            }
        }

        return $next($request);
    }
}

