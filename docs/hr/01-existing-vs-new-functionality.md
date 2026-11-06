# PHASE 0 — EXISTING VS NEW FUNCTIONALITY
## Architectural Differentiation & Module Reuse Matrix

---

### Executive Overview

A critical requirement of this enhancement is to **build upon the existing QR Menu SaaS architecture** rather than creating disconnected parallel systems. This document provides the explicit functional and architectural matrix distinguishing existing capabilities from new HR & Payroll modules, proving zero duplication of data models, permissions, or administrative hierarchies.

---

## 1. Comprehensive Functional Mapping Matrix

| Functional Domain | Existing System Capability | New HR / Workforce Extension | Architectural Linkage & Reuse Strategy |
| :--- | :--- | :--- | :--- |
| **Employee Identity & Login** | `App\Models\User`<br>- `id`, `name`, `email`, `role`<br>- `vendor_id`, `location_id`<br>- `pin_code`, `two_factor_*`<br>- Auto-provisions `VendorMembership` | `App\Models\EmployeeProfile`<br>- 1:1 relation to `User`<br>- `dob`, `national_id_ssn`, `address`<br>- `emergency_contact_name/phone`<br>- `hired_at`, `terminated_at`<br>- `status` (`active`, `on_leave`, `suspended`, `terminated`)<br>- `employment_type` (`full_time`, `part_time`, etc.)<br>- `department_id`, `position_id`<br>- `manager_id` (hierarchy)<br>- `compensation_type`, `base_salary`, `hourly_rate`, `bank_account` | **Zero duplicate employees.** `User` remains the authenticatable subject. Every staff member is a `User`. Detailed employment info extends `User->profile` via `hasOne(EmployeeProfile::class)`. |
| **Tenant & Roles Management** | `VendorMembership`, `Role`, `MembershipRole`, `MembershipPermission`, `AuthorizationService` | No new RBAC engine.<br>Extend `PermissionCatalog` with 25+ canonical HR permissions (`hr.employees.*`, `hr.contracts.*`, etc.). | Reuses existing RBAC without changes to core engine. Custom roles can include any combination of HR permissions. |
| **Organizational Structure** | `Location` (Branches) | `HrDepartment` (Kitchen, Hall, Bar, Delivery, Admin, Accounting)<br>`HrPosition` (Chef, Cook, Waiter, Barista, Cashier, Manager) | Fully tenant-scoped (`vendor_id`). Departments can optionally link to Locations. Reuses existing `Location` for branch assignment. |
| **Contracts & Agreements** | None (only vendor terms / SaaS subscription) | `EmploymentContract`<br>- `contract_number`, `user_id`<br>- `start_date`, `end_date`<br>- `wage_amount`, `wage_type`<br>- `probation_end_date`<br>- `document_path`, `status`<br>- Expiration notifications | Links to `User`. Supports sequential historical contracts. Uses existing `StorageService` for secure document storage. |
| **Rosters & Shifts** | `PosShift` (Cash drawer session for POS register: float, cash in/out, register discrepancy) | `WorkSchedule` & `WorkShift`<br>- Work schedules for branches/departments<br>- Staff shift planning with start/end time<br>- Unpaid/paid break minutes<br>- Overnight/midnight crossing support<br>- Conflict detection & shift swapping | `PosShift` remains untouched for POS cashier operations. `WorkShift` handles human resource scheduling. |
| **Attendance & Time Clock** | None | `AttendanceRecord`<br>- Clock-in, Clock-out, Break start/end<br>- Verification: Signed QR, PIN, Manager, Manual<br>- Geo/location validation<br>- Lateness & early departure detection<br>- `AttendanceAdjustment` with audit trail | Reuses `User` and `Location`. Leverages `TimeAwareService` for vendor local time and midnight-crossing shifts. |
| **Timesheets (Tabel)** | None | `Timesheet`<br>- Automated aggregation of planned vs actual hours<br>- Regular, Overtime, Night, Holiday hours<br>- Manager verification & approval lock | Bridge between Attendance and Payroll. Once approved, timesheet is immutable and feeds directly into payroll runs. |
| **Leave & Absence** | None | `LeaveType`, `LeaveRequest`, `EmployeeLeaveBalance`<br>- Annual, Sick, Unpaid, Personal, Maternity<br>- Approval workflow with manager comments<br>- Balance tracking per calendar year | Integrated with Work Schedules and Timesheets so approved leaves automatically flag on schedules and payroll. |
| **Payroll Calculation Engine**| None | `PayrollPeriod`, `PayrollItem`, `PayrollItemComponent`<br>- Supports Monthly, Hourly, Daily, Shift, Mixed<br>- Automated tax engine: Armenia Tax Code + generic rule driver<br>- Income Tax, Social Security, Stamp Duty<br>- Overtime & night wage premiums | Separate, testable engine using `decimal:2` money representation. Detailed breakdown stored in JSONB per line item. |
| **Salary Payments & Advances**| `SupplierPayment` (procurement payments only) | `SalaryPayment`<br>- Multi-tranche, cash or bank transfer<br>- Linked to approved payroll items<br>`EmployeeAdvance`<br>- Salary advance request, disbursement, and automated payroll recovery | Follows the design patterns of `SupplierPayment`. Prevents double counting or duplicate accounting entries. |
| **Employee Self-Service (ESS)**| Basic profile page (`/admin/profile`) | Enhanced ESS dashboard tab for staff:<br>- My Shifts & Schedule<br>- My Attendance Clock & History<br>- My Leave Requests & Balances<br>- My Payslips (PDF download)<br>- My Advances | Filtered strictly to `Auth::id()`. Zero visibility into peer compensation or confidential records. |
| **HR Reports & Analytics** | Sales, orders, and POS reporting | HR Analytics suite:<br>- Headcount & Turnover<br>- Attendance, Lateness & Absence trends<br>- Overtime expenditure<br>- Salary costs by Branch, Department, Position<br>- Export to CSV/Excel/PDF | Follows existing reporting patterns (`PosReportingController`, `InventoryReportController`). |

---

## 2. Structural Schema Comparison

```mermaid
classDiagram
    class User {
        +BigInteger id
        +BigInteger vendor_id
        +BigInteger location_id
        +String name
        +String email
        +String role
        +String pin_code
    }
    
    class EmployeeProfile {
        +BigInteger id
        +BigInteger user_id
        +BigInteger vendor_id
        +BigInteger department_id
        +BigInteger position_id
        +BigInteger manager_id
        +String employment_status
        +String employment_type
        +Decimal base_salary
        +Decimal hourly_rate
        +Date hired_at
    }

    class Location {
        +BigInteger id
        +BigInteger vendor_id
        +String name
    }

    class HrDepartment {
        +BigInteger id
        +BigInteger vendor_id
        +String name
    }

    class HrPosition {
        +BigInteger id
        +BigInteger vendor_id
        +BigInteger department_id
        +String title
    }

    class EmploymentContract {
        +BigInteger id
        +BigInteger user_id
        +BigInteger vendor_id
        +String contract_number
        +Decimal wage_amount
        +Date start_date
        +Date end_date
    }

    class WorkShift {
        +BigInteger id
        +BigInteger user_id
        +BigInteger vendor_id
        +BigInteger location_id
        +Date shift_date
        +Time start_time
        +Time end_time
    }

    class AttendanceRecord {
        +BigInteger id
        +BigInteger user_id
        +BigInteger vendor_id
        +BigInteger location_id
        +DateTime check_in_at
        +DateTime check_out_at
        +String source
    }

    class Timesheet {
        +BigInteger id
        +BigInteger user_id
        +BigInteger vendor_id
        +Date period_start
        +Date period_end
        +Decimal regular_hours
        +Decimal overtime_hours
    }

    class PayrollPeriod {
        +BigInteger id
        +BigInteger vendor_id
        +String name
        +Date start_date
        +Date end_date
        +String status
    }

    class PayrollItem {
        +BigInteger id
        +BigInteger payroll_period_id
        +BigInteger user_id
        +Decimal gross_salary
        +Decimal net_salary
        +Decimal income_tax
    }

    User "1" --> "1" EmployeeProfile : has profile
    User "1" --> "0..*" EmploymentContract : holds
    User "1" --> "0..*" WorkShift : assigned to
    User "1" --> "0..*" AttendanceRecord : logs
    User "1" --> "0..*" Timesheet : summarizes
    User "1" --> "0..*" PayrollItem : receives
    HrDepartment "1" --> "0..*" HrPosition : categorizes
    EmployeeProfile --> HrDepartment : belongs to
    EmployeeProfile --> HrPosition : holds
    Location "1" --> "0..*" WorkShift : located at
    PayrollPeriod "1" --> "0..*" PayrollItem : contains
```

---

## 3. Strict Guarantees

1. **Authentication Unbroken**: All existing users log in with their existing credentials, email, password, and optional PIN.
2. **Backward Compatibility**: Routes `/admin/team` and `admin.team.index` remain active and functional, enhanced with new HR tabs.
3. **Multi-Tenant Protection**: Every single new table contains `vendor_id` and utilizes the `BelongsToVendor` trait.
4. **Subscription Tier Integrity**: HR features gracefully obey plan entitlements (`Basic`, `Pro`, `Advanced`).

