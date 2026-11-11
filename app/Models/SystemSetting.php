<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public const CACHE_KEY = 'system_settings_all';

    public const CACHE_TTL = 86400; // 24 hours

    /** @var array<string, string|null>|null */
    protected static ?array $memoizedAll = null;

    protected static function booted(): void
    {
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }

    public static function clearCache(): void
    {
        static::$memoizedAll = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Retrieve all settings as key => value associative array with caching.
     *
     * @return array<string, string|null>
     */
    public static function getAll(): array
    {
        if (static::$memoizedAll !== null) {
            return static::$memoizedAll;
        }

        static::$memoizedAll = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::pluck('value', 'key')->toArray();
        });

        return static::$memoizedAll;
    }

    /**
     * Retrieve a specific setting value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::getAll();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Save or update a setting.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );

        static::clearCache();

        return $setting;
    }

    /**
     * Get system logo for light backgrounds.
     */
    public static function getLogoLight(): string
    {
        $val = static::get('site_logo_light');
        if (! empty($val)) {
            return str_starts_with($val, 'http') ? $val : asset(ltrim($val, '/'));
        }

        return asset('images/branding/logo-light.png');
    }

    /**
     * Get system logo for dark backgrounds.
     */
    public static function getLogoDark(): string
    {
        $val = static::get('site_logo_dark');
        if (! empty($val)) {
            return str_starts_with($val, 'http') ? $val : asset(ltrim($val, '/'));
        }

        return asset('images/branding/logo-dark.png');
    }

    /**
     * Get system favicon URL.
     */
    public static function getFavicon(): string
    {
        $val = static::get('site_favicon');
        if (! empty($val)) {
            return str_starts_with($val, 'http') ? $val : asset(ltrim($val, '/'));
        }

        return asset('images/branding/favicon.png');
    }

    /**
     * Get the configured site/platform name.
     */
    public static function getSiteName(): string
    {
        return static::get('site_name') ?: config('app.name', 'menu by eLab');
    }

    /**
     * Get the configured site tagline/subtitle (supports locale).
     */
    public static function getSiteTagline(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if ($locale === 'ru' && ! empty(static::get('site_tagline_ru'))) {
            return static::get('site_tagline_ru');
        }
        if ($locale === 'en' && ! empty(static::get('site_tagline_en'))) {
            return static::get('site_tagline_en');
        }
        if ($locale === 'hy' && ! empty(static::get('site_tagline_hy'))) {
            return static::get('site_tagline_hy');
        }

        $tagline = static::get('site_tagline');
        if (! empty($tagline)) {
            return __($tagline);
        }

        return __('Խելացի Ռեստորանային QR Մենյու & Պատվերների Համակարգ');
    }

    /**
     * Get SEO Title (supports locale).
     */
    public static function getSeoTitle(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if ($locale === 'en' && ! empty(static::get('seo_title_en'))) {
            return static::get('seo_title_en');
        }

        return static::get('seo_title') ?: (static::getSiteName().' — '.static::getSiteTagline());
    }

    /**
     * Get SEO Meta Description (supports locale).
     */
    public static function getSeoDescription(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if ($locale === 'en' && ! empty(static::get('seo_description_en'))) {
            return static::get('seo_description_en');
        }

        return static::get('seo_description') ?: 'Ժամանակակից ինտերակտիվ QR մենյու, սեղանից պատվերներ, մատուցողի կանչ և օնլայն վճարումներ ռեստորանների և սրճարանների համար։';
    }

    /**
     * Get SEO Keywords.
     */
    public static function getSeoKeywords(): string
    {
        return static::get('seo_keywords') ?: 'qr menu, qrmenu, qr menyu, restaurant menu, elab menu, ռեստորանային մենյու';
    }

    /**
     * Get OpenGraph / Social preview image.
     */
    public static function getOgImage(): string
    {
        $val = static::get('seo_og_image');
        if (! empty($val)) {
            return str_starts_with($val, 'http') ? $val : asset(ltrim($val, '/'));
        }

        return static::getLogoDark();
    }

    /**
     * Get platform payment gateway settings with defaults.
     *
     * @return array<string, mixed>
     */
    public static function getPaymentGatewaySettings(): array
    {
        $all = static::getAll();

        return [
            'idram' => [
                'enabled' => filter_var($all['payment_idram_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'merchant_id' => $all['payment_idram_merchant_id'] ?? '',
                'secret_key' => $all['payment_idram_secret_key'] ?? '',
                'sandbox' => filter_var($all['payment_idram_sandbox'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'telcell' => [
                'enabled' => filter_var($all['payment_telcell_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'shop_id' => $all['payment_telcell_shop_id'] ?? '',
                'key' => $all['payment_telcell_key'] ?? '',
                'sandbox' => filter_var($all['payment_telcell_sandbox'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'fastshift' => [
                'enabled' => filter_var($all['payment_fastshift_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'merchant_id' => $all['payment_fastshift_merchant_id'] ?? '',
                'api_key' => $all['payment_fastshift_api_key'] ?? '',
                'sandbox' => filter_var($all['payment_fastshift_sandbox'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'arca' => [
                'enabled' => filter_var($all['payment_arca_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'merchant_id' => $all['payment_arca_merchant_id'] ?? '',
                'terminal_id' => $all['payment_arca_terminal_id'] ?? '',
                'username' => $all['payment_arca_username'] ?? '',
                'password' => $all['payment_arca_password'] ?? '',
                'sandbox' => filter_var($all['payment_arca_sandbox'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'stripe' => [
                'enabled' => filter_var($all['payment_stripe_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'publishable_key' => $all['payment_stripe_publishable_key'] ?? '',
                'secret_key' => $all['payment_stripe_secret_key'] ?? '',
                'webhook_secret' => $all['payment_stripe_webhook_secret'] ?? '',
                'sandbox' => filter_var($all['payment_stripe_sandbox'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'bank_transfer' => [
                'enabled' => filter_var($all['payment_bank_transfer_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'company_name' => filled($all['payment_bank_transfer_company_name'] ?? null) ? $all['payment_bank_transfer_company_name'] : '«Մենյու ԷյԷմ» ՍՊԸ',
                'tin' => filled($all['payment_bank_transfer_tin'] ?? null) ? $all['payment_bank_transfer_tin'] : '02893072',
                'bank_name' => filled($all['payment_bank_transfer_bank_name'] ?? null) ? $all['payment_bank_transfer_bank_name'] : '«Ամերիաբանկ» ՓԲԸ',
                'account_number' => filled($all['payment_bank_transfer_account_number'] ?? null) ? $all['payment_bank_transfer_account_number'] : '1570055555550100',
                'notes' => $all['payment_bank_transfer_notes'] ?? 'Վճարման նպատակ՝ QR Menu բաժանորդագրություն (նշել հաշվի համարը)',
            ],
        ];
    }

    /**
     * Get list of active payment methods for subscription renewals.
     * Only returns gateways that Superadmin has enabled.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getActiveSubscriptionPaymentMethods(): array
    {
        $all = static::getPaymentGatewaySettings();
        $definitions = [
            'bank_transfer' => [
                'code' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'icon' => 'fa-solid fa-building-columns',
                'color' => '#10b981',
            ],
            'arca' => [
                'code' => 'arca',
                'name' => 'ArCa / Ameriabank',
                'icon' => 'fa-solid fa-credit-card',
                'color' => '#2563eb',
            ],
            'idram' => [
                'code' => 'idram',
                'name' => 'Idram Wallet / QR',
                'icon' => 'fa-solid fa-wallet',
                'color' => '#f97316',
            ],
            'telcell' => [
                'code' => 'telcell',
                'name' => 'Telcell Wallet',
                'icon' => 'fa-solid fa-mobile-screen-button',
                'color' => '#eab308',
            ],
            'fastshift' => [
                'code' => 'fastshift',
                'name' => 'FastShift',
                'icon' => 'fa-solid fa-bolt',
                'color' => '#06b6d4',
            ],
            'stripe' => [
                'code' => 'stripe',
                'name' => 'Stripe (Cards)',
                'icon' => 'fa-brands fa-stripe',
                'color' => '#6366f1',
            ],
        ];

        $active = [];
        foreach ($definitions as $code => $def) {
            if (! empty($all[$code]['enabled'])) {
                $def['name'] = __($def['name']);
                $active[$code] = $def;
            }
        }

        // If none are explicitly enabled, default to bank_transfer fallback
        if (empty($active)) {
            $def = $definitions['bank_transfer'];
            $def['name'] = __($def['name']);
            $active['bank_transfer'] = $def;
        }

        return $active;
    }
}
