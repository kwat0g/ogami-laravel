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

1. Go to **Administration → Users → New User**
2. Fill details:
   - Name: `Vendor User (ABC)`
   - Email: `jun.reyes@abcsupply.com`
   - Password: `VendorUser@12345!`
   - Role: `vendor`
3. A **Linked Vendor** dropdown appears → select **ABC Industrial Supply Co.**
4. Click **Create User**.

> ⚠️ **Only Admin** can manage user accounts. Vendor users must be linked to a specific vendor.

### Step 7: Vendor Imports Item Catalog (Vendor)

📧 **Login as vendor** *(use credentials from Step 6)*

1. After login → you're in the **Vendor Portal**
2. Go to **Items**
3. Click **"Import CSV"**
4. Upload: `storage/app/sample_vendor_items.csv` (20 items pre-created)
5. Verify all items appear with correct prices

### Step 8: Create Customers (Purchasing Officer)

📧 **Switch to**: `purchasing.officer@ogamierp.local` / `Officer@12345!`

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

### Step 9: Create CRM Manager + Client Portal User (Admin)

> **Prerequisite**: Ensure an employee record exists for the CRM Manager first (HR Manager creates employees). We will use the seeded HR Manager account's authority if needed, but for simplicity, we assume an existing employee or create one.

**Part A: Create Employee for CRM Manager (HR Manager)**
📧 **Switch to**: `hr.manager@ogamierp.local` / `HrManager@1234!`
1. Go to **Human Resources → All Employees → New Employee**
2. Create:
   - Name: `Carrie CRM`
   - Monthly Rate: `30000`
   - Position: `SALES-MGR` / Dept: `SALES`
   - ID: `EMP-CRM-001`
   - Status: `Active`

**Part B: Create Users (Admin)**
📧 **Switch to**: `admin@ogamierp.local` / `Admin@1234567890!`

1. Go to **Administration → Users → New User**
2. Create **CRM Manager**:
   - Name: `CRM Manager`
   - Email: `crm.manager@ogamierp.local`
   - Password: `CrmManager@12345!`
   - Role: `crm_manager`
   - **Linked Employee:** Select `Carrie CRM` (or searches by name)
3. Create **Client Portal User**:
   - Name: `Client User (XYZ)`
   - Email: `client@ogamierp.local`
   - Password: `Client@Test1234!`
   - Role: `client`
   - **Linked Customer:** Select **XYZ Manufacturing Corp.**

### Step 10: Create Inventory Items (Warehouse Head)

📧 **Switch to**: `warehouse.head@ogamierp.local` / `Head@123456789!`

1. Go to **Inventory → Item Categories** → click **"New Category"** → Create:
   - Code: `RAW` / Name: `Raw Materials`
   - Code: `PKG` / Name: `Packaging Materials`
   - Code: `MRO` / Name: `Maintenance Supplies`
2. Go to **Inventory → Item Master** → click **"New Item"** → Create items:
   - Category: `Raw Materials` / Name: `PP Resin` / Type: `Raw Material` / Unit of Measure: `kg`
   - Category: `Packaging Materials` / Name: `Small Carton` / Type: `Raw Material` / Unit of Measure: `pcs`
   - Category: `Maintenance Supplies` / Name: `Hydraulic Oil` / Type: `Spare Part` / Unit of Measure: `pail`

### Step 11: Create Equipment (Maintenance Head)

📧 **Switch to**: `maintenance.head@ogamierp.local` / `Head@123456789!`

1. Go to **Maintenance → Equipment** → click **"New Equipment"**
2. Create:
   - Name: `Injection Molding Machine #1`
   - Category: `Production`
   - Status: `Operational`
   - Location: `Production Floor A`

### Step 12: Create Mold Master (Mold Manager)

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
| 23 | CRM Manager | `crm.manager@ogamierp.local` | `CrmManager@12345!` | SALES | `crm_manager` |
| 24 | Vendor Portal User | `jun.reyes@abcsupply.com` | *(generated in Step 6)* | — | `vendor` |
| 25 | Client Portal User | `client@ogamierp.local` | `Client@Test1234!` | — | `client` |

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

| Dashboard | Sidebar | [view-only], Invoices, Credit Notes), **Banking** (Bank Accounts, Reconciliations), **Financial Reports** (Trial Balance, BS, IS, CF, AP/AR Aging, VAT, Tax), **Fixed Assets** (Register, Categories, Disposals), **Budget** (Cost Centers, Lines, vs Actual), Payroll (acctg-approve, disburse, publish), Procurement (view + budget-check), Inventory (full) |

**Key actions**: Journal entries, AP invoices (create/approve/pay), AR invoices, banking, reconciliations, payroll acctg-approve/disburse, budget, fixed assets, tax reports.  
**Does NOT have** (since SoD separation): vendor/customer create/edit/accredit/suspend/archive — **view-only on vendor/customer lists
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
| PurchasingOfficerDashboard — PO status, vendor performance | Procurement (full), Payables AP → Vendors (full management: add, edit, accredit, suspend, archive), Receivables (AR) → Customers (full management), Inventory (view + MRQ review), Delivery (view) |

**Key actions**: PR create/review, PO create/manage, GR create/confirm, **full vendor lifecycle** (create, edit, accredit, suspend, archive), **full customer lifecycle**, MRQ review.  
**Does NOT have**: vendor invoicing, AR invoicing, banking, payroll, accounting GL.

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

#### 15. CRM Manager — `crm.manager@ogamierp.local` / `CrmManager@12345!`

| Dashboard | Sidebar |
|-----------|---------|
| CrmManagerDashboard — ticket queue, SLA breaches | CRM (Tickets) |

**Key actions**: assign tickets, reply to clients, update status, close tickets.

---

#### 16. Vendor Portal User — *(credentials from Step 6)*

**Portal only** — logs in to the Vendor Portal and sees: Dashboard, Orders, Items, Receipts.

---

#### 17. Client Portal User — `client@ogamierp.local` / `Client@Test1234!`

**Portal only** — logs in to the Client Portal and sees: Tickets (create, view, reply).

---

## Part 5 — Full End-to-End Workflow (Single Story)

This is a single, real-life story that starts from setup and walks through procurement, inventory receiving, BOM, production, delivery, and accounting. If you already completed Part 1, you can skip Phase A and proceed to Phase B.

### Phase A — Setup & Master Data (one-time)
1. **Admin**: Verify System Settings and reference data (Part 1, Steps 1–2).
2. **Accounting Officer**: Create the two bank accounts (Part 1, Step 3).
3. **Purchasing Officer**: Create two vendors (Part 1, Step 4).
4. **Purchasing Officer**: Accredit both vendors (Part 1, Step 5).
5. **Admin**: Create vendor portal accounts (Part 1, Step 6).
6. **Vendor Portal**: Import vendor items from `storage/app/sample_vendor_items.csv` (Part 1, Step 7).
7. **Purchasing Officer**: Create two customers (Part 1, Step 8).
8. **HR Manager & Admin**: Create CRM Manager and Client Portal users (Part 1, Step 9).
9. **Warehouse Head**: Create item categories and item masters (Part 1, Step 10), plus a finished good:
   - Add `Plastic Container 500ml` (Finished Good, UoM: `pcs`).
10. **Warehouse Head**: Create a warehouse location (Inventory → Warehouse Locations):
   - Code: `WH-A1`, Name: `Warehouse A – Rack 1`.
11. **Production Manager**: Create a BOM (Production → BOMs → New):
   - Finished Good: `Plastic Container 500ml`.
   - Components: `PP Resin` (0.20 kg), `Small Carton` (1 pc).

### Phase B — Procurement to Goods Receipt
11. **Production Head**: Create a Purchase Request (Procurement → Purchase Requests → New).
    - Vendor: `ABC Industrial Supply Co.`
    - Items: select 2–3 items from the vendor catalog (e.g., resin + packaging).
    - Submit PR.
12. **Warehouse Head**: Open the PR and click **Note** with a short comment.
13. **HR Manager**: Open the PR and click **Check**.
14. **Purchasing Officer**: Open the PR and click **Review**.
15. **Accounting Officer**: Open the PR and click **Budget Check**.
    - If budget is missing, create a Cost Center + Budget Line first, then retry.
16. **Vice President**: Approve the PR (auto-creates a PO).
17. **Purchasing Officer**: Open the PO, map each line to an Item Master, set agreed unit cost, then click **Send to Vendor**.

### Phase C — Vendor Portal to Receiving
18. **Vendor Portal**: Orders → open the PO → mark **In Transit**.
19. **Vendor Portal**: Mark **Delivered** (use full quantity for the first run).
20. **Warehouse Head**: Procurement → Goods Receipts → New → select the PO → enter received quantities → **Confirm**.
21. **QC Manager**: QC/QA → Inspections → New → link the GR/PO and record results.
22. **Warehouse Head**: Inventory → Stock Balances → confirm on-hand quantities increased.
23. **Accounting Officer**: Inventory → Valuation Report → confirm totals populate.

### Phase D — MRQ to Production
24. **Staff**: Inventory → Requisitions → New → request `PP Resin` + `Small Carton` → Submit.
25. **Production Head**: Open MRQ → **Note**.
26. **HR Manager**: Open MRQ → **Check**.
27. **Purchasing Officer**: Open MRQ → **Review**.
28. **Vice President**: Approve MRQ.
29. **Warehouse Head**: Open MRQ → **Fulfill** (stock issues).
30. **Production Manager**: Production → Work Orders → New → select BOM → **Release**.
31. **Staff**: Production → Work Orders → open the released order → **Log Output** (e.g., 200 pcs).
32. **QC Manager**: QC/QA → Inspections → record final inspection; close NCR if any.

### Phase E — Delivery, AR, AP, Banking
33. **ImpEx Officer**: Delivery → Shipments → New → assign vehicle.
34. **Warehouse Head**: Delivery → Receipts → New → link shipment → record quantities.
35. **Accounting Officer**: Receivables (AR) → Invoices → New → create invoice for `XYZ Manufacturing Corp.` → Submit.
36. **Accounting Officer**: Receivables (AR) → Invoices → Receive Payment (record cash receipt).
37. **Accounting Officer**: Payables (AP) → Invoices → open the draft created from GR (if present) → Submit and Approve.
38. **Accounting Officer**: Banking → Reconciliations → match the AR receipt and AP payment.
39. **Executive**: Financial Reports → Trial Balance → verify balances update.

### Phase F — Payroll Test Data + Run (Detailed Dataset)

**Dataset (use these two employees):**

| Employee | Email | Employee Code | Monthly Rate | OT | Allowance | Deduction |
|---|---|---|---:|---|---:|---:|
| Production Operator | `prod.staff@ogamierp.local` | `EMP-2026-0023` | 25000 | 2.0 hrs Regular OT on 2026-03-06 | Meal Allowance 500 | Uniform Deduction 200 |
| Production Head | `production.head@ogamierp.local` | `EMP-2026-0019` | 32000 | 1.5 hrs Regular OT on 2026-03-10 | Transport Allowance 1000 | Cash Advance 300 |

40. **HR Manager**: HR → Employees → open both employees → ensure **Active**, assign **Salary Grade**, set **basic monthly rate** (per table), and confirm **Regular** shift schedule.

41. **Prepare Attendance CSV** (for Mar 1–15, 2026; 10 working days). Create a file `attendance_mar_1_15.csv` with this exact header and rows:

```
employee_code,work_date,time_in,time_out,source
EMP-2026-0023,2026-03-02,08:00,17:00,csv_import
EMP-2026-0023,2026-03-03,08:00,17:00,csv_import
EMP-2026-0023,2026-03-04,08:15,17:00,csv_import
EMP-2026-0023,2026-03-05,08:00,16:30,csv_import
EMP-2026-0023,2026-03-06,08:00,17:00,csv_import
EMP-2026-0023,2026-03-09,08:00,17:00,csv_import
EMP-2026-0023,2026-03-10,08:00,17:00,csv_import
EMP-2026-0023,2026-03-11,08:00,17:00,csv_import
EMP-2026-0023,2026-03-12,08:00,17:00,csv_import
EMP-2026-0023,2026-03-13,08:00,17:00,csv_import
EMP-2026-0019,2026-03-02,08:00,17:00,csv_import
EMP-2026-0019,2026-03-03,08:00,17:00,csv_import
EMP-2026-0019,2026-03-04,08:00,17:00,csv_import
EMP-2026-0019,2026-03-05,08:00,17:00,csv_import
EMP-2026-0019,2026-03-06,08:00,17:00,csv_import
EMP-2026-0019,2026-03-09,08:00,17:00,csv_import
EMP-2026-0019,2026-03-10,08:00,17:00,csv_import
EMP-2026-0019,2026-03-11,08:00,17:00,csv_import
EMP-2026-0019,2026-03-12,08:00,17:00,csv_import
EMP-2026-0019,2026-03-13,08:00,17:00,csv_import
```

42. **GA Officer**: Attendance → Import CSV → upload `attendance_mar_1_15.csv` → confirm both employees have 10 logs. Resolve any anomalies if prompted.

43. **Overtime Requests**:
   - **Production Operator** (`prod.staff`) submits OT: **2026-03-06**, **2.0 hours**, type **Regular OT**.
   - **Production Head** submits OT: **2026-03-10**, **1.5 hours**, type **Regular OT**.
   - **Production Head** supervises the staff OT; **HR Manager** approves both OT requests.

44. **HR Manager**: Payroll → Payroll Runs → New → period **Mar 1–15, 2026** → include both employees.
   - In **Adjustments** (per employee):
     - `prod.staff`: **Allowance** `Meal Allowance` +500, **Deduction** `Uniform Deduction` -200.
     - `production.head`: **Allowance** `Transport Allowance` +1000, **Deduction** `Cash Advance` -300.
   - Compute → Submit.

45. **Accounting Officer**: Payroll → Payroll Runs → Accounting Approve → Disburse → Publish.

46. **Staff**: User menu → My Payslips → verify:
   - Late minutes (15) and undertime (30) are reflected for `prod.staff`.
   - OT pay, allowances, and deductions show under Earnings/Deductions.

### Phase G — CRM Ticket Flow (Client + CRM Manager)
47. **Client Portal User**: Client Portal → Tickets → New → submit a complaint ticket for a delayed delivery.
48. **CRM Manager**: CRM → Tickets → assign to self → reply → set status to In Progress → resolve and close.
49. **Client Portal User**: Open the ticket thread → reply → confirm internal notes are not visible.

## Part 6 — Role-Based Scenario Scripts (Step-by-Step)

Run these after Part 5. Each role only performs the actions in its responsibility boundary. If a step depends on a pending transaction, create it in the earlier role step first.

### 6.1 Admin (System Owner)
1. Login as `admin@ogamierp.local`.
2. Confirm the sidebar only shows Administration modules.
3. Go to Payables (AP) → Vendors and create portal accounts for each accredited vendor.
4. Go to Administration → Users and create one test user (any role) to confirm user management works.
5. Open Administration → Audit Logs and verify the actions were recorded.

### 6.2 Super Admin (Full Bypass)
1. Login as `superadmin@ogamierp.local`.
2. Open HR → Employees and view any employee profile.
3. Open Accounting → Journal Entries and confirm you can open the create form.
4. Open Procurement → Purchase Orders and confirm unrestricted access.

### 6.3 Executive (Chairman or President, Read-Only)
1. Login as `chairman@ogamierp.local` (or `president@ogamierp.local`).
2. Accounting → Chart of Accounts and Journal Entries: confirm view-only and no create buttons.
3. Financial Reports → Trial Balance and Income Statement: confirm read-only access.
4. Production → Work Orders: confirm list is visible and no write actions are shown.

### 6.4 Vice President (Final Approvals)
1. Login as `vp@ogamierp.local`.
2. VP Approvals → Purchase Requests: approve the latest PR (created in 6.14 Production Head).
3. VP Approvals → Material Requisitions: approve the latest MRQ (created in 6.15 Staff).
4. VP Approvals → Loans: approve a pending loan (created in 6.15 Staff).
5. VP Approvals → Payroll Runs: approve a submitted payroll run (created in 6.5 HR Manager).

### 6.5 HR Manager (HR + Payroll)
1. Login as `hr.manager@ogamierp.local`.
2. HR → Employees → New: create one employee record (basic fields only).
3. HR → Leave Requests: open a pending leave and click "Check".
4. HR → Loans: open a pending loan and click "Check".
5. Payroll → Payroll Runs: create a new run, compute, and submit for approval.

### 6.6 GA Officer (GA Leave + Attendance)
1. Login as `ga.officer@ogamierp.local`.
2. Executive → GA Leave Processing: process a leave request that has passed HR check.
3. Team Management → Attendance Anomalies: resolve at least one anomaly (if available).
4. Team Management → Overtime: supervise or review any pending OT request.

### 6.7 Accounting Officer (Accounting + Finance)
1. Login as `acctg.officer@ogamierp.local`.
2. Banking → Bank Accounts: create the two bank accounts from Part 1 if not done.
3. Budget → Cost Centers: create a cost center, then Budget Lines: set a line item.
4. Payables (AP) → Invoices: create an invoice from a confirmed GR and approve it.
5. Receivables (AR) → Invoices: create a customer invoice and submit.
6. Accounting → Journal Entries: create and post a simple 2-line JE.
7. Fixed Assets → Register: add one asset, then run depreciation and create a disposal.
8. Payroll → Payroll Runs: perform Accounting approval on a submitted run.

### 6.8 Purchasing Officer (Procurement + Vendor Lifecycle)
1. Login as `purchasing.officer@ogamierp.local`.
2. Payables (AP) → Vendors: create a vendor, edit it, and accredit it.
3. Procurement → Purchase Requests: open the latest PR and click "Review".
4. Procurement → Purchase Orders: open the PO created after VP approval, assign a vendor if needed, and click "Send".
5. Procurement → Goods Receipts: create a GR and confirm it.

### 6.9 ImpEx Officer (Delivery)
1. Login as `impex.officer@ogamierp.local`.
2. Delivery → Shipments: create a shipment and assign a vehicle.
3. Delivery → Receipts: create a delivery receipt linked to the shipment.
4. Procurement → Purchase Orders: confirm view-only access.

### 6.10 Plant Manager (Plant Ops Oversight)
1. Login as `plant.manager@ogamierp.local`.
2. Production → Work Orders: open and review active orders.
3. QC/QA → Inspections: review the latest inspection record.
4. Maintenance → Work Orders: create one work order and assign a priority.
5. Mold → Mold Masters: open a mold and log shots.
6. ISO/IATF → Documents: open the document register.

### 6.11 Production Manager (Production Control)
1. Login as `prod.manager@ogamierp.local`.
2. Production → BOMs: create a BOM for a finished good.
3. Production → Work Orders: create a work order and release it.
4. Inventory → Stock Balances: confirm view-only access.

### 6.12 QC Manager (QC/QA)
1. Login as `qc.manager@ogamierp.local`.
2. QC/QA → Templates: create or update an inspection template.
3. QC/QA → Inspections: create an inspection linked to a production order.
4. QC/QA → NCR: create an NCR and close it.

### 6.13 Mold Manager (Mold Operations)
1. Login as `mold.manager@ogamierp.local`.
2. Mold → Mold Masters: create a mold master if not done in Part 1.
3. Mold → Mold Masters: log a shot count update.

### 6.14 Department Heads (Head role — repeat for each head account)

Warehouse Head (`warehouse.head@ogamierp.local`)
1. Inventory → Requisitions: open an approved MRQ and click "Fulfill".
2. Procurement → Goods Receipts: create and confirm a GR.

PPC Head (`ppc.head@ogamierp.local`)
1. Production → Work Orders: open a released order and log output.

Maintenance Head (`maintenance.head@ogamierp.local`)
1. Maintenance → Equipment: verify the equipment list.
2. Maintenance → Work Orders: create a work order and mark it in progress.

Production Head (`production.head@ogamierp.local`)
1. Procurement → Purchase Requests: create a PR using vendor catalog items.
2. Team Management → Team Leave: approve one pending leave request.

Processing Head (`processing.head@ogamierp.local`)
1. Team Management → Team Loans: add a note to a pending loan.

QC/QA Head (`qcqa.head@ogamierp.local`)
1. QC/QA → NCR: review or create an NCR record.

ISO Head (`iso.head@ogamierp.local`)
1. ISO/IATF → Documents: create a controlled document.
2. ISO/IATF → Audits: create an internal audit.

### 6.15 Staff (Self-Service + Operations)
1. Login as `prod.staff@ogamierp.local`.
2. User menu → My Leaves: submit a leave request.
3. User menu → My Overtime: submit an OT request.
4. User menu → My Loans: submit a loan request.
5. Inventory → Requisitions: create an MRQ and submit.
6. Production → Work Orders: log output on a released order.
7. Mold → Mold Masters: log shots if the mold module is visible.

### 6.16 Vendor Portal User (Vendor role)
1. Login using the vendor portal credentials created by Admin (Part 1, Step 6).
2. Vendor Portal → Items: import the CSV catalog if not done.
3. Vendor Portal → Orders: open a PO and mark it In Transit.
4. Vendor Portal → Orders: mark as Delivered and upload any receipt or invoice file if available.

### 6.17 CRM Manager (Tickets)
1. Login as `crm.manager@ogamierp.local`.
2. CRM → Tickets: open a new ticket from the client portal.
3. Assign the ticket to yourself and reply.
4. Update status to In Progress, then Resolved/Closed.

### 6.18 Client Portal User
1. Login as `client@ogamierp.local`.
2. Client Portal → Tickets: create a new ticket (complaint or inquiry).
3. Open the ticket thread and post a reply.

---

## Part 7 — Negative Tests (Access Denied Verification)

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

## Part 8 — Permission Implementation Audit

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
