# Database Architecture & Entity Relationship Schema

## 1. Architectural Principles
- **Multi-Tenant Isolation**: Strict isolation by `vendor_id` across every inventory, recipe, costing, warehouse, balance, and transaction table.
- **Tenant Scope Enforcement**: All models implement `BelongsToVendor` and `TenantScope`.
- **Concurrency & Precision**: Currency and monetary figures use `decimal(14, 4)` or `decimal(12, 2)`; inventory quantities and conversion factors use `decimal(12, 4)` and `decimal(14, 6)`.

---

## 2. Entity Tables

### 2.1 Units of Measurement (`units`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | YES | Null for global system units (kg, g, l, piece, portion), set for custom vendor units |
| `code` | varchar(30) | NO | Code identifier (e.g. `kg`, `g`, `l`, `ml`, `portion`) |
| `name` | varchar(100) | NO | Unit name |
| `name_translations` | jsonb | YES | Multilingual translations |
| `type` | varchar(30) | NO | `mass`, `volume`, `piece`, `packaging` |
| `base_unit_id` | bigint | YES | Base unit for standard dimensional conversion |
| `base_factor` | decimal(14, 6) | NO | Factor to convert into base unit |
| `is_system` | boolean | NO | True for system seeded units |
| `is_active` | boolean | NO | Active status |

### 2.2 Unit Conversions (`unit_conversions`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | YES | Vendor scope |
| `ingredient_id` | bigint | YES | Ingredient-specific conversion override |
| `from_unit_id` | bigint | NO | Source unit |
| `to_unit_id` | bigint | NO | Target unit |
| `factor` | decimal(14, 6) | NO | Multiplication factor ($Q_{\text{to}} = Q_{\text{from}} \times \text{factor}$) |

### 2.3 Ingredient Categories (`ingredient_categories`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | NO | Vendor ID |
| `parent_id` | bigint | YES | Hierarchical category parent ID |
| `name` | varchar(150) | NO | Category name |
| `name_translations` | jsonb | YES | Localized names |
| `description` | text | YES | Category description |
| `sort_order` | integer | NO | Sort order |
| `is_active` | boolean | NO | Active status |

### 2.4 Ingredients & Raw Materials (`ingredients`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | NO | Vendor ID |
| `category_id` | bigint | YES | Foreign key to `ingredient_categories` |
| `name` | varchar(200) | NO | Ingredient name |
| `name_translations` | jsonb | YES | Localized names |
| `sku` | varchar(60) | YES | Stock keeping unit |
| `barcode` | varchar(100) | YES | Barcode identifier |
| `type` | varchar(40) | NO | `raw_material`, `ingredient`, `semi_finished`, `finished`, `packaging`, `consumable` |
| `unit_id` | bigint | NO | Primary inventory unit |
| `min_stock` | decimal(12, 4) | NO | Reorder / safety warning threshold |
| `max_stock` | decimal(12, 4) | YES | Maximum warehouse capacity |
| `last_purchase_price` | decimal(12, 2) | NO | Last recorded PO purchase price |
| `current_cost` | decimal(12, 4) | NO | Current Weighted Average Cost (WAC) |
| `storage_conditions` | varchar(100) | YES | Temperature & storage specs |
| `shelf_life_days` | integer | YES | Shelf life in days |
| `density_kg_per_l` | decimal(8, 4) | YES | Density for mass-to-volume conversions |

### 2.5 Warehouses (`warehouses`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | NO | Vendor ID |
| `name` | varchar(150) | NO | Facility name |
| `code` | varchar(50) | NO | Unique warehouse code per vendor |
| `type` | varchar(30) | NO | `main`, `kitchen`, `bar`, `storage`, `transit` |
| `is_default` | boolean | NO | Default warehouse flag |
| `is_active` | boolean | NO | Active status |

### 2.6 Inventory Balances (`inventory_balances`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | NO | Vendor ID |
| `warehouse_id` | bigint | NO | Foreign key to `warehouses` |
| `ingredient_id` | bigint | NO | Foreign key to `ingredients` |
| `quantity_on_hand` | decimal(12, 4) | NO | Physical stock on hand |
| `quantity_reserved` | decimal(12, 4) | NO | Reserved for active kitchen prep / orders |
| `unit_id` | bigint | NO | Primary unit |
| `average_unit_cost` | decimal(14, 4) | NO | Current average cost |
| `total_value` | decimal(14, 4) | NO | Valuation ($Q \times \text{Cost}$) |

### 2.7 Recipes & Technical Cards (`recipes`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | NO | Vendor ID |
| `product_id` | bigint | YES | Linked sales menu item in `products` |
| `produced_ingredient_id` | bigint | YES | Linked semi-finished inventory item |
| `category_id` | bigint | YES | Ingredient category |
| `name` | varchar(200) | NO | Dish or recipe title |
| `code` | varchar(60) | NO | Unique recipe code per vendor |
| `type` | varchar(40) | NO | `dish`, `semi_finished`, `sauce`, `bakery`, `beverage`, `prep` |
| `status` | varchar(30) | NO | `draft`, `pending_approval`, `approved`, `active`, `archived` |
| `yield_quantity` | decimal(12, 4) | NO | Yield quantity output |
| `yield_unit_id` | bigint | NO | Unit of yield |
| `portion_size` | decimal(12, 4) | YES | Net weight or volume per serving |
| `portion_unit_id` | bigint | YES | Unit of portion |
| `selling_price` | decimal(12, 2) | YES | Retail menu price |
| `target_food_cost_percent`| decimal(5, 2) | YES | Target FC % (e.g. 28.00) |
| `instructions` | text | YES | Step-by-step preparation method |
| `critical_control_points`| text | YES | HACCP Critical Control Points |
| `current_version` | integer | NO | Version number |
| `approved_by` | bigint | YES | User who approved |
| `approved_at` | timestamp | YES | Approval timestamp |

### 2.8 Recipe Items (`recipe_items`)
| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigserial | NO | Primary key |
| `vendor_id` | bigint | NO | Vendor ID |
| `recipe_id` | bigint | NO | Foreign key to `recipes` |
| `type` | varchar(30) | NO | `ingredient` or `sub_recipe` |
| `ingredient_id` | bigint | YES | Raw ingredient |
| `sub_recipe_id` | bigint | YES | Nested sub-recipe |
| `quantity` | decimal(12, 4) | NO | Gross quantity in recipe unit |
| `unit_id` | bigint | NO | Recipe unit |
| `gross_weight` | decimal(12, 4) | NO | Raw gross weight (Բրուտտո) |
| `net_weight` | decimal(12, 4) | NO | Cleaned net weight (Նետտո) |
| `prep_loss_percent` | decimal(5, 2) | NO | Trimming / cold prep loss % |
| `cooking_loss_percent` | decimal(5, 2) | NO | Thermal / cooking shrinkage % |
| `final_weight` | decimal(12, 4) | YES | Final cooked yield weight (Ելք) |
| `unit_cost` | decimal(14, 4) | NO | Cost per recipe unit |
| `total_cost` | decimal(14, 4) | NO | Total line item cost |

### 2.9 Recipe Versions (`recipe_versions`)
Snapshots of recipe parameters, bill of materials, and costs for historical audit and production consistency.

### 2.10 Recipe Costs (`recipe_costs`)
Detailed cost calculations, food cost percentages, gross profit margins, and valuation methodology.

