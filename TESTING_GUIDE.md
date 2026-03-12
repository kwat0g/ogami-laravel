# Ogami ERP — Complete Testing Guide

> **Run first**: `php artisan migrate:fresh --seed`  
> **Frontend**: `http://localhost:5173` &nbsp;|&nbsp; **Backend**: `http://localhost:8000`

---

## Part 1 — Initial Data Setup

You MUST set up this data first before testing any module. Login as **Admin**.

📧 `admin@ogamierp.local` / `Admin@1234567890!`

### Step 1: Verify System Settings
1. Go to **Administration → System Settings**
2. Verify all rate tables loaded (SSS, PhilHealth, Pag-IBIG, TRAIN Tax)
3. Verify **Default Region** = `NCR` (minimum wage)

### Step 2: Verify Reference Data (Seeded Automatically)
These are pre-seeded — just verify they exist:
- **Departments** (13): HR, IT, ACCTG, PROD, SALES, EXEC, PLANT, QC, MOLD, WH, PPC, MAINT, ISO
- **Positions** (22): from CHAIRMAN to PROD-OP
- **Salary Grades** (15): SG-01 to SG-15
- **Shift Schedules**: Regular (08:00–17:00)
- **Leave Types**: VL, SL, EL, ML, PL, SPL, SIL, VAWC
- **Chart of Accounts**: 16 core accounts
- **Fiscal Periods**: Nov 2025 – Mar 2026

### Step 3: Create Bank Accounts (Accounting Officer)

📧 **Switch to**: `acctg.officer@ogamierp.local` / `AcctgManager@1234!`

1. Go to **Banking → Bank Accounts**
2. Click **"+ New Account"**
3. Create **company operating account**:
   - Name: `Ogami Manufacturing Inc. — BDO`
   - Account Number: `001-234-5678-90`
   - Bank Name: `BDO Unibank`
   - Account Type: `Checking`
   - GL Account: select `1001 — Cash in Bank`
   - Opening Balance: `0`
   - Active: ✅
4. Click **Save** → Create **payroll disbursement account**:
   - Name: `Ogami Mfg Payroll — Metrobank`
   - Account Number: `221-345-6789-01`
   - Bank Name: `Metrobank`
   - Account Type: `Checking`
   - GL Account: select `1001 — Cash in Bank`
   - Opening Balance: `0`
   - Active: ✅

### Step 4: Create Vendors (Purchasing Officer)

📧 **Switch to**: `purchasing.officer@ogamierp.local` / `Officer@12345!`

1. Go to **Payables (AP) → Vendors**
2. Click **"Add Vendor"**
3. Create **Vendor #1** — Raw Materials:
   - Vendor Name: `ABC Industrial Supply Co.`
   - TIN: `123-456-789-000`
   - Contact Person: `Jun Reyes`
   - Email: `jun.reyes@abcsupply.com`
   - Phone: `09171234567`
   - Payment Terms: `Net 30`
4. Click **"Add Vendor"** button → then create **Vendor #2** — Packaging:
   - Vendor Name: `Pacific Packaging Corp.`
   - TIN: `234-567-890-001`
   - Contact Person: `Lisa Santos`
   - Email: `lisa.santos@pacpack.com`
   - Phone: `09181234568`
   - Payment Terms: `Net 15`

### Step 5: Accredit Vendors (Purchasing Officer)

📧 **Stay as**: `purchasing.officer@ogamierp.local` / `Officer@12345!`

1. Go to **Payables (AP) → Vendors**
2. Find each vendor → in **Actions**, click **"Accredit"**
3. Confirm — vendor status changes to `Accredited`

> ⚠️ **Only Purchasing Officer** can accredit and suspend vendors.  
> **Accounting Officer** has view-only access to the vendor list.

### Step 6: Provision Vendor Portal Accounts (Admin only)

📧 **Switch to**: `admin@ogamierp.local` / `Admin@1234567890!`

1. Go to **Payables (AP) → Vendors**
2. Find each accredited vendor → in the **Actions** column, click **"Create Account"**
3. The popup shows generated credentials (email + password) → click **Copy**
4. Click **Done** to close

> ⚠️ **Only Admin** can create vendor portal accounts, and only for *accredited* vendors.

### Step 7: Vendor Imports Item Catalog (Vendor)

📧 **Login as vendor** *(use credentials from Step 6)*

1. After login → you're in the **Vendor Portal**
2. Go to **Items**
3. Click **"Import CSV"**
4. Upload: `storage/app/sample_vendor_items.csv` (20 items pre-created)
5. Verify all items appear with correct prices

### Step 8: Create Customers (Accounting Officer)

📧 **Switch to**: `acctg.officer@ogamierp.local` / `AcctgManager@1234!`

1. Go to **Receivables (AR) → Customers**
2. Click **"New Customer"**
3. Create **Customer #1**:
   - Name: `XYZ Manufacturing Corp.`
   - TIN: `345-678-901-002`
   - Contact Person: `Mark Tan`
   - Email: `mark.tan@xyzmfg.com`
   - Credit Limit (₱): `1000000`
4. Click **"Create Customer"** → then create **Customer #2**:
   - Name: `Mega Plastics Inc.`
   - TIN: `456-789-012-003`
   - Contact Person: `Rose Garcia`
   - Email: `rose.garcia@megaplastics.com`
   - Credit Limit (₱): `500000`

### Step 9: Create Inventory Items (Warehouse Head)

📧 **Switch to**: `warehouse.head@ogamierp.local` / `Head@123456789!`

1. Go to **Inventory → Item Categories** → click **"New Category"** → Create:
   - Code: `RAW` / Name: `Raw Materials`
   - Code: `PKG` / Name: `Packaging Materials`
   - Code: `MRO` / Name: `Maintenance Supplies`
2. Go to **Inventory → Item Master** → click **"New Item"** → Create items:
   - Category: `Raw Materials` / Name: `PP Resin` / Type: `Raw Material` / Unit of Measure: `kg`
   - Category: `Packaging Materials` / Name: `Small Carton` / Type: `Raw Material` / Unit of Measure: `pcs`
   - Category: `Maintenance Supplies` / Name: `Hydraulic Oil` / Type: `Spare Part` / Unit of Measure: `pail`

### Step 10: Create Equipment (Maintenance Head)

📧 **Switch to**: `maintenance.head@ogamierp.local` / `Head@123456789!`

1. Go to **Maintenance → Equipment** → click **"New Equipment"**
2. Create:
   - Name: `Injection Molding Machine #1`
   - Category: `Production`
   - Status: `Operational`
   - Location: `Production Floor A`

### Step 11: Create Mold Master (Mold Manager)

📧 **Switch to**: `mold.manager@ogamierp.local` / `Manager@12345!`

1. Go to **Mold → Mold Masters** → click **"New Mold"**
2. Create:
   - Name: `iPhone Case Mold v2`
   - Cavity Count: `4`
   - Max Shots: `500000`
   - Status: `Active`

---

## Part 2 — All Test Accounts Reference

| # | Role | Email | Password | Dept | Spatie Role |
|---|------|-------|----------|------|-------------|
| 1 | Admin | `admin@ogamierp.local` | `Admin@1234567890!` | — | `admin` |
| 2 | Super Admin | `superadmin@ogamierp.local` | `SuperAdmin@12345!` | — | `super_admin` |
| 3 | Executive (Chairman) | `chairman@ogamierp.local` | `Executive@12345!` | EXEC | `executive` |
| 4 | Executive (President) | `president@ogamierp.local` | `Executive@12345!` | EXEC | `executive` |
| 5 | Vice President | `vp@ogamierp.local` | `VicePresident@1!` | EXEC | `vice_president` |
| 6 | HR Manager | `hr.manager@ogamierp.local` | `HrManager@1234!` | HR | `manager` |
| 7 | Plant Manager | `plant.manager@ogamierp.local` | `Manager@12345!` | PLANT | `plant_manager` |
| 8 | Production Manager | `prod.manager@ogamierp.local` | `Manager@12345!` | PROD | `production_manager` |
| 9 | QC Manager | `qc.manager@ogamierp.local` | `Manager@12345!` | QC | `qc_manager` |
| 10 | Mold Manager | `mold.manager@ogamierp.local` | `Manager@12345!` | MOLD | `mold_manager` |
| 11 | Accounting Officer | `acctg.officer@ogamierp.local` | `AcctgManager@1234!` | ACCTG | `officer` |
| 12 | GA Officer | `ga.officer@ogamierp.local` | `Officer@12345!` | HR | `ga_officer` |
| 13 | Purchasing Officer | `purchasing.officer@ogamierp.local` | `Officer@12345!` | ACCTG | `purchasing_officer` |
| 14 | ImpEx Officer | `impex.officer@ogamierp.local` | `Officer@12345!` | ACCTG | `impex_officer` |
| 15 | Warehouse Head | `warehouse.head@ogamierp.local` | `Head@123456789!` | WH | `head` |
| 16 | PPC Head | `ppc.head@ogamierp.local` | `Head@123456789!` | PPC | `head` |
| 17 | Maintenance Head | `maintenance.head@ogamierp.local` | `Head@123456789!` | MAINT | `head` |
| 18 | Production Head | `production.head@ogamierp.local` | `Head@123456789!` | PROD | `head` |
| 19 | Processing Head | `processing.head@ogamierp.local` | `Head@123456789!` | PROD | `head` |
| 20 | QC/QA Head | `qcqa.head@ogamierp.local` | `Head@123456789!` | QC | `head` |
| 21 | ISO Head | `iso.head@ogamierp.local` | `Head@123456789!` | ISO | `head` |
| 22 | Staff (Prod Op) | `prod.staff@ogamierp.local` | `Staff@123456789!` | PROD | `staff` |

---

## Part 3 — Role Responsibility Matrix

> This defines the **authoritative** boundaries for each role. All frontend buttons, backend policies, and department permission templates must align with this matrix.

### Vendor Responsibility Boundary (SoD)

| Action | Accounting Officer | Purchasing Officer | Admin |
|--------|-------------------|--------------------|-------|
| View vendor list | ✅ | ✅ | ✅ |
| Create / Edit vendors | ❌ | ✅ | ✅ (bypass) |
| Accredit vendors | ❌ | ✅ | ✅ (bypass) |
| Suspend vendors | ❌ | ✅ | ✅ (bypass) |
| Archive vendors | ❌ | ✅ | ✅ (bypass) |
| Create vendor portal accounts | ❌ | ❌ | ✅ (admin only) |
| Create/process vendor invoices | ✅ | ❌ | — |
| Approve/pay vendor invoices | ✅ | ❌ | — |

---

## Part 4 — Per-Role Sidebar & Dashboard Audit

### What Each Role Should See

#### 1. Admin — `admin@ogamierp.local` / `Admin@1234567890!`

| Dashboard | Sidebar |
|-----------|---------|
| AdminDashboard — system health, user count, disk space | Administration (Users, Settings, Reference Tables, Audit Logs, Backup) |

**No business modules visible** — admin is system-only but bypasses all authorization checks via `before()` in every Policy.

---

#### 2. Executive — `chairman@ogamierp.local` / `Executive@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| ExecutiveDashboard — Revenue, P&L, financial KPIs | Accounting (view-only: COA, JE, AP, AR, Reports, Banking), Reports, Production (view-only) |

**No write access** — read-only observer role. Can executive-approve overtime.

---

#### 3. Vice President — `vp@ogamierp.local` / `VicePresident@1!`

| Dashboard | Sidebar |
|-----------|---------|
| VicePresidentDashboard — pending approvals, financial KPIs | VP Approvals, Procurement (view), Inventory (view + MRQ VP approve), Production (view), Accounting (view-only) |

**Key actions**: Final approver for loans (v2), payroll (VP approve), MRQ VP approve, purchase requests final approve.

---

#### 4. HR Manager — `hr.manager@ogamierp.local` / `HrManager@1234!`

| Dashboard | Sidebar |
|-----------|---------|
| ManagerDashboard — headcount, attendance, leave, payroll | Team Management, Human Resources, Payroll, Reports, Procurement (view + note/check), Inventory (view + MRQ check), Vendors (view-only) |

**Key actions**: Employee CRUD (all fields incl. salary + gov IDs), attendance import, leave/loan approval (manager check), payroll initiate/compute/HR-approve, BIR reports.  
**Does NOT have**: vendor write, AR/AP invoice write, banking.

---

#### 5. Plant Manager — `plant.manager@ogamierp.local` / `Manager@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| PlantManagerDashboard — plant ops, maintenance backlog | Team Management (self + leave team), Production, QC/QA, Maintenance, Mold, Delivery, ISO/IATF, Inventory (view) |

**Key actions**: Oversees ALL plant departments (PROD, QC, MOLD, WH, PPC, MAINT, ISO). Full access to production orders, QC inspections, NCRs, maintenance work orders, mold management, delivery, ISO.  
**Does NOT have**: payroll, accounting, vendor management, procurement (only inventory view).

---

#### 6. Production Manager — `prod.manager@ogamierp.local` / `Manager@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| ProductionManagerDashboard — production orders, efficiency | Production (full), Inventory (view) |

**Key actions**: Production orders CRUD, BOM management, delivery schedules, release/complete orders, QC override.  
**Does NOT have**: QC inspections (no `qc.inspections.create`), maintenance, mold, accounting.

---

#### 7. QC Manager — `qc.manager@ogamierp.local` / `Manager@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| QcManagerDashboard — inspections, NCR count, CAPA | QC/QA (full), Inventory (view) |

**Key actions**: QC templates, inspections, NCRs, CAPA. Can close NCRs.  
**Does NOT have**: production order management, maintenance, accounting.

---

#### 8. Mold Manager — `mold.manager@ogamierp.local` / `Manager@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| MoldManagerDashboard — mold status, shot counts | Mold (full), Inventory (view) |

**Key actions**: Mold CRUD, shot logging.  
**Does NOT have**: QC/mold inspections, production orders, accounting.

---

#### 9. Accounting Officer — `acctg.officer@ogamierp.local` / `AcctgManager@1234!`

| Dashboard | Sidebar |
|-----------|---------|
| OfficerDashboard — AP/AR aging bars, revenue vs expenses, cash position, action items | **Accounting** (COA, JE, GL, Loan Approvals, Recurring Templates), **Payables AP** (Vendors [view-only], Invoices, Credit Notes), **Receivables AR** (Customers, Invoices, Credit Notes), **Banking** (Bank Accounts, Reconciliations), **Financial Reports** (Trial Balance, BS, IS, CF, AP/AR Aging, VAT, Tax), **Fixed Assets** (Register, Categories, Disposals), **Budget** (Cost Centers, Lines, vs Actual), Payroll (acctg-approve, disburse, publish), Procurement (view + budget-check), Inventory (full) |

**Key actions**: Journal entries, AP invoices (create/approve/pay), AR invoices, banking, reconciliations, payroll acctg-approve/disburse, budget, fixed assets, tax reports.  
**Does NOT have** (since SoD separation): vendor create/edit/accredit/suspend/archive — **view-only on vendor list**.

---

#### 10. GA Officer — `ga.officer@ogamierp.local` / `Officer@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| GaOfficerDashboard — attendance anomalies, leave calendar | Executive (GA Leave Processing, OT Approvals), Team Management |

**Key actions**: Leave processing (GA step), OT supervision, attendance management, manage shifts, import attendance CSV.  
**No financial access** — zero AP/AR/payroll/accounting permissions.

---

#### 11. Purchasing Officer — `purchasing.officer@ogamierp.local` / `Officer@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| PurchasingOfficerDashboard — PO status, vendor performance | Procurement (full), Payables AP → Vendors (full management: add, edit, accredit, suspend, archive), Inventory (view + MRQ review), Delivery (view) |

**Key actions**: PR create/review, PO create/manage, GR create/confirm, **full vendor lifecycle** (create, edit, accredit, suspend, archive), MRQ review.  
**Does NOT have**: vendor invoicing, AR, banking, payroll, accounting GL.

---

#### 12. ImpEx Officer — `impex.officer@ogamierp.local` / `Officer@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| ImpexOfficerDashboard — shipments, deliveries | Delivery (full), Procurement (view), Inventory (view), Vendors (view-only) |

**Key actions**: Shipments, delivery receipts, GR create/confirm.  
**Does NOT have**: vendor management, procurement write, accounting.

---

#### 13. All Department Heads — `warehouse.head@ogamierp.local` / `Head@123456789!`

| Dashboard | Sidebar |
|-----------|---------|
| HeadDashboard — team overview, pending approvals | Team Management, Procurement (PR note, GR create/confirm), Inventory (view + MRQ create/note/fulfill), Production (view + log output), QC (inspections create + NCR view), Maintenance (full), Mold (full + log shots), Delivery (view), ISO (view + audit), GL/AP/AR (view-only) |

**Key actions**: PR noting (step 2), MRQ create/note/fulfill, leave head-approve, loan head-note, team management.  
**Does NOT have**: accounting write, payroll, vendor write.

---

#### 14. Staff — `prod.staff@ogamierp.local` / `Staff@123456789!`

| Dashboard | Sidebar |
|-----------|---------|
| EmployeeDashboard — personal info only (leave balance, loans, OT, payslips, attendance) | Production (view orders, log output), Inventory (create MRQ), Mold (log shots) |

**Key actions**: Self-service (leave file, OT submit, loan apply, payslips, profile), log production output, log mold shots, create material requisitions.

> ⚠️ **Staff does NOT have** `procurement.purchase-request.create` — they create **Material Requisitions** (MRQ), not Purchase Requests. PRs are created by managers/heads/officers.

---

## Part 5 — Testing Scenarios (By Workflow)

### Scenario 1: Full Procurement Flow (6 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | **Head creates PR** | `production.head@ogamierp.local` / `Head@123456789!` |
| | Go to Procurement → Purchase Requests → New. Select vendor from dropdown → pick items from catalog → set quantities → Submit | |
| 2 | **Head Notes (Step 2)** | `warehouse.head@ogamierp.local` / `Head@123456789!` |
| | Open the PR → click "Note" → confirm | |
| 3 | **Manager Checks (Step 3)** | `hr.manager@ogamierp.local` / `HrManager@1234!` |
| | Open the PR → click "Check" → confirm | |
| 4 | **Purchasing Officer Reviews (Step 4)** | `purchasing.officer@ogamierp.local` / `Officer@12345!` |
| | Open the PR → click "Review" → confirm | |
| 5 | **Accounting Officer Budget Checks (Step 5)** | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | Open the PR → click "Budget Check" → confirm | |
| 6 | **VP Final Approves (Step 6)** | `vp@ogamierp.local` / `VicePresident@1!` |
| | Open the PR → click "Final Approve" → auto-creates PO | |

---

### Scenario 2: Leave Flow (4 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | Staff submits leave | `prod.staff@ogamierp.local` / `Staff@123456789!` |
| | User menu → My Leaves → "New" → VL, 2 days → Submit | |
| 2 | Head approves | `production.head@ogamierp.local` / `Head@123456789!` |
| | Team Management → Team Leave → find request → "Approve" | |
| 3 | HR Manager checks | `hr.manager@ogamierp.local` / `HrManager@1234!` |
| | HR → Leave Requests → find request → "Check" | |
| 4 | GA Officer processes | `ga.officer@ogamierp.local` / `Officer@12345!` |
| | Executive → GA Leave Processing → find request → "Process" | |

---

### Scenario 3: Payroll Run (3 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | HR creates, adjusts & computes | `hr.manager@ogamierp.local` / `HrManager@1234!` |
| | Payroll → Payroll Runs → "New" → select period → scope → **add Adjustments** (e.g. ad-hoc bonus) → validate → compute → submit | |
| 2 | Accounting approves | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | Payroll → Payroll Runs → open run → "Acctg Approve" | |
| 3 | VP disbursement approval | `vp@ogamierp.local` / `VicePresident@1!` |
| | VP Approvals → find payroll run → "VP Approve" → disburse | |

---

### Scenario 4: Loan Application (5 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | Staff applies | `prod.staff@ogamierp.local` / `Staff@123456789!` |
| | User menu → My Loans → "Apply" → select type → amount → Submit | |
| 2 | Head notes | `production.head@ogamierp.local` / `Head@123456789!` |
| | Team Management → Team Loans → "Note" the loan | |
| 3 | HR Manager checks | `hr.manager@ogamierp.local` / `HrManager@1234!` |
| | HR → Loans → "Check" the loan | |
| 4 | Accounting reviews | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | Accounting → Loan Approvals → "Review" the loan | |
| 5 | VP final approves | `vp@ogamierp.local` / `VicePresident@1!` |
| | VP Approvals → Loans → "Approve" the loan | |

---

### Scenario 5: Vendor Onboarding (2 roles + admin)

| Step | Action | Login |
|------|--------|-------|
| 1 | Create & edit vendor | `purchasing.officer@ogamierp.local` / `Officer@12345!` |
| | Payables → Vendors → "Add Vendor" → fill details → Save | |
| 2 | Accredit vendor | `purchasing.officer@ogamierp.local` / `Officer@12345!` |
| | Find vendor → "Accredit" button → confirm | |
| 3 | Provision portal account | `admin@ogamierp.local` / `Admin@1234567890!` |
| | Find accredited vendor → "Create Account" → copy credentials | |
| 4 | Verify accounting officer cannot accredit | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | Payables → Vendors → verify: NO add/edit/accredit/suspend/archive buttons | |

---

### Scenario 6: Production + QC Flow (4 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | Create BOM | `prod.manager@ogamierp.local` / `Manager@12345!` |
| | Production → BOMs → "New" → add materials → Save | |
| 2 | Create production order | `prod.manager@ogamierp.local` / `Manager@12345!` |
| | Production → Work Orders → "New" → select BOM, qty → Release | |
| 3 | Staff logs output | `prod.staff@ogamierp.local` / `Staff@123456789!` |
| | Production → Work Orders → open order → "Log Output" | |
| 4 | QC inspects | `qc.manager@ogamierp.local` / `Manager@12345!` |
| | QC/QA → Inspections → "New" → link to production order → record results | |
| 5 | QC logs NCR (if fail) | `qcqa.head@ogamierp.local` / `Head@123456789!` |
| | QC/QA → NCR → "New" → link inspection → assign CAPA | |

---

### Scenario 7: Maintenance + Mold (3 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | Create work order | `maintenance.head@ogamierp.local` / `Head@123456789!` |
| | Maintenance → Work Orders → "New" → equipment, priority → Save | |
| 2 | Log mold shots | `mold.manager@ogamierp.local` / `Manager@12345!` |
| | Mold → Mold Masters → select mold → log shot count | |
| 3 | Plant Manager reviews | `plant.manager@ogamierp.local` / `Manager@12345!` |
| | Maintenance → Work Orders → review all plant WOs | |

---

### Scenario 8: Accounting & Finance (2 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | Journal entry | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | **Accounting** → Journal Entries → "New" → debit/credit lines → Submit → Post | |
| 2 | AP invoice | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | **Payables (AP)** → Invoices → "New" → link to vendor/PO → Submit → Approve | |
| 3 | AR invoice | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | **Receivables (AR)** → Invoices → "New" → Customer → items → Submit | |
| 4 | Bank reconciliation | `acctg.officer@ogamierp.local` / `AcctgManager@1234!` |
| | **Banking** → Reconciliations → select account → match/unmatch → Certify | |
| 5 | VP reviews financials | `vp@ogamierp.local` / `VicePresident@1!` |
| | **Financial Reports** → Trial Balance, Balance Sheet, Income Statement (read-only) | |

---

### Scenario 9: ISO & Document Control (1 role)

| Step | Action | Login |
|------|--------|-------|
| 1 | Create controlled document | `iso.head@ogamierp.local` / `Head@123456789!` |
| | ISO/IATF → Documents → "New" → title, rev → Save | |
| 2 | Create internal audit | `iso.head@ogamierp.local` / `Head@123456789!` |
| | ISO/IATF → Audits → "New" → scope, findings → Save | |

---

### Scenario 10: Delivery & Logistics (2 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | Create shipment | `impex.officer@ogamierp.local` / `Officer@12345!` |
| | Delivery → Shipments → "New" → assign vehicle → Save | |
| 2 | Create delivery receipt | `warehouse.head@ogamierp.local` / `Head@123456789!` |
| | Delivery → Receipts → "New" → link shipment → record qty | |

---

### Scenario 11: Material Requisition (5 roles)

| Step | Action | Login |
|------|--------|-------|
| 1 | Staff creates MRQ | `prod.staff@ogamierp.local` / `Staff@123456789!` |
| | Inventory → Requisitions → "New" → items, qty → Submit | |
| 2 | Head notes MRQ | `production.head@ogamierp.local` / `Head@123456789!` |
| | Inventory → Requisitions → "Note" | |
| 3 | Manager checks | `hr.manager@ogamierp.local` / `HrManager@1234!` |
| | Inventory → Requisitions → "Check" | |
| 4 | Purchasing reviews | `purchasing.officer@ogamierp.local` / `Officer@12345!` |
| | Inventory → Requisitions → "Review" | |
| 5 | VP approves | `vp@ogamierp.local` / `VicePresident@1!` |
| | VP Approvals → Material Requisitions → "Approve" | |
| 6 | Warehouse fulfills | `warehouse.head@ogamierp.local` / `Head@123456789!` |
| | Inventory → Requisitions → "Fulfill" | |

---

## Part 6 — Negative Tests (Access Denied Verification)

| Login | Try To Access | Expected |
|-------|---------------|----------|
| `prod.staff@ogamierp.local` / `Staff@123456789!` | `/hr/employees` | ❌ 403 |
| `prod.staff@ogamierp.local` / `Staff@123456789!` | `/accounting/journal-entries` | ❌ 403 |
| `prod.staff@ogamierp.local` / `Staff@123456789!` | `/payroll/runs` | ❌ 403 |
| `prod.staff@ogamierp.local` / `Staff@123456789!` | `/admin/users` | ❌ 403 |
| `acctg.officer@ogamierp.local` / `AcctgManager@1234!` | Vendor "Add Vendor" button | ❌ Not visible (view-only) |
| `acctg.officer@ogamierp.local` / `AcctgManager@1234!` | `PATCH /api/v1/accounting/vendors/1/accredit` | ❌ 403 |
| `acctg.officer@ogamierp.local` / `AcctgManager@1234!` | `PATCH /api/v1/accounting/vendors/1/suspend` | ❌ 403 |
| `acctg.officer@ogamierp.local` / `AcctgManager@1234!` | Approve own JE | ❌ SoD Violation |
| `hr.manager@ogamierp.local` / `HrManager@1234!` | `/accounting/vendors` (write) | ✅ View / ❌ Create |
| `purchasing.officer@ogamierp.local` / `Officer@12345!` | `/accounting/journal-entries` (write) | ❌ 403 |
| `warehouse.head@ogamierp.local` / `Head@123456789!` | `/hr/employees` (HR full) | ❌ 403 |
| `qc.manager@ogamierp.local` / `Manager@12345!` | `/mold/masters` | ❌ 403 |
| `mold.manager@ogamierp.local` / `Manager@12345!` | `/qc/inspections` | ❌ 403 |
| `ga.officer@ogamierp.local` / `Officer@12345!` | `/accounting/journal-entries` | ❌ 403 |
| `impex.officer@ogamierp.local` / `Officer@12345!` | Vendor "Add Vendor" button | ❌ Not visible (view-only) |

---

## Part 7 — Permission Implementation Audit

| # | Change | Severity | Status |
|---|--------|----------|--------|
| 1 | **Vendor SoD** — Accounting Officer (`officer`) is view-only on vendors; Purchasing Officer owns full lifecycle (create, edit, accredit, suspend, archive). | High | ✅ Fixed |
| 2 | **Portal account provisioning** — "Create Account" button now only visible to `admin` role for `accredited` vendors. | High | ✅ Fixed |
| 3 | **Auto-lock on suspend** — `VendorService::suspend()` locks linked portal `User.locked_until` in a DB transaction. | Medium | ✅ Fixed |
| 4 | **Department template** — `accounting_full` template stripped of vendor write permissions to enforce SoD at the dept-scoped layer. | High | ✅ Fixed |
| 5 | **`permissions.ts`** — Added `vendors.accredit` and `vendors.suspend` to the typed PERMISSIONS constant. | Medium | ✅ Fixed |
| 6 | **Frontend buttons gated** — Add Vendor, Edit, Accredit, Suspend, Archive all gated by their specific permissions in `VendorsPage.tsx`. | High | ✅ Fixed |
| 7 | **Staff sees "Production" in sidebar** — has `production.orders.view` + `production.orders.log_output`. Correct. | Low | ✅ Correct |
| 8 | **Staff does NOT see Procurement** — correct, staff creates **MRQs not PRs**. | Info | ✅ Correct |
| 9 | **Fixed Assets / Budget** — separate sidebar sections with all links. | Medium | ✅ Fixed |
| 10 | **Recurring Templates** — in Accounting sidebar. | Low | ✅ Fixed |
| 11 | **Credit Notes** — in Payables (AP) and Receivables (AR) sidebar sections. | Low | ✅ Fixed |

> **Summary**: All vendor-related SoD rules are now enforced at 3 layers — Spatie role permissions, department permission template, and frontend button visibility. The Accounting section is split into 7 focused sections (Accounting, Payables AP, Receivables AR, Banking, Financial Reports, Fixed Assets, Budget). Fiscal Periods moved to Admin.
