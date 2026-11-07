# PHASE 1 & 2 — FINANCIAL API & CONTROLLER SPECIFICATION
## Cash Management & Financial Operations Module

---

### 1. Endpoint Architecture Overview

All financial endpoints require authentication (`auth`), tenant verification (`TenantContext`), and fine-grained permission authorization (`can:finance.*`). They are scoped under `/admin/finance`.

```
/admin/finance
├── /dashboard                    [GET]  Summary KPIs, revenue vs expense, cash liquidity
├── /registers                    [GET, POST] Cash register management
│   └── /{register}/toggle        [POST] Activate/deactivate register
├── /shifts                       [GET] List of all shifts across registers
│   ├── /open                     [POST] Open new cash shift with float
│   └── /{shift}                  [GET] Detailed shift review & breakdown
│       ├── /movement             [POST] Record cash-in, cash-out, safe drop
│       ├── /close                [POST] Submit blind count & close shift
│       ├── /approve              [POST] Manager review & variance sign-off
│       └── /z-report             [GET] Print/export fiscal Z-report
├── /expenses                     [GET, POST] Operating expenses list & creation
│   └── /{expense}                [GET, PUT, DELETE] Expense details & updates
│       ├── /approve              [POST] Approve pending expense
│       ├── /reject               [POST] Reject expense with reason
│       └── /payments             [POST] Record partial or full payment
├── /incomes                      [GET, POST, PUT, DELETE] Non-menu revenue items
├── /transfers                    [GET, POST] Inter-account/safe asset transfers
│   └── /{transfer}/approve       [POST] Confirm receipt of transferred funds
├── /bank-accounts                [GET, POST] Manage bank accounts
│   └── /{bankAccount}            [GET] Bank account details & transactions
│       ├── /transactions         [POST] Manual entry or CSV statement import
│       └── /reconcile            [POST] Finalize monthly bank reconciliation
├── /bank-transactions/{id}/match [POST] Match bank line to system payment/disbursement
├── /accounts                     [GET, POST] Chart of Accounts catalog
├── /ledger                       [GET, POST] General Ledger journal entries
│   └── /{entry}/reverse          [POST] Post reversal journal entry
├── /periods                      [GET, POST] Accounting periods
│   └── /{period}/close           [POST] Lock accounting period
├── /reports
│   ├── /profit-loss              [GET] P&L Income statement
│   ├── /cash-flow                [GET] Cash Flow statement (Operating/Invest/Fin)
│   ├── /balance-sheet            [GET] Balance Sheet (Assets = Liab + Equity)
│   ├── /trial-balance            [GET] Trial Balance of all active accounts
│   ├── /sales                    [GET] Multi-dimension Sales Analytics
│   └── /export/{type}            [GET] Excel / CSV financial export
├── /approvals                    [GET] Central financial approval queue
│   └── /{request}/decide         [POST] Approve or reject request
├── /audit-trail                  [GET] Immutable financial audit logs
└── /settings                     [GET, POST] Approval thresholds & COA defaults
```

---

## 2. Request & Response Payload Examples

### 2.1. Open Cash Shift
**`POST /admin/finance/shifts/open`**
```json
{
  "cash_register_id": 1,
  "opening_float": 25000.00,
  "opening_notes": "Morning shift opening till. 5x5000 AMD notes verified."
}
```
**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Cash shift opened successfully.",
  "data": {
    "id": 142,
    "uuid": "4c677743-5afe-4905-a216-d9d47ab6ef61",
    "register_name": "Main Register 1",
    "opened_at": "2026-11-07T09:00:00Z",
    "opening_float": "25000.00",
    "expected_cash": "25000.00",
    "status": "open"
  }
}
```

### 2.2. Close Cash Shift (Blind Count)
**`POST /admin/finance/shifts/{shift}/close`**
```json
{
  "counted_cash": 182500.00,
  "closing_notes": "Evening closing. End of day reconciliation."
}
```
**Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Shift closed successfully.",
  "data": {
    "shift_id": 142,
    "expected_cash": 182500.00,
    "counted_cash": 182500.00,
    "discrepancy": 0.00,
    "variance_status": "balanced",
    "total_cash_sales": 160000.00,
    "total_card_sales": 95000.00,
    "status": "closed"
  }
}
```

### 2.3. Create Expense
**`POST /admin/finance/expenses`**
```json
{
  "category_id": 4,
  "account_id": 26,
  "title": "Kitchen Grease Trap Cleaning",
  "amount": 45000.00,
  "tax_amount": 0.00,
  "expense_date": "2026-11-07",
  "due_date": "2026-11-14",
  "location_id": 1,
  "supplier_id": 3,
  "payment_method": "cash",
  "cash_register_id": 1,
  "notes": "Emergency maintenance for branch 1."
}
```
**Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Expense created successfully.",
  "data": {
    "id": 88,
    "expense_number": "EXP-2026-00088",
    "amount": "45000.00",
    "status": "approved",
    "paid_amount": "0.00",
    "remaining_amount": "45000.00"
  }
}
```

