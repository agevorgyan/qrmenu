# PHASE 0 — SECURITY, RBAC & AUTHORIZATION SPECIFICATION
## Fine-Grained Permissions, Role Matrices, Sensitive Data Protection & Audit Trails

---

### Executive Summary

Workforce management and payroll handle the most sensitive data in an enterprise: compensation figures, national identification numbers, bank account coordinates, attendance violations, and direct cash disbursements. This document specifies the comprehensive security controls, fine-grained RBAC catalog additions, and defense-in-depth access boundaries.

---

## 1. Canonical HR Permissions Catalog

All permissions are integrated into `App\Security\Permission` and registered in `App\Security\PermissionCatalog` under the module `'hr'`.

| Permission Key | Action | Risk Level | Sensitive | Description |
| :--- | :--- | :--- | :--- | :--- |
| `hr.departments.view` | `view` | `low` | `false` | View organizational departments |
| `hr.departments.manage` | `manage` | `medium` | `false` | Create, edit, and reorganize departments |
| `hr.positions.view` | `view` | `low` | `false` | View job positions and bands |
| `hr.positions.manage` | `manage` | `medium` | `false` | Create and edit job positions |
| `hr.contracts.view` | `view` | `medium` | `true` | View employee employment contracts |
| `hr.contracts.manage` | `manage` | `high` | `true` | Draft, sign, and update employment contracts |
| `hr.schedules.view` | `view` | `low` | `false` | View shift rosters and calendars |
| `hr.schedules.manage` | `manage` | `medium` | `false` | Create, assign, and alter employee work shifts |
| `hr.attendance.view` | `view` | `low` | `false` | View live time clock and attendance logs |
| `hr.attendance.manage` | `manage` | `medium` | `false` | Record manual attendance and approve punch adjustments |
| `hr.attendance.clock` | `clock` | `low` | `false` | Punch in/out at time clock station (QR/PIN) |
| `hr.timesheets.view` | `view` | `low` | `false` | View consolidated timesheets (Tabel) |
| `hr.timesheets.manage` | `manage` | `medium` | `false` | Edit and calculate timesheet hours |
| `hr.timesheets.approve` | `approve`| `high` | `true` | Sign off and lock timesheets for payroll |
| `hr.leave.view` | `view` | `low` | `false` | View leave calendar and absence requests |
| `hr.leave.request` | `request`| `low` | `false` | Submit a personal leave application |
| `hr.leave.approve` | `approve`| `medium` | `false` | Approve or reject leave requests |
| `hr.payroll.view` | `view` | `high` | `true` | View payroll summaries and line items |
| `hr.payroll.calculate` | `calc` | `high` | `true` | Execute gross-to-net salary calculations |
| `hr.payroll.approve` | `approve`| `critical`| `true` | Formally approve calculated payroll periods |
| `hr.payroll.lock` | `lock` | `critical`| `true` | Lock payroll period and freeze historical data |
| `hr.payroll.pay` | `pay` | `critical`| `true` | Disburse and record salary payments |
| `hr.payroll.view_sensitive`| `view_sens`| `critical`| `true` | View SSN, bank accounts, and base compensation |
| `hr.advances.view` | `view` | `medium` | `true` | View employee salary advances |
| `hr.advances.request` | `request`| `low` | `false` | Apply for a salary advance |
| `hr.advances.approve` | `approve`| `high` | `true` | Approve and authorize salary advance payouts |
| `hr.documents.view` | `view` | `medium` | `true` | View and download employee documents |
| `hr.documents.manage` | `manage` | `high` | `true` | Upload and delete personnel documents |
| `hr.reports.view` | `view` | `medium` | `false` | View HR KPI dashboard and analytics |
| `hr.reports.export` | `export` | `high` | `true` | Export payroll, attendance, and tax reports |

---

## 2. Default System Role Permissions Matrix

| System Role | Granted HR Permissions |
| :--- | :--- |
| **Vendor Owner** (`vendor_owner`) | **All HR Permissions** (Complete administrative, payroll, and payment authority) |
| **Manager** (`manager`) | `hr.departments.view`, `hr.positions.view`, `hr.schedules.view`, `hr.schedules.manage`, `hr.attendance.view`, `hr.attendance.manage`, `hr.attendance.clock`, `hr.timesheets.view`, `hr.timesheets.manage`, `hr.timesheets.approve`, `hr.leave.view`, `hr.leave.approve`, `hr.advances.view`, `hr.advances.approve`, `hr.reports.view`, `hr.documents.view` *(Excluded from: `hr.payroll.pay`, `hr.payroll.lock`)* |
| **Staff / Waiter / Cashier / Chef** | `hr.attendance.clock`, `hr.leave.request`, `hr.advances.request` *(Plus default Employee Self-Service access to own records)* |

---

## 3. Strict Security Controls

### 3.1. Two-Person Separation Rule (Segregation of Duties)
- A single manager cannot unilaterally calculate, approve, and disburse payroll without elevated authority.
- Approval (`hr.payroll.approve`) and Payment (`hr.payroll.pay`) can be separated so that floor managers verify hours while accountants or business owners disburse funds.

### 3.2. Protection of Sensitive Data (PII & Financials)
- **Masking**: Bank accounts and National ID/SSN numbers are masked in general views (`•••• 4589`).
- **Authorization**: Revealing raw financial data requires `hr.payroll.view_sensitive`.
- **Self-Service Isolation**: Employees navigating to their personal self-service portal can only query records where `user_id == Auth::id()`. Queries are scoped using Eloquent scopes to prevent horizontal privilege escalation (IDOR).

### 3.3. Document Security
- Files (contracts, medical proofs, identity cards) are stored in private disks via `StorageService`.
- Direct public URLs are prohibited. Files are only accessible via temporary signed download URLs after passing policy authorization.

### 3.4. Immutable Audit Trail
- All approvals, adjustments, role changes, and payments trigger records in `authorization_audit_logs`.

