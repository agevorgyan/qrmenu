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

    // Ingredients & Inventory Permissions
    public const INGREDIENTS_VIEW = 'ingredients.view';

    public const INGREDIENTS_CREATE = 'ingredients.create';

    public const INGREDIENTS_UPDATE = 'ingredients.update';

    public const INGREDIENTS_DELETE = 'ingredients.delete';

    public const INGREDIENTS_EXPORT = 'ingredients.export';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_RECEIPT = 'inventory.receipt';

    public const INVENTORY_ISSUE = 'inventory.issue';

    public const INVENTORY_TRANSFER = 'inventory.transfer';

    public const INVENTORY_ADJUST = 'inventory.adjust';

    public const INVENTORY_COUNT = 'inventory.count';

    public const INVENTORY_APPROVE = 'inventory.approve';

    public const INVENTORY_WASTE = 'inventory.waste';

    public const INVENTORY_REPORTS = 'inventory.reports';

    // Recipe & Costing Permissions
    public const RECIPES_VIEW = 'recipes.view';

    public const RECIPES_CREATE = 'recipes.create';

    public const RECIPES_UPDATE = 'recipes.update';

    public const RECIPES_DELETE = 'recipes.delete';

    public const RECIPES_APPROVE = 'recipes.approve';

    public const RECIPES_COST_VIEW = 'recipes.cost.view';

    public const RECIPES_EXPORT = 'recipes.export';

    // Supplier & Procurement Permissions
    public const SUPPLIERS_VIEW = 'suppliers.view';

    public const SUPPLIERS_CREATE = 'suppliers.create';

    public const SUPPLIERS_UPDATE = 'suppliers.update';

    public const SUPPLIERS_DELETE = 'suppliers.delete';

    public const PROCUREMENT_VIEW = 'procurement.view';

    public const PROCUREMENT_CREATE = 'procurement.create';

    public const PROCUREMENT_APPROVE = 'procurement.approve';

    public const PROCUREMENT_RECEIVE = 'procurement.receive';

    public const PROCUREMENT_PAY = 'procurement.pay';

    // Production & Processing Permissions
    public const PRODUCTION_VIEW = 'production.view';

    public const PRODUCTION_CREATE = 'production.create';

    public const PRODUCTION_START = 'production.start';

    public const PRODUCTION_COMPLETE = 'production.complete';

    public const PRODUCTION_CANCEL = 'production.cancel';

    public const PRODUCTION_DELETE = 'production.delete';

    // HACCP & Food Safety Permissions
    public const HACCP_VIEW = 'haccp.view';

    public const HACCP_LOG = 'haccp.log';

    public const HACCP_MANAGE = 'haccp.manage';

    public const HACCP_REPORTS = 'haccp.reports';

    public const POS_REFUNDS = 'pos.refunds';

    public const POS_DISCOUNTS = 'pos.discounts';

    public const POS_REPORTS = 'pos.reports';

    public const POS_SETTINGS = 'pos.settings';

    public const POS_DEVICES = 'pos.devices';

    public const POS_KDS = 'pos.kds';

    // Fine-grained Waiter permissions
    public const WORKSPACE_WAITER_ACCESS = 'workspace.waiter.access';

    public const WORKSPACE_CASHIER_ACCESS = 'workspace.cashier.access';

    public const WORKSPACE_MANAGER_ACCESS = 'workspace.manager.access';

    public const WORKSPACE_KITCHEN_ACCESS = 'workspace.kitchen.access';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const RESERVATIONS_VIEW = 'reservations.view';

    public const RESERVATIONS_MANAGE = 'reservations.manage';

    public const CUSTOMERS_MANAGE = 'customers.manage';

    // Loyalty permissions
    public const LOYALTY_VIEW = 'loyalty.view';

    public const LOYALTY_MANAGE = 'loyalty.manage';

    public const LOYALTY_OPERATE = 'loyalty.operate';

    public const LOYALTY_ADJUST = 'loyalty.adjust';

    public const ROLES_VIEW = 'roles.view';

    public const ROLES_MANAGE = 'roles.manage';

    public const SECURITY_VIEW = 'security.view';

    public const SECURITY_MANAGE = 'security.manage';

    public const AI_MENU_BUILDER = 'ai.menu_builder';

    public const PLATFORM_SECURITY = 'platform.security';

    public const ORDERS_VIEW_OWN = 'orders.view_own';

    public const ORDERS_CREATE = 'orders.create';

    public const ORDERS_UPDATE_OWN = 'orders.update_own';

    public const ORDERS_SEND_TO_KITCHEN = 'orders.send_to_kitchen';

    public const ORDERS_CANCEL_REQUEST = 'orders.cancel_request';

    public const TABLES_VIEW_ASSIGNED = 'tables.view_assigned';

    public const TIPS_VIEW_OWN = 'tips.view_own';

    public const PROFILE_VIEW_OWN = 'profile.view_own';

    public const PROFILE_UPDATE_OWN = 'profile.update_own';

    public const PASSWORD_CHANGE_OWN = 'password.change_own';

    public const PIN_MANAGE_OWN = 'pin.manage_own';

    // Fine-grained Cashier permissions
    public const ORDERS_SEARCH = 'orders.search';

    public const ORDERS_VIEW_DETAILS = 'orders.view_details';

    public const PAYMENTS_CREATE = 'payments.create';

    public const PAYMENTS_CASH = 'payments.cash';

    public const PAYMENTS_CARD = 'payments.card';

    public const PAYMENTS_SPLIT = 'payments.split';

    public const RECEIPTS_PRINT = 'receipts.print';

    public const SHIFTS_OPEN = 'shifts.open';

    public const SHIFTS_CLOSE = 'shifts.close';

    public const SHIFTS_VIEW_OWN = 'shifts.view_own';

    public const REFUNDS_REQUEST = 'refunds.request';

    // Fine-grained Manager permissions
    public const ORDERS_VIEW_ALL = 'orders.view_all';

    public const ORDERS_MANAGE = 'orders.manage';

    public const ORDERS_APPROVE_CANCELLATION = 'orders.approve_cancellation';

    public const DISCOUNTS_APPROVE = 'discounts.approve';

    public const REFUNDS_APPROVE = 'refunds.approve';

    public const EMPLOYEES_VIEW = 'employees.view';

    public const EMPLOYEES_MANAGE = 'employees.manage';

    public const TABLES_MANAGE = 'tables.manage';

    public const SHIFTS_VIEW_ALL = 'shifts.view_all';

    public const AUDIT_LOGS_VIEW = 'audit_logs.view';

    // Platform (SuperAdmin) permissions
    public const PLATFORM_ACCESS = 'platform.access';

    public const PLATFORM_VENDORS = 'platform.vendors';

    public const PLATFORM_SUBSCRIPTIONS = 'platform.subscriptions';

    public const PLATFORM_SETTINGS = 'platform.settings';

    public const PLATFORM_LOGS = 'platform.logs';

    public const PLATFORM_LANGUAGES = 'platform.languages';

    public const PLATFORM_EMAILS = 'platform.emails';

    // HR & Workforce Management Permissions
    public const HR_DEPARTMENTS_VIEW = 'hr.departments.view';

    public const HR_DEPARTMENTS_MANAGE = 'hr.departments.manage';

    public const HR_POSITIONS_VIEW = 'hr.positions.view';

    public const HR_POSITIONS_MANAGE = 'hr.positions.manage';

    public const HR_CONTRACTS_VIEW = 'hr.contracts.view';

    public const HR_CONTRACTS_MANAGE = 'hr.contracts.manage';

    public const HR_SCHEDULES_VIEW = 'hr.schedules.view';

    public const HR_SCHEDULES_MANAGE = 'hr.schedules.manage';

    public const HR_ATTENDANCE_VIEW = 'hr.attendance.view';

    public const HR_ATTENDANCE_MANAGE = 'hr.attendance.manage';

    public const HR_ATTENDANCE_CLOCK = 'hr.attendance.clock';

    public const HR_TIMESHEETS_VIEW = 'hr.timesheets.view';

    public const HR_TIMESHEETS_MANAGE = 'hr.timesheets.manage';

    public const HR_TIMESHEETS_APPROVE = 'hr.timesheets.approve';

    public const HR_LEAVE_VIEW = 'hr.leave.view';

    public const HR_LEAVE_REQUEST = 'hr.leave.request';

    public const HR_LEAVE_APPROVE = 'hr.leave.approve';

    public const HR_PAYROLL_VIEW = 'hr.payroll.view';

    public const HR_PAYROLL_CALCULATE = 'hr.payroll.calculate';

    public const HR_PAYROLL_APPROVE = 'hr.payroll.approve';

    public const HR_PAYROLL_LOCK = 'hr.payroll.lock';

    public const HR_PAYROLL_PAY = 'hr.payroll.pay';

    public const HR_PAYROLL_VIEW_SENSITIVE = 'hr.payroll.view_sensitive';

    public const HR_ADVANCES_VIEW = 'hr.advances.view';

    public const HR_ADVANCES_REQUEST = 'hr.advances.request';

    public const HR_ADVANCES_APPROVE = 'hr.advances.approve';

    public const HR_DOCUMENTS_VIEW = 'hr.documents.view';

    public const HR_DOCUMENTS_MANAGE = 'hr.documents.manage';

    public const HR_REPORTS_VIEW = 'hr.reports.view';

    public const HR_REPORTS_EXPORT = 'hr.reports.export';

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
            self::ORDERS_VIEW_OWN,
            self::ORDERS_CREATE,
            self::ORDERS_UPDATE_OWN,
            self::ORDERS_SEND_TO_KITCHEN,
            self::ORDERS_CANCEL_REQUEST,
            self::TABLES_VIEW_ASSIGNED,
            self::TIPS_VIEW_OWN,
            self::PROFILE_VIEW_OWN,
            self::PROFILE_UPDATE_OWN,
            self::PASSWORD_CHANGE_OWN,
            self::PIN_MANAGE_OWN,
            self::ORDERS_SEARCH,
            self::ORDERS_VIEW_DETAILS,
            self::PAYMENTS_CREATE,
            self::PAYMENTS_CASH,
            self::PAYMENTS_CARD,
            self::PAYMENTS_SPLIT,
            self::RECEIPTS_PRINT,
            self::SHIFTS_OPEN,
            self::SHIFTS_CLOSE,
            self::SHIFTS_VIEW_OWN,
            self::REFUNDS_REQUEST,
            self::ORDERS_VIEW_ALL,
            self::ORDERS_MANAGE,
            self::ORDERS_APPROVE_CANCELLATION,
            self::DISCOUNTS_APPROVE,
            self::REFUNDS_APPROVE,
            self::EMPLOYEES_VIEW,
            self::EMPLOYEES_MANAGE,
            self::TABLES_MANAGE,
            self::SHIFTS_VIEW_ALL,
            self::AUDIT_LOGS_VIEW,
            self::PLATFORM_ACCESS,
            self::PLATFORM_VENDORS,
            self::PLATFORM_SUBSCRIPTIONS,
            self::PLATFORM_SETTINGS,
            self::PLATFORM_LOGS,
            self::PLATFORM_LANGUAGES,
            self::PLATFORM_EMAILS,
            self::PLATFORM_SECURITY,
            self::DASHBOARD_VIEW,
            self::WORKSPACE_WAITER_ACCESS,
            self::WORKSPACE_CASHIER_ACCESS,
            self::WORKSPACE_MANAGER_ACCESS,
            self::WORKSPACE_KITCHEN_ACCESS,
            self::RESERVATIONS_VIEW,
            self::RESERVATIONS_MANAGE,
            self::CUSTOMERS_MANAGE,
            self::ROLES_VIEW,
            self::ROLES_MANAGE,
            self::SECURITY_VIEW,
            self::SECURITY_MANAGE,
            self::AI_MENU_BUILDER,
            self::INGREDIENTS_VIEW,
            self::INGREDIENTS_CREATE,
            self::INGREDIENTS_UPDATE,
            self::INGREDIENTS_DELETE,
            self::INGREDIENTS_EXPORT,
            self::INVENTORY_VIEW,
            self::INVENTORY_RECEIPT,
            self::INVENTORY_ISSUE,
            self::INVENTORY_TRANSFER,
            self::INVENTORY_ADJUST,
            self::INVENTORY_COUNT,
            self::INVENTORY_APPROVE,
            self::INVENTORY_WASTE,
            self::INVENTORY_REPORTS,
            self::RECIPES_VIEW,
            self::RECIPES_CREATE,
            self::RECIPES_UPDATE,
            self::RECIPES_DELETE,
            self::RECIPES_APPROVE,
            self::RECIPES_COST_VIEW,
            self::RECIPES_EXPORT,
            self::SUPPLIERS_VIEW,
            self::SUPPLIERS_CREATE,
            self::SUPPLIERS_UPDATE,
            self::SUPPLIERS_DELETE,
            self::PROCUREMENT_VIEW,
            self::PROCUREMENT_CREATE,
            self::PROCUREMENT_APPROVE,
            self::PROCUREMENT_RECEIVE,
            self::PROCUREMENT_PAY,
            self::PRODUCTION_VIEW,
            self::PRODUCTION_CREATE,
            self::PRODUCTION_START,
            self::PRODUCTION_COMPLETE,
            self::PRODUCTION_CANCEL,
            self::PRODUCTION_DELETE,
            self::HACCP_VIEW,
            self::HACCP_LOG,
            self::HACCP_MANAGE,
            self::HACCP_REPORTS,
            self::LOYALTY_VIEW,
            self::LOYALTY_MANAGE,
            self::LOYALTY_OPERATE,
            self::LOYALTY_ADJUST,
            self::HR_DEPARTMENTS_VIEW,
            self::HR_DEPARTMENTS_MANAGE,
            self::HR_POSITIONS_VIEW,
            self::HR_POSITIONS_MANAGE,
            self::HR_CONTRACTS_VIEW,
            self::HR_CONTRACTS_MANAGE,
            self::HR_SCHEDULES_VIEW,
            self::HR_SCHEDULES_MANAGE,
            self::HR_ATTENDANCE_VIEW,
            self::HR_ATTENDANCE_MANAGE,
            self::HR_ATTENDANCE_CLOCK,
            self::HR_TIMESHEETS_VIEW,
            self::HR_TIMESHEETS_MANAGE,
            self::HR_TIMESHEETS_APPROVE,
            self::HR_LEAVE_VIEW,
            self::HR_LEAVE_REQUEST,
            self::HR_LEAVE_APPROVE,
            self::HR_PAYROLL_VIEW,
            self::HR_PAYROLL_CALCULATE,
            self::HR_PAYROLL_APPROVE,
            self::HR_PAYROLL_LOCK,
            self::HR_PAYROLL_PAY,
            self::HR_PAYROLL_VIEW_SENSITIVE,
            self::HR_ADVANCES_VIEW,
            self::HR_ADVANCES_REQUEST,
            self::HR_ADVANCES_APPROVE,
            self::HR_DOCUMENTS_VIEW,
            self::HR_DOCUMENTS_MANAGE,
            self::HR_REPORTS_VIEW,
            self::HR_REPORTS_EXPORT,
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
                self::ORDERS_VIEW_ALL,
                self::ORDERS_MANAGE,
                self::ORDERS_APPROVE_CANCELLATION,
                self::DISCOUNTS_APPROVE,
                self::REFUNDS_APPROVE,
                self::EMPLOYEES_VIEW,
                self::EMPLOYEES_MANAGE,
                self::TABLES_MANAGE,
                self::SHIFTS_VIEW_ALL,
                self::AUDIT_LOGS_VIEW,
                self::ORDERS_SEARCH,
                self::ORDERS_VIEW_DETAILS,
                self::PAYMENTS_CREATE,
                self::PAYMENTS_CASH,
                self::PAYMENTS_CARD,
                self::PAYMENTS_SPLIT,
                self::RECEIPTS_PRINT,
                self::SHIFTS_OPEN,
                self::SHIFTS_CLOSE,
                self::SHIFTS_VIEW_OWN,
                self::REFUNDS_REQUEST,
                self::ORDERS_VIEW_OWN,
                self::ORDERS_CREATE,
                self::ORDERS_UPDATE_OWN,
                self::ORDERS_SEND_TO_KITCHEN,
                self::ORDERS_CANCEL_REQUEST,
                self::TABLES_VIEW_ASSIGNED,
                self::TIPS_VIEW_OWN,
                self::PROFILE_VIEW_OWN,
                self::PROFILE_UPDATE_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_LEAVE_REQUEST,
                self::HR_ADVANCES_REQUEST,
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
                self::ORDERS_VIEW_ALL,
                self::ORDERS_MANAGE,
                self::ORDERS_APPROVE_CANCELLATION,
                self::DISCOUNTS_APPROVE,
                self::REFUNDS_APPROVE,
                self::EMPLOYEES_VIEW,
                self::EMPLOYEES_MANAGE,
                self::TABLES_MANAGE,
                self::SHIFTS_VIEW_ALL,
                self::AUDIT_LOGS_VIEW,
                self::ORDERS_SEARCH,
                self::ORDERS_VIEW_DETAILS,
                self::PAYMENTS_CREATE,
                self::PAYMENTS_CASH,
                self::PAYMENTS_CARD,
                self::PAYMENTS_SPLIT,
                self::RECEIPTS_PRINT,
                self::SHIFTS_OPEN,
                self::SHIFTS_CLOSE,
                self::SHIFTS_VIEW_OWN,
                self::REFUNDS_REQUEST,
                self::ORDERS_VIEW_OWN,
                self::ORDERS_CREATE,
                self::ORDERS_UPDATE_OWN,
                self::ORDERS_SEND_TO_KITCHEN,
                self::ORDERS_CANCEL_REQUEST,
                self::TABLES_VIEW_ASSIGNED,
                self::TIPS_VIEW_OWN,
                self::INGREDIENTS_VIEW,
                self::INGREDIENTS_CREATE,
                self::INGREDIENTS_UPDATE,
                self::INGREDIENTS_DELETE,
                self::INGREDIENTS_EXPORT,
                self::INVENTORY_VIEW,
                self::INVENTORY_RECEIPT,
                self::INVENTORY_ISSUE,
                self::INVENTORY_TRANSFER,
                self::INVENTORY_ADJUST,
                self::INVENTORY_COUNT,
                self::INVENTORY_APPROVE,
                self::INVENTORY_WASTE,
                self::INVENTORY_REPORTS,
                self::RECIPES_VIEW,
                self::RECIPES_CREATE,
                self::RECIPES_UPDATE,
                self::RECIPES_DELETE,
                self::RECIPES_APPROVE,
                self::RECIPES_COST_VIEW,
                self::RECIPES_EXPORT,
                self::SUPPLIERS_VIEW,
                self::SUPPLIERS_CREATE,
                self::SUPPLIERS_UPDATE,
                self::SUPPLIERS_DELETE,
                self::PROCUREMENT_VIEW,
                self::PROCUREMENT_CREATE,
                self::PROCUREMENT_APPROVE,
                self::PROCUREMENT_RECEIVE,
                self::PROCUREMENT_PAY,
                self::PRODUCTION_VIEW,
                self::PRODUCTION_CREATE,
                self::PRODUCTION_START,
                self::PRODUCTION_COMPLETE,
                self::PRODUCTION_CANCEL,
                self::PRODUCTION_DELETE,
                self::HACCP_VIEW,
                self::HACCP_LOG,
                self::HACCP_MANAGE,
                self::HACCP_REPORTS,
                self::LOYALTY_VIEW,
                self::LOYALTY_MANAGE,
                self::LOYALTY_OPERATE,
                self::LOYALTY_ADJUST,
                self::HR_DEPARTMENTS_VIEW,
                self::HR_DEPARTMENTS_MANAGE,
                self::HR_POSITIONS_VIEW,
                self::HR_POSITIONS_MANAGE,
                self::HR_CONTRACTS_VIEW,
                self::HR_CONTRACTS_MANAGE,
                self::HR_SCHEDULES_VIEW,
                self::HR_SCHEDULES_MANAGE,
                self::HR_ATTENDANCE_VIEW,
                self::HR_ATTENDANCE_MANAGE,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_TIMESHEETS_VIEW,
                self::HR_TIMESHEETS_MANAGE,
                self::HR_TIMESHEETS_APPROVE,
                self::HR_LEAVE_VIEW,
                self::HR_LEAVE_REQUEST,
                self::HR_LEAVE_APPROVE,
                self::HR_PAYROLL_VIEW,
                self::HR_PAYROLL_CALCULATE,
                self::HR_PAYROLL_APPROVE,
                self::HR_PAYROLL_LOCK,
                self::HR_PAYROLL_PAY,
                self::HR_PAYROLL_VIEW_SENSITIVE,
                self::HR_ADVANCES_VIEW,
                self::HR_ADVANCES_REQUEST,
                self::HR_ADVANCES_APPROVE,
                self::HR_DOCUMENTS_VIEW,
                self::HR_DOCUMENTS_MANAGE,
                self::HR_REPORTS_VIEW,
                self::HR_REPORTS_EXPORT,
                self::PROFILE_VIEW_OWN,
                self::PROFILE_UPDATE_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_LEAVE_REQUEST,
                self::HR_ADVANCES_REQUEST,
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
                self::AI_VIEW,
                self::SETTINGS_VIEW,
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
                self::ORDERS_VIEW_ALL,
                self::ORDERS_MANAGE,
                self::ORDERS_APPROVE_CANCELLATION,
                self::DISCOUNTS_APPROVE,
                self::REFUNDS_APPROVE,
                self::EMPLOYEES_VIEW,
                self::TABLES_MANAGE,
                self::SHIFTS_VIEW_ALL,
                self::AUDIT_LOGS_VIEW,
                self::INGREDIENTS_VIEW,
                self::INGREDIENTS_CREATE,
                self::INGREDIENTS_UPDATE,
                self::INGREDIENTS_DELETE,
                self::INGREDIENTS_EXPORT,
                self::INVENTORY_VIEW,
                self::INVENTORY_RECEIPT,
                self::INVENTORY_ISSUE,
                self::INVENTORY_TRANSFER,
                self::INVENTORY_ADJUST,
                self::INVENTORY_COUNT,
                self::INVENTORY_APPROVE,
                self::RECIPES_VIEW,
                self::RECIPES_CREATE,
                self::RECIPES_UPDATE,
                self::RECIPES_DELETE,
                self::RECIPES_APPROVE,
                self::RECIPES_COST_VIEW,
                self::RECIPES_EXPORT,
                self::LOYALTY_VIEW,
                self::LOYALTY_MANAGE,
                self::LOYALTY_OPERATE,
                self::LOYALTY_ADJUST,
                self::HR_DEPARTMENTS_VIEW,
                self::HR_DEPARTMENTS_MANAGE,
                self::HR_POSITIONS_VIEW,
                self::HR_POSITIONS_MANAGE,
                self::HR_CONTRACTS_VIEW,
                self::HR_CONTRACTS_MANAGE,
                self::HR_SCHEDULES_VIEW,
                self::HR_SCHEDULES_MANAGE,
                self::HR_ATTENDANCE_VIEW,
                self::HR_ATTENDANCE_MANAGE,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_TIMESHEETS_VIEW,
                self::HR_TIMESHEETS_MANAGE,
                self::HR_TIMESHEETS_APPROVE,
                self::HR_LEAVE_VIEW,
                self::HR_LEAVE_REQUEST,
                self::HR_LEAVE_APPROVE,
                self::HR_PAYROLL_VIEW,
                self::HR_PAYROLL_CALCULATE,
                self::HR_PAYROLL_APPROVE,
                self::HR_PAYROLL_LOCK,
                self::HR_PAYROLL_PAY,
                self::HR_PAYROLL_VIEW_SENSITIVE,
                self::HR_ADVANCES_VIEW,
                self::HR_ADVANCES_REQUEST,
                self::HR_ADVANCES_APPROVE,
                self::HR_DOCUMENTS_VIEW,
                self::HR_DOCUMENTS_MANAGE,
                self::HR_REPORTS_VIEW,
                self::HR_REPORTS_EXPORT,
                self::PROFILE_VIEW_OWN,
                self::PROFILE_UPDATE_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_LEAVE_REQUEST,
                self::HR_ADVANCES_REQUEST,
            ],
            'staff' => [
                self::POS_ACCESS,
                self::POS_ORDERS,
                self::MENU_VIEW,
                self::LOYALTY_OPERATE,
                self::ORDERS_VIEW_OWN,
                self::ORDERS_CREATE,
                self::ORDERS_UPDATE_OWN,
                self::TABLES_VIEW_ASSIGNED,
                self::TIPS_VIEW_OWN,
                self::PROFILE_VIEW_OWN,
                self::PROFILE_UPDATE_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_LEAVE_REQUEST,
                self::HR_ADVANCES_REQUEST,
            ],
            'waiter' => [
                self::POS_ACCESS,
                self::POS_ORDERS,
                self::MENU_VIEW,
                self::LOYALTY_OPERATE,
                self::ORDERS_VIEW_OWN,
                self::ORDERS_CREATE,
                self::ORDERS_UPDATE_OWN,
                self::ORDERS_SEND_TO_KITCHEN,
                self::ORDERS_CANCEL_REQUEST,
                self::TABLES_VIEW_ASSIGNED,
                self::TIPS_VIEW_OWN,
                self::PROFILE_VIEW_OWN,
                self::PROFILE_UPDATE_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_LEAVE_REQUEST,
                self::HR_ADVANCES_REQUEST,
            ],
            'chef' => [
                self::POS_ACCESS,
                self::POS_KDS,
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE, // For kitchen prep status transitions (e.g. preparing -> ready)
                self::INGREDIENTS_VIEW,
                self::INVENTORY_VIEW,
                self::INVENTORY_ISSUE,
                self::RECIPES_VIEW,
                self::RECIPES_CREATE,
                self::RECIPES_UPDATE,
                self::RECIPES_COST_VIEW,
                self::PROFILE_VIEW_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
            ],
            'kitchen' => [
                self::POS_ACCESS,
                self::POS_KDS,
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_UPDATE,
                self::RECIPES_VIEW,
                self::PROFILE_VIEW_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
            ],
            'cashier' => [
                self::POS_ACCESS,
                self::POS_ORDERS,
                self::POS_PAYMENTS,
                self::POS_CASH_DRAWER,
                self::POS_DISCOUNTS,
                self::LOYALTY_VIEW,
                self::LOYALTY_OPERATE,
                self::MENU_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_SEARCH,
                self::ORDERS_VIEW_DETAILS,
                self::PAYMENTS_CREATE,
                self::PAYMENTS_CASH,
                self::PAYMENTS_CARD,
                self::PAYMENTS_SPLIT,
                self::RECEIPTS_PRINT,
                self::SHIFTS_OPEN,
                self::SHIFTS_CLOSE,
                self::SHIFTS_VIEW_OWN,
                self::REFUNDS_REQUEST,
                self::PROFILE_VIEW_OWN,
                self::PROFILE_UPDATE_OWN,
                self::PASSWORD_CHANGE_OWN,
                self::PIN_MANAGE_OWN,
                self::HR_ATTENDANCE_CLOCK,
                self::HR_LEAVE_REQUEST,
                self::HR_ADVANCES_REQUEST,
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
