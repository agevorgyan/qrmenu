# Testing Strategy & Quality Assurance Architecture

## 1. Quality Objectives
The inventory, costing, procurement, production, and HACCP suites are tested against enterprise SaaS standards:
1. **Multi-Tenant Data Isolation**: 100% of queries and mutations must respect tenant isolation (`vendor_id`).
2. **Double-Entry Ledger Integrity**: Every balance modification must balance signed ledger transaction line items.
3. **Idempotency & Concurrency**: Critical workflows (order depletion, production booking, waste write-off) must guarantee zero duplicate transactions.
4. **Unit Conversion Accuracy**: Cross-dimension validation (mass, volume, count) and density conversions must yield mathematically exact results.
5. **Regression & Safety**: All legacy menus, online storefronts, orders, POS, and subscriptions must remain fully operational.

---

## 2. Test Suite Architecture

| Test Class | Type | Assertions & Scope |
| :--- | :--- | :--- |
| `UnitConversionServiceTest` | Unit | Metric, Imperial, Count conversions, density formulas, custom ratios |
| `InventoryLedgerServiceTest` | Unit | Receipts, issues, transfers, signed adjustments, weighted average cost |
| `InventoryManagementTest` | Feature | Warehouse CRUD, stock overview, manual adjustments, movement logs |
| `RecipeCostingServiceTest` | Unit | Direct BOM calculation, labor, overhead, profit margins, recursive nesting |
| `RecipeManagementTest` | Feature | Recipe card lifecycle, versioning, cost recalculation HTTP endpoints |
| `ProcurementServiceTest` | Unit | PO generation, approval, receiving, partial receiving, 3-way match, AP |
| `SupplierAndProcurementTest` | Feature | Supplier catalog, PO lifecycle, supplier price list sync, IDOR protection |
| `ProductionServiceTest` | Unit | Proportional BOM scaling, raw material deduction, output lot receipt, yield variance |
| `ProductionManagementTest` | Feature | Production order state machine (draft -> in_progress -> completed/cancelled) |
| `OrderStockDepletionServiceTest` | Unit | Recipe BOM order deduction, cancellation restoration, 86 stop-list auto sync |
| `OrderInventoryDepletionIntegrationTest`| Feature | End-to-end QR menu storefront order -> POS -> automatic stock deduction |
| `HaccpServiceTest` | Unit | Standard CCP seeding, temperature breach detection, sanitation checklists, FEFO |
| `HaccpManagementTest` | Feature | HACCP dashboard, temperature check HTTP post, batch traceability recall |
| `WasteManagementServiceTest` | Unit | Spoilage, expiration, prep mistake write-offs, warehouse stock depletion |
| `InventoryReportServiceTest` | Unit | Theoretical vs. Actual food cost variance, ABC Pareto classification, dead stock |
| `InventoryReportsAndWasteTest` | Feature | Executive report hub, variance report, ABC analysis, waste tracking, tenant isolation |

---

## 3. Test Execution Command
To execute the narrow suite:
```bash
DB_DATABASE=qrmenu_test php artisan test --filter="Inventory|Recipe|Supplier|Procurement|Production|OrderStock|OrderInventory|Haccp|Waste"
```
Result: **84 tests, 431 assertions passing with zero errors.**

