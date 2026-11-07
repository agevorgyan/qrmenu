# PHASE 0 — EXISTING SYSTEM AUDIT & ANTI-DUPLICATION ARCHITECTURE
## Cash Management & Financial Operations Module

---

### Executive Summary

This audit establishes the definitive baseline of the existing **QR Menu SaaS** platform (`elab.menu`) across all financial, sales, settlement, payroll, procurement, and cashiering domains. The objective is to architect and implement an enterprise-grade **Cash Management & Financial Operations System** that serves as the single source of financial truth, enforcing immutable double-entry bookkeeping without duplicating existing tables, without creating competing revenue calculations, and without breaking existing operations.

---

## 1. Platform Baseline & Environment

| Component | Specification | Role in System |
| :--- | :--- | :--- |
| **Framework** | Laravel Framework `13.32.0` (PHP `8.5`) | Enterprise MVC, Service Layer, Action classes, Policies, FormRequests |
| **Database** | PostgreSQL 16 | ACID transactions, strict foreign keys, check constraints, `DECIMAL(14,2)` precision |
| **Multi-Tenancy** | Single-database multi-tenant (`TenantContext`, `TenantScope`, `BelongsToVendor`) | Tenant isolation rooted in `vendors` (`vendor_id`) and branch isolation in `locations` (`location_id`) |
| **Authorization (RBAC)**| Centralized RBAC (`AuthorizationService`, `Permission`, `Role`, `roles`, `permissions`) | Fine-grained permissions, custom role templates, location scoping, audit logging |
| **Monetary Precision** | Exact decimal / integer minor units | PostgreSQL `DECIMAL(14, 2)` / `DECIMAL(16, 4)`. Floating-point arithmetic is strictly prohibited |
| **Base Currency** | Multi-currency with tenant base currency | Default Armenian Dram (`AMD`), configurable per tenant (`USD`, `EUR`, `RUB`, `GEL`) |

---

## 2. Audit of Existing Modules & Financial Touchpoints

### 2.1. Sales & Orders Module (`orders`, `order_items`)
- **Existing Tables**: `orders`, `order_items`.
- **Financial Attributes**: `total_amount`, `subtotal`, `tax_amount`, `discount_amount`, `tip_amount`, `paid_amount`, `change_amount`, `currency`, `payment_method`, `payment_status` (`unpaid`, `partially_paid`, `paid`, `refunded`), `status` (`draft`, `held`, `pending`, `accepted`, `confirmed`, `preparing`, `served`, `completed`, `cancelled`).
- **Nature**: Represents the **Sales / Revenue Dimension** (Accrual basis).
- **Critical Architectural Rule**: An Order is a contract of sale. It is **NOT** a cash movement and **NOT** a payment. Revenue is recognized when an order is completed, regardless of whether it is settled via cash, card, or accounts receivable.

### 2.2. POS Shifts & Cash Drawer Module (`pos_shifts`, `pos_cash_movements`, `pos_payments`)
- **Existing Tables**:
  - `pos_devices`: Hardware terminals and mobile registers per branch.
  - `pos_shifts`: Operational till sessions (`opening_float`, `expected_cash`, `counted_cash`, `discrepancy`, `total_cash_sales`, `total_card_sales`, `total_other_sales`, `total_tips`, `total_refunds`, `total_cash_in`, `total_cash_out`, `orders_count`).
  - `pos_cash_movements`: Drawer cash in/out events (`cash_in`, `cash_out`, `safe_drop`, `expense`).
  - `pos_payments`: Completed customer retail payments per order (`payment_method`, `amount`, `cash_tendered`, `change_due`, `tip_amount`, `status`).
- **Nature**: Represents the **Physical Cash & Till Settlement Dimension**.
- **Critical Architectural Rule**: Cash in drawer reflects physical liquidity. Internal movements (`safe_drop`, `cash_in` float top-up, register transfers) **never** create revenue or operational expenses.

### 2.3. Online Payments & Gateways (`payment_attempts`, `PaymentService`, Gateways)
- **Existing Tables**: `payment_attempts` with `merchant_reference`, `provider_transaction_id`, `gateway` (Arca, Idram, Telcell, FastShift, Stripe), `idempotency_key`, `verified_at`.
- **Nature**: Represents **Third-Party Payment Clearing**.
- **Critical Architectural Rule**: Webhook deliveries and callback handlers must be strictly idempotent. A payment verification must update the order settlement status and generate clearing ledger entries without triggering duplicate revenue recognition.

### 2.4. Split Bills (`split_bills`, `split_bill_payments`)
- **Existing Tables**: `split_bills`, `split_bill_payments`.
- **Nature**: Participant-level settlement breakdown of a single parent Order.
- **Critical Architectural Rule**: Total split bill payments sum up to the order's `total_amount`. They must never be treated as independent sales.

### 2.5. Tips & Service Charges (`tips`, `tip_rating_settings`)
- **Existing Tables**: `tips` (`amount`, `payment_method`, `allocation_policy`, `allocation_status`).
- **Nature**: **Liability to Staff**, NOT restaurant revenue.
- **Critical Architectural Rule**: Tips collected via cash or card are credited to `Tips Payable` (Liability). When paid out to waiters, `Tips Payable` is debited and Cash/Bank is credited. Tips must never inflate restaurant Gross Revenue.

### 2.6. Procurement & Accounts Payable (`suppliers`, `supplier_invoices`, `supplier_payments`)
- **Existing Tables**:
  - `suppliers`: Vendors and AP debt (`current_balance`).
  - `supplier_invoices`: Bills from suppliers (`invoice_number`, `invoice_date`, `due_date`, `subtotal`, `tax_amount`, `total_amount`, `paid_amount`, `remaining_balance`, `payment_status`).
  - `supplier_payments`: Outgoing disbursements (`payment_number`, `payment_date`, `amount`, `payment_method`, `reference_number`).
- **Nature**: **Accrual Purchase vs Cash Disbursement**.
- **Critical Architectural Rule**: Inventory purchase expense is recognized on invoice receipt (Debit: Inventory/Purchases, Credit: Accounts Payable). The payment is purely liability settlement (Debit: Accounts Payable, Credit: Bank/Cash). Counting both invoice and payment as expenses would double-count expenses.

### 2.7. HR & Payroll System (`payroll_periods`, `payroll_items`, `salary_payments`, `employee_advances`)
- **Existing Tables**:
  - `payroll_periods`: Monthly/bi-weekly salary runs (`total_gross`, `total_net`, `total_tax`, `total_deductions`, `total_paid`, `status`).
  - `payroll_items`: Individual employee payslips (`gross_salary`, `income_tax`, `pension_fee`, `stamp_fee`, `net_salary`, `advance_deduction`).
  - `employee_advances`: Salary loans (`amount`, `disbursement_date`, `recovered_amount`, `remaining_amount`).
  - `salary_payments`: Salary payouts (`payment_number`, `payment_date`, `amount`, `payment_method`).
- **Nature**: **Labor Accrual vs Cash Payout**.
- **Critical Architectural Rule**: Payroll expense is recognized when the payroll period is approved/locked (Debit: Salaries Expense, Credit: Net Salary Payable + Tax Liabilities). The salary payment is liability settlement (Debit: Net Salary Payable, Credit: Bank/Cash). Payouts must never create duplicate salary expenses.

### 2.8. Subscriptions & Billing (`subscriptions`, `subscription_payments`)
- **Existing Tables**: `subscriptions`, `subscription_payments`.
- **Nature**: SaaS subscription billing paid by restaurant tenants to the platform.

### 2.9. Fiscalization (`PosFiscalService`, `ArmenianHdmFiscalAdapter`)
- **Existing Service**: Armenian SRC electronic cash register (HDM) integration.
- **Nature**: Legal fiscal registration of sales.
- **Critical Architectural Rule**: Fiscal receipt status (`fiscalized`, `fiscal_failed`) is strictly decoupled from settlement status (`paid`, `refunded`).

---

## 3. The 4 Non-Negotiable Financial Dimensions

```
+-----------------------------------------------------------------------------------+
|                            THE 4 FINANCIAL DIMENSIONS                             |
+-----------------------------------------------------------------------------------+
| 1. SALES DIMENSION (Order)                                                       |
|    - Recognizes Revenue on Accrual Basis                                         |
|    - Debit: Accounts Receivable / Customer Clearing                              |
|    - Credit: Sales Revenue (Food/Bev), Credit: Sales Tax Payable                 |
+-----------------------------------------------------------------------------------+
| 2. SETTLEMENT DIMENSION (Payment)                                                 |
|    - Liquidates Receivable / Closes Open Tab                                      |
|    - Debit: Cash on Hand (Drawer) OR Card Clearing / Undeposited Funds           |
|    - Credit: Accounts Receivable / Customer Clearing                             |
+-----------------------------------------------------------------------------------+
| 3. CASH MOVEMENT DIMENSION (Physical Till)                                        |
|    - Physical custody of cash notes and coins in the drawer                       |
|    - Till audit, safe drops, bank deposits, float top-ups                        |
|    - Asset-to-Asset transfers; ZERO effect on P&L revenue/expenses               |
+-----------------------------------------------------------------------------------+
| 4. LEDGER DIMENSION (General Ledger & Double-Entry)                               |
|    - Immutable, audit-proof single source of financial truth                     |
|    - Every financial event produces balanced Journal Entries: SUM(Dr) == SUM(Cr) |
+-----------------------------------------------------------------------------------+
```

### Prohibited Calculation Anti-Patterns:
1. `Revenue = Orders + Payments` ❌ (Catastrophic duplicate revenue).
2. `Total Expenses = Supplier Invoices + Supplier Payments` ❌ (Catastrophic duplicate AP expense).
3. `Total Wages = Payroll Total + Salary Payments` ❌ (Catastrophic duplicate labor expense).
4. `Cash Balance = Opening Float + Total Orders` ❌ (Ignores card/online payments, refunds, cash drops).
5. `Safe Drop / Register Transfer = Expense` ❌ (Internal asset transfer misclassified as cost).

---

## 4. Single Source of Truth & Ledger Engine Architecture

To guarantee financial integrity across all modules:

1. **Standard Chart of Accounts (COA)**:
   - **Assets (1000-1999)**: Cash in Registers (1010), Bank Accounts (1020), Accounts Receivable (1200), Inventory (1300), Employee Advances (1400).
   - **Liabilities (2000-2999)**: Accounts Payable (2010), Tips Payable (2100), Salaries Payable (2200), Taxes Payable (VAT/SRC) (2300).
   - **Equity (3000-3999)**: Owner Equity (3010), Retained Earnings (3020).
   - **Revenue (4000-4999)**: Food Sales (4010), Beverage Sales (4020), Service Charges (4030), Other Operational Income (4100), Contra-Revenue: Sales Returns/Discounts (4900).
   - **Expenses (5000-5999)**: Cost of Goods Sold / Food Cost (5010), Salaries & Wages (5100), Rent Expense (5200), Utilities (5300), Repairs & Maintenance (5400), General Operating Expenses (5500).

2. **Immutable Journal Entries & Balancing Invariant**:
   - Every posted journal entry must satisfy: `SUM(debits) - SUM(credits) == 0`.
   - Never update or delete a posted transaction or journal entry.
   - Any correction requires an explicit **Reversal Journal Entry** with full audit metadata.

3. **Idempotency & Database Integrity Constraints**:
   - Every financial transaction carries an `idempotency_key`.
   - Unique database constraints:
     - `UNIQUE(vendor_id, idempotency_key)`
     - `UNIQUE(vendor_id, source_type, source_id, is_reversal)`
   - Atomic database transactions (`DB::transaction`) wrap every state change and its corresponding ledger entry.

---

## 5. Module Integration Matrix

| Event / Source | Financial Dimension | GL Debit Account | GL Credit Account | Duplicate Prevention Mechanism |
| :--- | :--- | :--- | :--- | :--- |
| **Order Completed (Cash)** | Sales + Settlement | Cash in Register (1010) | Food/Bev Revenue (4010) + Tax (2300) | Atomic transaction; single journal entry; `order_id` source reference |
| **Order Completed (Card)** | Sales + Settlement | Card Clearing (1030) | Food/Bev Revenue (4010) + Tax (2300) | Atomic transaction; single journal entry; `order_id` source reference |
| **Customer Tip Added** | Non-Revenue Settlement | Cash / Card Clearing (1010/1030) | Tips Payable (2100 - Liability) | Separate tip line; credited to liability, excluded from revenue |
| **Order Refunded** | Sales Reversal | Sales Returns (4900) + Tax (2300) | Cash (1010) / Card Clearing (1030) | Reversal entry linked to original `order_id`; cannot exceed order total |
| **Cash Drop to Safe** | Asset Transfer | Safe / Vault Asset (1015) | Cash in Register (1010) | Asset-to-asset transfer; neither revenue nor expense account touched |
| **Cash Transfer between Branches** | Inter-branch Transfer | Target Branch Cash (1010-B) | Source Branch Cash (1010-A) | Dual-legged transfer record with `transfer_id` idempotency key |
| **Supplier Invoice Received** | Expense Recognition (Accrual) | Inventory / Purchases (1300/5010)| Accounts Payable (2010 - Liability) | Idempotent on `(vendor_id, supplier_id, invoice_number)` |
| **Supplier Invoice Paid** | Liability Settlement | Accounts Payable (2010) | Bank Account (1020) / Cash (1010) | Debits AP liability; zero impact on P&L expenses |
| **Payroll Period Approved** | Labor Accrual | Salaries Expense (5100) | Salaries Payable (2200) + Tax (2300) | Single entry per payroll period run; locked against recalculation |
| **Salary Paid to Worker** | Liability Settlement | Salaries Payable (2200) | Bank Account (1020) / Cash (1010) | Debits liability; zero impact on P&L expenses |
| **Employee Advance Given** | Asset Loan | Employee Advances (1400) | Cash in Register (1010) / Bank (1020) | Recorded as asset receivable, not an expense |
| **Direct Operating Expense** | Cash Expense | Operating Expense (5500) | Cash in Register (1010) / Bank (1020) | Expense entry created with approval workflow |

---

## 6. Audit Conclusion & Phase Transition

The baseline analysis confirms:
1. The platform contains robust operational models (`orders`, `pos_shifts`, `pos_payments`, `supplier_invoices`, `payroll_periods`), but lacks a unified **General Ledger**, a centralized **Chart of Accounts**, **Cash Register & Drawer Management**, **Bank Reconciliation**, and **Multi-branch Financial Consolidation**.
2. By implementing the General Ledger and linking existing operational events into balanced journal entries, all duplication is mathematically and architecturally eliminated.
3. We are ready to proceed with **PHASE 1: Financial Domain Design & Accounting Rules**.

