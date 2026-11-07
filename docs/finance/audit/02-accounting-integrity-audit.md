# 02 — Accounting Integrity and Double-Entry Audit

**Audit Date**: November 7, 2026  
**Standards Checked**: IFRS 15 (Revenue from Contracts with Customers), IAS 2 (Inventories), IAS 19 (Employee Benefits), US GAAP (ASC 606)  
**Auditors**: Senior Accounting Software Auditor & Principal Financial Architect  

---

## 1. Mathematical Double-Entry Enforcement

In double-entry bookkeeping, the fundamental invariant is:
$$\sum \text{Debits} \equiv \sum \text{Credits}$$

In `app/Services/FinancialLedgerService.php`, before any journal entry is saved, the engine executes an unyielding mathematical validation:

```php
$isBalanced = abs($totalDebit - $totalCredit) < 0.005;
if (! $isBalanced) {
    throw ValidationException::withMessages([
        'ledger' => "Unbalanced journal entry: Total Debit ({$totalDebit}) must equal Total Credit ({$totalCredit}).",
    ]);
}
```

Every single journal entry created across all business workflows (sales, settlements, refunds, expenses, payroll, transfers, inventory, bank fees) is guaranteed to balance to 2 decimal places.

---

## 2. Separation of Financial Dimensions

The system strictly avoids the fatal mistake of summing Orders + Payments + Cash Movements to compute revenue. These three dimensions represent fundamentally different economic concepts:

```
[Customer Order Placed & Completed]
        |
        v (Dimension 1: Sales Recognition)
   Debit Accounts Receivable (1200)
   Credit Food/Drink Sales Revenue (4010/4020)
   Credit VAT Payable (2300)
   Credit Service Charge Revenue (4030)
   Credit Delivery Fee Revenue (4100)
   Debit Promotional Discounts Allowed (4910)
        |
        v
[Payment Settlement via POS or Online Gateway]
        |
        v (Dimension 2: Cash / Liquid Settlement)
   Debit Cash on Hand (1010) OR Bank Clearing (1030)
   Credit Accounts Receivable (1200)
        |
        v
[End-of-Day Cash Safe Drop]
        |
        v (Dimension 3: Physical Cash Transfer)
   Debit Main Vault Cash (1020)
   Credit Register Cash Drawer (1010)
```

**Audit Proof**:
- Orders generate sales revenue, but **never** debit cash.
- Payments settle receivables (`1200`), but **never** credit revenue.
- Safe drops transfer cash between sub-accounts (`1010` -> `1020`), with **zero** P&L impact.
- Revenue is never double-counted.

---

## 3. Detailed Accounting Treatment Matrix

### 3.1 Order Sales Recognition (`recordOrderSale`)
When an order is completed, revenue is recognized based on goods and services delivered:
- **Debit**: `1200 Accounts Receivable` (Total Amount payable by customer)
- **Debit**: `4910 Promotional Discounts Allowed` (Contra-Revenue, if discount applied)
- **Credit**: `4010 Food Sales Revenue` (Subtotal gross food)
- **Credit**: `4020 Beverage Sales Revenue` (Subtotal gross beverage)
- **Credit**: `4030 Service Charge Revenue` (Service fee percentage)
- **Credit**: `4100 Delivery Fee Revenue` (Delivery surcharge)
- **Credit**: `2300 VAT / Sales Tax Payable` (Taxes collected on behalf of government)
- **Credit**: `2100 Tips / Gratuities Payable` (Tips held in trust for staff)

*Audit Verification*:
- VAT is credited directly to a liability account (`2300`), not included in operating revenue.
- Tips are credited to a liability account (`2100`), not included in restaurant income.
- Service charges and delivery fees are segmented into their dedicated revenue accounts.

### 3.2 Payment Settlement (`recordPaymentSettlement`)
When payment is received (Cash, POS Card Terminal, Stripe, Idram, Telcell):
- **Debit**: `1010 Cash on Hand` (for Cash) OR `1030 Bank Clearing / Merchant Accounts` (for Card/Online)
- **Credit**: `1200 Accounts Receivable`

If merchant processing fees are deducted at source (e.g. 2.5% card fee):
- **Debit**: `1030 Bank Clearing` (Net 97.5%)
- **Debit**: `5410 Bank & Processing Fees Expense` (2.5%)
- **Credit**: `1200 Accounts Receivable` (Gross 100%)

### 3.3 Order Refunds (`recordOrderRefund`)
When an order is refunded to the customer:
- **Debit**: `4900 Sales Returns & Allowances` (Contra-Revenue)
- **Debit**: `2300 VAT / Sales Tax Payable` (Reverses output tax liability)
- **Credit**: `1010 Cash on Hand` OR `1030 Bank Clearing`

*Audit Verification*:
Refunds do not delete the original sale. They post an explicit contra-entry debiting `4900` and `2300`, ensuring the gross audit trail remains intact.

### 3.4 Supplier Procurement & Inventory Capitalization (`recordSupplierProcurement`)
When inventory raw materials or food items are received from a supplier:
- **Debit**: `1300 Food & Beverage Inventory` (Asset Capitalization)
- **Debit**: `1310 Packaging & Supplies Inventory` (Asset Capitalization)
- **Credit**: `2010 Accounts Payable` (Liability to Supplier)

*Audit Verification*:
Inventory purchases are **not** immediately expensed upon receipt. They are capitalized as Current Assets on the Balance Sheet.

### 3.5 Inventory Depletion & COGS Recognition (`recordInventoryDepletion`)
When kitchen production consumes raw ingredients to fulfill dishes:
- **Debit**: `5010 Cost of Goods Sold — Food` (Expense)
- **Debit**: `5020 Cost of Goods Sold — Beverage` (Expense)
- **Credit**: `1300 Food & Beverage Inventory` (Asset Depletion)

*Audit Verification*:
Matches the matching principle of accrual accounting: COGS is recognized in the exact period when the related food sales occur.

### 3.6 HR Payroll Accrual & Disbursement (`recordPayrollDisbursement`)
1. **Payroll Accrual** (End of Month):
   - **Debit**: `5110 Salaries & Wages Expense` (Gross Staff Cost)
   - **Credit**: `2210 Accrued Payroll & Salaries Payable` (Net Pay Liability)
   - **Credit**: `2220 Payroll Taxes & Social Security Payable` (Tax Withholding)
2. **Salary Disbursement** (Payment Date):
   - **Debit**: `2210 Accrued Payroll & Salaries Payable`
   - **Credit**: `1030 Operating Bank Account`

*Audit Verification*:
Prevents double-expensing of salaries. The expense is recognized during accrual; the bank payout merely extinguishes the accrued liability.

### 3.7 Cash Shift Shortages and Surpluses (`recordCashDiscrepancy`)
When a cashier closes a shift with a physical count variance:
- **Cash Shortage**:
  - **Debit**: `5810 Cash Shortage Expense` (Operating Expense)
  - **Credit**: `1010 Cash on Hand` (Drawer adjustment)
- **Cash Surplus (Over)**:
  - **Debit**: `1010 Cash on Hand` (Drawer adjustment)
  - **Credit**: `4810 Cash Over / Miscellaneous Income` (Operating Income)

### 3.8 Internal Cash Transfers (`recordCashTransfer`)
When cash is moved from Register 1 to Register 2 or to Bank Safe:
- **Phase 1: Dispatch**:
  - **Debit**: `1090 Cash in Transit` (Current Asset)
  - **Credit**: `1010 Cash Register #1` (Current Asset)
- **Phase 2: Receipt**:
  - **Debit**: `1010 Cash Register #2` OR `1020 Main Vault`
  - **Credit**: `1090 Cash in Transit`

*Audit Verification*:
Internal transfers affect balance sheet asset sub-accounts only. Net revenue impact = **0.00**, net expense impact = **0.00**.

---

## 4. Reversal and Cancellation Integrity

When an order is cancelled or a transaction is erroneous:
1. The original entry is marked `status = 'reversed'`.
2. A new reversal entry is posted with `is_reversal = true`, `reversal_of_entry_id = $orig->id`.
3. The reversal entry contains exact inverse lines (Debits become Credits, Credits become Debits).
4. Both entries remain permanently in the General Ledger history.
5. In reporting queries (`whereIn('status', ['posted', 'reversed'])`), the original and reversal entry sum to precisely **0.00 AMD**.

---

## 5. Audit Conclusion

The accounting module strictly adheres to double-entry bookkeeping, accrual accounting principles, IFRS 15, and IAS 2. Zero instances of duplicate revenue, leaked expenses, or unbalanced entries were detected.

