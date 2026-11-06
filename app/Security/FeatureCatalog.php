<?php

namespace App\Security;

use App\Models\Feature;

class FeatureCatalog
{
    // Legacy & core canonical feature keys
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

    public const TIPS_RATINGS = 'tips.ratings';

    public const BILLS_SPLIT = 'bills.split';

    public const POS_ACCESS = 'pos.access';

    // Inventory & Production
    public const INVENTORY_CATALOG = 'inventory.catalog';

    public const INVENTORY_WAREHOUSES = 'inventory.warehouses';

    public const INVENTORY_RECIPES = 'inventory.recipes';

    public const INVENTORY_COSTING = 'inventory.costing';

    public const INVENTORY_SUPPLIERS = 'inventory.suppliers';

    public const INVENTORY_PROCUREMENT = 'inventory.procurement';

    public const INVENTORY_PRODUCTION = 'inventory.production';

    public const INVENTORY_HACCP = 'inventory.haccp';

    public const INVENTORY_WASTE = 'inventory.waste';

    public const INVENTORY_REPORTS = 'inventory.reports';

    // Dynamic Feature Catalog canonical codes (Sections 3 & 4)
    public const MENU_QR = 'menu.qr';

    public const MENU_CATEGORIES_LIMIT = 'menu.categories.limit';

    public const MENU_PRODUCTS_LIMIT = 'menu.products.limit';

    public const MENU_PRODUCT_IMAGES = 'menu.product_images';

    public const MENU_VIDEOS = 'menu.videos';

    public const MENU_MODIFIERS = 'menu.modifiers';

    public const MENU_VARIANTS = 'menu.variants';

    public const MENU_SCHEDULING = 'menu.scheduling';

    public const LANGUAGES_MULTILINGUAL = 'languages.multilingual';

    public const MENU_LANGUAGES_LIMIT = 'menu.languages.limit';

    public const AI_TRANSLATION = 'ai.translation';

    public const CURRENCY_PRIMARY = 'currency.primary';

    public const CURRENCY_MULTI = 'currency.multi';

    public const CURRENCY_MAX_ADDITIONAL = 'currency.max_additional';

    public const CURRENCY_AUTO_RATE = 'currency.auto_rate';

    public const CURRENCY_MANUAL_RATE = 'currency.manual_rate';

    public const ORDERS_TABLE_QR = 'orders.table_qr';

    public const ORDERS_MANAGEMENT = 'orders.management';

    public const ORDERS_MONTHLY_LIMIT = 'orders.monthly_limit';

    public const ORDERS_ONLINE_PAYMENTS = 'orders.online_payments';

    public const ORDERS_CASH_PAYMENTS = 'orders.cash_payments';

    public const ORDERS_NOTIFICATIONS = 'orders.notifications';

    public const ORDERS_TRACKING = 'orders.tracking';

    public const ORDERS_ADVANCED_WORKFLOWS = 'orders.advanced_workflows';

    public const DELIVERY_ENABLED = 'delivery.enabled';

    public const PICKUP_ENABLED = 'pickup.enabled';

    public const DELIVERY_ZONES_LIMIT = 'delivery.zones.limit';

    public const DELIVERY_FEE_CONFIG = 'delivery.fee_config';

    public const DELIVERY_MIN_ORDER = 'delivery.min_order';

    public const DELIVERY_SCHEDULING = 'delivery.scheduling';

    public const DELIVERY_ADVANCED_SCHEDULING = 'delivery.advanced_scheduling';

    public const TABLES_MANAGEMENT = 'tables.management';

    public const RESERVATIONS_ENABLED = 'reservations.enabled';

    public const PREORDERS_ENABLED = 'preorders.enabled';

    public const KDS_ENABLED = 'kds.enabled';

    public const POS_ENABLED = 'pos.enabled';

    public const POS_MANAGER_MODE = 'pos.manager_mode';

    public const POS_CASHIER_MODE = 'pos.cashier_mode';

    public const POS_WAITER_MODE = 'pos.waiter_mode';

    public const STAFF_USERS_LIMIT = 'staff.users.limit';

    public const MARKETING_COUPONS = 'marketing.coupons';

    public const MARKETING_LOYALTY = 'marketing.loyalty';

    public const MARKETING_APPLE_WALLET = 'marketing.apple_wallet';

    public const MARKETING_CUSTOMERS = 'marketing.customers';

    public const MARKETING_SEGMENTATION = 'marketing.segmentation';

    public const REVIEWS_GENERATION = 'reviews.generation';

    public const REVIEWS_GOOGLE = 'reviews.google';

    public const REVIEWS_TRIPADVISOR = 'reviews.tripadvisor';

    public const REVIEWS_YANDEX = 'reviews.yandex';

    public const ANALYTICS_EXPORT = 'analytics.export';

    public const ANALYTICS_FULL_REPORTING = 'analytics.full_reporting';

    public const ANALYTICS_BUSINESS = 'analytics.business';

    public const AI_RECOMMENDATIONS = 'ai.recommendations';

    public const AI_ASSISTANT = 'ai.assistant';

    public const AI_MONTHLY_CREDITS = 'ai.monthly_credits';

    public const INTEGRATIONS_API = 'integrations.api';

    public const INTEGRATIONS_WEBHOOKS = 'integrations.webhooks';

    public const INTEGRATIONS_CUSTOM_DOMAIN = 'integrations.custom_domain';

    public const BRANDING_WHITE_LABEL = 'branding.white_label';

    public const LOCATIONS_MULTI = 'locations.multi';

    public const LOCATIONS_LIMIT = 'locations.limit';

    /**
     * Map of legacy or abbreviated aliases to canonical feature keys.
     */
    protected const ALIASES = [
        // POS aliases
        'pos' => self::POS_ACCESS,
        'pos_access' => self::POS_ACCESS,
        'pos.access' => self::POS_ACCESS,
        'pos_terminal' => self::POS_ACCESS,
        'point_of_sale' => self::POS_ACCESS,

        // Split Bill aliases
        'split_bill' => self::BILLS_SPLIT,
        'split_bills' => self::BILLS_SPLIT,
        'bills.split' => self::BILLS_SPLIT,

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

        // Tips & Ratings aliases
        'tips' => self::TIPS_RATINGS,
        'ratings' => self::TIPS_RATINGS,
        'waiter_rating' => self::TIPS_RATINGS,
        'tips_ratings' => self::TIPS_RATINGS,
        'service_ratings' => self::TIPS_RATINGS,

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
     * Check if a feature key is recognized in the catalog (static or database).
     */
    public static function isValid(string $feature): bool
    {
        $normalized = self::normalize($feature);

        if (in_array($normalized, self::all(), true) || in_array($normalized, self::dynamicCodes(), true)) {
            return true;
        }

        try {
            return Feature::where('code', $normalized)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Return all core canonical feature keys.
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
            self::TIPS_RATINGS,
            self::BILLS_SPLIT,
            self::POS_ACCESS,
        ];
    }

    /**
     * Return all extended dynamic feature codes.
     *
     * @return array<int, string>
     */
    public static function dynamicCodes(): array
    {
        return [
            self::MENU_QR,
            self::MENU_CATEGORIES_LIMIT,
            self::MENU_PRODUCTS_LIMIT,
            self::MENU_PRODUCT_IMAGES,
            self::MENU_VIDEOS,
            self::MENU_MODIFIERS,
            self::MENU_VARIANTS,
            self::MENU_SCHEDULING,
            self::LANGUAGES_MULTILINGUAL,
            self::MENU_LANGUAGES_LIMIT,
            self::AI_TRANSLATION,
            self::CURRENCY_PRIMARY,
            self::CURRENCY_MULTI,
            self::CURRENCY_MAX_ADDITIONAL,
            self::CURRENCY_AUTO_RATE,
            self::CURRENCY_MANUAL_RATE,
            self::ORDERS_ONLINE,
            self::ORDERS_TABLE_QR,
            self::ORDERS_MANAGEMENT,
            self::ORDERS_MONTHLY_LIMIT,
            self::ORDERS_ONLINE_PAYMENTS,
            self::ORDERS_CASH_PAYMENTS,
            self::ORDERS_NOTIFICATIONS,
            self::ORDERS_TRACKING,
            self::ORDERS_ADVANCED_WORKFLOWS,
            self::DELIVERY_ENABLED,
            self::PICKUP_ENABLED,
            self::DELIVERY_ZONES_LIMIT,
            self::DELIVERY_FEE_CONFIG,
            self::DELIVERY_MIN_ORDER,
            self::DELIVERY_SCHEDULING,
            self::DELIVERY_ADVANCED_SCHEDULING,
            self::TABLES_MANAGEMENT,
            self::RESERVATIONS_ENABLED,
            self::PREORDERS_ENABLED,
            self::KDS_ENABLED,
            self::POS_ENABLED,
            self::POS_MANAGER_MODE,
            self::POS_CASHIER_MODE,
            self::POS_WAITER_MODE,
            self::STAFF_USERS_LIMIT,
            self::TEAM_MANAGE,
            self::MARKETING_COUPONS,
            self::MARKETING_LOYALTY,
            self::MARKETING_APPLE_WALLET,
            self::MARKETING_CUSTOMERS,
            self::MARKETING_SEGMENTATION,
            self::REVIEWS_GENERATION,
            self::REVIEWS_GOOGLE,
            self::REVIEWS_TRIPADVISOR,
            self::REVIEWS_YANDEX,
            self::ANALYTICS_BASIC,
            self::ANALYTICS_ADVANCED,
            self::ANALYTICS_EXPORT,
            self::ANALYTICS_FULL_REPORTING,
            self::ANALYTICS_BUSINESS,
            self::AI_WAITER,
            self::AI_RECOMMENDATIONS,
            self::AI_ASSISTANT,
            self::AI_MONTHLY_CREDITS,
            self::INTEGRATIONS_API,
            self::INTEGRATIONS_WEBHOOKS,
            self::INTEGRATIONS_CUSTOM_DOMAIN,
            self::BRANDING_WHITE_LABEL,
            self::LOCATIONS_MULTI,
            self::LOCATIONS_LIMIT,
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
            'advanced', 'business' => [
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
                self::TIPS_RATINGS,
                self::BILLS_SPLIT,
                self::POS_ACCESS,
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
                self::TIPS_RATINGS,
                self::BILLS_SPLIT,
                self::POS_ACCESS,
            ],
            default => [
                self::MENU_BASE,
                self::MENU_TRANSLATIONS,
                self::ANALYTICS_BASIC,
                self::POS_ACCESS,
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
            self::INTEGRATIONS_API,
            self::INTEGRATIONS_WEBHOOKS,
            self::INTEGRATIONS_CUSTOM_DOMAIN,
            self::BRANDING_WHITE_LABEL,
            self::LOCATIONS_MULTI,
            self::POS_ENABLED,
            self::POS_MANAGER_MODE,
            self::POS_CASHIER_MODE,
            self::POS_WAITER_MODE,
            self::AI_ASSISTANT,
            self::ANALYTICS_FULL_REPORTING,
            self::ANALYTICS_BUSINESS,
            self::MARKETING_SEGMENTATION,
            self::ORDERS_ADVANCED_WORKFLOWS,
            self::DELIVERY_ADVANCED_SCHEDULING,
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
            self::TIPS_RATINGS,
            self::BILLS_SPLIT,
            self::MENU_VIDEOS,
            self::MENU_MODIFIERS,
            self::MENU_VARIANTS,
            self::MENU_SCHEDULING,
            self::AI_TRANSLATION,
            self::CURRENCY_MULTI,
            self::CURRENCY_AUTO_RATE,
            self::DELIVERY_ENABLED,
            self::PICKUP_ENABLED,
            self::RESERVATIONS_ENABLED,
            self::PREORDERS_ENABLED,
            self::KDS_ENABLED,
            self::MARKETING_COUPONS,
            self::MARKETING_LOYALTY,
            self::MARKETING_APPLE_WALLET,
            self::ANALYTICS_EXPORT,
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

        try {
            $dbFeature = Feature::where('code', $normalized)->orWhere('code', $feature)->first();
            if ($dbFeature) {
                return $dbFeature->localizedName($locale);
            }
        } catch (\Throwable) {
            // Fallback to static map if DB table not migrated yet
        }

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
            self::TIPS_RATINGS => 'Թեյավճար և Մատուցողի գնահատում',
            self::BILLS_SPLIT => 'Հաշվի բաժանում (Split Bill)',
            self::POS_ACCESS => 'POS Տերմինալ և Գործառնություններ',
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
            self::TIPS_RATINGS => 'Tips & Waiter Rating',
            self::BILLS_SPLIT => 'Split Bill',
            self::POS_ACCESS => 'POS Terminal & Operations',
        ];

        if ($locale === 'en') {
            return $namesEn[$normalized] ?? ucfirst(str_replace(['.', '_'], ' ', $normalized));
        }

        return $namesHy[$normalized] ?? $normalized;
    }
}
