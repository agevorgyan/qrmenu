# PHASE 0 — TESTING & QA STRATEGY
## Automated Test Plan, Test Scenarios, Security Edge Cases & Regression Safeguards

---

### Executive Summary

To ensure rock-solid stability and zero regressions in the existing QR Menu platform, every HR & Payroll feature is backed by automated tests using PHPUnit. Tests are divided into Unit Tests (pure business logic and tax computation), Feature Tests (end-to-end HTTP lifecycle, database persistence, and authorization), and Security Tests (multi-tenant boundary violations and IDOR attempts).

---

## 1. Test Pyramid & Coverage Scope

```mermaid
pie title Automated Test Distribution for HR & Payroll
    "Unit Tests (Math & Tax Rules)" : 30
    "Feature Tests (Workflows & Lifecycle)" : 50
    "Security & Multi-Tenant Tests" : 20
```

---

## 2. Unit Testing Matrix

| Test Suite | Target Logic | Key Test Cases |
| :--- | :--- | :--- |
| `PayrollCalculationServiceTest` | Gross-to-Net Engine | - Fixed monthly salary calculation with full attendance<br>- Prorated salary for mid-month hire/termination<br>- Hourly wage computation based on regular hours<br>- Overtime rate multiplier (1.5x) verification<br>- Night shift premium (0.3x) between 22:00 and 06:00<br>- Holiday work premium (2.0x)<br>- Zero-float precision and rounding to 2 decimal places |
| `ArmenianTaxRulesTest` | RA Statutory Deductions | - Income tax exact 20% calculation<br>- Pension contribution 5% calculation under statutory cap<br>- Pension contribution capped at max ceiling (1,125,000 AMD gross)<br>- Military stamp duty tier brackets ($1,500, $3,000, $5,500, $8,500, $15,000 AMD)<br>- Effective-dated historical rate integrity |
| `TimesheetHoursAggregatorTest` | Tabel Consolidation | - Regular vs overtime hour splitting<br>- Midnight-crossing shift hour splitting into regular/night<br>- Paid leave hours vs unpaid absence categorization |
| `EmployeeAdvanceRecoveryTest` | Advance Deduction | - Full advance recovery when net salary exceeds advance<br>- Partial recovery when net salary is less than advance (capped at payable amount)<br>- Negative net salary prevention guarantee |

---

## 3. Feature Testing Matrix

| Test Suite | Target Workflow | Key Test Cases |
| :--- | :--- | :--- |
| `EmployeeManagementTest` | Profile & Contracts | - User creation with automatic `EmployeeProfile` provision<br>- Updating personal, banking, and emergency details<br>- Issuing sequential employment contracts<br>- Department and position assignment |
| `WorkScheduleAndShiftTest` | Rostering & Calendar | - Schedule template creation for branches<br>- Creating scheduled shifts with midnight crossing<br>- Detecting shift overlap for the same employee<br>- Shift cancellation and status updates |
| `AttendanceAndClockTest` | Time Clock & Punch | - Clock-in and clock-out via PIN verification<br>- Signed dynamic QR token verification and expiration<br>- Lateness calculation based on scheduled shift<br>- Manager manual entry and adjustments with reason log |
| `LeaveManagementTest` | Absence Workflows | - Leave request submission and balance decrement<br>- Manager approval and rejection with review notes<br>- Preventing leave requests exceeding remaining balance<br>- Overlapping leave detection with scheduled shifts |
| `PayrollLifecycleTest` | End-to-End Payroll | - Initializing period -> calculating -> reviewing breakdown<br>- Freezing & locking payroll period<br>- Preventing modification of locked payroll records<br>- Generating downloadable PDF payslip |
| `SalaryPaymentTest` | Disbursements | - Recording partial and full salary payout transactions<br>- Updating `payment_status` on payroll items<br>- Preventing overpayment beyond net salary |
| `EmployeeSelfServiceTest` | Staff Portal | - Staff viewing their own roster, attendance, and leave balance<br>- Staff submitting leave request<br>- Staff downloading their own payslips |

---

## 4. Security & Multi-Tenant Isolation Tests

| Test Suite | Security Domain | Verification Scenarios |
| :--- | :--- | :--- |
| `HrMultiTenantIsolationTest` | Tenant Boundary | - Tenant A cannot view, edit, or delete Tenant B's employees, profiles, contracts, shifts, attendance, or payroll<br>- Cross-tenant foreign key assignment throws `InvalidArgumentException`<br>- Direct ID guessing returns 404 (scoped by tenant) |
| `HrAuthorizationAndRbacTest` | Access Control | - Unauthenticated requests receive 401/302 to login<br>- Staff without `hr.payroll.view` receive 403 on payroll routes<br>- Manager without `hr.payroll.pay` receives 403 on payout endpoints<br>- Sensitive fields masked unless `hr.payroll.view_sensitive` is granted |
| `EmployeeSelfServiceIdorTest` | Horizontal Privilege | - Employee X cannot view Employee Y's payslip or contracts<br>- Employee X cannot submit leave or clock-in on behalf of Employee Y |

---

## 5. Regression Safeguards

1. Run existing test suite for:
   - `tests/Feature/EnterpriseAccessControlAdminIntegrationTest.php`
   - `tests/Feature/Security/ComprehensiveMultiTenantIsolationTest.php`
   - `tests/Feature/PosRoleWorkspacesTest.php`
   - `tests/Feature/CentralAuthorizationEngineTest.php`
2. Validate that existing `/admin/team` routes and team invitation endpoints (`VendorAdminController::storeTeamMember`) remain 100% functional.

