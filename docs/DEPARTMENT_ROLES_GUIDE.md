# Department Roles Guide — Ogami ERP

> **Last updated:** 2026-03-23
> Authoritative source: `database/seeders/RolePermissionSeeder.php` and `app/Infrastructure/Middleware/ModuleAccessMiddleware.php`

---

## How Roles Work

Ogami ERP uses a **dual-layer RBAC** system:

1. **Role** — defines *what actions* a user can perform (create, approve, view, etc.)
2. **Department** — controls *which modules* the user can access

The same role (e.g. `manager`) assigned to different departments produces completely different effective access. A `manager` in HR manages employees and payroll. A `manager` in Accounting manages journals and invoices. Same role, different domain.

```
Role  +  Department  =  Effective Access
```

---

## System-Level Roles (no department required)

### `super_admin`
**Who:** IT system owner / developer
**Access:** Every permission in the system. Bypasses all middleware, policies, and SoD checks.
**Use:** Testing, emergency fixes, initial setup. Should never be used in day-to-day operations.

---

### `admin`
**Who:** IT Administrator or System Manager
**Access:** `system.*` permissions only — user management, role assignment, system settings, audit logs, reference tables, backups. Zero access to business data (no HR, no payroll, no accounting, no procurement).
**Use:** Onboarding users, resetting passwords, configuring system settings, assigning departments to new accounts. Can access the Vendors and Customers pages specifically to provision vendor/client portal accounts.

**Sidebar:** Administration section only (Users, System Settings, Reference Tables, Audit Logs, Backup).

---

## Executive-Level Roles (EXEC department)

### `executive`
**Who:** Chairman, President, Board Director
**Access:** Read-only across virtually all modules — employees, payroll runs, GL, AP/AR, budget, fixed assets, inventory, production orders, QC, maintenance. Can approve leave and overtime at the executive level (`overtime.executive_approve`, `leaves.executive_approve`). Cannot create, edit, or approve financial transactions.

**Sidebar:** Executive section (Pending Approvals, financial reports).
**Landing page:** `/approvals/pending`
**Cannot:** Create anything, approve PRs/payroll/loans, post journal entries.

---

### `vice_president`
**Who:** Vice President of Operations / Finance
**Access:** Final approver for the entire organization. Has read access equivalent to executive plus full approval rights:

| Module | VP Action |
|---|---|
| Purchase Requests | Final VP approval (`budget_verified → approved`) |
| Payroll | VP approval step (`ACCTG_APPROVED → VP_APPROVED`) |
| Loans | VP final approval (SoD-014 — exclusive to VP) |
| Material Requisitions | VP approval step |
| Vendor & Customer Invoices | Approval rights |
| Budget | Can approve budget requests |
| Sales / Client Orders | VP-level order approval |

**Sidebar:** Executive section (Pending Approvals + Reports: Trial Balance, AP/AR Aging, Budget vs Actual).
**Landing page:** `/approvals/pending`
**Cannot:** Create PRs, initiate payroll, create employees, post journal entries, manage inventory.
**SoD note:** VP is the *only* role with `loans.vp_approve` and `payroll.vp_approve`. No other role can perform the final approval step.

---

## Operational Roles

The roles below (`manager`, `officer`, `head`, `staff`) work together with a department assignment. The **department** controls which module APIs are reachable. The role controls what the user can do inside those modules.

---

### `manager`

Managers have **full access** within their assigned department's modules. They are the highest operational authority — they can approve what officers process and manage what heads supervise.

| Department | Title | Key Responsibilities |
|---|---|---|
| **HR** (`HR`) | HR Manager | Full employee lifecycle (create → activate → terminate), payroll HR-approval step, leave/loan configuration, salary grade management, government reports (BIR, SSS, PhilHealth, PagIBIG) |
| **Accounting** (`ACCTG`) | Accounting Manager | Full GL/AP/AR, payroll accounting-approval step, post journal entries, bank reconciliation certification, budget management, fixed assets management |
| **Production** (`PROD`) | Plant Manager | Work order creation/release/complete, BOM management, full MRQ workflow, QC management, maintenance management |
| **Warehouse** (`WH`) | Warehouse Manager | Full inventory (items, stock, adjustments, locations), MRQ fulfillment, goods receipt confirmation, delivery management |
| **Purchasing** (`PURCH`) | Purchasing Manager | Full procurement (PR → PO → GR), vendor management, MRQ creation, Vendor RFQs |
| **Sales** (`SALES`) | Sales Manager | Client order management/approval/negotiation, CRM, customer invoices, AR |
| **QC** (`QC`) | QC Manager | Inspections, NCRs, CAPA, QC templates, close NCRs |
| **Maintenance** (`MAINT`) | Maintenance Manager | Equipment registry, maintenance work orders |

**Key trait:** Managers hold the critical approval permissions (`hr_approve`, `acctg_approve` for payroll; `post` for journal entries; `fulfill` for MRQs; `confirm` for GRs) that officers do not.

---

### `officer`

Officers perform day-to-day operations — they create and process records but cannot give final approval. They hand off to a manager or VP for the approval step.

| Department | Title | Key Responsibilities |
|---|---|---|
| **Accounting** (`ACCTG`) | Accounting Officer | Creates journal entries (submit, not post), creates/submits AP invoices, processes AR invoices, bank entries, performs budget verification on PRs (`procurement.purchase-request.budget-check`) |
| **Purchasing** (`PURCH`) | Purchasing Officer | Technically reviews PRs (`procurement.purchase-request.review`), creates POs, creates GRs, manages vendors, creates Vendor RFQs |
| **HR** (`HR`) | HR Officer | Employee records view, processes leave and attendance, loan review step, generates payroll reports |
| **Sales** (`SALES`) | Sales Officer | Processes client orders, manages CRM tickets, creates customer invoices |
| **Any dept** | Dept Officer | Team management for own department: approves leave/OT for their team, views team attendance, supervises loans |

**Key trait:** Officers can create and process but not finalize. They also hold team management permissions for their department (approve leave/OT of subordinates — SoD prevents approving their own).

---

### `head`

Heads are team supervisors. They do first-level approvals within their team and handle limited operational tasks.

| Department | Title | Key Responsibilities |
|---|---|---|
| **Production** (`PROD`) | Production Head | Creates/releases work orders, creates MRQs, creates PRs (dept-scoped only via `create-dept`), views QC/maintenance. **Cannot** start WOs (manager) or note MRQs from other depts (SoD) |
| **Warehouse** (`WH`) | Warehouse Head | Notes MRQs (first step of fulfillment workflow), views inventory, creates delivery receipts. **Cannot** fulfill MRQs (warehouse manager only) |
| **Purchasing** (`PURCH`) | Purchasing Head | PR creation, team supervision, similar to purchasing officer with head-level team approval |
| **QC** (`QC`) | QC Head | Creates inspections and NCRs, manages ISO documents and audit records |
| **Maintenance** (`MAINT`) | Maintenance Head | Manages equipment registry and maintenance work orders |
| **Sales** (`SALES`) | Sales Head | Reviews client orders (cannot approve/negotiate — manager/VP scope), manages CRM tickets |
| **HR** (`HR`) | HR Head | Team supervision, leave/loan first-level notes, works under HR Manager |
| **Any dept** | Dept Head | Approves own team's leave and overtime (SoD — cannot approve own requests); provides first-level loan notes |

**Key trait:** The `procurement.purchase-request.create-dept` permission means heads can only create PRs for their own department. Officers with the full `procurement.purchase-request.create` can create PRs for any department.

---

### `staff`

Staff is the most restricted operational role. No approval rights. No create permissions for primary business records. Self-service and production logging only.

| Department | Title | Key Responsibilities |
|---|---|---|
| **Production** (`PROD`) | Production Staff | View work orders, log production output (`production.orders.log_output`), create/view QC inspections and NCRs, view inventory and mold data |
| **Warehouse** (`WH`) | Warehouse Staff | View inventory and stock, view/create QC inspections, view deliveries |
| **Any dept** | Staff | File own leave, apply for loan, view own payslip, submit own overtime, view own attendance |

**Cannot:** Create PRs, MRQs, or work orders. Cannot approve anything. Cannot view other employees' records.

---

## Portal Roles (external users)

### `vendor`
**Who:** External supplier company
**Access:** Vendor Portal only (`/vendor-portal/*`) — view POs assigned to them, update fulfillment status, manage their product catalog, view goods receipts, submit invoices.
**Cannot:** Access any internal ERP pages.

---

### `client`
**Who:** External customer/buyer
**Access:** Client Portal only (`/client-portal/*`) — browse catalog, place orders, view order status, create and view support tickets.
**Cannot:** Access any internal ERP pages.

---

## Department-Module Access Matrix

Which departments can reach which modules (enforced by `ModuleAccessMiddleware`).
VP, Executive, Admin, and Super Admin bypass this check entirely.

| Module | Allowed Departments |
|---|---|
| HR / Employees | HR, PURCH, PROD, PLANT, WH, QC, MAINT, SALES, ACCTG, IT |
| Attendance / Leave / Overtime | HR, PURCH, PROD, PLANT, WH, QC, MAINT, SALES, ACCTG, IT |
| Payroll | HR, ACCTG |
| Loans | HR, ACCTG |
| Accounting / GL / Tax / Banking | ACCTG |
| Budget | ACCTG, EXEC |
| Fixed Assets | ACCTG |
| AP — Vendors & Invoices | ACCTG, PURCH |
| AR — Customers & Invoices | SALES, ACCTG, PURCH |
| Procurement (all) | PURCH, PROD, PLANT, ACCTG, WH |
| Purchase Orders / Vendor RFQs | PURCH only |
| Goods Receipts | PURCH, WH |
| Inventory (items / stock) | WH, PURCH, PROD, PLANT, SALES |
| MRQ / Requisitions | WH, PURCH, PROD |
| Stock Adjustments / Locations | WH only |
| Production / Work Orders / BOMs | PROD, PLANT, PPC |
| QC / Inspections | QC, PROD, WH |
| NCR / CAPA | QC only |
| Maintenance / Equipment | MAINT, PROD, PLANT |
| Mold | MOLD, PROD |
| Delivery / Shipments | WH, SALES, PROD, PLANT |
| ISO / IATF | ISO, QC |
| CRM / Tickets | SALES |
| Administration | IT, EXEC |
| Approvals Dashboard | EXEC, VP |
| Reports (gov / financial) | HR, ACCTG, EXEC |

---

## Segregation of Duties (SoD) Reference

| Rule | What it prevents |
|---|---|
| SOD-001 | Employee creator ≠ activator |
| SOD-002 | Leave requester ≠ approver |
| SOD-003 | OT requester ≠ approver |
| SOD-004 | Loan applicant ≠ HR approver |
| SOD-005/006 | HR prepares payroll, Accounting approves — never the same person |
| SOD-007 | Payroll accounting approver ≠ HR who prepared it |
| SOD-008 | Journal entry creator ≠ poster |
| SOD-009 | AP invoice creator ≠ approver |
| SOD-010 | AR invoice creator ≠ approver |
| SOD-011 | Loan applicant ≠ head who notes it |
| SOD-012 | Loan head noter ≠ manager checker |
| SOD-013 | Loan manager checker ≠ officer reviewer |
| SOD-014 | `loans.vp_approve` and `payroll.vp_approve` are VP-exclusive — no other role holds them |
| PR SoD | PR submitter ≠ VP approver (enforced in `PurchaseRequestPolicy`) |
| MRQ SoD | MRQ creator ≠ MRQ noter (enforced in `MaterialRequisitionPolicy`) |

---

## Test Accounts Reference

Created by `TestAccountsSeeder`. Run after `RolePermissionSeeder` and `DepartmentPositionSeeder`.

| Email | Password | Role | Department | Name |
|---|---|---|---|---|
| `admin@ogamierp.local` | `Admin@1234567890!` | admin | — | System Administrator *(created by RolePermissionSeeder)* |
| `executive@ogamierp.local` | `Executive@Test1234!` | executive | EXEC | Roberto Reyes |
| `vp@ogamierp.local` | `Vice_president@Test1234!` | vice_president | EXEC | Elena Cruz |
| `hr.manager@ogamierp.local` | `Manager@Test1234!` | manager | HR | Maria Santos |
| `plant.manager@ogamierp.local` | `Manager@Test1234!` | manager | PLANT | Carlos Rivera |
| `prod.manager@ogamierp.local` | `Manager@Test1234!` | manager | PROD | Jose Garcia |
| `qc.manager@ogamierp.local` | `Manager@Test1234!` | manager | QC | Linda Tan |
| `mold.manager@ogamierp.local` | `Manager@Test1234!` | manager | MOLD | Ramon Aquino |
| `sales.manager@ogamierp.local` | `Manager@Test1234!` | manager | SALES | Diana Cruz |
| `acctg.officer@ogamierp.local` | `Officer@Test1234!` | officer | ACCTG | Anna Marie Lim |
| `ga.officer@ogamierp.local` | `Officer@Test1234!` | officer | HR | Grace Mendoza |
| `purchasing.officer@ogamierp.local` | `Officer@Test1234!` | officer | PURCH | Mark Villanueva |
| `impex.officer@ogamierp.local` | `Officer@Test1234!` | officer | SALES | Diana Ramos |
| `dept.head@ogamierp.local` | `Head@Test1234!` | head | PROD | Ricardo Bautista |
| `warehouse.head@ogamierp.local` | `Head@Test1234!` | head | WH | Ernesto Bautista |
| `ppc.head@ogamierp.local` | `Head@Test1234!` | head | PPC | Jerome Florido |
| `staff@ogamierp.local` | `Staff@Test1234!` | staff | PROD | Juan dela Cruz |
| `hr.staff@ogamierp.local` | `Staff@Test1234!` | staff | HR | Juan Dela Cruz |
| `vendor@ogamierp.local` | `Vendor@Test1234!` | vendor | — | Vendor User (ABC Supplier) |
| `client@ogamierp.local` | `Client@Test1234!` | client | — | Client User (XYZ Corp) |

> Password pattern: `{Ucfirst(role_name)}@Test1234!`
> Example: role `head` → `Head@Test1234!` · role `vice_president` → `Vice_president@Test1234!`
>
> Vendor and client accounts have `must_change_password = true` on first login.

---

## Related Documents

| File | Purpose |
|---|---|
| `docs/RBAC_V2_GUIDE.md` | Technical RBAC implementation details |
| `docs/SOD_AUDIT_REPORT.md` | Full SoD audit findings |
| `docs/MANUAL_TESTING_GUIDE.md` | Manual testing steps per role |
| `database/seeders/RolePermissionSeeder.php` | Authoritative permission assignments |
| `app/Infrastructure/Middleware/ModuleAccessMiddleware.php` | Dept-to-module access map |
| `app/Services/DepartmentModuleService.php` | Runtime permission resolution for dept users |
