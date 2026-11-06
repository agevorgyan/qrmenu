# PHASE 0 — PAYROLL & COMPENSATION BUSINESS RULES
## Mathematical Formulas, Armenian Tax Legislation & Calculation Engine Rules

---

### Executive Summary

Payroll calculation is the highest-liability component of workforce management. This document defines the exact mathematical models, rounding standards, labor code multipliers, and statutory tax brackets applied by the QR Menu payroll engine.

> [!IMPORTANT]
> **Legal Disclaimer**: The system provides automated calculation assistance based on user-configured rules and standard parameters (such as the RA Tax Code and Labor Code). The software does not provide certified legal, financial, or tax advice; tax parameters are fully configurable by authorized tenant administrators and accountants.

---

## 1. Zero-Float Precision Standard

1. **Rule**: Under no circumstances shall floating point numbers (`float`, `double`) be used for payroll computations or monetary storage.
2. **Implementation**:
   - In PostgreSQL: `DECIMAL(14, 2)` or `DECIMAL(12, 2)`.
   - In PHP: Rounding is applied explicitly using `round($amount, 2)` or integer math.
   - Currency display in Armenia is in Armenian Drams (AMD) rounded to the nearest integer DRAM for payouts, or 2 decimal places for itemized component tracking.

---

## 2. Compensation Types & Gross Earnings

### 2.1. Fixed Monthly Salary (`monthly`)
$$\text{Daily Rate} = \frac{\text{Base Salary}}{\text{Standard Working Days in Period}}$$
$$\text{Base Earnings} = \text{Daily Rate} \times \text{Actual Worked Days}$$

*If employee worked the entire standard period, $\text{Base Earnings} = \text{Base Salary}$.*

### 2.2. Hourly Rate (`hourly`)
$$\text{Base Earnings} = \text{Hourly Rate} \times \text{Regular Worked Hours}$$

### 2.3. Per Shift Rate (`shift`)
$$\text{Base Earnings} = \text{Shift Rate} \times \text{Completed Shifts Count}$$

### 2.4. Overtime Calculation (RA Labor Code Art. 184)
- **Threshold**: Hours worked beyond scheduled shift or daily/weekly legal limit (standard 8h/day or 40h/week).
- **Multiplier**: Minimum **1.5x** hourly rate.
$$\text{Overtime Hourly Rate} = \text{Hourly Rate} \times 1.5$$
$$\text{Overtime Pay} = \text{Overtime Hours} \times \text{Overtime Hourly Rate}$$

### 2.5. Night Shift Premium (RA Labor Code Art. 184)
- **Time Window**: Hours worked between **22:00 and 06:00**.
- **Multiplier**: Minimum extra **+0.3x** hourly rate (30% additional premium).
$$\text{Night Premium Rate} = \text{Hourly Rate} \times 0.3$$
$$\text{Night Pay} = \text{Night Hours} \times \text{Night Premium Rate}$$

### 2.6. Public Holiday / Rest Day Premium (RA Labor Code Art. 185)
- **Multiplier**: Double rate (**2.0x** hourly/daily rate).
$$\text{Holiday Pay} = \text{Holiday Hours} \times (\text{Hourly Rate} \times 2.0)$$

### 2.7. Total Gross Salary (Համախառն աշխատավարձ)
$$\text{Gross} = \text{Base Earnings} + \text{Overtime Pay} + \text{Night Pay} + \text{Holiday Pay} + \text{Bonuses} + \text{Allowances}$$

---

## 3. Statutory Taxes & Deductions (Armenia Standard Rules)

The system ships with default configuration reflecting Republic of Armenia statutory payroll rules, versioned via `hr_payroll_settings`.

### 3.1. Income Tax (Եկամտային հարկ)
- Flat rate: **20%** of Gross Salary.
$$\text{Income Tax} = \text{Gross Salary} \times 0.20$$

### 3.2. Mandatory Funded Pension Contribution (Կուտակային կենսաթոշակային վճար)
Applicable to employees born on or after January 1, 1974.
- Standard contribution rate: **5%**.
- Maximum statutory monthly salary cap: $15 \times \text{Minimum Wage} = 15 \times 75,000 = 1,125,000 \text{ AMD}$.
- Formula:
  $$\text{Subject Gross} = \min(\text{Gross Salary}, 1,125,000 \text{ AMD})$$
  $$\text{Pension Contribution} = \text{Subject Gross} \times 0.05$$
  *(Maximum monthly pension deduction: 56,250 AMD)*

### 3.3. Military Insurance Stamp Duty (Դրոշմանիշային վճար)
Tiered fixed deduction based on Gross Salary bracket:

| Gross Salary Range (AMD) | Monthly Stamp Fee (AMD) |
| :--- | :--- |
| Up to 100,000 AMD | 1,500 AMD |
| 100,001 to 200,000 AMD | 3,000 AMD |
| 200,001 to 500,000 AMD | 5,500 AMD |
| 500,001 to 1,000,000 AMD | 8,500 AMD |
| Above 1,000,000 AMD | 15,000 AMD |

### 3.4. Net Salary (Մաքուր աշխատավարձ)
$$\text{Statutory Deductions} = \text{Income Tax} + \text{Pension} + \text{Stamp Fee}$$
$$\text{Net Before Advances} = \text{Gross} - \text{Statutory Deductions} - \text{Other Penalties/Deductions}$$
$$\text{Advance Deduction} = \min(\text{Outstanding Advance Balance}, \text{Net Before Advances})$$
$$\text{Net Payable Salary} = \text{Net Before Advances} - \text{Advance Deduction}$$

---

## 4. Edge Cases & Protection Rules

1. **Mid-Month Joiners & Leavers**:
   - The engine automatically calculates the exact working days between `hired_at` (or `terminated_at`) and the period bounds.
2. **Negative Net Salary Prevention**:
   - Deductions and advance recoveries can never reduce `Net Payable Salary` below zero. Any excess unrecovered advance rolls over to the next pay cycle.
3. **Mid-Night Shift Splitting**:
   - A shift spanning 20:00 to 04:00 will attribute 2 hours of regular evening pay (20:00 - 22:00) and 6 hours of night premium pay (22:00 - 04:00).
4. **Locked Period Immutability**:
   - Once a `PayrollPeriod` is transitioned to `locked`, no records in `payroll_items`, `payroll_item_components`, or related `timesheets` can be updated or deleted. Any adjustments require an explicit adjustment record in a subsequent period.

