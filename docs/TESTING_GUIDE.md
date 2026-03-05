# Ogami ERP — End-to-End Testing Guide

> **Environment:** `http://localhost:5173` (Vite dev server) → API at `http://127.0.0.1:8000`
> **Start services:** `npm run dev` (or `bash dev.sh`) from the project root.

---

## Seeded User Accounts Quick Reference

> All accounts have MFA disabled in dev/test. Passwords are as listed below.

### Executive & VP

| Email | Password | Role | Position | Purpose |
|-------|----------|------|----------|---------|
| `chairman@ogamierp.local` | `Executive@12345!` | **executive** | Chairman | Highest authority. Final approver for leave/OT filed by managers. Views executive KPI dashboard. |
| `president@ogamierp.local` | `Executive@12345!` | **executive** | President | Same permissions as Chairman (co-executive). Use for SoD testing when Chairman already acted. |
| `vp@ogamierp.local` | `VicePresident@1!` | **vice_president** | Vice President | Final approver for Loans (step 5), Purchase Requests (step 5), and MRQs. Views VP approval dashboard. |

### Managers (role: `manager`)

| Email | Password | Position | Purpose |
|-------|----------|----------|---------|
| `plant.manager@ogamierp.local` | `Manager@12345!` | Plant Manager | **Plant operations only** (role: `plant_manager`). Full access to Production, QC/QA, Maintenance, Mold, Delivery, ISO/IATF. View-only for inventory stock. Self-service for own leave, OT, loans, payslips. No HR, payroll, or approval workflow access. |
| `hr.manager@ogamierp.local` | `HrManager@1234!` | HR Manager | **Primary HR & payroll manager** (role: `manager`). Manages employees, attendance, leave, loans, initiates and approves payroll runs. Use as the **HR-approve** step in SoD tests. |
| `prod.manager@ogamierp.local` | `Manager@12345!` | Production Manager | Production orders, BOMs, production KPIs (role: `plant_manager`). |
| `qc.manager@ogamierp.local` | `Manager@12345!` | QC/QA Manager | Quality management: inspection templates, CAPAs, NCRs (role: `plant_manager`). |
| `mold.manager@ogamierp.local` | `Manager@12345!` | Mold Manager | Mold master management, shot logging, criticality tracking (role: `plant_manager`). |

### Officers (role: `officer`)

| Email | Password | Position | Purpose |
|-------|----------|----------|---------|
| `acctg.officer@ogamierp.local` | `AcctgManager@1234!` | Accounting Officer | **Primary accounting user.** Posts journal entries, approves AP invoices (SoD-009), accounting-approves payroll (step 6), loan step 4. |
| `ga.officer@ogamierp.local` | `Officer@12345!` | GA Officer | Creates JEs, vendor invoices, customer invoices, manages AP/AR. Use as JE **creator** in SoD tests (acctg.officer is the **poster**). |
| `purchasing.officer@ogamierp.local` | `Officer@12345!` | Purchasing Officer | Procurement: reviews Purchase Requests (step 4), converts PRs to POs. |
| `impex.officer@ogamierp.local` | `Officer@12345!` | ImpEx Officer | Import/Export operations. Spare officer account for additional SoD scenarios. |

### Department Heads (role: `head`)

| Email | Password | Department | Purpose |
|-------|----------|------------|---------|
| `warehouse.head@ogamierp.local` | `Head@123456789!` | Warehouse | Loan step 2 (head notes), GR confirmation, inventory adjustments, inbound delivery receipts. |
| `ppc.head@ogamierp.local` | `Head@123456789!` | PPC | Production Planning & Control head. Manages delivery schedules. |
| `maintenance.head@ogamierp.local` | `Head@123456789!` | Maintenance | Creates work orders, manages PM schedules, equipment records. |
| `production.head@ogamierp.local` | `Head@123456789!` | Production | Releases production orders, logs output, completes production runs. |
| `processing.head@ogamierp.local` | `Head@123456789!` | Processing | Processing dept head. Secondary head for OT/leave endorsement tests. |
| `qcqa.head@ogamierp.local` | `Head@123456789!` | QC/QA | Creates QC inspections, raises NCRs, issues corrective actions. |
| `iso.head@ogamierp.local` | `Head@123456789!` | ISO/Mgmt System | Creates controlled ISO documents, conducts internal audits, manages findings. |
| `hr.supervisor@ogamierp.local` | `HrSupervisor@1234!` | HR | **Primary head for workflow tests.** Endorses OT and leave from HR staff. Linked to HR dept. |

### Staff (role: `staff`)

| Email | Password | Department | Purpose |
|-------|----------|------------|---------|
| `hr.staff@ogamierp.local` | `HrStaff@1234!` | HR | **Primary end-user.** Files leave, submits OT requests, applies for loans, views own payslips. |

### System Admin (role: `admin`)

| Email | Password | Purpose |
|-------|----------|---------|
| `admin@ogamierp.local` | `Admin@1234567890!` | System configuration, user management, role assignment. Has **no access** to HR/payroll/accounting business data — this is by design. |

---

## Module 1 — HR (Employee Management)

### 1.1 Add a New Employee

**Login as:** `hr.manager@ogamierp.local` (role: `manager`)

1. Navigate to **HR → Employees → Add Employee**
2. Fill in required fields:
   - First Name, Last Name, Date of Birth, Gender, Civil Status
   - Department: select any (e.g., `PROD`)
   - Position: select a position in that department
   - Salary Grade: e.g., `SG-05`
   - Hired Date: any past date
3. Fill in Government IDs tab:
   - SSS No, TIN, PhilHealth No, Pag-IBIG No
4. Fill in Bank Details tab:
   - Bank Name, Account Number
5. Click **Save** — employee is created in `draft` status
6. **Verify:** Employee appears in the list with status `draft`

### 1.2 Activate an Employee (SoD enforcement)

> **SoD-001:** The user who created the employee **cannot** activate them.

**Login as:** `hr.manager@ogamierp.local` *(same account — the system should reject the activation attempt)*

1. Try to activate the employee you just created
2. **Verify:** System rejects with a SoD error — only a *different* user with `employees.activate` can activate

1. Find the newly created employee
2. Click **Activate** (or change status to `active`)
3. **Verify:** Status changes to `active`; the system should reject the activation if attempted by the same user who created the record

### 1.3 Upload Employee Documents

**Login as:** `hr.manager@ogamierp.local`

1. Open any active employee record
2. Go to **Documents** tab
3. Upload a file (e.g., PDF contract)
4. **Verify:** File appears in the documents list; download works

### 1.4 Update Employee Salary

**Login as:** `hr.manager@ogamierp.local`

1. Open any active employee
2. Go to **Compensation** tab
3. Change `Basic Monthly Rate` to a value within the employee's salary grade range
4. **Verify:** Change is saved; audit trail entry is created

### 1.5 Suspend / Terminate an Employee

**Login as:** `hr.manager@ogamierp.local`

1. Open an active employee
2. Click **Suspend** — status changes to `suspended`
3. Click **Terminate** — status changes to `terminated`
4. **Verify:** Terminated employee cannot be included in future payroll runs

---

## Module 2 — Attendance

### 2.1 Log Attendance (Manual Entry)

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Attendance → Logs → Add Entry**
2. Select employee, date, time-in, time-out
3. Save
4. **Verify:** Log appears; late/overtime minutes are computed

### 2.2 Import Attendance via CSV

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Attendance → Import CSV**
2. Download the template (if available) or prepare a CSV with columns: `employee_code, date, time_in, time_out`
3. Upload the CSV
4. **Verify:** Records are created; count matches uploaded rows

### 2.3 Assign Shift Schedule

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Attendance → Shift Assignments**
2. Select an employee and assign them to a shift (e.g., `Day Shift 8h`)
3. **Verify:** Assignment appears in the employee's schedule

---

## Module 3 — Overtime (OT) Request Workflow

> **Approval chains by requester role:**
> - **Staff** → head endorses → **manager approves** *(final — no executive step)*
> - **Head / Officer** → **manager approves** *(final — no executive step)*
> - **Manager** → **executive approves** *(final)*

### 3.1 Submit OT Request (from Staff)

**Login as:** `hr.staff@ogamierp.local` (role: `staff`)

1. Navigate to **Attendance → Overtime Requests → New**
2. Select date, OT hours, reason
3. Click **Submit**
4. **Verify:** Request appears with status `submitted`

### 3.2 Head Endorsement

**Login as:** `hr.supervisor@ogamierp.local` (role: `head`)

1. Navigate to **Attendance → Overtime → Team Requests**
2. Find the submitted OT request
3. Click **Endorse**
4. **Verify:** Status changes to `head_endorsed`

### 3.3 Manager Final Approval (Staff / Head / Officer requests)

**Login as:** `plant.manager@ogamierp.local` (role: `plant_manager`)

1. Navigate to **Attendance → Overtime → Pending Approvals**
2. Find the endorsed OT request
3. Click **Approve**
4. **Verify:** Status changes to `approved` — this is the **final state** for requests from staff, heads, and officers

### 3.4 OT from a Manager — Executive Final Approval

> Executive approval is **only** triggered when the requester is a `manager`-role user.

**Login as:** `plant.manager@ogamierp.local` (role: `plant_manager`) — *submit the OT*

1. Navigate to **Attendance → Overtime Requests → New**
2. Submit OT request

**Login as:** `chairman@ogamierp.local` (role: `executive`)

1. Navigate to **Attendance → Overtime → Pending Executive**
2. Find the manager's request
3. Click **Executive Approve**
4. **Verify:** Status changes to `executive_approved`; OT included in next payroll

---

## Module 4 — Leave Request Workflow

> **Approval chains by requester role:**
> - **Staff** → head supervises → **manager approves** *(final — no executive step)*
> - **Head / Officer** → **manager approves** *(final — no executive step)*
> - **Manager** → **executive approves** *(final)*

### 4.1 File a Leave Request (from Staff)

**Login as:** `hr.staff@ogamierp.local`

1. Navigate to **Leave → New Request**
2. Select Leave Type (e.g., `Vacation Leave`), start date, end date
3. Check available balance — must be > 0
4. Click **Submit**
5. **Verify:** Status is `submitted`

### 4.2 Head Supervision

**Login as:** `hr.supervisor@ogamierp.local`

1. Navigate to **Leave → Team Requests**
2. Find the leave request
3. Click **Supervise / Endorse**
4. **Verify:** Status changes to `supervisor_approved`

### 4.3 Manager Final Approval (Staff / Head / Officer requests)

**Login as:** `plant.manager@ogamierp.local`

1. Navigate to **Leave → Pending Approvals**
2. Find the supervised request
3. Click **Approve**
4. **Verify:** Status changes to `approved` — this is the **final state** for requests from staff, heads, and officers

### 4.4 Leave from a Manager — Executive Final Approval

> Executive approval is **only** triggered when the requester is a `manager`-role user.

**Login as:** `plant.manager@ogamierp.local` — *submit the leave request*

1. Navigate to **Leave → New Request**, submit leave

**Login as:** `chairman@ogamierp.local`

1. Navigate to **Leave → Pending Executive**
2. Click **Executive Approve**
3. **Verify:** Status changes to `executive_approved`

### 4.5 Adjust Leave Balance

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Leave → Balance Management**
2. Select employee, leave type
3. Adjust balance (add or deduct days)
4. **Verify:** Balance is updated; audit trail is recorded

---

## Module 5 — Loan Request Workflow (5-Stage)

> **Chain:** `staff` applies → `head` notes → `manager` checks → `officer` reviews → `vp` approves

### 5.1 Apply for a Loan

**Login as:** `hr.staff@ogamierp.local`

1. Navigate to **Loans → New Application**
2. Select Loan Type (e.g., `Emergency Loan`), amount, number of months
3. The amortization schedule is auto-calculated
4. Click **Submit**
5. **Verify:** Status is `submitted`; reference number is generated

### 5.2 Head Notes the Loan

**Login as:** `warehouse.head@ogamierp.local` *(different person from applicant's dept head)*

1. Navigate to **Loans → Pending — Head Review**
2. Find the loan application
3. Click **Note** (add comment if required)
4. **Verify:** Status changes to `head_noted`

### 5.3 Manager Checks the Loan

**Login as:** `plant.manager@ogamierp.local`

1. Navigate to **Loans → Pending — Manager**
2. Find the noted loan
3. Click **Check / Recommend**
4. **Verify:** Status changes to `manager_checked`

### 5.4 Officer Reviews the Loan

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **Loans → Pending — Officer Review**
2. Find the checked loan
3. Click **Review / Endorse**
4. **Verify:** Status changes to `officer_reviewed`

### 5.5 VP Final Approval

**Login as:** `vp@ogamierp.local`

1. Navigate to **Approvals → VP Dashboard** or **Loans → Pending — VP**
2. Find the officer-reviewed loan
3. Click **VP Approve**
4. **Verify:** Status changes to `approved`; amortization deductions will appear in payroll

### 5.6 Test Loan Rejection

**Any approver can reject at their step:**

1. At any stage above, click **Reject** instead of Approve/Note/Check/Review
2. **Verify:** Status changes to `rejected`; applicant's account shows rejection

---

## Module 6 — Payroll

> **Full pipeline:** Initiate → Scope → Compute → Submit → HR Approve → Acctg Approve → Disburse/Publish

### 6.1 Manage Pay Periods

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Payroll → Pay Periods**
2. Verify existing periods for 2026 are listed (seeded: Jan–Apr, Jun)
3. Create a new period if needed (Status must be `open`)

### 6.2 Initiate a Payroll Run

**Login as:** `hr.manager@ogamierp.local`

1. Navigate to **Payroll → Runs → New Run**
2. Select pay period (e.g., `March 2026 – 1st Half`)
3. Select run type: `regular`
4. Click **Initiate**
5. **Verify:** Run is created with status `draft`

### 6.3 Scope the Run (Include/Exclude Employees)

**Login as:** `hr.manager@ogamierp.local`

1. Open the draft payroll run
2. Click **Scope** — view employees included
3. Preview scope; optionally exclude specific employees
4. Click **Lock Scope**
5. **Verify:** Status changes to `scoped`

### 6.4 Run Pre-Run Checks

**Login as:** `hr.manager@ogamierp.local`

1. On the scoped run, click **Pre-Run Validation**
2. Review warnings (missing attendance records, anomalies, etc.)
3. Resolve any blockers
4. **Verify:** Checks pass (or show only non-blocking warnings)

### 6.5 Compute Payroll

**Login as:** `hr.manager@ogamierp.local`

1. Click **Compute** — this dispatches the 17-step computation pipeline to the queue
2. Monitor progress via the **Progress** indicator
3. **Verify:** Status changes to `computed`; payroll details are generated for all scoped employees

### 6.6 Review Breakdown / Flag Employee

**Login as:** `hr.manager@ogamierp.local`

1. Click **Review** → view individual employee payslip breakdowns
2. Optionally flag an employee's detail for review
3. **Verify:** Flagged rows are highlighted

### 6.7 Submit for HR Approval

**Login as:** `hr.manager@ogamierp.local`

1. Click **Submit for HR**
2. **Verify:** Status changes to `SUBMITTED`

### 6.8 HR Approve

> **SoD-005/006:** Cannot be approved by the person who submitted.

**Login as:** `plant.manager@ogamierp.local` is **not eligible**. Use a second `manager`-role account or confirm with the client how the SoD second signatory is handled.

> For now, use `hr.manager@ogamierp.local` to submit AND a second test manager account to approve if available. Otherwise document this as a process gap for the client.

### 6.9 Accounting Approve

> **SoD-007:** Must be performed by officer, not the HR approver.

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **Payroll → Pending Acctg Approval** (also visible on Officer Dashboard)
2. Open the HR-approved run
3. Click **Accounting Approve**
4. **Verify:** Status changes to `ACCTG_APPROVED`

### 6.10 Publish / Disburse Payroll

**Login as:** `hr.manager@ogamierp.local`

1. On the accounting-approved run, click **Publish**
2. Optionally download **Bank Disbursement File**
3. Optionally download **Payroll Register**
4. **Verify:** Status changes to `PUBLISHED`; employees can now view payslips

### 6.11 Employee Views Payslip

**Login as:** `hr.staff@ogamierp.local`

1. Navigate to **My Payslips**
2. Select the published run's payslip
3. Click **Download PDF**
4. **Verify:** PDF is generated with correct gross pay, deductions, net pay

---

## Module 7 — Accounting (GL, AP, AR)

### 7.1 Chart of Accounts

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **Accounting → Chart of Accounts**
2. View existing seeded accounts
3. Create a new leaf account (e.g., under `6000 – Operating Expenses`)
4. **Verify:** Account appears in COA tree

### 7.2 Journal Entries

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **Accounting → Journal Entries → New**
2. Add at least 2 lines (debit + credit, must balance to zero)
3. Click **Save as Draft**
4. Click **Submit**
5. **Verify:** Status changes to `SUBMITTED`

**Post a Journal Entry (SoD-008):**

> The poster must be different from the creator.

**Login as:** `acctg.officer@ogamierp.local` *(second officer — different from the creator)*

1. Find the submitted JE
2. Click **Post**
3. **Verify:** Status changes to `POSTED`; GL balances are updated

### 7.3 Accounts Payable — Vendor Invoice

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **AP → Vendors** — verify `Chinatown Resins Inc.` exists (seeded)
2. Navigate to **AP → Invoices → New**
3. Select vendor, enter invoice number, date, amount, EWT ATC code
4. Click **Save**, then **Submit**
5. **Verify:** Status `PENDING_APPROVAL`

**Approve AP Invoice (SoD-009):**

**Login as:** `acctg.officer@ogamierp.local` *(must be different from the submitter)*

1. Open the submitted vendor invoice
2. Click **Approve**
3. **Verify:** Status changes to `APPROVED`; VAT ledger entry created

**Record Payment:**

**Login as:** `ga.officer@ogamierp.local`

1. Open approved vendor invoice
2. Click **Record Payment** → enter payment amount, date, bank account
3. **Verify:** Invoice status changes to `PAID` or `PARTIALLY_PAID`; JE for payment is created

### 7.4 Accounts Receivable — Customer Invoice

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **AR → Customers** — verify `Ace Hardware Philippines` exists (seeded)
2. Navigate to **AR → Invoices → New**
3. Select customer, line items, due date
4. Click **Save**, then **Approve** *(officer can self-approve AR)*
5. **Verify:** Status `APPROVED`

**Receive Payment:**

1. Open approved AR invoice
2. Click **Receive Payment** → enter amount, date
3. **Verify:** Invoice status becomes `PAID`; JE created

### 7.5 Bank Reconciliation

**Login as:** `ga.officer@ogamierp.local`

1. Navigate to **Accounting → Bank Reconciliation → New**
2. Select bank account and period
3. Import bank statement (CSV)
4. Match transactions
5. Click **Certify**
6. **Verify:** Reconciliation is marked as `certified`

### 7.6 Financial Reports

**Login as:** `ga.officer@ogamierp.local` or `chairman@ogamierp.local`

1. Navigate to **Reports**
2. Test each report:
   - **Trial Balance** — verify debits equal credits
   - **Income Statement** — revenue vs expenses
   - **Balance Sheet** — assets = liabilities + equity
   - **GL Report** — filter by account code

---

## Module 8 — Procurement (Purchase Request → PO → Goods Receipt)

> **PR approval chain:** `staff` submits → `head` notes → `manager` checks → `officer` reviews → `vp` approves → convert to PO

### 8.1 Create a Purchase Request

**Login as:** `hr.staff@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests → New**
2. Fill in title, items requested (description, quantity, estimated cost)
3. Click **Submit**
4. **Verify:** Status `submitted`; PR reference number generated (e.g., `PR-2026-03-00001`)

### 8.2 Head Notes the PR

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests → Pending — Head**
2. Click **Note** on the submitted PR
3. **Verify:** Status changes to `noted`

### 8.3 Manager Checks the PR

**Login as:** `plant.manager@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests → Pending — Manager**
2. Click **Check**
3. **Verify:** Status changes to `checked`

### 8.4 Officer Reviews the PR

**Login as:** `purchasing.officer@ogamierp.local`

1. Navigate to **Procurement → Purchase Requests → Pending — Officer** (also on Officer Dashboard)
2. Click **Review**
3. **Verify:** Status changes to `reviewed`

### 8.5 VP Approves the PR

**Login as:** `vp@ogamierp.local`

1. Navigate to **Approvals → VP Dashboard** → Purchase Requests tab
2. Click **VP Approve**
3. **Verify:** Status changes to `approved`

### 8.6 Convert PR to Purchase Order

**Login as:** `purchasing.officer@ogamierp.local`

1. Open the approved PR
2. Click **Convert to PO**
3. Select vendor (e.g., `Chinatown Resins Inc.`)
4. Confirm line items, prices, delivery date
5. Click **Create PO**
6. **Verify:** PO created with status `draft`; PR status becomes `converted_to_po`

### 8.7 Send Purchase Order

**Login as:** `purchasing.officer@ogamierp.local`

1. Open the draft PO
2. Click **Send** (marks as sent to vendor)
3. **Verify:** Status changes to `sent`

### 8.8 Receive Goods (Goods Receipt)

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Procurement → Goods Receipts → New**
2. Link to the PO, enter received quantities per line
3. Click **Save**
4. Click **Confirm**
5. **Verify:** GR status `confirmed`; PO status becomes `partially_received` or `fully_received`

---

## Module 9 — Inventory & Warehouse

### 9.1 View Item Master

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Inventory → Items**
2. Verify seeded items: `RAW-001 PP Resin Natural`, `RAW-002 HDPE Resin Black`, `FGD-001 Plastic Container 500ml`

### 9.2 Material Requisition (MRQ)

> **Chain:** `head` creates → `head` notes → `manager` checks → `officer` reviews → `vp` approves → `head` fulfills

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Inventory → Material Requisitions → New**
2. Select items and quantities needed
3. Click **Submit**
4. Follow the same 5-stage approval chain as Purchase Requests (steps 8.2–8.5)
5. After VP approval, click **Fulfill** to issue materials from stock
6. **Verify:** Stock levels decrease; MRQ status is `fulfilled`

### 9.3 Inventory Adjustment

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Inventory → Adjustments → New**
2. Select item, location, adjustment type (add/remove), quantity, reason
3. Save
4. **Verify:** Stock balance reflects adjustment

---

## Module 10 — Production

### 10.1 Create Bill of Materials (BOM)

**Login as:** `prod.manager@ogamierp.local`

1. Navigate to **Production → BOMs → New**
2. Select finished good item, add raw material components with quantities
3. Save
4. **Verify:** BOM is listed for the selected item

### 10.2 Create Production Order

**Login as:** `production.head@ogamierp.local`

1. Navigate to **Production → Orders → New**
2. Select BOM, quantity to produce, target date
3. Click **Create** (status: `draft`)
4. Click **Release** (status: `released`)
5. Click **Start** (status: `in_progress`)

### 10.3 Log Production Output

**Login as:** `production.head@ogamierp.local`

1. On an `in_progress` production order, click **Log Output**
2. Enter actual quantity produced
3. Save

### 10.4 Complete Production Order

**Login as:** `prod.manager@ogamierp.local`

1. Open the production order
2. Click **Complete**
3. **Verify:** Status changes to `completed`; finished goods inventory increases

### 10.5 Delivery Schedule

**Login as:** `ppc.head@ogamierp.local`

1. Navigate to **Production → Delivery Schedules → New**
2. Link to customer / order, set dates and quantities
3. Save and view schedule
4. **Verify:** Schedule appears in the list

---

## Module 11 — QC / Quality Management

### 11.1 Create Inspection Template

**Login as:** `qc.manager@ogamierp.local`

1. Navigate to **QC → Templates → New**
2. Set template name, stage (`iqc` / `ipqc` / `oqc`)
3. Add criteria (e.g., `Visual Inspection: No cracks`, `Weight: 50g ± 2g`)
4. Save
5. **Verify:** Template is available for inspections

### 11.2 Create Inspection

**Login as:** `qcqa.head@ogamierp.local`

1. Navigate to **QC → Inspections → New**
2. Link to a Goods Receipt or Production Order
3. Select template, set inspection date, enter quantities
4. Save (status: `open`)
5. Click **Submit Results** → enter pass/fail per criterion
6. **Verify:** Status changes to `passed` or `failed`

### 11.3 Raise a Non-Conformance Report (NCR)

**Login as:** `qcqa.head@ogamierp.local`

1. Navigate to **QC → NCRs → New**
2. Link to a failed inspection
3. Set title, description, severity (`minor` or `major`)
4. Save (status: `open`)

### 11.4 Issue CAPA (Corrective/Preventive Action)

**Login as:** `qc.manager@ogamierp.local`

1. Open the NCR
2. Click **Issue CAPA**
3. Enter action description, type (`corrective` or `preventive`), due date, assign to user
4. Save
5. Later — click **Complete CAPA** to close it
6. Click **Close NCR** when all CAPAs are done

---

## Module 12 — Maintenance

### 12.1 View Equipment

**Login as:** `maintenance.head@ogamierp.local`

1. Navigate to **Maintenance → Equipment**
2. Verify seeded equipment: `Injection Moulding Machine #1`, `Hydraulic Press #3`
3. Check status indicators (operational, under_maintenance, decommissioned)

### 12.2 Create a Work Order

**Login as:** `maintenance.head@ogamierp.local`

1. Navigate to **Maintenance → Work Orders → New**
2. Select equipment, type (`corrective` or `preventive`), priority (`low/normal/high/critical`)
3. Fill in title, description, scheduled date
4. Click **Create** (status: `open`)

### 12.3 Complete a Work Order

**Login as:** `maintenance.head@ogamierp.local`

1. Open an `open` or `in_progress` work order
2. Click **Complete**
3. Enter completion notes, actual completion date
4. **Verify:** Status changes to `completed`; equipment status updated

### 12.4 PM Schedules

1. Navigate to **Maintenance → PM Schedules**
2. Verify seeded PM schedules for `Injection Moulding Machine #1`
3. Check `next_due` is computed from `last_done_on + frequency_days`

---

## Module 13 — Mold Management

### 13.1 View Mold Masters

**Login as:** `mold.manager@ogamierp.local`

1. Navigate to **Mold → Masters**
2. Verify seeded molds: `Container 500ml – Cavity 4` (91% shots used — CRITICAL)
3. Check status (`active`, `under_maintenance`, `retired`)

### 13.2 Log Shots

**Login as:** `mold.manager@ogamierp.local`

1. Open an active mold
2. Click **Log Shots**
3. Enter shot count, log date, operator
4. **Verify:** `current_shots` increases; criticality indicator updates

---

## Module 14 — Delivery & Logistics

### 14.1 Create Inbound Delivery Receipt

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Delivery → Receipts → New**
2. Set direction: `inbound`, link to a vendor
3. Add line items (item, expected qty, unit)
4. Save (status: `draft`)
5. Click **Confirm**
6. **Verify:** Status changes to `confirmed`; inventory stock is updated

### 14.2 Create Outbound Delivery Receipt

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Delivery → Receipts → New**
2. Set direction: `outbound`, link to a customer
3. Add finished goods line items
4. Save (status: `draft`)
5. **Verify:** Outbound DR appears on Officer Dashboard `delivery.outbound_draft`

### 14.3 Create a Shipment

**Login as:** `warehouse.head@ogamierp.local`

1. Navigate to **Delivery → Shipments → New**
2. Link to a confirmed delivery receipt
3. Enter carrier, tracking number, shipped date, estimated arrival
4. Save (status: `pending`)
5. Update to `in_transit`
6. Update to `delivered`
7. **Verify:** Shipment status trail is correct

---

## Module 15 — ISO / IATF Document Management

### 15.1 Create a Controlled Document

**Login as:** `iso.head@ogamierp.local`

1. Navigate to **ISO → Documents → New**
2. Select category, document type, enter title, version
3. Set status to `draft`
4. Click **Under Review**, then **Approve**
5. **Verify:** Status changes to `approved`; document code auto-generated (e.g., `DOC-00004`)

### 15.2 Internal Audit

**Login as:** `iso.head@ogamierp.local`

1. Navigate to **ISO → Internal Audits → New**
2. Set scope, standard (`ISO 9001:2015` or `IATF 16949:2016`), lead auditor, date
3. Save (status: `planned`)
4. Start the audit → status: `in_progress`
5. Complete → status: `completed`

### 15.3 Audit Finding & Improvement Action

**Login as:** `iso.head@ogamierp.local`

1. Open a completed internal audit
2. Click **Add Finding**
3. Set finding type (`nonconformity` or `observation`), clause reference, description, severity
4. Save (status: `open`)
5. Click **Add Improvement Action** → set type (`corrective/preventive`), due date, assign to a user
6. Later — click **Complete Action**
7. **Verify:** Finding status updates; NCR cross-reference if raised

---

## Module 16 — Role-Specific Dashboard Verifications

### 16.1 Executive Dashboard

**Login as:** `chairman@ogamierp.local`

- Verify KPIs: headcount changes, attrition rate, avg tenure
- Check revenue/expense trend charts
- Verify department cost allocation

### 16.2 VP Dashboard

**Login as:** `vp@ogamierp.local`

- Verify `pending_approvals.total` reflects pending loans + PRs + MRQs from steps above
- Verify `financial_summary` shows correct payroll and open production order counts
- Verify `recent_approvals` list is populated

### 16.3 Officer Dashboard

**Login as:** `ga.officer@ogamierp.local`

- Verify `accounting` section: pending vendor invoices, JEs to post
- Verify `procurement` section: pending PR reviews, open POs
- Verify `delivery` section: inbound/outbound draft counts
- Verify `payroll` section: runs pending accounting approval

### 16.4 Manager Dashboard

**Login as:** `plant.manager@ogamierp.local`

- Verify headcount, pending leave requests, pending OT requests for department

### 16.5 Head Dashboard

**Login as:** `warehouse.head@ogamierp.local`

- Verify team attendance summary, pending loan notes, pending PR notes

### 16.6 Staff Dashboard

**Login as:** `hr.staff@ogamierp.local`

- Verify self-service widgets: leave balance, upcoming payslip, active loans

---

## Module 17 — Government Reports

**Login as:** `hr.manager@ogamierp.local`

After publishing at least one payroll run:

1. Navigate to **Reports → BIR**
   - **BIR 2316** — Annual income tax certificate per employee
   - **BIR Alphalist** — Quarterly alphalist of employees
   - **BIR 1601-C** — Monthly withholding tax remittance return
2. Navigate to **Reports → SSS** — SSS SBR-2 contribution schedule
3. Navigate to **Reports → PhilHealth** — RF-1 remittance form
4. Navigate to **Reports → Pag-IBIG** — MP2 / monthly contribution

**Verify:** Reports generate correctly with employee data; can be exported.

---

## Module 18 — Admin & System

### 18.1 User Management

**Login as:** `admin@ogamierp.local`

1. Navigate to **Admin → Users**
2. Create a new user, assign role (`staff`)
3. Lock / unlock a user account
4. **Verify:** User can or cannot log in based on lock status

### 18.2 Role & Permissions

1. Navigate to **Admin → Roles** — view all 7 roles and their permissions

### 18.3 System Settings

**Login as:** `admin@ogamierp.local`

1. Navigate to **Admin → Settings**
2. View/edit settings by group (e.g., `payroll`, `hr`)

### 18.4 Audit Log

**Login as:** `chairman@ogamierp.local` (has `system.view_audit_log`)

1. Navigate to **Admin → Audit Logs**
2. Filter by entity type, date range
3. **Verify:** All create/update/delete operations from testing are logged

---

## Common Test Scenarios / Edge Cases

| Scenario | Expected Behaviour |
|----------|-------------------|
| Same user submits and approves leave | System blocks — SoD enforced |
| Same user creates and activates employee | System blocks — SoD-001 |
| Same user submits and HR-approves payroll | System blocks — SoD-005/006 |
| Same user posts a JE they created | System blocks — SoD-008 |
| Employee with no attendance in pay period | Included with 0 basic pay (or excluded per setting) |
| Loan repayment deducted from net pay | Verify `payroll_details.loan_deductions_centavos` > 0 |
| VL balance = 0, staff files VL | System rejects (insufficient balance) |
| Staff tries to access HR employee list | 403 Forbidden |
| Admin tries to access payroll run | 403 Forbidden (admin has no business data access) |

---

## Test Completion Checklist

- [ ] Employee created, activated, documents uploaded
- [ ] OT request (staff/head/officer → manager) — 3-step chain completed
- [ ] OT request (manager → executive) — 2-step chain completed
- [ ] Leave request (staff/head/officer → manager) — 3-step chain completed
- [ ] Leave request (manager → executive) — 2-step chain completed
- [ ] Loan application — full 5-step chain completed
- [ ] Payroll run — full 8-step pipeline completed
- [ ] Payslip downloaded by staff
- [ ] JE created, submitted, posted (SoD)
- [ ] Vendor invoice submitted and approved (SoD)
- [ ] Payment recorded on AP invoice
- [ ] Customer invoice created and paid
- [ ] PR full 5-step chain + converted to PO
- [ ] GR confirmed against PO
- [ ] Production order created → released → in_progress → completed
- [ ] QC inspection → failed → NCR raised → CAPA issued → closed
- [ ] Mold shots logged; criticality flag triggered
- [ ] Delivery receipt (inbound) confirmed
- [ ] Shipment tracked to `delivered`
- [ ] ISO document approved
- [ ] Dashboard KPIs verified for VP and Officer roles
- [ ] Government reports generated
- [ ] Audit log records all operations
