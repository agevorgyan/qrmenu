<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use App\Services\EntitlementService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanHasFeature
{
    public function __construct(
        protected EntitlementService $entitlementService,
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        $vendor = null;

        // 1. If this is a storefront route with vendor_slug, route parameter is authoritative
        $vendorSlug = $request->route('vendor_slug') ?? $request->route('vendor');
        if ($vendorSlug) {
            $vendor = ($vendorSlug instanceof Vendor)
                ? $vendorSlug
                : Vendor::where('slug', (string) $vendorSlug)->first();

            // Storefront routes for missing or inactive restaurants must 404
            if (! $vendor || ! $vendor->is_active) {
                abort(404, 'Restaurant not found.');
            }
        } else {
            // 2. Admin panel context: resolve from authenticated user or TenantContext
            $vendor = $user?->vendor ?? $this->tenantContext->getTenant();
        }

        if ($vendor) {
            $result = $this->entitlementService->check($vendor, $feature, $user);

            if (! $result->allowed) {
                if ($request->expectsJson() || $request->is('api/*') || $request->is('*/feed')) {
                    return response()->json($result->toArray(), 403);
                }

                return redirect()->route('admin.subscription')
                    ->with('warning', $result->message);
            }
        }

        return $next($request);
    }
}
