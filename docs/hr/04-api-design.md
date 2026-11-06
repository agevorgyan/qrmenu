# PHASE 0 — API & ENDPOINT DESIGN SPECIFICATION
## RESTful Endpoints, FormRequests, Authorization Policies & Payload Contracts

---

### 1. General API Architecture & Routing Conventions

All HR & Payroll API endpoints are integrated within the existing authenticated Admin and Staff routing groups in `routes/web.php` (and corresponding JSON API endpoints where used by mobile/POS/ESS clients).

- **Admin/Manager Routes**: Prefix `/admin/hr`, middleware `['auth', 'verified', 'role:vendor_owner,manager', EnsureSubscriptionIsActive::class]`.
- **Employee Self-Service (ESS) Routes**: Prefix `/admin/hr/self-service`, middleware `['auth', 'verified']`.
- **Time Clock Station (POS / Kiosk)**: Prefix `/admin/hr/timeclock`, middleware `['auth', 'verified']`.
- **Response Format**: Consistent JSON responses for AJAX/API requests:
  ```json
  {
    "success": true,
    "message": "Operation completed successfully.",
    "data": {}
  }
  ```

---

## 2. Comprehensive Endpoint Catalog

### 2.1. Departments & Positions
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/departments` | `hr.departments.view` | List departments with employee count |
| `POST` | `/admin/hr/departments` | `hr.departments.manage` | Create new department |
| `PUT` | `/admin/hr/departments/{dept}` | `hr.departments.manage` | Update department details |
| `DELETE`| `/admin/hr/departments/{dept}` | `hr.departments.manage` | Soft delete or deactivate department |
| `GET` | `/admin/hr/positions` | `hr.positions.view` | List positions grouped by department |
| `POST` | `/admin/hr/positions` | `hr.positions.manage` | Create new position |
| `PUT` | `/admin/hr/positions/{pos}` | `hr.positions.manage` | Update position and default pay rates |
| `DELETE`| `/admin/hr/positions/{pos}` | `hr.positions.manage` | Deactivate position |

### 2.2. Employee Profiles & Records
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/employees` | `employees.view` | Paginated employee roster with HR details |
| `GET` | `/admin/hr/employees/{user}` | `employees.view` | Detailed employee profile (Tabs) |
| `PUT` | `/admin/hr/employees/{user}` | `employees.manage` | Update personal and employment info |
| `GET` | `/admin/hr/employees/{user}/sensitive`| `employees.view_sensitive`| View compensation, bank account, SSN |
| `POST`| `/admin/hr/employees/{user}/transfer`| `employees.manage` | Transfer branch/dept/position (with history) |
| `POST`| `/admin/hr/employees/{user}/salary` | `hr.payroll.manage` | Revise base salary / rate (with history) |

### 2.3. Employment Contracts & Documents
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/contracts` | `hr.contracts.view` | List contracts with status & expiration |
| `POST` | `/admin/hr/contracts` | `hr.contracts.create`| Issue new employment contract |
| `PUT` | `/admin/hr/contracts/{contract}`| `hr.contracts.update`| Update contract terms |
| `POST` | `/admin/hr/documents/{user}` | `hr.documents.manage`| Upload employee PDF / ID document |
| `GET` | `/admin/hr/documents/{doc}/download`| `hr.documents.view`| Secure signed download URL |

### 2.4. Work Scheduling & Shift Roster
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/schedules` | `hr.schedules.view` | View calendar/weekly shift matrix |
| `POST` | `/admin/hr/shifts` | `hr.schedules.manage`| Create planned shift for employee |
| `PUT` | `/admin/hr/shifts/{shift}` | `hr.schedules.manage`| Update shift hours or reassignment |
| `DELETE`| `/admin/hr/shifts/{shift}` | `hr.schedules.manage`| Cancel scheduled shift |
| `POST` | `/admin/hr/shifts/copy-week` | `hr.schedules.manage`| Duplicate previous week's roster |

### 2.5. Attendance & Time Clock
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/attendance` | `hr.attendance.view` | Daily attendance live log & status |
| `POST` | `/admin/hr/timeclock/punch` | `hr.attendance.clock` | Employee Clock-in/Clock-out (QR/PIN) |
| `POST` | `/admin/hr/attendance/manual` | `hr.attendance.manage`| Manager manual attendance entry |
| `POST` | `/admin/hr/attendance/{rec}/adjust`| `hr.attendance.manage`| Supervisor punch card adjustment |
| `GET` | `/admin/hr/timeclock/qr-token` | `hr.attendance.clock`| Get rotating signed dynamic QR token |

### 2.6. Timesheet (Tabel) Management
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/timesheets` | `hr.timesheets.view` | Timesheet matrix for selected period |
| `POST` | `/admin/hr/timesheets/generate`| `hr.timesheets.manage`| Compile timesheets from attendance |
| `POST` | `/admin/hr/timesheets/{ts}/approve`| `hr.timesheets.approve`| Lock & approve timesheet for payroll |

### 2.7. Leave & Absence Management
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/leaves` | `hr.leave.view` | Leave calendar & pending applications |
| `POST` | `/admin/hr/leaves` | `hr.leave.request` | Apply for leave (Staff or Manager) |
| `POST` | `/admin/hr/leaves/{req}/review`| `hr.leave.approve` | Approve or Reject leave request |
| `GET` | `/admin/hr/leaves/balances` | `hr.leave.view` | Annual leave balances per staff |

### 2.8. Payroll Calculation & Runs
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/payroll` | `hr.payroll.view` | List payroll periods & summary totals |
| `POST` | `/admin/hr/payroll/create-period`| `hr.payroll.calculate`| Initialize new payroll period |
| `POST` | `/admin/hr/payroll/{period}/calculate`| `hr.payroll.calculate`| Execute gross-to-net computation |
| `GET` | `/admin/hr/payroll/{period}/preview`| `hr.payroll.view` | View detailed breakdown before approval |
| `POST` | `/admin/hr/payroll/{period}/approve`| `hr.payroll.approve` | Formally approve payroll calculation |
| `POST` | `/admin/hr/payroll/{period}/lock` | `hr.payroll.lock` | Lock period and archive snapshot |
| `GET` | `/admin/hr/payroll/items/{item}/payslip`| `hr.payroll.view` | Generate PDF payslip |

### 2.9. Salary Payouts & Advances
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/payments` | `hr.payroll.view` | Payout history & pending balances |
| `POST` | `/admin/hr/payments` | `hr.payroll.pay` | Record salary payout transaction |
| `GET` | `/admin/hr/advances` | `hr.advances.view` | List employee advance requests |
| `POST` | `/admin/hr/advances` | `hr.advances.request`| Request salary advance |
| `POST` | `/admin/hr/advances/{adv}/review`| `hr.advances.approve`| Approve or reject advance request |

### 2.10. Employee Self-Service (ESS)
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/self-service` | `auth` | Staff personalized portal dashboard |
| `GET` | `/admin/hr/self-service/shifts` | `auth` | View assigned upcoming shifts |
| `GET` | `/admin/hr/self-service/attendance`| `auth` | View own attendance & worked hours |
| `POST` | `/admin/hr/self-service/leaves` | `auth` | Submit personal leave application |
| `GET` | `/admin/hr/self-service/payslips`| `auth` | Download own historical payslips |

### 2.11. Reports & Exports
| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admin/hr/reports/dashboard` | `hr.reports.view` | HR KPI metrics & operational charts |
| `GET` | `/admin/hr/reports/payroll-cost`| `hr.reports.view` | Labor cost grouped by branch/dept |
| `GET` | `/admin/hr/reports/attendance` | `hr.reports.view` | Lateness, absence & overtime audit |
| `GET` | `/admin/hr/reports/export/{type}` | `hr.reports.export`| CSV / Excel export |

