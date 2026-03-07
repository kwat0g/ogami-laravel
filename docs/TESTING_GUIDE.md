# Ogami ERP — End-to-End Testing Guide

> **Environment:** `http://localhost:5173` (Vite dev server) → API at `http://127.0.0.1:8000`
> **Start services:** `npm run dev` (or `bash dev.sh`) from the project root.
> **Database:** Re-seed before a fresh test run: `php artisan migrate:fresh --seed`

This guide is written as connected business scenarios, not isolated feature checks. Each scenario tells a real story — follow them in order where indicated, as later scenarios build on data created in earlier ones.

---

## Test Accounts Quick Reference

> All accounts have MFA disabled. Passwords are as listed.

### Executive & VP

| Email | Password | Role | Notes |
|-------|----------|------|-------|
| `chairman@ogamierp.local` | `Executive@12345!` | `executive` | Views Executive KPI dashboard; no leave approval role in new workflow |
| `president@ogamierp.local` | `Executive@12345!` | `executive` | Second executive — use for SoD tests when Chairman has already acted |
| `vp@ogamierp.local` | `VicePresident@1!` | `vice_president` | Final approver for loans, PRs, MRQs (step 5 of each workflow) |

### Managers

| Email | Password | Role | Scope |
|-------|----------|------|-------|
| `hr.manager@ogamierp.local` | `HrManager@1234!` | `manager` | HR, payroll, all employee records, leave admin |
| `plant.manager@ogamierp.local` | `Manager@12345!` | `plant_manager` | All plant ops — Production, QC, Maintenance, Mold, Delivery, ISO |
| `prod.manager@ogamierp.local` | `Manager@12345!` | `production_manager` | Production orders, BOMs only |
| `qc.manager@ogamierp.local` | `Manager@12345!` | `qc_manager` | Inspection templates, CAPA/NCR management |
| `mold.manager@ogamierp.local` | `Manager@12345!` | `mold_manager` | Mold masters, shot logs, criticality |

### Officers

| Email | Password | Role | Scope |
|-------|----------|------|-------|
| `acctg.officer@ogamierp.local` | `AcctgManager@1234!` | `officer` | Full GL/AP/AR, payroll accounting, loan officer review |
| `ga.officer@ogamierp.local` | `Officer@12345!` | `ga_officer` | Attendance admin, shift assignments — **no financial access** |
| `purchasing.officer@ogamierp.local` | `Officer@12345!` | `purchasing_officer` | PR→PO→GR cycle, vendor management — **no financial access** |
| `impex.officer@ogamierp.local` | `Officer@12345!` | `impex_officer` | Delivery receipts, shipments, GR confirmation — **no financial access** |

### Department Heads (role: `head`)

| Email | Password | Department |
|-------|----------|------------|
| `warehouse.head@ogamierp.local` | `Head@123456789!` | Warehouse |
| `ppc.head@ogamierp.local` | `Head@123456789!` | PPC (Production Planning & Control) |
| `maintenance.head@ogamierp.local` | `Head@123456789!` | Maintenance |
| `production.head@ogamierp.local` | `Head@123456789!` | Production |
| `processing.head@ogamierp.local` | `Head@123456789!` | Processing |
| `qcqa.head@ogamierp.local` | `Head@123456789!` | QC/QA |
| `iso.head@ogamierp.local` | `Head@123456789!` | ISO / Management System |
| `hr.supervisor@ogamierp.local` | `HrSupervisor@1234!` | HR |

### Staff & Admin

| Email | Password | Role |
|-------|----------|------|
| `hr.staff@ogamierp.local` | `HrStaff@1234!` | `staff` |
| `admin@ogamierp.local` | `Admin@1234567890!` | `admin` |

### Seeded Reference Data

The following master data is pre-loaded and should be used in test scenarios:

| Type | Code | Name |
|------|------|------|
| Raw Material | `RAW-001` | PP Resin Natural |
| Raw Material | `RAW-002` | HDPE Resin Black |
| Finished Good | `FGD-001` | Plastic Container 500ml |
| Vendor | — | Chinatown Resins Inc. |
| Customer | — | Ace Hardware Philippines |
| Equipment | — | Injection Moulding Machine #1 |
| Equipment | — | Hydraulic Press #3 |
| Mold | — | Container 500ml – Cavity 4 (CRITICAL — 91% shot life used) |

---

## Scenario 1 — New Employee Onboarding

> **Story:** The plant recently hired a new production operator, *Juan dela Cruz*, who starts March 10, 2026. HR needs to create his record, configure his attendance, and process his first loan application.

### 1.1 Create the Employee Record

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **HR → All Employees → Add Employee**
2. Enter the following details:
   - **First Name:** Juan, **Last Name:** dela Cruz
   - **Date of Birth:** 1995-06-15, **Gender:** Male, **Civil Status:** Single
   - **Department:** Production, **Position:** Production Operator
   - **Salary Grade:** SG-03, **Hired Date:** 2026-03-10
3. On the **Government IDs** tab:
   - **SSS No:** 33-1234567-8, **TIN:** 123-456-789-000
   - **PhilHealth No:** 12-345678901-2, **Pag-IBIG No:** 1234-5678-9012
4. On the **Bank Details** tab:
   - **Bank:** BDO, **Account No:** 1234-5678-90
5. Click **Save**
6. **Verify:** Employee record created with status `draft`; employee code auto-generated (e.g., `EMP-00XXX`)

### 1.2 Verify Automatic Activation

> **How it works:** There is no manual "Activate" button. When the employee form is saved with all four government IDs (SSS, TIN, PhilHealth, Pag-IBIG) **and** bank account details filled in, the system automatically sets `is_active = true` and `onboarding_status = active`. Leave balances are created at the same moment.

**Login as:** `hr.manager@ogamierp.local`

1. Return to Juan dela Cruz's employee record (it was saved in step 1.1 with all IDs and bank details completed)
2. **Verify:** The status badge on the employee profile shows **Active** — no further action is required
3. Navigate to **HR → Leave Balances** and confirm that VL, SL, and Emergency Leave balances were auto-created for the current year

> **What if the employee is still inactive?** This means one or more government IDs were left blank when the record was saved. Re-open the employee form, fill in the missing fields, and save again — the system will auto-activate on the next save.

### 1.3 Assign a Shift via the Employee Form

> **How it works:** The shift schedule dropdown is part of the **Add / Edit Employee** form. When the form is saved, the system automatically creates an `employee_shift_assignment` record using the employee's hired date as the `effective_from`. The GA officer can **view** shift schedules but cannot assign them to employees.

**Login as:** `hr.manager@ogamierp.local`

1. Open Juan dela Cruz's employee record and click **Edit**
2. Scroll to the **Shift Schedule** field (in the Employment Details section)
3. Select **Day Shift 8h** from the dropdown
4. Click **Save**
5. **Verify:** The employee profile now shows the assigned shift (Day Shift 8h, effective 2026-03-10)

> **Confirm the schedule exists first:** Navigate to **Team Management → Shift Schedules** as `ga.officer@ogamierp.local` to view schedules. You can edit or delete them there, but shift *assignment* to a specific employee is done through the employee form by the HR Manager.

### 1.4 Upload Employment Contract

**Login as:** `hr.manager@ogamierp.local`

1. Open Juan dela Cruz's employee record
2. Go to the **Documents** tab
3. Upload a PDF or JPG file and set the type to `Employment Contract`
4. **Verify:** File appears in the list; clicking the file name triggers a download

### 1.5 Record First Attendance

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **Team Management → Team Attendance**
2. Click **Add Manual Log**
3. Select employee: Juan dela Cruz, Date: 2026-03-10
   - Time-in: 07:58, Time-out: 17:05
4. Save
5. **Verify:** Record appears with `worked_minutes ≈ 487`, `late_minutes = 0`, `overtime_minutes ≈ 5`

### 1.6 Juan Files a Vacation Leave Request

> **Workflow (AD-084-00):** Employee submits → Dept Head approves → Plant Manager checks → GA Officer processes (sets action_taken) → VP notes (balance deducted)

**Login as:** `hr.staff@ogamierp.local`

1. Navigate to **My Leaves → New Request**
2. Select **Leave Type:** Vacation Leave
3. Date From: 2026-03-20, Date To: 2026-03-20 (1 day)
4. Reason: "Personal errand"
5. Click **Submit**
6. **Verify:** Status `submitted`; no balance deducted yet

---

**Step 2 — Dept Head Approves**

**Login as:** `hr.supervisor@ogamierp.local` (HR dept head — `head` role)

1. Navigate to **Team Management → Team Leave**
2. Find the request (status: `submitted`), click **Approve**
3. **Verify:** Status changes to `head_approved`

---

**Step 3 — Plant Manager Checks**

**Login as:** `plant.manager@ogamierp.local`

1. Navigate to **Team Management → Team Leave**
2. Find the request (status: `head_approved`), click **Check**
3. **Verify:** Status changes to `manager_checked`

---

**Step 4 — GA Officer Processes**

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **Executive → GA Leave Processing**
2. Find the request (status: `manager_checked`), click **Process**
3. In the modal, set **Action Taken:** `Approved With Pay`
4. Optionally add remarks, click **Submit**
5. **Verify:** Status changes to `ga_processed`; balance snapshot captured (`beginning_balance`, `applied_days`, `ending_balance` set)

---

**Step 5 — VP Notes (Final Approval)**

**Login as:** `vp@ogamierp.local`

1. Navigate to **Team Management → Team Leave** (or HR → Leave Requests as admin)
2. Find the request (status: `ga_processed`), click **Note**
3. **Verify:**
   - Status changes to `approved`
   - VL balance officially deducted by 1 day
   - Employee receives approval notification

---

**SoD Check LV-004:** Try to approve as the same person who filed — system must block it.

### 1.7 File an Emergency Loan Application (Full 5-Stage Chain)

**Login as:** `hr.staff@ogamierp.local`

1. Navigate to **My Loans → New Application**
2. Fill in:
   - **Loan Type:** Emergency Loan
   - **Principal:** ₱15,000
   - **Term:** 6 months
   - **Deduction Cut-off:** 2nd cut-off (16th–end)
   - **Purpose:** Emergency home repair
3. Click **Submit**
4. **Verify:** Status `pending`; Reference No generated (e.g., `LN-2026-00001`); Amortization shown as ≈ ₱2,500/month

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Team Management → Team Loans**
2. Find the loan application, open it
3. Click **Head Note**, add remark: *"Employee has a clean record. Recommend approval."*
4. **Verify:** Status changes to `head_noted`; timeline shows Step 0 completed

**Login as:** `plant.manager@ogamierp.local`

1. Navigate to **Team Management → Team Loans**
2. Open the `head_noted` loan
3. Click **Check / Recommend**, add remark: *"Verified employee tenure; financially capable."*
4. **Verify:** Status changes to `manager_checked`

**Login as:** `acctg.officer@ogamierp.local`

1. Navigate to **Financial Reports → Loan Approvals** (or look on the accounting officer dashboard)
2. Open the `manager_checked` loan
3. Click **Review / Endorse**, add remark: *"No existing outstanding balance."*
4. **Verify:** Status changes to `officer_reviewed`

**Login as:** `vp@ogamierp.local`

1. Navigate to **VP Approvals → Loans**
2. Open the `officer_reviewed` loan
3. Click **VP Approve**
4. **Verify:** Status changes to `approved`; amortization schedule is generated

**Login as:** `acctg.officer@ogamierp.local`

1. Open the `approved` loan
2. Click **Disburse Funds**
3. **Verify:** Status changes to `active`; GL entry created debiting Loans Receivable and crediting Cash

---

## Scenario 2 — Raw Material Procurement Cycle (PR → PO → GR → AP → Payment)

> **Story:** The production department is running low on `RAW-001 PP Resin Natural`. The production head raises a Purchase Request for **500 kg at ₱180/kg (₱90,000 total)**. The Purchasing Officer processes the order to `Chinatown Resins Inc.`, goods arrive short (498 kg), QC performs incoming inspection, and accounting processes the vendor invoice and payment.

### 2.1 Create the Purchase Request

**Login as:** `production.head@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests → New**
2. Fill in:
   - **Title:** Replenishment — PP Resin Natural (March 2026)
   - **Department:** Production
   - **Required Date:** 2026-03-15
3. Add line item:
   - **Item Description:** RAW-001 PP Resin Natural
   - **Quantity:** 500, **Unit:** kg
   - **Estimated Unit Cost:** ₱180.00
   - **Estimated Total:** ₱90,000.00
4. Click **Submit**
5. **Verify:** Status `submitted`; PR Reference generated (e.g., `PR-2026-03-00001`)

### 2.2 Head Notes the PR

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests**, filter by status `submitted`
2. Open `PR-2026-03-00001`
3. Click **Note**, add remark: *"Current stock at warehouse is below safety level. Urgent."*
4. **Verify:** Status changes to `noted`

### 2.3 Plant Manager Checks the PR

**Login as:** `plant.manager@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests**, filter by `noted`
2. Open the PR, click **Check**
3. Add remark: *"Confirm — production schedule requires this material by March 15."*
4. **Verify:** Status changes to `checked`

### 2.4 Purchasing Officer Reviews the PR

**Login as:** `purchasing.officer@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests** (or check the Purchasing dashboard for pending reviews)
2. Open the `checked` PR
3. Click **Review**, add remark: *"Chinatown Resins Inc. confirmed availability. Lead time 3 days."*
4. **Verify:** Status changes to `reviewed`

### 2.5 VP Approves the PR

**Login as:** `vp@ogamierp.local`

1. Navigate to **VP Approvals → Purchase Requests**
2. Open the `reviewed` PR for ₱90,000
3. Review line items and click **VP Approve**
4. **Verify:** Status changes to `approved`

### 2.6 Convert Approved PR to Purchase Order

**Login as:** `purchasing.officer@ogamierp.local`

1. Open the `approved` `PR-2026-03-00001`
2. Click **Convert to PO**
3. On the PO creation form:
   - **Vendor:** Chinatown Resins Inc.
   - **Expected Delivery Date:** 2026-03-14
   - Confirm line item: 500 kg PP Resin Natural @ ₱180.00 = ₱90,000.00
4. Click **Create PO**
5. **Verify:**
   - PO created with status `draft`, reference `PO-2026-03-00001`
   - PR status becomes `converted_to_po`

### 2.7 Send the Purchase Order to Vendor

**Login as:** `purchasing.officer@ogamierp.local`

1. Open `PO-2026-03-00001` and click **Send**
2. **Verify:** PO status changes to `sent`

### 2.8 Receive Goods at Warehouse (Short Delivery)

**Login as:** `warehouse.head@ogamierp.local`

On March 14, 2026, the truck arrives with 498 kg (2 kg short):

1. Navigate to **Procurement → Goods Receipts → New**
2. Link to **Purchase Order:** `PO-2026-03-00001`
3. System pulls expected line item (500 kg PP Resin Natural)
4. Enter actual received quantities: **498 kg**
5. Set **Receipt Date:** 2026-03-14
6. Click **Save → Confirm**
7. **Verify:**
   - GR status: `confirmed`, reference `GR-2026-03-00001`
   - PO status: `partially_received` *(498/500 kg received)*
   - Inventory → Stock Balances: RAW-001 stock increased by 498 kg

### 2.9 Follow-up GR for Remaining 2 kg

**Login as:** `warehouse.head@ogamierp.local`

When the remaining 2 kg arrives on March 16:

1. Navigate to **Procurement → Goods Receipts → New**
2. Link to same `PO-2026-03-00001`
3. Enter **Qty Received:** 2 kg, **Receipt Date:** 2026-03-16
4. **Save → Confirm**
5. **Verify:**
   - New GR `GR-2026-03-00002` confirmed
   - PO status changes to `fully_received`
   - RAW-001 stock now increased by 500 kg total

### 2.10 IQC — Incoming Quality Inspection

**Login as:** `qcqa.head@ogamierp.local`

Before releasing raw material to production:

1. Navigate to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** IQC (Incoming)
   - **Item:** RAW-001 PP Resin Natural
   - **Lot/Batch:** `LOT-CH-2026-03-14`
   - **Inspection Date:** 2026-03-14
   - **Qty Inspected:** 498
   - **Template:** PP Resin Incoming Inspection *(create via QC → Templates first if absent — see sidebar below)*

3. Click **Save**, then **Submit Results**:
   - Visual contamination check: **Conforming**
   - Moisture content < 0.05%: **Conforming**, Actual: `0.03%`
   - Melt Flow Index (12–15 g/10min): **Conforming**, Actual: `13.2`

4. **Verify:** Inspection status: `passed`

> **How to create the IQC template first:** Login as `qc.manager@ogamierp.local` → **QC / QA → Templates → New** → Name: `PP Resin Incoming Inspection`, Stage: `IQC` → Add criteria: (1) Visual Contamination — Visual — None visible; (2) Moisture content — Gravimetric — < 0.05%; (3) Melt Flow Index — ASTM D1238 — 12–15 g/10min → Save

### 2.11 Record the Vendor Invoice (AP)

**Login as:** `acctg.officer@ogamierp.local`

Invoice `CR-INV-2026-1542` from Chinatown Resins for 498 kg × ₱180 = ₱89,640:

1. Navigate to **Accounting → AP Invoices → New**
2. Fill in:
   - **Vendor:** Chinatown Resins Inc.
   - **Invoice No:** CR-INV-2026-1542
   - **Invoice Date:** 2026-03-14
   - **Due Date:** 2026-04-13 *(30-day terms)*
   - **Amount:** ₱89,640.00
   - **EWT ATC Code:** WC158 — 2% (materials supplier)
3. Link to GR: associate with `GR-2026-03-00001`
4. Click **Save → Submit**
5. **Verify:** Invoice status `pending_approval`; appears in **AP Due Monitor** with due date April 13

### 2.12 Approve the Vendor Invoice (SoD-009)

> **SoD-009:** The user who submitted the invoice **cannot** approve it.

**Test the violation:**

**Login as:** `acctg.officer@ogamierp.local` *(same submitter)*

1. Open the submitted invoice and click **Approve**
2. **Expected:** System blocks — *"You cannot approve an invoice you submitted."*

**Complete with a second officer** (create one if needed — see below):

> If no second officer exists: **Login as** `admin@ogamierp.local` → **Admin → Users → New User** → Email: `acctg.officer2@ogamierp.local`, Role: `officer`, Password: `AcctgManager@2345!`

**Login as:** `acctg.officer2@ogamierp.local`

1. Navigate to **Accounting → AP Invoices**, find the pending invoice
2. Click **Approve**
3. **Verify:** Status changes to `approved`; EWT of ₱1,793 (2% of ₱89,640) computed

### 2.13 Record Vendor Payment

**Login as:** `acctg.officer@ogamierp.local`

On March 25, 2026 the company pays the net amount:

1. Open approved invoice `CR-INV-2026-1542`
2. Click **Record Payment**
3. Fill in:
   - **Amount:** ₱87,847.00 *(₱89,640 − ₱1,793 EWT)*
   - **Payment Date:** 2026-03-25
   - **Bank Account:** select the operating bank account
4. Save
5. **Verify:**
   - Invoice status: `paid`
   - GL journal entry auto-created:
     - DR Accounts Payable ₱89,640
     - CR EWT Payable ₱1,793
     - CR Cash / Bank ₱87,847
   - Navigate to **Financial Reports → General Ledger**, filter by the AP account — the debit side should show this clearance

---

## Scenario 3 — Customer Order Fulfillment (Production → QC → Inventory → Delivery → AR)

> **Story:** Ace Hardware Philippines orders **10,000 units of `FGD-001 Plastic Container 500ml`** at ₱28.00/unit = ₱280,000 (+ 12% VAT = ₱313,600 total), delivery by March 25, 2026. PPC creates the delivery schedule, production runs the order, OQC passes the batch, the warehouse ships them out, and accounting raises the AR invoice and receives payment.

### 3.1 Create Delivery Schedule

**Login as:** `ppc.head@ogamierp.local`

1. Navigate to **Production → Delivery Schedules → New**
2. Fill in:
   - **Customer:** Ace Hardware Philippines
   - **Item:** FGD-001 Plastic Container 500ml
   - **Ordered Qty:** 10,000 units
   - **Required Delivery Date:** 2026-03-25
3. Save
4. **Verify:** Delivery schedule created with the correct customer and date

### 3.2 Create and Run the Production Order

**Login as:** `production.head@ogamierp.local`

1. Navigate to **Production → Orders → New**
2. Fill in:
   - **Item (BOM):** FGD-001 Plastic Container 500ml
   - **Quantity to Produce:** 10,000 units
   - **Target Completion Date:** 2026-03-22
3. Click **Create** (status: `draft`) → **Release** (status: `released`) → **Start** (status: `in_progress`)
4. **Verify:** Production order reference generated (e.g., `PO-PROD-2026-00001`)

> **Cross-check inventory:** Navigate to **Inventory → Stock Balances** and confirm RAW-001 has sufficient stock for this run. The BOM for FGD-001 requires approximately 120g PP Resin per unit; 10,000 units = ~1,200 kg. If insufficient, complete Scenario 2 first.

### 3.3 Log Production Output and Complete

**Login as:** `production.head@ogamierp.local`

1. On the `in_progress` order, click **Log Output**:
   - **Qty Produced:** 10,050 units
   - **Qty Rejected (scrap):** 43 units
   - **Net Good Output:** 10,007 units
2. Save

**Login as:** `prod.manager@ogamierp.local`

1. Open the production order, click **Complete**
2. **Verify:**
   - Order status: `completed`
   - FGD-001 stock in **Inventory → Stock Balances** increased by ~10,007 units
   - RAW-001 stock decreased proportionally (consumed)

### 3.4 Outgoing Quality Inspection (OQC)

**Login as:** `qcqa.head@ogamierp.local`

Sample 200 units from the produced batch for OQC:

1. Navigate to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** OQC (Outgoing)
   - **Item:** FGD-001 Plastic Container 500ml
   - **Lot/Batch:** `LOT-ACE-2026-03-22`
   - **Inspection Date:** 2026-03-22, **Qty Inspected:** 200
3. **Submit Results**:
   - Dimensional check (height 110±2mm): **Conforming**, Actual: `111mm`
   - Wall thickness (2.0±0.2mm): **Conforming**, Actual: `2.1mm`
   - Lid fit — no leakage: **Conforming**
   - Visual — no flash, sink marks, burns: **Conforming**
4. **Verify:** Inspection status: `passed`; reference `INS-2026-03-OQC-001`

### 3.5 Create Outbound Delivery Receipt

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Delivery → Receipts → New**
2. Fill in:
   - **Direction:** Outbound
   - **Customer:** Ace Hardware Philippines
   - **Receipt Date:** 2026-03-24
3. Add line item: FGD-001, Qty Expected: 10,000, Qty Released: 10,000
4. Click **Save → Confirm**
5. **Verify:**
   - DR status: `confirmed`, reference `DR-OUT-2026-00001`
   - FGD-001 stock decreased by 10,000 units

### 3.6 Create Shipment and Track Delivery

**Login as:** `impex.officer@ogamierp.local`

1. Navigate to **Delivery → Shipments → New**
2. Fill in:
   - **Link to DR:** `DR-OUT-2026-00001`
   - **Carrier:** JRS Express
   - **Tracking Number:** JRS-2026-032401
   - **Shipped Date:** 2026-03-24
   - **Estimated Arrival:** 2026-03-25
3. Save (status: `pending`)
4. Click **Update Status → In Transit**
5. On March 25: Click **Update Status → Delivered**, enter **Actual Arrival:** 2026-03-25
6. **Verify:** Shipment status: `delivered`; ImpEx dashboard shows no in-transit shipments

### 3.7 Raise Customer Invoice and Receive Payment

**Login as:** `acctg.officer@ogamierp.local`

1. Navigate to **Financial Reports → AR Invoices → New**
2. Fill in:
   - **Customer:** Ace Hardware Philippines
   - **Invoice Date:** 2026-03-25, **Due Date:** 2026-04-24
   - **Line Item:** Plastic Container 500ml, Qty 10,000 @ ₱28.00 = ₱280,000
   - **VAT (12%):** ₱33,600, **Total:** ₱313,600
3. Click **Save → Approve**
4. **Verify:** Invoice status `approved`; customer balance updated

**Receive payment on April 20:**

1. Open the AR invoice
2. Click **Receive Payment**:
   - **Amount:** ₱313,600
   - **Payment Date:** 2026-04-20
   - **Reference:** AIBTRF-20260420-001
3. **Verify:**
   - Invoice status: `paid`
   - GL entry: DR Cash/Bank ₱313,600 / CR Accounts Receivable ₱313,600

---

## Scenario 4 — March 2026 Payroll Run (1st Half)

> **Story:** HR processes regular payroll for March 1–15, 2026. The 8-step pipeline runs through HR initiation → computation → HR approval → accounting approval → publish. Employees view payslips. SoD-005 (self-approval) and SoD-007 (accounting/HR boundary) are exercised.

### 4.1 Verify Pay Period Exists

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Payroll → Pay Periods**
2. Confirm `March 2026 – 1st Half` exists with dates 2026-03-01 to 2026-03-15 and status `open`
3. If missing, click **New Period** and create it

### 4.2 Initiate the Payroll Run

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Payroll → Runs → New Run**
2. Select **Pay Period:** March 2026 – 1st Half, **Run Type:** Regular
3. Click **Initiate**
4. **Verify:** Run created with status `draft`, reference `PR-RUN-2026-03-01`

### 4.3 Scope the Run

**Login as:** `hr.manager@ogamierp.local`

1. Click **Scope** on the draft run
2. Review scoped employees — all `active` employees are listed
3. Verify Juan dela Cruz (if activated) is included; the system should prorate his pay from March 10–15
4. Click **Lock Scope**
5. **Verify:** Status changes to `scoped`; employee count confirmed

### 4.4 Run Pre-Validation Checks

**Login as:** `hr.manager@ogamierp.local`

1. Click **Pre-Run Validation**
2. Review warnings:
   - Any missing time-in/out for the period — these generate red blockers
   - Approved leave days (should reduce basic pay days)
   - Approved OT (should add to gross pay)
3. Resolve all red blockers; acknowledge yellow warnings
4. **Verify:** Validation passes with no blockers

### 4.5 Compute Payroll

**Login as:** `hr.manager@ogamierp.local`

1. Click **Compute** — the 17-step pipeline processes in the background
2. Monitor the progress:
   - Steps 1–3: Snapshots, Period Meta, Attendance Summary
   - Steps 4–9: YTD Load, Basic Pay, OT, Holiday Pay, Night Diff, Gross
   - Steps 10–14: SSS, PhilHealth, Pag-IBIG, Taxable Income, Withholding Tax
   - Steps 15–17: Loan Deductions, Other Deductions, Net Pay
3. **Verify:** Status changes to `computed`; payslip breakdown is generated for each employee

### 4.6 Review Breakdown for Sample Employee

**Login as:** `hr.manager@ogamierp.local`

1. Click **Review** to enter the detail view
2. Find `hr.staff@ogamierp.local` and open their payslip
3. Verify computed values:
   - **Gross Pay:** Half-month basic pay (approx. 15/26 × monthly rate) + any approved OT pay
   - **SSS Deduction:** Per SSS contribution table applicable to their salary bracket
   - **PhilHealth:** 5% of monthly rate ÷ 2 (employee share for half-month)
   - **Pag-IBIG:** ₱100 (half of ₱200/month)
   - **Withholding Tax:** Per TRAIN Law tax table on taxable gross income
   - **Loan Deduction:** ₱2,500 (if emergency loan from Scenario 1.7 is active and set to this cut-off)
   - **Net Pay:** Gross minus all deductions
4. If Juan dela Cruz is in scope, verify his pay is prorated for 6 days (March 10–15)

### 4.7 Submit for HR Approval

**Login as:** `hr.manager@ogamierp.local`

1. Click **Submit for HR Review**
2. **Verify:** Status changes to `hr_review`

### 4.8 HR Approve (SoD-005 — Self-Approval Blocked)

> **SoD-005:** The submitter cannot HR-approve the same run.

**Test the violation:**

**Login as:** `hr.manager@ogamierp.local` *(same user)*

1. Open the `hr_review` run and click **HR Approve**
2. **Expected:** System blocks — *"SoD violation — you submitted this run and cannot approve it."*

**Complete approval with a second manager:**

> If no second manager exists, create one: **Admin → Users → New User** — Email: `hr.manager2@ogamierp.local`, Role: `manager`, Password: `HrManager@2345!`

**Login as:** `hr.manager2@ogamierp.local`

1. Navigate to **Payroll → Runs**, open the `hr_review` run
2. Review summary totals and click **HR Approve**
3. **Verify:** Status changes to `hr_approved`

### 4.9 Accounting Approve (SoD-007)

> **SoD-007:** Accounting approval must be performed by the accounting officer, not HR.

**Login as:** `acctg.officer@ogamierp.local`

1. Navigate to **Payroll → Runs** (or check the Accounting dashboard for pending approvals)
2. Open the `hr_approved` run
3. Review the GL summary preview:
   - DR Salaries Expense (gross per department)
   - CR SSS Payable, PhilHealth Payable, Pag-IBIG Payable, Withholding Tax Payable
   - CR Net Pay Payable (amount to disburse)
4. Click **Accounting Approve**
5. **Verify:**
   - Status: `acctg_approved`
   - GL journal entry auto-posted for the period

### 4.10 Publish and Download Reports

**Login as:** `hr.manager@ogamierp.local`

1. Open the `acctg_approved` run and click **Publish**
2. **Verify:** Status: `published`
3. Download **Payroll Register** (summary by department) — total should match computed gross values
4. Download **Bank Disbursement File** — sum of all net pays matches total Net Pay Payable from GL

### 4.11 Employee Views Payslip

**Login as:** `hr.staff@ogamierp.local`

1. Navigate to **My Payslips**
2. Open `March 2026 – 1st Half`
3. Click **Download PDF**
4. **Verify:** PDF shows the correct employee name, department, period, and all deduction line items

---

## Scenario 5 — Equipment Failure & Corrective Maintenance

> **Story:** Injection Moulding Machine #1 breaks down on March 12, 2026. The maintenance head creates a corrective work order and closes it the same day. Mold shot logs are updated, and the criticality threshold is reviewed.

### 5.1 Create Corrective Work Order

**Login as:** `maintenance.head@ogamierp.local`

1. Navigate to **Maintenance → Work Orders → New**
2. Fill in:
   - **Equipment:** Injection Moulding Machine #1
   - **Type:** Corrective, **Priority:** Critical
   - **Title:** Hydraulic system leak — production stoppage March 12
   - **Description:** Machine stopped at 09:30. Hydraulic fluid leaking from main cylinder seal. Requires seal replacement.
   - **Scheduled Date:** 2026-03-12
3. Click **Create** → **Start Work**
4. **Verify:** Status `in_progress`, reference `WO-2026-03-00001`

### 5.2 Complete the Work Order

**Login as:** `maintenance.head@ogamierp.local`

1. Click **Complete**
2. Fill in completion notes: *"Replaced main cylinder seal (Part No: MC-SEAL-017). Pressure test passed at 250 bar. Machine operational."*
3. Set **Actual Completion Date:** 2026-03-12, **Labor Hours:** 3.5
4. **Verify:**
   - Work order status: `completed`
   - Equipment status: `operational`

### 5.3 Log Mold Shots and Check Criticality

**Login as:** `mold.manager@ogamierp.local`

1. Navigate to **Mold → Masters**, open **Container 500ml – Cavity 4**
2. **Verify:** Pre-seeded criticality is at 91% — badge shows **CRITICAL**
3. Click **Log Shots**:
   - **Shot Count:** 10,050 (from the production run in Scenario 3)
   - **Log Date:** 2026-03-22
4. **Verify:** Criticality percentage increases further; if it exceeds 95% or 100%, the system flags this mold for maintenance

### 5.4 Review Preventive Maintenance Schedule

**Login as:** `maintenance.head@ogamierp.local`

1. Navigate to **Maintenance → Equipment**, open Injection Moulding Machine #1
2. Check linked PM schedules
3. **Verify:** `next_due` is computed as `last_done_on + frequency_days`
4. If PM is overdue, create a new preventive work order: **Maintenance → Work Orders → New** with **Type: Preventive**

---

## Scenario 6 — QC Failure: NCR and CAPA

> **Story:** An IPQC inspection on a production run finds wall thickness below spec. An NCR is raised, a corrective action is issued to the production head, and after the fix is verified the NCR is closed.

### 6.1 IPQC Inspection — Failing Result

**Login as:** `qcqa.head@ogamierp.local`

1. Navigate to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** IPQC, **Item:** FGD-001, **Lot/Batch:** `LOT-FAIL-2026-03`
   - **Inspection Date:** 2026-03-18, **Qty Inspected:** 50
3. **Submit Results**:
   - Visual contamination: **Conforming**
   - Wall thickness check (min 1.8mm): **Non-Conforming**, Actual: `1.72mm`
4. **Verify:** Inspection status: `failed`

### 6.2 Raise a Non-Conformance Report

**Login as:** `qcqa.head@ogamierp.local`

1. Navigate to **QC / QA → NCRs → New**
2. Fill in:
   - **Title:** Wall thickness below minimum spec — Production Run March 18
   - **Description:** In-process inspection on Lot LOT-FAIL-2026-03 found wall thickness averaging 1.72mm, below the minimum acceptable of 1.80mm. Suspect mold wear in Container 500ml – Cavity 4.
   - **Severity:** Major
3. **Verify:** NCR reference generated (e.g., `NCR-2026-03-00001`), status `open`

### 6.3 Issue CAPA

**Login as:** `qc.manager@ogamierp.local`

1. Navigate to **QC / QA → NCRs**, open `NCR-2026-03-00001`
2. Click **Issue CAPA**:
   - **Type:** Corrective
   - **Description:** Inspect and re-shim Container 500ml – Cavity 4 mold to restore cavity depth. Verify wall thickness with CMM before resuming production. Quarantine suspect units from Lot LOT-FAIL-2026-03.
   - **Due Date:** 2026-03-20, **Assigned To:** production.head@ogamierp.local
3. **Verify:** NCR status: `capa_issued`; CAPA status: `open`

### 6.4 Complete the CAPA

**Login as:** `production.head@ogamierp.local`

1. Navigate to **QC / QA → NCRs**, open the NCR, find the CAPA
2. Click **Complete CAPA**
3. Enter: *"Mold Cavity 4 re-shimmed by +0.16mm. CMM measurement confirms wall thickness now at 1.94mm. Trial units inspected — all conforming. Suspect lot quarantined."*
4. **Verify:** CAPA status: `completed`

### 6.5 Close the NCR

**Login as:** `qc.manager@ogamierp.local`

1. Open `NCR-2026-03-00001`, click **Close NCR**
2. **Verify:** Status: `closed`; all linked CAPAs in `completed` state

---

## Scenario 7 — ISO Internal Audit (Clause 8.4 — External Providers)

> **Story:** Following the short delivery in Scenario 2, the ISO head schedules an internal audit for supplier control procedures, finds a nonconformity, and tracks it to closure.

### 7.1 Create a Controlled Document

**Login as:** `iso.head@ogamierp.local`

1. Navigate to **ISO / IATF → Documents → New**
2. Fill in:
   - **Title:** Supplier Evaluation and Control Procedure
   - **Document Type:** Procedure, **Version:** 1.0
3. Save → click **Under Review** → click **Approve**
4. **Verify:** Document status: `approved`; document code generated (e.g., `DOC-00005`)

### 7.2 Plan and Conduct an Internal Audit

**Login as:** `iso.head@ogamierp.local`

1. Navigate to **ISO / IATF → Internal Audits → New**
2. Fill in:
   - **Scope:** Supplier evaluation process, incoming inspection, GR procedures
   - **Standard:** ISO 9001:2015, **Clauses:** 8.4.1, 8.4.2, 8.6
   - **Planned Date:** 2026-03-20
3. Save (status: `planned`) → **Start Audit** (status: `in_progress`) → **Complete** (status: `completed`)

### 7.3 Record a Finding and Improvement Action

**Login as:** `iso.head@ogamierp.local`

1. On the completed audit, click **Add Finding**:
   - **Type:** Nonconformity, **Clause:** 8.4.1
   - **Description:** Approved supplier list has no re-evaluation schedule. Chinatown Resins Inc. short-delivered 2 kg on `PR-2026-03-00001`; this was not logged as a supplier performance event.
   - **Severity:** Minor
2. Click **Add Improvement Action**:
   - **Action:** Add annual re-evaluation schedule to supplier procedure. Register the short delivery as a performance event. Re-evaluate Chinatown Resins Inc. before next PO.
   - **Due Date:** 2026-04-15, **Assigned To:** purchasing.officer@ogamierp.local
3. After `purchasing.officer` completes the action, click **Complete Action**
4. **Verify:** Finding status: `closed`

---

## Scenario 8 — Period-End Journal Entries and Financial Reports

> **Story:** At month-end, the accounting officer posts depreciation and reviews financial statements. Executives and the VP access read-only financial reports. SoD-008 (self-posting) is tested.

### 8.1 Post Depreciation Journal Entry

**Login as:** `acctg.officer@ogamierp.local`

1. Navigate to **Accounting → Journal Entries → New**
2. Create the JE:
   - **Date:** 2026-03-31, **Description:** Monthly depreciation — Injection Moulding Machine #1
   - Line 1: DR Depreciation Expense — Machinery ₱12,500
   - Line 2: CR Accumulated Depreciation — Machinery ₱12,500
3. Click **Save as Draft → Submit**
4. **Verify:** Status: `submitted`

**Test SoD-008:**

**Login as:** `acctg.officer@ogamierp.local` *(same submitter)*

1. Open the submitted JE and click **Post**
2. **Expected:** System blocks — *"You cannot post a journal entry you submitted."*

**Login as:** `acctg.officer2@ogamierp.local`

1. Find the submitted JE and click **Post**
2. **Verify:** JE status: `posted`; reflects in the GL for March 2026

### 8.2 Review Trial Balance

**Login as:** `acctg.officer@ogamierp.local`

1. Navigate to **Financial Reports → Trial Balance**, select March 2026
2. **Verify:**
   - Total Debits = Total Credits (always)
   - Cash balance = opening + AR receipt (₱313,600) − AP payment (₱87,847) − estimated payroll net disbursements

### 8.3 Balance Sheet and Income Statement

**Login as:** `chairman@ogamierp.local`

1. Navigate to **Financial Reports → Balance Sheet**, as of 2026-03-31
2. **Verify:**
   - Assets = Liabilities + Equity
   - Accounts Receivable reflects any unpaid AR invoices
   - Loans Receivable — Employee includes the ₱15,000 emergency loan

**Login as:** `vp@ogamierp.local`

1. Navigate to **Financial Reports → Income Statement**, period March 1–31, 2026
2. **Verify:**
   - Revenue: ₱280,000 (net of VAT) from Ace Hardware
   - Salaries Expense: matches the computed gross payroll totals

---

## Scenario 9 — Government Compliance Reports

> **Pre-condition:** At least one payroll run must be published (Scenario 4 completed).

**Login as:** `hr.manager@ogamierp.local`

### 9.1 BIR Reports

1. Navigate to **Reports → Government Reports → BIR**
2. **1601-C (Monthly WHT):** Period March 2026 — verify tax total matches sum of all March payslip withholding amounts
3. **BIR 2316 (Annual ITR):** Filter year 2026 — each active employee has a record with YTD compensation data
4. **Alphalist:** Generate for Q1 2026 — employee list, compensation, and tax withheld all populated

### 9.2 SSS, PhilHealth, Pag-IBIG

1. **SSS → SBR-2 Schedule:** March 2026 — verify contribution amounts match the SSS table for each salary bracket
2. **PhilHealth → RF-1:** March 2026 — 5% rate applied; employee and employer shares shown separately
3. **Pag-IBIG → Contribution File:** ₱200/employee (₱100 employee + ₱100 employer) confirmed

---

## Scenario 10 — Access Control and SoD Enforcement Matrix

| Test ID | Scenario | Accounts / Steps | Expected Outcome |
|---------|----------|------------------|------------------|
| SoD-001 | Employee self-activation | `hr.manager` creates employee → tries to activate own record | Blocked: *"Creator cannot activate"* |
| SoD-005 | Payroll self-approval | `hr.manager` submits payroll run → tries HR-approve same run | Blocked: *"Submitter cannot approve"* |
| SoD-007 | HR approves accounting step | `hr.manager` navigates to `/payroll/runs/{id}/acctg-review` | HTTP 403 |
| SoD-008 | JE self-posting | `acctg.officer` submits JE → tries to post own JE | Blocked: *"Creator cannot post"* |
| SoD-009 | AP self-approval | `acctg.officer` submits invoice → tries to approve own invoice | Blocked: *"Submitter cannot approve"* |
| RBAC-01 | Staff → HR list | Login as `hr.staff` → navigate to `/hr/employees/all` | HTTP 403 |
| RBAC-02 | Admin → Payroll | Login as `admin` → navigate to `/payroll/runs` | HTTP 403 |
| RBAC-03 | GA Officer → GL | Login as `ga.officer` → navigate to `/accounting/journal-entries` | HTTP 403 |
| RBAC-04 | Purchasing → Accounting | Login as `purchasing.officer` → navigate to `/accounting/accounts` | HTTP 403 |
| RBAC-05 | Plant Mgr → HR list | Login as `plant.manager` → navigate to `/hr/employees/all` | HTTP 403 |
| RBAC-06 | ImpEx → AP invoices | Login as `impex.officer` → navigate to `/accounting/ap-invoices` | HTTP 403 |
| BIZ-01 | Leave with zero balance | `hr.staff` files a second VL when balance = 0 | Rejected: *"Insufficient leave balance"* |
| BIZ-02 | Negative net pay on payslip | Manually add a large deduction to a low-wage employee's run | System flags as error during validation step |

---

## Scenario 11 — Dashboard Verification

### 11.1 Executive Dashboard

**Login as:** `chairman@ogamierp.local`

- **Financial Reports** section visible in sidebar — can view Balance Sheet, Income Statement, Trial Balance
- KPI cards: Headcount, Attrition Rate — values reflect active employee count
- Revenue/Expense trend shows March activity
- Navigate to Balance Sheet — no 403, data loads correctly

### 11.2 VP Approvals Dashboard

**Login as:** `vp@ogamierp.local`

- **Pending Approvals counter:** combined loans + PRs + MRQs at step `reviewed` / `officer_reviewed`
- **Purchase Requests tab:** shows the PP Resin PR (or `approved` if Scenario 2 completed)
- **Loans tab:** shows the emergency loan (or `active` if Scenario 1.7 completed)
- **Financial Reports** section visible in sidebar

### 11.3 Accounting Officer Dashboard

**Login as:** `acctg.officer@ogamierp.local`

- **Pending Vendor Invoices:** reflects submitted/pending AP invoices
- **JEs to Post:** shows submitted JEs needing second officer (from Scenario 8.1)
- **Payroll Pending Acctg Approval:** if Scenario 4 is at `hr_approved`, this counter shows 1
- Sidebar: Accounting, AP Invoices, AP Vendors, VAT Ledger, Financial Reports all accessible

### 11.4 GA Officer Dashboard

**Login as:** `ga.officer@ogamierp.local`

- Team overview cards: Team Members, Present Today, On Leave, Absent/Late
- **Team Management → Shift Schedules** visible in sidebar
- No financial modules (Accounting, AP, AR, Payroll) in sidebar
- Navigate to `/accounting/journal-entries` → 403 Forbidden

### 11.5 Purchasing Officer Dashboard

**Login as:** `purchasing.officer@ogamierp.local`

- Procurement stats: Pending PRs, Draft POs, Sent POs
- **Quick links:** Purchase Requests, Purchase Orders, Goods Receipts, Vendors all accessible
- No Accounting or Payroll sections in sidebar
- `PR-2026-03-00001` appears in the PR list with its current status

### 11.6 ImpEx Officer Dashboard

**Login as:** `impex.officer@ogamierp.local`

- Delivery stats: Pending Inbound Receipts, Active Shipments
- After Scenario 3.6 completion: active shipments count = 0
- **Delivery → Shipments** visible in sidebar
- No Accounting, Payroll, or Procurement in sidebar

### 11.7 Plant Manager Dashboard

**Login as:** `plant.manager@ogamierp.local`

- Production overview: active orders, pending QC inspections
- **Team Management** section visible (plant_manager is included in team roles)
- NCR count from Scenario 6 reflects correctly (0 if closed, 1 if still open)

### 11.8 QC Manager Dashboard

**Login as:** `qc.manager@ogamierp.local`

- **Team Management** section visible
- **QC / QA → Templates** visible — shows PP Resin Incoming Inspection template
- NCR count: 0 if Scenario 6 closed, 1 if still open

### 11.9 Warehouse Head Dashboard

**Login as:** `warehouse.head@ogamierp.local`

- **Team Management → Team Loans** visible; pending loan notes show count
- After Scenario 1.7: emergency loan at `head_noted` (if this head acted) or `pending` (if not)
- Procurement activity visible: recent GRs listed

### 11.10 Staff Self-Service Dashboard

**Login as:** `hr.staff@ogamierp.local`

- **Leave Balance:** VL deducted by 1 day after Scenario 1.6
- **Active Loan:** Emergency loan from Scenario 1.7 shows monthly amortization ₱2,500 and remaining balance
- **Payslip:** March 1st Half available after Scenario 4.10 — loan deduction is itemized

---

## Test Completion Checklist

Complete all checkboxes before certifying a release:

**Scenario 1 — Employee Onboarding**
- [ ] Employee record created (draft state verified)
- [ ] SoD-001 violation tested and blocked
- [ ] Shift assigned (Day Shift 8h from 2026-03-10)
- [ ] Employment contract document uploaded
- [ ] Attendance manually logged — worked_minutes and late_minutes verified
- [ ] Leave request: 4-step approval chain completed (submitted → head_approved → manager_checked → ga_processed → approved)
- [ ] Loan: full 5-stage chain completed (pending → head_noted → manager_checked → officer_reviewed → approved → active)

**Scenario 2 — Procurement Cycle**
- [ ] PR created by production.head, 5-step approval chain completed (PR-2026-03-00001 → approved)
- [ ] PO-2026-03-00001 created and sent to Chinatown Resins Inc.
- [ ] GR-2026-03-00001 created for 498 kg (short delivery); PO status = `partially_received`
- [ ] GR-2026-03-00002 created for remaining 2 kg; PO status = `fully_received`
- [ ] Inventory RAW-001 stock increased by 500 kg total
- [ ] IQC inspection passed for Lot LOT-CH-2026-03-14
- [ ] AP invoice CR-INV-2026-1542 for ₱89,640 submitted
- [ ] SoD-009 tested (self-approval blocked)
- [ ] Invoice approved by second officer
- [ ] Payment ₱87,847 recorded; invoice status = `paid`
- [ ] GL entry (DR AP / CR EWT Payable / CR Cash) verified in General Ledger

**Scenario 3 — Customer Order Fulfillment**
- [ ] Delivery schedule created for Ace Hardware, 10,000 units
- [ ] Production order created, released, started, output logged, completed
- [ ] FGD-001 stock increased by ~10,007 units; RAW-001 stock decreased
- [ ] OQC inspection passed (LOT-ACE-2026-03-22)
- [ ] Outbound DR-OUT-2026-00001 confirmed; FGD-001 stock decreased by 10,000
- [ ] Shipment SHP via JRS Express tracked to `delivered`
- [ ] AR invoice for ₱313,600 approved
- [ ] Customer payment received; invoice = `paid`; GL entry verified

**Scenario 4 — Payroll Run**
- [ ] Pay period March 2026 – 1st Half confirmed open
- [ ] Run initiated → scoped → validated (no blockers)
- [ ] Computed — all 17 pipeline steps completed
- [ ] Breakdown reviewed: SSS, PhilHealth, Pag-IBIG, tax, and loan deduction all present and correct
- [ ] SoD-005 tested (self-HR-approval blocked)
- [ ] HR-approved by second manager
- [ ] Accounting-approved by acctg.officer; GL entry verified
- [ ] Published; Payroll Register and Bank Disbursement File downloaded
- [ ] Staff payslip downloaded (PDF) — loan deduction and leave deduction itemized

**Scenario 5 — Maintenance**
- [ ] Corrective work order WO-2026-03-00001 created, started, completed for Machine #1
- [ ] Equipment status reverts to `operational` after completion
- [ ] Mold shots logged for Container 500ml – Cavity 4 (10,050 shots)
- [ ] Mold criticality reviewed — CRITICAL badge present

**Scenario 6 — NCR and CAPA**
- [ ] IPQC inspection LOT-FAIL-2026-03 marked `failed` (wall thickness 1.72mm)
- [ ] NCR-2026-03-00001 raised with severity `major`
- [ ] CAPA issued by qc.manager to production.head
- [ ] CAPA completed with corrective notes
- [ ] NCR closed

**Scenario 7 — ISO**
- [ ] Controlled document (Supplier Evaluation Procedure) created and approved
- [ ] Internal audit for Clause 8.4 planned → completed
- [ ] Nonconformity finding raised; improvement action assigned and closed

**Scenario 8 — Financial Reporting**
- [ ] Depreciation JE submitted by acctg.officer
- [ ] SoD-008 tested (self-posting blocked by system)
- [ ] JE posted by second officer
- [ ] Trial Balance: debits = credits
- [ ] Balance Sheet: Assets = Liabilities + Equity
- [ ] Income Statement: Revenue reflects Ace Hardware invoice; Salaries match payroll

**Scenario 9 — Government Reports**
- [ ] BIR 1601-C generated — WHT total matches payroll
- [ ] BIR 2316 per-employee records present
- [ ] Alphalist generated for Q1 2026
- [ ] SSS SBR-2 contribution schedule generated
- [ ] PhilHealth RF-1 generated
- [ ] Pag-IBIG contribution file generated

**Scenario 10 — Access Controls**
- [ ] All 5 SoD violations blocked as expected
- [ ] All 6 RBAC-0x tests return 403 Forbidden
- [ ] Business rule BIZ-01 (zero leave balance) enforced

**Scenario 11 — Dashboards**
- [ ] All 9 role dashboards verified for correct widgets and sidebar access
- [ ] Audit Logs: **Admin → Audit Logs** shows all major Scenario 1–8 operations with user, entity, before/after values
