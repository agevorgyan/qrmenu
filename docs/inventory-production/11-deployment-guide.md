# Deployment & Operational Runbook

## 1. System Requirements
- **PHP**: 8.2+ (Targeting PHP 8.5)
- **Database**: PostgreSQL 15+ (Production on PostgreSQL 18)
- **Cache & Queue**: Redis 7+
- **Node.js**: 20+

---

## 2. Database Migrations
Run all database schema migrations across the production and staging databases:
```bash
php artisan migrate --force
```

### Migrations executed in this release:
1. `2026_11_06_000001_create_units_and_inventory_tables.php` (Units, Ingredients, Warehouses, Balances, Ledgers)
2. `2026_11_06_000002_create_recipes_and_technical_cards_tables.php` (Recipes, Items, Steps, Versions, Costs)
3. `2026_11_06_000003_create_suppliers_and_procurement_tables.php` (Suppliers, POs, Invoices, Payments)
4. `2026_11_06_000004_create_production_orders_and_batches_tables.php` (Production Orders, Items, Inventory Batches)
5. `2026_11_06_000005_create_haccp_and_food_safety_tables.php` (HACCP CCPs, Temperature Logs, Sanitation Checklists)
6. `2026_11_06_000006_create_waste_logs_tables.php` (Waste write-offs, Financial loss, Ledger links)

---

## 3. Post-Deployment Reconciliation
Run the authorization reconciliation command to sync all canonical permissions and system roles:
```bash
php artisan auth:reconcile-permissions
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 4. Frontend Asset Compilation
Compile Tailwind CSS v4 and Alpine.js assets:
```bash
npm run build
```

---

## 5. Background Jobs & Workers
Ensure queue workers are active for asynchronous order stock depletion and notification alerts:
```bash
php artisan queue:work redis --queue=default,high --tries=3 --timeout=90
```

---

## 6. Scheduled Tasks (Cron)
Add the standard Laravel scheduler to crontab:
```bash
* * * * * cd /path/to/qrmenu && php artisan schedule:run >> /dev/null 2>&1
```
Scheduled jobs:
- Daily audit of batches expiring in $\le 3$ days.
- Nightly calculation of ABC inventory valuation and turnover ratios.
- Automated stop-list verification for zero-balance raw materials.

