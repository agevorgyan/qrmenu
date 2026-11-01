<?php

namespace App\Http\Middleware;

use App\Security\Permission;
use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin() && $user->hasPermission(Permission::PLATFORM_ACCESS)) {
            return $next($request);
        }

        $userRoles = app(AuthorizationService::class)->getUserRoles($user);
        $userRoleSlugs = array_map(function ($r) {
            return is_string($r) ? $r : $r->slug;
        }, $userRoles);

        if (! empty($user->role) && ! in_array($user->role, $userRoleSlugs, true)) {
            $userRoleSlugs[] = $user->role;
        }

        if (! empty(array_intersect($roles, $userRoleSlugs))) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/api/*')) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHORIZED_ROLE',
                'message' => __('Unauthorized action for your assigned role.'),
            ], 403);
        }

        abort(403, __('Unauthorized action for your assigned role.'));
    }
}
