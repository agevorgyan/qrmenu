<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Models\Vendor;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdentifyTenant
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Resolve custom domain (if request host is not the main platform host)
        $host = strtolower($request->getHost());
        $mainHost = strtolower(parse_url(config('app.url', 'https://menu.elab.am'), PHP_URL_HOST) ?? '');

        $isMainPlatformHost = empty($host)
            || $host === $mainHost
            || in_array($host, ['localhost', '127.0.0.1', 'menu.elab.am', 'qrmenu.local']);

        $customDomainVendor = null;

        if (! $isMainPlatformHost) {
            $cleanHost = preg_replace('/^www\./i', '', $host);

            // 1. Query CustomDomain entity (only ACTIVE status routes traffic)
            $customDomain = CustomDomain::withoutGlobalScopes()
                ->with('vendor')
                ->where(function ($q) use ($host, $cleanHost) {
                    $q->where('normalized_domain', $host)
                        ->orWhere('normalized_domain', $cleanHost)
                        ->orWhere('normalized_domain', 'www.'.$cleanHost);
                })
                ->where('status', CustomDomain::STATUS_ACTIVE)
                ->first();

            if ($customDomain && $customDomain->vendor && $customDomain->vendor->is_active) {
                $customDomainVendor = $customDomain->vendor;
            } else {
                // If domain exists in custom_domains but is not active (pending/suspended/failed), block routing
                $inactiveExists = CustomDomain::withoutGlobalScopes()
                    ->where(function ($q) use ($host, $cleanHost) {
                        $q->where('normalized_domain', $host)
                            ->orWhere('normalized_domain', $cleanHost)
                            ->orWhere('normalized_domain', 'www.'.$cleanHost);
                    })
                    ->where('status', '!=', CustomDomain::STATUS_ACTIVE)
                    ->exists();

                if (! $inactiveExists) {
                    // Fallback to legacy vendors.custom_domain for backwards compatibility
                    $customDomainVendor = Vendor::where(function ($q) use ($host, $cleanHost) {
                        $q->where('custom_domain', $host)
                            ->orWhere('custom_domain', $cleanHost)
                            ->orWhere('custom_domain', 'www.'.$cleanHost);
                    })->where('is_active', true)->first();
                }
            }

            if ($customDomainVendor) {
                $this->tenantContext->setTenant($customDomainVendor);
                $this->tenantContext->setCustomDomainHost($host);

                // Disallow platform superadmin panel access via vendor's private custom domain
                if ($request->is('superadmin*')) {
                    $platformUrl = rtrim(config('app.url', 'https://menu.elab.am'), '/');

                    return redirect()->to($platformUrl.'/'.$request->path());
                }

                // If user is already authenticated on this custom domain, ensure they belong to this vendor
                if (Auth::check()) {
                    $user = Auth::user();
                    if (! $user->isSuperAdmin() && (int) $user->vendor_id !== (int) $customDomainVendor->id) {
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return redirect()->route('login')->withErrors([
                            'email' => "Այս կառավարման վահանակը նախատեսված է միայն {$customDomainVendor->name} ռեստորանի անձնակազմի համար։",
                        ]);
                    }
                }

                return $next($request);
            }
        }

        // 2. Default: Check for storefront vendor_slug in route parameters
        $slug = $request->route('vendor_slug');
        if ($slug) {
            $cached = $request->attributes->get('resolved_vendor');
            if ($cached instanceof Vendor && $cached->slug === $slug) {
                $vendor = $cached;
            } else {
                $vendor = Vendor::where('slug', $slug)
                    ->with(['menuTemplate', 'locations', 'vendorLanguages.language'])
                    ->first();
                if ($vendor) {
                    $request->attributes->set('resolved_vendor', $vendor);
                }
            }
            if ($vendor) {
                $this->tenantContext->setTenant($vendor);
            }
        } elseif (Auth::check()) {
            $user = Auth::user();
            if ($user && $user->role === 'superadmin') {
                // SuperAdmin platform administrative context
                $this->tenantContext->clear();
            } elseif ($user && $user->vendor_id) {
                $this->tenantContext->setTenantId((int) $user->vendor_id);
            }
        }

        return $next($request);
    }
}
