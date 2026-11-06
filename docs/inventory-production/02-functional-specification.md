# PHASE 1 — FUNCTIONAL SPECIFICATION
## Product & Ingredient Catalog, Warehouses & Inventory Ledger

---

### 1. Overview & Capabilities

Phase 1 establishes the foundational data layer and transactional engine for inventory management within the QR Menu SaaS platform. It enables vendors to manage raw materials, ingredients, packaging, and consumables across multiple storage facilities, tracking real-time stock balances and recording an immutable audit trail of movements.

---

### 2. Core Functional Modules

#### 2.1. Product & Ingredient Catalog (`ingredients`)
- **Classification**:
  - `raw_material`: Unprocessed ingredients (e.g. raw meat, flour, whole vegetables).
  - `ingredient`: Processed or cooking ingredients (e.g. olive oil, spices).
  - `semi_finished`: Prepared intermediate goods (e.g. dough, minced meat, stock/broth).
  - `finished`: Finished goods ready for serving or retail packaging.
  - `packaging`: Containers, boxes, bottles, wrapping materials.
  - `consumable`: Auxiliary supplies (e.g. napkins, sanitizing solutions).
- **Attributes**:
  - Name with multilingual translation support (`name_translations`).
  - Internal SKU and barcode (EAN/UPC).
  - Categorization via hierarchical `ingredient_categories`.
  - Base Unit of Measurement (`unit_id`).
  - Stock thresholds: `min_stock`, `max_stock`, `reorder_point`, `safety_stock`.
  - Costing parameters: `last_purchase_price`, `current_cost` (weighted average).
  - Storage specifications: storage condition, temperature range (`storage_temperature_min`, `storage_temperature_max`), shelf life in days.
  - Mass-to-volume density factor (`density_kg_per_l`).
  - Nutritional values: calories, protein, carbs, fat.
  - Multi-select allergens linked via `ingredient_allergens` pivot to the platform's canonical `allergens` table.

#### 2.2. Units of Measurement & Conversion Engine (`units`, `unit_conversions`)
- **Standard Metric Units**:
  - Mass: `kg` (Base), `g` (0.001), `ton` (1000).
  - Volume: `l` (Base), `ml` (0.001).
  - Count: `piece` (Base), `portion` (Base).
  - Packaging: `pack`, `box`, `bottle`.
- **Conversion Principles**:
  - Same dimension conversions calculate automatically using `base_factor`.
  - Cross-dimension conversions (Mass $\leftrightarrow$ Volume) calculate via `density_kg_per_l` (e.g. 1 liter oil = 0.92 kg).
  - Explicit item-specific conversions via `unit_conversions` (e.g. 1 box of eggs = 12 pieces).
  - Uncalibrated cross-dimension conversions without explicit density or rule are strictly blocked.

#### 2.3. Warehouses & Facilities (`warehouses`)
- Multi-warehouse support per vendor (`main`, `kitchen`, `cold_room`, `freezer`, `production`, `finished_goods`, `bar`).
- Branch scoping via `location_id` (enables multi-branch inventory separation).
- Facility supervisor assignment (`responsible_user_id`).
- Default warehouse designation for automated procurement and order deductions.
- Safety check preventing deletion of any warehouse with active on-hand stock.

#### 2.4. Real-Time Balances (`inventory_balances`)
- Composite tracking per `(warehouse_id, ingredient_id, batch_id)`.
- Explicit tracking of:
  - `quantity_on_hand`: Physical stock on the shelves.
  - `quantity_reserved`: Stock claimed by orders or scheduled production.
  - `available_quantity`: Derived as `max(0, quantity_on_hand - quantity_reserved)`.
  - `unit_cost`: Current Weighted Average Cost.
  - `total_value`: Valuation equal to `quantity_on_hand * unit_cost`.

#### 2.5. Immutable Inventory Ledger (`inventory_transactions`, `inventory_transaction_items`)
- Atomically records all movements:
  - `receipt`: Increases stock and recalculates Weighted Average Cost:
    $$\text{New Cost} = \frac{(\text{Current Qty} \times \text{Current Cost}) + (\text{Received Qty} \times \text{Received Cost})}{\text{Current Qty} + \text{Received Qty}}$$
  - `issue` / `waste`: Decreases stock and computes consumption value.
  - `transfer`: Atomically deducts from source warehouse and increments target warehouse.
  - `adjustment`: Reconciles physical count variance and records discrepancy audit trail.
- Concurrency protection via row-level locks (`SELECT ... FOR UPDATE`).
- Idempotency support via unique `idempotency_key`.

---

### 3. User Interface & Navigation

- **Sidebar Menu Section**: Dedicated collapsible `Inventory & Production` menu item with active state routing.
- **Views**:
  - `dashboard.blade.php`: High-level metrics, total valuation, low stock warnings, recent movements.
  - `ingredients/index.blade.php`: Searchable, filterable catalog table with low stock indicators and CSV export.
  - `ingredients/create.blade.php` & `edit.blade.php`: Multi-section form covering specifications, costing, HACCP and allergens.
  - `categories/index.blade.php`: Hierarchical category management with modals.
  - `units/index.blade.php`: Metric and packaging unit definitions with conversion rules.
  - `warehouses/index.blade.php`, `create.blade.php`, `edit.blade.php`: Multi-facility cards and branch binding.
  - `stocks/index.blade.php`: Real-time stock overview with interactive stock movement modal.
  - `stocks/movements.blade.php`: Detailed ledger history with itemized audit breakdown.
- **Multilingual Support**: All UI strings translated across Armenian (`hy`), English (`en`), and Russian (`ru`).

