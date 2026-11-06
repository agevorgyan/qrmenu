# PHASE 0 — ARCHITECTURE ANALYSIS & IMPLEMENTATION SPECIFICATION
## QR Menu: Inventory, Procurement, Recipe Costing, Production & HACCP Management System

---

### Executive Summary

This document establishes the comprehensive architectural analysis, integration blueprint, risk mitigation matrix, and step-by-step phased execution roadmap for integrating an enterprise-grade **Inventory, Procurement, Recipe Costing, Production & HACCP Management System** into the existing **QR Menu SaaS** platform (`elab.menu`).

The addition transforms the platform from a digital QR Menu, Ordering & POS suite into a unified **Restaurant & Food Production Management SaaS Platform**, while strictly maintaining:
1. Zero disruption to existing active modules (Menu, Online Orders, POS, Tables, Waiter Calls, AI Waiter, Billing).
2. Deep architectural conformance with existing multi-tenant isolation patterns (`TenantContext`, `TenantScope`, `BelongsToVendor`).
3. Native integration with the established Fine-Grained RBAC engine (`PermissionCatalog`, `AuthorizationService`).
4. Native integration with SaaS Plan & Quota management (`FeatureCatalog`, `QuotaCatalog`, `EntitlementService`, `SubscriptionService`).
5. Design system unity (Tailwind CSS v4, Blade, Alpine.js, centralized UI components).
6. Full multilingual support across Armenian (`hy`), English (`en`), and Russian (`ru`).
7. 100% test-backed, transaction-safe, idempotent operations.

---

## 1. Codebase & Technology Stack Baseline

| Component | Current Technology & Version | Inventory & Production Role |
| :--- | :--- | :--- |
| **Framework** | Laravel Framework `13.32.0` (PHP `8.5`) | Core REST API, Controllers, FormRequests, Policies, Console Commands |
| **Database** | PostgreSQL `18.3` (102 existing tables in schema `public`) | Relational storage, composite foreign keys, transactional integrity, JSONB |
| **Frontend UI** | Laravel Blade + Tailwind CSS `v4` + Alpine.js | Responsive Admin & Operation panels, mobile-first warehouse/kitchen views |
| **Asset Pipeline** | Vite `8.0` + Tailwind Vite Plugin | Fast asset compilation, zero mock data |
| **Real-time Engine** | Laravel Reverb `1.11.1` (WebSockets) | Real-time order alerts, KDS updates, CCP temperature violation broadcasts |
| **Testing Engine** | PHPUnit `12.5.35` (1,166 feature tests, 42 unit tests) | Unit, integration, multi-tenant isolation, concurrency & edge-case tests |
| **Code Formatter** | Laravel Pint `1.32.1` | Automated formatting according to project standards |
| **File Storage** | `StorageService` + `VendorStorageFile` (local/s3) | Technical sheets, supplier invoices, certificates, inspection proof |

---

## 2. Multi-Tenant Isolation Architecture

### 2.1. Established Tenancy Rules
The platform uses single-database multi-tenancy where **Vendor** (`vendors` table) represents the Root Tenant.

1. **`TenantContext`**:
   - Manages the active `$tenantId` per HTTP request (via session, domain, or route context) and background job (`runInTenantContext`).
2. **`BelongsToVendor` Trait & `TenantScope`**:
   - Automatically attaches `TenantScope` to all tenant-owned models.
   - Automatically stamps `vendor_id` on model creation.
   - Throws `\InvalidArgumentException('Cross-tenant entity creation forbidden.')` if `vendor_id` mismatch occurs.
   - Guards against cross-tenant mutation during model updates.
3. **Defense-in-Depth for New Entities**:
   - **All** new tables (`ingredients`, `recipes`, `warehouses`, `suppliers`, `purchase_orders`, `production_orders`, `haccp_plans`, etc.) **must** include `vendor_id` constrained to `vendors(id) ON DELETE CASCADE`.
   - Every new Model **must** use `App\Models\Traits\BelongsToVendor`.
   - Every Controller and FormRequest **must** validate foreign keys (`warehouse_id`, `supplier_id`, `ingredient_id`) belong to the current authenticated vendor.
   - Database queries **must not** bypass `TenantScope` except inside audited SuperAdmin administrative reporting.

---

## 3. Subscription & Entitlement Architecture

### 3.1. Existing SaaS Plans in Database
Database inspection confirms three active tiers:
1. **Basic** (`slug: basic`) — ID: 1
2. **Pro** (`slug: pro`) — ID: 2
3. **Advanced** (`slug: advanced`) — ID: 5

*(Legacy plan `business` is archived).*

### 3.2. Tier Allocation Matrix for New Features

| Feature / Limit Key | Basic | Pro | Advanced | Description |
| :--- | :---: | :---: | :---: | :--- |
| `inventory.catalog` | Enabled | Enabled | Enabled | Raw materials, ingredient catalog, units, categories |
| `inventory.recipes.basic` | Enabled | Enabled | Enabled | Single-level recipes, basic portion costing |
| `inventory.warehouses.limit` | 1 | 3 | Unlimited (-1) | Physical/virtual storage locations |
| `inventory.suppliers.limit` | 3 | 25 | Unlimited (-1) | Registered suppliers |
| `inventory.procurement` | Disabled | Enabled | Enabled | Purchase requests, purchase orders, goods receipts |
| `inventory.supplier_debts` | Disabled | Enabled | Enabled | Supplier invoices, debt ledger, payment allocation |
| `inventory.batches` | Disabled | Enabled | Enabled | LOT/Batch tracking, expiry dates, FEFO/FIFO |
| `inventory.semi_finished` | Disabled | Enabled | Enabled | Semi-finished products (nested BOMs) |
| `inventory.production` | Disabled | Enabled | Enabled | Production orders, planned vs actual consumption |
| `inventory.advanced_costing` | Disabled | Enabled | Enabled | Labor, overheads, yield variance, cost history |
| `inventory.haccp.basic` | Disabled | Enabled | Enabled | Basic temperature logs, daily cleaning schedules |
| `inventory.haccp.full` | Disabled | Disabled | Enabled | Full HACCP plans, hazard analysis, CCP/OPRP, calibration |
| `inventory.traceability` | Disabled | Disabled | Enabled | One-step-back / One-step-forward lot tracing |
| `inventory.product_recall` | Disabled | Disabled | Enabled | Product recall workflows & batch quarantining |
| `inventory.advanced_reports` | Disabled | Disabled | Enabled | Food cost variance, waste analytics, CAPA tracking |

### 3.3. Integration Points
- Add canonical feature constants to `app/Security/FeatureCatalog.php`.
- Add quota limits and aliases to `app/Security/QuotaCatalog.php`.
- Protect web routes via `EnsurePlanHasFeature::class . ':feature_code'`.
- Verify resource counts in FormRequests/Services via `app(QuotaService::class)->assertQuotaAvailable($vendor, $quotaKey)`.

---

## 4. Role-Based Access Control (RBAC) Architecture

### 4.1. Existing Engine
Permissions are canonicalized in `app/Security/Permission.php` and cataloged in `app/Security/PermissionCatalog.php`.
Authorization is evaluated through `app/Services/AuthorizationService.php`, which enforces:
- Explicit user denial (`denied`)
- SuperAdmin platform scope check
- Vendor membership active status
- Explicit user allowance (`allowed`)
- Role-based permissions (`effective`)

### 4.2. New Granular Permissions Catalog

```php
// 1. Ingredients & Catalog
'ingredients.view'        => 'Browse ingredients, raw materials and units',
'ingredients.create'      => 'Add new ingredients and raw materials',
'ingredients.update'      => 'Edit ingredient specifications and minimum levels',
'ingredients.delete'      => 'Delete ingredients (soft delete)',
'ingredients.export'      => 'Export catalog to CSV / Excel',

// 2. Recipes & Costing
'recipes.view'            => 'View recipes and technical cards',
'recipes.create'          => 'Create new recipes and nested technical cards',
'recipes.update'          => 'Edit recipe items, steps and yields',
'recipes.approve'         => 'Approve and release technical cards',
'recipes.archive'         => 'Archive deprecated recipe versions',
'recipes.cost.view'       => 'View detailed food costing, labor and profit margins',

// 3. Warehouses & Inventory Ledger
'inventory.view'          => 'View warehouse balances and movements',
'inventory.receipt'       => 'Perform stock receipts',
'inventory.issue'         => 'Perform stock issue / deduction',
'inventory.transfer'      => 'Transfer stock between warehouses',
'inventory.adjust'        => 'Create stock adjustments / write-offs',
'inventory.count'         => 'Conduct periodic physical inventory counts',
'inventory.approve'       => 'Approve stock adjustments and count variances',

// 4. Suppliers & Procurement
'suppliers.view'          => 'View suppliers directory and price histories',
'suppliers.manage'        => 'Create and edit supplier profiles',
'procurement.view'        => 'View purchase orders and goods receipts',
'procurement.create'      => 'Create purchase requests and orders',
'procurement.approve'     => 'Approve purchase orders for fulfillment',
'procurement.receive'     => 'Receive goods and inspect batches',
'procurement.payments'    => 'Manage supplier invoices, debts and payments',

// 5. Production
'production.view'         => 'View production orders and batch outputs',
'production.create'       => 'Create and schedule production orders',
'production.approve'      => 'Approve production and reserve materials',
'production.start'        => 'Mark production order in-progress',
'production.complete'     => 'Record actual consumption, yields and batch creation',
'production.cancel'       => 'Cancel production order and release reservations',

// 6. HACCP & Food Safety
'haccp.view'              => 'View HACCP plans, CCPs, logs and checklists',
'haccp.manage'            => 'Configure hazards, CCP limits and cleaning programs',
'haccp.logs.create'       => 'Log temperature readings, hygiene and calibration',
'haccp.corrective_action' => 'Open, assign and close CAPA incidents',
'haccp.audit'             => 'View audit logs, compliance reports and verification',

// 7. Traceability & Recall
'traceability.view'       => 'Perform forward and backward batch tracing',
'traceability.recall'     => 'Initiate and manage product recall campaigns'
```

---

## 5. Existing Codebase Integration Map & Anti-Duplication Strategy

### 5.1. Existing Inventory State in QR Menu
The repository currently contains:
- `products`: Has `tracks_inventory` (boolean), `stock_quantity` (decimal), `low_stock_threshold` (decimal).
- `pos_inventory_movements`: Basic ledger logging direct `sale`, `restock`, `waste`, `refund` on finished `products`.
- `PosInventoryService`: Handles direct stock decrements for POS orders.

### 5.2. Integration & Transition Strategy
1. **Preserve Current POS Flow (No Regression)**:
   - When a product **does not** have an active Recipe linked, `PosInventoryService` continues operating on the `stock_quantity` column as before.
2. **Recipe-Linked POS & Online Order Deduction**:
   - When a Product has a linked `Recipe`, order confirmation/payment triggers the new `InventoryDeductionService`.
   - The service resolves the recipe components (and nested semi-finished components), identifies available batches using **FEFO/FIFO**, deducts the corresponding ingredient ledger balances, and updates availability.
3. **Idempotency Guarantee**:
   - Inventory movements are indexed with unique `(vendor_id, order_id, order_item_id, movement_type)`. Duplicate webhook or POS retry requests will safely short-circuit without double deductions.

### 5.3. Menu & Recipe Relationship
- `products` (existing) represents what is sold on the QR Menu and POS.
- `recipes` (new) defines how an item is constructed, cooked, portioned, and costed.
- A 1-to-1 or 1-to-Many relationship allows a `product` (or `product_variation`) to point to a `recipe_id`.

---

## 6. Target Database Schema & Entity Relationships

```
[ vendors ] (Root Tenant)
   │
   ├── [ ingredient_categories ] (Hierarchical)
   ├── [ units ] & [ unit_conversions ]
   ├── [ ingredients ] (Raw materials, packaging, consumables)
   │      ├── [ ingredient_allergens ] (Links to existing allergens table)
   │      └── [ ingredient_suppliers ] (Price contracts & supplier SKUs)
   │
   ├── [ recipes ] (Finished dishes, semi-finished products)
   │      ├── [ recipe_versions ] (Approval states: draft, approved, active, archived)
   │      ├── [ recipe_items ] (Ingredients or nested semi-finished recipes)
   │      └── [ recipe_costs ] (Cost snapshots: materials, labor, overheads)
   │
   ├── [ warehouses ] (Main, Kitchen, Cold Room, Freezer)
   │      ├── [ inventory_balances ] (Cached current on-hand & reserved per warehouse)
   │      ├── [ inventory_transactions ] & [ items ] (Immutable Ledger)
   │      ├── [ stock_reservations ] (Order & production locks)
   │      └── [ stock_counts ] & [ items ] (Periodic auditing)
   │
   ├── [ batches ] (LOT tracking, expiry, supplier reference, quarantine status)
   │
   ├── [ suppliers ] (Vendor procurement directory)
   │      ├── [ purchase_orders ] & [ items ]
   │      ├── [ goods_receipts ] & [ items ]
   │      ├── [ supplier_invoices ] (Debts & payment terms)
   │      └── [ supplier_payments ] & [ allocations ]
   │
   ├── [ production_orders ] (Batch cooking & semi-finished processing)
   │      ├── [ production_consumptions ] (Actual material consumed)
   │      ├── [ production_outputs ] (Yielded product & newly generated LOTs)
   │      └── [ production_losses ] (Waste & shrinkage)
   │
   ├── [ haccp_plans ] & [ hazard_analyses ]
   │      ├── [ control_points ] (CCPs & OPRPs with critical limits)
   │      ├── [ temperature_logs ] (Equipment & walk-in readings)
   │      ├── [ cleaning_schedules ] & [ cleaning_records ]
   │      ├── [ equipment_calibrations ]
   │      ├── [ non_conformances ] & [ corrective_actions ] (CAPA)
   │      └── [ traceability_records ] & [ recall_cases ]
   │
   └── [ audit_logs ] (Immutable security & inventory compliance audit trail)
```

---

## 7. Business Logic & Mathematical Formulas

### 7.1. Unit Conversions
- Conversions within same physical dimension (mass: `kg` $\leftrightarrow$ `g` $\leftrightarrow$ `ton`; volume: `l` $\leftrightarrow$ `ml`) use standard metric ratios.
- Conversions across dimensions (mass $\leftrightarrow$ volume $\leftrightarrow$ pieces) require an explicit item-specific `density` or `conversion_rate` (e.g. 1 liter olive oil = 0.92 kg).
- **Hard Rule**: The system strictly blocks automatic uncalibrated cross-dimension conversions.

### 7.2. Yield & Weight Adjustments
$$\text{Net Weight} = \text{Gross Weight} \times (1 - \text{Preparation Loss \%})$$
$$\text{Final Yield} = \text{Net Weight} \times (1 - \text{Cooking Loss \%})$$

### 7.3. Recipe Costing
$$\text{Ingredient Cost} = \text{Quantity} \times \text{Unit Cost}$$
$$\text{Total Recipe Cost} = \sum(\text{Ingredient Costs}) + \text{Packaging} + \text{Labor} + \text{Overhead}$$
$$\text{Portion Cost} = \frac{\text{Total Recipe Cost}}{\text{Actual Yield Portions}}$$
$$\text{Food Cost \%} = \left(\frac{\text{Portion Cost}}{\text{Selling Price}}\right) \times 100$$
$$\text{Gross Profit} = \text{Selling Price} - \text{Portion Cost}$$
$$\text{Profit Margin \%} = \left(\frac{\text{Gross Profit}}{\text{Selling Price}}\right) \times 100$$

### 7.4. Inventory Valuation: Weighted Average & FIFO/FEFO
- **Weighted Average Cost (WAC)**:
$$\text{New Average Cost} = \frac{(\text{Current Qty} \times \text{Current Cost}) + (\text{Received Qty} \times \text{Received Cost})}{\text{Current Qty} + \text{Received Qty}}$$
- **FEFO (First Expired, First Out)**:
  Stock consumption queries order candidate batches by `expiry_date ASC, id ASC`, ensuring items closest to expiration are allocated first.
- **Stock Availability**:
$$\text{Available Stock} = \text{On-Hand Stock} - \text{Reserved Stock}$$

---

## 8. Architectural Risks & Mitigation Matrix

| Risk ID | Identified Risk Description | Impact | Architectural Mitigation Strategy |
| :---: | :--- | :---: | :--- |
| **R-01** | **Circular Reference in Nested Recipes** (e.g., Recipe A includes Recipe B, which includes Recipe A). | **Critical** (Stack overflow / infinite recursion) | Directed Acyclic Graph (DAG) cycle validator in `RecipeService` before saving any sub-recipe item. |
| **R-02** | **Race Condition on Concurrent Stock Allocation** (two POS cashiers or production orders claiming the last 5 kg simultaneously). | **High** (Negative inventory / overselling) | Database row-level locking (`SELECT ... FOR UPDATE`) inside atomic database transactions on `inventory_balances`. |
| **R-03** | **Double Inventory Deduction between POS & KDS**. | **High** (Inaccurate balances, financial distortion) | State-machine driven idempotency. Stock is reserved upon order confirmation and permanently committed upon prep/delivery, keyed by `(order_id, item_id)`. |
| **R-04** | **Retroactive Financial Corruption** (Modifying an ingredient cost retroactively alters closed historical sales or production orders). | **Critical** (Accounting mismatch) | Strict cost snapshotting. Closed production runs and historical sales orders store the immutable cost calculated at execution time. |
| **R-05** | **Cross-Tenant Data Exposure** via bulk exports or deep relational joins. | **Critical** (Tenant privacy breach) | Enforce `BelongsToVendor` on every model, validate `vendor_id` on every query, and run automated cross-tenant security test suites. |
| **R-06** | **Unsafe Temperature & HACCP Overwrite** (Staff tampering with recorded temperature violations to hide non-compliance). | **High** (Regulatory violation / food safety) | Temperature logs and CCP deviations are append-only. Edits create an audited correction entry with supervisor signature and CAPA linkage. |
| **R-07** | **Decimal Precision Loss** during multi-step unit conversions. | **Medium** (Discrepancies in balance sheets) | Standardize all quantities and monetary values to `DECIMAL(12, 4)` and `DECIMAL(12, 2)` respectively; never use floating-point types. |

---

## 9. Phased Implementation Roadmap

```
Phase 0: Architecture Analysis & Specification (COMPLETE)
   │
Phase 1: Foundation (Ingredients, Categories, Units, Warehouses, Balances, Ledger)
   │
Phase 2: Recipe Management & Costing (Technical Cards, Versions, Nested BOM, Food Cost %)
   │
Phase 3: Suppliers, Procurement & Payables (POs, Receipts, Invoices, Debt Ledger, Payments)
   │
Phase 4: Batches, Expiry & Production (LOTs, FEFO/FIFO, Production Orders, Yields, Losses)
   │
Phase 5: QR Menu & POS Integration (Recipe-to-Product link, Idempotent deductions, Availability)
   │
Phase 6: HACCP & Food Safety (Plans, CCPs, Temperature Logs, Cleaning, CAPA, Traceability, Recall)
   │
Phase 7: Analytics, Reporting & Subscriptions (Dashboards, Entitlements, Quotas, Usage Meters)
   │
Phase 8: Hardening, Automated Testing & Documentation (Unit, Integration, Multi-Tenant Tests, Audit)
```

---

## 10. Verification & Readiness Criteria
- [x] Codebase structure, database schema, multi-tenancy, RBAC, and subscription systems completely mapped.
- [x] Integration points with QR Menu, Orders, POS, and KDS identified without conflicting with existing code.
- [x] Anti-duplication strategy confirmed (reusing existing allergens, storage, and user models).
- [x] Database migrations, permissions, and service layer planned with strict backward compatibility.
- [x] Document published at `docs/inventory-production/01-architecture-analysis.md`.

