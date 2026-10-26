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
}
