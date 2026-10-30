<?php

namespace App\Security;

class Permission
{
    // Menu permissions
    public const MENU_VIEW = 'menu.view';

    public const MENU_CREATE = 'menu.create';

    public const MENU_UPDATE = 'menu.update';

    public const MENU_DELETE = 'menu.delete';

    // Orders permissions
    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_UPDATE = 'orders.update';

    public const ORDERS_CANCEL = 'orders.cancel';

    public const ORDERS_REFUND = 'orders.refund';

    // Customers permissions
    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_EXPORT = 'customers.export';

    // Locations permissions
    public const LOCATIONS_VIEW = 'locations.view';

    public const LOCATIONS_MANAGE = 'locations.manage';

    // Team permissions
    public const TEAM_VIEW = 'team.view';

    public const TEAM_MANAGE = 'team.manage';

    // Billing permissions
    public const BILLING_VIEW = 'billing.view';

    public const BILLING_MANAGE = 'billing.manage';

    // AI permissions
    public const AI_VIEW = 'ai.view';

    public const AI_MANAGE = 'ai.manage';

    // Settings permissions
    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    // Storage permissions
    public const STORAGE_VIEW = 'storage.view';

    public const STORAGE_MANAGE = 'storage.manage';

    // Reports permissions
    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    // Tips and Ratings permissions
    public const TIPS_VIEW = 'tips.view';

    public const TIPS_MANAGE = 'tips.manage';

    public const RATINGS_VIEW = 'ratings.view';

    public const RATINGS_MANAGE = 'ratings.manage';

    public const STAFF_METRICS_VIEW = 'staff.metrics';

    // Split Bill permissions
    public const SPLIT_BILL_VIEW = 'split_bill.view';

    public const SPLIT_BILL_MANAGE = 'split_bill.manage';

    // Waiter Management permissions
    public const WAITERS_VIEW = 'waiters.view';

    public const WAITERS_MANAGE = 'waiters.manage';

    // Translation permissions
    public const TRANSLATIONS_VIEW = 'translations.view';

    public const TRANSLATIONS_MANAGE = 'translations.manage';

    public const TRANSLATIONS_PUBLISH = 'translations.publish';

    // POS permissions
    public const POS_ACCESS = 'pos.access';

    public const POS_ORDERS = 'pos.orders';

    public const POS_PAYMENTS = 'pos.payments';

    public const POS_CASH_DRAWER = 'pos.cash_drawer';

    public const POS_REFUNDS = 'pos.refunds';

    public const POS_DISCOUNTS = 'pos.discounts';

    public const POS_REPORTS = 'pos.reports';

    public const POS_SETTINGS = 'pos.settings';

    public const POS_DEVICES = 'pos.devices';

    public const POS_KDS = 'pos.kds';

    // Platform (SuperAdmin) permissions
    public const PLATFORM_ACCESS = 'platform.access';

    public const PLATFORM_VENDORS = 'platform.vendors';

    public const PLATFORM_SUBSCRIPTIONS = 'platform.subscriptions';

    public const PLATFORM_SETTINGS = 'platform.settings';

    public const PLATFORM_LOGS = 'platform.logs';

    public const PLATFORM_LANGUAGES = 'platform.languages';

    public const PLATFORM_EMAILS = 'platform.emails';

    /**
     * Get all defined permissions in the system.
     */
    public static function all(): array
    {
        return [
            self::MENU_VIEW,
            self::MENU_CREATE,
            self::MENU_UPDATE,
            self::MENU_DELETE,
            self::ORDERS_VIEW,
            self::ORDERS_UPDATE,
            self::ORDERS_CANCEL,
            self::ORDERS_REFUND,
            self::CUSTOMERS_VIEW,
            self::CUSTOMERS_EXPORT,
            self::LOCATIONS_VIEW,
            self::LOCATIONS_MANAGE,
            self::TEAM_VIEW,
            self::TEAM_MANAGE,
            self::BILLING_VIEW,
            self::BILLING_MANAGE,
            self::AI_VIEW,
            self::AI_MANAGE,
            self::SETTINGS_VIEW,
            self::SETTINGS_MANAGE,
            self::STORAGE_VIEW,
            self::STORAGE_MANAGE,
            self::REPORTS_VIEW,
            self::REPORTS_EXPORT,
            self::TIPS_VIEW,
            self::TIPS_MANAGE,
            self::RATINGS_VIEW,
            self::RATINGS_MANAGE,
            self::STAFF_METRICS_VIEW,
            self::SPLIT_BILL_VIEW,
            self::SPLIT_BILL_MANAGE,
            self::WAITERS_VIEW,
            self::WAITERS_MANAGE,
            self::TRANSLATIONS_VIEW,
            self::TRANSLATIONS_MANAGE,
            self::TRANSLATIONS_PUBLISH,
            self::POS_ACCESS,
            self::POS_ORDERS,
            self::POS_PAYMENTS,
            self::POS_CASH_DRAWER,
            self::POS_REFUNDS,
            self::POS_DISCOUNTS,
            self::POS_REPORTS,
            self::POS_SETTINGS,
            self::POS_DEVICES,
            self::POS_KDS,
            self::PLATFORM_ACCESS,
            self::PLATFORM_VENDORS,
            self::PLATFORM_SUBSCRIPTIONS,
            self::PLATFORM_SETTINGS,
            self::PLATFORM_LOGS,
            self::PLATFORM_LANGUAGES,
            self::PLATFORM_EMAILS,
        ];
    }

    /**
     * Default permission assignments for each system role.
     */
    public static function defaultRolePermissions(): array
    {
        return [
            'superadmin' => [
                self::PLATFORM_ACCESS,
                self::PLATFORM_VENDORS,
                self::PLATFORM_SUBSCRIPTIONS,
                self::PLATFORM_SETTINGS,
                self::PLATFORM_LOGS,
                self::PLATFORM_LANGUAGES,
                self::PLATFORM_EMAILS,
                self::TRANSLATIONS_VIEW,
                self::TRANSLATIONS_MANAGE,
                self::TRANSLATIONS_PUBLISH,
                self::STORAGE_VIEW,
                self::REPORTS_VIEW,
                self::REPORTS_EXPORT,
                self::TIPS_VIEW,
                self::TIPS_MANAGE,
                self::RATINGS_VIEW,
                self::RATINGS_MANAGE,
                self::STAFF_METRICS_VIEW,
                self::SPLIT_BILL_VIEW,
                self::SPLIT_BILL_MANAGE,
                self::WAITERS_VIEW,
                self::WAITERS_MANAGE,
                self::POS_ACCESS,
                self::POS_ORDERS,
                self::POS_PAYMENTS,
                self::POS_CASH_DRAWER,
                self::POS_REFUNDS,
                self::POS_DISCOUNTS,
                self::POS_REPORTS,
                self::POS_SETTINGS,
                self::POS_DEVICES,
                self::POS_KDS,
            ],
            'vendor_owner' => [
                self::MENU_VIEW,
                self::MENU_CREATE,
                self::MENU_UPDATE,
                self::MENU_DELETE,
                self::TRANSLATIONS_VIEW,
                self::TRANSLATIONS_MANAGE,
                self::TRANSLATIONS_PUBLISH,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE,
                self::ORDERS_CANCEL,
                self::ORDERS_REFUND,
                self::CUSTOMERS_VIEW,
                self::CUSTOMERS_EXPORT,
                self::LOCATIONS_VIEW,
                self::LOCATIONS_MANAGE,
                self::TEAM_VIEW,
                self::TEAM_MANAGE,
                self::BILLING_VIEW,
                self::BILLING_MANAGE,
                self::AI_VIEW,
                self::AI_MANAGE,
                self::SETTINGS_VIEW,
                self::SETTINGS_MANAGE,
                self::STORAGE_VIEW,
                self::STORAGE_MANAGE,
                self::REPORTS_VIEW,
                self::REPORTS_EXPORT,
                self::TIPS_VIEW,
                self::TIPS_MANAGE,
                self::RATINGS_VIEW,
                self::RATINGS_MANAGE,
                self::STAFF_METRICS_VIEW,
                self::SPLIT_BILL_VIEW,
                self::SPLIT_BILL_MANAGE,
                self::WAITERS_VIEW,
                self::WAITERS_MANAGE,
                self::POS_ACCESS,
                self::POS_ORDERS,
                self::POS_PAYMENTS,
                self::POS_CASH_DRAWER,
                self::POS_REFUNDS,
                self::POS_DISCOUNTS,
                self::POS_REPORTS,
                self::POS_SETTINGS,
                self::POS_DEVICES,
                self::POS_KDS,
            ],
            'manager' => [
                self::MENU_VIEW,
                self::MENU_CREATE,
                self::MENU_UPDATE,
                self::MENU_DELETE,
                self::TRANSLATIONS_VIEW,
                self::TRANSLATIONS_MANAGE,
                self::TRANSLATIONS_PUBLISH,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE,
                self::ORDERS_CANCEL,
                self::ORDERS_REFUND,
                self::CUSTOMERS_VIEW,
                self::CUSTOMERS_EXPORT,
                self::LOCATIONS_VIEW,
                self::LOCATIONS_MANAGE,
                self::TEAM_VIEW,
                // Managers do NOT get team.manage (cannot invite/fire team members)
                // Managers do NOT get billing.view or billing.manage
                self::AI_VIEW,
                // Managers do NOT get ai.manage (cannot change API keys)
                self::SETTINGS_VIEW,
                // Managers do NOT get settings.manage (cannot change payment secrets or credentials)
                self::STORAGE_VIEW,
                self::STORAGE_MANAGE,
                self::REPORTS_VIEW,
                self::REPORTS_EXPORT,
                self::TIPS_VIEW,
                self::RATINGS_VIEW,
                self::RATINGS_MANAGE,
                self::STAFF_METRICS_VIEW,
                self::SPLIT_BILL_VIEW,
                self::SPLIT_BILL_MANAGE,
                self::WAITERS_VIEW,
                self::WAITERS_MANAGE,
                self::POS_ACCESS,
                self::POS_ORDERS,
                self::POS_PAYMENTS,
                self::POS_CASH_DRAWER,
                self::POS_REFUNDS,
                self::POS_DISCOUNTS,
                self::POS_REPORTS,
                self::POS_SETTINGS,
                self::POS_DEVICES,
                self::POS_KDS,
            ],
            'staff' => [
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE,
                self::CUSTOMERS_VIEW,
                self::LOCATIONS_VIEW,
                self::STORAGE_VIEW,
                self::TIPS_VIEW,
                self::RATINGS_VIEW,
                self::SPLIT_BILL_VIEW,
                self::WAITERS_VIEW,
                self::POS_ACCESS,
                self::POS_ORDERS,
            ],
            'waiter' => [
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE,
                self::CUSTOMERS_VIEW,
                self::LOCATIONS_VIEW,
                self::STORAGE_VIEW,
                self::TIPS_VIEW,
                self::RATINGS_VIEW,
                self::SPLIT_BILL_VIEW,
                self::WAITERS_VIEW,
                self::POS_ACCESS,
                self::POS_ORDERS,
            ],
            'chef' => [
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE, // For kitchen prep status transitions (e.g. preparing -> ready)
                self::LOCATIONS_VIEW,
                self::POS_ACCESS,
                self::POS_KDS,
            ],
            'kitchen' => [
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE,
                self::LOCATIONS_VIEW,
                self::POS_ACCESS,
                self::POS_KDS,
            ],
            'cashier' => [
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE,
                self::ORDERS_CANCEL,
                self::ORDERS_REFUND,
                self::CUSTOMERS_VIEW,
                self::LOCATIONS_VIEW,
                self::REPORTS_VIEW,
                self::TIPS_VIEW,
                self::POS_ACCESS,
                self::POS_ORDERS,
                self::POS_PAYMENTS,
                self::POS_CASH_DRAWER,
                self::POS_DISCOUNTS,
                self::POS_REPORTS,
                // Cashier CANNOT:
                // manage menu items
                // export customer data
                // manage locations, team, billing, AI, settings
            ],
        ];
    }

    /**
     * Get default permissions for a specific role.
     */
    public static function forRole(string $role): array
    {
        return self::defaultRolePermissions()[$role] ?? [];
    }

    /**
     * Check if a role is a valid recognized role in the system.
     */
    public static function isValidRole(string $role): bool
    {
        return array_key_exists($role, self::defaultRolePermissions());
    }

    /**
     * Roles assignable by a tenant vendor owner/manager to team members.
     */
    public static function assignableTenantRoles(): array
    {
        return [
            'manager',
            'staff',
            'waiter',
            'chef',
            'cashier',
        ];
    }
}
