# Ogami ERP - Roles and Positions Guide

Complete reference of all system roles (RBAC) and department positions.

---

## Table of Contents

1. [System Roles (RBAC)](#system-roles-rbac)
2. [Department Positions](#department-positions)
3. [Role-to-Position Mapping](#role-to-position-mapping)
4. [SoD (Segregation of Duties) Rules](#sod-segregation-of-duties-rules)

---

## System Roles (RBAC)

### Overview

Ogami ERP uses **18 system roles** for access control. Each role defines what a user can do across the system.

### Role Hierarchy

```
super_admin        (Testing only - bypasses all rules)
    ↓
admin              (System administration only)
    ↓
executive          (Chairman/President - read-only observers)
    ↓
vice_president     (Final approver - financial authority)
    ↓
[Department Managers]    [Officers]     [Heads]     [Staff]
- manager                - officer      - head      - staff
- plant_manager          - ga_officer
- production_manager     - purchasing_officer
- qc_manager             - impex_officer
- mold_manager
- crm_manager
    ↓
vendor / client    (External portal users)
```

---

### 1. ADMIN

| Attribute | Value |
|-----------|-------|
| **Role Name** | `admin` |
| **Purpose** | System custodian - manages users, settings, backups |
| **Business Data Access** | ❌ NONE (zero business data access) |
| **SoD Bypass** | ❌ No (except system functions) |

**Permissions:**
- System management (users, roles, departments, settings)
- Rate table management (SSS, PhilHealth, tax brackets)
- Holiday calendar management
- Audit log viewing
- Backup/restore
- View vendors/customers (for portal account provisioning)

**Typical Users:**
- `admin@ogamierp.local` - System Administrator

---

### 2. SUPER_ADMIN

| Attribute | Value |
|-----------|-------|
| **Role Name** | `super_admin` |
| **Purpose** | Testing superuser - full system access |
| **Business Data Access** | ✅ ALL modules |
| **SoD Bypass** | ✅ YES (bypasses SoD + department scope) |

**Permissions:**
- ALL permissions in the system
- Bypasses Gate checks
- Bypasses SoD rules
- Bypasses department scoping

**⚠️ WARNING:** Only for testing/development. Never use in production workflows.

**Typical Users:**
- `superadmin@ogamierp.local` - Testing superuser

---

### 3. EXECUTIVE

| Attribute | Value |
|-----------|-------|
| **Role Name** | `executive` |
| **Purpose** | Chairman/President - board-level oversight |
| **Business Data Access** | ✅ Read-only across all modules |
| **SoD Bypass** | ❌ No |

**Permissions:**
- View employees, attendance, overtime (executive approval)
- View leaves, loans, payroll
- View journal entries, COA, fiscal periods
- View vendors, customers, invoices
- Financial reports (statements, GL, trial balance, aging)
- Budget view-only
- Fixed assets view-only
- Self-service (file own overtime)

**Typical Users:**
- `executive@ogamierp.local` - Chairman/President

---

### 4. VICE_PRESIDENT

| Attribute | Value |
|-----------|-------|
| **Role Name** | `vice_president` |
| **Purpose** | VP - final approver of all financial requests |
| **Business Data Access** | ✅ Cross-department visibility |
| **SoD Rule** | SOD-011 to SOD-014 (final approval) |

**Permissions:**
- **Approvals Dashboard** (`/approvals/pending`)
- Final approval for: loans, payroll, procurement, MRQ
- Budget approval
- View-only access to all modules (context for approvals)
- Self-service (leaves, overtime)

**Approval Authority:**
- Loans: Step 5 final approval
- PR: Step 4 VP approval (high value)
- MRQ: VP approval
- Payroll: VP approval (if configured)

**Typical Users:**
- `vp@ogamierp.local` - Vice President

---

### 5. MANAGER (HR Manager)

| Attribute | Value |
|-----------|-------|
| **Role Name** | `manager` |
| **Purpose** | HR Manager - full HR and Payroll management |
| **Department** | HR only |
| **SoD Rule** | Cannot approve own payroll runs |

**Permissions:**
- **HR Full Access:**
  - Create/update/activate/suspend/terminate employees
  - View salary and government IDs
  - Upload/download documents
  - Manage org structure
- **Attendance:**
  - Import CSV, manage shifts, resolve anomalies
  - Approve/reject overtime
- **Leave:**
  - Approve leaves (Step 2 checker)
  - GA process (Step 4)
  - Adjust balances
- **Loans:**
  - HR approve (Step 2 checker in v2 chain)
  - Manager check (v2)
- **Payroll:**
  - Create/initiate runs
  - Manage pay periods
  - Disburse (with SoD separation)
- **Procurement:** Step 2 checker (PR check)

**Typical Users:**
- `hr.manager@ogamierp.local` - Maria Santos (HR Manager)

---

### 6. PLANT_MANAGER

| Attribute | Value |
|-----------|-------|
| **Role Name** | `plant_manager` |
| **Purpose** | Oversees ALL plant operations |
| **Scope** | Production, QC, Maintenance, Mold, Delivery, ISO |

**Permissions:**
- **Production/PPC:** Full control (BOM, schedules, work orders)
- **QC/QA:** Full management (templates, inspections, NCR)
- **Maintenance:** Full management
- **Mold:** Full management + shot logging
- **Delivery/Logistics:** Full management
- **ISO/IATF:** Full management + audits
- **Inventory:** View stock, create MRQs
- **Approval:** Step 2 reviewer for PR, MRQ

**Typical Users:**
- `plant.manager@ogamierp.local` - Carlos Mendoza

---

### 7. PRODUCTION_MANAGER

| Attribute | Value |
|-----------|-------|
| **Role Name** | `production_manager` |
| **Purpose** | Supervises production activities only |
| **Scope** | Production floor, work orders |

**Permissions:**
- **Production:** Full control (BOM, schedules, work orders)
- **Work Orders:** Create, release, complete, log output
- **Inventory:** View only (material availability)
- **Self-service:** Own attendance, leaves, overtime

**Typical Users:**
- `prod.manager@ogamierp.local` - Jose Martinez

---

### 8. QC_MANAGER

| Attribute | Value |
|-----------|-------|
| **Role Name** | `qc_manager` |
| **Purpose** | Quality control and assurance operations |
| **Scope** | QC/QA only |

**Permissions:**
- **QC/QA:** Full management
  - Templates (create/manage)
  - Inspections (incoming, in-process, final)
  - NCR (create, manage, close)
  - QC override for production
- **Inventory:** View items/stock (QC context)
- **Self-service:** Own attendance, leaves, overtime

**Typical Users:**
- `qc.manager@ogamierp.local` - Linda Wong

---

### 9. MOLD_MANAGER

| Attribute | Value |
|-----------|-------|
| **Role Name** | `mold_manager` |
| **Purpose** | Mold department operations |
| **Scope** | Mold only |

**Permissions:**
- **Mold:** Full management
  - View/manage mold masters
  - Log shot counts
- **Inventory:** View only
- **Self-service:** Own attendance, leaves, overtime

**Typical Users:**
- `mold.manager@ogamierp.local` - Ramon Del Rosario

---

### 10. OFFICER (Accounting Officer)

| Attribute | Value |
|-----------|-------|
| **Role Name** | `officer` |
| **Purpose** | Full financial management (GL/AP/AR/Payroll/Banking) |
| **Scope** | All accounting and finance |
| **SoD Rules** | Cannot create vendors/customers (purchasing does) |

**Permissions:**
- **GL & Journal Entries:**
  - Create, update, submit, post, reverse
  - Chart of accounts management
  - Fiscal periods management
- **AP (Accounts Payable):**
  - Create/update/submit vendor invoices
  - Approve vendor invoices (with SoD)
  - Record payments
  - Generate BIR 2307
- **AR (Accounts Receivable):**
  - Create/update customer invoices
  - Approve invoices
  - Receive payments, write-offs
  - Apply payments
- **Banking:**
  - Bank accounts (CRUD)
  - Reconciliations (create, certify)
- **Payroll:**
  - Approve (accounting side)
  - Disburse, publish
  - Download bank files, registers
  - Government reports
- **Budget:** Full management
- **Fixed Assets:** Full management
- **Inventory:** Stock management, adjustments
- **Procurement:** Step 3 reviewer, PO management

**Typical Users:**
- `acctg.officer@ogamierp.local` - Anna Marie Lim
- `acctg.manager@ogamierp.local` - Amelia Cordero

---

### 11. GA_OFFICER (General Affairs Officer)

| Attribute | Value |
|-----------|-------|
| **Role Name** | `ga_officer` |
| **Purpose** | HR administrative support |
| **Scope** | HR support functions |
| **NO Financial Access** | ❌ Cannot access accounting/payroll approvals |

**Permissions:**
- **HR Support:**
  - View team employees
  - Upload/download documents
- **Attendance Management:**
  - Import CSV
  - Resolve anomalies
  - Manage shifts
- **Leave:**
  - GA process (Step 4)
  - View team leaves
- **Self-service:** Own payslip, attendance, leaves

**Typical Users:**
- `ga.officer@ogamierp.local` - Grace Torres

---

### 12. PURCHASING_OFFICER

| Attribute | Value |
|-----------|-------|
| **Role Name** | `purchasing_officer` |
| **Purpose** | Procurement and materials ordering |
| **Scope** | Procurement only |
| **NO Financial Access** | ❌ Cannot approve invoices or payments |

**Permissions:**
- **Procurement:**
  - Create/manage purchase requests
  - Create/manage purchase orders
  - Review PR (Step 2/3)
- **Vendors:**
  - Full management (create, accredit, suspend, archive)
- **Customers:**
  - Manage (for AR master data setup)
- **Inventory:** View only (sourcing context)
- **Delivery:** View inbound receipts

**Typical Users:**
- `purchasing@ogamierp.local` - (Test account)

---

### 13. IMPEX_OFFICER

| Attribute | Value |
|-----------|-------|
| **Role Name** | `impex_officer` |
| **Purpose** | Import/export and delivery management |
| **Scope** | Logistics and shipments |
| **NO Financial Access** | ❌ |

**Permissions:**
- **Delivery:** Full management
- **Goods Receipts:** Create and confirm
- **Procurement:** View POs/PRs (shipment context)
- **Inventory:** View only (shipment context)
- **Vendors:** View only

**Typical Users:**
- `impex@ogamierp.local` - (Test account)

---

### 14. HEAD

| Attribute | Value |
|-----------|-------|
| **Role Name** | `head` |
| **Purpose** | Department Heads - Step 2 approver |
| **Scope** | Varies by department |

**Permissions (Base):**
- **Team Management:**
  - View team employees
  - Create employees (initial)
  - Upload documents
- **Attendance:** View team, import CSV, manage shifts
- **Overtime:** Supervise, approve
- **Leave:** Head approve (Step 2)
- **Loans:** Supervisor review, head note (v2)
- **Finance View:** Vendors, invoices, payments (context)
- **Inventory:** View stock, note MRQs

**Sub-types:**
- **warehouse_head** - Full inventory + goods receipt management
- **ppc_head** - Production planning + MRQ creation

**Typical Users:**
- `hr.head@ogamierp.local` - Ricardo Cruz
- `acctg.head@ogamierp.local` - Roberto Fernandez
- `prod.head@ogamierp.local` - Elena Rodriguez
- `qc.head@ogamierp.local` - Patricia Gonzalez
- `mold.head@ogamierp.local` - Fernando Bautista
- `plant.head@ogamierp.local` - Manuel Villar
- `dept.head@ogamierp.local` - Generic head (TestAccountsSeeder)

---

### 15. STAFF

| Attribute | Value |
|-----------|-------|
| **Role Name** | `staff` |
| **Purpose** | Rank-and-file - creates and submits requests |
| **Scope** | Self-service + data entry |

**Permissions:**
- **Self-service:**
  - View own payslip
  - File leaves, overtime
  - Apply for loans
  - View own attendance
- **Production:** Log output
- **Mold:** Log shots
- **Inventory:** Create MRQs
- **Cannot approve anything**

**Typical Users:**
- `hr.staff@ogamierp.local` - Juan Dela Cruz
- `acctg.staff@ogamierp.local` - Miguel Garcia
- `prod.staff@ogamierp.local` - Carlos Reyes
- `qc.staff@ogamierp.local` - Ramon Flores
- `mold.staff@ogamierp.local` - Antonio Gomez
- `sales.staff@ogamierp.local` - Diana Santos
- `staff@ogamierp.local` - Generic staff (TestAccountsSeeder)

---

### 16. CRM_MANAGER

| Attribute | Value |
|-----------|-------|
| **Role Name** | `crm_manager` |
| **Purpose** | Customer relationship management |
| **Scope** | CRM, support tickets |

**Permissions:**
- **CRM:**
  - View/create/reply/manage tickets
  - Assign agents
  - Close tickets
- **Self-service:** Own attendance, leaves, overtime

**Typical Users:**
- `crm.manager@ogamierp.local` - Carrie Macaraig

---

### 17. VENDOR (External)

| Attribute | Value |
|-----------|-------|
| **Role Name** | `vendor` |
| **Purpose** | External vendor portal access |
| **Scope** | Vendor portal only |

**Permissions:**
- View purchase orders
- Update fulfillment status
- Manage items
- View receipts

**Typical Users:**
- `vendor@ogamierp.local` - ABC Supplier

---

### 18. CLIENT (External)

| Attribute | Value |
|-----------|-------|
| **Role Name** | `client` |
| **Purpose** | External client/customer portal access |
| **Scope** | Client portal only |

**Permissions:**
- View own tickets
- Create tickets
- Reply to tickets

**Typical Users:**
- `client@ogamierp.local` - XYZ Corp

---

## Department Positions

### Position Codes Reference

Positions define job titles within departments. They are used for:
- Employee records
- Salary grade assignment
- Organizational hierarchy

| Code | Title | Department | Salary Grade |
|------|-------|------------|--------------|
| **HR** ||||
| HR-MGR | HR Manager | HR | SG-10 |
| HR-SUP | HR Supervisor | HR | SG-07 |
| HR-ASST | HR Assistant | HR | SG-05 |
| **IT** ||||
| IT-ADMIN | IT Administrator | IT | SG-10 |
| IT-SUPP | IT Support | IT | SG-06 |
| **ACCTG** ||||
| ACCT-MGR | Accounting Manager | ACCTG | SG-11 |
| ACCT-OFF | Accounting Officer | ACCTG | SG-10 |
| ACCT-CLK | Accounting Clerk | ACCTG | SG-06 |
| ACCT-ANL | Financial Analyst | ACCTG | SG-08 |
| GA-OFF | General Administration Officer | HR | SG-10 |
| PURCH-OFF | Purchasing Officer | ACCTG | SG-10 |
| IMPEX-OFF | Import/Export Officer | ACCTG | SG-10 |
| **PROD** ||||
| PROD-MGR | Production Manager | PROD | SG-12 |
| PROD-SUP | Production Supervisor | PROD | SG-08 |
| PROD-OP | Production Operator | PROD | SG-05 |
| PROD-HEAD | Production Head | PROD | SG-09 |
| PROC-HEAD | Processing Head | PROD | SG-09 |
| **SALES** ||||
| SALES-MGR | Sales Manager | SALES | SG-11 |
| SALES-REP | Sales Representative | SALES | SG-07 |
| **EXEC** ||||
| CHAIRMAN | Chairman | EXEC | SG-15 |
| PRESIDENT | President | EXEC | SG-15 |
| VP | Vice President | EXEC | SG-14 |
| **PLANT** ||||
| PLANT-MGR | Plant Manager | PLANT | SG-13 |
| **QC** ||||
| QC-MGR | QC/QA Manager | QC | SG-12 |
| QC-HEAD | QC/QA Head | QC | SG-09 |
| QC-STAFF | QC Inspector | QC | SG-05 |
| **MOLD** ||||
| MOLD-MGR | Mold Manager | MOLD | SG-12 |
| MOLD-HEAD | Mold Head | MOLD | SG-09 |
| MOLD-TECH | Mold Technician | MOLD | SG-06 |
| **WH** ||||
| WH-HEAD | Warehouse Head | WH | SG-08 |
| WH-STAFF | Warehouse Staff | WH | SG-05 |
| **PPC** ||||
| PPC-HEAD | PPC Head | PPC | SG-09 |
| PPC-STAFF | PPC Staff | PPC | SG-05 |
| **MAINT** ||||
| MAINT-HEAD | Maintenance Head | MAINT | SG-09 |
| MAINT-TECH | Maintenance Technician | MAINT | SG-06 |
| **ISO** ||||
| ISO-HEAD | Management System Head | ISO | SG-09 |
| ISO-STAFF | Management System Staff | ISO | SG-05 |

---

## Role-to-Position Mapping

### Recommended Role Assignments by Position

| Position | Recommended Role(s) |
|----------|---------------------|
| HR-MGR | `manager` |
| HR-SUP, HR-ASST | `ga_officer` or `head` or `staff` |
| IT-ADMIN | `admin` (system) or `ga_officer` |
| ACCT-MGR | `officer` |
| ACCT-OFF, ACCT-ANL | `officer` |
| ACCT-CLK | `staff` |
| GA-OFF | `ga_officer` |
| PURCH-OFF | `purchasing_officer` |
| IMPEX-OFF | `impex_officer` |
| PROD-MGR | `production_manager` |
| PROD-HEAD, PROC-HEAD | `head` |
| PROD-SUP, PROD-OP | `staff` |
| SALES-MGR | `crm_manager` |
| SALES-REP | `staff` |
| PRESIDENT, CHAIRMAN | `executive` |
| VP | `vice_president` |
| PLANT-MGR | `plant_manager` |
| QC-MGR | `qc_manager` |
| QC-HEAD | `head` |
| QC-STAFF | `staff` |
| MOLD-MGR | `mold_manager` |
| MOLD-HEAD | `head` |
| MOLD-TECH | `staff` |
| WH-HEAD | `warehouse_head` or `head` |
| WH-STAFF | `staff` |
| PPC-HEAD | `ppc_head` or `head` |
| MAINT-HEAD | `head` |
| MAINT-TECH | `staff` |
| ISO-HEAD | `head` |
| ISO-STAFF | `staff` |

---

## SoD (Segregation of Duties) Rules

### SoD Rules Summary

| Rule | Description | Enforced Via |
|------|-------------|--------------|
| **SOD-001** | Employee creator cannot activate | `employees.activate` permission |
| **SOD-002** | Leave submitter cannot approve | `leaves.approve` permission + useSodCheck |
| **SOD-003** | OT submitter cannot approve | `overtime.approve` permission + useSodCheck |
| **SOD-004** | Loan requester cannot HR approve | `loans.hr_approve` permission |
| **SOD-005** | Payroll preparer cannot HR approve | `payroll.hr_approve` permission |
| **SOD-006** | Same user cannot prepare and approve payroll | Workflow states |
| **SOD-007** | HR Manager cannot accounting-approve payroll | `payroll.acctg_approve` permission |
| **SOD-008** | JE creator cannot post | `journal_entries.post` permission + useSodCheck |
| **SOD-009** | AP invoice creator cannot approve | `vendor_invoices.approve` permission + useSodCheck |
| **SOD-010** | AR invoice creator cannot approve | `customer_invoices.approve` permission + useSodCheck |
| **SOD-011** | Head cannot note own loan | `loans.head_note` permission |
| **SOD-012** | Manager cannot check own loan | `loans.manager_check` permission |
| **SOD-013** | Officer cannot review own loan | `loans.officer_review` permission |
| **SOD-014** | VP cannot approve own loan | `loans.vp_approve` permission |

### SoD Enforcement in Frontend

```tsx
// Using useSodCheck hook
const { isBlocked, reason } = useSodCheck(record.created_by_id);

// Using SodActionButton component
<SodActionButton
  initiatedById={record.created_by_id}
  label="Approve"
  onClick={handleApprove}
  disabled={isLoading}
/>
```

When `isBlocked` is true:
- Button shows "Action (SoD)" in disabled state
- Tooltip: "Segregation of Duties violation: you initiated this record and cannot approve it. A different user must perform the approval."
- Opacity reduced to 60%

---

## Quick Reference: Test Accounts by Role

| Role | Test Account Email | Password | Employee Code |
|------|-------------------|----------|---------------|
| admin | admin@ogamierp.local | Admin@1234567890! | — |
| super_admin | superadmin@ogamierp.local | SuperAdmin@12345! | — |
| executive | executive@ogamierp.local | Executive@Test1234! | EMP-EXEC-001 |
| vice_president | vp@ogamierp.local | Vice_president@Test1234! | EMP-EXEC-002 |
| manager | hr.manager@ogamierp.local | Manager@Test1234! | EMP-HR-001 |
| plant_manager | plant.manager@ogamierp.local | Plant_manager@Test1234! | EMP-PLANT-001 |
| production_manager | prod.manager@ogamierp.local | Production_manager@Test1234! | EMP-PROD-001 |
| qc_manager | qc.manager@ogamierp.local | Qc_manager@Test1234! | EMP-QC-001 |
| mold_manager | mold.manager@ogamierp.local | Mold_manager@Test1234! | EMP-MOLD-001 |
| officer | acctg.officer@ogamierp.local | Officer@Test1234! | EMP-ACCT-002 |
| officer | acctg.manager@ogamierp.local | Manager@12345! | EMP-ACCT-001 |
| ga_officer | ga.officer@ogamierp.local | Ga_officer@Test1234! | EMP-HR-002 |
| head | hr.head@ogamierp.local | Head@Test1234! | EMP-HR-003 |
| head | acctg.head@ogamierp.local | Head@Test1234! | EMP-ACCT-003 |
| head | prod.head@ogamierp.local | Head@Test1234! | EMP-PROD-002 |
| head | qc.head@ogamierp.local | Head@Test1234! | EMP-QC-002 |
| head | mold.head@ogamierp.local | Head@Test1234! | EMP-MOLD-002 |
| head | plant.head@ogamierp.local | Head@Test1234! | EMP-PLANT-002 |
| staff | hr.staff@ogamierp.local | Staff@Test1234! | EMP-HR-004 |
| staff | acctg.staff@ogamierp.local | Staff@Test1234! | EMP-ACCT-004 |
| crm_manager | crm.manager@ogamierp.local | CrmManager@12345! | EMP-SALES-001 |
| vendor | vendor@ogamierp.local | Vendor@Test1234! | — |
| client | client@ogamierp.local | Client@Test1234! | — |

---

*Document Version: 1.0*  
*Last Updated: 2026-03-16*  
*Total System Roles: 18*  
*Total Department Positions: 40+*
