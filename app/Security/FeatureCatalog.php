<?php

namespace App\Security;

class FeatureCatalog
{
    // Canonical feature keys
    public const MENU_BASE = 'menu.base';

    public const MENU_TRANSLATIONS = 'menu.translations';

    public const ORDERS_ONLINE = 'orders.online';

    public const ORDERS_DELIVERY = 'orders.delivery';

    public const RESERVATIONS_MANAGE = 'reservations.manage';

    public const PREORDERS_MANAGE = 'preorders.manage';

    public const CUSTOMERS_CRM = 'customers.crm';

    public const BRANCHES_MULTIPLE = 'branches.multiple';

    public const TEAM_MANAGE = 'team.manage';

    public const ANALYTICS_BASIC = 'analytics.basic';

    public const ANALYTICS_ADVANCED = 'analytics.advanced';

    public const AI_MENU_BUILDER = 'ai.menu_builder';

    public const AI_WAITER = 'ai.waiter';

    public const BRANDING_CUSTOM_DOMAIN = 'branding.custom_domain';

    public const API_ACCESS = 'api.access';

    /**
     * Map of legacy or abbreviated aliases to canonical feature keys.
     */
    protected const ALIASES = [
        // Orders aliases
        'orders' => self::ORDERS_ONLINE,
        'online_orders' => self::ORDERS_ONLINE,
        'order' => self::ORDERS_ONLINE,

        // Delivery aliases
        'delivery' => self::ORDERS_DELIVERY,
        'takeaway' => self::ORDERS_DELIVERY,

        // Reservations & Pre-orders aliases
        'reservations' => self::RESERVATIONS_MANAGE,
        'reservation' => self::RESERVATIONS_MANAGE,
        'table_reservations' => self::RESERVATIONS_MANAGE,
        'preorders' => self::PREORDERS_MANAGE,
        'preorder' => self::PREORDERS_MANAGE,

        // Customers CRM aliases
        'customers' => self::CUSTOMERS_CRM,
        'customer' => self::CUSTOMERS_CRM,
        'crm' => self::CUSTOMERS_CRM,

        // Branches & Locations aliases
        'locations' => self::BRANCHES_MULTIPLE,
        'location' => self::BRANCHES_MULTIPLE,
        'multi_location' => self::BRANCHES_MULTIPLE,
        'branches' => self::BRANCHES_MULTIPLE,

        // Team & Roles aliases
        'team' => self::TEAM_MANAGE,
        'staff' => self::TEAM_MANAGE,
        'staff_roles' => self::TEAM_MANAGE,
        'roles' => self::TEAM_MANAGE,

        // Analytics aliases
        'analytics' => self::ANALYTICS_BASIC,
        'basic_analytics' => self::ANALYTICS_BASIC,
        'advanced_analytics' => self::ANALYTICS_ADVANCED,
        'reports' => self::ANALYTICS_ADVANCED,

        // AI aliases
        'ai' => self::AI_MENU_BUILDER,
        'ai.import' => self::AI_MENU_BUILDER,
        'ai_import' => self::AI_MENU_BUILDER,
        'ai_menu' => self::AI_MENU_BUILDER,
        'ai_waiter' => self::AI_WAITER,
        'waiter' => self::AI_WAITER,

        // Custom domain & branding aliases
        'branding' => self::MENU_BASE,
        'custom_domain' => self::BRANDING_CUSTOM_DOMAIN,
        'domain' => self::BRANDING_CUSTOM_DOMAIN,

        // Menu aliases
        'menu' => self::MENU_BASE,
        'qr' => self::MENU_BASE,
        'translations' => self::MENU_TRANSLATIONS,
    ];

    /**
     * Normalize an input feature name to its canonical feature key.
     */
    public static function normalize(string $feature): string
    {
        $clean = strtolower(trim($feature));

        return self::ALIASES[$clean] ?? $clean;
    }

    /**
     * Check if a feature key is recognized in the catalog.
     */
    public static function isValid(string $feature): bool
    {
        $normalized = self::normalize($feature);

        return in_array($normalized, self::all(), true);
    }

    /**
     * Return all canonical feature keys.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::MENU_BASE,
            self::MENU_TRANSLATIONS,
            self::ORDERS_ONLINE,
            self::ORDERS_DELIVERY,
            self::RESERVATIONS_MANAGE,
            self::PREORDERS_MANAGE,
            self::CUSTOMERS_CRM,
            self::BRANCHES_MULTIPLE,
            self::TEAM_MANAGE,
            self::ANALYTICS_BASIC,
            self::ANALYTICS_ADVANCED,
            self::AI_MENU_BUILDER,
            self::AI_WAITER,
            self::BRANDING_CUSTOM_DOMAIN,
            self::API_ACCESS,
        ];
    }

    /**
     * Return default feature bundle for a plan tier slug.
     *
     * @return array<int, string>
     */
    public static function getDefaultFeaturesForPlan(string $planSlug): array
    {
        $slug = strtolower(trim($planSlug));

        if ($slug === 'custom') {
            return self::all();
        }

        return match ($slug) {
            'business' => [
                self::MENU_BASE,
                self::MENU_TRANSLATIONS,
                self::ANALYTICS_BASIC,
                self::ORDERS_ONLINE,
                self::ORDERS_DELIVERY,
                self::RESERVATIONS_MANAGE,
                self::PREORDERS_MANAGE,
                self::CUSTOMERS_CRM,
                self::BRANCHES_MULTIPLE,
                self::TEAM_MANAGE,
                self::ANALYTICS_ADVANCED,
                self::AI_MENU_BUILDER,
                self::AI_WAITER,
                self::BRANDING_CUSTOM_DOMAIN,
                self::API_ACCESS,
            ],
            'pro' => [
                self::MENU_BASE,
                self::MENU_TRANSLATIONS,
                self::ANALYTICS_BASIC,
                self::ORDERS_ONLINE,
                self::ORDERS_DELIVERY,
                self::RESERVATIONS_MANAGE,
                self::PREORDERS_MANAGE,
                self::CUSTOMERS_CRM,
                self::AI_MENU_BUILDER,
                self::AI_WAITER,
                self::BRANDING_CUSTOM_DOMAIN,
            ],
            default => [
                self::MENU_BASE,
                self::MENU_TRANSLATIONS,
                self::ANALYTICS_BASIC,
            ],
        };
    }

    /**
     * Minimum plan tier required for a feature.
     */
    public static function getMinimumPlan(string $feature): string
    {
        $normalized = self::normalize($feature);

        if (in_array($normalized, [
            self::BRANCHES_MULTIPLE,
            self::TEAM_MANAGE,
            self::ANALYTICS_ADVANCED,
            self::API_ACCESS,
        ], true)) {
            return 'Business';
        }

        if (in_array($normalized, [
            self::ORDERS_ONLINE,
            self::ORDERS_DELIVERY,
            self::RESERVATIONS_MANAGE,
            self::PREORDERS_MANAGE,
            self::CUSTOMERS_CRM,
            self::AI_MENU_BUILDER,
            self::AI_WAITER,
            self::BRANDING_CUSTOM_DOMAIN,
        ], true)) {
            return 'Pro';
        }

        return 'Basic';
    }

    /**
     * Human-readable feature name for messages and alerts.
     */
    public static function getDisplayName(string $feature, string $locale = 'hy'): string
    {
        $normalized = self::normalize($feature);

        $namesHy = [
            self::MENU_BASE => 'Թվային QR Մենյու',
            self::MENU_TRANSLATIONS => 'Բազմալեզու թարգմանություններ',
            self::ORDERS_ONLINE => 'Օնլայն պատվերներ սեղաններից',
            self::ORDERS_DELIVERY => 'Առաքում և Takeaway',
            self::RESERVATIONS_MANAGE => 'Սեղանների ամրագրում',
            self::PREORDERS_MANAGE => 'Նախնական պատվերներ',
            self::CUSTOMERS_CRM => 'Հաճախորդների CRM',
            self::BRANCHES_MULTIPLE => 'Բազմամասնաճյուղ կառավարում',
            self::TEAM_MANAGE => 'Աշխատակիցների դերեր և թիմ',
            self::ANALYTICS_BASIC => 'Հիմնական վիճակագրություն',
            self::ANALYTICS_ADVANCED => 'Ընդլայնված վերլուծություն',
            self::AI_MENU_BUILDER => 'AI Մենյուի ներմուծում և գեներացիա',
            self::AI_WAITER => 'AI Մատուցող (խորհրդատու)',
            self::BRANDING_CUSTOM_DOMAIN => 'Սեփական դոմեն (White-label)',
            self::API_ACCESS => 'API Ինտեգրացիաներ',
        ];

        $namesEn = [
            self::MENU_BASE => 'Digital QR Menu',
            self::MENU_TRANSLATIONS => 'Multilingual Translations',
            self::ORDERS_ONLINE => 'Online Table Orders',
            self::ORDERS_DELIVERY => 'Delivery & Takeaway',
            self::RESERVATIONS_MANAGE => 'Table Reservations',
            self::PREORDERS_MANAGE => 'Pre-Orders',
            self::CUSTOMERS_CRM => 'Customer CRM',
            self::BRANCHES_MULTIPLE => 'Multi-Branch Management',
            self::TEAM_MANAGE => 'Staff Roles & Team',
            self::ANALYTICS_BASIC => 'Basic Analytics',
            self::ANALYTICS_ADVANCED => 'Advanced Analytics',
            self::AI_MENU_BUILDER => 'AI Menu Builder & Import',
            self::AI_WAITER => 'AI Waiter Advisor',
            self::BRANDING_CUSTOM_DOMAIN => 'Custom Domain Branding',
            self::API_ACCESS => 'API Access',
        ];

        if ($locale === 'en') {
            return $namesEn[$normalized] ?? ucfirst(str_replace(['.', '_'], ' ', $normalized));
        }

        return $namesHy[$normalized] ?? $normalized;
    }
}
