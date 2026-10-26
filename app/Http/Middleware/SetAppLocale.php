<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use App\Services\Localization\LocaleManager;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    public function __construct(
        protected LocaleManager $localeManager,
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Storefront visitor requests (web and API)
        if ($request->is('m/*') || $request->is('api/m/*')) {
            $vendor = $this->resolveStorefrontVendor($request);
            if ($vendor) {
                $locale = $this->localeManager->resolveStorefrontLocale($request, $vendor);
            } else {
                $fallback = $this->localeManager->getSystemDefaultLocale();
                $param = $request->get('lang') ?? $request->get('locale') ?? session('app_locale') ?? session('locale');
                $locale = ($param && $this->localeManager->isValidLocale($param)) ? strtolower(trim($param)) : $fallback;
            }

            session(['app_locale' => $locale, 'locale' => $locale]);
            App::setLocale($locale);

            if ($request->cookie('app_locale') !== $locale) {
                cookie()->queue('app_locale', $locale, 525600);
                cookie()->queue('locale', $locale, 525600);
            }

            return $next($request);
        }

        // 2. Generic API endpoints
        if ($request->is('api/*')) {
            $param = $request->get('lang') ?? $request->get('locale') ?? $request->header('X-Locale');
            if ($param && $this->localeManager->isValidLocale($param)) {
                $locale = strtolower(trim($param));
            } else {
                $locale = $this->localeManager->parseAcceptLanguageHeader($request, $this->localeManager->getActiveLocales())
                    ?? $this->localeManager->getSystemDefaultLocale();
            }

            App::setLocale($locale);

            return $next($request);
        }

        // 3. Admin, SuperAdmin, Auth, and Web Platform views
        $adminLocale = $this->localeManager->resolveAdminLocale($request, Auth::user());
        session(['admin_locale' => $adminLocale, 'app_locale' => $adminLocale, 'locale' => $adminLocale]);
        App::setLocale($adminLocale);

        if (Auth::check()) {
            $user = Auth::user();
            if ($user && $user->locale !== $adminLocale) {
                $user->locale = $adminLocale;
                $user->saveQuietly();
            }
        }

        if ($request->cookie('app_locale') !== $adminLocale || $request->cookie('admin_locale') !== $adminLocale) {
            cookie()->queue('app_locale', $adminLocale, 525600);
            cookie()->queue('admin_locale', $adminLocale, 525600);
            cookie()->queue('locale', $adminLocale, 525600);
        }

        return $next($request);
    }

    /**
     * Resolve the active vendor for storefront requests.
     */
    protected function resolveStorefrontVendor(Request $request): ?Vendor
    {
        $tenant = $this->tenantContext->getTenant();
        if ($tenant) {
            return $tenant;
        }

        $cached = $request->attributes->get('resolved_vendor');
        if ($cached instanceof Vendor) {
            return $cached;
        }

        $slug = $request->route('vendor_slug') ?? $request->segment(2);
        if ($slug && is_string($slug)) {
            $vendor = Vendor::where('slug', $slug)
                ->with(['menuTemplate', 'locations', 'vendorLanguages.language'])
                ->first();
            if ($vendor) {
                $request->attributes->set('resolved_vendor', $vendor);
            }

            return $vendor;
        }

        return null;
    }
}
