# QR Menu SaaS — Financial Operations Deployment & Migration Guide

## Overview

This guide provides the operational procedures for deploying the Cash Management & Financial Operations module into staging and production environments of the QR Menu SaaS platform.

---

## 1. Prerequisites & System Requirements

- **PHP**: 8.4+ (Tested on PHP 8.5.10 with `bcmath`, `pgsql`, `pdo_pgsql`, `intl`, `mbstring`).
- **Database**: PostgreSQL 16+ (Utilizes `uuid-ossp`, `numeric(14,2)` precision, partial indexes, and JSONB).
- **Framework**: Laravel 13.32+.
- **Cache / Queues**: Redis 7.0+ for high-throughput idempotency locks and asynchronous report compilation.

---

## 2. Migration Execution Order

The module introduces 7 database migrations, executed in strict dependency sequence:

1. `2026_11_07_020001_create_financial_chart_of_accounts_and_periods_tables.php`:
   - Creates `chart_of_accounts` and `accounting_periods`.
2. `2026_11_07_020002_create_financial_ledger_tables.php`:
   - Creates `journal_entries` and `journal_entry_lines`.
3. `2026_11_07_020003_create_cash_registers_and_enhanced_shifts_tables.php`:
   - Creates `cash_registers` and enhances `pos_shifts` / `pos_cash_movements`.
4. `2026_11_07_020004_create_financial_categories_expenses_and_incomes_tables.php`:
   - Creates `financial_categories`, `bank_accounts`, `expenses`, `expense_payments`, `incomes`.
5. `2026_11_07_020005_create_financial_transfers_and_bank_tables.php`:
   - Creates `financial_transfers`, `bank_transactions`, `bank_reconciliations`.
6. `2026_11_07_020006_create_financial_approval_and_audit_tables.php`:
   - Creates `financial_approval_policies`, `financial_approval_requests`, `financial_audit_logs`.
7. `2026_11_07_174336_add_journal_entry_id_to_expenses_table.php`:
   - Connects accrued expenses directly to `journal_entries`.

### Running Migrations

```bash
php artisan migrate --force --no-interaction
```

---

## 3. Permission Seeding & Enterprise Roles

The module registers 19 fine-grained permissions under the `finance` domain:

| Permission Code | Description | Default Roles |
| :--- | :--- | :--- |
| `finance.dashboard.view` | View executive financial dashboard | Owner, Accountant, Finance Manager |
| `finance.registers.view` | View cash registers & balances | Owner, Branch Manager, Cashier |
| `finance.registers.manage` | Create/edit registers & open drawers | Owner, Finance Manager |
| `finance.shifts.manage` | Open, reconcile, and close cash shifts | Owner, Branch Manager, Cashier |
| `finance.transfers.manage`| Safe drops, till transfers, bank deposits | Owner, Branch Manager |
| `finance.expenses.view` | View recorded operating expenses | Owner, Accountant, Finance Manager |
| `finance.expenses.create` | Create draft expenses | Owner, Staff, Cashier, Manager |
| `finance.expenses.approve`| Approve expenses & recognize liabilities | Owner, Finance Manager |
| `finance.expenses.pay` | Disburse cash or bank payments | Owner, Finance Manager |
| `finance.incomes.view` | View operational non-order incomes | Owner, Accountant, Finance Manager |
| `finance.incomes.create` | Record catering, rent, ancillary income | Owner, Finance Manager |
| `finance.bank.view` | View bank accounts & statements | Owner, Accountant, Finance Manager |
| `finance.bank.manage` | Add corporate bank accounts | Owner, Finance Manager |
| `finance.bank.reconcile` | Import statements & reconcile books | Owner, Accountant |
| `finance.ledger.view` | View Chart of Accounts & GL lines | Owner, Accountant, Auditor |
| `finance.ledger.manage` | Post manual journals & period closures | Owner, Accountant |
| `finance.reports.view` | Access P&L, Balance Sheet, Cash Flow | Owner, Accountant, Auditor |
| `finance.approvals.manage`| Configure monetary approval thresholds | Owner, Finance Manager |
| `finance.audit.view` | View immutable audit logs & tamper check| Owner, Auditor |

### Synchronizing Permissions to Database

```bash
php artisan db:seed --class=EnterprisePermissionsSeeder --force
```

---

## 4. Tenant Onboarding & COA Initialization

When a new restaurant tenant registers, or when existing tenants activate the finance module, their standard restaurant Chart of Accounts must be initialized.

### Automatic Initialization

`FinancialLedgerService::initializeDefaultAccounts(Vendor $vendor)` automatically runs during tenant creation or upon visiting `/admin/finance/ledger`.

### Default Chart of Accounts Template

- **1000s Assets**:
  - `1010`: Cash in Registers (Petty Cash/Till)
  - `1015`: Cash in Safe / Vault
  - `1020`: Operating Bank Account
  - `1030`: Card Clearing / Undeposited Funds
  - `1040`: Online Gateway Clearing (Idram / Telcell / Stripe)
  - `1200`: Accounts Receivable (Customer Tabs)
  - `1300`: Food & Beverage Inventory
- **2000s Liabilities**:
  - `2010`: Accounts Payable (Suppliers)
  - `2100`: Tips Payable (Employee Pool)
  - `2200`: Net Salaries Payable
  - `2300`: Sales Tax / VAT Payable (Armenia SRC)
  - `2350`: Payroll Taxes & Pension Payable
- **3000s Equity**:
  - `3010`: Owner Capital & Equity
  - `3020`: Retained Earnings
- **4000s Revenue**:
  - `4010`: Food Sales Revenue
  - `4020`: Beverage Sales Revenue
  - `4030`: Service Charge Revenue
  - `4100`: Other Operational Income
  - `4900`: Sales Returns & Allowances (Contra)
  - `4910`: Promotional Discounts Allowed (Contra)
- **5000s Expenses**:
  - `5010`: Cost of Goods Sold (Food Cost)
  - `5020`: Cost of Goods Sold (Beverage Cost)
  - `5100`: Wages & Salaries Expense
  - `5200`: Rent & Facility Lease
  - `5300`: Utilities (Electric, Gas, Water)
  - `5400`: Marketing & Advertising
  - `5500`: Operating Supplies & Disposables
  - `5800`: Cash Over / Short Discrepancy

---

## 5. Webhook & Background Queue Configuration

Configure the Laravel queue worker to handle async payment notifications and bank statement parsing:

```bash
php artisan queue:work redis --queue=financial-transactions,default --tries=3 --timeout=60
```

### Idempotent Webhook Processing

All incoming payment webhooks must pass the provider event ID as `idempotency_key` to `FinancialTransactionService::recordPaymentSettlement()`. Retried deliveries will instantly return the existing `JournalEntry` without re-posting lines or double-crediting balances.

---

## 6. Verification Checklist

After deploying to staging or production:

1. [ ] Run `php artisan test --compact tests/Feature/Finance` (Verify all 29 tests pass).
2. [ ] Visit `/admin/finance` as a Vendor Owner; ensure dashboard metrics render cleanly.
3. [ ] Verify sidebar navigation under **Finance & Cash** displays all 11 sub-modules.
4. [ ] Run `verifyTamperIntegrity` from `/admin/finance/audit/verify` to confirm zero initial ledger anomalies.
5. [ ] Open and close a test cashier shift to verify float reconciliation.

