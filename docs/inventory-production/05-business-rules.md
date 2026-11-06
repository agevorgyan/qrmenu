# 05. Core Business Rules & Invariants

## 1. Multi-Tenant Isolation
- **Tenant Context**: All operational records belong strictly to one `Vendor` (`vendor_id`).
- **Isolation Enforcement**: Enforced globally at the Eloquent level via `TenantScope` and the `BelongsToVendor` model trait.
- **Cross-Tenant Safety**: Cross-tenant entity creation or modification attempts immediately throw an exception. Route model binding failures return 404/403.
- **Tenant Integrity**: No warehouse, recipe, stock transaction, supplier, or purchase order may reference an entity belonging to another vendor.

---

## 2. Inventory Ledger & Immutability
- **Double-Entry Principle**: Inventory transactions are immutable append-only records (`inventory_transactions` & `inventory_transaction_items`).
- **Ledger Invariant**: The total balance for an ingredient in a warehouse must always equal the sum of all past transaction line items in that warehouse.
- **Negative Stock Handling**: Configurable per vendor (`allow_negative_stock`). When disabled, stock deductions that would result in negative balances are aborted with an `InsufficientStockException`.
- **Row Locking**: Balance updates always execute within an atomic `DB::transaction()` using `lockForUpdate()` to prevent race conditions during concurrent orders or receipts.

---

## 3. Valuation & Costing Invariants
- **Weighted Average Cost (WAC)**:
  $$\text{WAC}_{\text{new}} = \frac{(\text{Current Qty} \times \text{Old WAC}) + (\text{Received Qty} \times \text{Unit Cost})}{\text{Current Qty} + \text{Received Qty}}$$
- **Stock Depletion Costing**: Stock deductions (issues, sales depletions) consume inventory at the current WAC.
- **Recipe Costing on Gross**: Raw material shrinkage (peeling, trimming, moisture loss) does not diminish the financial cost of purchasing the raw item. Therefore:
  $$\text{Item Cost} = \text{Gross Weight} \times \text{Base Unit Cost}$$
  Shrinkage percentages determine the net and yield weights, which govern portion sizing and nutritional density.

---

## 4. Recipe Directed Acyclic Graph (DAG) Invariant
- A recipe may contain semi-finished sub-recipes up to an arbitrary depth (e.g., Dish $\to$ Sauce $\to$ Stock $\to$ Base).
- **Cycle Detection**: Self-reference ($A \to A$) and cyclic references ($A \to B \to A$) are strictly prohibited. The system executes a depth-first search (DFS) cycle-detection algorithm before any recipe or item is saved.
- **Recursive Cost Roll-Up**: Whenever a sub-recipe is updated or re-costed, dependent parent recipes recalculate their cost roll-ups.

---

## 5. Procurement & Accounts Payable Rules
- **Three-Way Matching**:
  1. Purchase Order (Planned quantities and contract rates).
  2. Goods Receipt / Warehouse Ledger Intake (Actual received quantities and lot tracking).
  3. Supplier Invoice (Financial bill and payable liability).
- **Automated Replenishment**:
  $$\text{Suggested Order Qty} = \text{Max Stock} - (\text{Current Stock} + \text{On Order Qty})$$
  Triggered when $\text{Current Stock} \le \text{Reorder Point}$.
- **FIFO Payment Allocation**: Payments recorded against a supplier are sequentially allocated to the oldest outstanding approved invoices.

