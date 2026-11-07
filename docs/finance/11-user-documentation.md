# QR Menu SaaS — Financial Operations User Manual

## Target Audience
This manual provides standard operating procedures for **Cashiers**, **Store/Branch Managers**, **Accountants**, and **Restaurant Owners / CFOs**.

---

## 1. Cashier Operational Guide

### 1.1 Opening a Shift
1. Navigate to **Finance & Cash** > **Cash Shifts** or open the POS terminal.
2. Click **Open Shift**.
3. Select your assigned **Cash Register**.
4. Count the physical banknotes and coins in the drawer and enter the **Opening Float** (e.g., 20,000 AMD).
5. Click **Open Shift**. The drawer balance is now locked into your active session.

### 1.2 Managing Cash Movements During Service
- **Cash In (Float Addition)**: If more change is added to the till during peak hours, select **Add Cash Movement** > Type: **Cash In**, specify amount and reason.
- **Cash Out (Emergency Petty Expense)**: If cash is taken from the till to pay for an emergency purchase (e.g., ice or lemons), select **Cash Out**, specify amount and attach vendor receipt note.
- **Safe Drop (Cash Skim)**: When the till exceeds maximum cash limits (e.g. 200,000 AMD), perform a **Cash Drop** to transfer excess cash to the branch vault.

### 1.3 Closing a Shift & Blind Counting
1. At the end of your work period, navigate to your active shift and click **Close Shift**.
2. **Blind Counting**: Count every banknote and coin in the till without looking at system sales totals. Enter the exact **Counted Cash** amount into the confirmation dialog.
3. Add any shift closing notes and click **Submit & Close Shift**.
4. The system calculates the variance:
   - **Zero Discrepancy**: Shift closes with a balanced status.
   - **Over / Short Variance**: If a discrepancy exists, it is highlighted in red (shortage) or blue (overage) and submitted for manager approval.

---

## 2. Branch Manager Operational Guide

### 2.1 Approving Shifts & Investigating Discrepancies
1. Navigate to **Finance & Cash** > **Cash Shifts**.
2. Filter by status: **Pending Approval** or **Closed with Discrepancy**.
3. Click on the shift to view the audit breakdown:
   - Opening Float
   - Cash Payments Received
   - Cash Refunds Deducted
   - Payouts & Drops
   - Counted Cash vs Expected Cash
4. Click **Approve Shift**. The variance is automatically posted to the general ledger under account `5800 (Cash Over/Short)`.

### 2.2 Approving Operating Expenses
1. Navigate to **Finance & Cash** > **Expenses**.
2. Filter by **Pending Approval**.
3. Inspect the expense title, supplier, category, attached invoice/receipt image, and amount.
4. Click **Approve**. Once approved:
   - The liability is recognized in the general ledger (Credit Accounts Payable 2010).
   - The expense is queued for disbursement.

### 2.3 Safe-to-Bank and Till-to-Safe Transfers
1. Navigate to **Finance & Cash** > **Cash Transfers**.
2. Click **New Transfer**.
3. Choose the source (e.g., *Main Cash Safe*) and destination (e.g., *Ameriabank Operating Account* or *Armenian Post Transit*).
4. Enter the amount and courier / transit reference.
5. Click **Execute Transfer**. The system executes an asset-to-asset journal entry with zero revenue/expense impact.

---

## 3. Accountant & Finance Director Guide

### 3.1 Managing Corporate Bank Accounts
1. Navigate to **Finance & Cash** > **Bank Accounts**.
2. View real-time bank ledger balances versus last reconciled statement balances.
3. To add a new account, click **Add Bank Account**, enter bank name, account number, IBAN, SWIFT, and select the linked Chart of Accounts code (default `1020`).

### 3.2 Importing Statements & Bank Reconciliation
1. Open the target Bank Account and click **Import Statement**.
2. Upload the CSV / bank export containing date, amount, credit/debit indicator, and counterparty reference.
3. The automated matching engine cross-references transaction references against:
   - Supplier Payments (`SupplierPayment`)
   - Expense Wire Disbursements (`ExpensePayment`)
   - POS Card Settlement Batches (`PosPayment`)
4. To finalize the monthly close, enter the **Bank Statement Ending Balance** and click **Reconcile Statement**. If the difference is `0.00 AMD`, the reconciliation status marks as **Completed**.

### 3.3 Operating the General Ledger
1. Navigate to **Finance & Cash** > **General Ledger**.
2. Review the structured **Chart of Accounts** (Assets, Liabilities, Equity, Revenue, Expenses).
3. To post a non-routine journal (e.g., depreciation, tax adjustment, capital injection):
   - Click **Post Journal Entry**.
   - Add balanced Debit and Credit lines ($\sum \text{Debit} = \sum \text{Credit}$).
   - Click **Post**. Unbalanced entries are blocked by database constraints.

### 3.4 Generating Financial Statements & Reports
Navigate to **Finance & Cash** > **Financial Reports**:

- **Profit & Loss (Income Statement)**:
  - Gross Sales Revenue
  - Less: Promotional Discounts & Returns
  - **Net Revenue**
  - Less: Cost of Goods Sold (Food & Beverage)
  - **Gross Profit & Gross Margin %**
  - Less: Operating Expenses (Wages, Utilities, Rent, Marketing)
  - **Operating Profit (EBITDA)**
  - Less: Taxes & Statutory Fees
  - **Net Profit & Net Margin %**
- **Balance Sheet (Statement of Financial Position)**:
  - Assets (Current Cash, Bank, Inventory, AR)
  - Liabilities (Accounts Payable, Taxes Payable, Tips Pool)
  - Equity (Owner Capital, Retained Earnings, Current Period Net Income)
  - Verifies: $\text{Assets} = \text{Liabilities} + \text{Equity}$.
- **Cash Flow Statement**:
  - Operating Cash Flows (Customer receipts, supplier payouts, payroll)
  - Investing Cash Flows (Kitchen equipment capex)
  - Financing Cash Flows (Capital injections, dividends)
  - Net Cash Change and Ending Cash on Hand.
- **Trial Balance**:
  - Full tabular breakdown of all GL accounts verifying debit/credit equilibrium.
- **Sales Analytics**:
  - Multi-branch breakdown, payment method distribution, order types, and trend analysis.

### 3.5 Security & Tamper Integrity Audit
1. Navigate to **Finance & Cash** > **Audit Trail**.
2. Review immutable log entries for every financial action (creator, timestamp, IP, old/new values).
3. Click **Verify Ledger Integrity**:
   - The engine audits thousands of ledger lines.
   - Computes debit/credit zero-balance identity.
   - Re-verifies all current account balances against cumulative posted lines.
   - Displays green **HEALTHY** status banner or flags compromised entries with drill-down links.

