# B2B Wholesale POS & Distribution Subsystem
## 03 — Database Architecture & ERD Specification
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Architectural Standards & Guidelines

1. **Strict Multi-Tenancy**: Every wholesale table contains `vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE`.
2. **Numeric Precision**:
   - Monies & Subtotals: `NUMERIC(14, 2)`
   - Unit Prices & Costs: `NUMERIC(14, 4)`
   - Quantities: `NUMERIC(12, 4)`
   - Tax & Discount Rates: `NUMERIC(5, 2)`
3. **Immutability of Financial Records**: Confirmed orders, sales, items, payments, and tax documents preserve point-in-time snapshots of prices, names, and tax rates.
4. **Foreign Key Integrity**: All relationships have explicit foreign key constraints, cascading deletes where parent-child ownership applies, and restricting deletes on critical accounting anchors.

---

## 2. Table Specifications

### 2.1 Super Admin Module Access
- `tenant_module_access`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `module_key`: VARCHAR(64) NOT NULL ('wholesale_pos')
  - `is_enabled`: BOOLEAN NOT NULL DEFAULT FALSE
  - `enabled_at`: TIMESTAMPTZ NULL
  - `expires_at`: TIMESTAMPTZ NULL
  - `granted_by`: BIGINT NULL (FK -> `users`)
  - `revoked_at`: TIMESTAMPTZ NULL
  - `revoked_by`: BIGINT NULL (FK -> `users`)
  - `reason`: TEXT NULL
  - `timestamps`
  - UNIQUE(`vendor_id`, `module_key`)

- `tenant_module_access_logs`
  - `id`: BIGSERIAL PRIMARY KEY
  - `tenant_module_access_id`: BIGINT NOT NULL (FK -> `tenant_module_access`)
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `module_key`: VARCHAR(64) NOT NULL
  - `action`: VARCHAR(32) NOT NULL (granted, suspended, revoked, expired, renewed)
  - `acted_by`: BIGINT NULL (FK -> `users`)
  - `previous_state`: JSONB NULL
  - `new_state`: JSONB NULL
  - `reason`: TEXT NULL
  - `ip_address`: VARCHAR(45) NULL
  - `user_agent`: TEXT NULL
  - `created_at`: TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP

---

### 2.2 Sellers & Tax Profiles
- `wholesale_sellers`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `name`: VARCHAR(200) NOT NULL
  - `legal_name`: VARCHAR(255) NOT NULL
  - `tin`: VARCHAR(20) NOT NULL (8-digit Armenian Tax ID)
  - `vat_number`: VARCHAR(30) NULL (e.g. `{TIN}/1`)
  - `tax_regime`: VARCHAR(40) NOT NULL ('vat_payer', 'turnover_tax', 'micro_business')
  - `address`: TEXT NOT NULL
  - `default_supply_location`: TEXT NULL
  - `default_bank_account_id`: BIGINT NULL
  - `is_active`: BOOLEAN NOT NULL DEFAULT TRUE
  - `timestamps`, `deleted_at`
  - INDEX(`vendor_id`, `is_active`)
  - UNIQUE(`vendor_id`, `tin`)

- `wholesale_seller_bank_accounts`
  - `id`: BIGSERIAL PRIMARY KEY
  - `seller_id`: BIGINT NOT NULL (FK -> `wholesale_sellers`)
  - `bank_name`: VARCHAR(150) NOT NULL
  - `account_number`: VARCHAR(50) NOT NULL
  - `iban`: VARCHAR(50) NULL
  - `currency`: VARCHAR(10) NOT NULL DEFAULT 'AMD'
  - `is_default`: BOOLEAN NOT NULL DEFAULT FALSE
  - `timestamps`

- `wholesale_seller_locations`
  - `id`: BIGSERIAL PRIMARY KEY
  - `seller_id`: BIGINT NOT NULL (FK -> `wholesale_sellers`)
  - `name`: VARCHAR(150) NOT NULL
  - `address`: TEXT NOT NULL
  - `warehouse_id`: BIGINT NULL (FK -> `warehouses`)
  - `is_default`: BOOLEAN NOT NULL DEFAULT FALSE
  - `timestamps`

---

### 2.3 Customers & Pricing
- `wholesale_customers`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `name`: VARCHAR(200) NOT NULL
  - `legal_name`: VARCHAR(255) NOT NULL
  - `tin`: VARCHAR(20) NOT NULL
  - `vat_number`: VARCHAR(30) NULL
  - `customer_type`: VARCHAR(40) NOT NULL DEFAULT 'legal_entity'
  - `customer_group`: VARCHAR(60) NULL
  - `credit_limit`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `current_balance`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `payment_terms_days`: INT NOT NULL DEFAULT 0
  - `phone`: VARCHAR(50) NULL
  - `email`: VARCHAR(120) NULL
  - `is_active`: BOOLEAN NOT NULL DEFAULT TRUE
  - `timestamps`, `deleted_at`
  - INDEX(`vendor_id`, `tin`)

- `wholesale_customer_addresses`
  - `id`: BIGSERIAL PRIMARY KEY
  - `customer_id`: BIGINT NOT NULL (FK -> `wholesale_customers`)
  - `type`: VARCHAR(30) NOT NULL DEFAULT 'delivery'
  - `address_line`: TEXT NOT NULL
  - `city`: VARCHAR(100) NULL
  - `contact_person`: VARCHAR(150) NULL
  - `phone`: VARCHAR(50) NULL
  - `is_default`: BOOLEAN NOT NULL DEFAULT FALSE
  - `timestamps`

- `wholesale_price_lists`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `name`: VARCHAR(150) NOT NULL
  - `code`: VARCHAR(60) NOT NULL
  - `currency`: VARCHAR(10) NOT NULL DEFAULT 'AMD'
  - `effective_from`: DATE NULL
  - `effective_to`: DATE NULL
  - `is_default`: BOOLEAN NOT NULL DEFAULT FALSE
  - `is_active`: BOOLEAN NOT NULL DEFAULT TRUE
  - `timestamps`

- `wholesale_price_list_items`
  - `id`: BIGSERIAL PRIMARY KEY
  - `price_list_id`: BIGINT NOT NULL (FK -> `wholesale_price_lists`)
  - `product_id`: BIGINT NOT NULL (FK -> `wholesale_products`)
  - `price`: NUMERIC(14, 4) NOT NULL
  - `min_quantity`: NUMERIC(12, 4) NOT NULL DEFAULT 1.0000
  - `timestamps`
  - UNIQUE(`price_list_id`, `product_id`, `min_quantity`)

---

### 2.4 Independent Wholesale Product Catalog
- `wholesale_categories`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `name`: VARCHAR(150) NOT NULL
  - `parent_id`: BIGINT NULL (FK -> `wholesale_categories`)
  - `sort_order`: INT NOT NULL DEFAULT 0
  - `is_active`: BOOLEAN NOT NULL DEFAULT TRUE
  - `timestamps`

- `wholesale_products`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `category_id`: BIGINT NULL (FK -> `wholesale_categories`)
  - `name`: VARCHAR(255) NOT NULL
  - `sku`: VARCHAR(80) NOT NULL
  - `barcode`: VARCHAR(100) NULL
  - `gtin`: VARCHAR(50) NULL
  - `classifier_code`: VARCHAR(30) NOT NULL (ATGAA / FEACN code)
  - `unit_id`: BIGINT NOT NULL (FK -> `units`)
  - `packaging_unit_id`: BIGINT NULL (FK -> `units`)
  - `units_per_package`: NUMERIC(12, 4) NOT NULL DEFAULT 1.0000
  - `min_order_quantity`: NUMERIC(12, 4) NOT NULL DEFAULT 1.0000
  - `order_quantity_multiple`: NUMERIC(12, 4) NOT NULL DEFAULT 1.0000
  - `cost_price`: NUMERIC(14, 4) NOT NULL DEFAULT 0.0000
  - `base_wholesale_price`: NUMERIC(14, 4) NOT NULL
  - `min_wholesale_price`: NUMERIC(14, 4) NOT NULL
  - `is_vat_applicable`: BOOLEAN NOT NULL DEFAULT TRUE
  - `vat_rate`: NUMERIC(5, 2) NOT NULL DEFAULT 20.00
  - `physical_stock_ingredient_id`: BIGINT NULL (FK -> `ingredients`)
  - `is_active`: BOOLEAN NOT NULL DEFAULT TRUE
  - `timestamps`, `deleted_at`
  - UNIQUE(`vendor_id`, `sku`)
  - INDEX(`vendor_id`, `classifier_code`)

---

### 2.5 Orders, Sales & Items
- `wholesale_orders`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `order_number`: VARCHAR(60) NOT NULL
  - `seller_id`: BIGINT NOT NULL (FK -> `wholesale_sellers`)
  - `customer_id`: BIGINT NOT NULL (FK -> `wholesale_customers`)
  - `delivery_address_id`: BIGINT NULL (FK -> `wholesale_customer_addresses`)
  - `status`: VARCHAR(40) NOT NULL DEFAULT 'draft'
  - `order_date`: DATE NOT NULL
  - `expected_delivery_date`: DATE NULL
  - `subtotal`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `discount_total`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `vat_total`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `delivery_fee`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `currency`: VARCHAR(10) NOT NULL DEFAULT 'AMD'
  - `notes`: TEXT NULL
  - `is_preorder`: BOOLEAN NOT NULL DEFAULT FALSE
  - `created_by`: BIGINT NULL (FK -> `users`)
  - `confirmed_by`: BIGINT NULL (FK -> `users`)
  - `timestamps`
  - UNIQUE(`vendor_id`, `order_number`)

- `wholesale_order_items`
  - `id`: BIGSERIAL PRIMARY KEY
  - `order_id`: BIGINT NOT NULL (FK -> `wholesale_orders`)
  - `product_id`: BIGINT NOT NULL (FK -> `wholesale_products`)
  - `product_name_snapshot`: VARCHAR(255) NOT NULL
  - `sku_snapshot`: VARCHAR(80) NOT NULL
  - `classifier_code_snapshot`: VARCHAR(30) NOT NULL
  - `unit_name_snapshot`: VARCHAR(50) NOT NULL
  - `quantity_ordered`: NUMERIC(12, 4) NOT NULL
  - `quantity_delivered`: NUMERIC(12, 4) NOT NULL DEFAULT 0.0000
  - `quantity_invoiced`: NUMERIC(12, 4) NOT NULL DEFAULT 0.0000
  - `unit_price`: NUMERIC(14, 4) NOT NULL
  - `discount_percent`: NUMERIC(5, 2) NOT NULL DEFAULT 0.00
  - `discount_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `vat_rate`: NUMERIC(5, 2) NOT NULL DEFAULT 0.00
  - `vat_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_price`: NUMERIC(14, 2) NOT NULL
  - `timestamps`

- `wholesale_sales`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `sale_number`: VARCHAR(60) NOT NULL
  - `order_id`: BIGINT NULL (FK -> `wholesale_orders`)
  - `seller_id`: BIGINT NOT NULL (FK -> `wholesale_sellers`)
  - `customer_id`: BIGINT NOT NULL (FK -> `wholesale_customers`)
  - `delivery_address_id`: BIGINT NULL (FK -> `wholesale_customer_addresses`)
  - `status`: VARCHAR(40) NOT NULL DEFAULT 'posted'
  - `sale_date`: DATE NOT NULL
  - `subtotal`: NUMERIC(14, 2) NOT NULL
  - `discount_total`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `vat_total`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `delivery_fee`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_amount`: NUMERIC(14, 2) NOT NULL
  - `paid_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `balance_due`: NUMERIC(14, 2) NOT NULL
  - `currency`: VARCHAR(10) NOT NULL DEFAULT 'AMD'
  - `journal_entry_id`: BIGINT NULL (FK -> `journal_entries`)
  - `created_by`: BIGINT NULL (FK -> `users`)
  - `timestamps`
  - UNIQUE(`vendor_id`, `sale_number`)

- `wholesale_sale_items`
  - `id`: BIGSERIAL PRIMARY KEY
  - `sale_id`: BIGINT NOT NULL (FK -> `wholesale_sales`)
  - `order_item_id`: BIGINT NULL (FK -> `wholesale_order_items`)
  - `product_id`: BIGINT NOT NULL (FK -> `wholesale_products`)
  - `product_name_snapshot`: VARCHAR(255) NOT NULL
  - `sku_snapshot`: VARCHAR(80) NOT NULL
  - `classifier_code_snapshot`: VARCHAR(30) NOT NULL
  - `unit_name_snapshot`: VARCHAR(50) NOT NULL
  - `quantity`: NUMERIC(12, 4) NOT NULL
  - `unit_price`: NUMERIC(14, 4) NOT NULL
  - `discount_percent`: NUMERIC(5, 2) NOT NULL DEFAULT 0.00
  - `discount_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `vat_rate`: NUMERIC(5, 2) NOT NULL DEFAULT 0.00
  - `vat_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_price`: NUMERIC(14, 2) NOT NULL
  - `cost_price_snapshot`: NUMERIC(14, 4) NOT NULL DEFAULT 0.0000
  - `timestamps`

---

### 2.6 Deliveries, Returns & Logistics
- `wholesale_deliveries`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `delivery_number`: VARCHAR(60) NOT NULL
  - `order_id`: BIGINT NOT NULL (FK -> `wholesale_orders`)
  - `sale_id`: BIGINT NULL (FK -> `wholesale_sales`)
  - `warehouse_id`: BIGINT NOT NULL (FK -> `warehouses`)
  - `status`: VARCHAR(40) NOT NULL DEFAULT 'planned'
  - `driver_name`: VARCHAR(120) NULL
  - `driver_phone`: VARCHAR(50) NULL
  - `vehicle_plate`: VARCHAR(50) NULL
  - `scheduled_at`: TIMESTAMPTZ NULL
  - `departed_at`: TIMESTAMPTZ NULL
  - `delivered_at`: TIMESTAMPTZ NULL
  - `failure_reason`: TEXT NULL
  - `proof_of_delivery_signature`: TEXT NULL
  - `proof_of_delivery_image`: VARCHAR(255) NULL
  - `inventory_transaction_id`: BIGINT NULL (FK -> `inventory_transactions`)
  - `created_by`: BIGINT NULL (FK -> `users`)
  - `timestamps`

- `wholesale_delivery_items`
  - `id`: BIGSERIAL PRIMARY KEY
  - `delivery_id`: BIGINT NOT NULL (FK -> `wholesale_deliveries`)
  - `order_item_id`: BIGINT NOT NULL (FK -> `wholesale_order_items`)
  - `product_id`: BIGINT NOT NULL (FK -> `wholesale_products`)
  - `quantity_planned`: NUMERIC(12, 4) NOT NULL
  - `quantity_delivered`: NUMERIC(12, 4) NOT NULL DEFAULT 0.0000
  - `quantity_rejected`: NUMERIC(12, 4) NOT NULL DEFAULT 0.0000
  - `rejection_reason`: VARCHAR(255) NULL
  - `timestamps`

- `wholesale_returns`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `return_number`: VARCHAR(60) NOT NULL
  - `sale_id`: BIGINT NOT NULL (FK -> `wholesale_sales`)
  - `customer_id`: BIGINT NOT NULL (FK -> `wholesale_customers`)
  - `seller_id`: BIGINT NOT NULL (FK -> `wholesale_sellers`)
  - `status`: VARCHAR(40) NOT NULL DEFAULT 'draft'
  - `return_date`: DATE NOT NULL
  - `subtotal`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `vat_total`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `reason`: TEXT NULL
  - `journal_entry_id`: BIGINT NULL (FK -> `journal_entries`)
  - `inventory_transaction_id`: BIGINT NULL (FK -> `inventory_transactions`)
  - `created_by`: BIGINT NULL (FK -> `users`)
  - `timestamps`

- `wholesale_return_items`
  - `id`: BIGSERIAL PRIMARY KEY
  - `return_id`: BIGINT NOT NULL (FK -> `wholesale_returns`)
  - `sale_item_id`: BIGINT NOT NULL (FK -> `wholesale_sale_items`)
  - `product_id`: BIGINT NOT NULL (FK -> `wholesale_products`)
  - `quantity`: NUMERIC(12, 4) NOT NULL
  - `unit_price`: NUMERIC(14, 4) NOT NULL
  - `vat_rate`: NUMERIC(5, 2) NOT NULL DEFAULT 0.00
  - `vat_amount`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_price`: NUMERIC(14, 2) NOT NULL
  - `restock_warehouse_id`: BIGINT NULL (FK -> `warehouses`)
  - `condition`: VARCHAR(50) NOT NULL DEFAULT 'good'
  - `timestamps`

---

### 2.7 Payments & Allocations
- `wholesale_payments`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `payment_number`: VARCHAR(60) NOT NULL
  - `customer_id`: BIGINT NOT NULL (FK -> `wholesale_customers`)
  - `seller_id`: BIGINT NOT NULL (FK -> `wholesale_sellers`)
  - `payment_method`: VARCHAR(40) NOT NULL ('cash', 'bank_transfer', 'pos_terminal')
  - `amount`: NUMERIC(14, 2) NOT NULL
  - `currency`: VARCHAR(10) NOT NULL DEFAULT 'AMD'
  - `payment_date`: DATE NOT NULL
  - `reference_number`: VARCHAR(100) NULL
  - `bank_account_id`: BIGINT NULL (FK -> `bank_accounts`)
  - `cash_register_id`: BIGINT NULL (FK -> `cash_registers`)
  - `pos_shift_id`: BIGINT NULL (FK -> `pos_shifts`)
  - `status`: VARCHAR(40) NOT NULL DEFAULT 'completed'
  - `notes`: TEXT NULL
  - `journal_entry_id`: BIGINT NULL (FK -> `journal_entries`)
  - `created_by`: BIGINT NULL (FK -> `users`)
  - `timestamps`
  - UNIQUE(`vendor_id`, `payment_number`)

- `wholesale_payment_allocations`
  - `id`: BIGSERIAL PRIMARY KEY
  - `payment_id`: BIGINT NOT NULL (FK -> `wholesale_payments`)
  - `sale_id`: BIGINT NOT NULL (FK -> `wholesale_sales`)
  - `allocated_amount`: NUMERIC(14, 2) NOT NULL
  - `timestamps`
  - UNIQUE(`payment_id`, `sale_id`)

---

### 2.8 Tax Documents & PEK XML Exports
- `wholesale_tax_documents`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `seller_id`: BIGINT NOT NULL (FK -> `wholesale_sellers`)
  - `customer_id`: BIGINT NOT NULL (FK -> `wholesale_customers`)
  - `sale_id`: BIGINT NULL (FK -> `wholesale_sales`)
  - `return_id`: BIGINT NULL (FK -> `wholesale_returns`)
  - `document_type`: VARCHAR(40) NOT NULL ('invoice', 'accounting_document')
  - `document_series`: VARCHAR(10) NOT NULL ('A' for invoice, 'B' for acc_doc)
  - `document_number`: VARCHAR(30) NOT NULL
  - `full_document_number`: VARCHAR(40) NOT NULL
  - `procedure`: INT NOT NULL DEFAULT 1
  - `adjustment_account`: BOOLEAN NOT NULL DEFAULT FALSE
  - `adjustment_diff_flag`: INT NULL
  - `parent_document_number`: VARCHAR(40) NULL
  - `parent_document_submit_date`: DATE NULL
  - `supply_date`: DATE NULL
  - `delivery_date`: DATE NULL
  - `seller_tin`: VARCHAR(20) NOT NULL
  - `seller_name`: VARCHAR(255) NOT NULL
  - `seller_address`: TEXT NOT NULL
  - `seller_vat_number`: VARCHAR(30) NULL
  - `seller_bank_name`: VARCHAR(150) NULL
  - `seller_bank_account`: VARCHAR(50) NULL
  - `seller_supply_location`: TEXT NULL
  - `buyer_tin`: VARCHAR(20) NOT NULL
  - `buyer_name`: VARCHAR(255) NOT NULL
  - `buyer_address`: TEXT NOT NULL
  - `buyer_vat_number`: VARCHAR(30) NULL
  - `buyer_delivery_location`: TEXT NULL
  - `total_price_without_vat`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_vat`: NUMERIC(14, 2) NOT NULL DEFAULT 0.00
  - `total_price`: NUMERIC(14, 2) NOT NULL
  - `xml_content`: TEXT NULL
  - `validation_status`: VARCHAR(40) NOT NULL DEFAULT 'draft'
  - `validation_errors`: JSONB NULL
  - `generated_by`: BIGINT NULL (FK -> `users`)
  - `timestamps`
  - UNIQUE(`vendor_id`, `full_document_number`)

- `wholesale_tax_document_items`
  - `id`: BIGSERIAL PRIMARY KEY
  - `tax_document_id`: BIGINT NOT NULL (FK -> `wholesale_tax_documents`)
  - `item_index`: INT NOT NULL
  - `product_id`: BIGINT NULL (FK -> `wholesale_products`)
  - `good_parent_id`: VARCHAR(64) NULL
  - `description`: VARCHAR(255) NOT NULL
  - `classifier_code`: VARCHAR(30) NOT NULL
  - `unit_name`: VARCHAR(50) NOT NULL
  - `amount`: NUMERIC(12, 4) NOT NULL
  - `price_per_unit`: NUMERIC(14, 5) NOT NULL
  - `price`: NUMERIC(14, 2) NOT NULL
  - `vat_rate`: NUMERIC(5, 2) NULL
  - `vat`: NUMERIC(14, 2) NULL
  - `discount`: NUMERIC(5, 2) NULL
  - `deal_type`: VARCHAR(50) NULL
  - `total_price`: NUMERIC(14, 2) NOT NULL
  - `timestamps`

- `wholesale_pek_exports`
  - `id`: BIGSERIAL PRIMARY KEY
  - `vendor_id`: BIGINT NOT NULL (FK -> `vendors`)
  - `export_batch_number`: VARCHAR(60) NOT NULL
  - `document_type`: VARCHAR(40) NOT NULL
  - `documents_count`: INT NOT NULL
  - `file_name`: VARCHAR(255) NOT NULL
  - `file_path`: VARCHAR(255) NOT NULL
  - `file_size`: BIGINT NOT NULL
  - `exported_by`: BIGINT NULL (FK -> `users`)
  - `timestamps`

