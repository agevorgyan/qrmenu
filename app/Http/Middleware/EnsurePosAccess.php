<?php

namespace App\Http\Middleware;

use App\Security\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson() || $request->is('pos/api/*') || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Please sign in to access the POS terminal.',
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        // SuperAdmin with platform access is always authorized
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $vendor = $user->vendor;
        if (! $vendor) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'NO_VENDOR',
                    'message' => 'User is not associated with any restaurant account.',
                ], 403);
            }

            abort(403, 'User is not associated with any restaurant account.');
        }

        // Feature entitlement check
        if (! $vendor->hasFeature('pos.access') && ! $vendor->hasFeature('pos')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'FEATURE_NOT_ENTITLED',
                    'message' => 'POS Terminal module is not enabled for your subscription plan.',
                ], 403);
            }

            abort(403, 'POS Terminal module is not enabled for your subscription plan.');
        }

        // User role / permission check
        if (! $user->hasPermission(Permission::POS_ACCESS)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'PERMISSION_DENIED',
                    'message' => 'Your staff role does not have permission to access the POS.',
                ], 403);
            }

            abort(403, 'Your staff role does not have permission to access the POS.');
        }

        return $next($request);
    }
}
