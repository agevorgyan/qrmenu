# QR Menu SaaS — Financial Reconciliation & Anti-Duplication Report

## Executive Summary

The Cash Management & Financial Operations module integrates an immutable, double-entry general ledger inside the multi-tenant QR Menu SaaS platform. The system operates under a **Single Source of Truth** paradigm, strictly resolving and reconciling four independent financial dimensions:

1. **Sales Recognition (Accrual Dimension)**: Derived exclusively from `Order` sales posted to Revenue accounts (Credit 4010/4020) and Accounts Receivable (Debit 1200).
2. **Settlement (Liquidity Dimension)**: Derived exclusively from `PosPayment` and online gateway events, settling Accounts Receivable (Credit 1200) into Cash Till (Debit 1010), Card Clearing (Debit 1030), or Online Gateway (Debit 1040).
3. **Till Liquidity (Physical Cash Dimension)**: Monitored through `PosCashMovement` and `PosShift` records, tracking drawer cash, float, drops, payouts, and transfers without affecting sales revenue or expense accounts.
4. **Accounting Impact (General Ledger Dimension)**: Maintained in balanced `JournalEntry` and `JournalEntryLine` tables, ensuring total debits equal total credits for every transaction.

---

## 1. Prevention of Duplicate Financial Calculations

### The "Naive Summation" Anti-Pattern

In conventional POS systems, financial reports frequently compute revenue by naively querying multiple tables:
$$\text{Flawed Revenue} = \sum \text{Orders} + \sum \text{Payments}$$

Consider an order of 15,000 AMD split into 10,000 AMD cash and 5,000 AMD card, followed by a till-to-safe transfer of 8,000 AMD:
- If Orders are summed: 15,000 AMD.
- If Payments are summed: 15,000 AMD.
- If Cash Movements are summed: 8,000 AMD.
- **Naive sum**: 38,000 AMD (**153% overstated revenue!**)

### The QR Menu SaaS Solution

In this module:
- **Order Sales Recognition**:
  $$\text{Dr } 1200 \text{ (Accounts Receivable)} = 15,000$$
  $$\text{Cr } 4010 \text{ (Food Sales Revenue)} = 15,000$$
- **Payment Settlement**:
  $$\text{Dr } 1010 \text{ (Cash in Till)} = 10,000$$
  $$\text{Dr } 1030 \text{ (Card Clearing)} = 5,000$$
  $$\text{Cr } 1200 \text{ (Accounts Receivable)} = 15,000$$
- **Internal Till Transfer**:
  $$\text{Dr } 1015 \text{ (Cash in Safe)} = 8,000$$
  $$\text{Cr } 1010 \text{ (Cash in Till)} = 8,000$$

### Financial Dimension Verification Matrix

| Entity | Primary Table | Debited Account | Credited Account | P&L Impact | Cash Flow Impact |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Order Sale** | `orders` | 1200 (AR) | 4010/4020 (Revenue) | +15,000 | 0.00 |
| **Cash Settlement** | `pos_payments` | 1010 (Cash Till) | 1200 (AR) | 0.00 | +10,000 |
| **Card Settlement** | `pos_payments` | 1030 (Card Clearing)| 1200 (AR) | 0.00 | 0.00 (In Transit) |
| **Safe Transfer** | `financial_transfers` | 1015 (Safe) | 1010 (Cash Till) | 0.00 | 0.00 (Asset Swap) |

**Result**: Net Revenue reported on P&L is **exactly 15,000.00 AMD**, verified by automated test `FinancialReportingAndReconciliationTest::test_single_source_of_truth_proof_preventing_duplicate_calculations`.

---

## 2. Mathematical Reconciliation Proofs

### A. Shift Physical Cash Reconciliation Formula

Every shift computes expected cash according to strict physical liquidity tracking:
$$\text{Expected Cash} = \text{Opening Float} + \text{Cash Payments} + \text{Cash In} - \text{Cash Refunds} - \text{Cash Payouts} - \text{Cash Drops} - \text{Transfers Out} + \text{Transfers In}$$

$$\text{Cash Discrepancy} = \text{Counted Cash} - \text{Expected Cash}$$

- If $\text{Counted Cash} < \text{Expected Cash}$, a **Cash Shortage** is posted:
  $$\text{Dr } 5800 \text{ (Cash Over/Short Expense)}$$
  $$\text{Cr } 1010 \text{ (Cash in Till)}$$
- If $\text{Counted Cash} > \text{Expected Cash}$, a **Cash Overage** is posted:
  $$\text{Dr } 1010 \text{ (Cash in Till)}$$
  $$\text{Cr } 5800 \text{ (Cash Over/Short Expense)}$$

### B. Expense Accrual and Settlement Separation

Operating expenses strictly separate the recognition of expense obligations from cash/bank disbursements:

1. **Expense Accrual (Upon Approval)**:
   $$\text{Dr } 5300 \text{ (Operating Expense)} = X$$
   $$\text{Cr } 2010 \text{ (Accounts Payable)} = X$$
   *(Recognizes expense in period without prematurely deducting cash)*
2. **Partial Disbursement 1**:
   $$\text{Dr } 2010 \text{ (Accounts Payable)} = Y_1$$
   $$\text{Cr } 1020 \text{ (Bank Account)} = Y_1$$
3. **Final Disbursement 2**:
   $$\text{Dr } 2010 \text{ (Accounts Payable)} = Y_2$$
   $$\text{Cr } 1020 \text{ (Bank Account)} = Y_2$$

Where $Y_1 + Y_2 = X$.
- At final payment, the balance of Account `2010` is **0.00 AMD**.
- The expense recognized in P&L remains **exactly $X$** (never duplicated to $X + Y_1 + Y_2$).

### C. Fundamental Balance Sheet Equation

$$\text{Total Assets} = \text{Total Liabilities} + \text{Total Equity}$$

$$\text{Total Equity} = \text{Owner Capital} + \text{Retained Earnings Prior} + \text{Current Period Net Income}$$

$$\text{Current Period Net Income} = \sum \text{Revenue Credits} - \sum \text{Expense Debits}$$

All balance sheet generations verify `abs(Total Assets - Total Liabilities - Total Equity) < 0.01` to guarantee mathematical integrity.

### D. Bank Statement Reconciliation Proof

$$\text{Statement Ending Balance} - \text{General Ledger Book Balance} = \text{Unreconciled Variance}$$

Automated matching rules resolve statement items against `ExpensePayment`, `SupplierPayment`, and `PosPayment` batches by matching amount, reference number, and date windows.

---

## 3. Idempotency & Reversal Architecture

1. **Unique Idempotency Keys**:
   - Every financial mutation requires an idempotency key (e.g., `ord-sale-{id}`, `payment-settlement-{id}`, `exp-accrual-{id}`).
   - Duplicated API calls or retried webhooks safely return the existing `JournalEntry` without generating duplicate entries.
2. **Immutability of Posted Financial Records**:
   - `JournalEntry` rows with `status = 'posted'` cannot be edited or deleted.
   - Database foreign key constraints enforce `RESTRICT` on delete for active ledger accounts.
3. **Audited Reversals**:
   - Corrections require explicit reversal entries referencing `reversal_of_id`.
   - The original entry is marked `reversed`, preserving an unalterable audit chain.

---

## 4. Cryptographic & Mathematical Tamper Detection

The `FinancialAuditService::verifyTamperIntegrity` method runs scheduled or on-demand audits that mathematically evaluate:
1. **Header vs Line Sum Identity**:
   $$\text{Header Total Debit} = \sum \text{Line Base Currency Debits}$$
   $$\text{Header Total Credit} = \sum \text{Line Base Currency Credits}$$
2. **Entry Zero-Balance Invariant**:
   $$\left| \sum \text{Debits} - \sum \text{Credits} \right| < 0.01$$
3. **Stored Account Balance vs Ledger Invariant**:
   $$\text{Stored Balance} = \sum \text{All Historical Posted Debits} - \sum \text{All Historical Posted Credits}$$
4. **Duplicate Number Inspection**:
   $$\text{Count}(\text{entry\_number}) = 1 \quad \forall \text{ entries within tenant}$$

Any manual tampering directly inside PostgreSQL (e.g., via rogue SQL updates) is detected and flagged as `compromised` with detailed diagnostic line indicators.

