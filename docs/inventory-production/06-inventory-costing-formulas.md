# Inventory, Recipe Costing & Shrinkage Mathematical Formulas

## 1. Physical Units & Dimensions

The system supports strict physical unit conversions across physical dimensions:
- **Mass**: $\text{kg}$, $\text{g}$, $\text{mg}$, $\text{ton}$
- **Volume**: $\text{l}$, $\text{ml}$
- **Pieces & Packaging**: $\text{piece}$, $\text{portion}$, $\text{pack}$, $\text{box}$, $\text{bottle}$

### Mass-to-Volume Conversion (Density based)
Where an item is specified in volume (e.g., liters) but stocked in mass (e.g., kg), or vice-versa:
$$\text{Mass (kg)} = \text{Volume (L)} \times \rho$$
Where $\rho$ is the item density in $\text{kg/L}$. If $\rho$ is uncalibrated, cross-dimension conversion is blocked to prevent financial errors.

---

## 2. Recipe Shrinkage & Loss Calculations

Each recipe component tracks raw gross weight, cold preparation/trimming loss %, net weight, cooking/thermal loss %, and final yield output weight.

### 2.1 Cold Preparation Shrinkage (Սառը մշակման / մաքրման կորուստ)
Given raw gross weight $G$ (Բրուտտո) and prep loss percentage $L_{\text{prep}}$ (%):
$$\text{Net Weight } (N) = G \times \left(1 - \frac{L_{\text{prep}}}{100}\right)$$

Conversely, if required net weight $N$ is known:
$$\text{Gross Weight } (G) = \frac{N}{1 - \frac{L_{\text{prep}}}{100}}$$

### 2.2 Cooking / Thermal Loss (Ջերմային մշակման կորուստ)
Given net weight $N$ (Նետտո) and cooking loss percentage $L_{\text{cook}}$ (%):
$$\text{Final Output Weight } (F) = N \times \left(1 - \frac{L_{\text{cook}}}{100}\right)$$

Total shrinkage loss from raw material to plate:
$$\text{Total Loss \%} = \left(1 - \frac{F}{G}\right) \times 100$$

---

## 3. Recipe Costing (Ինքնարժեքի Հաշվարկ)

### 3.1 Raw Material Cost Allocation
**Essential Culinary Rule:** The kitchen pays for the **Gross Weight** $G$ of raw materials, not the cleaned Net weight or finished weight, because trimmed skins, bones, and fat were purchased:
$$\text{Component Cost } (C_i) = G_{i, \text{primary}} \times P_{i}$$
Where:
- $G_{i, \text{primary}}$ is the gross quantity converted to the ingredient's primary inventory unit via `UnitConversionService`.
- $P_i$ is the unit valuation price of the ingredient (Weighted Average Cost $WAC$ or Last Purchase Price $LPP$).

### 3.2 Recursive Multi-Level BOM Costing (Nested Sub-Recipes)
For sub-recipes (sauces, dressings, stocks, doughs):
$$\text{Sub-Recipe Unit Cost } (P_{\text{sub}}) = \frac{\text{Total Cost of Sub-Recipe}}{\text{Sub-Recipe Yield Quantity}}$$

The system evaluates the Directed Acyclic Graph (DAG) recursively. Cycles ($A \to B \to A$) are detected and blocked with an `InvalidArgumentException`.

### 3.3 Total Recipe Batch Cost
$$\text{Total Batch Cost } (C_{\text{total}}) = \sum_{i=1}^{n} C_{\text{raw}, i} + \sum_{j=1}^{m} C_{\text{pkg}, j} + C_{\text{labor}} + C_{\text{overhead}}$$

### 3.4 Portion Cost (Cost of Goods Sold per Portion)
$$\text{Portion Cost } (C_{\text{portion}}) = \frac{C_{\text{total}}}{Y}$$
Where $Y$ is the recipe yield quantity in portions.

---

## 4. Profitability & Food Cost Metrics

Given selling price $S$ (from menu product or recipe catalog):

### 4.1 Food Cost Percentage
$$\text{Food Cost \%} = \left(\frac{C_{\text{portion}}}{S}\right) \times 100$$
Standard industry benchmarks:
- **Optimal (Green)**: $\le 30.0\%$
- **Acceptable (Yellow)**: $30.1\% - 35.0\%$
- **Critical (Red)**: $> 35.0\%$

### 4.2 Gross Profit per Portion
$$\text{Gross Profit} = S - C_{\text{portion}}$$

### 4.3 Profit Margin Percentage
$$\text{Profit Margin \%} = \left(\frac{\text{Gross Profit}}{S}\right) \times 100$$

---

## 5. Inventory Weighted Average Costing (WAC / Միջին Կշռված Ինքնարժեք)

When receiving a new batch $k$ with quantity $Q_{\text{new}}$ and unit cost $P_{\text{new}}$ into existing stock with quantity $Q_{\text{old}}$ and current cost $P_{\text{old}}$:
$$WAC_{\text{new}} = \frac{(Q_{\text{old}} \times P_{\text{old}}) + (Q_{\text{new}} \times P_{\text{new}})}{Q_{\text{old}} + Q_{\text{new}}}$$

When stock is issued, deducted, or wasted:
- The issued units are expensed at the current $WAC$.
- The $WAC$ remains unchanged until the next receipt batch arrives.

