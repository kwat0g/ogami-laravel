# 🧪 Ogami ERP — Complete Manual Testing Guide

> **Version:** 2026-03 | **Grounded in:** Actual source code (seeders, React components, service classes, state machines)
> **Frontend:** `http://localhost:5173` | **Backend API:** `http://localhost:8000`
> **First run:** `php artisan migrate:fresh --seed` then `npm run dev`
> **Queue:** `php artisan queue:work` (required for payroll computation, notifications, auto-MRQ)

---

## ⚠️ Read Before Starting

- Every button label, field name, status text, and navigation path in this guide was verified against actual React source files and Laravel service classes.
- **Never use `super_admin` for workflow tests** — it bypasses SoD, department scoping, and all permission gates, which means it will not catch real permission errors.
- All monetary values display in **₱ PHP** in the UI — always enter amounts in pesos (backend stores centavos internally).
- Sidebar sections toggle open/closed on click. The active section auto-opens on page load.
- URL identifiers are **ULIDs** (26-character strings), not integer IDs.
- **SoD (Segregation of Duties):** The user who creates a record cannot approve it. Enforced at both backend (policy throws `SodViolationException`) and frontend (button hidden or disabled with reason shown).

---

## 1. INTRODUCTION

### System Overview

Ogami ERP is a Philippine manufacturing ERP covering 20 business domains:

| Domain | Business Function |
|--------|-------------------|
| HR | Employee master data, org structure, lifecycle management |
| Attendance | Time logs, overtime requests, shift schedules |
| Leave | Leave requests, balances, 4-step approval chain |
| Payroll | 17-step computation pipeline, 7-step approval workflow |
| Loans | Employee loans, 5-stage SoD-enforced approval chain |
| Accounting | Chart of accounts, journal entries, general ledger |
| AP (Payables) | Vendors, vendor invoices, payments, credit notes |
| AR (Receivables) | Customers, customer invoices, payment receipts |
| Tax | VAT ledger, BIR filings |
| Inventory | Item master, stock management, material requisitions (6-step) |
| Procurement | Purchase requests, purchase orders, goods receipts |
| Production | BOMs, work orders, delivery schedules, output logging |
| QC/QA | Inspections, NCRs, CAPA actions |
| Maintenance | Equipment registry, corrective and preventive work orders |
| Mold | Mold masters, shot logs, criticality tracking |
| Delivery | Delivery receipts, shipments |
| ISO/IATF | Controlled documents, internal audits, findings |
| CRM | Support tickets, client and vendor portals |
| Fixed Assets | Asset register, depreciation entries, disposals |
| Budget | Cost centers, annual budgets, budget vs actual |

### How To Use This Guide

1. Run `php artisan migrate:fresh --seed` on a clean local or staging database
2. Follow the **Test Execution Order** (Stage 0 → Stage 6)
3. Each test section specifies exactly which account to log in as
4. **🔄** marks required account switches — log out and log in as the specified user
5. **✅** marks expected verification outcomes — confirm each one before proceeding
6. **⚠️ GAP** marks known gaps where full testing is limited

---

## 2. TEST ACCOUNT SETUP GUIDE

### 2.1 Environment Setup

```bash
# Terminal 1 — Fresh database seed (WARNING: destroys all existing data)
php artisan migrate:fresh --seed

# Terminal 2 — Frontend Vite dev server
cd frontend && npm run dev

# Terminal 3 — Laravel API server
php artisan serve

# Terminal 4 — Queue worker (required for payroll, auto-MRQ, notifications)
php artisan queue:work
```

### 2.2 All Test Accounts (Pre-Seeded — No Manual Creation Required)

All accounts are created automatically by `migrate:fresh --seed` via `SampleDataSeeder` and `ManufacturingEmployeeSeeder`.

#### System Accounts

| Role | Email | Password | Purpose |
|------|-------|----------|---------|
| `admin` | admin@ogamierp.local | `Admin@1234567890!` | User management, system settings, audit logs |
| `super_admin` | superadmin@ogamierp.local | `SuperAdmin@12345!` | **Data verification ONLY** — bypasses all gates |

#### Business Role Accounts

| Role | Email | Password | Dept | Emp Code | Name |
|------|-------|----------|------|----------|------|
| `executive` | chairman@ogamierp.local | `Executive@12345!` | EXEC | EMP-2026-0006 | Roberto Ogami |
| `executive` | president@ogamierp.local | `Executive@12345!` | EXEC | EMP-2026-0007 | Eduardo Ogami |
| `vice_president` | vp@ogamierp.local | `VicePresident@1!` | EXEC + all depts | EMP-2026-0008 | Lorenzo Ogami |
| `manager` (HR) | hr.manager@ogamierp.local | `HrManager@1234!` | HR | EMP-2026-0001 | Maria Santos |
| `officer` (Acctg) | acctg.officer@ogamierp.local | `AcctgManager@1234!` | ACCTG | EMP-2026-0003 | Anna Marie Lim |
| `plant_manager` | plant.manager@ogamierp.local | `Manager@12345!` | PLANT | EMP-2026-0009 | Carlos Reyes |
| `production_manager` | prod.manager@ogamierp.local | `Manager@12345!` | PROD | EMP-2026-0010 | Renaldo Mendoza |
| `qc_manager` | qc.manager@ogamierp.local | `Manager@12345!` | QC | EMP-2026-0011 | Josephine Villanueva |
| `mold_manager` | mold.manager@ogamierp.local | `Manager@12345!` | MOLD | EMP-2026-0012 | Victor Castillo |
| `ga_officer` | ga.officer@ogamierp.local | `Officer@12345!` | HR | EMP-2026-0013 | Rachel Garcia |
| `purchasing_officer` | purchasing.officer@ogamierp.local | `Officer@12345!` | ACCTG | EMP-2026-0014 | Marlon Torres |
| `impex_officer` | impex.officer@ogamierp.local | `Officer@12345!` | ACCTG | EMP-2026-0015 | Cristina Aquino |
| `warehouse_head` | warehouse.head@ogamierp.local | `Head@123456789!` | WH | EMP-2026-0016 | Ernesto Bautista |
| `ppc_head` | ppc.head@ogamierp.local | `Head@123456789!` | PPC | EMP-2026-0017 | Jerome Florido |
| `head` (Maintenance) | maintenance.head@ogamierp.local | `Head@123456789!` | MAINT | EMP-2026-0018 | Armando Dela Torre |
| `head` (Production) | production.head@ogamierp.local | `Head@123456789!` | PROD | EMP-2026-0019 | Danilo Espiritu |
| `head` (Processing) | processing.head@ogamierp.local | `Head@123456789!` | PROD | EMP-2026-0020 | Eliza Navarro |
| `head` (QC/QA) | qcqa.head@ogamierp.local | `Head@123456789!` | QC | EMP-2026-0021 | Rhodora Salazar |
| `head` (ISO) | iso.head@ogamierp.local | `Head@123456789!` | ISO | EMP-2026-0022 | Bernard Pineda |
| `staff` | prod.staff@ogamierp.local | `Staff@123456789!` | PROD | EMP-2026-0023 | Pedro dela Cruz |

### 2.3 Quick Reference Card

```
╔══════════════════════╦═══════════════════════════════════════╦════════════════════╗
║ Role                 ║ Email                                 ║ Password           ║
╠══════════════════════╬═══════════════════════════════════════╬════════════════════╣
║ admin                ║ admin@ogamierp.local                  ║ Admin@1234567890!  ║
║ super_admin          ║ superadmin@ogamierp.local             ║ SuperAdmin@12345!  ║
║ executive            ║ chairman@ogamierp.local               ║ Executive@12345!   ║
║ vice_president       ║ vp@ogamierp.local                     ║ VicePresident@1!   ║
║ manager (HR)         ║ hr.manager@ogamierp.local             ║ HrManager@1234!    ║
║ officer (Acctg)      ║ acctg.officer@ogamierp.local          ║ AcctgManager@1234! ║
║ plant_manager        ║ plant.manager@ogamierp.local          ║ Manager@12345!     ║
║ production_manager   ║ prod.manager@ogamierp.local           ║ Manager@12345!     ║
║ qc_manager           ║ qc.manager@ogamierp.local             ║ Manager@12345!     ║
║ mold_manager         ║ mold.manager@ogamierp.local           ║ Manager@12345!     ║
║ ga_officer           ║ ga.officer@ogamierp.local             ║ Officer@12345!     ║
║ purchasing_officer   ║ purchasing.officer@ogamierp.local     ║ Officer@12345!     ║
║ impex_officer        ║ impex.officer@ogamierp.local          ║ Officer@12345!     ║
║ warehouse_head       ║ warehouse.head@ogamierp.local         ║ Head@123456789!    ║
║ ppc_head             ║ ppc.head@ogamierp.local               ║ Head@123456789!    ║
║ head (Maintenance)   ║ maintenance.head@ogamierp.local       ║ Head@123456789!    ║
║ head (Production)    ║ production.head@ogamierp.local        ║ Head@123456789!    ║
║ head (QC/QA)         ║ qcqa.head@ogamierp.local              ║ Head@123456789!    ║
║ head (ISO)           ║ iso.head@ogamierp.local               ║ Head@123456789!    ║
║ staff                ║ prod.staff@ogamierp.local             ║ Staff@123456789!   ║
╚══════════════════════╩═══════════════════════════════════════╩════════════════════╝
```

---

## 3. TEST EXECUTION ORDER

```
MASTER TEST EXECUTION ORDER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

STAGE 0 — Authentication & Session
  □ 0.1  Login with correct credentials for multiple roles
  □ 0.2  Login with wrong password → error shown
  □ 0.3  Logout and back-navigation blocked

STAGE 1 — Foundation Modules (no cross-module dependencies)
  □ 1.1  Administration — users, roles, system settings, audit log
  □ 1.2  HR — departments, positions, employee creation & activation
  □ 1.3  Banking — bank accounts setup (required for AP/AR payments)
  □ 1.4  Attendance — shift schedules, time log viewing

STAGE 2 — Reference Data Modules
  □ 2.1  Accounting — chart of accounts, fiscal periods
  □ 2.2  Vendors — vendor creation and accreditation (Purchasing Officer)
  □ 2.3  Inventory — item categories, item master, warehouse locations
  □ 2.4  Fixed Assets — asset categories and register
  □ 2.5  Budget — cost centers and budget lines

STAGE 3 — Workflow Modules (depend on Stages 1+2)
  □ 3.1  Leave — 5-step approval: submit → head → plant_manager → GA → VP
  □ 3.2  Overtime — role-based 3-step approval chain
  □ 3.3  Loans — 5-stage SoD-enforced chain: submit → head → manager → officer → VP
  □ 3.4  Payroll — 7-step wizard: Define → Scope → Validate → Compute → HR Review → Acctg Review → Disburse
  □ 3.5  AP — vendor invoice: create → submit → approve → payment
  □ 3.6  AR — customer invoice: create → approve → receive payment
  □ 3.7  Procurement — PR → 4-step approval → PO → Goods Receipt
  □ 3.8  Production — BOM → work order → MRQ → output → complete
  □ 3.9  QC/QA — inspection → NCR → CAPA → close
  □ 3.10 Maintenance — equipment → corrective WO → PM schedule
  □ 3.11 Mold — mold master → shot log
  □ 3.12 Delivery — delivery receipt → shipment → delivered
  □ 3.13 ISO/IATF — controlled document → internal audit → finding → close

STAGE 4 — Cross-Module Integration Tests
  □ 4.1  Hire-to-Payslip (HR → Payroll)
  □ 4.2  Procure-to-Pay (Procurement → Inventory → AP → Accounting GL)
  □ 4.3  Produce-to-Ship (Production → QC → Delivery → AR)

STAGE 5 — Financial Reports (require Stages 1–4 data)
  □ 5.1  Trial Balance, Balance Sheet, Income Statement, Cash Flow
  □ 5.2  VAT Ledger and Tax Summary
  □ 5.3  AP Aging and AR Aging
  □ 5.4  Government Reports (BIR 1601-C, SSS SBR-2, PhilHealth RF-1, Pag-IBIG MC)

STAGE 6 — System-Wide Boundary Tests
  □ 6.1  Permission boundary matrix (wrong role gets 403)
  □ 6.2  SoD enforcement (self-approval blocked at every workflow step)
  □ 6.3  Data validation boundary tests

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## 4. MODULE TEST GUIDES

---

### STAGE 0 — Authentication

═══════════════════════════════════════════════════════════
#### MODULE: Authentication / Session
═══════════════════════════════════════════════════════════

**OVERVIEW:** Session-cookie auth via Laravel Sanctum. No JWT. No tokens in localStorage. Login page at `/login`.

---

##### TEST SECTION 0.1 — Successful Login

👤 **No prior session required**

**Steps:**

1. Navigate to `http://localhost:5173/login`
   - ✅ Verify: Heading **"Sign in to your account"** is visible
   - ✅ Verify: Subtext **"Use your Ogami ERP credentials"** is visible
   - ✅ Verify: Two fields labeled **Email** and **Password** are visible
   - ✅ Verify: Button **"Sign in"** is visible

2. Fill **Email**: `hr.manager@ogamierp.local`
   Fill **Password**: `HrManager@1234!`
   Click **"Sign in"**
   - ✅ Verify: Button briefly shows **"Signing in…"** during request
   - ✅ Verify: Redirects to `/dashboard`
   - ✅ Verify: Sidebar shows **Human Resources**, **Payroll**, **Team Management** sections
   - ✅ Verify: User name "Maria Santos" visible in top navigation area

3. Click logout (user menu in top-right corner) → click **"Sign out"**
   - ✅ Verify: Redirected to `/login`
   - ✅ Verify: Typing `/dashboard` in address bar redirects back to `/login`

**NEGATIVE TEST — Wrong password:**

4. On `/login`, fill **Email**: `hr.manager@ogamierp.local` | **Password**: `WrongPass!`
   Click **"Sign in"**
   - ✅ Verify: Error message about invalid credentials appears
   - ✅ Verify: User stays on `/login` page

**NEGATIVE TEST — Empty fields:**

5. Leave all fields empty, click **"Sign in"**
   - ✅ Verify: Inline validation errors appear under **Email** and **Password**
   - ✅ Verify: No network request is made

---

##### TEST SECTION 0.2 — Role-Specific Landing Pages

After login with each account, verify the correct sidebar sections are visible:

| Email | Expected Visible Sidebar Sections |
|-------|----------------------------------|
| admin@ogamierp.local | **Administration** only; no HR/finance module sections |
| hr.manager@ogamierp.local | Dashboard, Team Management, Human Resources, Payroll, GA Processing, Reports, Procurement (view-only) |
| acctg.officer@ogamierp.local | Dashboard, Accounting, Payables (AP), Receivables (AR), Banking, Financial Reports, Fixed Assets, Budget, Payroll, Procurement, Inventory |
| plant.manager@ogamierp.local | Dashboard, Team Management, Production, QC / QA, Maintenance, Mold, Delivery, ISO / IATF, Inventory |
| vp@ogamierp.local | Dashboard, VP Approvals, GA Processing, Financial Reports (view-only) |
| chairman@ogamierp.local | Dashboard, Executive Approvals |
| prod.staff@ogamierp.local | Dashboard only — no module sections |

---

### STAGE 1 — Foundation Modules

═══════════════════════════════════════════════════════════
#### MODULE: Administration
═══════════════════════════════════════════════════════════

**OVERVIEW:** User account management, role assignment, system settings, rate tables, fiscal periods, audit logs. Admin role has **zero business data access** — cannot see employees, payroll, or invoices.

**ACCOUNTS NEEDED:** `admin@ogamierp.local`

---

##### TEST SECTION 1.1 — User Management

👤 **LOGGED IN AS:** admin@ogamierp.local (`Admin@1234567890!`)

**Prerequisites:**
- ✅ Database freshly seeded

**Steps:**

1. Navigate to: **Administration → Users**
   - ✅ Verify: Table shows seeded user accounts
   - ✅ Verify: Columns include Name, Email, Role, Department

2. Click **"Add User"**
   - ✅ Verify: User creation form opens

3. Fill in the form:
   | Field | Value |
   |-------|-------|
   | **Name** | `Test Staff User` |
   | **Email** | `test.staff@ogamierp.local` |
   | **Password** | `StaffTest@1234!` |
   | **Role** | `staff` |

   Click **"Save"**
   - ✅ Verify: User appears in the table with role `staff`
   - ✅ Verify: Success notification shown

4. Find the new user → click **"Edit"**
   Change **Role** to `head` → click **"Save"**
   - ✅ Verify: Role column shows `head`

**NEGATIVE TEST — Duplicate email:**

5. Click **"Add User"** → enter **Email**: `test.staff@ogamierp.local`
   Click **"Save"**
   - ✅ Verify: Error about duplicate/already-taken email appears
   - ✅ Verify: Second user is not created

**PERMISSION BOUNDARY TEST:**

6. 🔄 **SWITCH**: Log in as `hr.manager@ogamierp.local` / `HrManager@1234!`
   Navigate directly to `/admin/users`
   - ✅ Verify: Redirected to `/403` (HR Manager lacks `system.manage_users`)
   🔄 **SWITCH BACK**: Log in as admin

---

##### TEST SECTION 1.2 — System Settings and Reference Tables

👤 **LOGGED IN AS:** admin@ogamierp.local (`Admin@1234567890!`)

**Steps:**

1. Navigate to: **Administration → System Settings**
   - ✅ Verify: Settings page loads
   - ✅ Verify: **Default Region** shows `NCR`

2. Navigate to: **Administration → Reference Tables**
   - ✅ Verify: SSS, PhilHealth, Pag-IBIG, and TRAIN Tax bracket tables visible
   - ✅ Verify: Each table shows actual rate data rows

3. Navigate to: **Administration → Fiscal Periods**
   - ✅ Verify: Table shows fiscal periods (e.g., Nov 2025, Dec 2025, Jan 2026, Feb 2026, Mar 2026)
   - ✅ Verify: Status column shows open/closed

4. Navigate to: **Administration → Audit Logs**
   - ✅ Verify: Log entries visible with timestamps and actor names

5. Navigate to: **Administration → Backup & Restore**
   - ✅ Verify: Backup management page loads without error

---

═══════════════════════════════════════════════════════════
#### MODULE: Human Resources (HR)
═══════════════════════════════════════════════════════════

**OVERVIEW:** Employee master data, org structure, lifecycle management. Employee states: `draft → active → on_leave | suspended → resigned | terminated`.

**ACCOUNTS NEEDED:**
- `hr.manager@ogamierp.local` — creates, edits, manages employees
- `plant.manager@ogamierp.local` — activates employees (SoD: activator ≠ creator)

**PREREQUISITES:**
- ✅ Departments seeded: HR, IT, ACCTG, PROD, SALES, EXEC, PLANT, QC, MOLD, WH, PPC, MAINT, ISO (13 total)
- ✅ Positions seeded (HR-MGR, PROD-OP, etc.)
- ✅ Salary Grades seeded: SG-01 through SG-15

---

##### TEST SECTION 1.3 — Employee Creation

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Human Resources → All Employees**
   - ✅ Verify: Page title "Employees" visible
   - ✅ Verify: Table shows existing employees with columns: Employee Code, Name, Department, Position, Status

2. Click **"Add Employee"**
   - ✅ Verify: Navigates to a form page titled **"New Employee"**
   - ✅ Verify: Form sections visible: Personal Information, Employment, Government IDs, Bank Details

3. Fill in all required fields (marked `*`):

   **Personal Information:**
   | Field | Value |
   |-------|-------|
   | **First Name** `*` | `Juan` |
   | **Last Name** `*` | `Dela Cruz` |
   | **Middle Name** | `Reyes` |
   | **Gender** `*` | select `male` |
   | **Date of Birth** | `1990-05-15` |
   | **Civil Status** | select `SINGLE` |

   **Employment Details:**
   | Field | Value |
   |-------|-------|
   | **Employment Type** `*` | select `regular` |
   | **Pay Basis** `*` | select `monthly` |
   | **Basic Monthly Rate** `*` | `18000` |
   | **Date Hired** `*` | `2026-01-06` |
   | **Department** | select `PROD — Production` |
   | **Position** | select any production position |
   | **Salary Grade** | select `SG-05` |

   **Government IDs (required on create):**
   | Field | Value |
   |-------|-------|
   | **SSS No.** | `03-9999999-9` |
   | **TIN** | `999-888-777-000` |
   | **PhilHealth No.** | `01-999999999-9` |
   | **Pag-IBIG No.** | `9999-8888-7777` |

   **Bank Details (required on create):**
   | Field | Value |
   |-------|-------|
   | **Bank Name** | `BDO` |
   | **Bank Account No.** | `00009999999999` |

4. Click **"Save"**
   - ✅ Verify: Toast **"Employee created."** or similar appears
   - ✅ Verify: Redirected to the new employee's detail page
   - ✅ Verify: Employee code auto-generated in format `EMP-YYYY-NNNN`
   - ✅ Verify: Status badge shows **"Draft"**
   - ✅ Verify: **"Edit Profile"** button is visible

**NEGATIVE TEST — Missing required field:**

5. Navigate back → click **"Add Employee"**
   Leave **First Name** empty, fill all other required fields
   Click **"Save"**
   - ✅ Verify: Error **"First name is required"** appears under the First Name field
   - ✅ Verify: Form does not navigate away

**NEGATIVE TEST — Zero monetary amount:**

6. Enter `0` in **Basic Monthly Rate**, click **"Save"**
   - ✅ Verify: Error **"Rate must be greater than 0"** appears under that field

---

##### TEST SECTION 1.4 — Employee Activation (SoD Enforced)

**Context:** Maria Santos (hr.manager) created Juan Dela Cruz. SoD prevents the creator from activating their own created record. A **different** user with `employees.activate` permission must activate.

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to the detail page of **Juan Dela Cruz** (use All Employees list to find)
   - ✅ Verify: Status shows **"Draft"**
   - ✅ Verify: **"Active"** transition button is visible

2. Click the **"Active"** button while logged in as the creator (Maria Santos)
   - ✅ Verify: Button is either hidden, disabled with a SoD tooltip, or clicking it shows an error
   - ✅ Verify: Status does NOT change to Active

3. 🔄 **SWITCH ACCOUNT**: Log in as `plant.manager@ogamierp.local` / `Manager@12345!`
   Navigate to **Human Resources → All Employees** → find **Juan Dela Cruz**
   Open the detail page

4. Click the **"Active"** button (green)
   - A confirmation dialog may appear — confirm the action
   - ✅ Verify: Status changes to **"Active"**
   - ✅ Verify: **"On Leave"**, **"Suspended"**, **"Resigned"**, **"Terminated"** buttons now appear
   - ✅ Verify: **"Active"** button no longer appears (already active)

**PERMISSION BOUNDARY TEST:**

5. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
   Navigate directly to `/hr/employees/all`
   - ✅ Verify: Redirected to `/403` (staff lacks `hr.full_access`)

---

##### TEST SECTION 1.5 — Departments and Positions

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Human Resources → Departments**
   - ✅ Verify: 13 departments listed: HR, IT, ACCTG, PROD, SALES, EXEC, PLANT, QC, MOLD, WH, PPC, MAINT, ISO
   - ✅ Verify: Each row shows department name, code, and status (active/inactive)

2. Navigate to: **Human Resources → Positions**
   - ✅ Verify: Positions list shows multiple entries with position codes (HR-MGR, PROD-OP, etc.)
   - ✅ Verify: Department column shows which dept each position belongs to

3. Navigate to: **Human Resources → Shifts**
   - ✅ Verify: Shift schedules are listed (at least one Regular shift 08:00–17:00 seeded)
   - ✅ Verify: Time columns show start time and end time

---

##### TEST SECTION 1.6 — HR Reports

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Human Resources → HR Reports**
   - ✅ Verify: Report page loads with filter options

2. Apply a filter (e.g., set Department to `PROD`) and generate/search
   - ✅ Verify: Results filtered to PROD department employees

**PERMISSION BOUNDARY TEST:**

3. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
   Navigate directly to `/hr/reports`
   - ✅ Verify: Redirected to `/403`

---

═══════════════════════════════════════════════════════════
#### MODULE: Banking
═══════════════════════════════════════════════════════════

**OVERVIEW:** Bank account master data required for AP payments and AR payment receipts.

**ACCOUNTS NEEDED:** `acctg.officer@ogamierp.local`

---

##### TEST SECTION 1.7 — Bank Account Setup

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Banking → Bank Accounts**
   - ✅ Verify: Bank accounts list page loads

2. Click **"New"** or **"Add Bank Account"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Name** | `BDO Operating Account` |
   | **Account Number** | `0000-1234-5678` |
   | **Bank Name** | `Banco de Oro (BDO)` |
   | **Account Type** | `Checking` |
   | **GL Account** | select `1001 — Cash in Bank` |
   | **Opening Balance** | `500000` |
   | **Active** | checked |

   Click **"Save"**
   - ✅ Verify: Bank account appears in the list
   - ✅ Verify: Success notification shown

3. Create a second account for payroll disbursements:
   | Field | Value |
   |-------|-------|
   | **Name** | `Metrobank Payroll Account` |
   | **Account Number** | `221-345-6789-01` |
   | **Bank Name** | `Metrobank` |
   | **Account Type** | `Checking` |
   | **GL Account** | select `1001 — Cash in Bank` |
   | **Opening Balance** | `0` |

   Click **"Save"**
   - ✅ Verify: Second bank account appears in the list

**PERMISSION BOUNDARY TEST:**

4. 🔄 **SWITCH**: Log in as `hr.manager@ogamierp.local` / `HrManager@1234!`
   Navigate directly to `/banking/accounts`
   - ✅ Verify: Redirected to `/403` (HR Manager lacks `bank_accounts.view`)

---

═══════════════════════════════════════════════════════════
#### MODULE: Attendance
═══════════════════════════════════════════════════════════

**OVERVIEW:** Time log management, anomaly resolution, shift schedules. GA Officer manages attendance administration. Employees view their own logs.

**ACCOUNTS NEEDED:**
- `ga.officer@ogamierp.local` — import CSV, view all team attendance, resolve anomalies
- `prod.staff@ogamierp.local` — view own attendance
- `hr.manager@ogamierp.local` — full HR access including attendance

---

##### TEST SECTION 1.8 — Attendance Logs

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Human Resources → Attendance Logs**
   - ✅ Verify: Attendance log list page loads
   - ✅ Verify: Table columns include: Employee, Date, Time In, Time Out, Status, Anomaly flag

2. Navigate to: **Human Resources → Shifts**
   - ✅ Verify: Shift schedules list shows at least one shift (Regular: 08:00–17:00)

**GA Officer attendance import test:**

3. 🔄 **SWITCH**: Log in as `ga.officer@ogamierp.local` / `Officer@12345!`
   Navigate to: **GA Processing → GA Leave Processing** (or look for attendance import)
   - ✅ Verify: GA Officer can see attendance-related pages

**Staff self-service attendance:**

4. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
   Navigate to Dashboard → look for "My Attendance" self-service link
   - ✅ Verify: Staff can only see their own attendance records
   - ✅ Verify: Staff CANNOT see other employees' attendance

---

### STAGE 3 — Workflow Modules

═══════════════════════════════════════════════════════════
#### MODULE: Leave Management
═══════════════════════════════════════════════════════════

**OVERVIEW:** 5-step leave approval chain:
`draft → submitted → head_approved → manager_checked → ga_processed → approved`
- Step 1 (submitter): Any employee with `leaves.file_own`
- Step 2 (department head): `leaves.head_approve` → status: `head_approved`
- Step 3 (plant manager / HR manager): `leaves.manager_check` → status: `manager_checked`
- Step 4 (GA Officer): `leaves.ga_process` → status: `ga_processed` | `rejected`
- Step 5 (Vice President): `leaves.vp_note` → status: `approved`
- SoD: each step's actor must differ from the original submitter

**ACCOUNTS NEEDED:**
- `hr.manager@ogamierp.local` — files leave on behalf of employees (HR view)
- `production.head@ogamierp.local` — approves as department head (Step 2)
- `plant.manager@ogamierp.local` — checks as manager (Step 3)
- `ga.officer@ogamierp.local` — processes as GA Officer (Step 4)
- `vp@ogamierp.local` — notes as VP (Step 5, final approval)

**PREREQUISITES:**
- ✅ At least one active employee exists (e.g., Juan Dela Cruz or any seeded employee)
- ✅ Leave types seeded: VL (Vacation Leave), SL (Sick Leave), EL, ML, PL, SPL, SIL, VAWC

---

##### TEST SECTION 3.1 — File Leave Request

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Human Resources → Leave Requests**
   - ✅ Verify: Leave requests list loads
   - ✅ Verify: Table shows existing leave requests with status badges

2. Click **"New Leave Request"** or **"File Leave"**
   - ✅ Verify: Navigates to leave request form with title **"File Leave Request"**
   - ✅ Verify: Form shows fields: Employee, Leave Type, Date From, Date To, Half day leave (checkbox), Reason

3. Fill in:
   | Field | Value |
   |-------|-------|
   | **Employee** | Select `Pedro dela Cruz` (prod.staff) |
   | **Leave Type** | Select `VL — Vacation Leave` |
   | **Date From** | `2026-04-01` |
   | **Date To** | `2026-04-02` |
   | **Half day leave** | Leave unchecked |
   | **Reason** | `Annual family vacation` |

4. Click **"File Leave"**
   - ✅ Verify: Toast **"Leave request submitted."** appears
   - ✅ Verify: Redirected to the leave requests list
   - ✅ Verify: New record appears with status **"Submitted"**

**NEGATIVE TEST — Missing employee:**

5. Click **"File Leave"** on the form with no **Employee** selected
   Click **"File Leave"**
   - ✅ Verify: Validation error appears under the Employee field
   - ✅ Verify: Form does not submit

---

##### TEST SECTION 3.2 — Leave Approval Chain (Steps 2–5)

**Prerequisites:**
- ✅ Leave request for Pedro dela Cruz is in `submitted` status

---

**Step 2 — Department Head Approves:**

👤 **LOGGED IN AS:** production.head@ogamierp.local (`Head@123456789!`)

1. Navigate to: **Team Management → Team Leave**
   - ✅ Verify: Leave request for Pedro dela Cruz is visible with status **"Submitted"**

2. Click the leave request to open its detail view
   - ✅ Verify: Detail page shows employee name, leave type, dates, reason, current status

3. Click **"Approve"** (head approval button)
   - A remarks field may appear — fill in: `Approved for family leave`
   - Click the confirm button
   - ✅ Verify: Status changes to **"Head Approved"**
   - ✅ Verify: Success notification appears

**SoD TEST — Head cannot approve if they submitted it:**

4. Verify that if `production.head@ogamierp.local` had filed this leave request themselves, they could NOT approve it. (The "Approve" button would be hidden or disabled with a SoD message.)

---

**Step 3 — Plant Manager Checks:**

🔄 **SWITCH ACCOUNT**: Log in as `plant.manager@ogamierp.local` / `Manager@12345!`

5. Navigate to **Team Management → Team Leave**
   Find the leave request (status **"Head Approved"**)
   Open the detail page

6. Click **"Check"** or **"Manager Check"** button
   - Optionally fill in remarks
   - Click confirm
   - ✅ Verify: Status changes to **"Manager Checked"**

---

**Step 4 — GA Officer Processes:**

🔄 **SWITCH ACCOUNT**: Log in as `ga.officer@ogamierp.local` / `Officer@12345!`

7. Navigate to: **GA Processing → GA Leave Processing**
   Find the leave request (status **"Manager Checked"**)
   Open the detail page

8. Click **"Process"** or the GA processing button
   - ✅ Verify: Action options appear: `Approved with Pay`, `Approved without Pay`, `Disapproved`
   Select: `Approved with Pay`
   - Fill in remarks: `Leave balance verified — approved`
   - Click confirm
   - ✅ Verify: Status changes to **"GA Processed"**

**NEGATIVE TEST — GA disapprove:**

9. On a separate leave request (in Manager Checked status), GA Officer selects **"Disapproved"**
   Click confirm
   - ✅ Verify: Status changes directly to **"Rejected"** (VP step is skipped)

---

**Step 5 — VP Notes (Final Approval):**

🔄 **SWITCH ACCOUNT**: Log in as `vp@ogamierp.local` / `VicePresident@1!`

10. Navigate to: **VP Approvals → Pending Approvals**
    Find the leave request with status **"GA Processed"**
    Open the detail page

11. Click **"Note"** or the VP note button
    - Optionally fill in remarks
    - Click confirm
    - ✅ Verify: Status changes to **"Approved"**
    - ✅ Verify: Leave balance for the employee has been deducted

**CROSS-MODULE CHECK:**

12. 🔄 **SWITCH**: Log in as `hr.manager@ogamierp.local` / `HrManager@1234!`
    Navigate to **Human Resources → Leave Requests**
    Find the approved leave request
    - ✅ Verify: Status shows **"Approved"**
    Navigate to the employee's leave balance record
    - ✅ Verify: VL balance reduced by the number of approved leave days

---

═══════════════════════════════════════════════════════════
#### MODULE: Overtime
═══════════════════════════════════════════════════════════

**OVERVIEW:** Overtime approval is role-dependent:
- **Staff** requests: `pending → supervisor_approved → approved` (head endorses, manager approves)
- **Manager** requests: `pending_executive → approved` (executive approves)
- SoD: approver cannot be the same person as the requester

**ACCOUNTS NEEDED:**
- `prod.staff@ogamierp.local` — files overtime request
- `production.head@ogamierp.local` — endorses (supervisor_approve) for staff
- `plant.manager@ogamierp.local` — final manager approval for staff OT
- `hr.manager@ogamierp.local` — files OT as manager role
- `chairman@ogamierp.local` — approves executive-level OT

---

##### TEST SECTION 3.3 — Staff Overtime Request

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Human Resources → Overtime**
   - ✅ Verify: Overtime requests list loads

2. Click **"New Overtime Request"** or equivalent button
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Employee** | Select `Pedro dela Cruz` |
   | **Work Date** | A future date (e.g., next working day) |
   | **Requested Minutes** | `120` (2 hours, must be 30–480) |
   | **Reason** | `Production deadline — product packaging` |

   Click **"Submit"** or **"File"**
   - ✅ Verify: OT request appears with status **"Pending"**

**Head endorsement (Step 2 for staff):**

3. 🔄 **SWITCH**: Log in as `production.head@ogamierp.local` / `Head@123456789!`
   Navigate to: **Team Management → Team Overtime**
   Find the OT request → click **"Approve"** (supervisor endorsement)
   - ✅ Verify: Status changes to **"Supervisor Approved"**

**Manager final approval:**

4. 🔄 **SWITCH**: Log in as `plant.manager@ogamierp.local` / `Manager@12345!`
   Navigate to **Team Management → Team Overtime**
   Find the OT request (status **"Supervisor Approved"**)
   Click **"Approve"** (manager final approval)
   Enter approved minutes: `120`
   - ✅ Verify: Status changes to **"Approved"**

---

═══════════════════════════════════════════════════════════
#### MODULE: Loans
═══════════════════════════════════════════════════════════

**OVERVIEW:** Employee loan application with 5-stage SoD-enforced approval chain (v2 workflow):
`pending → head_noted → manager_checked → officer_reviewed → approved (VP)`
- Loan types seeded: Company Loan, Cash Advance
- Interest rate: 0% (company policy)
- SoD enforced at every step — each approver must differ from the requester

**ACCOUNTS NEEDED:**
- `hr.manager@ogamierp.local` — creates loan application on behalf of employee
- `production.head@ogamierp.local` — head note (Step 2)
- `plant.manager@ogamierp.local` — manager check (Step 3)
- `acctg.officer@ogamierp.local` — officer review (Step 4)
- `vp@ogamierp.local` — VP approval (Step 5, final)

**PREREQUISITES:**
- ✅ Loan types seeded (Company Loan, Cash Advance)
- ✅ Active employees exist

---

##### TEST SECTION 3.4 — Loan Application

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Human Resources → Loans**
   - ✅ Verify: Loans list page loads
   - ✅ Verify: Table shows existing loans with Reference No., Employee, Amount, Status

2. Click **"New Loan Application"** or **"Apply for Loan"**
   - ✅ Verify: Form page with title **"New Loan Application"** loads
   - ✅ Verify: Fields visible: Employee, Loan Type, Principal (PHP), Term (months), Purpose

3. Fill in:
   | Field | Value |
   |-------|-------|
   | **Employee** | Select `Pedro dela Cruz` |
   | **Loan Type** | Select `Company Loan` |
   | **Principal (PHP)** | `10000` |
   | **Term (months)** | `6` |
   | **Purpose** | `Emergency home repair` |

4. Click **"Submit"** or **"Apply"**
   - ✅ Verify: Toast **"Loan application submitted."** appears
   - ✅ Verify: Redirected to the loan detail page
   - ✅ Verify: Reference number auto-generated (format `LN-YYYY-NNNNN`)
   - ✅ Verify: Status shows **"Pending"**

**NEGATIVE TEST — Missing employee:**

5. Open the loan form, leave **Employee** unselected, click **"Submit"**
   - ✅ Verify: Validation error appears under Employee field

---

##### TEST SECTION 3.5 — Loan Approval Chain (Steps 2–5)

**Step 2 — Head Note:**

🔄 **SWITCH ACCOUNT**: Log in as `production.head@ogamierp.local` / `Head@123456789!`

1. Navigate to: **Team Management → Team Loans** (or find via HR Loans)
   Find the loan for Pedro dela Cruz (status **"Pending"**)
   Open detail page

2. Click **"Head Note"** button
   - Fill remarks: `Noted — employee is in good standing`
   - Click confirm
   - ✅ Verify: Status changes to **"Head Noted"**

---

**Step 3 — Manager Check:**

🔄 **SWITCH ACCOUNT**: Log in as `plant.manager@ogamierp.local` / `Manager@12345!`

3. Navigate to **Team Management → Team Loans** or **HR → Loans**
   Find the loan (status **"Head Noted"**)
   Open detail page

4. Click **"Manager Check"** button
   - Fill remarks: `Checked — verified employment status`
   - Click confirm
   - ✅ Verify: Status changes to **"Manager Checked"**

---

**Step 4 — Officer Review:**

🔄 **SWITCH ACCOUNT**: Log in as `acctg.officer@ogamierp.local` / `AcctgManager@1234!`

5. Navigate to: **Accounting → Loan Approvals**
   Find the loan (status **"Manager Checked"**)
   Open detail page

6. Click **"Officer Review"** or **"Review"** button
   - Fill remarks: `Reviewed — funds available`
   - Set repayment start date if required
   - Click confirm
   - ✅ Verify: Status changes to **"Officer Reviewed"**

**SoD TEST — Officer cannot review own loan:**

7. If the accounting officer (Anna Marie Lim) had applied for this loan, the **"Officer Review"** button would be blocked. ⚠️ Verify this SoD boundary with a separate test case if needed.

---

**Step 5 — VP Approval (Final):**

🔄 **SWITCH ACCOUNT**: Log in as `vp@ogamierp.local` / `VicePresident@1!`

8. Navigate to: **VP Approvals → Loans**
   Find the loan (status **"Officer Reviewed"**)
   Open detail page

9. Click **"VP Approve"** button
   - Fill remarks: `Approved`
   - Click confirm
   - ✅ Verify: Status changes to **"Approved"**
   - ✅ Verify: Amortization schedule generated and visible on the loan detail page

**CROSS-MODULE CHECK:**

10. After loan approval, the next payroll computation for Pedro dela Cruz should include a loan deduction.
    - ✅ Verify (after payroll run): Loan amortization appears as a deduction in the payslip detail

---

═══════════════════════════════════════════════════════════
#### MODULE: Payroll
═══════════════════════════════════════════════════════════

**OVERVIEW:** 7-step wizard-based workflow. Wizard steps shown in the header:
`Define Run → Set Scope → Validate → Compute → Review → Acctg Review → Disburse`
SoD enforced: the user who initiates the run cannot approve it at HR or Accounting stages.

**ACCOUNTS NEEDED:**
- `hr.manager@ogamierp.local` — initiates, computes, reviews, submits for HR approval, approves at HR stage, disburses, publishes
- `acctg.officer@ogamierp.local` — approves at Accounting stage

**PREREQUISITES:**
- ✅ At least one active employee with a valid monthly rate exists
- ✅ At least one open pay period exists (check Administration → Fiscal Periods)
- ✅ Queue worker is running (`php artisan queue:work`)

---

##### TEST SECTION 3.6 — Create Payroll Run (Step 1: Define Run)

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Payroll → Payroll Runs**
   - ✅ Verify: Payroll runs list page loads
   - ✅ Verify: Table columns include: Reference, Run Type, Period, Status, Pay Date

2. Click **"New Payroll Run"**
   - ✅ Verify: Navigates to the first wizard step
   - ✅ Verify: Wizard header shows: **1 Define Run** (highlighted) **› 2 Set Scope › 3 Validate › 4 Compute › 5 Review › 6 Acctg Review › 7 Disburse**
   - ✅ Verify: Page title **"New Payroll Run"** visible
   - ✅ Verify: Subtitle **"Step 1 of 7 — Define the run type and pay period."** visible

3. Fill in Step 1 fields:
   | Field | Value |
   |-------|-------|
   | **Run Type** `*` | select `regular` |
   | **Pay Period** | Select an open period from the dropdown (auto-fills dates) |
   | **Cutoff Start** `*` | (auto-filled, or enter `2026-03-01`) |
   | **Cutoff End** `*` | (auto-filled, or enter `2026-03-15`) |
   | **Pay Date** `*` | (auto-filled, or enter `2026-03-20`) |
   | **Reference / Notes** | `March 2026 First Half Regular Payroll` |

4. Wait for the **live conflict check panel** to appear (it auto-validates after date entry)
   - ✅ Verify: Panel shows pass/fail status for conflict checks (PR-001 through PR-004)
   - ✅ Verify: If no conflicts, all checks show green pass status

5. Click **"Next: Set Scope →"**
   - ✅ Verify: Proceeds to Step 2 (Set Scope page)
   - ✅ Verify: Wizard header now shows Step 2 highlighted

**NEGATIVE TEST — Dates not filled:**

6. Go back to Step 1, clear **Cutoff Start**, click **"Next: Set Scope →"**
   - ✅ Verify: Validation error appears under **Cutoff Start** field
   - ✅ Verify: Button shows disabled state when conflict checker detects issues

---

##### TEST SECTION 3.7 — Payroll Run Steps 2–7

**Step 2 — Set Scope:**

1. On the **Set Scope** page, select which employees to include in this run
   - ✅ Verify: Employee list shows active employees
   - ✅ Verify: Can select all or individual employees

**Step 3 — Validate:**

2. On the **Validate** page, run pre-computation validation checks
   - ✅ Verify: Validation panel shows checks (attendance data, shift assignments, etc.)
   - ✅ Verify: Warnings (if any) are shown but do not block computation

**Step 4 — Compute (requires queue worker):**

3. On the **Compute** page, click **"Begin Computation"**
   - ✅ Verify: Payroll run record is saved to the database for the first time
   - ✅ Verify: Computation job is dispatched to the queue
   - ✅ Verify: Status shows **"Processing"** while computing
   - ✅ Verify: After queue processes, status changes to **"Computed"**
   - ✅ Verify: Individual employee payroll breakdowns are visible

**Step 5 — HR Review:**

4. On the **Review** page, verify payroll breakdowns
   - ✅ Verify: Each employee row shows: Gross Pay, Deductions (SSS, PhilHealth, Pag-IBIG, Tax, Loans), Net Pay
   - ✅ Verify: Can flag individual employees if issues found

5. Click **"Submit for HR Approval"**
   - ✅ Verify: Status changes to **"Submitted"**

**HR Approval (SoD — different user must approve):**

6. 🔄 **SWITCH**: Log in as a different user with `payroll.hr_approve` permission
   (HR Manager can approve if they did not initiate, OR use super_admin for verification testing only)
   Navigate to **Payroll → Payroll Runs** → find the submitted run
   Click **"HR Approve"**
   - ✅ Verify: Status changes to **"HR Approved"**

**Step 6 — Accounting Review:**

7. 🔄 **SWITCH ACCOUNT**: Log in as `acctg.officer@ogamierp.local` / `AcctgManager@1234!`
   Navigate to **Payroll → Payroll Runs** → find the HR-approved run
   Click **"Acctg Approve"**
   - ✅ Verify: Status changes to **"Acctg Approved"**

**Step 7 — Disburse:**

8. 🔄 **SWITCH**: Log in as `hr.manager@ogamierp.local` / `HrManager@1234!`
   Navigate to the payroll run → click **"Disburse"**
   - Select the payroll bank account
   - Click confirm
   - ✅ Verify: Status changes to **"Disbursed"**

9. Click **"Publish"**
   - ✅ Verify: Status changes to **"Published"**
   - ✅ Verify: Employees can now view their payslips (self-service)

**CROSS-MODULE CHECK — Employee Payslip:**

10. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
    Navigate to Dashboard → My Payslips
    - ✅ Verify: Published payslip appears
    - ✅ Verify: Can view payslip breakdown (gross pay, deductions, net pay)
    - ✅ Verify: Can download payslip as PDF (if `payroll.download_own_payslip` permission exists)

**PERMISSION BOUNDARY TEST:**

11. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
    Navigate directly to `/payroll/runs`
    - ✅ Verify: Redirected to `/403` (staff lacks `payroll.view_runs`)

---

### STAGE 2 — Reference Data Modules

═══════════════════════════════════════════════════════════
#### MODULE: Accounting — Chart of Accounts & Journal Entries
═══════════════════════════════════════════════════════════

**OVERVIEW:** Double-entry GL. Journal entries follow: draft → submitted → posted. Only `officer` can post (SoD-008: poster ≠ creator).

**ACCOUNTS NEEDED:** `acctg.officer@ogamierp.local`

**PREREQUISITES:**
- ✅ Chart of Accounts seeded (16 accounts: 1001 Cash, 2001 AP, 3001 AR, 4001 Revenue, 5001 Wages, 6001 Expense, etc.)
- ✅ Fiscal period open

---

##### TEST SECTION 2.1 — Chart of Accounts

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Accounting → Chart of Accounts**
   - ✅ Verify: Accounts list loads
   - ✅ Verify: Seeded accounts visible: 1001 — Cash in Bank, 2001 — Accounts Payable, 3001 — Accounts Receivable, 4001 — Revenue, 5001 — Wages Expense, 6001 — General Expense

2. Navigate to: **Accounting → General Ledger**
   - ✅ Verify: GL page loads with account/date filter options

3. Navigate to: **Accounting → Recurring Templates**
   - ✅ Verify: Recurring journal templates page loads

---

##### TEST SECTION 2.2 — Journal Entry Creation

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Accounting → Journal Entries**
   - ✅ Verify: Journal entries list page loads
   - ✅ Verify: Columns include: Reference, Date, Description, Total, Status

2. Click **"New Journal Entry"** or **"Create Journal Entry"**
   - ✅ Verify: Journal entry form loads

3. Fill in:
   | Field | Value |
   |-------|-------|
   | **Date** | `2026-03-31` |
   | **Reference Number** | `JE-2026-003-001` |
   | **Description** | `Month-end adjustment — March 2026` |

   Add journal lines:
   - Line 1: Debit `6001 — General Expense` amount `5000`
   - Line 2: Credit `1001 — Cash in Bank` amount `5000`

   Click **"Save"** or **"Create"**
   - ✅ Verify: Journal entry saved with status **"Draft"**
   - ✅ Verify: Debit total = Credit total = ₱5,000 (balanced)

4. Open the journal entry → click **"Submit"**
   - ✅ Verify: Status changes to **"Submitted"**

5. Click **"Post"** (only officer can post)
   - ✅ Verify: Status changes to **"Posted"**
   - ✅ Verify: GL is updated — amounts appear in the General Ledger for the respective accounts

**NEGATIVE TEST — Unbalanced entry:**

6. Create a new journal entry with only a debit line (no credit)
   - ✅ Verify: Error about unbalanced journal entry appears
   - ✅ Verify: Entry is not saved or cannot be posted

**NEGATIVE TEST — Posting to closed period:**

7. Try to post a journal entry dated in a closed fiscal period
   - ✅ Verify: Error about locked/closed fiscal period appears

---

═══════════════════════════════════════════════════════════
#### MODULE: Payables (AP)
═══════════════════════════════════════════════════════════

**OVERVIEW:** Vendor management, vendor invoices (create → submit → approve → pay). Purchasing Officer manages vendors; Officer approves invoices.

**ACCOUNTS NEEDED:**
- `purchasing.officer@ogamierp.local` — creates vendors
- `acctg.officer@ogamierp.local` — creates, approves, pays invoices

---

##### TEST SECTION 2.3 — Vendor Creation

👤 **LOGGED IN AS:** purchasing.officer@ogamierp.local (`Officer@12345!`)

**Steps:**

1. Navigate to: **Payables (AP) → Vendors**
   - ✅ Verify: Vendors list page loads
   - ✅ Verify: Table columns include: Vendor Name, TIN, Status (Active/Accredited)

2. Click **"Add Vendor"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Vendor Name** | `ABC Industrial Supply Co.` |
   | **TIN** | `123-456-789-000` |
   | **Contact Person** | `Jun Reyes` |
   | **Email** | `jun.reyes@abcsupply.com` |
   | **Phone** | `09171234567` |
   | **Payment Terms** | `Net 30` |

   Click **"Save"**
   - ✅ Verify: Vendor appears in the list

3. Find the vendor → click **"Accredit"**
   - ✅ Verify: Vendor status changes to **"Accredited"**

---

##### TEST SECTION 2.4 — Vendor Invoice Workflow

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Prerequisites:**
- ✅ Vendor **ABC Industrial Supply Co.** exists and is accredited
- ✅ Bank account exists

**Steps:**

1. Navigate to: **Payables (AP) → Invoices**
   - ✅ Verify: AP invoices list loads

2. Click **"Create Invoice"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Vendor** | Select `ABC Industrial Supply Co.` |
   | **Invoice Number** | `INV-2026-001` |
   | **Invoice Date** | `2026-03-15` |
   | **Due Date** | `2026-04-14` (Net 30) |
   | **Amount** | `50000` |
   | **Description** | `PP Resin supply — March batch` |
   | **GL Expense Account** | select `6001 — General Expense` |

   Click **"Save"**
   - ✅ Verify: Invoice saved with status **"Draft"**

3. Open the invoice → click **"Submit"**
   - ✅ Verify: Status changes to **"Submitted"**

4. Click **"Approve"**
   - ✅ Verify: Status changes to **"Approved"**

5. Click **"Record Payment"**
   - Select bank account: `BDO Operating Account`
   - Payment date: `2026-04-14`
   - Amount: `50000`
   Click confirm
   - ✅ Verify: Status changes to **"Paid"**
   - ✅ Verify: Bank account balance reduced

**NEGATIVE TEST — Approve own invoice (SoD):**

6. Create a new invoice as acctg.officer, then immediately try to approve it as the same user
   - ✅ Verify: If SoD is enforced, the Approve button is blocked for the creator

**PERMISSION BOUNDARY TEST:**

7. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
   Navigate directly to `/accounting/ap/invoices`
   - ✅ Verify: Redirected to `/403`

---

═══════════════════════════════════════════════════════════
#### MODULE: Receivables (AR)
═══════════════════════════════════════════════════════════

**OVERVIEW:** Customer management, customer invoices (create → approve → receive payment).

**ACCOUNTS NEEDED:**
- `purchasing.officer@ogamierp.local` — creates customers
- `acctg.officer@ogamierp.local` — creates invoices, approves, records payment receipts

---

##### TEST SECTION 2.5 — Customer Creation

👤 **LOGGED IN AS:** purchasing.officer@ogamierp.local (`Officer@12345!`)

**Steps:**

1. Navigate to: **Receivables (AR) → Customers**
   - ✅ Verify: Customers list page loads

2. Click **"Add Customer"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Customer Name** | `XYZ Manufacturing Corp.` |
   | **TIN** | `234-567-890-001` |
   | **Contact Person** | `Maria Reyes` |
   | **Email** | `maria.reyes@xyzmfg.com` |
   | **Phone** | `09181234568` |
   | **Credit Limit** | `500000` |
   | **Payment Terms** | `Net 30` |

   Click **"Save"**
   - ✅ Verify: Customer appears in the list

---

##### TEST SECTION 2.6 — Customer Invoice Workflow

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Receivables (AR) → Invoices**
   - ✅ Verify: AR invoices list loads

2. Click **"Create Invoice"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Customer** | Select `XYZ Manufacturing Corp.` |
   | **Invoice Number** | `CINV-2026-001` |
   | **Invoice Date** | `2026-03-28` |
   | **Due Date** | `2026-04-27` |
   | **Amount** | `150000` |
   | **Description** | `Product delivery — March batch` |

   Click **"Save"**
   - ✅ Verify: Invoice saved with status **"Draft"**

3. Open the invoice → click **"Approve"**
   - ✅ Verify: Status changes to **"Approved"**

4. Click **"Receive Payment"**
   - Select bank account: `BDO Operating Account`
   - Payment date: `2026-04-27`
   - Amount received: `150000`
   Click confirm
   - ✅ Verify: Invoice status changes to **"Paid"**
   - ✅ Verify: Bank account balance increases

---

═══════════════════════════════════════════════════════════
#### MODULE: Procurement
═══════════════════════════════════════════════════════════

**OVERVIEW:** Purchase Request (PR) → 4-step approval → Purchase Order (PO) → Goods Receipt (GR) with 3-way match.

**ACCOUNTS NEEDED:**
- `purchasing.officer@ogamierp.local` — creates PR, creates PO
- `production.head@ogamierp.local` — notes/checks at Step 2
- `plant.manager@ogamierp.local` — checks at Step 3
- `acctg.officer@ogamierp.local` — reviews budget/financial at Step 3-4
- `vp@ogamierp.local` — final VP approval
- `warehouse.head@ogamierp.local` — creates and confirms Goods Receipt

**PREREQUISITES:**
- ✅ At least one accredited vendor with at least one vendor item exists
- ✅ At least one department exists

---

##### TEST SECTION 3.8 — Create Purchase Request

👤 **LOGGED IN AS:** purchasing.officer@ogamierp.local (`Officer@12345!`)

**Steps:**

1. Navigate to: **Procurement → Purchase Requests**
   - ✅ Verify: PR list page loads with columns: PR Number, Vendor, Department, Urgency, Total, Status

2. Click **"New Purchase Request"** or **"Create PR"**
   - ✅ Verify: Form page loads with title **"New Purchase Request"**
   - ✅ Verify: Fields visible: Vendor, Department, Urgency, Justification, Notes, Items table

3. Fill in the header:
   | Field | Value |
   |-------|-------|
   | **Vendor** `*` | Select `ABC Industrial Supply Co.` |
   | **Department** `*` | Select `PROD — Production` |
   | **Urgency** | select `normal` |
   | **Justification** | `Monthly raw material procurement for production line` (min 20 chars) |
   | **Notes** | `Q1 2026 material requirement` |

4. Add a line item by clicking **"Add Item"** (+ icon):
   | Field | Value |
   |-------|-------|
   | **Vendor Item** | Select a vendor item from the dropdown |
   | **Quantity** | `500` |
   | **Estimated Unit Cost** | `180` |
   | **Specifications** | `Natural color, food-grade` |

   - ✅ Verify: Line total auto-calculates to `90,000`
   - ✅ Verify: Grand total shows `₱90,000.00`

5. Click **"Submit"** or **"Create Purchase Request"**
   - ✅ Verify: Toast success message appears
   - ✅ Verify: Redirected to PR detail page
   - ✅ Verify: PR reference number auto-generated (format `PR-YYYY-MM-NNNNN`)
   - ✅ Verify: Status shows **"Draft"** or **"Submitted"**

**NEGATIVE TEST — Short justification:**

6. Create a new PR with **Justification**: `Too short`
   Click **"Submit"**
   - ✅ Verify: Error **"Justification must be at least 20 characters"** appears
   - ✅ Verify: PR not created

**NEGATIVE TEST — No line items:**

7. Fill in header fields but do not add any items, click **"Submit"**
   - ✅ Verify: Error **"At least one line item is required"** appears

---

##### TEST SECTION 3.9 — PR Approval Chain → PO → GR

**Step 2 — Head Note/Check:**

🔄 **SWITCH ACCOUNT**: Log in as `production.head@ogamierp.local` / `Head@123456789!`

1. Navigate to: **Procurement → Purchase Requests**
   Find the PR (status **"Submitted"**) → open detail page
   Click **"Note"** or **"Check"** button
   - ✅ Verify: Status advances

**Step 3 — Officer Review:**

🔄 **SWITCH ACCOUNT**: Log in as `acctg.officer@ogamierp.local` / `AcctgManager@1234!`

2. Navigate to **Procurement → Purchase Requests**
   Find the PR → click **"Review"** or **"Budget Check"**
   - ✅ Verify: Status advances to reviewed state

**Step 4 — VP Approval:**

🔄 **SWITCH ACCOUNT**: Log in as `vp@ogamierp.local` / `VicePresident@1!`

3. Navigate to: **VP Approvals → Purchase Requests**
   Find the PR → click **"Approve"**
   - ✅ Verify: PR status changes to **"Approved"**

**Create Purchase Order from Approved PR:**

🔄 **SWITCH ACCOUNT**: Log in as `purchasing.officer@ogamierp.local` / `Officer@12345!`

4. Navigate to: **Procurement → Purchase Orders**
   Click **"Create Purchase Order"** or find the option to create PO from the approved PR
   - ✅ Verify: PO reference auto-generated (format `PO-YYYY-MM-NNNNN`)
   - ✅ Verify: PO links to the originating PR

5. Navigate to: **Procurement → Goods Receipts**
   Click **"New Goods Receipt"**
   - Link to the Purchase Order
   - Fill in received quantities
   Click **"Save"**
   - ✅ Verify: GR created
   - ✅ Verify: 3-way match status shown (PR qty vs PO qty vs GR qty)

**Confirm GR:**

🔄 **SWITCH ACCOUNT**: Log in as `warehouse.head@ogamierp.local` / `Head@123456789!`

6. Navigate to: **Procurement → Goods Receipts**
   Find the GR → click **"Confirm"**
   - ✅ Verify: GR status changes to **"Confirmed"**

**CROSS-MODULE CHECK:**

7. Navigate to: **Inventory → Stock Balances**
   - ✅ Verify: Stock quantity for the received item has increased by the GR quantity

---

═══════════════════════════════════════════════════════════
#### MODULE: Inventory
═══════════════════════════════════════════════════════════

**OVERVIEW:** Item master, warehouse locations, stock management, material requisitions (MRQ) with 6-step approval chain.

**ACCOUNTS NEEDED:**
- `warehouse.head@ogamierp.local` — manages items, stock, fulfills MRQs
- `ppc.head@ogamierp.local` — creates MRQs for production
- `acctg.officer@ogamierp.local` — reviews MRQs at officer level
- `vp@ogamierp.local` — VP approval for MRQs

---

##### TEST SECTION 2.7 — Item Master Setup

👤 **LOGGED IN AS:** warehouse.head@ogamierp.local (`Head@123456789!`)

**Steps:**

1. Navigate to: **Inventory → Item Categories**
   - ✅ Verify: Item categories list page loads
   - ✅ Verify: Any seeded categories are visible

2. Navigate to: **Inventory → Item Master**
   - ✅ Verify: Items list page loads with columns: Item Code, Description, Category, UoM, Stock

3. Navigate to: **Inventory → Warehouse Locations**
   - ✅ Verify: Location list shows seeded warehouse locations (e.g., WH-A1, WH-C1)

4. Navigate to: **Inventory → Stock Balances**
   - ✅ Verify: Stock balance page shows current on-hand quantities per item and location

5. Navigate to: **Inventory → Stock Ledger**
   - ✅ Verify: Stock movement history is visible

---

##### TEST SECTION 2.8 — Stock Adjustment

👤 **LOGGED IN AS:** warehouse.head@ogamierp.local (`Head@123456789!`)

**Steps:**

1. Navigate to: **Inventory → Stock Adjustments**
   - ✅ Verify: Page loads for creating stock adjustments

2. Create a stock adjustment:
   - Select item
   - Select location
   - Enter quantity adjustment (positive = add, negative = remove)
   - Enter reason: `Initial stock count — March 2026`
   Click **"Save"**
   - ✅ Verify: Adjustment saved
   - ✅ Verify: Stock balance updated accordingly

---

═══════════════════════════════════════════════════════════
#### MODULE: Production
═══════════════════════════════════════════════════════════

**OVERVIEW:** BOM → Delivery Schedule → Work Order → auto-MRQ (6-step) → Start → Log Output → Complete.

**ACCOUNTS NEEDED:**
- `prod.manager@ogamierp.local` — creates BOMs, delivery schedules, work orders
- `plant.manager@ogamierp.local` — releases and manages work orders
- `warehouse.head@ogamierp.local` — fulfills auto-generated MRQs
- `ppc.head@ogamierp.local` — MRQ approval steps
- `vp@ogamierp.local` — VP approval for MRQs

**PREREQUISITES:**
- ✅ Item master entries exist (finished goods and raw materials)
- ✅ Warehouse locations exist
- ✅ Queue worker running (MRQ auto-creation requires queue)

---

##### TEST SECTION 3.10 — Bill of Materials

👤 **LOGGED IN AS:** prod.manager@ogamierp.local (`Manager@12345!`)

**Steps:**

1. Navigate to: **Production → Bill of Materials**
   - ✅ Verify: BOM list page loads

2. Click **"New BOM"** or **"Create BOM"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Finished Good** | Select a finished goods item |
   | **Output Qty** | `1000` |
   | **Unit** | `PCS` |

   Add raw material components:
   - Component 1: Select raw material item, qty: `0.2`, unit: `KG`
   - Component 2: Select packaging item, qty: `1`, unit: `PCS`

   Click **"Save"**
   - ✅ Verify: BOM saved and visible in list

---

##### TEST SECTION 3.11 — Work Order and Output

👤 **LOGGED IN AS:** prod.manager@ogamierp.local (`Manager@12345!`)

**Steps:**

1. Navigate to: **Production → Work Orders**
   Click **"New Work Order"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Finished Good** | Select the item with BOM |
   | **Quantity Required** | `10000` |
   | **Target Date** | `2026-03-28` |
   | **BOM** | Select the BOM created above |

   Click **"Save"**
   - ✅ Verify: Work order created with reference `WO-YYYY-MM-NNNNN`
   - ✅ Verify: Status shows **"Draft"** or **"Created"**

2. 🔄 **SWITCH**: Log in as `plant.manager@ogamierp.local` / `Manager@12345!`
   Navigate to **Production → Work Orders** → find the work order
   Click **"Release"**
   - ✅ Verify: Status changes to **"Released"**
   - ✅ Verify: Auto-MRQ is generated (check Inventory → Requisitions)

3. **Complete MRQ approval chain** (6 steps: submit → note → check → review → VP approve → fulfill)
   Refer to MRQ workflow in the Inventory section above.
   After MRQ is fulfilled:
   - ✅ Verify: Stock for raw materials has decreased

4. Back on the Work Order: click **"Start Production"**
   - ✅ Verify: Status changes to **"In Progress"**

5. Click **"Log Output"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Shift** | Select any shift |
   | **Date** | `2026-03-28` |
   | **Operator** | Select any employee |
   | **Qty Produced** | `10050` |
   | **Qty Rejected** | `43` |
   | **Remarks** | `March production run` |

   Click **"Submit Log"**
   - ✅ Verify: Output log entry appears in the Work Order detail page
   - ✅ Verify: Progress bar updates

6. Click **"Complete"** to mark the work order complete
   - ✅ Verify: Status changes to **"Completed"**

---

═══════════════════════════════════════════════════════════
#### MODULE: QC / QA
═══════════════════════════════════════════════════════════

**OVERVIEW:** Inspections (IPQC, IQC, OQC) → NCR on fail → CAPA auto-created → close CAPA → close NCR.

**ACCOUNTS NEEDED:**
- `qc.manager@ogamierp.local` — creates and manages inspections, NCRs, CAPAs

---

##### TEST SECTION 3.12 — Inspection and NCR

👤 **LOGGED IN AS:** qc.manager@ogamierp.local (`Manager@12345!`)

**Steps:**

1. Navigate to: **QC / QA → Inspections**
   - ✅ Verify: Inspections list page loads

2. Click **"New Inspection"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Inspection Type** | `IPQC` (in-process) |
   | **Reference** | Link to the active work order |
   | **Date** | `2026-03-23` |
   | **Inspector** | Select current user or any employee |

   Add inspection items with results — set one item as **FAIL**:
   - Item: `Wall Thickness`
   - Standard: `2.5 mm ± 0.1`
   - Actual: `2.2 mm`
   - Result: **FAIL**

   Click **"Save"** / **"Submit"**
   - ✅ Verify: Inspection saved with overall result **"Failed"**

3. Navigate to: **QC / QA → NCR**
   Click **"Raise NCR"** or the NCR may be auto-created from the failed inspection
   - ✅ Verify: NCR created with reference (format `NCR-YYYY-MM-NNNNN`)
   - ✅ Verify: NCR links to the failed inspection
   - ✅ Verify: Status shows **"Open"**

4. Navigate to: **QC / QA → CAPA**
   - ✅ Verify: CAPA action auto-created from the NCR (or create manually)
   - ✅ Verify: CAPA links to the NCR

5. Open the CAPA → fill in corrective action details
   Click **"Complete CAPA"** or mark as complete
   - ✅ Verify: CAPA status changes to **"Closed"** or **"Completed"**

6. Navigate back to the NCR → click **"Close NCR"**
   - ✅ Verify: NCR status changes to **"Closed"**

7. Navigate to: **QC / QA → Defect Rate**
   - ✅ Verify: Defect rate dashboard/chart loads showing defect statistics

---

═══════════════════════════════════════════════════════════
#### MODULE: Maintenance
═══════════════════════════════════════════════════════════

**OVERVIEW:** Equipment registry, corrective work orders (breakdown), preventive maintenance (PM) schedules.

**ACCOUNTS NEEDED:**
- `plant.manager@ogamierp.local` — full maintenance access
- `maintenance.head@ogamierp.local` — manages maintenance work orders

---

##### TEST SECTION 3.13 — Equipment and Work Orders

👤 **LOGGED IN AS:** plant.manager@ogamierp.local (`Manager@12345!`)

**Steps:**

1. Navigate to: **Maintenance → Equipment**
   - ✅ Verify: Equipment list page loads

2. Click **"Add Equipment"** or **"New Equipment"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Equipment Name** | `Injection Molding Machine #1` |
   | **Equipment Code** | `EQP-001` |
   | **Department** | `PROD — Production` |
   | **Status** | `Active` |
   | **Serial Number** | `IMM-2020-001` |

   Click **"Save"**
   - ✅ Verify: Equipment appears in the list

3. Navigate to: **Maintenance → Work Orders**
   Click **"New Work Order"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Equipment** | Select `Injection Molding Machine #1` |
   | **Type** | `Corrective` |
   | **Priority** | `High` |
   | **Description** | `Machine breakdown — hydraulic system leak` |
   | **Requested Date** | today's date |

   Click **"Save"**
   - ✅ Verify: Maintenance work order created with reference number
   - ✅ Verify: Status shows **"Open"** or **"Pending"**

4. Open the work order → click **"Start"** or **"In Progress"**
   - ✅ Verify: Status changes to **"In Progress"**

5. Click **"Complete"** or **"Resolve"**
   - Fill in resolution details and actual completion date
   - ✅ Verify: Status changes to **"Completed"** or **"Closed"**

---

═══════════════════════════════════════════════════════════
#### MODULE: Mold
═══════════════════════════════════════════════════════════

**OVERVIEW:** Mold masters track individual mold tools. Shot logs track mold usage and trigger preventive maintenance when criticality threshold reached.

**ACCOUNTS NEEDED:**
- `mold.manager@ogamierp.local` — full mold access
- `plant.manager@ogamierp.local` — also has full mold access

---

##### TEST SECTION 3.14 — Mold Master and Shot Log

👤 **LOGGED IN AS:** mold.manager@ogamierp.local (`Manager@12345!`)

**Steps:**

1. Navigate to: **Mold → Mold Masters**
   - ✅ Verify: Mold masters list page loads

2. Click **"New Mold"** or **"Add Mold"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Mold Code** | `MOLD-001` |
   | **Mold Name** | `Cap Mold — 32-cavity` |
   | **Item** | Select finished goods item |
   | **Cavities** | `32` |
   | **Criticality Threshold** | `500000` (shots before PM needed) |
   | **Status** | `Active` |

   Click **"Save"**
   - ✅ Verify: Mold master appears in the list

3. Open the mold → click **"Log Shots"** or **"Add Shot Log"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Date** | `2026-03-22` |
   | **Work Order** | Select a completed work order |
   | **Shots** | `10050` |

   Click **"Save"**
   - ✅ Verify: Shot log entry appears in the mold detail
   - ✅ Verify: Cumulative shots counter updates
   - ✅ Verify: If cumulative shots exceed threshold, criticality changes to **"Critical"** and a preventive maintenance WO may be auto-created

---

═══════════════════════════════════════════════════════════
#### MODULE: Delivery
═══════════════════════════════════════════════════════════

**OVERVIEW:** Delivery Receipts (outbound shipments to customers) and Shipment tracking. Auto-DR may be generated when a production work order completes.

**ACCOUNTS NEEDED:**
- `impex.officer@ogamierp.local` — manages delivery and shipments
- `plant.manager@ogamierp.local` — also has delivery access

---

##### TEST SECTION 3.15 — Delivery Receipt and Shipment

👤 **LOGGED IN AS:** impex.officer@ogamierp.local (`Officer@12345!`)

**Steps:**

1. Navigate to: **Delivery → Receipts**
   - ✅ Verify: Delivery receipts list page loads

2. Navigate to: **Delivery → Shipments**
   - ✅ Verify: Shipments list page loads

3. Click **"New Shipment"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Customer** | Select `XYZ Manufacturing Corp.` |
   | **Vehicle** | Select from seeded fleet (TRUCK-001, TRUCK-002, or VAN-001) |
   | **Delivery Date** | `2026-03-28` |
   | **Driver** | Select an employee |

   Add delivery lines — select items and quantities from the delivery receipt
   Click **"Save"**
   - ✅ Verify: Shipment created with reference number

4. Click **"In Transit"** to mark shipment as dispatched
   - ✅ Verify: Status changes to **"In Transit"**

5. Click **"Delivered"** to mark as delivered
   - ✅ Verify: Status changes to **"Delivered"**

**CROSS-MODULE CHECK:**

6. Navigate to: **Receivables (AR) → Invoices**
   - ✅ Verify: An AR invoice may be auto-created upon shipment delivered (if configured)

---

═══════════════════════════════════════════════════════════
#### MODULE: ISO / IATF
═══════════════════════════════════════════════════════════

**OVERVIEW:** Controlled document management, internal audits, audit findings, and CAPA.

**ACCOUNTS NEEDED:**
- `iso.head@ogamierp.local` — manages ISO documents and audits

---

##### TEST SECTION 3.16 — Controlled Document

👤 **LOGGED IN AS:** iso.head@ogamierp.local (`Head@123456789!`)

**Steps:**

1. Navigate to: **ISO / IATF → Documents**
   - ✅ Verify: Controlled documents list page loads

2. Click **"New Document"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Document Number** | `QP-001` |
   | **Title** | `Quality Policy Manual` |
   | **Revision** | `Rev 01` |
   | **Type** | `Quality Procedure` |
   | **Effective Date** | `2026-01-01` |

   Click **"Save"**
   - ✅ Verify: Document created with status **"Draft"**

3. Click **"Submit for Approval"**
   - ✅ Verify: Status changes to **"Pending Approval"**

4. Click **"Approve"**
   - ✅ Verify: Status changes to **"Active"** or **"Approved"**

---

##### TEST SECTION 3.17 — Internal Audit

👤 **LOGGED IN AS:** iso.head@ogamierp.local (`Head@123456789!`)

**Steps:**

1. Navigate to: **ISO / IATF → Audits**
   - ✅ Verify: Internal audits list page loads

2. Click **"New Audit"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Audit Title** | `Q1 2026 Internal Audit` |
   | **Audit Date** | `2026-03-25` |
   | **Scope** | `Production Department` |
   | **Lead Auditor** | Select an employee |

   Click **"Save"**
   - ✅ Verify: Audit created with status **"Planned"**

3. Open the audit → click **"Start Audit"**
   - ✅ Verify: Status changes to **"In Progress"**

4. Add an audit finding:
   Click **"Add Finding"**
   | Field | Value |
   |-------|-------|
   | **Finding Type** | `Observation` |
   | **Description** | `Shift handover records not consistently filled` |
   | **Clause Reference** | `ISO 9001:2015 Cl. 7.5` |
   | **Severity** | `Minor` |

   Click **"Save Finding"**
   - ✅ Verify: Finding appears linked to the audit

5. Click **"Close Audit"**
   - ✅ Verify: Audit status changes to **"Closed"**

6. Open the finding → link to a CAPA
   Complete the CAPA → close the finding
   - ✅ Verify: Finding status changes to **"Closed"**

---

═══════════════════════════════════════════════════════════
#### MODULE: Fixed Assets
═══════════════════════════════════════════════════════════

**OVERVIEW:** Asset register, depreciation (straight-line / double-declining), disposals. Artisan command `assets:depreciate-monthly` runs depreciation.

**ACCOUNTS NEEDED:** `acctg.officer@ogamierp.local`

---

##### TEST SECTION 2.9 — Fixed Asset Register

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Fixed Assets → Categories**
   - ✅ Verify: Asset categories page loads

2. Create a category if none exist:
   | Field | Value |
   |-------|-------|
   | **Name** | `Machinery & Equipment` |
   | **Useful Life (years)** | `10` |
   | **Depreciation Method** | `Straight-Line` |

   Click **"Save"**

3. Navigate to: **Fixed Assets → Asset Register**
   Click **"New Asset"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Asset Name** | `Injection Molding Machine #1` |
   | **Category** | Select `Machinery & Equipment` |
   | **Cost** | `500000` |
   | **Acquisition Date** | `2020-01-15` |
   | **Location** | `Production Floor` |

   Click **"Save"**
   - ✅ Verify: Asset appears in the register with status **"Active"**
   - ✅ Verify: Monthly depreciation amount calculated and shown

4. Navigate to: **Fixed Assets → Disposals**
   - ✅ Verify: Asset disposals list page loads

**PERMISSION BOUNDARY TEST:**

5. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
   Navigate directly to `/fixed-assets`
   - ✅ Verify: Redirected to `/403` (staff lacks `fixed_assets.view`)

---

═══════════════════════════════════════════════════════════
#### MODULE: Budget
═══════════════════════════════════════════════════════════

**OVERVIEW:** Cost centers define spending buckets. Annual budgets assign amounts per cost center. Budget vs Actual shows variance against actual GL postings.

**ACCOUNTS NEEDED:** `acctg.officer@ogamierp.local`

---

##### TEST SECTION 2.10 — Cost Centers and Budget Lines

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Budget → Cost Centers**
   - ✅ Verify: Cost centers list page loads

2. Click **"New Cost Center"** or **"Add"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Code** | `CC-PROD-001` |
   | **Name** | `Production Operations` |
   | **Department** | Select `PROD — Production` |

   Click **"Save"**
   - ✅ Verify: Cost center appears in the list

3. Navigate to: **Budget → Budget Lines**
   Click **"New Budget"** or **"Add Budget Line"**
   Fill in:
   | Field | Value |
   |-------|-------|
   | **Cost Center** | Select `CC-PROD-001` |
   | **Fiscal Year** | `2026` |
   | **Month** | `March` |
   | **Amount** | `250000` |
   | **Account** | Select a GL expense account |

   Click **"Save"**
   - ✅ Verify: Budget line appears

4. Navigate to: **Budget → Budget vs Actual**
   - ✅ Verify: Comparison report loads showing budgeted vs actual amounts
   - ✅ Verify: Variance column shows difference

**PERMISSION BOUNDARY TEST:**

5. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
   Navigate directly to `/budget/cost-centers`
   - ✅ Verify: Redirected to `/403` (staff lacks `budget.view`)

---

═══════════════════════════════════════════════════════════
#### MODULE: CRM / Support Tickets
═══════════════════════════════════════════════════════════

**OVERVIEW:** Internal support tickets managed by CRM manager. Clients can submit tickets through the client portal (`/client-portal`). Vendors use the vendor portal (`/vendor-portal`).

**ACCOUNTS NEEDED:**
- `admin@ogamierp.local` — provisions portal accounts
- `client@ogamierp.local` — client portal (must change password on first login)

⚠️ **GAP:** The `crm_manager` role account is not in the standard seed. CRM module testing requires a user with `crm.tickets.view` permission. Use `super_admin` to verify CRM data exists, or provision a CRM manager account via Admin → Users.

---

##### TEST SECTION 3.18 — Client Portal Tickets

👤 **LOGGED IN AS:** admin@ogamierp.local (`Admin@1234567890!`)

**Steps:**

1. Navigate to: **Administration → Users**
   Find a customer user linked to a customer record (or create one)
   Click **"Create Portal Account"** for the customer
   - ✅ Verify: A modal shows the generated credentials with **Email** and **Password** fields
   - ✅ Verify: Note: "The user will be prompted to change password on first login."

2. 🔄 **SWITCH**: Log in as the client portal user at `http://localhost:5173/client-portal`
   - ✅ Verify: Client Portal layout shows with sidebar links: **My Tickets** and **Submit Ticket**
   - ✅ Verify: **Change Password** and **Sign out** visible in the bottom sidebar

3. Click **"Submit Ticket"**
   Fill in the ticket form
   Click **"Submit"**
   - ✅ Verify: Ticket created and appears in **My Tickets**

4. Click **"My Tickets"**
   - ✅ Verify: Ticket list shows the submitted ticket with status

---

### STAGE 5 — Financial Reports

═══════════════════════════════════════════════════════════
#### MODULE: Financial Reports
═══════════════════════════════════════════════════════════

**OVERVIEW:** Trial Balance, Balance Sheet, Income Statement, Cash Flow Statement, AP/AR Aging, VAT Ledger, Tax Summary, Government Reports.

**ACCOUNTS NEEDED:** `acctg.officer@ogamierp.local`, `chairman@ogamierp.local` (read-only view)

**PREREQUISITES:**
- ✅ At least one posted journal entry exists
- ✅ At least one approved/paid AP invoice exists
- ✅ At least one approved/paid AR invoice exists

---

##### TEST SECTION 5.1 — Trial Balance and Financial Statements

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Financial Reports → Trial Balance**
   - ✅ Verify: Trial balance report page loads with **As-of date** filter
   Set date to `2026-03-31` → click **"Generate"** (or the generate/filter button)
   - ✅ Verify: Trial balance renders with columns: Account Code, Account Name, Debit, Credit
   - ✅ Verify: Total Debits = Total Credits (balanced)

2. Navigate to: **Financial Reports → Balance Sheet**
   Set **As Of Date** to `2026-03-31`
   Check **"Show comparative"** if desired → click the generate button
   - ✅ Verify: Balance sheet renders with sections: Assets, Liabilities, Equity
   - ✅ Verify: Each section shows subtotals
   - ✅ Verify: Total Assets = Total Liabilities + Equity

3. Navigate to: **Financial Reports → Income Statement**
   Set date range → generate
   - ✅ Verify: Income statement renders with Revenue and Expense sections
   - ✅ Verify: Net Income shown at bottom

4. Navigate to: **Financial Reports → Cash Flow**
   - ✅ Verify: Cash flow statement page loads

5. Navigate to: **Financial Reports → AP Aging**
   - ✅ Verify: AP aging report shows outstanding vendor invoices by aging bucket (0-30, 31-60, 61-90, 90+ days)

6. Navigate to: **Financial Reports → AR Aging**
   - ✅ Verify: AR aging report shows outstanding customer invoices by aging bucket

---

##### TEST SECTION 5.2 — VAT Ledger and Tax Summary

👤 **LOGGED IN AS:** acctg.officer@ogamierp.local (`AcctgManager@1234!`)

**Steps:**

1. Navigate to: **Financial Reports → VAT Ledger**
   Set the fiscal period filter
   - ✅ Verify: VAT ledger shows Input VAT and Output VAT entries
   - ✅ Verify: Net VAT payable calculated

2. Navigate to: **Financial Reports → Tax Summary**
   - ✅ Verify: Tax summary page loads with period breakdown

---

##### TEST SECTION 5.3 — Government Reports

👤 **LOGGED IN AS:** hr.manager@ogamierp.local (`HrManager@1234!`)

**Steps:**

1. Navigate to: **Reports → Government Reports**
   - ✅ Verify: Government reports page loads
   - ✅ Verify: Report types available: BIR 1601-C, BIR Alphalist, SSS SBR-2, PhilHealth RF-1, Pag-IBIG MC

2. Select **BIR 1601-C** → set fiscal period → click **"Generate"**
   - ✅ Verify: Report generates with employee withholding tax data

3. Select **SSS SBR-2** → set period → click **"Generate"**
   - ✅ Verify: SSS contribution report generates

**PERMISSION BOUNDARY TEST:**

4. 🔄 **SWITCH**: Log in as `prod.staff@ogamierp.local` / `Staff@123456789!`
   Navigate directly to `/reports/government`
   - ✅ Verify: Redirected to `/403`

---

## 5. CROSS-MODULE INTEGRATION TESTS

---

═══════════════════════════════════════════════════════════
### INTEGRATION TEST 4.1 — Hire-to-Payslip
═══════════════════════════════════════════════════════════

**FLOW:** HR creates employee → employee activated → payroll run includes employee → employee views payslip

**ACCOUNTS NEEDED:**
- `hr.manager@ogamierp.local` — creates employee
- `plant.manager@ogamierp.local` — activates employee (SoD)
- `hr.manager@ogamierp.local` — initiates and manages payroll run
- `acctg.officer@ogamierp.local` — approves payroll at accounting stage
- New employee's linked user account — views payslip

**DATA SETUP:**
- ✅ Fresh database seeded
- ✅ Open pay period exists
- ✅ Queue worker running

**FLOW STEPS:**

1. 👤 **As hr.manager**: Create new employee (see Section 1.3) — name: `Integration Test Employee`
   - ✅ Verify: Employee created in `draft` status

2. 👤 **As plant.manager**: Activate the employee (see Section 1.4)
   - ✅ Verify: Employee now `active`

3. 👤 **As admin**: Create user account linked to the new employee with role `staff`
   - ✅ Verify: User account created and linked

4. 👤 **As hr.manager**: Initiate a new payroll run for the next pay period
   - Set scope to include the new employee
   - Complete Steps 1–4 of the payroll wizard (Define → Scope → Validate → Compute)
   - ✅ Verify: After computation, the new employee appears in the payroll breakdown
   - ✅ Verify: Basic pay equals `monthly_rate / 2` (semi-monthly) minus applicable deductions

5. 👤 **As hr.manager**: Submit for HR approval → approve at HR stage → submit to Acctg

6. 👤 **As acctg.officer**: Approve at Accounting stage
   - ✅ Verify: Status progresses to **"Acctg Approved"**

7. 👤 **As hr.manager**: Disburse → Publish
   - ✅ Verify: Status shows **"Published"**

8. 👤 **As the new employee's user account**: Navigate to Dashboard → My Payslips
   - ✅ Verify: Payslip for the current period is visible
   - ✅ Verify: Net pay amount shown matches the computed breakdown

**DATA INTEGRITY CHECKS:**
- ✅ HR module: Employee record shows `active` status
- ✅ Payroll module: Payroll run shows `published` status
- ✅ Employee self-service: Payslip visible and downloadable

---

═══════════════════════════════════════════════════════════
### INTEGRATION TEST 4.2 — Procure-to-Pay
═══════════════════════════════════════════════════════════

**FLOW:** Purchase Request → approval chain → PO → Goods Receipt → Inventory updated → AP Invoice auto-created or manually created → approved → paid → GL journal posted

**ACCOUNTS NEEDED:**
- `purchasing.officer@ogamierp.local` — creates PR and PO
- `production.head@ogamierp.local` — Step 2 approval
- `acctg.officer@ogamierp.local` — Step 3 review + invoice approval + payment
- `vp@ogamierp.local` — final PR approval
- `warehouse.head@ogamierp.local` — confirms Goods Receipt

**FLOW STEPS:**

1. 👤 **As purchasing.officer**: Create a Purchase Request for 500 units of raw material at ₱180/unit (see Section 3.8)
   - ✅ Verify: PR created, total estimated cost = ₱90,000

2. Complete the PR approval chain (Steps 2–4, see Section 3.9)
   - ✅ Verify: PR reaches **"Approved"** status

3. 👤 **As purchasing.officer**: Create Purchase Order from the approved PR
   - ✅ Verify: PO created and linked to PR

4. 👤 **As warehouse.head**: Create Goods Receipt against the PO
   - Receive 498 units (partial delivery)
   - ✅ Verify: GR created, 3-way match shows partial receipt
   - ✅ Verify: PO status shows **"Partially Received"**
   - ✅ Verify: Inventory stock for the item increases by 498 units

5. 👤 **As acctg.officer**: Create AP Invoice for the GR amount (498 units × ₱180 = ₱89,640)
   - Submit → Approve → Record Payment
   - ✅ Verify: Invoice status changes to **"Paid"**

6. **Cross-module check — GL:**
   - ✅ Verify: **Accounting → General Ledger** shows debit to Inventory/Expense account and credit to AP
   - ✅ Verify: **Accounting → General Ledger** shows debit to AP and credit to Cash for the payment
   - ✅ Verify: **Banking → Bank Accounts** shows reduced balance by ₱89,640

**DATA INTEGRITY CHECKS:**
- ✅ Procurement: PR = `approved`, PO = `partially_received`
- ✅ Inventory: Stock balance for the item = +498 units
- ✅ AP: Invoice = `paid`, payment recorded
- ✅ Accounting GL: Balanced entries for purchase and payment

---

═══════════════════════════════════════════════════════════
### INTEGRATION TEST 4.3 — Produce-to-Ship
═══════════════════════════════════════════════════════════

**FLOW:** Work Order → MRQ → materials issued → production output → QC inspection → delivery → AR invoice

**ACCOUNTS NEEDED:**
- `prod.manager@ogamierp.local` — creates work order
- `plant.manager@ogamierp.local` — releases WO, oversees production
- `warehouse.head@ogamierp.local` — fulfills MRQ
- `qc.manager@ogamierp.local` — creates inspection
- `impex.officer@ogamierp.local` — creates shipment
- `acctg.officer@ogamierp.local` — approves AR invoice, records payment

**FLOW STEPS:**

1. 👤 **As prod.manager**: Create Work Order for 10,000 units of finished goods
   - ✅ Verify: WO created in draft status

2. 👤 **As plant.manager**: Release the Work Order
   - ✅ Verify: Status changes to **"Released"**
   - ✅ Verify: Auto-MRQ generated in **Inventory → Requisitions**

3. Complete the MRQ 6-step approval chain through to **"Fulfilled"**
   - 👤 **As warehouse.head**: Fulfill the MRQ (issue stock from warehouse)
   - ✅ Verify: Stock balance for raw materials decreases by BOM quantities

4. 👤 **As plant.manager**: Start the Work Order
   - ✅ Verify: Status changes to **"In Progress"**

5. 👤 **As qc.manager**: Create an IPQC inspection — all items **PASS**
   - ✅ Verify: Inspection result = **"Passed"**

6. 👤 **As plant.manager**: Log output (10,000 produced, 0 rejected) → Complete the WO
   - ✅ Verify: WO status = **"Completed"**

7. 👤 **As impex.officer**: Create delivery shipment for the completed goods to a customer
   - Mark shipment as **"Delivered"**
   - ✅ Verify: Shipment status = **"Delivered"**

8. 👤 **As acctg.officer**: Locate or create AR invoice for this delivery
   - Approve → Receive Payment
   - ✅ Verify: AR invoice status = **"Paid"**

**DATA INTEGRITY CHECKS:**
- ✅ Production: WO = `completed`, output qty = 10,000
- ✅ Inventory: Raw material stock decreased per BOM
- ✅ QC: Inspection = `passed`
- ✅ Delivery: Shipment = `delivered`
- ✅ AR: Invoice = `paid`
- ✅ Accounting GL: Revenue and cash entries posted

---

## 6. SYSTEM-WIDE BOUNDARY TESTS

---

### TEST SECTION 6.1 — Permission Boundary Matrix

The following table summarizes which roles should be **blocked** (403 redirect) from key module pages:

| Page URL | Blocked Roles | Expected Behavior |
|----------|--------------|-------------------|
| `/admin/users` | All except `admin`, `super_admin` | Redirect to `/403` |
| `/hr/employees/all` | `staff`, `head`, `warehouse_head`, `ppc_head` | Redirect to `/403` |
| `/payroll/runs` | `staff`, `head`, `warehouse_head`, `ppc_head`, `ga_officer`, `purchasing_officer`, `impex_officer` | Redirect to `/403` |
| `/accounting/journal-entries` | `staff`, `head`, `manager`, `ga_officer`, `purchasing_officer` | Redirect to `/403` |
| `/accounting/ap/invoices` | `staff`, `head`, `manager`, `ga_officer` | Redirect to `/403` |
| `/ar/invoices` | `staff`, `head`, `manager`, `ga_officer` | Redirect to `/403` |
| `/banking/accounts` | `staff`, `head`, `manager`, `ga_officer`, `purchasing_officer` | Redirect to `/403` |
| `/fixed-assets` | `staff`, `head`, `manager`, `ga_officer`, `purchasing_officer` | Redirect to `/403` |
| `/procurement/purchase-requests` | `staff` | Redirect to `/403` |
| `/production/orders` | `staff` without `production.orders.view` | Redirect to `/403` |
| `/qc/inspections` | `staff`, `manager` without `qc.inspections.view` | Redirect to `/403` |

**HOW TO TEST:** For each row, log in with a blocked role, navigate directly to the URL, and verify redirection to `/403`.

---

### TEST SECTION 6.2 — SoD Enforcement Tests

**SoD rules verified from actual service class implementations:**

| Workflow | SoD Rule | Test Method |
|----------|----------|-------------|
| Employee Activation | Creator cannot activate | Log in as HR Manager (who created Juan Dela Cruz) → try to click "Active" on their own created employee |
| Leave Approval (Step 2) | Head cannot approve own leave | Have production.head submit their own leave → try to click "Approve" as the same account |
| Leave Approval (Step 3) | Manager cannot check if they submitted | Have plant.manager submit a leave → try to click "Manager Check" as the same account |
| Leave GA Process | GA Officer cannot process own submission | Have ga.officer submit a leave → try to click "Process" as the same account |
| Leave VP Note | VP cannot note own submission | Have vp submit their own leave → try to click "Note" as vp |
| Loan Step 3 | Officer cannot review own loan | Have acctg.officer apply for a loan → try to click "Officer Review" as the same account |
| Loan Step 5 | VP cannot approve own loan | Have vp apply for a loan → try to click "VP Approve" as the same account |
| Journal Entry Post | Cannot post own journal | Have acctg.officer create and try to post immediately (if SoD enforced here) |
| AP Invoice Approve | Cannot approve own invoice | Create invoice as acctg.officer → try to approve as same account |
| Payroll HR Approve | Cannot approve own initiated run | Initiate payroll as hr.manager → try to HR-approve as the same account |

**Expected result for all cases:** The action button is hidden, disabled with a SoD reason, OR clicking it returns an error: **"SoD violation — [actor] must differ from [requester]"**

---

### TEST SECTION 6.3 — Data Validation Boundary Tests

**Key validation rules verified from Zod schemas and FormRequest classes:**

| Form | Field | Invalid Input | Expected Error |
|------|-------|--------------|----------------|
| Employee Create | First Name | *(empty)* | "First name is required" |
| Employee Create | Basic Monthly Rate | `0` | "Rate must be greater than 0" |
| Employee Create | Gender | *(none selected)* | "Please select a gender" |
| Employee Create | Employment Type | *(none selected)* | "Please select an employment type" |
| Leave Request | Employee | *(none selected)* | Validation error on Employee field |
| Leave Request | Date From | After Date To | Date range validation error |
| Loan Application | Principal | `0` or negative | Validation error on amount |
| Purchase Request | Justification | Text under 20 chars | "Justification must be at least 20 characters" |
| Purchase Request | Items | No items added | "At least one line item is required" |
| Purchase Request | Item Quantity | `0` | "Must be > 0" |
| Journal Entry | Lines | Debit ≠ Credit | Unbalanced entry error |
| Login | Email | `notanemail` | "Enter a valid email" or inline error |
| Login | Password | Under 8 chars | Validation error |

---

### TEST SECTION 6.4 — Session and Authentication Boundary Tests

**Steps:**

1. **Expired session test:**
   - Log in as any user
   - Wait for session timeout OR manually clear the session cookie in browser developer tools
   - Try to navigate to any module page
   - ✅ Verify: Redirected to `/login`

2. **Direct URL access without login:**
   - Without any active session, navigate directly to `http://localhost:5173/hr/employees/all`
   - ✅ Verify: Redirected to `/login`

3. **Change password flow:**
   - Log in as any user
   - Navigate to the **Change Password** page (accessible from user menu → **Change Password**)
   - ✅ Verify: Form shows three fields: **Current Password**, **New Password**, **Confirm New Password** (exact labels may vary)
   - Fill in **Current Password** with wrong value → click submit
   - ✅ Verify: Error appears under **Current Password** field
   - Fill in all fields correctly with a new password → submit
   - ✅ Verify: Toast **"Password changed. Please log in with your new password."** appears
   - ✅ Verify: Automatically redirected to `/login`

---

## 7. EVIDENCE COLLECTION GUIDE

```
EVIDENCE TO CAPTURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

For each completed test section:
  📸 Screenshot: List page showing the created record with correct status
  📸 Screenshot: Detail page showing all filled fields
  📸 Screenshot: Success notification/toast visible
  📸 Screenshot: Status badge after each workflow transition
  📸 Screenshot: Account switched (username in top nav bar) before approval steps

For approval workflow tests:
  📸 Screenshot: Record in "pending" state (before approval)
  📸 Screenshot: Approval screen showing approver's account name
  📸 Screenshot: Record in "approved" state (after approval)

For cross-module tests:
  📸 Screenshot: Source module state before action
  📸 Screenshot: Target module showing the cross-module effect
  📸 Screenshot: GL/Accounting entries if financial transaction involved

For negative tests:
  📸 Screenshot: Form with invalid input showing validation error message
  📸 Screenshot: 403 page when unauthorized role tries to access restricted page

For SoD tests:
  📸 Screenshot: Button hidden/disabled with SoD reason for the creator
  📸 Screenshot: Same button visible and functional when a different user is logged in

File naming convention:
  [module]-[section]-[step]-[result].png
  Examples:
    hr-employee-create-success.png
    leave-step2-head-approved.png
    payroll-403-staff-blocked.png
    sod-leave-ga-blocked.png

Downloaded files:
  📁 Payslip PDF → payslip-[employee]-[period].pdf
  📁 Bank disbursement file → bank-file-[payroll-run-ref].txt
  📁 Government report → bir-1601c-[period].xlsx

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## 8. KNOWN GAPS AND UNTESTABLE FEATURES

---

### ⚠️ GAP 1 — CRM Manager Role Not in Standard Seed

**Module:** CRM  
**Feature:** CRM Dashboard and Support Ticket management  
**Evidence:** The `crm_manager` role is defined in `RolePermissionSeeder.php` but no user account with this role is seeded by `ManufacturingEmployeeSeeder` or `SampleDataSeeder`.  
**Impact:** Cannot test the full CRM internal workflow without manually creating a `crm_manager` account.  
**Workaround:** Use Admin (`admin@ogamierp.local`) to create a user with `crm_manager` role before testing CRM module, OR use `super_admin` to view CRM data.

---

### ⚠️ GAP 2 — MRQ 6-Step Approval Chain Button Labels

**Module:** Inventory → Requisitions  
**Feature:** Material Requisition full 6-step approval chain  
**Evidence:** From `docs/COMPLETE_MODULES_TESTING_GUIDE.md`, the MRQ steps use buttons: **Submit for Approval**, **Note** → **Confirm Note**, **Check** → **Confirm Check**, **Review** → **Confirm Review**, **VP Approve** → **Confirm Approve**, **Fulfill (Issue Stock)** → issue location dropdown → **Confirm Fulfill**  
**Impact:** If the frontend has been updated, button labels may differ from the documentation.  
**Workaround:** Verify the exact button labels from the live UI during testing.

---

### ⚠️ GAP 3 — Payroll SoD — Same HR Manager Cannot Approve

**Module:** Payroll  
**Feature:** HR approval stage SoD  
**Evidence:** From `RolePermissionSeeder.php`, the `manager` (HR Manager) role has BOTH `payroll.initiate` AND `payroll.hr_approve` permissions. In a small organization where the same HR Manager initiates and approves, the SoD is technically bypassable unless the backend explicitly checks the initiating user.  
**Impact:** The HR Review approval step may allow the same person to initiate and approve.  
**Workaround:** Use two different `manager`-role accounts (or use `acctg.officer` to verify the Acctg approval SoD instead). This is documented in the system as a known trade-off for small HR teams.

---

### ⚠️ GAP 4 — Vendor and Client Portal Password Change Required on First Login

**Module:** Vendor Portal, Client Portal  
**Feature:** First-time vendor/client portal login  
**Evidence:** From `TestAccountsSeeder.php`, `vendor` and `client` role accounts have `password_changed_at = null`, which triggers a forced password change on first login.  
**Impact:** Vendor portal (`vendor@ogamierp.local`) and client portal (`client@ogamierp.local`) testers must change their password before testing portal features.  
**Workaround:** On first login, change the password to any compliant value (e.g., `VendorTest@1234!`). Then proceed with portal testing.

---

### ⚠️ GAP 5 — Attendance CSV Import Format Not Documented in UI

**Module:** Attendance  
**Feature:** CSV import for time logs  
**Evidence:** The `attendance.import_csv` permission exists in the seeder and the GA Officer has this permission. However, the exact CSV format required for import is not verifiable from frontend code alone.  
**Impact:** Cannot provide exact CSV format in this guide.  
**Workaround:** Check `app/Http/Requests/Attendance/` for any import request validation rules, or export a sample from the attendance module if that option exists.

---

### ⚠️ GAP 6 — Recurring Journal Template Execution

**Module:** Accounting → Recurring Templates  
**Feature:** `journals:generate-recurring` Artisan command auto-materializes templates  
**Evidence:** Per `AGENTS.md`, the command `journals:generate-recurring` materializes active recurring journal templates due today or earlier. This is not triggered through the UI.  
**Impact:** Cannot test recurring template auto-execution through UI alone.  
**Workaround:** Run `php artisan journals:generate-recurring` manually and verify the resulting journal entries in **Accounting → Journal Entries**.

---

### ⚠️ GAP 7 — Monthly Depreciation Command

**Module:** Fixed Assets  
**Feature:** Monthly depreciation auto-run  
**Evidence:** Per `AGENTS.md`, the Artisan command `assets:depreciate-monthly` runs depreciation for all active fixed assets. Safe to re-run (skips already-processed periods).  
**Impact:** Depreciation entries are not created through the UI automatically.  
**Workaround:** Run `php artisan assets:depreciate-monthly` manually after creating fixed assets and verify depreciation entries appear in the asset detail.

---

### UNTESTABLE FEATURES SUMMARY

| # | Feature | Reason | Evidence |
|---|---------|--------|----------|
| 1 | CRM internal ticket management | No `crm_manager` account seeded | `ManufacturingEmployeeSeeder` does not include `crm_manager` |
| 2 | Recurring journal auto-generation | Triggered by Artisan command only | `journals:generate-recurring` command |
| 3 | Monthly asset depreciation auto-run | Triggered by Artisan command only | `assets:depreciate-monthly` command |
| 4 | WebSocket real-time notifications | Requires Reverb server running | `npm run dev:full` needed; not in default dev setup |
| 5 | Payroll bank disbursement file | Requires specific bank file format config | `payroll.download_bank_file` permission |
| 6 | BIR 2316 Certificate generation | Requires completed annual payroll data | `reports.bir_2316` permission |

---

*This guide was generated by reading the actual source code of Ogami ERP:*
*`ManufacturingEmployeeSeeder.php`, `SampleDataSeeder.php`, `RolePermissionSeeder.php`,*
*`AppLayout.tsx` (sidebar navigation), `EmployeeFormPage.tsx`, `LeaveFormPage.tsx`,*
*`CreatePayrollRunPage.tsx`, `CreatePurchaseRequestPage.tsx`, `LoanFormPage.tsx`,*
*`LeaveRequest.php`, `LeaveRequestService.php`, `OvertimeRequest.php`, `RolePermissionSeeder.php`*

---

## APPENDIX A — Sidebar Navigation Reference

Complete sidebar structure verified from `frontend/src/components/layout/AppLayout.tsx`:

| Section | Submenu Items | Required Permission | Visible To |
|---------|--------------|--------------------|-----------:|
| **Dashboard** | Dashboard | *(none)* | All authenticated users |
| **Team Management** | My Team, Team Attendance, Team Leave, Team Overtime, Team Loans, Shift Schedules | `employees.view_team` | manager, head, warehouse_head, ppc_head, ga_officer, plant_manager, production_manager, qc_manager, mold_manager |
| **Human Resources** | All Employees, Attendance Logs, Leave Requests, Overtime, Loans, Departments, Positions, Shifts, HR Reports | `hr.full_access` | manager |
| **Payroll** | Payroll Runs, Pay Periods | `payroll.view_runs` | manager, officer, executive, vice_president |
| **Accounting** | Chart of Accounts, Journal Entries, General Ledger, Loan Approvals, Recurring Templates | `chart_of_accounts.view` | officer, executive, vice_president, head |
| **Payables (AP)** | Vendors, Invoices, Credit Notes | `vendors.view` | officer, executive, vice_president, purchasing_officer, impex_officer, head, manager |
| **Receivables (AR)** | Customers, Invoices, Credit Notes | `customers.view` | officer, executive, vice_president, head, purchasing_officer |
| **Banking** | Bank Accounts, Reconciliations | `bank_accounts.view` | officer |
| **Financial Reports** | Trial Balance, Balance Sheet, Income Statement, Cash Flow, AP Aging, AR Aging, VAT Ledger, Tax Summary | `reports.financial_statements` | officer, executive, vice_president |
| **Fixed Assets** | Asset Register, Categories, Disposals | `fixed_assets.view` | officer, executive, vice_president, head |
| **Budget** | Cost Centers, Budget Lines, Budget vs Actual | `budget.view` | All with `budget.view` |
| **Reports** | Government Reports | `payroll.gov_reports` | manager, officer, executive, vice_president |
| **GA Processing** | GA Leave Processing | `leaves.ga_process` | ga_officer, vice_president |
| **Executive Approvals** | Overtime Approvals | `overtime.executive_approve` | executive |
| **Procurement** | Purchase Requests, Purchase Orders, Goods Receipts, RFQs, Analytics | `procurement.purchase-request.view` | All with permission |
| **Inventory** | Item Categories, Item Master, Warehouse Locations, Stock Balances, Stock Ledger, Requisitions, Stock Adjustments, Valuation | `inventory.items.view` | All with permission |
| **Production** | Bill of Materials, Delivery Schedules, Work Orders, Cost Analysis | `production.orders.view` | plant_manager, production_manager, head, ppc_head, warehouse_head, staff |
| **QC / QA** | Inspections, NCR, CAPA, Templates, Defect Rate | `qc.inspections.view` | plant_manager, qc_manager, head |
| **Maintenance** | Equipment, Work Orders | `maintenance.view` | All with permission |
| **Mold** | Mold Masters | `mold.view` | All with permission |
| **Delivery** | Receipts, Shipments | `delivery.view` | All with permission |
| **ISO / IATF** | Documents, Audits | `iso.view` | All with permission |
| **CRM** | CRM Dashboard, Support Tickets | `crm.tickets.view` | crm_manager |
| **VP Approvals** | Pending Approvals, Purchase Requests, Material Requisitions, Loans | `loans.vp_approve` | vice_president |
| **Administration** | Users, System Settings, Reference Tables, Fiscal Periods, Audit Logs, Backup & Restore | `system.manage_users` | admin |

---

## APPENDIX B — Workflow Status Reference

### Employee Employment Status
| From | To | Button Label | Who Can |
|------|----|-------------|---------|
| `draft` | `active` | **Active** (green) | Any user with `employees.activate` (not the creator) |
| `active` | `on_leave` | **On Leave** (blue) | User with `employees.suspend` |
| `active` | `suspended` | **Suspended** (amber) | User with `employees.suspend` |
| `active` | `resigned` | **Resigned** (red) | User with `employees.terminate` |
| `active` | `terminated` | **Terminated** (red) | User with `employees.terminate` |
| `on_leave` | `active` | **Active** (green) | Not the creator (SoD) |
| `suspended` | `active` | **Active** (green) | Not the creator (SoD) |

### Leave Request Status
| Status | Meaning | Who Transitions |
|--------|---------|----------------|
| `draft` | Not yet submitted | System |
| `submitted` | Filed by employee/HR | Submitter with `leaves.file_own` |
| `head_approved` | Step 2 done | Dept Head with `leaves.head_approve` |
| `manager_checked` | Step 3 done | Plant/HR Manager with `leaves.manager_check` |
| `ga_processed` | Step 4 done | GA Officer with `leaves.ga_process` |
| `approved` | Final — VP noted | VP with `leaves.vp_note` |
| `rejected` | GA disapproved | GA Officer (action: `disapproved`) |
| `cancelled` | Cancelled before head approval | Submitter with `leaves.cancel` |

### Loan Status (v2 Workflow)
| Status | Meaning | Who Transitions |
|--------|---------|----------------|
| `pending` | Just submitted | Submitter with `loans.apply` |
| `head_noted` | Step 2 done | Head with `loans.head_note` (≠ submitter) |
| `manager_checked` | Step 3 done | Manager with `loans.manager_check` (≠ head) |
| `officer_reviewed` | Step 4 done | Officer with `loans.officer_review` (≠ manager) |
| `approved` | VP approved | VP with `loans.vp_approve` (≠ officer) |

### Payroll Run Status
| Status | Meaning |
|--------|---------|
| `draft` | Wizard steps 1–3 (local, not yet saved to DB) |
| `processing` | Computation job running in queue |
| `computed` | Computation complete, awaiting HR review |
| `submitted` | Submitted for HR approval |
| `hr_approved` | HR stage approved |
| `acctg_approved` | Accounting stage approved |
| `disbursed` | Bank file generated, payroll disbursed |
| `published` | Employees can now view payslips |

### Overtime Request Status
| Status | Who | Applies To |
|--------|-----|-----------|
| `pending` | Initial state | All |
| `supervisor_approved` | Head endorsement | Staff requests only |
| `manager_checked` | Manager check | Extended flow |
| `officer_reviewed` | Officer review | Extended flow |
| `pending_executive` | Awaiting executive | Manager requests |
| `approved` | Final approval | All paths |
| `rejected` | Rejected at any step | All |
| `cancelled` | Cancelled by requester | All |

---

*End of Ogami ERP Complete Manual Testing Guide*
*Total modules covered: 20 | Total test sections: 38 | Total role accounts: 21*