# B2B Wholesale POS & Distribution Subsystem
## 04 — API & Endpoint Architecture Specification
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Architectural Conventions

1. **Authentication & Guarding**: All endpoints require `auth:sanctum` or stateful web authentication with `EnsureWholesaleAccess` middleware.
2. **RESTful Structure**:
   - Web routes: `/admin/wholesale/*`
   - Internal Wholesale POS API: `/api/v1/wholesale/*`
   - Super Admin Access Console: `/superadmin/wholesale/*`
3. **Response Envelope**: Standard JSON response format:
   ```json
   {
       "success": true,
       "data": {},
       "message": "Operation completed successfully.",
       "meta": {}
   }
   ```
4. **Error Responses**: Standard HTTP status codes (400, 401, 403, 404, 422, 500) with diagnostic code and localized message.

---

## 2. Super Admin Module Access API

- `GET /superadmin/wholesale/tenants`: List all tenants with Wholesale POS entitlement status, expiration, and last actor.
- `POST /superadmin/wholesale/tenants/{vendor}/grant`: Grant Wholesale POS access with optional expiration date and reason.
- `POST /superadmin/wholesale/tenants/{vendor}/suspend`: Temporarily suspend access.
- `POST /superadmin/wholesale/tenants/{vendor}/revoke`: Revoke access immediately.
- `GET /superadmin/wholesale/tenants/{vendor}/logs`: Retrieve immutable audit history of access state changes.

---

## 3. Wholesale POS & Orders API

- `GET /api/v1/wholesale/pos/bootstrap`: Returns active seller entities, price lists, customer search index, payment methods, and cash register status.
- `GET /api/v1/wholesale/pos/products`: Search wholesale products by query, category, SKU, barcode, or ATGAA code.
- `POST /api/v1/wholesale/pos/calculate`: Run pricing engine across cart items (applies contract prices, quantity breaks, customer tier discounts, and VAT).
- `POST /api/v1/wholesale/orders`: Create draft, pre-order, or confirmed wholesale order.
- `POST /api/v1/wholesale/orders/{order}/confirm`: Move order to confirmed status.
- `POST /api/v1/wholesale/orders/{order}/sales`: Generate sales invoice from confirmed order.

---

## 4. Deliveries & Returns API

- `GET /api/v1/wholesale/deliveries`: List delivery dispatches by status, driver, and date.
- `POST /api/v1/wholesale/deliveries`: Create and schedule delivery batch.
- `POST /api/v1/wholesale/deliveries/{delivery}/dispatch`: Mark delivery as in-transit.
- `POST /api/v1/wholesale/deliveries/{delivery}/complete`: Record delivery completion, customer signature, and trigger atomic stock depletion.
- `POST /api/v1/wholesale/returns`: Process customer return, generate credit note, restock physical inventory, and post GL reversal.

---

## 5. Payments & Accounts Receivable API

- `GET /api/v1/wholesale/customers/{customer}/receivables`: Retrieve customer outstanding balance, overdue invoices, and credit limit.
- `POST /api/v1/wholesale/payments`: Record cash or bank transfer payment, allocating to open invoices and posting to General Ledger.

---

## 6. PEK XML Export API

- `GET /api/v1/wholesale/tax-documents`: List tax documents with validation status (draft, valid, invalid, exported).
- `POST /api/v1/wholesale/tax-documents/generate`: Generate Tax Invoices (Հարկային հաշիվ) or Accounting Documents (Հաշիվ վավերագիր) from confirmed sales.
- `POST /api/v1/wholesale/tax-documents/{document}/validate`: Run W3C XSD schema validation.
- `GET /api/v1/wholesale/tax-documents/export`: Download single XML or ZIP archive of validated XML files for manual upload to `e-invoicing.taxservice.am`.

