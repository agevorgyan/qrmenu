# B2B Wholesale POS & Distribution Subsystem
## 02 — Business Requirements Specification
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Domain Overview & Scope

The B2B Wholesale POS & Distribution subsystem provides enterprise food and beverage operators, distributors, central production facilities, and franchise hubs with a comprehensive suite for:
1. B2B Customer Relationship and Credit Management.
2. Independent Wholesale Product Catalog and Packaging Management.
3. Multi-tier Contract and Tiered Pricing.
4. Wholesale POS Terminal with Order and Pre-Order Workflows.
5. Delivery Logistics, Dispatching, Partial Delivery, and Returns.
6. Cash and Bank Transfer Payments with Accounts Receivable Tracking.
7. Armenian State Revenue Committee (ՀՀ ՊԵԿ) Compliant XML Export.
8. Double-entry General Ledger and Inventory Integration.

---

## 2. Super Admin-Controlled Access Model

### 2.1 Access Policy & Lifecycle
- **Status at Creation**: Default DISABLED (`is_enabled = false`) for all existing and future tenants.
- **Granting Authority**: Super Admin ONLY via `/superadmin/wholesale/access`.
- **Tenant Scope**: Tenant administrators have no visibility into activation status or upsell mechanisms.
- **Suspension / Revocation**: Instantaneous block; active sessions are invalidated upon next request.
- **Expiration**: Optional timestamp; when passed, requests are rejected with HTTP 403.

---

## 3. Seller Management & Tax Regimes

Each tenant can manage multiple legal entities:
- **Legal Profile**: Legal Name, Trade Name, Tax Identification Number (TIN - 8 digits), VAT Registration Number (e.g. `{TIN}/1`).
- **Tax Regimes**:
  - `VAT_PAYER`: Value Added Tax at 20.0%, issues official Tax Invoices (Հարկային հաշիվ).
  - `TURNOVER_TAX`: Special regime, issues Accounting Documents (Հաշիվ վավերագիր).
  - `MICRO_BUSINESS`: Special exempt regime, issues Accounting Documents (Հաշիվ վավերագիր).
- **Locations**: Independent physical supply locations with street, district, and settlement specifications.
- **Bank Accounts**: Multiple corporate bank accounts with IBAN and SWIFT details.

---

## 4. Product Catalog & Packaging

- **Separation**: Zero contamination between retail QR menu items and wholesale goods.
- **Identification**: SKU, Barcode (EAN-13), GTIN.
- **Tax & Classification**: Valid FEACN / ATGAA 4-digit or 10-digit classifier codes (e.g., `1602`, `1902`, `2106.90.98`).
- **Units & Packaging**:
  - Base Unit (e.g. piece, kg, liter).
  - Multi-tier packaging (e.g. box of 12 pieces, pallet of 50 boxes).
  - Minimum Order Quantity (MOQ) and Quantity Multiples.
- **Physical Inventory**: Explicit mapping to `ingredients.id` when shared stock tracking is required.

---

## 5. Pricing Engine

The pricing engine evaluates prices hierarchically:
1. **Contract Price**: Customer-specific negotiated price for a product.
2. **Customer Group Price**: Price list assigned to a tier (e.g. "Wholesale Gold", "Supermarket Chain").
3. **Volume / Quantity Tier**: Discount rules triggered by order quantities (e.g. >= 50 units -> 5% off, >= 100 units -> 10% off).
4. **Base Wholesale Price**: Default wholesale catalog price.
5. **Guardrail Invariant**: Final unit price cannot drop below `min_wholesale_price` unless explicitly overridden by an authorized user (`wholesale.price.override`).

---

## 6. Wholesale POS & Pre-orders

- Dedicated interface optimized for desktop and tablet operations.
- Rapid product search by name, SKU, or barcode scanning.
- Seller entity selector (mandatory before checkout).
- Customer selector with immediate credit limit and outstanding balance badge.
- Order types:
  - **Direct Sale**: Immediate confirmation, delivery, and invoice creation.
  - **Pre-Order**: Scheduled future supply date with optional deposit.
  - **Draft Order**: Work-in-progress quote.
- Printing: Standard 80mm thermal receipts and A4 B2B commercial invoices.

---

## 7. Delivery Logistics & Partial Fulfillment

- Multi-shipment support: One wholesale order can be fulfilled through multiple sequential deliveries.
- Statuses: Planned -> Loaded -> In Transit -> Delivered / Partially Delivered / Failed.
- Digital Proof of Delivery: Driver notes, receiver signature, and timestamp.
- Inventory Depletion: Real-time atomic deduction from warehouse stock upon delivery confirmation.

---

## 8. Payments & Accounts Receivable

- Payment Methods: Cash, Corporate Bank Transfer, POS Terminal.
- Partial Payments & Allocation: Unallocated deposits distributed across open invoices.
- Credit Terms: Credit limits (e.g. 500,000 AMD) and payment terms (e.g. Net 14, Net 30).
- Overdue tracking and interest/warning indicators.

---

## 9. Armenian PEK XML Generation

- Manual export of XML packages for manual upload to the Armenian tax portal.
- Document Generators:
  1. **Tax Invoice (Հարկային հաշիվ)**: VAT rate, VAT amount, general metadata.
  2. **Accounting Document (Հաշիվ վավերագիր)**: Non-VAT regime, `SPECIAL_TAX_SYSTEM` deal type, seller bank details.
- Pre-export validation against compiled official XSD schemas.

