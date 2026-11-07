# PHASE 1 — ACCOUNTING RULES & GENERAL LEDGER SPECIFICATION
## Cash Management & Financial Operations Module

---

### 1. Fundamental Accounting Equation & Double-Entry Invariant

All financial operations in the QR Menu SaaS platform adhere strictly to standard double-entry bookkeeping:

$$\text{Assets} = \text{Liabilities} + \text{Equity} + (\text{Revenue} - \text{Expenses})$$

#### The Fundamental Balancing Invariant:
For every posted journal entry:
$$\sum \text{Debits} - \sum \text{Credits} = 0.00$$

A journal entry will be rejected with an `UnbalancedJournalEntryException` if total debits do not equal total credits down to exact monetary decimal cents/lumas (`0.00`).

---

## 2. Standard Chart of Accounts (COA)

Every tenant is provisioned with a standardized Chart of Accounts upon registration. Tenants may create custom sub-accounts, but system accounts cannot be deleted.

| Account Code | Account Name | Type | Normal Balance | Classification | System Locked |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **1010** | Cash in Registers (Petty Cash/Till) | Asset | Debit | Current Asset / Cash | Yes |
| **1015** | Cash in Safe / Vault | Asset | Debit | Current Asset / Cash | Yes |
| **1020** | Operating Bank Account | Asset | Debit | Current Asset / Bank | Yes |
| **1030** | Card Clearing / Undeposited Funds | Asset | Debit | Current Asset / Clearing | Yes |
| **1040** | Online Payment Gateway Clearing | Asset | Debit | Current Asset / Clearing | Yes |
| **1200** | Accounts Receivable (Customer Tabs) | Asset | Debit | Current Asset / Receivables | Yes |
| **1300** | Food & Beverage Inventory | Asset | Debit | Current Asset / Inventory | Yes |
| **1400** | Employee Advances & Loans | Asset | Debit | Current Asset / Receivables | Yes |
| **2010** | Accounts Payable (Suppliers) | Liability | Credit | Current Liability / AP | Yes |
| **2100** | Tips Payable (Employee Pool) | Liability | Credit | Current Liability / Staff | Yes |
| **2200** | Net Salaries Payable | Liability | Credit | Current Liability / Payroll | Yes |
| **2300** | Sales Tax / VAT Payable (Armenia SRC)| Liability | Credit | Current Liability / Taxes | Yes |
| **2350** | Payroll Taxes & Pension Payable | Liability | Credit | Current Liability / Taxes | Yes |
| **3010** | Owner's Capital & Equity | Equity | Credit | Equity / Capital | Yes |
| **3020** | Retained Earnings | Equity | Credit | Equity / Retained | Yes |
| **4010** | Food Sales Revenue | Revenue | Credit | Operating Revenue | Yes |
| **4020** | Beverage Sales Revenue | Revenue | Credit | Operating Revenue | Yes |
| **4030** | Service Charge Revenue | Revenue | Credit | Operating Revenue | Yes |
| **4100** | Other Operational Income | Revenue | Credit | Non-Operating Revenue | Yes |
| **4900** | Sales Returns & Allowances | Revenue | Debit (Contra) | Contra-Revenue | Yes |
| **4910** | Promotional Discounts Allowed | Revenue | Debit (Contra) | Contra-Revenue | Yes |
| **5010** | Cost of Goods Sold (COGS - Food) | Expense | Debit | Direct Cost | Yes |
| **5020** | Cost of Goods Sold (COGS - Bev) | Expense | Debit | Direct Cost | Yes |
| **5100** | Wages & Salaries Expense | Expense | Debit | Operating Expense / Labor | Yes |
| **5150** | Employer Pension & Social Security | Expense | Debit | Operating Expense / Labor | Yes |
| **5200** | Rent & Facility Expense | Expense | Debit | Operating Expense / Rent | Yes |
| **5300** | Utilities (Gas, Water, Electricity)| Expense | Debit | Operating Expense / Util | Yes |
| **5400** | Repairs & Maintenance | Expense | Debit | Operating Expense | Yes |
| **5500** | General & Admin Expenses | Expense | Debit | Operating Expense / G&A | Yes |
| **5600** | Marketing & Advertising | Expense | Debit | Operating Expense / Mktg | Yes |
| **5700** | Bank Fees & Payment Processing | Expense | Debit | Financial Expense | Yes |
| **5800** | Cash Over / Short (Till Discrepancy)| Expense | Debit / Credit | Other Operating | Yes |

---

## 3. Revenue Recognition Principle

1. **Accrual Basis**: Revenue is recognized at the moment an order is **completed** and delivered to the customer, NOT when payment is received.
2. **Gross vs Net Revenue**:
   $$\text{Gross Sales} = \text{Sum of Menu Items at Regular Selling Price}$$
   $$\text{Net Sales} = \text{Gross Sales} - \text{Discounts Allowed} - \text{Refunds/Returns}$$
3. **Exclusion of Taxes and Tips**:
   - VAT / Sales Tax collected from the customer is recorded directly into `2300 - Sales Tax / VAT Payable` (Liability). It is NEVER counted as revenue.
   - Tips collected for staff are recorded directly into `2100 - Tips Payable` (Liability). Tips NEVER belong to restaurant revenue.

---

## 4. Expense Recognition & Separation of Payment

1. **Accrual Expenses (Suppliers & Labor)**:
   - When a supplier invoice arrives:
     - Debit: `1300 - Inventory` (or `5010 - COGS`)
     - Credit: `2010 - Accounts Payable`
   - When the supplier invoice is paid later:
     - Debit: `2010 - Accounts Payable`
     - Credit: `1020 - Bank Account` (or `1010 - Cash in Register`)
   - **Crucial Rule**: The payment does NOT touch the Expense account! Only the initial invoice touches the expense/asset account.

2. **Direct Operating Expenses (Petty Cash / Register Payouts)**:
   - When a manager takes cash from the drawer to buy emergency kitchen cleaning supplies:
     - Debit: `5500 - General Operating Expenses`
     - Credit: `1010 - Cash in Register`
     - Cash shift expected cash balance is reduced by the payout amount.

---

## 5. Physical Till Discrepancies & Cash Over/Short

When a cash register shift is closed:
$$\text{Variance} = \text{Counted Cash} - \text{Expected Cash}$$

1. **Cash Shortage** ($\text{Variance} < 0$):
   - Physical cash is missing.
   - Debit: `5800 - Cash Over / Short` (Expense)
   - Credit: `1010 - Cash in Register` (Asset reduction)
2. **Cash Overage** ($\text{Variance} > 0$):
   - Physical cash is surplus.
   - Debit: `1010 - Cash in Register` (Asset increase)
   - Credit: `5800 - Cash Over / Short` (Negative expense / Other income)
3. **Balanced** ($\text{Variance} = 0.00$):
   - No adjustment entry required.

---

## 6. Internal Asset Transfers

Internal money movements between drawers, vaults, and bank accounts are **Asset-to-Asset** reclassifications:
- Safe drop: `DR: 1015 Safe` / `CR: 1010 Register`
- Bank cash deposit: `DR: 1020 Bank` / `CR: 1010 Register`
- Branch cash transfer: `DR: 1010 Branch B` / `CR: 1010 Branch A`

**Strict Invariant**: Internal transfers must NEVER touch any account in classes 4000 (Revenue) or 5000 (Expense).

---

## 7. Reversals & Immutability Rules

1. **No Hard Deletes**: The SQL statement `DELETE FROM journal_entries` or `DELETE FROM pos_payments` is strictly forbidden and protected by database triggers / foreign keys.
2. **Reversals**: To reverse a transaction:
   - A new journal entry is created with `is_reversal = true` and `reversal_of_entry_id = [original_entry_id]`.
   - Exact inverse debit and credit lines are posted.
   - Original entry status changes from `posted` to `reversed`.

---

## 8. Period Closing & Date Locking

1. Tenants define accounting periods (e.g. Monthly).
2. When a period status is set to `closed`:
   - No new journal entries or operational transactions can be back-dated into that period.
   - Any late adjustments must be posted into the currently `open` period with an explanatory memo.

