<?php

namespace App\Security;

class QuotaCatalog
{
    /**
     * Sentinel value indicating unlimited quota.
     * Note: NULL in plan definitions is also normalized to UNLIMITED (-1) where applicable.
     */
    public const UNLIMITED = -1;

    // Quota classification types
    public const TYPE_RESOURCE = 'resource';

    public const TYPE_EVENT = 'event';

    // Canonical Quota Keys
    public const KEY_PRODUCTS_COUNT = 'products.count';

    public const KEY_CATEGORIES_COUNT = 'categories.count';

    public const KEY_BRANCHES_COUNT = 'branches.count';

    public const KEY_TEAM_MEMBERS_COUNT = 'team_members.count';

    public const KEY_STORAGE_BYTES = 'storage.bytes';

    public const KEY_ORDERS_PERIOD = 'orders.period';

    public const KEY_RESERVATIONS_PERIOD = 'reservations.period';

    public const KEY_AI_REQUESTS_PERIOD = 'ai.requests.period';

    public const KEY_API_REQUESTS_PERIOD = 'api.requests.period';

    public const KEY_LANGUAGES_COUNT = 'languages.count';

    public const KEY_DELIVERY_ZONES_COUNT = 'delivery_zones.count';

    public const KEY_CURRENCIES_COUNT = 'currencies.count';

    /**
     * Aliases map for legacy or conversational keys.
     */
    protected const ALIASES = [
        'products' => self::KEY_PRODUCTS_COUNT,
        'product' => self::KEY_PRODUCTS_COUNT,
        'dishes' => self::KEY_PRODUCTS_COUNT,
        'active_products' => self::KEY_PRODUCTS_COUNT,
        'menu.products.limit' => self::KEY_PRODUCTS_COUNT,

        'categories' => self::KEY_CATEGORIES_COUNT,
        'category' => self::KEY_CATEGORIES_COUNT,
        'menus' => self::KEY_CATEGORIES_COUNT,
        'active_menus' => self::KEY_CATEGORIES_COUNT,
        'menu.categories.limit' => self::KEY_CATEGORIES_COUNT,

        'branches' => self::KEY_BRANCHES_COUNT,
        'branch' => self::KEY_BRANCHES_COUNT,
        'locations' => self::KEY_BRANCHES_COUNT,
        'location' => self::KEY_BRANCHES_COUNT,
        'locations.limit' => self::KEY_BRANCHES_COUNT,

        'team' => self::KEY_TEAM_MEMBERS_COUNT,
        'team_members' => self::KEY_TEAM_MEMBERS_COUNT,
        'staff' => self::KEY_TEAM_MEMBERS_COUNT,
        'users' => self::KEY_TEAM_MEMBERS_COUNT,
        'staff.users.limit' => self::KEY_TEAM_MEMBERS_COUNT,

        'storage' => self::KEY_STORAGE_BYTES,
        'disk' => self::KEY_STORAGE_BYTES,
        'storage_bytes' => self::KEY_STORAGE_BYTES,

        'orders' => self::KEY_ORDERS_PERIOD,
        'orders.monthly' => self::KEY_ORDERS_PERIOD,
        'orders.monthly_limit' => self::KEY_ORDERS_PERIOD,
        'online_orders' => self::KEY_ORDERS_PERIOD,

        'reservations' => self::KEY_RESERVATIONS_PERIOD,
        'reservations.monthly' => self::KEY_RESERVATIONS_PERIOD,
        'table_reservations' => self::KEY_RESERVATIONS_PERIOD,

        'ai' => self::KEY_AI_REQUESTS_PERIOD,
        'ai_requests' => self::KEY_AI_REQUESTS_PERIOD,
        'ai.requests' => self::KEY_AI_REQUESTS_PERIOD,
        'ai.monthly_credits' => self::KEY_AI_REQUESTS_PERIOD,
        'ai_credits' => self::KEY_AI_REQUESTS_PERIOD,

        'api' => self::KEY_API_REQUESTS_PERIOD,
        'api_requests' => self::KEY_API_REQUESTS_PERIOD,
        'api.requests' => self::KEY_API_REQUESTS_PERIOD,

        'languages' => self::KEY_LANGUAGES_COUNT,
        'menu.languages.limit' => self::KEY_LANGUAGES_COUNT,

        'delivery_zones' => self::KEY_DELIVERY_ZONES_COUNT,
        'delivery.zones.limit' => self::KEY_DELIVERY_ZONES_COUNT,

        'currencies' => self::KEY_CURRENCIES_COUNT,
        'currency.max_additional' => self::KEY_CURRENCIES_COUNT,
    ];

    /**
     * Normalize an input key to canonical quota key.
     */
    public static function normalize(string $key): string
    {
        $clean = strtolower(trim($key));

        return self::ALIASES[$clean] ?? $clean;
    }

    /**
     * Determine if a quota key is recognized.
     */
    public static function isValid(string $key): bool
    {
        $norm = self::normalize($key);

        return in_array($norm, self::all(), true) || in_array($norm, self::extendedKeys(), true);
    }

    /**
     * List all canonical quota keys.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::KEY_PRODUCTS_COUNT,
            self::KEY_CATEGORIES_COUNT,
            self::KEY_BRANCHES_COUNT,
            self::KEY_TEAM_MEMBERS_COUNT,
            self::KEY_STORAGE_BYTES,
            self::KEY_ORDERS_PERIOD,
            self::KEY_RESERVATIONS_PERIOD,
            self::KEY_AI_REQUESTS_PERIOD,
            self::KEY_API_REQUESTS_PERIOD,
        ];
    }

    /**
     * Extended resource quota keys.
     *
     * @return array<int, string>
     */
    public static function extendedKeys(): array
    {
        return [
            self::KEY_LANGUAGES_COUNT,
            self::KEY_DELIVERY_ZONES_COUNT,
            self::KEY_CURRENCIES_COUNT,
        ];
    }

    /**
     * Get quota classification type: 'resource' (stateful count) or 'event' (billing-period metered).
     */
    public static function getType(string $key): string
    {
        $canonical = self::normalize($key);

        return match ($canonical) {
            self::KEY_PRODUCTS_COUNT,
            self::KEY_CATEGORIES_COUNT,
            self::KEY_BRANCHES_COUNT,
            self::KEY_TEAM_MEMBERS_COUNT,
            self::KEY_STORAGE_BYTES,
            self::KEY_LANGUAGES_COUNT,
            self::KEY_DELIVERY_ZONES_COUNT,
            self::KEY_CURRENCIES_COUNT => self::TYPE_RESOURCE,
            default => self::TYPE_EVENT,
        };
    }

    /**
     * Measurement unit name for display and formatting.
     */
    public static function getUnit(string $key): string
    {
        $canonical = self::normalize($key);

        return match ($canonical) {
            self::KEY_PRODUCTS_COUNT => 'items',
            self::KEY_CATEGORIES_COUNT => 'categories',
            self::KEY_BRANCHES_COUNT => 'locations',
            self::KEY_TEAM_MEMBERS_COUNT => 'members',
            self::KEY_STORAGE_BYTES => 'bytes',
            self::KEY_ORDERS_PERIOD => 'orders',
            self::KEY_RESERVATIONS_PERIOD => 'reservations',
            self::KEY_AI_REQUESTS_PERIOD => 'requests',
            self::KEY_API_REQUESTS_PERIOD => 'calls',
            self::KEY_LANGUAGES_COUNT => 'languages',
            self::KEY_DELIVERY_ZONES_COUNT => 'zones',
            self::KEY_CURRENCIES_COUNT => 'currencies',
            default => 'units',
        };
    }

    /**
     * Default quota limits per subscription plan slug.
     *
     * @return array<string, int>
     */
    public static function getDefaultQuotasForPlan(string $planSlug): array
    {
        $slug = strtolower(trim($planSlug));

        if ($slug === 'custom') {
            return [
                self::KEY_PRODUCTS_COUNT => self::UNLIMITED,
                self::KEY_CATEGORIES_COUNT => self::UNLIMITED,
                self::KEY_BRANCHES_COUNT => self::UNLIMITED,
                self::KEY_TEAM_MEMBERS_COUNT => self::UNLIMITED,
                self::KEY_STORAGE_BYTES => 10737418240, // 10 GB
                self::KEY_ORDERS_PERIOD => self::UNLIMITED,
                self::KEY_RESERVATIONS_PERIOD => self::UNLIMITED,
                self::KEY_AI_REQUESTS_PERIOD => self::UNLIMITED,
                self::KEY_API_REQUESTS_PERIOD => self::UNLIMITED,
            ];
        }

        return match ($slug) {
            'advanced', 'business' => [
                self::KEY_PRODUCTS_COUNT => self::UNLIMITED,
                self::KEY_CATEGORIES_COUNT => self::UNLIMITED,
                self::KEY_BRANCHES_COUNT => 10,
                self::KEY_TEAM_MEMBERS_COUNT => 25,
                self::KEY_STORAGE_BYTES => 2147483648, // 2 GB
                self::KEY_ORDERS_PERIOD => self::UNLIMITED,
                self::KEY_RESERVATIONS_PERIOD => self::UNLIMITED,
                self::KEY_AI_REQUESTS_PERIOD => 1000,
                self::KEY_API_REQUESTS_PERIOD => 10000,
            ],
            'pro' => [
                self::KEY_PRODUCTS_COUNT => 250,
                self::KEY_CATEGORIES_COUNT => 30,
                self::KEY_BRANCHES_COUNT => 1,
                self::KEY_TEAM_MEMBERS_COUNT => 5,
                self::KEY_STORAGE_BYTES => 524288000, // 500 MB
                self::KEY_ORDERS_PERIOD => 1000,
                self::KEY_RESERVATIONS_PERIOD => 200,
                self::KEY_AI_REQUESTS_PERIOD => 150,
                self::KEY_API_REQUESTS_PERIOD => 0,
            ],
            default => [
                // Basic / Free trial plan
                self::KEY_PRODUCTS_COUNT => 50,
                self::KEY_CATEGORIES_COUNT => 10,
                self::KEY_BRANCHES_COUNT => 1,
                self::KEY_TEAM_MEMBERS_COUNT => 2,
                self::KEY_STORAGE_BYTES => 104857600, // 100 MB
                self::KEY_ORDERS_PERIOD => 0,
                self::KEY_RESERVATIONS_PERIOD => 0,
                self::KEY_AI_REQUESTS_PERIOD => 0,
                self::KEY_API_REQUESTS_PERIOD => 0,
            ],
        };
    }

    /**
     * Default limit for a specific plan and quota key.
     */
    public static function getDefaultLimit(string $planSlug, string $quotaKey): int
    {
        $canonical = self::normalize($quotaKey);
        $quotas = self::getDefaultQuotasForPlan($planSlug);

        if (array_key_exists($canonical, $quotas)) {
            return $quotas[$canonical];
        }

        $slug = strtolower(trim($planSlug));

        return match ($canonical) {
            self::KEY_LANGUAGES_COUNT => match ($slug) {
                'advanced', 'business', 'custom' => self::UNLIMITED,
                'pro' => 5,
                default => 2,
            },
            self::KEY_DELIVERY_ZONES_COUNT => match ($slug) {
                'advanced', 'business', 'custom' => self::UNLIMITED,
                'pro' => 5,
                default => 0,
            },
            self::KEY_CURRENCIES_COUNT => match ($slug) {
                'advanced', 'business', 'custom' => self::UNLIMITED,
                'pro' => 3,
                default => 0,
            },
            default => 0,
        };
    }

    /**
     * Localized human-readable title for a quota.
     */
    public static function getDisplayName(string $key, string $locale = 'hy'): string
    {
        $canonical = self::normalize($key);

        $namesHy = [
            self::KEY_PRODUCTS_COUNT => 'Ակտիվ ապրանքների քանակ',
            self::KEY_CATEGORIES_COUNT => 'Բաժինների / Մենյուների քանակ',
            self::KEY_BRANCHES_COUNT => 'Մասնաճյուղերի քանակ',
            self::KEY_TEAM_MEMBERS_COUNT => 'Աշխատակիցների քանակ',
            self::KEY_STORAGE_BYTES => 'Սկավառակի ծավալ (Storage)',
            self::KEY_ORDERS_PERIOD => 'Օնլայն պատվերներ (ամսական)',
            self::KEY_RESERVATIONS_PERIOD => 'Ամրագրումներ (ամսական)',
            self::KEY_AI_REQUESTS_PERIOD => 'AI Հարցումներ (ամսական)',
            self::KEY_API_REQUESTS_PERIOD => 'API Հարցումներ (ամսական)',
            self::KEY_LANGUAGES_COUNT => 'Առավելագույն լեզուների քանակ',
            self::KEY_DELIVERY_ZONES_COUNT => 'Առաքման գոտիների քանակ',
            self::KEY_CURRENCIES_COUNT => 'Լրացուցիչ արժույթների քանակ',
        ];

        $namesEn = [
            self::KEY_PRODUCTS_COUNT => 'Active Products Count',
            self::KEY_CATEGORIES_COUNT => 'Categories / Menus Count',
            self::KEY_BRANCHES_COUNT => 'Branches / Locations Count',
            self::KEY_TEAM_MEMBERS_COUNT => 'Team Members Count',
            self::KEY_STORAGE_BYTES => 'Disk Storage (Bytes)',
            self::KEY_ORDERS_PERIOD => 'Online Orders (Per Period)',
            self::KEY_RESERVATIONS_PERIOD => 'Reservations (Per Period)',
            self::KEY_AI_REQUESTS_PERIOD => 'AI Requests (Per Period)',
            self::KEY_API_REQUESTS_PERIOD => 'API Requests (Per Period)',
            self::KEY_LANGUAGES_COUNT => 'Maximum Languages Count',
            self::KEY_DELIVERY_ZONES_COUNT => 'Delivery Zones Count',
            self::KEY_CURRENCIES_COUNT => 'Additional Currencies Count',
        ];

        $namesRu = [
            self::KEY_PRODUCTS_COUNT => 'Количество активных товаров',
            self::KEY_CATEGORIES_COUNT => 'Количество категорий / меню',
            self::KEY_BRANCHES_COUNT => 'Количество филиалов',
            self::KEY_TEAM_MEMBERS_COUNT => 'Количество сотрудников',
            self::KEY_STORAGE_BYTES => 'Объем хранилища (Storage)',
            self::KEY_ORDERS_PERIOD => 'Онлайн-заказы (в месяц)',
            self::KEY_RESERVATIONS_PERIOD => 'Бронирования (в месяц)',
            self::KEY_AI_REQUESTS_PERIOD => 'AI-запросы (в месяц)',
            self::KEY_API_REQUESTS_PERIOD => 'API-запросы (в месяц)',
            self::KEY_LANGUAGES_COUNT => 'Максимальное количество языков',
            self::KEY_DELIVERY_ZONES_COUNT => 'Количество зон доставки',
            self::KEY_CURRENCIES_COUNT => 'Дополнительные валюты',
        ];

        if ($locale === 'en') {
            return $namesEn[$canonical] ?? ucfirst(str_replace(['.', '_'], ' ', $canonical));
        }

        if ($locale === 'ru') {
            return $namesRu[$canonical] ?? $namesEn[$canonical] ?? $canonical;
        }

        return $namesHy[$canonical] ?? $canonical;
    }
}
