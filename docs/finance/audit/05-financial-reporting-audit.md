# 05 — Financial Reporting and Ledger Reconciliation Audit

**Audit Date**: November 7, 2026  
**Auditors**: Senior Accounting Software Auditor & Principal Financial Architect  

---

## 1. Single Source of Truth Mandate

Financial reporting systems fail when reports query separate operational tables (e.g. summing `orders.total_amount` for sales and `expense_payments.amount` for expenses). 

In this application, **all financial reports are generated exclusively from the General Ledger (`journal_entry_lines`)**:
- No operational table is ever queried to compute reported Revenue, COGS, OPEX, or Assets.
- Profit & Loss, Balance Sheet, Cash Flow, and Trial Balance share identical underlying ledger transactions.
- As proven in Phase 13 remediations, queries include both `'posted'` and `'reversed'` entries, ensuring reversals net out with mathematical perfection.

---

## 2. Profit and Loss Statement Recalculation

The P&L is structured according to IFRS Multi-Step Income Statement format:

$$\begin{aligned}
\text{Gross Revenue} &= \sum \text{Credits}(4010, 4020, 4030, 4100, \dots) \\
\text{Less: Sales Returns \& Discounts} &= \sum \text{Debits}(4900, 4910) \\
\mathbf{\text{Net Revenue}} &= \mathbf{\text{Gross Revenue} - \text{Contra Revenue}} \\
\text{Less: Cost of Goods Sold (COGS)} &= \sum \text{Debits}(5010 \dots 5090) \\
\mathbf{\text{Gross Profit}} &= \mathbf{\text{Net Revenue} - \text{COGS}} \\
\text{Less: Operating Expenses (OPEX)} &= \sum \text{Debits}(5100 \dots 5890) \\
\mathbf{\text{Operating Profit (EBIT)}} &= \mathbf{\text{Gross Profit} - \text{OPEX}} \\
\text{Less: Corporate Taxes} &= \sum \text{Debits}(5900 \dots 5990) \\
\mathbf{\text{Net Profit}} &= \mathbf{\text{Operating Profit} - \text{Taxes}}
\end{aligned}$$

### Contra-Revenue Handling:
- Code `4900` (Sales Returns & Allowances) and `4910` (Promotional Discounts Allowed) carry normal debit balances.
- In `FinancialReportingService::getProfitAndLoss()`, these accounts are separated into `$contraRevenueAccounts` and subtracted from `$grossRevenue` to arrive at `$netRevenue`.
- Remediated bug: Service Charge Revenue (`4030`) was removed from contra-revenue and recognized as gross operating revenue.

---

## 3. Balance Sheet Integrity: Assets = Liabilities + Equity

The Balance Sheet equation must hold at any given calendar date:

$$\text{Total Assets} \equiv \text{Total Liabilities} + \text{Total Equity} + \text{Current Period Earnings}$$

### Account Groupings:
1. **Assets (1000–1999)**:
   - Cash and Cash Equivalents (`1010`, `1020`, `1030`)
   - Accounts Receivable (`1200`)
   - Inventories (`1300`, `1310`)
   - Property, Plant & Equipment (`1500`, less accumulated depreciation `1510`)
2. **Liabilities (2000–2999)**:
   - Accounts Payable (`2010`)
   - Tips Payable (`2100`)
   - Accrued Payroll & Salaries Payable (`2210`)
   - VAT & Tax Liabilities (`2300`)
3. **Equity (3000–3999)**:
   - Share Capital (`3010`)
   - Retained Earnings (`3100`)
   - Current Period Net Income (Dynamically calculated as $\text{Total Revenue} - \text{Total Expenses}$)

### Cross-Statement Reconciliation:
- In `FinancialReportingService::getBalanceSheet()`:
  $$\text{Current Period Retained Earnings} = \sum \text{Net Revenue} - \sum \text{Total Expenses}$$
- This dynamic equity adjustment ensures that if the restaurant makes 500,000 AMD in Net Profit, the Balance Sheet equity increases by exactly 500,000 AMD, maintaining $\text{Assets} = \text{Liabilities} + \text{Equity}$.

---

## 4. Cash Flow Statement (Direct Method)

The Cash Flow Statement groups all cash movements into three standard categories:

$$\Delta \text{Cash} = \text{Operating Cash Flow} + \text{Investing Cash Flow} + \text{Financing Cash Flow}$$

1. **Operating Cash Flows**:
   - Cash inflows from customer orders
   - Cash outflows for food & packaging supplier purchases
   - Cash outflows for staff payroll disbursements
   - Cash outflows for operating utilities, rent, and maintenance
2. **Investing Cash Flows**:
   - Outflows for kitchen equipment, POS hardware, and restaurant renovation
3. **Financing Cash Flows**:
   - Inflows from owner capital contributions
   - Outflows for owner dividends or loan repayments

### Beginning to Ending Cash Reconciliation:
$$\text{Ending Cash as of Date } T = \text{Beginning Cash as of Date } (T_0 - 1) + \sum_{t=T_0}^T \Delta \text{Cash}_t$$

*Audit Verification*:
Ending Cash on the Cash Flow Statement perfectly equals `Total Cash & Cash Equivalents` reported on the Balance Sheet as of the same date.

---

## 5. Trial Balance Recalculation

The Trial Balance extracts every active account code in the Chart of Accounts, computes total cumulative debits and credits, and validates the master equation:

$$\sum \text{Account Debits} \equiv \sum \text{Account Credits}$$

In `FinancialReportingService::getTrialBalance()`:
```php
$isBalanced = abs($totalDebits - $totalCredits) < 0.01;
```
If this equation does not balance to within 0.01 AMD, the Trial Balance flags a critical out-of-balance alert.

---

## 6. Audit Conclusion

The financial reporting subsystem provides mathematically rigorous, fully reconciled financial statements. Cross-statement reconciliation (P&L Net Income -> Balance Sheet Equity -> Cash Flow Ending Cash) is flawless.

