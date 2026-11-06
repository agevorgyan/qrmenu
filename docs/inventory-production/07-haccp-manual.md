# HACCP, Food Safety & Traceability Manual

## 1. Executive Summary & Principles
The QR Menu HACCP (Hazard Analysis and Critical Control Points) & Food Safety system delivers digital compliance, automated safety checks, and bidirectional food traceability. Integrated into the restaurant's operational flow, it ensures:
1. Systematic identification and monitoring of Critical Control Points (CCPs).
2. Immutable digital temperature logging with instant breach alerts.
3. FEFO (First-Expired, First-Out) shelf-life enforcement.
4. Digital shift opening, closing, and hygiene checklists.
5. End-to-end bidirectional recall simulation (Supplier PO $\rightarrow$ Batch $\rightarrow$ Production Order $\rightarrow$ Customer Orders).

---

## 2. Critical Control Points (CCPs)

### 2.1. Standard Seeded CCPs
Each tenant can auto-seed the standard restaurant safety CCPs:

| CCP Code | Name | Process Stage | Critical Range | Frequency | Corrective Action |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `CCP-1-RECEIVE-CHILLED` | Chilled Delivery Inspection | Receiving | $0^\circ\text{C} \le T \le 4^\circ\text{C}$ | Per Delivery | Reject goods if $> 5^\circ\text{C}$ |
| `CCP-1-RECEIVE-FROZEN` | Frozen Delivery Inspection | Receiving | $T \le -18^\circ\text{C}$ | Per Delivery | Reject goods if thawing or $> -15^\circ\text{C}$ |
| `CCP-2-STORAGE-FRIDGE` | Refrigerated Walk-in Cooler | Storage | $0^\circ\text{C} \le T \le 4^\circ\text{C}$ | Twice Daily | Move food to backup cooler, service motor |
| `CCP-2-STORAGE-FREEZER` | Deep Freezer Storage | Storage | $T \le -18^\circ\text{C}$ | Twice Daily | Inspect seals and defrost cycle if $> -15^\circ\text{C}$ |
| `CCP-3-COOKING-CORE` | Core Thermal Cooking | Preparation | $T \ge 75^\circ\text{C}$ | Per Batch | Keep cooking until core probe $\ge 75^\circ\text{C}$ for 30s |
| `CCP-4-HOT-HOLDING` | Hot Holding Buffet/Line | Service | $T \ge 63^\circ\text{C}$ | Every 2h | Reheat rapidly to $75^\circ\text{C}$ or discard if $>2\text{h}$ |
| `CCP-5-RAPID-COOLING` | Rapid Blast Chilling | Post-Cooking | $T \le 10^\circ\text{C}$ in $<2\text{h}$ | Per Batch | Ice bath or blast chiller. Discard if exceeded |

---

## 3. Digital Temperature Logging & Breach Management
When a staff member or manager logs a temperature via UI or API:
1. The measurement is validated against `min_value` and `max_value` of the CCP.
2. If $T < \text{min}$ or $T > \text{max}$, `is_breached` is flagged as `true`.
3. The system enforces mandatory entry of `corrective_action_taken`.
4. High-risk breaches trigger audit alerts and are highlighted on the administrative dashboard.

---

## 4. FEFO & Expiration Management
All incoming purchase orders and completed production orders generate an `InventoryBatch` with:
- `batch_number` (unique internal/supplier lot identifier)
- `production_date` & `expiry_date`
- `initial_quantity` & `remaining_quantity`
- `status`: `active`, `quarantined`, `expired`, `depleted`

### Threshold Categorization:
- **Expired**: $\text{expiry\_date} < \text{today}$ (Disposal mandatory).
- **Critical**: $\text{expiry\_date} \le \text{today} + 3\text{ days}$ (Immediate priority in prep).
- **Warning**: $\text{today} + 3\text{ days} < \text{expiry\_date} \le \text{today} + 7\text{ days}$.

---

## 5. Bidirectional Traceability & Recall Simulation
In the event of a supplier contamination notice or customer safety complaint:
1. **Upstream Traceability**:
   - Trace from batch number to the receiving Purchase Order, supplier contact, and delivery timestamp.
2. **Internal Processing Traceability**:
   - Find all semi-finished or finished production orders that consumed ingredients from that batch.
3. **Downstream Traceability**:
   - Query all customer orders created since the batch was received that contain menu items formulated with that batch's ingredient.
   - Outputs table number, customer name/phone, order status, and total sales.

