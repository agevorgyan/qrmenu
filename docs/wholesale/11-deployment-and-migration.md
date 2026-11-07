# B2B Wholesale POS & Distribution Subsystem
## 11 — Deployment, Database Migration & Rollback Strategy
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Zero-Downtime Deployment Strategy

The Wholesale POS & Distribution subsystem introduces completely new domain tables and does not alter existing retail orders, POS tables, or existing retail product catalogs. 

1. **Non-Breaking Schema Changes**: All migrations create new `wholesale_*` tables and `tenant_module_access` tables. Existing tables receive zero schema drops or mandatory column additions.
2. **Feature-Flagged Activation**: Module access is default-disabled for 100% of tenants, ensuring zero exposure until explicit Super Admin grant.
3. **Backward Compatibility**: Retail POS and menu functionality continue without modification or degradation.

---

## 2. Migration Execution Order

```bash
# 1. Super Admin Access Gating Engine
php artisan migrate --path=database/migrations/2026_11_08_000001_create_tenant_module_access_tables.php

# 2. Wholesale Seller Management & Tax Profiles
php artisan migrate --path=database/migrations/2026_11_08_000002_create_wholesale_sellers_and_tax_profiles_tables.php

# 3. Independent Wholesale Catalog & ATGAA Classification
php artisan migrate --path=database/migrations/2026_11_08_000003_create_wholesale_catalog_and_classification_tables.php

# 4. B2B Customers & Wholesale Pricing Engine
php artisan migrate --path=database/migrations/2026_11_08_000004_create_wholesale_customers_and_pricing_tables.php

# 5. Wholesale Orders, Pre-Orders & Sales
php artisan migrate --path=database/migrations/2026_11_08_000005_create_wholesale_orders_and_sales_tables.php

# 6. Deliveries, Logistics & Returns
php artisan migrate --path=database/migrations/2026_11_08_000006_create_wholesale_deliveries_and_returns_tables.php

# 7. Wholesale Payments & Accounts Receivable
php artisan migrate --path=database/migrations/2026_11_08_000007_create_wholesale_payments_and_ar_tables.php

# 8. Tax Documents & PEK XML Exports
php artisan migrate --path=database/migrations/2026_11_08_000008_create_wholesale_tax_documents_and_exports_tables.php
```

---

## 3. Rollback & Disaster Recovery Procedures

Each migration implements a clean `down()` method that drops created tables in reverse dependency order.

If a rollback is required:
```bash
php artisan migrate:rollback --step=8
```
Because no retail tables or data are touched by wholesale migrations, rollback carries zero risk to retail restaurant data or active POS orders.

