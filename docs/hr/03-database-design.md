# PHASE 0 — DATABASE DESIGN SPECIFICATION
## PostgreSQL 16 Schema Blueprint, Constraints, Decimal Precision & Indexing

---

### Executive Summary

This document specifies the complete relational schema design for the Workforce, HR & Payroll module in the QR Menu SaaS platform. Every table is tenant-scoped, enforces strict foreign key referential integrity with cascading rules, uses exact fixed-point `DECIMAL(14, 2)` precision for all financial and currency columns, and defines optimal B-Tree indexes for tenant-isolated high-concurrency lookups.

---

## 1. Schema Definitions & Table Specifications

### 1.1. `hr_departments`
Represents organizational functional departments (Kitchen, Hall/Dining, Bar, Delivery, Management, Accounting).
- **Justification**: The audit revealed only hardcoded user roles. Restaurants need customizable departments to organize rosters, calculate departmental labor costs, and structure managerial responsibilities.

```sql
CREATE TABLE hr_departments (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    manager_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_hr_departments_vendor_name UNIQUE (vendor_id, name)
);

CREATE INDEX idx_hr_departments_vendor_active ON hr_departments(vendor_id, is_active);
```

### 1.2. `hr_positions`
Represents specific job titles and positions (Head Chef, Line Cook, Waiter, Barista, Cashier, General Manager).
- **Justification**: A job title is distinct from system permissions. A "Waiter" or "Head Waiter" might share the same POS permission role, but have different salary bands, job descriptions, and supervisory levels.

```sql
CREATE TABLE hr_positions (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    department_id BIGINT NULL REFERENCES hr_departments(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    default_pay_type VARCHAR(50) NOT NULL DEFAULT 'monthly', -- 'monthly', 'hourly', 'daily', 'shift'
    default_pay_rate DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_hr_positions_vendor_title UNIQUE (vendor_id, title)
);

CREATE INDEX idx_hr_positions_vendor_dept ON hr_positions(vendor_id, department_id);
```

### 1.3. `employee_profiles`
1:1 extension of the existing `users` table for comprehensive HR personnel records.
- **Justification**: Protects the core `users` table from becoming cluttered with 30+ HR-specific fields while avoiding duplicate employee lists.

```sql
CREATE TABLE employee_profiles (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    department_id BIGINT NULL REFERENCES hr_departments(id) ON DELETE SET NULL,
    position_id BIGINT NULL REFERENCES hr_positions(id) ON DELETE SET NULL,
    manager_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    
    -- Personal Information
    national_id_ssn VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    gender VARCHAR(20) NULL,
    address TEXT NULL,
    emergency_contact_name VARCHAR(255) NULL,
    emergency_contact_phone VARCHAR(50) NULL,
    emergency_contact_relation VARCHAR(100) NULL,
    
    -- Employment Details
    internal_employee_code VARCHAR(100) NULL,
    employment_status VARCHAR(50) NOT NULL DEFAULT 'active', -- 'active', 'on_leave', 'suspended', 'terminated'
    employment_type VARCHAR(50) NOT NULL DEFAULT 'full_time', -- 'full_time', 'part_time', 'contract', 'seasonal', 'temporary'
    hired_at DATE NOT NULL DEFAULT CURRENT_DATE,
    probation_ends_at DATE NULL,
    terminated_at DATE NULL,
    termination_reason TEXT NULL,
    
    -- Compensation & Banking Details
    compensation_type VARCHAR(50) NOT NULL DEFAULT 'monthly', -- 'monthly', 'hourly', 'daily', 'shift', 'mixed'
    base_salary DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    hourly_rate DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    bank_name VARCHAR(255) NULL,
    bank_account_number VARCHAR(100) NULL,
    
    -- Metadata
    notes TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_employee_profiles_vendor_status ON employee_profiles(vendor_id, employment_status);
CREATE INDEX idx_employee_profiles_dept_pos ON employee_profiles(department_id, position_id);
CREATE INDEX idx_employee_profiles_manager ON employee_profiles(manager_id);
```

### 1.4. `employee_position_histories`
Audit log of position, department, and branch transfers.
- **Justification**: Labor compliance requires recording historical job title and station changes without losing historical records.

```sql
CREATE TABLE employee_position_histories (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    department_id BIGINT NULL REFERENCES hr_departments(id) ON DELETE SET NULL,
    position_id BIGINT NULL REFERENCES hr_positions(id) ON DELETE SET NULL,
    location_id BIGINT NULL REFERENCES locations(id) ON DELETE SET NULL,
    effective_from DATE NOT NULL,
    effective_until DATE NULL,
    reason TEXT NULL,
    changed_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_emp_pos_hist_user ON employee_position_histories(user_id, effective_from);
```

### 1.5. `employee_salary_histories`
Audit log of wage revisions and compensation restructuring.
- **Justification**: Wage changes must not retroactively corrupt historical payroll calculations.

```sql
CREATE TABLE employee_salary_histories (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    compensation_type VARCHAR(50) NOT NULL,
    base_salary DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    hourly_rate DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    effective_from DATE NOT NULL,
    effective_until DATE NULL,
    reason TEXT NULL,
    approved_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_emp_salary_hist_user ON employee_salary_histories(user_id, effective_from);
```

### 1.6. `employment_contracts`
Tracks official labor agreements, probation terms, and contract documents.
- **Justification**: Tracks fixed-term contract expirations and regulatory documentation.

```sql
CREATE TABLE employment_contracts (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    contract_number VARCHAR(100) NOT NULL,
    contract_type VARCHAR(50) NOT NULL DEFAULT 'indefinite', -- 'indefinite', 'fixed_term', 'service', 'internship'
    start_date DATE NOT NULL,
    end_date DATE NULL,
    probation_end_date DATE NULL,
    wage_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    wage_type VARCHAR(50) NOT NULL DEFAULT 'monthly',
    working_hours_per_week INT NOT NULL DEFAULT 40,
    file_path TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- 'draft', 'active', 'expiring', 'expired', 'terminated'
    notes TEXT NULL,
    created_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_contracts_vendor_number UNIQUE (vendor_id, contract_number)
);

CREATE INDEX idx_contracts_vendor_status ON employment_contracts(vendor_id, status);
CREATE INDEX idx_contracts_user_dates ON employment_contracts(user_id, start_date, end_date);
```

### 1.7. `work_schedules`
Defines operational shift schedules and templates.
- **Justification**: Templates for weekly/monthly shift rotation per branch or department.

```sql
CREATE TABLE work_schedules (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    location_id BIGINT NULL REFERENCES locations(id) ON DELETE CASCADE,
    department_id BIGINT NULL REFERENCES hr_departments(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    schedule_type VARCHAR(50) NOT NULL DEFAULT 'weekly', -- 'weekly', 'monthly', 'custom'
    start_date DATE NOT NULL,
    end_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_work_schedules_vendor_loc ON work_schedules(vendor_id, location_id);
```

### 1.8. `work_shifts`
Specific planned shift assigned to an individual staff member.
- **Justification**: Handles planned rosters, start/end times, break allowances, and overnight (midnight-crossing) flags.

```sql
CREATE TABLE work_shifts (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    schedule_id BIGINT NULL REFERENCES work_schedules(id) ON DELETE CASCADE,
    location_id BIGINT NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    shift_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    cross_midnight BOOLEAN NOT NULL DEFAULT FALSE,
    unpaid_break_minutes INT NOT NULL DEFAULT 30,
    status VARCHAR(50) NOT NULL DEFAULT 'scheduled', -- 'scheduled', 'confirmed', 'completed', 'cancelled', 'swapped'
    notes TEXT NULL,
    created_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_work_shifts_user_date ON work_shifts(user_id, shift_date);
CREATE INDEX idx_work_shifts_vendor_loc_date ON work_shifts(vendor_id, location_id, shift_date);
```

### 1.9. `attendance_records`
Actual time clock check-in and check-out logs.
- **Justification**: Captures biometric/QR/PIN punches, actual worked duration, lateness, and geolocation.

```sql
CREATE TABLE attendance_records (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    location_id BIGINT NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    shift_id BIGINT NULL REFERENCES work_shifts(id) ON DELETE SET NULL,
    
    check_in_at TIMESTAMP WITH TIME ZONE NOT NULL,
    check_out_at TIMESTAMP WITH TIME ZONE NULL,
    break_start_at TIMESTAMP WITH TIME ZONE NULL,
    break_end_at TIMESTAMP WITH TIME ZONE NULL,
    total_break_minutes INT NOT NULL DEFAULT 0,
    
    source VARCHAR(50) NOT NULL DEFAULT 'qr_badge', -- 'qr_badge', 'pin', 'manager', 'manual'
    check_in_latitude DECIMAL(10, 7) NULL,
    check_in_longitude DECIMAL(10, 7) NULL,
    
    status VARCHAR(50) NOT NULL DEFAULT 'present', -- 'present', 'late', 'early_departure', 'incomplete'
    late_minutes INT NOT NULL DEFAULT 0,
    early_departure_minutes INT NOT NULL DEFAULT 0,
    
    is_verified BOOLEAN NOT NULL DEFAULT TRUE,
    verified_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_attendance_user_in ON attendance_records(user_id, check_in_at);
CREATE INDEX idx_attendance_vendor_loc ON attendance_records(vendor_id, location_id, check_in_at);
```

### 1.10. `attendance_adjustments`
Supervisor manual corrections to punch cards with full audit reasoning.
- **Justification**: Guarantees audit compliance whenever an administrator edits or adds clock hours manually.

```sql
CREATE TABLE attendance_adjustments (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    attendance_record_id BIGINT NOT NULL REFERENCES attendance_records(id) ON DELETE CASCADE,
    adjusted_by BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    original_check_in TIMESTAMP WITH TIME ZONE NULL,
    original_check_out TIMESTAMP WITH TIME ZONE NULL,
    adjusted_check_in TIMESTAMP WITH TIME ZONE NOT NULL,
    adjusted_check_out TIMESTAMP WITH TIME ZONE NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'approved', -- 'pending', 'approved', 'rejected'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_attendance_adj_rec ON attendance_adjustments(attendance_record_id);
```

### 1.11. `timesheets`
Consolidated working hours per employee per pay period (Tabel).
- **Justification**: Aggregates raw attendance punches into auditable pay units (Regular, Overtime, Night, Holiday).

```sql
CREATE TABLE timesheets (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    location_id BIGINT NULL REFERENCES locations(id) ON DELETE SET NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    
    scheduled_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    worked_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    regular_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    overtime_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    night_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    holiday_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    paid_leave_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    unpaid_leave_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    
    status VARCHAR(50) NOT NULL DEFAULT 'draft', -- 'draft', 'submitted', 'approved', 'locked'
    approved_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP WITH TIME ZONE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_timesheet_user_period UNIQUE (vendor_id, user_id, period_start, period_end)
);

CREATE INDEX idx_timesheets_vendor_period ON timesheets(vendor_id, period_start, period_end, status);
```

### 1.12. `leave_types`
Configurable absence categories (Annual, Sick, Unpaid, Maternity, Compassionate).
- **Justification**: Allows flexible absence categories without hardcoding statutory leave rules.

```sql
CREATE TABLE leave_types (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL, -- 'annual', 'sick', 'unpaid', 'personal', 'maternity'
    is_paid BOOLEAN NOT NULL DEFAULT TRUE,
    default_days_per_year INT NOT NULL DEFAULT 20,
    requires_approval BOOLEAN NOT NULL DEFAULT TRUE,
    requires_attachment BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_leave_types_vendor_code UNIQUE (vendor_id, code)
);
```

### 1.13. `leave_requests`
Employee leave applications and approval workflows.

```sql
CREATE TABLE leave_requests (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    leave_type_id BIGINT NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5, 2) NOT NULL,
    reason TEXT NOT NULL,
    attachment_path TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'cancelled'
    reviewed_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP WITH TIME ZONE NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_leave_requests_user ON leave_requests(user_id, start_date, end_date);
CREATE INDEX idx_leave_requests_vendor_status ON leave_requests(vendor_id, status);
```

### 1.14. `employee_leave_balances`
Tracks annual leave entitlements, accruals, and utilized days.

```sql
CREATE TABLE employee_leave_balances (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    leave_type_id BIGINT NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    year INT NOT NULL,
    allocated_days DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    used_days DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    remaining_days DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_emp_leave_bal UNIQUE (vendor_id, user_id, leave_type_id, year)
);
```

### 1.15. `payroll_periods`
Master payroll calculation cycles (e.g. Monthly, Bi-weekly).

```sql
CREATE TABLE payroll_periods (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    cycle_type VARCHAR(50) NOT NULL DEFAULT 'monthly', -- 'monthly', 'semi_monthly', 'weekly', 'custom'
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    cutoff_date DATE NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'AMD',
    
    total_gross DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    total_net DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    total_tax DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    total_paid DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    
    status VARCHAR(50) NOT NULL DEFAULT 'draft', -- 'draft', 'calculating', 'pending_approval', 'approved', 'paid', 'locked', 'cancelled'
    approved_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP WITH TIME ZONE NULL,
    locked_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    locked_at TIMESTAMP WITH TIME ZONE NULL,
    
    snapshot_rules JSONB NULL,
    notes TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payroll_periods_vendor ON payroll_periods(vendor_id, start_date, end_date, status);
```

### 1.16. `payroll_items`
Individual employee earnings, deductions, taxes, and net payable in a payroll run.

```sql
CREATE TABLE payroll_items (
    id BIGSERIAL PRIMARY KEY,
    payroll_period_id BIGINT NOT NULL REFERENCES payroll_periods(id) ON DELETE CASCADE,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    base_salary DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    worked_days DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    worked_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    
    regular_pay DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    overtime_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    overtime_pay DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    night_hours DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    night_pay DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    holiday_pay DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    bonuses_total DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    allowances_total DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    
    gross_salary DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    
    -- Taxes & Statutory Deductions
    income_tax DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    pension_fee DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    stamp_fee DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    other_deductions DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    advance_deduction DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    
    net_salary DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid', -- 'unpaid', 'partially_paid', 'paid'
    
    calculation_breakdown JSONB NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_payroll_period_user UNIQUE (payroll_period_id, user_id)
);

CREATE INDEX idx_payroll_items_user ON payroll_items(user_id, payment_status);
```

### 1.17. `payroll_item_components`
Transparent itemized lines (Earnings, Deductions, Taxes, Employer Levies) for payslip display.

```sql
CREATE TABLE payroll_item_components (
    id BIGSERIAL PRIMARY KEY,
    payroll_item_id BIGINT NOT NULL REFERENCES payroll_items(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'earning', 'deduction', 'tax', 'employer_contribution'
    code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    rate DECIMAL(8, 4) NULL,
    amount DECIMAL(14, 2) NOT NULL,
    meta JSONB NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payroll_components_item ON payroll_item_components(payroll_item_id, type);
```

### 1.18. `employee_advances`
Salary advance disbursements and payroll deduction schedule.

```sql
CREATE TABLE employee_advances (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount DECIMAL(14, 2) NOT NULL,
    requested_date DATE NOT NULL,
    disbursement_date DATE NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash', -- 'cash', 'bank_transfer', 'other'
    recovered_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    remaining_amount DECIMAL(14, 2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'disbursed', 'recovered', 'cancelled'
    reason TEXT NOT NULL,
    approved_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    payroll_period_id BIGINT NULL REFERENCES payroll_periods(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_advances_vendor_user ON employee_advances(vendor_id, user_id, status);
```

### 1.19. `salary_payments`
Financial payout transactions fulfilling net salaries.

```sql
CREATE TABLE salary_payments (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    payroll_period_id BIGINT NOT NULL REFERENCES payroll_periods(id) ON DELETE CASCADE,
    payroll_item_id BIGINT NOT NULL REFERENCES payroll_items(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    payment_number VARCHAR(100) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'bank_transfer', -- 'bank_transfer', 'cash', 'other'
    reference_number VARCHAR(255) NULL,
    proof_document_path TEXT NULL,
    notes TEXT NULL,
    created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status VARCHAR(50) NOT NULL DEFAULT 'completed', -- 'completed', 'voided'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_salary_payments_vendor_number UNIQUE (vendor_id, payment_number)
);

CREATE INDEX idx_salary_payments_item ON salary_payments(payroll_item_id);
```

### 1.20. `employee_documents`
Staff digital personnel files (contracts, diplomas, IDs, medical cards, decrees).

```sql
CREATE TABLE employee_documents (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'contract', -- 'contract', 'id', 'medical', 'decree', 'other'
    file_path TEXT NOT NULL,
    file_size_bytes BIGINT NOT NULL DEFAULT 0,
    mime_type VARCHAR(100) NOT NULL,
    expires_at DATE NULL,
    uploaded_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_employee_docs_user ON employee_documents(user_id, category);
```

### 1.21. `hr_payroll_settings`
Configurable tax, social, and overtime policy rates per vendor.

```sql
CREATE TABLE hr_payroll_settings (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    country_code VARCHAR(10) NOT NULL DEFAULT 'AM',
    income_tax_rate DECIMAL(6, 4) NOT NULL DEFAULT 0.2000, -- 20%
    pension_rate DECIMAL(6, 4) NOT NULL DEFAULT 0.0500,    -- 5%
    pension_ceiling_gross DECIMAL(14, 2) NOT NULL DEFAULT 1125000.00,
    stamp_fee_tiers JSONB NOT NULL,
    overtime_rate_multiplier DECIMAL(4, 2) NOT NULL DEFAULT 1.50,
    night_rate_multiplier DECIMAL(4, 2) NOT NULL DEFAULT 0.30,
    holiday_rate_multiplier DECIMAL(4, 2) NOT NULL DEFAULT 2.00,
    standard_monthly_hours INT NOT NULL DEFAULT 168,
    standard_weekly_hours INT NOT NULL DEFAULT 40,
    effective_from DATE NOT NULL DEFAULT CURRENT_DATE,
    effective_until DATE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_hr_payroll_settings_vendor_date UNIQUE (vendor_id, effective_from)
);
```

