# B2B Wholesale POS & Distribution Subsystem
## 08 — Automated Testing & Quality Assurance Strategy
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Quality Objectives & Test Coverage

The testing strategy guarantees:
1. Zero regression on Retail POS, online orders, and existing menu operations.
2. Complete multi-tenant isolation with cross-tenant penetration tests.
3. Strict Super Admin module access gating with expired and revoked token verification.
4. XML output byte-for-byte schema compliance against official Armenian PEK XML specifications using both uploaded fixtures (`հարկային հաշիվ.xml` and `հաշիվ վավերագիր.xml`).
5. Double-entry financial balancing invariant (`SUM(debit) === SUM(credit)`).
6. Atomic physical inventory depletion and stock returns.

---

## 2. Test Architecture & Suites

### 2.1 Unit Test Suite (`tests/Unit/Wholesale/`)
- `WholesalePricingEngineTest.php`: Tests base prices, contract prices, quantity discount tiers, customer group discounts, and minimum price threshold enforcement.
- `PekInvoiceXmlGeneratorTest.php`: Tests XML generation against `հարկային հաշիվ.xml` fixture; verifies root tags, namespaces, element ordering, tax calculations, and PKCS7 signature block.
- `PekAccDocXmlGeneratorTest.php`: Tests XML generation against `հաշիվ վավերագիր.xml` fixture; verifies `SPECIAL_TAX_SYSTEM` deal type, seller bank details, and omission of VAT fields.
- `PekXsdValidationServiceTest.php`: Tests XSD schema validation; verifies valid documents pass and invalid inputs (bad TIN, malformed date, missing classifier) produce precise validation errors.
- `WholesaleTaxEngineTest.php`: Tests automatic selection of document type (Tax Invoice vs Accounting Document) based on seller tax regime.

### 2.2 Feature & Integration Test Suite (`tests/Feature/Wholesale/`)
- `WholesaleModuleEntitlementSecurityTest.php`:
  - Tenant without `wholesale_pos` receives 403 on all web and API routes.
  - Non-entitled tenant has 0% wholesale visibility in navigation or sidebar.
  - Tenant administrators cannot enable Wholesale POS for themselves.
  - Super Admin grants access -> tenant can immediately access module.
  - Revocation by Super Admin immediately cuts off tenant access.
  - Expired access (`expires_at < NOW()`) is automatically blocked.
- `WholesaleMultiTenantIsolationTest.php`:
  - Tenant A cannot view, edit, or delete Sellers, Customers, Orders, Deliveries, or Tax Documents of Tenant B.
  - Cross-tenant foreign keys or query parameter injections are strictly rejected.
- `WholesalePosOrderLifecycleTest.php`:
  - Draft order -> Confirmed order -> Sale creation -> Delivery fulfillment -> Stock depletion.
  - Verifies snapshot values of prices, classifier codes, and product names.
- `WholesaleGeneralLedgerPostingTest.php`:
  - Confirmed sale posts balanced double-entry to accounts `2211`, `6111`, `5243`.
  - Cash and bank payments post balanced entries to `2511`/`2521` and `2211`.
  - Customer return posts debit to `6121`, `5243` and credit to `2211`.
  - Idempotency key prevents duplicate journal entries under repeated calls.
- `WholesaleInventoryDepletionTest.php`:
  - Delivery confirmation depletes stock from `warehouses` via `InventoryLedgerService`.
  - Unmapped products do not trigger inventory errors.
  - Return restocks warehouse inventory correctly.
- `WholesalePekExportTest.php`:
  - Generates downloadable ZIP archive containing validated XML documents.
  - Tests export batch tracking and audit logs.

---

## 3. Test Fixtures

The production fixtures are stored in `tests/Fixtures/Wholesale/`:
- `հարկային հաշիվ.xml`
- `հաշիվ վավերագիր.xml`
- `Invoice-1.0.xsd`
- `AccountingDocument-1.0.xsd`

