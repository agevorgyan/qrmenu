# 04. API Documentation & Route Reference

## Overview
This document specifies the internal web and REST endpoints implemented for the **Inventory, Procurement, Recipe Costing, Production & HACCP Management System** in QR Menu SaaS (`elab.menu`).

All routes are multi-tenant scoped under the authenticated vendor context (`vendor_id`) and protected by the `vendor.active` middleware and granular Spatie permissions.

---

## 1. Units of Measurement & Conversions

### `GET /admin/inventory/units`
- **Permission**: `inventory.units.view`
- **Description**: List all system standard and custom tenant units grouped by dimension (`mass`, `volume`, `piece`, `length`, `area`).

### `POST /admin/inventory/units`
- **Permission**: `inventory.units.create`
- **Payload**:
  ```json
  {
    "name": "Piece (500g Pack)",
    "code": "pack_500g",
    "dimension": "piece",
    "is_fractional": false
  }
  ```

### `POST /admin/inventory/units/conversions`
- **Permission**: `inventory.units.create`
- **Payload**:
  ```json
  {
    "source_unit_id": 1,
    "destination_unit_id": 2,
    "conversion_factor": "1000.000000",
    "ingredient_id": null
  }
  ```
- **Description**: Register global dimensional conversions or ingredient-specific density conversions (e.g., 1L of Olive Oil = 0.920 kg).

---

## 2. Warehouses & Storage Locations

### `GET /admin/inventory/warehouses`
- **Permission**: `inventory.warehouses.view`
- **Description**: List all warehouses for the active vendor.

### `POST /admin/inventory/warehouses`
- **Permission**: `inventory.warehouses.create`
- **Payload**:
  ```json
  {
    "name": "Main Kitchen Storage",
    "code": "WH-KITCHEN-01",
    "type": "kitchen",
    "is_temperature_controlled": true,
    "min_temperature": "2.0",
    "max_temperature": "6.0",
    "is_default": true
  }
  ```

---

## 3. Product & Ingredient Catalog

### `GET /admin/inventory/ingredients`
- **Permission**: `inventory.ingredients.view`
- **Query Params**: `search`, `category_id`, `type`, `low_stock=1`

### `POST /admin/inventory/ingredients`
- **Permission**: `inventory.ingredients.create`
- **Payload**:
  ```json
  {
    "name": "Tomato Paste",
    "sku": "ING-TOM-01",
    "type": "raw_material",
    "category_id": 4,
    "base_unit_id": 1,
    "storage_temperature_type": "dry",
    "min_stock": "10.0000",
    "max_stock": "50.0000",
    "reorder_point": "15.0000",
    "purchase_price": "4.50"
  }
  ```

---

## 4. Inventory Ledger & Stock Transactions

### `GET /admin/inventory/transactions`
- **Permission**: `inventory.stock.view`
- **Description**: Paginated immutable transaction journal showing receipts, issues, transfers, and adjustments.

### `POST /admin/inventory/transactions`
- **Permission**: `inventory.stock.adjust`
- **Payload**:
  ```json
  {
    "type": "adjustment",
    "warehouse_id": 1,
    "reason": "physical_count",
    "notes": "End of week discrepancy count",
    "items": [
      {
        "ingredient_id": 10,
        "quantity": "-2.5000",
        "unit_id": 1,
        "unit_cost": "4.50"
      }
    ]
  }
  ```

---

## 5. Technical Cards & Recipe Costing

### `GET /admin/inventory/recipes`
- **Permission**: `recipes.view`
- **Query Params**: `type` (`dish`, `semi_finished`, `modifier`), `search`

### `POST /admin/inventory/recipes`
- **Permission**: `recipes.create`
- **Payload**:
  ```json
  {
    "name": "Signature Bolognese",
    "code": "REC-BOL-01",
    "type": "dish",
    "yield_quantity": "10.0000",
    "yield_unit_id": 5,
    "selling_price": "28.00",
    "preparation_time_minutes": 45,
    "cooking_time_minutes": 120,
    "items": [
      {
        "type": "ingredient",
        "ingredient_id": 12,
        "gross_weight": "2.5000",
        "cold_prep_loss_percent": "5.00",
        "net_weight": "2.3750",
        "cooking_loss_percent": "15.00",
        "final_weight": "2.0188",
        "unit_id": 1
      }
    ]
  }
  ```

### `POST /admin/inventory/recipes/{recipe}/approve`
- **Permission**: `recipes.approve`
- **Description**: Validates and locks the recipe into an immutable version snapshot with version numbering (v1, v2) and archived costing.

---

## 6. Suppliers & Procurement

### `GET /admin/inventory/suppliers`
- **Permission**: `suppliers.view`

### `POST /admin/inventory/suppliers`
- **Permission**: `suppliers.create`
- **Payload**:
  ```json
  {
    "name": "Fresh Farms Organic LLC",
    "tax_id": "02589631",
    "contact_person": "Arthur Martirosyan",
    "email": "orders@freshfarms.am",
    "phone": "+37410555666",
    "payment_terms_days": 14,
    "currency": "AMD"
  }
  ```

### `POST /admin/inventory/procurement`
- **Permission**: `procurement.create`
- **Payload**:
  ```json
  {
    "supplier_id": 3,
    "warehouse_id": 1,
    "order_date": "2026-11-06",
    "expected_delivery_date": "2026-11-08",
    "items": [
      {
        "ingredient_id": 12,
        "quantity": "50.0000",
        "unit_id": 1,
        "unit_price": "3.80"
      }
    ]
  }
  ```

### `POST /admin/inventory/procurement/{purchaseOrder}/receive`
- **Permission**: `procurement.receive`
- **Payload**:
  ```json
  {
    "invoice_number": "INV-FF-2026-099",
    "delivery_note_number": "DN-88741",
    "items": [
      {
        "id": 105,
        "received_quantity": "50.0000",
        "unit_price": "3.80",
        "batch_number": "LOT-TOM-2611",
        "expiry_date": "2027-05-01"
      }
    ]
  }
  ```
- **Description**: Atomically books warehouse intake, updates $WAC$, flags PO as `received`, and generates the corresponding `SupplierInvoice`.

### `POST /admin/inventory/invoices/{invoice}/payments`
- **Permission**: `procurement.pay`
- **Payload**:
  ```json
  {
    "amount": "190.00",
    "payment_method": "bank_transfer",
    "payment_date": "2026-11-06",
    "reference_number": "TXN-BANK-00918"
  }
  ```
- **Description**: Records accounts payable disbursement and updates invoice and supplier balances.

