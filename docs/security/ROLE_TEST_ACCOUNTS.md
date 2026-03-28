# Ogami ERP — Role-Based Test Accounts

**Generated:** 2026-03-10  
**Based on:** `company_operational_flow.md` × `RolePermissionSeeder` × `ManufacturingEmployeeSeeder`

All accounts below are seeded by the standard `php artisan migrate:fresh --seed` command.  
To re-seed without wiping data: `php artisan db:seed --class=ManufacturingEmployeeSeeder`

---

## Quick Reference — All Credentials

| Position | Email | Password | Role | Dept | Emp Code |
|---|---|---|---|---|---|
| System Administrator | `admin@ogamierp.local` | `Admin@1234567890!` | `admin` | — | — |
| Super Admin (all-access) | `superadmin@ogamierp.local` | `SuperAdmin@12345!` | `super_admin` | — | — |
| Chairman | `chairman@ogamierp.local` | `Executive@12345!` | `executive` | EXEC | EMP-2026-0006 |
| President | `president@ogamierp.local` | `Executive@12345!` | `executive` | EXEC | EMP-2026-0007 |
| Vice President | `vp@ogamierp.local` | `VicePresident@1!` | `vice_president` | EXEC + all | EMP-2026-0008 |
| HR Manager | `hr.manager@ogamierp.local` | `HrManager@1234!` | `manager` | HR | EMP-2026-0001 |
| Plant Manager | `plant.manager@ogamierp.local` | `Manager@12345!` | `plant_manager` | PLANT + 6 | EMP-2026-0009 |
| Production Manager | `prod.manager@ogamierp.local` | `Manager@12345!` | `production_manager` | PROD | EMP-2026-0010 |
| QC/QA Manager | `qc.manager@ogamierp.local` | `Manager@12345!` | `qc_manager` | QC | EMP-2026-0011 |
| Mold Manager | `mold.manager@ogamierp.local` | `Manager@12345!` | `mold_manager` | MOLD | EMP-2026-0012 |
| Accounting Officer | `acctg.officer@ogamierp.local` | `AcctgManager@1234!` | `officer` | ACCTG | EMP-2026-0003 |
| GA Officer | `ga.officer@ogamierp.local` | `Officer@12345!` | `ga_officer` | HR | EMP-2026-0013 |
| Purchasing Officer | `purchasing.officer@ogamierp.local` | `Officer@12345!` | `purchasing_officer` | ACCTG | EMP-2026-0014 |
| ImpEx Officer | `impex.officer@ogamierp.local` | `Officer@12345!` | `impex_officer` | ACCTG | EMP-2026-0015 |
| Warehouse Head | `warehouse.head@ogamierp.local` | `Head@123456789!` | `head` | WH | EMP-2026-0016 |
| PPC Head | `ppc.head@ogamierp.local` | `Head@123456789!` | `head` | PPC | EMP-2026-0017 |
| Maintenance Head | `maintenance.head@ogamierp.local` | `Head@123456789!` | `head` | MAINT | EMP-2026-0018 |
| Production Head | `production.head@ogamierp.local` | `Head@123456789!` | `head` | PROD | EMP-2026-0019 |
| Processing Head | `processing.head@ogamierp.local` | `Head@123456789!` | `head` | PROD | EMP-2026-0020 |
| QC/QA Head | `qcqa.head@ogamierp.local` | `Head@123456789!` | `head` | QC | EMP-2026-0021 |
| Mgmt System Head | `iso.head@ogamierp.local` | `Head@123456789!` | `head` | ISO | EMP-2026-0022 |
| Production Operator | `prod.staff@ogamierp.local` | `Staff@123456789!` | `staff` | PROD | EMP-2026-0023 |

> **Notes:**
> - VP (`vp@ogamierp.local`) has `user_department_access` entries for **all 13 departments** so the approvals queue shows cross-department requests. The `vice_president` role is scoped by middleware — unlike `executive`, it is NOT automatically exempt.
> - Plant Manager has dept access for: PLANT, PROD, QC, MOLD, WH, PPC, MAINT, ISO.
> - `super_admin` bypasses all SoD checks, dept-scope, and gates — use only to test raw data without workflow constraints.

---

## Detailed Role Profiles

---

### System Roles

#### `admin` — System Administrator
**Credentials:** `admin@ogamierp.local` / `Admin@1234567890!`  
**Company Position:** IT Administrator (system custodian)

**Responsibility:** Manages user accounts, assigns roles, configures system settings, and monitors system health. Has **zero access to business data** — cannot see employees, payroll, invoices, etc.

**Module Access:**

| Module | Access |
|---|---|
| User Management | ✅ Full (create, edit, unlock, delete accounts) |
| Role Assignment | ✅ Assign roles to users |
| Department Assignment | ✅ Assign users to departments |
| System Settings | ✅ Edit global settings |
| Rate Tables | ✅ SSS, PhilHealth, Pag-IBIG, TRAIN tax tables |
| Holiday Calendar | ✅ Manage public holidays |
| EWT/ATC Codes | ✅ BIR withholding tax codes |
| Fiscal Period | ✅ Reopen closed periods |
| Audit Log | ✅ View all system activity |
| Horizon / Pulse | ✅ Queue + metrics monitoring |
| Backups | ✅ Trigger and manage backups |
| **All other modules** | ❌ No access |

**Test Scenarios:** Creating new user accounts, assigning roles, unlocking locked accounts.

---

#### `super_admin` — Super Administrator
**Credentials:** `superadmin@ogamierp.local` / `SuperAdmin@12345!`  
**Company Position:** N/A (testing account only)

**Responsibility:** Testing superuser. Has ALL permissions, bypasses Gate checks, SoD enforcement, and department scoping.

**Use only for:** Initial smoke testing of any module without worrying about workflow state. **Do not use to validate SoD or role-based restrictions.**

---

### Executive Management

#### `executive` — Chairman / President
**Credentials:**
- Chairman: `chairman@ogamierp.local` / `Executive@12345!` (Roberto Ogami)
- President: `president@ogamierp.local` / `Executive@12345!` (Eduardo Ogami)

**Company Responsibilities:**
- Chairman: Supervises the overall status and direction of the company
- President: Maintains good relationships with customers and company owners

**Module Access (read-only observer role):**

| Module | Access |
|---|---|
| Employees | ✅ View list |
| Attendance | ✅ View team summary |
| Overtime | ✅ View + Executive approve |
| Leave | ✅ View own + team |
| Loans | ✅ View own + dept |
| Payroll | ✅ View pay runs + own payslip |
| GL / Journal Entries | ✅ View |
| Chart of Accounts | ✅ View |
| AP (Vendors / Invoices) | ✅ View |
| AR (Customers / Invoices) | ✅ View |
| Reports | ✅ Financial statements, GL, Trial Balance, AP Aging, Bank Reconciliation |
| **Write / approve anything** | ❌ No |

**Test Scenarios:** Viewing financial reports, viewing payroll status, executive overtime approval.

---

#### `vice_president` — Vice President
**Credentials:** `vp@ogamierp.local` / `VicePresident@1!` (Lorenzo Ogami)

**Company Responsibility:** Responsible for acquiring new projects from customers. Final approver for all financial requests — purchases, payments, loans, allowances.

**Approval Chain Position:** Step 5 (final) of the request approval workflow:
> Staff → Head (noted) → Manager (checked) → Officer (reviewed) → **VP (approves)**

**Module Access:**

| Module | Access |
|---|---|
| Approvals Queue | ✅ View + approve all pending VP approvals |
| Loans | ✅ VP final approval (`loans.vp_approve`) |
| Inventory MRQ | ✅ VP final approval (`inventory.mrq.vp_approve`) |
| Purchase Requests | ✅ View (final approval context) |
| Purchase Orders | ✅ View |
| Goods Receipts | ✅ View |
| Production / BOM / Delivery Schedule | ✅ View |
| Employees | ✅ View |
| Payroll | ✅ View runs + own payslip |
| GL / AP / AR / Invoices | ✅ View |
| Financial Reports | ✅ Financial statements, trial balance, GL, AP/AR aging |
| Leave | ✅ View own + team, file own, VP note |
| **Write / create accounting entries** | ❌ No |

**Dept Scope:** `user_department_access` covers all 13 departments — cross-department approval visibility is enabled.

**Test Scenarios:**
1. Approve a loan after it has been noted by Head → checked by Manager → reviewed by Officer
2. Approve an MRQ that reached VP stage
3. View the approvals queue showing pending items from all departments

---

### Department Managers

#### `manager` — HR Manager
**Credentials:** `hr.manager@ogamierp.local` / `HrManager@1234!` (Maria Santos)

**Company Responsibility:** Manages Human Resources and administrative functions.

**Module Access:**

| Module | Access |
|---|---|
| Employees | ✅ Full (create, update, activate, suspend, terminate, salary update) |
| Attendance | ✅ Full (view team, import CSV, manage shifts, resolve anomalies, lock/unlock) |
| Overtime | ✅ Full (view, approve, reject, supervise) |
| Leave | ✅ Full (view team, file on behalf, adjust balance, configure types, approve, reject, SIL monetization) |
| Loans | ✅ HR approve, manager check, configure loan types |
| Payroll | ✅ Full payroll cycle (initiate, compute, HR approve, publish, disburse, government reports) |
| Inventory | ✅ View items/stock/locations + MRQ check (checked by in approval chain) |
| Procurement (PRs) | ✅ View + create + check (checked by) |
| Production / QC / Maintenance / Mold / Delivery / ISO | ❌ No access (handled by plant_manager, production_manager, qc_manager, mold_manager) |
| BIR Reports | ✅ 2316, Alphalist, 1601C, SSS, PhilHealth, Pag-IBIG |
| **Accounting / GL / AP / AR** | ❌ No financial entries |

**Approval Chain Position:** Step 3 (checked by) for loans, leave, and purchase requests.

**Test Scenarios:**
1. Create a new employee → link to user account
2. Run payroll: initiate → compute → HR approve → submit to accounting
3. Approve or reject a leave request
4. Perform an HR check on a loan application

---

#### `plant_manager` — Plant Manager
**Credentials:** `plant.manager@ogamierp.local` / `Manager@12345!` (Carlos Reyes)

**Company Responsibility:** Oversees all plant operations and activities. Supervises Production, QC, Maintenance, Mold, Delivery, and ISO teams.

**Module Access:**

| Module | Access |
|---|---|
| Production (BOM, Orders, Delivery Schedule) | ✅ Full |
| QC / QA (Templates, Inspections, NCR) | ✅ Full |
| Maintenance (Equipment, Work Orders, PM) | ✅ Full |
| Mold (Master, Shot Logs) | ✅ Full |
| Delivery / Logistics (Shipments, Receipts, Vehicles) | ✅ Full |
| ISO (Documents, Audits, Findings) | ✅ Full |
| Inventory | ✅ View items, stock, locations, MRQ |
| Self-service (attendance, leave, loans, payslip) | ✅ |
| Leave (team view) | ✅ View team + manager check |
| **HR / Payroll / Accounting / Procurement** | ❌ No access |

**Dept Scope:** PLANT (primary) + PROD, QC, MOLD, WH, PPC, MAINT, ISO (secondary via multi-dept access).

**Test Scenarios:**
1. Create a production order and release it
2. Log a QC inspection and raise an NCR
3. Schedule preventive maintenance for a machine
4. Log mold shots for a mold master record

---

#### `production_manager` — Production Manager
**Credentials:** `prod.manager@ogamierp.local` / `Manager@12345!` (Renaldo Mendoza)

**Company Responsibility:** Supervises overall production activities.

**Module Access:**

| Module | Access |
|---|---|
| Production (BOM, Orders, Delivery Schedule) | ✅ Full |
| Inventory | ✅ View (items, stock, locations, MRQ) |
| Self-service | ✅ |
| **QC / Maintenance / Mold / Delivery / ISO** | ❌ No access |

**Test Scenarios:**
1. Manage BOM (Bill of Materials) entries
2. Create a production order and track output completion
3. Update delivery schedules for production batches

---

#### `qc_manager` — QC/QA Manager
**Credentials:** `qc.manager@ogamierp.local` / `Manager@12345!` (Josephine Villanueva)

**Company Responsibility:** Manages quality control and quality assurance operations.

**Module Access:**

| Module | Access |
|---|---|
| QC / QA (Templates, Inspections, NCR, CAPA) | ✅ Full |
| Inventory | ✅ View items + stock |
| Self-service | ✅ |
| **Production / Maintenance / Mold / Delivery / ISO** | ❌ No access |

**Test Scenarios:**
1. Create and manage inspection templates
2. Record a QC inspection result
3. Raise and close a Non-Conformance Report (NCR)

---

#### `mold_manager` — Mold Manager
**Credentials:** `mold.manager@ogamierp.local` / `Manager@12345!` (Victor Castillo)

**Company Responsibility:** Oversees the mold department and related operations.

**Module Access:**

| Module | Access |
|---|---|
| Mold (Mold Master, Shot Logs) | ✅ Full |
| Inventory | ✅ View items + stock |
| Self-service | ✅ |
| **Production / QC / Maintenance / Delivery / ISO** | ❌ No access |

**Test Scenarios:**
1. Register a new mold master record
2. Log shot counts for a mold
3. Track mold maintenance intervals

---

### Officers

#### `officer` — Accounting Officer
**Credentials:** `acctg.officer@ogamierp.local` / `AcctgManager@1234!` (Anna Marie Lim)

**Company Responsibility:** Handles all financial management of the company.

**Approval Chain Position:** Step 4 (reviewed by) for loans, purchase requests, and MRQs. Also approves and posts payroll.

**Module Access:**

| Module | Access |
|---|---|
| GL / Journal Entries | ✅ Full (create, submit, post, reverse, export) |
| Chart of Accounts | ✅ Full |
| Fiscal Periods | ✅ Manage |
| AP — Vendors | ✅ Full (manage, accredit, suspend) |
| AP — Vendor Invoices | ✅ Full (create, approve, reject, record payment, cancel, export) |
| AP — Vendor Payments | ✅ View + create |
| AP — BIR 2307 | ✅ Generate |
| AR — Customers | ✅ Full (manage, archive) |
| AR — Customer Invoices | ✅ Full (create, approve, receive payment, write off, apply payment) |
| Banking (Accounts + Reconciliations) | ✅ Full |
| Financial Reports | ✅ Financial statements, GL, Trial Balance, AP/AR Aging, VAT, Bank Reconciliation |
| Payroll | ✅ Accounting approve, reject, disburse, post, publish, bank file, register download |
| Loans | ✅ Accounting approve (v1) + officer review (v2 Step 3) |
| Procurement | ✅ Review PRs + budget-check, manage POs |
| Inventory MRQ | ✅ Review (Step 4) |
| **HR / Attendance / Leave management** | ❌ No people management |

**Test Scenarios:**
1. Post a journal entry and reverse it
2. Approve a vendor invoice and record payment
3. Perform accounting approval on a payroll run
4. Review a loan application at the officer stage

---

#### `ga_officer` — General Administration Officer
**Credentials:** `ga.officer@ogamierp.local` / `Officer@12345!` (Rachel Garcia)

**Company Responsibility:** Supports HR and administrative operations.

**Module Access:**

| Module | Access |
|---|---|
| Employees | ✅ View team, upload/download documents |
| Attendance | ✅ View team, import CSV, manage shifts, resolve anomalies |
| Overtime | ✅ View + supervise |
| Leave | ✅ View team, file on behalf, GA process (Step 3 leave workflow) |
| Self-service | ✅ Payslip, own leave, loans apply |
| **Payroll / Accounting / Procurement** | ❌ No financial access |

**Test Scenarios:**
1. Import attendance CSV for a team
2. Process a leave request at the GA stage (after head and manager approval)
3. File a leave on behalf of an employee

---

#### `purchasing_officer` — Purchasing Officer
**Credentials:** `purchasing.officer@ogamierp.local` / `Officer@12345!` (Marlon Torres)

**Company Responsibility:** Responsible for ordering all materials required by the company.

**Module Access:**

| Module | Access |
|---|---|
| Procurement — Purchase Requests | ✅ View, create, review (Step 3) |
| Procurement — Purchase Orders | ✅ Create + manage full PO lifecycle |
| Procurement — Goods Receipts | ✅ Create + confirm |
| Vendors | ✅ View, manage, accredit |
| Vendor Items (Catalogue) | ✅ View MRQ, review |
| Inventory | ✅ View items, stock, locations |
| Delivery | ✅ View (inbound context) |
| Self-service | ✅ Payslip, own leave, loans apply |
| **Accounting / GL / AP invoices / Payroll** | ❌ No financial access |

**Test Scenarios:**
1. Create a Purchase Request
2. Convert an approved PR to a Purchase Order
3. Record a Goods Receipt when vendor delivers
4. Accredit a new vendor

---

#### `impex_officer` — Import/Export Officer
**Credentials:** `impex.officer@ogamierp.local` / `Officer@12345!` (Cristina Aquino)

**Company Responsibility:** Manages import/export shipments and delivery documentation.

**Module Access:**

| Module | Access |
|---|---|
| Delivery (Shipments, Receipts, Vehicles) | ✅ Full (primary responsibility) |
| Procurement | ✅ View PRs, POs, create + confirm Goods Receipts |
| Vendors | ✅ View |
| Inventory | ✅ View items, stock, locations |
| Self-service | ✅ Payslip, own leave, loans apply (loan *reviewing* belongs to Accounting/Purchasing Officers only) |
| **Accounting / Payroll / HR** | ❌ No access |

**Test Scenarios:**
1. Create a shipment record and mark it in-transit
2. Record a delivery receipt for a PO
3. Confirm goods received at port/warehouse

---

### Department Heads

All `head` accounts share the same role and permissions. They differ only in their department assignment.

**Credentials format:** `<dept>.head@ogamierp.local` / `Head@123456789!`

| Account | Name | Department | Scope |
|---|---|---|---|
| `warehouse.head@ogamierp.local` | Ernesto Bautista | WH | Warehouse & Logistics |
| `ppc.head@ogamierp.local` | Jerome Florido | PPC | Production Planning & Control |
| `maintenance.head@ogamierp.local` | Armando Dela Torre | MAINT | Maintenance |
| `production.head@ogamierp.local` | Danilo Espiritu | PROD | Production |
| `processing.head@ogamierp.local` | Eliza Navarro | PROD | Processing / Inspection |
| `qcqa.head@ogamierp.local` | Rhodora Salazar | QC | Quality Control |
| `iso.head@ogamierp.local` | Bernard Pineda | ISO | Management Systems |

**Company Responsibilities:**

| Position | Responsibility |
|---|---|
| Warehouse Head | Manages warehouse operations, receiving deliveries, and distribution |
| PPC Head | Production planning, delivery scheduling (local + export), inventory control, material ordering |
| Maintenance Head | Ensures proper maintenance of all machines and equipment |
| Production Head | Oversees production of products required by customers |
| Processing Head | Conducts inspection and verification of produced products |
| QC/QA Head | Ensures product quality before delivery |
| Mgmt System Head | Oversees ISO/IATF compliance systems |

**Approval Chain Position:** Step 2 (noted by) for loans, purchase requests, and MRQs.

**Module Access (all `head` accounts):**

| Module | Access |
|---|---|
| Employees (team) | ✅ View team, upload/download documents |
| Attendance | ✅ View team, import CSV, resolve anomalies |
| Overtime | ✅ View + supervise |
| Leave | ✅ View team, file on behalf, **head approve** (Step 1) |
| Loans | ✅ Own + apply, **head note** (v2 Step 1), supervisor review (v1) |
| Purchase Requests | ✅ View + **note** (Step 1) |
| Goods Receipts | ✅ View, create, confirm |
| Inventory / MRQ | ✅ View, create MRQ, **note** (Step 2), fulfill |
| Production | ✅ View + log output |
| QC Inspections | ✅ View + create |
| NCR | ✅ View |
| Maintenance | ✅ Full |
| Mold | ✅ Full + log shots |
| Delivery | ✅ View |
| ISO | ✅ View + audit |
| GL / AP / AR | ✅ View only |
| Reports | ✅ GL + AP Aging (read) |
| **Create accounting entries / approve payroll** | ❌ No |

**Test Scenarios:**
1. Note a loan application (head note — Step 1 of the 5-stage approval chain)
2. Approve a leave request for a subordinate
3. Create a Material Requisition (MRQ) and note it
4. Confirm goods receipt for an inbound delivery
5. Log production output for an order

---

### Staff

#### `staff` — Production Operator
**Credentials:** `prod.staff@ogamierp.local` / `Staff@123456789!` (Pedro dela Cruz)

**Company Responsibility:** Executes tasks assigned by the Department Head. Initiates requests — the starting point of every approval chain.

**Module Access (self-service only):**

| Module | Access |
|---|---|
| Own Payslip | ✅ View + download |
| Own Leave | ✅ View, file, cancel |
| Loans | ✅ View own + **apply** (first step, creates the request) |
| Own Attendance | ✅ View |
| Overtime | ✅ View + **submit** |
| Own Profile | ✅ View + submit updates |
| Inventory MRQ | ✅ View + **create** |
| Production Orders | ✅ View + **log output** |
| Mold | ✅ Log shots |
| **Any approval / management action** | ❌ No |

**Test Scenarios:**
1. Submit a loan application → triggers the 5-stage approval chain
2. File a leave request → Head approves → Manager checks → GA processes
3. Create an MRQ → Head notes → Manager checks → Officer reviews → VP approves
4. Submit an overtime request
5. Log production output for an assigned order

---

## Approval Chain Walkthroughs

Use these multi-account chains to test full end-to-end workflows.

---

### Loan Approval Chain (5 stages — SoD enforced)

```
[1] prod.staff@ogamierp.local     (staff)            → Apply for loan
[2] production.head@ogamierp.local (head)             → Head Note
[3] hr.manager@ogamierp.local      (manager)          → Manager Check
[4] acctg.officer@ogamierp.local   (officer)          → Officer Review
[5] vp@ogamierp.local              (vice_president)   → VP Approve / Reject
```

> ⚠️ **SoD:** The same user cannot perform two sequential steps. The system will block it.

---

### Leave Request Chain (3 stages)

```
[1] prod.staff@ogamierp.local      (staff)    → File own leave
[2] production.head@ogamierp.local (head)     → Head Approve
[3] hr.manager@ogamierp.local      (manager)  → Manager Check
    ga.officer@ogamierp.local      (ga_officer) → GA Process (admin step)
```

---

### Purchase Request Chain (5 stages)

```
[1] prod.staff@ogamierp.local          (staff)            → Create PR
[2] production.head@ogamierp.local     (head)             → Note
[3] hr.manager@ogamierp.local          (manager)          → Check (HR Manager acts as Plant-side manager for PR workflow)
[4] acctg.officer@ogamierp.local       (officer)          → Review + Budget Check
[5] vp@ogamierp.local                  (vice_president)   → Approve → triggers auto-PO draft
```

After VP approval, `purchasing.officer@ogamierp.local` converts the draft PO to a live order and manages vendor interaction.

---

### Material Requisition Chain (5 stages)

```
[1] prod.staff@ogamierp.local          (staff)            → Create MRQ
[2] production.head@ogamierp.local     (head)             → Note
[3] hr.manager@ogamierp.local          (manager)          → Check
[4] acctg.officer@ogamierp.local       (officer)          → Review
[5] vp@ogamierp.local                  (vice_president)   → VP Approve
    warehouse.head@ogamierp.local      (head)             → Fulfill MRQ from warehouse
```

---

### Payroll Approval Chain (3 stages)

```
[hr.manager@ogamierp.local]     (manager) → Initiate + Compute + HR Approve
[acctg.officer@ogamierp.local]  (officer) → Accounting Approve + Disburse + Publish
```

> The VP does not have a step in the payroll chain. Payroll is a bilateral HR–Accounting workflow.

---

### OT Approval

```
[1] prod.staff@ogamierp.local      (staff)   → Submit OT request
[2] production.head@ogamierp.local (head)    → Supervise (approve OT at team level)
[3] hr.manager@ogamierp.local      (manager) → Final approve / reject
```

---

## Department Scope Notes

| Role | Dept Scope Behaviour |
|---|---|
| `super_admin` | No scope — sees all data |
| `executive` | No scope — bypass middleware |
| `admin` | No scope — system-only, no business data |
| `vice_president` | Scoped, but seeded with access to all 13 departments |
| `plant_manager` | Scoped to PLANT + PROD, QC, MOLD, WH, PPC, MAINT, ISO |
| All other roles | Scoped to their own department only |

> `manager` (HR Manager) is scoped to HR only. If cross-dept queries are needed (e.g. listing all employees), the service uses `Employee::withoutDepartmentScope()` internally.

---

*Seeder files:*
- *`database/seeders/RolePermissionSeeder.php` — roles, permissions, admin + superadmin accounts*
- *`database/seeders/SampleDataSeeder.php` — HR Manager + Accounting Officer (with attendance + payroll data)*
- *`database/seeders/ManufacturingEmployeeSeeder.php` — all other 18 position accounts + multi-dept access*
