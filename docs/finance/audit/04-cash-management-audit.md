# 04 — Cash Management and POS Shift Audit

**Audit Date**: November 7, 2026  
**Auditors**: Restaurant POS Specialist & Senior Accounting Software Auditor  

---

## 1. Cash Lifecycle Mathematical Verification

The core invariant governing cash drawer balances during a cashier shift is:

$$\begin{aligned}
\text{Expected Cash} &= \text{Opening Float} \\
&\quad + \text{Cash Sales Payments} \\
&\quad + \text{Cash In (Floats / Replenishments)} \\
&\quad - \text{Cash Refunds} \\
&\quad - \text{Cash Payouts (Direct Petty Cash Expenses)} \\
&\quad - \text{Cash Drops (Skims to Main Safe)} \\
&\quad - \text{Cash Transfers Out} \\
&\quad + \text{Cash Transfers In}
\end{aligned}$$

### Verification Against Source Code (`CashShiftService::closeShift`):
```php
$expectedCash = $shift->opening_float
    + $cashPayments
    + $cashIn
    - $cashRefunds
    - $cashPayouts
    - $cashDrops
    - $cashTransfersOut
    + $cashTransfersIn;
```

**Non-Cash Exclusion Proof**:
- Card payments (POS card terminals) are tracked separately as `$cardPayments` and settle to `1030 Bank Clearing`.
- Online payments (QR menu / mobile wallets) are tracked separately as `$onlinePayments` and settle to payment processor accounts.
- Neither card nor online transactions are added to physical drawer cash. Physical cash balances strictly reflect currency notes and coins.

---

## 2. Blind Cash Counting & Fraud Prevention

To eliminate the temptation for cashiers to pocket surplus cash or manipulate shift records:
1. **Blind Counting**: During shift closure, the cashier is presented with a denomination counter (e.g. 50,000, 20,000, 10,000, 5,000, 1,000 AMD bills, coins).
2. The UI **never** discloses the system's `expected_cash` amount to the cashier prior to submission.
3. The cashier counts the physical cash and enters the counts.
4. The system calculates `actual_cash = sum(denomination * count)`.
5. The variance is computed:
   $$\text{Discrepancy} = \text{Actual Cash} - \text{Expected Cash}$$
   - If $\text{Discrepancy} < 0$: **Cash Shortage** (Cashier is under).
   - If $\text{Discrepancy} > 0$: **Cash Surplus** (Cashier is over).
   - If $\text{Discrepancy} = 0$: **Perfect Balance**.

---

## 3. Discrepancy Accounting & Manager Approval

When a discrepancy is discovered:
- If discrepancy exceeds the vendor's tolerance threshold (e.g. > 1,000 AMD), the shift requires explicit Manager Approval before it can be closed.
- The manager inspects the shift report and submits approval with notes.
- Upon closure, the discrepancy is automatically posted to the General Ledger:
  - **Shortage**:
    - Debit: `5810 Cash Shortage Expense`
    - Credit: `1010 Cash on Hand`
  - **Surplus**:
    - Debit: `1010 Cash on Hand`
    - Credit: `4810 Cash Over / Miscellaneous Income`

*Audit Verification*:
This ensures that the physical drawer balance in the GL is reconciled to the actual cash handed over to the safe, while discrepancies are surfaced on the P&L as operating expenses/income.

---

## 4. Multi-Register & Multi-Branch Support

Each physical branch can operate unlimited concurrent cash registers:
- Each register belongs to a specific `location_id` and has an assigned primary `account_id` (e.g. `1011 Main Bar Register`, `1012 Terrace Register`).
- Concurrent shifts run in parallel across registers without data bleeding.
- When cash is transferred between registers (e.g. Bar transfers 50,000 AMD to Terrace for change):
  ```
  Step 1 (Bar Cashier initiates Transfer Out):
     Debit: 1090 Cash in Transit   50,000
     Credit: 1011 Bar Register     50,000
     
  Step 2 (Terrace Cashier confirms Transfer In):
     Debit: 1012 Terrace Register  50,000
     Credit: 1090 Cash in Transit  50,000
  ```
- **P&L Impact**: Exactly 0.00 AMD.
- If the receiving register rejects the transfer, the funds are returned to the sender.

---

## 5. Post-Close Shift Immutability

Once a shift status changes to `'closed'`:
- All mutating endpoints (`cash-in`, `cash-out`, `cash-drop`, `record-payment`, `transfer`) reject requests for this shift with HTTP 422 (`Shift is already closed`).
- Retroactive additions or alterations are blocked.
- Any subsequent transactions must occur in a newly opened shift with a fresh opening float.

---

## 6. Audit Conclusion

The cash management lifecycle is robust, mathematically verifiable, and strictly enforces dual control (cashier blind count + manager signoff). Fraud vectors such as skimming, drawer pocketing, and unregistered payouts are completely mitigated.

