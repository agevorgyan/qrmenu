<?php

namespace App\Services\Localization;

use App\Models\Language;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LocaleManager
{
    public const CACHE_KEY_LANGUAGES = 'i18n:active_languages';

    public const CACHE_TTL_SECONDS = 86400; // 24 hours

    /**
     * Get all active system languages.
     *
     * @return Collection<int, Language>
     */
    public function getActiveLanguages(): Collection
    {
        class_exists(Language::class);

        try {
            $cached = Cache::get(self::CACHE_KEY_LANGUAGES);

            if (is_array($cached)) {
                return Language::hydrate($cached);
            }

            if ($cached instanceof Collection && ($cached->isEmpty() || $cached->first() instanceof Language)) {
                return $cached;
            }

            $languages = Language::query()->active()->get();

            if ($languages->isNotEmpty()) {
                Cache::put(self::CACHE_KEY_LANGUAGES, $languages->toArray(), self::CACHE_TTL_SECONDS);
            }

            return $languages;
        } catch (\Throwable $e) {
            Cache::forget(self::CACHE_KEY_LANGUAGES);

            return collect([
                new Language([
                    'id' => 1,
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                    'flag' => '🇬🇧',
                    'direction' => 'ltr',
                    'is_active' => true,
                    'is_default' => true,
                ]),
            ]);
        }
    }

    /**
     * Get all active language codes.
     *
     * @return array<int, string>
     */
    public function getActiveLocales(): array
    {
        return $this->getActiveLanguages()->pluck('code')->all();
    }

    /**
     * Find a language model by its code.
     */
    public function getLanguageByCode(string $code): ?Language
    {
        $code = strtolower(trim($code));

        return $this->getActiveLanguages()->firstWhere('code', $code);
    }

    /**
     * Check if a given locale code is an active system language.
     */
    public function isValidLocale(string $code): bool
    {
        return in_array(strtolower(trim($code)), $this->getActiveLocales(), true);
    }

    /**
     * Get the system default language.
     */
    public function getSystemDefaultLanguage(): Language
    {
        $default = $this->getActiveLanguages()->firstWhere('is_default', true);

        if ($default) {
            return $default;
        }

        $fallback = $this->getLanguageByCode('en');
        if ($fallback) {
            return $fallback;
        }

        // Return in-memory fallback if database has not yet been seeded
        $lang = new Language([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'flag' => '🇬🇧',
            'direction' => 'ltr',
            'is_active' => true,
            'is_default' => true,
        ]);
        $lang->id = 1;

        return $lang;
    }

    /**
     * Get the system default locale code.
     */
    public function getSystemDefaultLocale(): string
    {
        return $this->getSystemDefaultLanguage()->code;
    }

    /**
     * Get the fallback locale code.
     */
    public function getFallbackLocale(): string
    {
        return config('app.fallback_locale', 'en');
    }

    /**
     * Clear cached languages list.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_LANGUAGES);
    }

    /**
     * Get configured and active languages for a specific vendor.
     *
     * @return array<int, array{code: string, name: string, native_name: string, flag: string, direction: string, is_default: bool}>
     */
    public function getVendorLanguages(Vendor $vendor): array
    {
        return $vendor->getSupportedLanguages();
    }

    /**
     * Get active locale codes for a vendor.
     *
     * @return array<int, string>
     */
    public function getVendorLocales(Vendor $vendor): array
    {
        $languages = $this->getVendorLanguages($vendor);

        return array_values(array_filter(array_map(
            fn ($l) => is_array($l) ? strtolower((string) ($l['code'] ?? '')) : strtolower((string) $l),
            $languages
        )));
    }

    /**
     * Get default locale code for a vendor.
     */
    public function getVendorDefaultLocale(Vendor $vendor): string
    {
        return $vendor->getDefaultLanguageCode();
    }

    /**
     * Check if a locale code is active and supported for a vendor.
     */
    public function isValidVendorLocale(string $code, Vendor $vendor): bool
    {
        return in_array(strtolower(trim($code)), $this->getVendorLocales($vendor), true);
    }

    /**
     * Resolve storefront locale from request and vendor configuration.
     */
    public function resolveStorefrontLocale(Request $request, Vendor $vendor): string
    {
        $vendorLocales = $this->getVendorLocales($vendor);
        $defaultLocale = $this->getVendorDefaultLocale($vendor);

        // 1. Explicit query parameter ?lang= or ?locale=
        $param = $request->get('lang') ?? $request->get('locale');
        if ($param && is_string($param)) {
            $candidate = strtolower(trim($param));
            if (in_array($candidate, $vendorLocales, true)) {
                return $candidate;
            }
        }

        // 2. Guest session or cookie
        $sessionLocale = session('app_locale') ?? session('locale') ?? $request->cookie('app_locale') ?? $request->cookie('locale');
        if ($sessionLocale && is_string($sessionLocale)) {
            $candidate = strtolower(trim($sessionLocale));
            if (in_array($candidate, $vendorLocales, true)) {
                return $candidate;
            }
        }

        // 3. Browser Accept-Language header matching vendor languages
        $headerLocale = $this->parseAcceptLanguageHeader($request, $vendorLocales);
        if ($headerLocale) {
            return $headerLocale;
        }

        // 4. Vendor default locale
        return $defaultLocale;
    }

    /**
     * Resolve administrative/backoffice locale from request or user profile.
     */
    public function resolveAdminLocale(Request $request, ?User $user = null): string
    {
        $activeLocales = $this->getActiveLocales();
        $defaultLocale = $this->getSystemDefaultLocale();

        // 1. Explicit query parameter
        $param = $request->get('lang') ?? $request->get('locale');
        if ($param && is_string($param)) {
            $candidate = strtolower(trim($param));
            if (in_array($candidate, $activeLocales, true)) {
                return $candidate;
            }
        }

        // 2. Session
        $sessionLocale = session('admin_locale') ?? session('app_locale') ?? session('locale');
        if ($sessionLocale && is_string($sessionLocale)) {
            $candidate = strtolower(trim($sessionLocale));
            if (in_array($candidate, $activeLocales, true)) {
                return $candidate;
            }
        }

        // 3. User preference if saved
        if ($user && ! empty($user->locale) && in_array(strtolower($user->locale), $activeLocales, true)) {
            return strtolower($user->locale);
        }

        // 4. Long-lived cookie preference (survives session restarts, browser refreshes, and logout)
        $cookieLocale = $request->cookie('admin_locale') ?? $request->cookie('app_locale') ?? $request->cookie('locale');
        if ($cookieLocale && is_string($cookieLocale)) {
            $candidate = strtolower(trim($cookieLocale));
            if (in_array($candidate, $activeLocales, true)) {
                return $candidate;
            }
        }

        return $defaultLocale;
    }

    /**
     * Parse Accept-Language header from request.
     */
    public function parseAcceptLanguageHeader(Request $request, array $allowedLocales): ?string
    {
        $header = $request->header('Accept-Language');
        if (! $header) {
            return null;
        }

        $languages = explode(',', $header);
        foreach ($languages as $lang) {
            $parts = explode(';', trim($lang));
            $code = strtolower(substr(trim($parts[0]), 0, 2));
            if (in_array($code, $allowedLocales, true)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Format a currency amount according to the given locale and currency symbol.
     */
    public function formatCurrency(float $amount, string $currency = 'AMD', ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $formattedNumber = number_format($amount, 0, '.', ',');

        return match (strtoupper($currency)) {
            'AMD', '֏' => "{$formattedNumber} ֏",
            'USD' => "\${$formattedNumber}",
            'EUR' => "€{$formattedNumber}",
            'RUB' => "{$formattedNumber} ₽",
            'GEL' => "{$formattedNumber} ₾",
            'GBP' => "£{$formattedNumber}",
            'AED' => "{$formattedNumber} د.إ",
            default => "{$formattedNumber} {$currency}",
        };
    }

    /**
     * Format a date according to the given locale.
     */
    public function formatDate(mixed $date, ?string $format = null, ?string $locale = null): string
    {
        if (! $date) {
            return '';
        }

        $locale = $locale ?: app()->getLocale();
        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        if ($format) {
            return $carbon->locale($locale)->isoFormat($format);
        }

        return match ($locale) {
            'hy' => $carbon->locale('hy')->isoFormat('D MMMM, YYYY'),
            'ru' => $carbon->locale('ru')->isoFormat('D MMMM YYYY г.'),
            default => $carbon->locale('en')->isoFormat('MMM D, YYYY'),
        };
    }
}
