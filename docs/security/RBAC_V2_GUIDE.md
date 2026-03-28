# RBAC v2 Guide — Role-Based Access Control

**Version:** 2.0  
**Date:** 2026-03-17  
**Status:** Active

---

## Overview

RBAC v2 simplifies the role system to **7 core roles + 2 portal roles**. Permissions are determined by:

```
Effective Permissions = Base Role + Department Module Assignment
```

---

## Role Hierarchy

| Role | Purpose | Bypass SoD | Bypass Dept Scope |
|------|---------|------------|-------------------|
| `super_admin` | Testing superuser | ✅ Yes | ✅ Yes |
| `admin` | System administration | ✅ Yes | ✅ Yes |
| `executive` | Board-level read-only | ❌ No | ✅ Yes |
| `vice_president` | Final approver | ❌ No | ✅ Yes |
| `manager` | Full department access | ❌ No | ❌ No |
| `officer` | Operations/Processing | ❌ No | ❌ No |
| `head` | Team supervisor | ❌ No | ❌ No |
| `staff` | Self-service only | ❌ No | ❌ No |
| `vendor` | Vendor portal | N/A | N/A |
| `client` | Client portal | N/A | N/A |

---

## Core Role Permissions

### `super_admin` — Testing Superuser
- **All permissions** — bypasses all checks
- **Use for:** Testing without workflow constraints
- **Do not use for:** Validating SoD or role-based restrictions

### `admin` — System Administrator
- User management, role assignment, department assignment
- System settings, rate tables, holidays
- Audit logs, backups, monitoring
- **No business data access** (employees, payroll, invoices)

### `executive` — Board Read-Only
- View-only access to all modules
- Executive approvals (overtime, special requests)
- Cannot create, edit, or approve standard workflows

### `vice_president` — Final Approver
- Cross-department approval authority
- VP approval queue for PRs, loans, MRQs
- Final sign-off on financial transactions

### `manager` — Department Manager
- Full CRUD for assigned department modules
- **HR Department:** employees, payroll, leaves, loans
- **Accounting:** journal entries, AP, AR, banking
- **Production:** orders, BOM, schedules
- Can approve workflows within department

### `officer` — Department Operations
- Create, update, process records
- **Cannot** final approve (different user must approve)
- Accounting officer: GL, AP, AR, banking
- HR officer: attendance, leave processing

### `head` — Team Supervisor
- View team data
- First-level approvals (leave head approve, OT supervise)
- Cannot access full department management

### `staff` — Self-Service
- View own profile, attendance, payslips
- Submit own leave, OT, loan requests
- Cannot access team or department data

---

## Department Modules

Modules determine **which permissions** a role has within their assigned department:

| Module | Permissions Include |
|--------|---------------------|
| `hr` | employees.*, payroll.*, leaves.*, loans.*, attendance.* |
| `accounting` | journal_entries.*, vendors.*, ap.*, ar.*, banking.* |
| `production` | production.*, qc.*, maintenance.*, mold.*, inventory.view |
| `warehouse` | inventory.*, mrq.*, delivery.view |
| `purchasing` | procurement.*, vendors.view |
| `sales` | crm.*, customers.view |
| `operations` | Limited access (IT, Executive, ISO) |

---

## Segregation of Duties (SoD)

### Enforced SoD Rules

| Rule | Description | Enforcement |
|------|-------------|-------------|
| SOD-001 | Creator ≠ Activator (employees) | Employee activation blocked for creator |
| SOD-002 | Submitter ≠ Approver (leaves) | SoD button disabled for submitter |
| SOD-003 | Requester ≠ Approver (overtime) | SoD button disabled for requester |
| SOD-004 | HR ≠ Approver (loans v1) | Separate HR and accounting approval |
| SOD-005/006 | Payroll preparer ≠ HR approver | Different users required |
| SOD-007 | HR ≠ Accounting approver | Accounting must approve HR-prepared payroll |
| SOD-008 | Creator ≠ Poster (journal entries) | SoD button disabled for creator |
| SOD-009 | Creator ≠ Approver (vendor invoices) | SoD button disabled for creator |
| SOD-010 | Creator ≠ Approver (customer invoices) | SoD button disabled for creator |

### SoD Bypass

Only these roles bypass SoD:
- `admin`
- `super_admin`

**Note:** `manager` does **NOT** bypass SoD. This ensures proper workflow separation.

---

## Frontend Implementation

### Using SodActionButton

```tsx
import { SodActionButton } from '@/components/ui/SodActionButton'

<SodActionButton
  initiatedById={record.submitted_by}  // ID of user who created/submitted
  label="Approve"
  onClick={() => handleApprove(record.id)}
  isLoading={mutation.isPending}
  variant="success"
/>
```

### Using useSodCheck Hook

```tsx
import { useSodCheck } from '@/hooks/useSodCheck'

const { isBlocked, reason } = useSodCheck(record.created_by)

<button 
  disabled={isBlocked}
  title={reason ?? undefined}
>
  Approve
</button>
```

---

## Backend Implementation

### SoD Middleware

```php
Route::patch('payroll/runs/{run}/approve', [PayrollRunController::class, 'approve'])
    ->middleware(['sod:payroll,approve']);
```

### Department Scope Middleware

```php
Route::middleware(['dept_scope'])->group(function () {
    Route::apiResource('employees', EmployeeController::class);
});
```

---

## Test Accounts

| Email | Password | Role | Department |
|-------|----------|------|------------|
| superadmin@ogamierp.local | SuperAdmin@12345! | super_admin | — |
| admin@ogamierp.local | Admin@1234567890! | admin | — |
| chairman@ogamierp.local | Executive@12345! | executive | EXEC |
| vp@ogamierp.local | Vp123!@# | vice_president | All |
| hr.manager@ogamierp.local | HrManager@1234! | manager | HR |
| plantmanager@ogamierp.local | PlantManager123!@# | manager | PLANT |
| acctg.officer@ogamierp.local | AcctgManager@1234! | officer | ACCTG |
| supervisor@ogamierp.local | Supervisor123!@# | head | PROD |
| prod.staff@ogamierp.local | Staff@123456789! | staff | PROD |

---

## Migration from RBAC v1

### Deprecated Roles (Replaced)

| Old Role | New Role | Notes |
|----------|----------|-------|
| `plant_manager` | `manager` + plant dept | Assign to PLANT department |
| `production_manager` | `manager` + prod dept | Assign to PROD department |
| `qc_manager` | `manager` + qc dept | Assign to QC department |
| `mold_manager` | `manager` + mold dept | Assign to MOLD department |
| `ga_officer` | `officer` + hr dept | Assign to HR department |
| `purchasing_officer` | `officer` + purch dept | Assign to PURCH department |
| `impex_officer` | `officer` + impex dept | Assign to IMPEX department |
| `supervisor` | `head` | Renamed role |

### Migration Command

```bash
php artisan rbac:cleanup-old-roles
```

---

## Common Issues

### Issue: Manager cannot approve their own leave request
**Expected:** This is correct SoD behavior. A different manager must approve.

### Issue: Officer sees "SoD" block on approve button
**Expected:** Officers create/process but cannot final approve. Different role required.

### Issue: Head cannot see all department employees
**Expected:** Head sees only their assigned team. Manager sees full department.

### Issue: VP cannot approve cross-department
**Check:** Verify VP has department assignments for all relevant departments.

---

## References

- [SoD Audit Report](./SOD_AUDIT_REPORT.md)
- [Role Test Accounts](./ROLE_TEST_ACCOUNTS.md)
- [API Documentation](./API.md)
- [System Flowchart](./SYSTEM_FLOWCHART.md)
