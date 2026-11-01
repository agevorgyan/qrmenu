<?php

namespace App\Http\Middleware;

use App\Security\Permission;
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

        if (in_array($user->role, $roles, true) || ($user->isSuperAdmin() && $user->hasPermission(Permission::PLATFORM_ACCESS))) {
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
