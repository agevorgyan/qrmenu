# PHASE 1 & 2 — FINANCIAL FLOW DIAGRAMS & TRANSACTION CYCLES
## Republic of Armenia Standard Chart of Accounts (ՀՀ Հաշվային Պլան)

---

### 1. Order Sale & Settlement Flow (Accrual vs Cash)

```mermaid
flowchart TD
    subgraph OrderLifecycle["1. Order Lifecycle (Sales Dimension)"]
        O1["Customer Order Placed / Draft"] --> O2["Order Confirmed / Served"]
        O2 --> O3["Order Completed"]
    end

    subgraph LedgerSales["2. General Ledger Impact (Accrual)"]
        O3 -->|Generates JE-SALES| JE1["Journal Entry: Order Sale"]
        JE1 -->|DR| AR["2211 - Accounts Receivable: 12,000 AMD"]
        JE1 -->|CR| REV["6111 - Food Sales Revenue: 10,000 AMD"]
        JE1 -->|CR| TAX["5243 - VAT / Sales Tax Payable: 2,000 AMD"]
    end

    subgraph SettlementLifecycle["3. Settlement Dimension (Payment)"]
        P1["Customer Tenders Payment"] --> P2{"Payment Method"}
        P2 -->|Cash| P_CASH["Cash in Till: 12,000 AMD"]
        P2 -->|Card / Online| P_CARD["Card / Gateway Clearing: 12,000 AMD"]
    end

    subgraph LedgerSettlement["4. General Ledger Impact (Settlement)"]
        P_CASH -->|Generates JE-PMT| JE2["Journal Entry: Cash Settlement"]
        JE2 -->|DR| CASH_ACC["2511 - Cash in Till (Ազգային արժույթի դրամարկղ): 12,000 AMD"]
        JE2 -->|CR| AR
        
        P_CARD -->|Generates JE-PMT| JE3["Journal Entry: Card Settlement"]
        JE3 -->|DR| CARD_ACC["2551 - Card Acquirer Clearing (Միջոցներ ճանապարհին): 12,000 AMD"]
        JE3 -->|CR| AR
    end
```

---

### 2. Order Refund & Reversal Flow

```mermaid
flowchart TD
    R1["Customer Requests Refund / Order Voided"] --> R2["Manager Approves Refund"]
    R2 --> R3["Process Refund in RefundService"]
    
    R3 -->|Creates Reversal JE| RJ["Journal Entry: Order Refund Reversal"]
    RJ -->|Reference| ORIG["reversal_of_entry_id = JE-SALES-XXXX"]
    
    RJ -->|DR| RET["6121 - Sales Returns & Allowances (Contra-Rev): 10,000 AMD"]
    RJ -->|DR| TAX_REV["5243 - VAT / Sales Tax Payable: 2,000 AMD"]
    RJ -->|CR| PAY_REV{"Original Settlement Method"}
    
    PAY_REV -->|Cash Refund| CASH_OUT["2511 - Cash in Till: 12,000 AMD"]
    PAY_REV -->|Card Refund| CARD_OUT["2551 - Card Clearing: 12,000 AMD"]
```

---

### 3. Cash Shift Lifecycle & Till Reconciliation Flow

```mermaid
flowchart TD
    A["Cashier Logs In & Selects Cash Register"] --> B["Enter Opening Float: e.g. 25,000 AMD"]
    B --> C["Shift Status: OPEN"]
    
    C --> D1["Cash Sales (+ Cash In Drawer)"]
    C --> D2["Cash In: Float Top-up (+ Cash)"]
    C --> D3["Cash Refunds (- Cash Out of Drawer)"]
    C --> D4["Safe Drop / Payout (- Cash Out of Drawer)"]
    C --> D5["Register Transfers In/Out (+ / - Cash)"]
    
    D1 & D2 & D3 & D4 & D5 --> E["System Live Expected Cash Calculation"]
    
    E --> F["End of Shift: Cashier Initiates Shift Close"]
    F --> G["Blind Cash Count: Cashier Enters Counted Notes & Coins"]
    
    G --> H{"Variance Calculation"}
    H -->|Counted == Expected| I["Variance = 0.00: BALANCED"]
    H -->|Counted < Expected| J["Variance < 0.00: CASH SHORTAGE (DR 7145 / CR 2511)"]
    H -->|Counted > Expected| K["Variance > 0.00: CASH OVERAGE (DR 2511 / CR 6143)"]
    
    I & J & K --> L["Manager Review & Approval"]
    L --> M["Status: CLOSED. Shift Z-Report & Audit Locked"]
```

---

### 4. Supplier Procurement & Accounts Payable vs Cash Flow

```mermaid
flowchart TD
    subgraph AccrualStage["1. Invoice Receipt (Accrual Expense)"]
        SI1["Supplier Invoice Received: 50,000 AMD"] --> SI2["Approved by Kitchen / Manager"]
        SI2 -->|Generates JE-BILL| JE_INV["Journal Entry: Supplier Invoice"]
        JE_INV -->|DR| INV_ACC["2111 - Kitchen Raw Materials & Inventory: 50,000 AMD"]
        JE_INV -->|CR| AP_ACC["5211 - Accounts Payable (Trade Suppliers): 50,000 AMD"]
    end

    subgraph SettlementStage["2. Invoice Payment (Cash Settlement)"]
        SP1["Accounts Payable Payment Initiated: 50,000 AMD"] --> SP2{"Payment Source"}
        SP2 -->|Bank Transfer| SP_BANK["Bank Account"]
        SP2 -->|Cash from Register| SP_CASH["Cash Register Till"]
        
        SP_BANK & SP_CASH -->|Generates JE-DISB| JE_PMT["Journal Entry: AP Disbursement"]
        JE_PMT -->|DR| AP_ACC
        JE_PMT -->|CR| CR_SRC["2521 - Operating Bank OR 2511 - Cash in Till: 50,000 AMD"]
    end
```

---

### 5. HR Payroll Accrual vs Salary Disbursement Flow

```mermaid
flowchart TD
    subgraph PayrollAccrual["1. Payroll Period Approval (Labor Cost Accrual)"]
        PP1["Timesheets Finalized & Payroll Calculated"] --> PP2["Manager Approves & Locks Period"]
        PP2 -->|Generates JE-PAYROLL| JE_PR["Journal Entry: Labor Accrual"]
        JE_PR -->|DR| WAGE_EXP["7131 - Administrative Wages & Salaries Expense: 1,000,000 AMD"]
        JE_PR -->|CR| TAX_LIAB["5242 / 5251 - Income Tax & Pension Liabilities: 250,000 AMD"]
        JE_PR -->|CR| ADV_REC["2281 - Employee Advances Recovered: 50,000 AMD"]
        JE_PR -->|CR| NET_PAY["5271 - Net Salaries Payable: 700,000 AMD"]
    end

    subgraph PayrollDisbursement["2. Salary Payout (Cash / Bank Settlement)"]
        SP_INIT["Initiate Salary Payout: 700,000 AMD"] --> SP_DISB{"Disbursement Channel"}
        SP_DISB -->|Bank Wire| SP_WIRE["Corporate Bank Account"]
        SP_DISB -->|Cash Payout| SP_TILL["Cash Register Till"]
        
        SP_WIRE & SP_TILL -->|Generates JE-SALPAY| JE_SP["Journal Entry: Salary Payout"]
        JE_SP -->|DR| NET_PAY
        JE_SP -->|CR| CR_DISB["2521 - Operating Bank OR 2511 - Cash in Till: 700,000 AMD"]
    end
```

---

### 6. Bank Statement Reconciliation Flow

```mermaid
flowchart TD
    B1["Import Bank Statement (CSV / Feed)"] --> B2["Create Bank Transactions (Unreconciled)"]
    B2 --> B3["Automated Reconciliation Rule Engine"]
    
    B3 -->|Exact Amount & Reference Match| B4["Match with PosPayment / SupplierPayment / SalaryPayment"]
    B3 -->|Unmatched Transaction| B5["Manual Matching Interface"]
    
    B4 & B5 --> B6["Reconciliation Status: MATCHED"]
    B6 --> B7["Generate Monthly Bank Reconciliation Statement"]
    B7 --> B8["Ending Bank Statement Balance == GL Book Balance (2521) + In-Transit Items (2551)"]
    B8 --> B9["Sign-off & Lock Statement Period"]
```
