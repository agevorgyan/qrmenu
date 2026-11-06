# PHASE 0 — EXISTING SYSTEM AUDIT
## QR Menu SaaS: Team & Employees Module Audit & HRIS/Payroll Enhancement Readiness

---

### Executive Summary

This audit establishes the baseline of the existing **QR Menu SaaS** platform (`elab.menu`) specifically focusing on the **Team & Employees** (`team`) module, authentication, multi-tenancy, RBAC, subscription enforcement, and data models. The objective is to upgrade the existing Team & Employees module into a complete, enterprise-grade **Workforce Management & HR/Payroll System** without duplicating tables, without creating a secondary user/employee list, and without disrupting existing operations.

---

## 1. Platform Baseline & Technology Stack

| Component | Active Version / Implementation | Role in System |
| :--- | :--- | :--- |
| **Framework** | Laravel Framework `13.32.0` (PHP `8.5.10`) | Robust backend architecture, Eloquent ORM, Service Layer, FormRequests, Policies |
| **Database** | PostgreSQL `16` (102 core tables) | Strict foreign keys, decimal precision for financial numbers, JSONB configuration |
| **Multi-Tenancy** | Single-database multi-tenant (`TenantContext`, `TenantScope`, `BelongsToVendor`) | Strong tenant isolation rooted in `vendors` table (`vendor_id`) |
| **Authorization (RBAC)** | Fine-grained engine (`AuthorizationService`, `PermissionCatalog`, `Role`) | 21+ modules, role-to-permission mapping, custom overrides per membership |
| **Subscription & Quota** | Entitlement Engine (`EntitlementService`, `FeatureCatalog`, `QuotaCatalog`) | Plans: `basic`, `pro`, `advanced` (with legacy `business` alias); quota meters |
| **Frontend UI** | Laravel Blade + Tailwind CSS v4 + Alpine.js | Responsive admin layout, dynamic tabs, interactive modals, zero mock data |
| **Audit & Logging** | Immutable logs (`AuthorizationAuditLog`, `SecurityAuditService`) | Tamper-proof audit trails for critical operations |
| **File Storage** | `StorageService` (`VendorStorageFile`) | Tenant-isolated document and media storage |
| **Testing** | PHPUnit `12.5.35` (1,200+ automated tests) | Compact test runner, high regression coverage |

---

## 2. Deep Dive: Existing Backend Architecture

### 2.1. User & Staff Models (`App\Models\User`)
The platform already possesses a centralized `User` model representing all system actors:
- **Columns**: `id`, `name`, `email`, `password`, `vendor_id`, `location_id`, `role`, `phone`, `two_factor_enabled`, `custom_permissions`, `locale`, `display_name`, `short_description`, `employee_id`, `avatar_url`, `is_active`, `sort_order`, `assigned_table_ids`, `pin_code`, `pos_mode`.
- **Tenant Scope**: Directly tied to `vendor_id` for restaurant staff. A booted hook automatically synchronizes a `VendorMembership` upon creation.
- **Role Identification**: Helper methods `isVendorOwner()`, `isManager()`, `isStaff()`, `isWaiter()`, `isChef()`, `isCashier()`.
- **Security**: PIN code hashing (`pin_code` via `Hash::make`), Two-Factor Authentication (`two_factor_enabled`, `two_factor_secret`), sensitive attribute masking (`maskedEmail()`).

### 2.2. Membership & Access Control Architecture
- **`VendorMembership`**: Bridges `vendor_id` and `user_id`. Tracks `status` (`active`, `inactive`), `all_locations` (boolean), `joined_at`.
- **`MembershipRole` (`membership_roles`)**: Maps membership to multiple custom or system roles (`Role`).
- **`MembershipLocation` (`membership_locations`)**: Scopes an employee's access to one or more physical branches (`Location`).
- **`MembershipPermission` (`membership_permissions`)**: Stores explicit fine-grained permission grants or denials (overrides).
- **`AuthorizationService`**: Central evaluation engine combining System Roles, Custom Tenant Roles, Location Scoping, and Custom Overrides with multi-layer caching.
- **`PermissionCatalog`**: Definitive source of truth for all permissions categorized by module (`employees`, `orders`, `inventory`, `billing`, etc.) with risk levels and sensitivity flags.

### 2.3. Locations / Branches (`App\Models\Location`)
- Represents physical restaurant branches.
- Fields: `vendor_id`, `name`, `slug`, `address`, `phone`, `opening_hours`, `working_hours`, schedules for dine-in/delivery/takeaway.
- Multi-branch enforcement: Governed by `FeatureCatalog::BRANCHES_MULTIPLE` and `QuotaCatalog::KEY_BRANCHES_COUNT`.

### 2.4. Existing Controllers & Endpoints
- **`/admin/team` (`AccessControlController::index`)**:
  - Protected by middleware `['auth', 'verified', 'role:vendor_owner,manager,chef,staff', EnsureSubscriptionIsActive::class, EnsurePlanHasFeature:team]`.
  - Currently serves the Team & Access Control dashboard with tabs: Team Members, Roles & Templates, Permissions Matrix, Location Scopes, Access Audit Logs.
- **`/admin/team` POST (`VendorAdminController::storeTeamMember`)**:
  - Validates `name`, `email`, `role`, `location_id`, `password`.
  - Enforces `QuotaCatalog::KEY_TEAM_MEMBERS_COUNT` before creation.
  - Automatically provisions `User` and `VendorMembership`.

---

## 3. Database Audit: Schema & Data Structures

### 3.1. Existing Team & Identity Tables
1. `users`: Stores all tenant users and staff members.
2. `vendor_memberships`: Stores tenant membership records.
3. `membership_roles`: Pivot table linking memberships to roles.
4. `membership_locations`: Pivot table linking memberships to locations.
5. `membership_permissions`: Fine-grained permission overrides per membership.
6. `roles`: Tenant and system roles (`vendor_owner`, `manager`, `waiter`, `chef`, `cashier`, `staff`).
7. `permissions`: Catalog of 100+ fine-grained permissions.
8. `role_permissions`: Pivot linking roles to permissions.
9. `authorization_audit_logs`: Immutable security audit logs.

### 3.2. Existing Payments & Finance Tables
- `supplier_payments`: Tracks procurement payments (`vendor_id`, `supplier_id`, `supplier_invoice_id`, `payment_number`, `payment_date`, `amount`, `payment_method`, `reference_number`). Uses `decimal:2`.
- `pos_payments`: Handles POS retail orders payment transactions.
- `split_bill_payments`: Handles table bill split transactions.
- `subscription_payments`: Handles SaaS recurring subscription payments.
- **FINDING**: There is currently **no table for employee payroll runs, salary calculations, salary payouts, or employee advances**.

### 3.3. Existing Schedule & Shift Tables
- `pos_shifts`: Represents POS till/cash-drawer operational sessions (`opening_float`, `counted_cash`, `discrepancy`, `orders_count`).
- **FINDING**: This is **strictly a cash register session**, NOT an employee work roster schedule, timesheet, or biometric/QR attendance log.

---

## 4. Frontend & Navigation Audit

### 4.1. Navigation (`resources/views/layouts/partials/sidebar.blade.php`)
- The navigation menu has a section **"Business Management"** containing:
  - Branches (`locations.view` -> `route('admin.locations.index')`)
  - **Team & Employees** (`team.view` -> `route('admin.team.index')`)
  - Waiters Management (`waiters.view` -> `route('admin.waiters.index')`)
- The route name is `admin.team.index` and the title is already `Team & Employees` (`{{ __('Team & Employees') }}`).

### 4.2. Team View Components
- `resources/views/admin/access_control/index.blade.php`: Rich Alpine.js single-page application managing employee list, roles, permission matrix, location scopes, and audit logs.
- Modals: `inviteMemberModal`, `editRolesModal`, `editLocationsModal`, `customPermissionsModal`, `createRoleModal`.

---

## 5. SaaS Multi-Tenancy & Subscriptions Audit

### 5.1. Tenant Isolation
- Guarded by `BelongsToVendor` Eloquent trait and `TenantScope`.
- Automatically filters queries by `vendor_id`.
- Throws `InvalidArgumentException('Cross-tenant entity creation forbidden.')` if `vendor_id` does not match active tenant.
- Audit logs guarantee complete traceability of actor and tenant.

### 5.2. Subscription Plans & Quotas
- Database holds active tiers: `basic`, `pro`, `advanced` (and `business` alias).
- Feature flags managed via `FeatureCatalog`:
  - `FeatureCatalog::TEAM_MANAGE`: Basic has limited access, Pro has 5 staff, Advanced has 25 staff.
- Quotas managed via `QuotaCatalog`:
  - `KEY_TEAM_MEMBERS_COUNT`: Basic = 2, Pro = 5, Advanced = 25, Custom = unlimited.

---

## 6. Gap Analysis: What is Missing for HR & Payroll

| HR Capability | Current State in QR Menu | Action Required |
| :--- | :--- | :--- |
| **Employee Detailed Profile** | Basic User fields (name, email, phone, role) | Add `employee_profiles` (DOB, address, emergency contact, hire date, status, department, position, base wage) |
| **Departments & Positions** | Hardcoded roles only; no formal departments | Add `hr_departments` and `hr_positions` tables (tenant-scoped) |
| **Employment Contracts** | None | Add `employment_contracts` (types, wages, dates, files, status) |
| **Work Schedules & Rosters** | Only POS register shifts exist (`pos_shifts`) | Add `work_schedules` and `work_shifts` (roster planning, night shifts, cross-midnight) |
| **Attendance Tracking** | None | Add `attendance_records` (QR/PIN/Manager, check-in/out, breaks, late detection) |
| **Timesheets Management** | None | Add `timesheets` (aggregation of actual vs planned hours, overtime, approval lock) |
| **Leave & Absence** | None | Add `leave_types`, `leave_requests`, `employee_leave_balances` |
| **Payroll Calculation Engine**| None | Add `payroll_periods`, `payroll_items`, `payroll_item_components`, `hr_payroll_settings` |
| **Salary Payments & Advances**| Only POS and supplier payments exist | Add `salary_payments` and `employee_advances` (integrated with audit log) |
| **Payslips & Self-Service** | None | Add payslip generator & Employee Self-Service (ESS) portal restricted to own records |
| **HR Reports & Analytics** | Sales/menu reports only | Add HR dashboard metrics, attendance, turnover, and salary cost reports |

---

## 7. Non-Negotiable Reuse Strategy

1. **NO duplicate employee table**: The `User` model continues to be the primary entity for identity, login, credentials, and RBAC. The new `employee_profiles` table acts as a 1:1 extension of `User`.
2. **NO secondary RBAC**: The existing `AuthorizationService`, `PermissionCatalog`, `Role`, and `VendorMembership` will be extended with canonical HR permissions (`hr.employees.*`, `hr.contracts.*`, `hr.attendance.*`, `hr.payroll.*`, etc.).
3. **NO secondary Branch system**: Existing `Location` model will be directly linked to work schedules, shifts, attendance, and branch payroll cost centers.
4. **NO separate HR main navigation**: All HR features reside cohesively inside the existing **Team & Employees** (`/admin/team`) module.

