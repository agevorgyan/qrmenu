# B2B Wholesale POS & Distribution Subsystem
## 07 — Security, RBAC & Permissions Architecture
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Security Architecture Principles

1. **Super Admin Module Gating**: Wholesale POS is a restricted subsystem enabled per-tenant exclusively by Super Admin via `tenant_module_access`.
2. **Tenant Isolation**: Every database operation, query, and cache key is scoped to the tenant's `vendor_id`.
3. **Role-Based Access Control**: Granular permissions control viewing, creating, updating, discounting, approving, and exporting wholesale records.
4. **Audit Trail**: All critical events (access grants, price overrides, tax generation, exports) are recorded immutably.

---

## 2. Wholesale Permissions Catalog

The following permissions are defined in `App\Security\Permission`:

```php
// Super Admin Module Entitlement
public const WHOLESALE_MODULE_MANAGE = 'wholesale.module.manage'; // Superadmin only

// Wholesale General & POS
public const WHOLESALE_POS_ACCESS = 'wholesale.pos.access';
public const WHOLESALE_PRICE_OVERRIDE = 'wholesale.price.override';

// Orders & Sales
public const WHOLESALE_ORDERS_VIEW = 'wholesale.orders.view';
public const WHOLESALE_ORDERS_CREATE = 'wholesale.orders.create';
public const WHOLESALE_ORDERS_CONFIRM = 'wholesale.orders.confirm';
public const WHOLESALE_ORDERS_CANCEL = 'wholesale.orders.cancel';

// Product Catalog & Pricing
public const WHOLESALE_CATALOG_VIEW = 'wholesale.catalog.view';
public const WHOLESALE_CATALOG_MANAGE = 'wholesale.catalog.manage';
public const WHOLESALE_PRICING_MANAGE = 'wholesale.pricing.manage';

// Customers & Sellers
public const WHOLESALE_CUSTOMERS_MANAGE = 'wholesale.customers.manage';
public const WHOLESALE_SELLERS_MANAGE = 'wholesale.sellers.manage';

// Deliveries & Logistics
public const WHOLESALE_DELIVERIES_VIEW = 'wholesale.deliveries.view';
public const WHOLESALE_DELIVERIES_MANAGE = 'wholesale.deliveries.manage';

// Payments & Accounting
public const WHOLESALE_PAYMENTS_RECORD = 'wholesale.payments.record';
public const WHOLESALE_RECEIVABLES_VIEW = 'wholesale.receivables.view';

// Tax & PEK Export
public const WHOLESALE_TAX_DOCUMENTS_MANAGE = 'wholesale.tax.manage';
public const WHOLESALE_PEK_EXPORT = 'wholesale.pek.export';

// Reports
public const WHOLESALE_REPORTS_VIEW = 'wholesale.reports.view';
```

---

## 3. Role Mapping for Wholesale

| Role | Wholesale Permissions Granted |
| :--- | :--- |
| **Super Admin** | Platform-wide access; manages `tenant_module_access` and views audit logs. |
| **Vendor Owner** | Full wholesale administrative access across all sellers, customers, pricing, orders, deliveries, payments, tax, and exports. |
| **Wholesale Manager** | Orders, POS, Customers, Deliveries, Catalog, Pricing, Reports, Tax Documents. (Excludes seller tax profile alterations). |
| **Wholesale Sales Rep / Cashier** | Wholesale POS, Orders View/Create, Payments Record (cash/terminal), Catalog View. Cannot override minimum prices or export tax documents. |
| **Logistics / Driver** | Deliveries View, Status Update (Loaded -> Delivered), Upload Proof of Delivery. |
| **Accountant** | Tax Documents Manage, PEK XML Export, Receivables View, Payments Record, Reports View. |

---

## 4. Middleware & Request Protection

```php
Route::middleware([
    'auth',
    'identify.tenant',
    'ensure.wholesale.access', // Verifies tenant_module_access + permission
])->prefix('admin/wholesale')->name('admin.wholesale.')->group(function () {
    // Wholesale Web Routes
});
```

