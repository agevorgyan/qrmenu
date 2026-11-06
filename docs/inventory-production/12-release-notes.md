# Release Notes: Inventory, Costing, Production & HACCP Management System

## Version 2.0.0 — Production Release (November 2026)

### Highlights
We are proud to introduce the complete **Inventory, Procurement, Recipe Costing, Production & HACCP Management System** natively integrated into the QR Menu SaaS platform. This release connects customer table orders, POS counters, and kitchen prep directly with warehouse double-entry accounting and food safety compliance.

---

### Delivered Modules & Capabilities

#### 1. Unified Product & Raw Material Catalog (Modules 1, 2 & 5)
- Support for 7 inventory item types: Raw Materials, Ingredients, Semi-Finished components, Finished Dishes, Packaging, and Consumables.
- Comprehensive unit conversion system (Mass, Volume, Count) with density and custom packaging factor conversions.
- Warehouse location management: Main warehouses, Kitchen lines, Bar stations, Storage rooms, and Transit locations.
- Real-time stock valuation using Weighted Average Cost (WAC) and Last Purchase Price.

#### 2. Technical Cards & Recipe Costing (Modules 3 & 4)
- Bill of Materials (BOM) formulation with gross weight, net weight, peeling shrinkage %, and cooking loss %.
- Multi-level recursive nesting: Semi-finished recipes (sauces, broths, doughs) inside finished menu dishes.
- Comprehensive costing breakdown: Direct ingredient costs, packaging, labor, overhead %, and target gross profit margins.
- Immutable versioning history and one-click recalculation across all active recipes.

#### 3. Suppliers, Procurement & Accounts Payable (Modules 7, 8 & 9)
- Vendor catalog management with supplier SKUs and custom package unit conversions.
- Purchase Order lifecycle (Draft -> Approved -> Sent -> Received -> Cancelled).
- Smart reorder suggestions based on safety stocks and min/max thresholds.
- Multi-warehouse receiving with automated lot/batch stamping.
- Supplier invoices and payment recording with 3-way matching.

#### 4. Semi-Finished Production Orders (Module 10)
- Planned vs. actual kitchen production batch runs.
- Proportional scaling of BOM ingredients based on planned yield.
- Automatic double-entry deduction of raw materials and receipt of finished batches.
- Yield variance tracking and unit cost recalculation.

#### 5. Real-Time QR Menu & POS Auto-Depletion (Module 17)
- Instant background stock deduction upon order placement across Storefront QR Menu, Waiter POS, and KDS.
- Idempotency key protection preventing duplicate deductions.
- Order cancellation and refund auto-restoration.
- Automated Stop-List / 86 integration: Dishes automatically disabled when required ingredients are depleted.

#### 6. Batches, Expiration (FEFO) & HACCP Traceability (Modules 11, 12, 13 & 14)
- First-Expired, First-Out (FEFO) inventory management.
- Standard Critical Control Points (CCPs) for receiving, refrigeration, cooking core probes, and blast chilling.
- Digital temperature logging with critical limit breach detection and mandatory corrective actions.
- Shift hygiene and sanitation checklists.
- Bidirectional traceability: Trace any lot number upstream to supplier PO and downstream to affected customer orders.

#### 7. Waste, Spoilage & Business Intelligence (Modules 15 & 16)
- Digital waste write-offs with automatic warehouse inventory reduction.
- Categorization by spoilage, expiration, prep mistakes, burns, and damaged goods.
- Actual vs. Theoretical Food Cost Variance report with overrun alerts.
- ABC Inventory Analysis (Pareto 80/20 rule) categorizing high, moderate, and low-value capital.
- Inventory turnover ratio, Days Inventory Outstanding (DIO), and dead stock detection.

---

### Technical Metrics
- **Automated Tests**: 84 tests, 431 assertions passing with 100% success rate.
- **Security**: Strict tenant isolation across all queries via `BelongsToVendor`, `TenantScope`, and Spatie permissions.
- **Code Quality**: Formatted according to Laravel Pint PSR-12 and Pint agent standards.
- **Multilingual Support**: Fully localized in Armenian (`hy`), English (`en`), and Russian (`ru`).

