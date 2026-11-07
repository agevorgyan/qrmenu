# Phase 10: Wholesale Subsystem User Manual
## QR Menu SaaS — B2B Wholesale POS & Distribution Subsystem (v1.0)

---

## 1. Introduction & Module Access
The Wholesale POS & Distribution subsystem is an enterprise-grade commercial sales, distribution, and tax invoicing tool for multi-tenant food manufacturers and hospitality suppliers.

> [!IMPORTANT]
> **Super Admin Gated Feature**:
> By default, the Wholesale subsystem is disabled for all tenants.
> To activate Wholesale POS for a tenant, a Super Admin must navigate to:
> `/superadmin/wholesale/tenants`
> and click **Grant Access**. An optional expiration date and internal reason can be attached.
> Once granted, the tenant's admin navigation bar will display the **Wholesale** menu section.

---

## 2. Managing Seller Companies & Tax Profiles
Each tenant can manage multiple legal selling entities with independent tax regimes, bank accounts, and warehouse supply addresses.

1. Navigate to **Wholesale &rarr; Seller Companies**.
2. Click **Add Seller Company**:
   - **Display Name**: Internal identifier (e.g. *Sales Plus LLC*).
   - **Legal Name (Հայերեն)**: Official legal entity name registered with the State Register (e.g. *«ՍԵՅԼՍ ՊԼՅՈՒՍ» ՍՊԸ*).
   - **TIN (ՀՎՀՀ)**: 8-digit Armenian Taxpayer Identification Number (e.g. *08293072*).
   - **Tax Regime**:
     - **VAT Registered (ԱԱՀ)**: Issues official Tax Invoices (Հարկային հաշիվ) with 20% VAT.
     - **Turnover Tax (Շրջանառության հարկ)**: Issues Accounting Documents (Հաշիվ վավերագիր) under Special Tax System with no VAT tags.
   - **Legal Address**: Registered tax office address.
   - **PEK Username**: Username for e-Invoicing identification (e.g. *salesplus25*).
3. Under the Seller details view, add:
   - **Bank Accounts**: Official bank name and 16-digit account number (mandatory for Turnover Tax e-invoices).
   - **Supply Locations**: Physical warehouses or supply branches from which goods are dispatched.

---

## 3. Independent Wholesale Product Catalog & ATGAA Classifications
Wholesale products operate in a dedicated catalog independent of the retail QR restaurant menu.

1. Navigate to **Wholesale &rarr; Products**.
2. Click **Add Wholesale Product**:
   - **Product Name**: Commercial item title (e.g. *Պելմենի Ֆիրմային 450գր*).
   - **SKU**: Unique wholesale stock code (e.g. *PELM-FIRM-450*).
   - **ATGAA Code (ԱՏԳ ԱԱ)**: Select from the official Armenian/FEACN customs classification directory (e.g. *1602* for prepared meat products, *1902* for pasta).
   - **Unit of Measurement**: Default sales unit (e.g. *հատ*, *կգ*, *տուփ*).
   - **Base Wholesale Price**: Default selling price before volume breaks.
   - **Minimum Wholesale Price (Floor)**: The hard price floor that prevents accidental under-cost sales.
   - **Packaging**: Define carton hierarchies (e.g. 1 Master Box = 24 Packs).
   - **Physical Inventory Link (Optional)**: Map to an existing kitchen ingredient in core inventory to automatically deplete physical stock on delivery.

---

## 4. Corporate Customers, Credit Terms & Price Lists

### 4.1 B2B Customer Management
1. Navigate to **Wholesale &rarr; Customers &rarr; Add Customer**.
2. Fill in:
   - **Company Legal Name** and **TIN (ՀՎՀՀ)**.
   - **Credit Limit (AMD)**: Maximum outstanding receivable balance allowed.
   - **Payment Terms**: Days allowed for delayed payment (e.g. Net 15, Net 30).
   - **Delivery Addresses**: Corporate warehouses and branch locations.

### 4.2 Dynamic Pricing & Price Lists
1. Navigate to **Wholesale &rarr; Price Lists**.
2. Create tiered price lists (e.g. *Supermarket Chains*, *Distributors*).
3. Assign custom product prices or quantity discounts (e.g. 5% off for 50+ units, 10% off for 200+ units).
4. Assign the price list to specific customer accounts.

---

## 5. Wholesale POS Terminal Operation
The dedicated Wholesale POS terminal is optimized for high-speed order processing and phone sales:

1. Navigate to **Wholesale &rarr; Wholesale POS**.
2. **Select Seller**: Choose which company issues the sale.
3. **Select Customer**: Choose the corporate buyer. The system displays current outstanding balance, available credit, and credit terms.
4. **Search & Add Products**: Search by SKU, barcode, or product name. Pricing is calculated automatically based on the customer's contract pricing and volume discounts.
5. **Adjust Discounts & Quantities**: Line items validate that unit prices cannot drop below the item's minimum price floor.
6. **Checkout Options**:
   - **Immediate Sale**: Generates commercial invoice and creates delivery order.
   - **Draft / Pre-Order**: Saves order as pending for warehouse packing and credit approval.

---

## 6. Logistics Fulfillment & Customer Returns

### 6.1 Delivery Fulfillment
1. Navigate to **Wholesale &rarr; Deliveries**.
2. Create or open a delivery dispatch order:
   - Assign driver, vehicle, and dispatch warehouse.
   - Click **Dispatch Delivery** when goods leave the facility.
3. When goods arrive at the customer location, click **Confirm Delivery**:
   - Enter proof of delivery notes or recipient name.
   - If products are mapped to physical stock ingredients, physical warehouse inventory is automatically depleted via `InventoryLedgerService`.

### 6.2 Customer Returns
1. If goods are damaged or rejected, navigate to **Wholesale &rarr; Returns &rarr; Create Return**.
2. Select the customer, source invoice, returned products, and reason.
3. Choose Action Type:
   - **RESTOCK**: Re-increments warehouse stock and credits the customer's accounts receivable balance.
   - **WASTE**: Discards goods without restocking, but issues financial credit.

---

## 7. Payments, Collections & General Ledger

1. Navigate to **Wholesale &rarr; Payments &rarr; Record Payment**.
2. Select Customer and Payment Method:
   - **Cash**: Direct POS cash till collection (links to active shift register).
   - **Bank Transfer**: Records wire transfer deposit to company operating account.
3. Enter amount and allocate across open unpaid wholesale sales invoices.
4. The system automatically creates double-entry journal entries in the Armenian General Ledger:
   - **Cash Sale**: Debit POS Cash (2511), Credit Sales Revenue (6111), Credit VAT Payable (5243).
   - **Credit Sale**: Debit Trade Receivables (2211), Credit Sales Revenue (6111), Credit VAT Payable (5243).
   - **Payment Received**: Debit Operating Bank (2521), Credit Trade Receivables (2211).

---

## 8. Official Armenian e-Invoicing (ՊԵԿ / SRC XML Export)

The system automatically generates State Revenue Committee (SRC / ՊԵԿ) compliant XML files.

### 8.1 Document Types
- **Հարկային հաշիվ (Tax Invoice)**:
  Generated for VAT-registered sellers (Series `A`). Contains taxable base, 20% VAT rates, line totals, and ATGAA commodity codes.
- **Հաշիվ վավերագիր (Accounting Document)**:
  Generated for Turnover Tax / Special Tax System sellers (Series `B`). Excludes VAT tags, sets `<DealType>SPECIAL_TAX_SYSTEM</DealType>`, and includes mandatory seller bank accounts.

### 8.2 W3C XSD Validation
Every document is automatically validated against official W3C XML Schema Definition (`.xsd`) files upon generation. If any required field is missing, specific validation errors are highlighted on the document page.

### 8.3 Single & Batch Export
1. To download a single XML file, open the Tax Document and click **Download XML**.
2. To download a batch archive:
   - Navigate to **Wholesale &rarr; PEK XML Export**.
   - Check the pending tax documents.
   - Click **Package Selected Documents into ZIP**.
   - Download the generated `.zip` archive containing the individual `.xml` files.
3. Open the official Armenian Tax Service portal ([file-online.taxservice.am](https://file-online.taxservice.am)) and manually import the exported XML files for signing.

---

## 9. Reports & Analytics
Navigate to **Wholesale &rarr; Reports** to access:
- **Executive Summary**: Gross wholesale revenue, total VAT collected, outstanding A/R, and delivery fulfillment rate.
- **Tax Summary**: Comparison between VAT (20%) sales vs Turnover Tax sales.
- **ATGAA Customs Classification**: Total revenue and units sold grouped by official 4-digit and 8-digit customs codes.
- **Sales by Seller & Customer**: Comprehensive revenue rankings with one-click CSV exports.
- **Accounts Receivable Aging**: Breakdown of current vs 1-30, 31-60, 61-90, 90+ days overdue balances.

