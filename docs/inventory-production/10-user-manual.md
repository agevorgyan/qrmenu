# User Manual: Inventory, Costing, Production & HACCP System

## 1. Introduction
Welcome to the QR Menu Inventory, Procurement, Recipe Costing, Production, and Food Safety Management System. This manual guides restaurant managers, head chefs, and operational personnel through the daily workflows.

---

## 2. Role-Based Workflows

### 2.1. Head Chef & Recipe Costing
1. **Catalog Setup**: Define raw materials, ingredients, and semi-finished components in **Inventory -> Ingredients**.
2. **Technical Recipe Cards**:
   - Navigate to **Inventory -> Recipes & Technical Cards**.
   - Click **Create Recipe**.
   - Choose dish or semi-finished product.
   - Enter Bill of Materials (BOM) ingredients, gross/net weights, waste/shrinkage %, labor cost, and overhead %.
   - System automatically calculates **Cost per Portion**, **Suggested Selling Price**, and **Food Cost %**.
   - Click **Approve Recipe** to activate standard food costing and inventory deduction links.

### 2.2. Warehouse & Procurement Specialist
1. **Suppliers**: Manage vendor catalogs, supplier SKUs, and agreed price lists in **Inventory -> Suppliers**.
2. **Purchase Orders (POs)**:
   - Generate PO manually or use **Smart Reorder Suggestions** based on reorder points and safety stock.
   - Send PO to supplier via PDF / Email.
   - Upon physical delivery: Click **Receive Goods**, enter received quantities, and input supplier batch/lot numbers and expiration dates.
   - The double-entry inventory ledger immediately increments warehouse physical stock and calculates Weighted Average Cost (WAC).
3. **Accounts Payable**: Log supplier invoices and record partial or full payments in **Inventory -> Invoices & AP**.

### 2.3. Production & Kitchen Prep Staff
1. **Semi-Finished Prep**:
   - In **Inventory -> Production Orders**, create a production batch (e.g. 50 kg Pizza Dough or 100 Burger Patties).
   - Click **Start Production** to mark items in prep.
   - Click **Complete Production**: Enter actual output yield.
   - The system automatically issues all raw materials from the dry store and books the finished patties/dough into the kitchen prep walk-in with a newly stamped FEFO batch number.

### 2.4. Food Safety & HACCP Officer
1. **Critical Control Points (CCPs)**:
   - In **Inventory -> HACCP & Food Safety**, review CCP standards (chilled receiving, cooking core probe, walk-in coolers).
   - Use **Seed Standard CCPs** for instant international restaurant safety compliance.
2. **Digital Temperature Logs**:
   - Perform routine shift checks on coolers and cooking line.
   - If a temperature exceeds critical limits (e.g. walk-in cooler reads 7.5°C), the system prompts for mandatory corrective action.
3. **Traceability & Recalls**:
   - Enter or barcode-scan any batch lot number in **Traceability & Recall Audit**.
   - Instantly view the source PO, receiving timestamp, downstream kitchen production batches, and every customer table order that consumed dishes containing that batch.

### 2.5. General Manager: Analytics & Waste Control
1. **Waste Write-Offs**:
   - Record burnt, spoiled, or dropped items in **Inventory -> Waste & Loss**.
   - The system automatically credits warehouse inventory and updates loss attribution charts.
2. **Executive Reports & Intelligence**:
   - **Actual vs. Theoretical Food Cost**: Identify food cost overruns and variance % between theoretical BOM recipes and physical stock consumption.
   - **ABC Inventory Analysis**: Group raw materials into Class A (high value), Class B (moderate value), and Class C (bulk/low cost) for cycle counts.
   - **Turnover & Dead Stock**: Monitor inventory turns, Days Inventory Outstanding (DIO), and eliminate dead stock tying up cash.

