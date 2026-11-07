# PHASE 1 & 2 — SECURITY, RBAC & APPROVAL ARCHITECTURE
## Cash Management & Financial Operations Module

---

### 1. Multi-Tenant & Branch Data Isolation

1. **Vendor Boundary**:
   - Every financial entity (`ChartOfAccount`, `JournalEntry`, `CashRegister`, `PosShift`, `Expense`, `BankAccount`, `FinancialAuditLog`) is strictly bound to `vendor_id`.
   - The global scope `TenantScope` automatically appends `WHERE vendor_id = ?` to all queries.
   - Any query attempting to access or mutate a financial entity belonging to another vendor throws an immediate `AuthorizationException` (HTTP 403 / 404).

2. **Branch Scoping**:
   - Location-specific entities (`CashRegister`, `PosShift`, location-scoped `JournalEntryLines`, `Expenses`) enforce `location_id` filtering.
   - Cashiers and local branch managers can only view and manage registers, shifts, and cash movements for their assigned branch.
   - Company owners, finance directors, and central accountants have enterprise-wide multi-branch visibility.

---

## 2. Permission Catalog & Roles Matrix

### 2.1. New Permissions Added to `App\Security\Permission`:

| Permission Constant | String Key | Description | Risk Level |
| :--- | :--- | :--- | :--- |
| `FINANCE_VIEW` | `finance.view` | View financial menu & overview | Low |
| `FINANCE_DASHBOARD` | `finance.dashboard` | View top-level financial KPI dashboard | Medium |
| `FINANCE_REGISTERS_MANAGE` | `finance.registers.manage` | Create, configure & assign cash registers | High |
| `FINANCE_SHIFTS_VIEW` | `finance.shifts.view` | View cash shifts & summaries | Low |
| `FINANCE_SHIFTS_MANAGE` | `finance.shifts.manage` | Open, record movements, & close shifts | Medium |
| `FINANCE_SHIFTS_APPROVE` | `finance.shifts.approve` | Manager sign-off on cash variances | High |
| `FINANCE_EXPENSES_VIEW` | `finance.expenses.view` | View operating expenses list | Low |
| `FINANCE_EXPENSES_CREATE` | `finance.expenses.create` | Submit expense requests / bills | Low |
| `FINANCE_EXPENSES_APPROVE` | `finance.expenses.approve` | Approve or reject pending expenses | High |
| `FINANCE_EXPENSES_PAY` | `finance.expenses.pay` | Disburse payment for approved expenses | High |
| `FINANCE_INCOME_MANAGE` | `finance.income.manage` | Record other operational income | Medium |
| `FINANCE_TRANSFERS_MANAGE` | `finance.transfers.manage`| Execute cash drops & inter-account transfers| High |
| `FINANCE_BANK_MANAGE` | `finance.bank.manage` | Add bank accounts & import statements | High |
| `FINANCE_BANK_RECONCILE` | `finance.bank.reconcile` | Perform bank reconciliation & matching | High |
| `FINANCE_LEDGER_VIEW` | `finance.ledger.view` | Inspect General Ledger journal entries | Medium |
| `FINANCE_LEDGER_MANAGE` | `finance.ledger.manage` | Post manual entries & process reversals | Critical |
| `FINANCE_REPORTS_VIEW` | `finance.reports.view` | View P&L, Balance Sheet, Cash Flow | Medium |
| `FINANCE_REPORTS_EXPORT` | `finance.reports.export` | Export sensitive financial data (CSV/Excel)| High |
| `FINANCE_PERIOD_CLOSE` | `finance.period.close` | Lock accounting periods against changes | Critical |

---

### 2.2. Role-to-Permission Mapping Matrix

| Permission | Owner | Finance Manager / Accountant | Branch Manager | Cashier | Auditor |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `finance.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `finance.dashboard` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `finance.registers.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `finance.shifts.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `finance.shifts.manage` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `finance.shifts.approve` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `finance.expenses.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `finance.expenses.create` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `finance.expenses.approve` | ✅ | ✅ | ❌* | ❌ | ❌ |
| `finance.expenses.pay` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `finance.income.manage` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `finance.transfers.manage`| ✅ | ✅ | ✅ | ❌ | ❌ |
| `finance.bank.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `finance.bank.reconcile` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `finance.ledger.view` | ✅ | ✅ | ❌ | ❌ | ✅ |
| `finance.ledger.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `finance.reports.view` | ✅ | ✅ | ✅ (Branch) | ❌ | ✅ |
| `finance.reports.export` | ✅ | ✅ | ❌ | ❌ | ✅ |
| `finance.period.close` | ✅ | ✅ | ❌ | ❌ | ❌ |

*\*Branch Managers may approve petty expenses below their configured branch threshold (e.g. 25,000 AMD).*

---

## 3. Configurable Approval Limits & Tiered Authorization

The platform supports a policy engine `financial_approval_policies`:
- **Expenses**:
  - Below Tier 1 (e.g. 25,000 AMD): Auto-approved or Branch Manager approval.
  - Tier 2 (25,000 – 250,000 AMD): Requires Finance Manager / Accountant approval.
  - Tier 3 (> 250,000 AMD): Requires Restaurant Owner approval.
- **Refunds**:
  - Cashier cannot issue refunds exceeding 5,000 AMD without Manager PIN verification.
- **Cash Variances**:
  - Shift discrepancies greater than 2,000 AMD flag an automatic audit warning requiring written managerial justification.

---

## 4. Tamper-Proof Financial Audit Trail

1. Every monetary transaction automatically inserts a row into `financial_audit_logs`.
2. The log captures:
   - `action` (`expense_approved`, `shift_closed`, `reversal_posted`, `reconciliation_completed`, etc.)
   - `user_id`, `ip_address`, `user_agent`
   - `old_values` and `new_values` snapshots (JSONB)
   - `amount` and `currency`
3. Audit logs cannot be updated or deleted via application APIs.

