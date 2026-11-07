# 10 — Critical Bugs Discovered and Remediation Report

**Audit Date**: November 7, 2026  
**Auditors**: Independent Financial Systems Architecture Review Team  

---

## 1. Overview of Audit Findings

During the Phase 13 independent audit, the audit team subjected the implementation to rigorous stress testing, schema validation, and accounting scrutiny. A total of **8 bugs** (ranging from Critical to Medium severity) were identified, isolated, and remediated in the codebase.

Every single bug now has an automated regression test proving the fix.

---

## 2. Bug Remediation Catalog

### BUG-01: Fatal Query Exceptions on PostgreSQL in Dashboard Summary
- **Severity**: **CRITICAL**
- **Affected File**: `app/Services/FinancialReportingService.php` (`getDashboardSummary`)
- **Impact**: The Executive Financial Dashboard threw fatal SQL errors on PostgreSQL, rendering the entire financial landing page inaccessible.
- **Root Causes**:
  1. Queried non-existent column `cash_registers.is_active` (actual column is `cash_registers.status`).
  2. Queried non-existent column `expenses.total_amount` and `expenses.payment_status` (actual columns are `amount`, `remaining_amount`, `status`).
  3. Queried non-existent table `order_refunds` (refunds are tracked in General Ledger and POS payments).
  4. Queried non-existent column `pos_shifts.cash_discrepancy` (actual column is `pos_shifts.discrepancy`).
  5. Missing class import: `use App\Models\JournalEntry;`.
- **Remediation**:
  Refactored all dashboard queries to query exact schema columns and queried GL entries with `source_type = 'Refund'` for refunds.
- **Verification**: `IndependentFinancialAuditTest` and dashboard tests pass cleanly without SQLSTATE errors.

---

### BUG-02: Cryptographic Signature Float Formatting Mismatch
- **Severity**: **HIGH**
- **Affected Files**: `app/Services/FinancialLedgerService.php`, `app/Services/FinancialAuditService.php`
- **Impact**: Cryptographic tamper detection failed with false positive `cryptographic_signature_mismatch` errors on un-tampered entries.
- **Root Cause**: PHP floating-point numbers serialize non-deterministically (e.g. `50000` vs `50000.00`). If one method used raw cast `(float) $amount` while the other used formatted string, HMAC hashes differed.
- **Remediation**:
  Standardized canonical string representation via `number_format((float) $amount, 2, '.', '')` across both `FinancialLedgerService::generateEntryHash()` and `FinancialAuditService::verifyTamperIntegrity()`.
- **Verification**: Verified via `test_cryptographic_hmac_hash_chain_detects_malicious_database_alteration`.

---

### BUG-03: General Ledger Reversal Queries Excluding Reversed Original Entries
- **Severity**: **CRITICAL**
- **Affected File**: `app/Services/FinancialReportingService.php`
- **Impact**: When an order was reversed, P&L reported a **negative revenue** (e.g. -30,000 AMD) instead of net zero (0.00 AMD).
- **Root Cause**:
  When an entry is reversed, its status is updated to `'reversed'` and a reversal entry is created with status `'posted'`. All financial reporting queries (`getProfitAndLoss`, `getBalanceSheet`, `getTrialBalance`, `calculateCashBalanceAtDate`) filtered strictly by `where('journal_entries.status', 'posted')`. Consequently, they excluded the original entry (+30,000) and only included the reversal entry (-30,000), producing -30,000!
- **Remediation**:
  Updated all reporting queries to filter by `whereIn('journal_entries.status', ['posted', 'reversed'])`. Both the original (+30,000) and reversal (-30,000) entries are summed, yielding precisely 0.00 AMD.
- **Verification**: Verified via `test_cancelled_order_reversal_wipes_premature_revenue`.

---

### BUG-04: Misclassified Contra-Revenue Accounts in P&L Engine
- **Severity**: **HIGH**
- **Affected File**: `app/Services/FinancialReportingService.php`
- **Impact**: Service Charge Revenue (`4030`) was treated as a contra-revenue deduction, artificially deflating gross revenue.
- **Root Cause**:
  P&L hardcoded `in_array($acc['code'], ['4030', '4040'])` as contra accounts. In the standard chart of accounts, `4030` is **Service Charge Revenue** (normal operating revenue), while contra-revenue accounts are `4900 (Sales Returns)` and `4910 (Promotional Discounts Allowed)`.
- **Remediation**:
  Updated check to `in_array($acc['code'], ['4900', '4910'], true) || $acc['subtype'] === 'contra_revenue'`.
- **Verification**: Verified via `test_service_charge_delivery_fee_and_discounts_accounting_balance`.

---

### BUG-05: Premature Sales Revenue Posting on Cancelled Orders
- **Severity**: **HIGH**
- **Affected File**: `app/Services/FinancialTransactionService.php` (`recordOrderSale`)
- **Impact**: Calling `recordOrderSale` on an already cancelled or voided order posted sales revenue to the ledger.
- **Root Cause**: Missing validation check asserting `$order->status !== 'cancelled'`.
- **Remediation**:
  Added guard throwing `ValidationException` if attempting to post sales revenue for a cancelled, voided, or refunded order. Added `reverseOrderSale()` method for reversing previously posted orders that get cancelled downstream.
- **Verification**: Verified via `test_cancelled_order_cannot_post_sales_revenue`.

---

### BUG-06: Non-Deterministic Random Idempotency Keys in Order Refunds
- **Severity**: **HIGH**
- **Affected Files**: `app/Services/FinancialTransactionService.php`, `app/Services/RefundService.php`
- **Impact**: Retrying a refund request created a duplicate journal entry because the idempotency key contained `Str::random(6)`.
- **Root Cause**: `idempotencyKey = "refund-order-{$order->id}-" . Str::random(6);` generated a new key on every retry!
- **Remediation**:
  Removed `Str::random(6)`. Enabled deterministic custom idempotency keys (e.g. payment gateway refund ID or `refund-{$refund->id}`).
- **Verification**: Verified via `test_duplicate_refund_requests_are_idempotent`.

---

### BUG-07: Unprotected Deletion of Posted Journal Entries & Lines
- **Severity**: **HIGH**
- **Affected Files**: `app/Models/JournalEntry.php`, `app/Models/JournalEntryLine.php`
- **Impact**: Developers or commands calling `$entry->delete()` or `$line->delete()` could delete posted accounting transactions.
- **Root Cause**: Absence of deleting hooks in Eloquent models.
- **Remediation**:
  Added `static::deleting` model lifecycle hooks throwing `LogicException` on any attempt to delete posted or reversed journal entries or lines.
- **Verification**: Verified via `test_posted_journal_entries_and_lines_are_immutable_against_deletion` and `test_posted_journal_entry_lines_are_immutable_against_deletion`.

---

### BUG-08: Debit/Credit Balance Miscalculation for Contra-Revenue Accounts
- **Severity**: **MEDIUM**
- **Affected File**: `app/Models/ChartOfAccount.php`
- **Impact**: Promotional Discounts Allowed (`4910`) displayed negative current balances because its `subtype = 'contra_revenue'` was treated as credit-normal like general revenue.
- **Root Cause**: `isDebitNormal()` only checked `type === 'asset' || type === 'expense'`.
- **Remediation**:
  Updated `isDebitNormal()` to include `subtype === 'contra_revenue'` and `isCreditNormal()` to exclude it.
- **Verification**: Verified via `test_service_charge_delivery_fee_and_discounts_accounting_balance`.

---

## 3. Summary of Remediations

| Bug ID | Title | Severity | Status | Regression Test |
| :--- | :--- | :---: | :---: | :--- |
| **BUG-01** | Dashboard SQLSTATE Query Failures | Critical | **RESOLVED** | `IndependentFinancialAuditTest` |
| **BUG-02** | HMAC Signature Format Mismatch | High | **RESOLVED** | `test_cryptographic_hmac_hash_chain...` |
| **BUG-03** | Reversal Query Negative Revenue | Critical | **RESOLVED** | `test_cancelled_order_reversal...` |
| **BUG-04** | P&L Contra-Revenue Account Codes | High | **RESOLVED** | `test_service_charge_delivery_fee...` |
| **BUG-05** | Premature Revenue on Cancelled Orders | High | **RESOLVED** | `test_cancelled_order_cannot_post...` |
| **BUG-06** | Non-Deterministic Refund Keys | High | **RESOLVED** | `test_duplicate_refund_requests...` |
| **BUG-07** | Deletable Posted Journal Entries | High | **RESOLVED** | `test_posted_journal_entries...` |
| **BUG-08** | Contra-Revenue Normal Balance | Medium | **RESOLVED** | `test_service_charge_delivery_fee...` |

All identified defects have been remediated, verified, and locked in with permanent regression tests.

