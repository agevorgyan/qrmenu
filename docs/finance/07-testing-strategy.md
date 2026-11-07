# PHASE 1 & 2 — TESTING STRATEGY & COMPREHENSIVE TEST MATRIX
## Cash Management & Financial Operations Module

---

### 1. Test Architecture & Quality Standards

Financial systems demand the highest standard of verification. The testing strategy enforces:
1. **Automated Feature & Unit Tests**: Covering every scenario in PHPUnit without mocks where real database constraints can be tested.
2. **ACID Concurrency Testing**: Testing race conditions on cash registers, idempotency keys, and shift closings.
3. **Double-Entry Mathematical Precision**: Verifying that debits equal credits on every posted journal entry.
4. **Zero-Duplication Verification**: Explicit tests proving that orders and payments do not create duplicate revenue, and that invoice payments do not create duplicate expenses.

---

## 2. Test Scenario Matrix

### Category A: Sales & Settlements
- [x] **A1: Single Order, Single Cash Settlement**: Order for 15,000 AMD paid in cash. Revenue: 12,500 AMD, VAT: 2,500 AMD, Cash: 15,000 AMD.
- [x] **A2: Single Order, Multiple / Mixed Payments**: Order for 20,000 AMD paid with 10,000 AMD cash and 10,000 AMD card.
- [x] **A3: Partial Settlement**: Order for 10,000 AMD paid with 6,000 AMD deposit; remaining 4,000 AMD in receivables.
- [x] **A4: Cancelled Order**: Draft order cancelled before preparation; zero revenue posted to General Ledger.
- [x] **A5: Full Order Refund**: Completed order refunded; verifies contra-revenue debit and cash/card reversal.
- [x] **A6: Partial Order Refund**: Order for 10,000 AMD partially refunded by 3,000 AMD.

### Category B: Cash Registers, Drawers & Shifts
- [x] **B1: Open Cash Drawer**: Initial float of 20,000 AMD recorded. Expected cash initialized.
- [x] **B2: Operational Movements**: Cash In (+5,000 AMD) and Cash Out (-3,000 AMD) update live expected balance.
- [x] **B3: Balanced Shift Close**: Counted cash matches expected cash; variance is 0.00 AMD.
- [x] **B4: Cash Shortage (Till Short)**: Counted cash is less than expected; cash short expense (5800) recognized.
- [x] **B5: Cash Overage (Till Surplus)**: Counted cash exceeds expected; surplus recorded.
- [x] **B6: Safe Drop / Vault Transfer**: 50,000 AMD moved from register to safe; asset-to-asset transfer with zero P&L impact.
- [x] **B7: Concurrent Cash Register Operations**: Two cashiers processing simultaneous orders on different registers.

### Category C: Operating Expenses & Incomes
- [x] **C1: Create Expense**: Draft expense submitted with category, amount, and receipt attachment.
- [x] **C2: Approval Workflow**: Manager approves expense; status moves to `approved`.
- [x] **C3: Rejection Workflow**: Manager rejects expense with justification note.
- [x] **C4: Partial Expense Payment**: 100,000 AMD expense paid in two installments (40,000 AMD + 60,000 AMD).
- [x] **C5: Full Expense Payment**: Cash payment from drawer reduces cash balance and marks expense `paid`.
- [x] **C6: Duplicate Expense Block**: Attempting to submit identical expense twice blocked by idempotency key.

### Category D: Duplicate Prevention & Idempotency
- [x] **D1: Duplicate Webhook Delivery**: Gateway fires payment webhook twice; second delivery returns 200 with no duplicate ledger entry.
- [x] **D2: Duplicate Payment Attempt**: Concurrent charge requests rejected by database unique constraint.
- [x] **D3: Duplicate Bank Statement Import**: Re-importing same bank statement file skips already imported transaction lines.
- [x] **D4: Duplicate Order Revenue**: Calling order completion handler multiple times creates exactly one journal entry.

### Category E: Accounting & Double-Entry Invariants
- [x] **E1: Debit/Credit Balance Check**: Any unbalancing journal entry rejected with exception before DB commit.
- [x] **E2: Transaction Reversal**: Posting reversal creates exact inverse lines and links `reversal_of_entry_id`.
- [x] **E3: Period Closing Lock**: Attempting to post transaction into a closed accounting period throws exception.
- [x] **E4: Financial Statements Reconciliation**: P&L net income matches the change in Retained Earnings on Balance Sheet.

### Category F: Multi-Tenant & RBAC Security
- [x] **F1: Cross-Tenant Isolation**: Tenant A user cannot read or mutate Tenant B cash registers, shifts, or ledger entries.
- [x] **F2: Branch Isolation**: Cashier in Branch 1 cannot access registers or open shifts in Branch 2.
- [x] **F3: Permission Enforcement**: User without `finance.expenses.approve` blocked with 403 on approval endpoint.
- [x] **F4: Immutable Audit Trail**: All financial updates produce tamper-proof audit records.

### Category G: Reconciliation Engine
- [x] **G1: Sales vs Orders**: Total completed order amounts equal credit revenue + tax in General Ledger.
- [x] **G2: Settlements vs Receipts**: Total POS payments match debits to cash/clearing accounts.
- [x] **G3: Bank Statement vs GL Cash**: Bank account reconciliation matches statement balance to ledger book balance.
- [x] **G4: Supplier Invoices vs Payments**: Supplier debt accurately reflects unpaid invoices minus payments.
- [x] **G5: Payroll Accrual vs Payouts**: Net salaries payable cleared exactly by salary disbursements.

