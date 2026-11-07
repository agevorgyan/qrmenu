# B2B Wholesale POS & Distribution Subsystem
## Phase 0: System Architecture Audit & Enterprise Domain Design
### Architecture Version 1.0 — QR Menu SaaS Platform

---

### Executive Summary

This document establishes the architectural foundation and execution strategy for implementing a production-grade **B2B Wholesale POS & Distribution** subsystem within the QR Menu SaaS platform. 

The subsystem operates under three immutable architectural pillars:
1. **Independent Wholesale Catalog**: Wholesale products, SKU, ATGAA / FEACN classifications, units of measurement, packaging tiers, and tax configurations are completely separated from the retail QR menu catalog. Shared physical stock is linked exclusively via explicit stock-item mapping to the core inventory ledger.
2. **PEK XML Export Engine**: Generates 100% compliant e-Invoicing XML files for the State Revenue Committee of the Republic of Armenia (ՀՀ ՊԵԿ). It implements two strictly separated generators: **Invoice (Հարկային հաշիվ)** for VAT payers and **Accounting Document (Հաշիվ վավերագիր)** for non-VAT or special tax regime entities. Direct manual import into the PEK e-invoicing portal is supported; no unauthorized external API signing or submission is performed.
3. **Tenant-Level Multi-Seller Management**: Each tenant (Vendor) independently configures and operates one or more legal Seller Companies, each possessing independent tax profiles, bank accounts, supply locations, and historical document series.

Additionally, access to the Wholesale POS subsystem is strictly gated as a **Super Admin-Controlled, Tenant-Restricted Feature** via a dedicated `tenant_module_access` engine, preventing unauthorized exposure or self-service enablement.

---

## 1. Existing Architecture Audit

### 1.1 Technical Stack & Runtime Environment
- **Framework**: Laravel 13.32.0 running on PHP 8.5.
- **Database**: PostgreSQL 16 (production), SQLite in-memory (automated test suites).
- **Frontend / UI**: Blade templates, Tailwind CSS, Alpine.js, FontAwesome 6, Chart.js, Reverb WebSockets.
- **Localization**: Tri-lingual system architecture (`hy` Armenian, `en` English, `ru` Russian) managed by `LocaleManager` and `SetAppLocale` middleware.

### 1.2 Tenancy Architecture & Isolation Model
- **Tenant Entity**: The tenant is represented by `App\Models\Vendor`. Tenants represent restaurant groups, catering companies, hospitality venues, or wholesale distributors.
- **Scoped Entities**: All business models use `App\Models\Traits\BelongsToVendor` and `App\Models\Scopes\TenantScope`.
- **Tenant Context Resolution**: `App\Services\TenantContext` maintains the active tenant state per request. In admin panels, `IdentifyTenant` populates `TenantContext` from the authenticated user's `vendor_id`.
- **Security Boundary**: The `BelongsToVendor` boot hooks strictly enforce:
  - Automatic injection of `vendor_id` during creation.
  - Hard rejection of mismatched `vendor_id` on insert: `throw new InvalidArgumentException('Cross-tenant entity creation forbidden.')`.
  - Hard rejection of tenant modification on update: `throw new InvalidArgumentException('Cross-tenant entity transfer forbidden.')`.

### 1.3 Authorization & Enterprise RBAC
- Enterprise Role-Based Access Control implemented via `App\Security\Permission`, `App\Models\Role`, `App\Models\MembershipRole`, and `App\Models\MembershipPermission`.
- Granular permission checking via `@can('permission.key')`, `EnsureRole` middleware, and model policies.
- Super Admin platform console isolated at route prefix `/superadmin` with role guard `role:superadmin`.

---

## 2. Existing Modules Available for Reuse (Zero Duplication)

To maintain architectural integrity and prevent redundant logic, the Wholesale subsystem directly reuses the following battle-tested modules:

| Core Subsystem | Existing Classes / Services | Wholesale Integration & Reuse Plan |
| :--- | :--- | :--- |
| **General Ledger** | `FinancialLedgerService`<br>`JournalEntry`<br>`JournalEntryLine`<br>`ChartOfAccount`<br>`AccountingPeriod` | **Direct Double-Entry Posting**: Post all Wholesale sales, payments, credit memos, and returns into the General Ledger using RA Chart of Accounts (`2211` A/R, `6111` Revenue, `5243` VAT, `2511`/`2521` Cash/Bank). Zero duplicate ledger code. |
| **Cash Management** | `CashRegisterService`<br>`CashShiftService`<br>`PosShift`<br>`PosCashMovement` | **Wholesale Cash Settlement**: Wholesale cash collections integrate directly into active POS shifts or drawer sessions via `PosCashMovement` linked to Wholesale payment IDs. |
| **Banking** | `BankAccount`<br>`BankTransaction`<br>`BankReconciliationService` | **B2B Bank Settlement**: Wholesale bank transfer receipts link directly to tenant bank accounts for automated reconciliation against bank statements. |
| **Physical Inventory** | `InventoryLedgerService`<br>`Warehouse`<br>`Ingredient`<br>`InventoryBalance`<br>`InventoryTransaction` | **Stock Depletion & Restock**: Wholesale deliveries deduct stock from `warehouses` via `InventoryLedgerService::recordSale()` or `recordIssue()`. Returns credit warehouse stock via `recordReceipt()`. |
| **Units & Measurement** | `Unit`<br>`UnitConversionService` | **Standard Units**: Reuses existing units (`kg`, `g`, `l`, `piece`, `box`, `pack`, etc.) and multi-tier packaging conversion ratios. |
| **Procurement Tax Parsing** | `ArmenianTaxInvoiceService` | **Tax Data Models & Mapping**: Reuses unit translation dictionaries, TIN formatting, and Armenian tax classification parsing algorithms. |
| **Audit & Security** | `FinancialAuditService`<br>`SecurityAuditLog` | **Immutable Audit Trail**: Reuses audit logging format for recording status changes, price overrides, tax adjustments, and PEK exports. |

---

## 3. Super Admin-Controlled Module Access Architecture

### 3.1 Strict Security & Default Access Policy
As required by the critical architecture specification:
1. **Default Disabled**: Wholesale POS is disabled by default for 100% of tenants. Newly created or registered tenants never receive access automatically.
2. **No Tenant Self-Service**: Tenant owners or managers cannot enable Wholesale POS or view subscription upgrade prompts for it.
3. **Super Admin Authorization**: Access can only be granted, configured, suspended, or revoked by an authorized platform Super Admin.

### 3.2 Database Schema: `tenant_module_access` & Audit Logs
A centralized, high-performance table gates module access:

```sql
CREATE TABLE tenant_module_access (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    module_key VARCHAR(64) NOT NULL DEFAULT 'wholesale_pos',
    is_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    enabled_at TIMESTAMPTZ NULL,
    expires_at TIMESTAMPTZ NULL,
    granted_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    revoked_at TIMESTAMPTZ NULL,
    revoked_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    reason TEXT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_tenant_module UNIQUE (vendor_id, module_key)
);

CREATE TABLE tenant_module_access_logs (
    id BIGSERIAL PRIMARY KEY,
    tenant_module_access_id BIGINT NOT NULL REFERENCES tenant_module_access(id) ON DELETE CASCADE,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    module_key VARCHAR(64) NOT NULL,
    action VARCHAR(32) NOT NULL, -- granted, suspended, revoked, expired, renewed
    acted_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    previous_state JSONB NULL,
    new_state JSONB NULL,
    reason TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
```

### 3.3 Backend Enforcement & Middleware
- Dedicated middleware: `EnsureWholesaleAccess`
- Execution sequence:
  1. Authenticates user and resolves current `vendor_id`.
  2. Queries `tenant_module_access` for `module_key = 'wholesale_pos'`.
  3. Verifies `is_enabled == true`.
  4. Verifies `expires_at IS NULL OR expires_at > NOW()`.
  5. Verifies absence of `revoked_at`.
  6. Checks user permission (e.g. `wholesale.pos.access` or `wholesale.orders.view`).
  7. If any check fails: returns `403 Forbidden` (JSON for API, custom HTTP 403 error for Web).
- **Background Jobs & Exports**: All export jobs (e.g. PEK XML generator queue) run validation through `WholesaleAccessService::assertTenantEntitled($vendorId)`.

### 3.4 Complete Tenant UI Isolation
When access is not active:
- Sidebar menu completely hides the `B2B Wholesale` section.
- Dashboard widgets and search bars return no wholesale results or links.
- No "Upgrade to Wholesale" or lock-badges are shown (total stealth isolation).

---

## 4. Domain-Driven Architecture: `App\Domains\Wholesale`

The entire subsystem is encapsulated within `app/Domains/Wholesale/`:

```
app/Domains/Wholesale/
├── Actions/                  # Single-purpose transactional command handlers
│   ├── CreateWholesaleOrderAction.php
│   ├── ConfirmWholesaleOrderAction.php
│   ├── ProcessWholesaleSaleAction.php
│   ├── RecordWholesalePaymentAction.php
│   ├── DispatchDeliveryAction.php
│   ├── ProcessWholesaleReturnAction.php
│   └── GeneratePekXmlAction.php
├── DTOs/                     # Strongly-typed data transfer objects
│   ├── WholesaleOrderData.php
│   ├── PricingCalculationResult.php
│   ├── PekTaxDocumentData.php
│   └── DeliveryDispatchData.php
├── Enums/                    # Strict PHP 8 backed enums
│   ├── SellerTaxRegime.php
│   ├── WholesaleOrderStatus.php
│   ├── WholesaleSaleStatus.php
│   ├── DeliveryStatus.php
│   ├── TaxDocumentType.php
│   └── PekDealType.php
├── Events/                   # Domain event triggers
│   ├── WholesaleOrderConfirmed.php
│   ├── WholesaleSalePosted.php
│   ├── DeliveryCompleted.php
│   └── PekDocumentExported.php
├── Http/                     # Web & API controllers, requests, resources
│   ├── Controllers/
│   │   ├── WholesaleDashboardController.php
│   │   ├── WholesalePosController.php
│   │   ├── WholesaleOrderController.php
│   │   ├── WholesaleProductController.php
│   │   ├── WholesaleCustomerController.php
│   │   ├── WholesaleSellerController.php
│   │   ├── WholesaleDeliveryController.php
│   │   ├── WholesalePaymentController.php
│   │   ├── WholesaleTaxDocumentController.php
│   │   └── WholesaleSuperAdminAccessController.php
│   ├── Middleware/
│   │   └── EnsureWholesaleAccess.php
│   └── Requests/
├── Models/                   # Scoped Eloquent models using BelongsToVendor
│   ├── WholesaleSeller.php
│   ├── WholesaleSellerTaxProfile.php
│   ├── WholesaleSellerLocation.php
│   ├── WholesaleSellerBankAccount.php
│   ├── WholesaleCustomer.php
│   ├── WholesaleCustomerAddress.php
│   ├── WholesaleProduct.php
│   ├── WholesaleCategory.php
│   ├── WholesaleProductPackaging.php
│   ├── WholesalePriceList.php
│   ├── WholesalePriceListItem.php
│   ├── WholesaleCustomerPricing.php
│   ├── WholesaleOrder.php
│   ├── WholesaleOrderItem.php
│   ├── WholesaleSale.php
│   ├── WholesaleSaleItem.php
│   ├── WholesalePayment.php
│   ├── WholesalePaymentAllocation.php
│   ├── WholesaleDelivery.php
│   ├── WholesaleDeliveryItem.php
│   ├── WholesaleReturn.php
│   ├── WholesaleReturnItem.php
│   ├── WholesaleTaxDocument.php
│   └── WholesaleTaxDocumentItem.php
└── Services/                 # Business logic & engines
    ├── WholesalePricingEngine.php
    ├── WholesaleInventoryBridge.php
    ├── WholesaleAccountingBridge.php
    ├── WholesaleTaxEngine.php
    ├── PekInvoiceXmlGenerator.php
    ├── PekAccDocXmlGenerator.php
    ├── PekXsdValidationService.php
    └── WholesaleReportService.php
```

---

## 5. PEK XML Engine & Schema Validation Analysis

### 5.1 Inspection of Official Reference Fixtures
Analysis of the provided production fixtures:
- `հարկային հաշիվ.xml` (Tax Invoice — Series A, Number 6629988041)
- `հաշիվ վավերագիր.xml` (Accounting Document — Series B, Number 9771705511)

#### Comparative Specification:
| Attribute / Element | Tax Invoice (Հարկային հաշիվ) | Accounting Document (Հաշիվ վավերագիր) |
| :--- | :--- | :--- |
| **Root Element** | `<ExportedData>` | `<ExportedAccDocData>` |
| **First Child** | `<SignedData>` | `<SignedAccDocData>` |
| **Data Container** | `<Data>` | `<Data>` |
| **Signable Data** | `<SignableData Version="1.0" xsi:type="Invoice">` | `<SignableData Version="1.0" xsi:type="AccountingDocument">` |
| **XML Namespace** | `http://www.taxservice.am/tp3/invoice/definitions` | `http://www.taxservice.am/tp3/invoice/definitions` |
| **Target Seller Regime** | VAT Payers (ԱԱՀ վճարող) | Non-VAT / Turnover Tax (Շրջհարկ / Հատուկ հարկային համակարգ) |
| **Tax Fields** | `<VATRate>20.0</VATRate>`, `<VAT>725</VAT>` | Explicitly omitted |
| **Deal Type Element** | Omitted (implied standard VAT) | `<DealType>SPECIAL_TAX_SYSTEM</DealType>` |
| **Bank Account** | Optional in Invoice | Required in SupplierInfo `<BankAccount>` |
| **Metadata Tag** | `<InvoiceMetadata><SubmissionDate>` | `<AccDocMetadata><SubmissionDate>` |
| **Signature Format** | `<Signature><SignatureType>PKCS7</SignatureType></Signature>` | `<Signature><SignatureType>PKCS7</SignatureType></Signature>` |

### 5.2 Official XSD Availability & Schema Validation Engine
1. **Namespace & Definition**: Both documents target the official namespace `http://www.taxservice.am/tp3/invoice/definitions`.
2. **Regression Schema Compilation**: Two strict W3C XML Schema definitions (`Invoice-1.0.xsd` and `AccountingDocument-1.0.xsd`) have been reverse-engineered from the official specification and production XML fixtures.
3. **Automated Validation Service**: `PekXsdValidationService` utilizes PHP's `DOMDocument::schemaValidate()` before any export is marked valid or offered for download.
4. **Validation Failure Handling**: Syntax errors, missing mandatory elements, non-standard date formats, invalid TIN lengths, and negative amounts produce structured, actionable diagnostic logs in the database.

---

## 6. Financial & Inventory Integration Architecture

### 6.1 Financial Ledger Invariant (Zero Duplicate Ledger)
All financial events generated in Wholesale invoke `FinancialLedgerService::postJournalEntry()` with immutable balancing lines:

#### Event A: Wholesale Sale Confirmed & Posted
```
Debit:  Account 2211 (Accounts Receivable - Wholesale Customer)    [Total Amount]
Credit: Account 6111 (Wholesale Revenue - Goods & Products)       [Subtotal]
Credit: Account 5243 (VAT Payable / ԱԱՀ)                         [VAT Total, if VAT payer]
Idempotency Key: "wholesale-sale-{sale_id}"
```

#### Event B: Wholesale Payment Received (Cash)
```
Debit:  Account 2511 (Cash in POS Registers / Till)                [Paid Amount]
Credit: Account 2211 (Accounts Receivable - Wholesale Customer)    [Paid Amount]
Idempotency Key: "wholesale-payment-{payment_id}"
```

#### Event C: Wholesale Payment Received (Bank Transfer)
```
Debit:  Account 2521 (Operating Bank Account - AMD)                [Paid Amount]
Credit: Account 2211 (Accounts Receivable - Wholesale Customer)    [Paid Amount]
Idempotency Key: "wholesale-payment-{payment_id}"
```

#### Event D: Wholesale Customer Return / Credit Note
```
Debit:  Account 6121 (Sales Returns & Allowances)                 [Subtotal]
Debit:  Account 5243 (VAT Reversal)                               [VAT Total, if VAT payer]
Credit: Account 2211 (Accounts Receivable / Customer Balance)     [Total Amount]
Idempotency Key: "wholesale-return-{return_id}"
```

### 6.2 Physical Inventory Integration (Explicit Stock Mapping)
- Each wholesale product maintains an optional `physical_stock_ingredient_id` referencing an existing `ingredients` record in the inventory foundation.
- When an order is dispatched and confirmed as delivered:
  1. `WholesaleInventoryBridge` queries mapped physical stock items.
  2. Resolves source warehouse from seller delivery settings.
  3. Verifies available balance in `inventory_balances`.
  4. Invokes `InventoryLedgerService::recordSale()` to atomically deplete physical stock.
  5. Links `inventory_transaction_id` on `wholesale_deliveries`.
- Unmapped products (e.g. customized services, delivery surcharges) bypass physical inventory depletion cleanly without throwing errors.

---

## 7. Security, Risk & Armenian Tax Compliance Assessment

| Risk Vector | Threat Analysis | Architectural Mitigation |
| :--- | :--- | :--- |
| **Unauthorized Module Access** | Non-paying or retail-only tenants accessing wholesale screens or API routes. | Strict `EnsureWholesaleAccess` middleware; `tenant_module_access` DB check; 0% exposure in UI/sidebar unless active. |
| **Cross-Tenant Data Leak** | Tenant A querying or referencing Seller/Customer/Order of Tenant B. | `BelongsToVendor` global scope, compound foreign keys, explicit `vendor_id` checks on all DTOs and routes. |
| **Invalid Tax Document Generation** | Incorrect XML exported, causing rejection by the State Revenue Committee (ՊԵԿ). | Automated XSD validation prior to download; strict regex checks for 8-digit Armenian TIN; automated calculation cross-checks. |
| **Duplicate Accounting Entries** | Retried webhook or double POS button clicks generating dual GL entries. | Unique UUID-based `idempotency_key` on all payments, sales, and ledger transactions. |
| **Inventory Desynchronization** | Wholesale order completed with insufficient stock in warehouse. | Optimistic inventory reservation during order confirmation; atomic database transactions during dispatch. |

---

## 8. Detailed Implementation Roadmap

- **PHASE 0**: System architecture analysis, repository audit, PEK XML fixture reverse engineering, and formal documentation (Current Phase).
- **PHASE 1**: Core domain foundation, Super Admin module access engine, `tenant_module_access` migrations, seller companies, tax profiles, and bank accounts.
- **PHASE 2**: Independent wholesale catalog, categories, packaging units, ATGAA classification management, and physical inventory stock-item mapping.
- **PHASE 3**: B2B customer directory, delivery addresses, multi-tier pricing engine, customer-specific contracts, and volume discounts.
- **PHASE 4**: Dedicated Wholesale POS terminal, order drafting, pre-orders, order confirmation, and invoice/sale creation.
- **PHASE 5**: Delivery logistics, driver assignments, partial deliveries, proof of delivery, customer returns, and physical stock depletion integration.
- **PHASE 6**: Payments processing (Cash & Bank Transfer), customer receivables ledger, credit limits, cash drawer/shift integration, and double-entry General Ledger posting.
- **PHASE 7**: PEK XML generation engine (Tax Invoices & Accounting Documents), XSD validation pipeline, error reporting, and export archive generation.
- **PHASE 8**: Wholesale reports & analytics (Sales by Seller/Customer/Product, Receivables aging, Tax summaries, PEK export logs).
- **PHASE 9**: Granular RBAC, security audits, regression tests with XML fixtures, multi-tenant isolation tests, and deployment guide.

