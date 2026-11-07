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
\text{Gross Revenue} &= \sum \text{Credits}(6111, 6112, 6114, 6144, \dots) \\
\text{Less: Sales Returns \& Discounts} &= \sum \text{Debits}(6121, 6123) \\
\mathbf{\text{Net Revenue}} &= \mathbf{\text{Gross Revenue} - \text{Contra Revenue}} \\
\text{Less: Cost of Goods Sold (COGS)} &= \sum \text{Debits}(7111, 7112, 7114) \\
\mathbf{\text{Gross Profit}} &= \mathbf{\text{Net Revenue} - \text{COGS}} \\
\text{Less: Operating Expenses (OPEX)} &= \sum \text{Debits}(7121 \dots 7148) \\
\mathbf{\text{Operating Profit (EBIT)}} &= \mathbf{\text{Gross Profit} - \text{OPEX}} \\
\text{Less: Corporate Taxes} &= \sum \text{Debits}(7511, 7512) \\
\mathbf{\text{Net Profit}} &= \mathbf{\text{Operating Profit} - \text{Taxes}}
\end{aligned}$$

### Contra-Revenue Handling:
- Code `6121` (Sales Returns & Allowances) and `6123` (Promotional Discounts Allowed) carry normal debit balances.
- In `FinancialReportingService::getProfitAndLoss()`, these accounts are separated into `$contraRevenueAccounts` and subtracted from `$grossRevenue` to arrive at `$netRevenue`.
- Service Charge Revenue (`6114`) is recognized as gross operating revenue.

---

## 3. Balance Sheet Integrity: Assets = Liabilities + Equity

The Balance Sheet equation must hold at any given calendar date:

$$\text{Total Assets} \equiv \text{Total Liabilities} + \text{Total Equity} + \text{Current Period Earnings}$$

### Account Groupings (RA Standard):
1. **Assets (Դաս 1-2)**:
   - Cash and Cash Equivalents (`2511`, `2512`, `2521`, `2551`, `2552`)
   - Accounts Receivable (`2211`, `2281`)
   - Inventories (`2111`, `2114`, `2152`, `2161`)
   - Property, Plant & Equipment (`1114`, `1116`, less accumulated depreciation `1121`)
2. **Liabilities (Դաս 4-5)**:
   - Accounts Payable (`5211`, `5213`)
   - Tips Payable (`5311`)
   - Accrued Payroll & Salaries Payable (`5271`)
   - VAT & Tax Liabilities (`5241`, `5242`, `5243`, `5248`, `5249`, `5251`)
   - Long-Term Debt & Leases (`4111`, `4211`)
3. **Equity (Դաս 3)**:
   - Share Capital (`3111`)
   - Retained Earnings (`3411`)
   - Current Period Net Income (`3421` / Dynamically calculated as $\text{Total Revenue} - \text{Total Expenses}$)

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

