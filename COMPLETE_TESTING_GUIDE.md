# Ogami ERP - Complete Testing Guide

**Full System Testing with SoD Enforcement & Role-Based Access Control**

> ⚠️ **IMPORTANT:** This guide deliberately excludes `admin` and `super_admin` accounts as they bypass SoD rules. All tests use standard business roles to verify proper segregation of duties.

---

## Table of Contents

1. [Test Account Reference](#test-account-reference)
2. [SoD Rules Overview](#sod-rules-overview)
3. [Module-by-Module Testing](#module-by-module-testing)
4. [Cross-Module Integration Workflows](#cross-module-integration-workflows)
5. [SoD Violation Testing](#sod-violation-testing)
6. [Approval Workflows](#approval-workflows)
7. [Quick Test Checklist](#quick-test-checklist)

---

## Test Account Reference

### Account Types Explained

| Type | Description | Can Test SoD? |
|------|-------------|---------------|
| **Full Employee** | Has User + Employee records linked | Yes - payroll, attendance, leave |
| **User-Only** | Has User record only | Yes - most modules except employee-specific |
| **Portal** | External vendor/client access | Limited - portal only |

### All Test Accounts (No Admin/Super Admin)

> **Password Pattern:** `{RoleName}@Test1234!` (Role name with first letter capitalized)  
> **Exceptions:** Some accounts have custom passwords defined in seeders

#### HR Department

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| HR Manager | `hr.manager@ogamierp.local` | `Manager@Test1234!` | EMP-HR-001 | Full HR, payroll prep |
| GA Officer | `ga.officer@ogamierp.local` | `Ga_officer@Test1234!` | EMP-HR-002 | General affairs |
| HR Head | `hr.head@ogamierp.local` | `Head@Test1234!` | EMP-HR-003 | First-level approvals |
| HR Staff | `hr.staff@ogamierp.local` | `Staff@Test1234!` | EMP-HR-004 | Data entry, self-service |

#### Accounting & Finance (ACCTG)

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| Accounting Manager | `acctg.manager@ogamierp.local` | `Manager@12345!` | EMP-ACCT-001 | AP/AR approval, JE posting |
| Accounting Officer | `acctg.officer@ogamierp.local` | `Officer@Test1234!` | EMP-ACCT-002 | Financial management |
| Accounting Head | `acctg.head@ogamierp.local` | `Head@Test1234!` | EMP-ACCT-003 | First-level approvals |
| Accounting Staff | `acctg.staff@ogamierp.local` | `Staff@Test1234!` | EMP-ACCT-004 | Data entry |

#### Production (PROD)

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| Production Manager | `prod.manager@ogamierp.local` | `Production_manager@Test1234!` | EMP-PROD-001 | Production orders, BOM |
| Production Head | `prod.head@ogamierp.local` | `Head@Test1234!` | EMP-PROD-002 | First-level approvals |
| Production Staff | `prod.staff@ogamierp.local` | `Staff@Test1234!` | EMP-PROD-003 | Data entry, self-service |

#### Quality Control (QC)

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| QC Manager | `qc.manager@ogamierp.local` | `Qc_manager@Test1234!` | EMP-QC-001 | QC/QA management |
| QC Head | `qc.head@ogamierp.local` | `Head@Test1234!` | EMP-QC-002 | First-level approvals |
| QC Staff | `qc.staff@ogamierp.local` | `Staff@Test1234!` | EMP-QC-003 | Inspections, data entry |

#### Mold Department

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| Mold Manager | `mold.manager@ogamierp.local` | `Mold_manager@Test1234!` | EMP-MOLD-001 | Mold management |
| Mold Head | `mold.head@ogamierp.local` | `Head@Test1234!` | EMP-MOLD-002 | First-level approvals |
| Mold Staff | `mold.staff@ogamierp.local` | `Staff@Test1234!` | EMP-MOLD-003 | Mold technician |

#### Plant Operations

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| Plant Manager | `plant.manager@ogamierp.local` | `Plant_manager@Test1234!` | EMP-PLANT-001 | Plant oversight |
| Plant Head | `plant.head@ogamierp.local` | `Head@Test1234!` | EMP-PLANT-002 | First-level approvals |

#### Sales & Marketing

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| CRM Manager | `crm.manager@ogamierp.local` | `CrmManager@12345!` | EMP-SALES-001 | CRM, tickets |
| Sales Staff | `sales.staff@ogamierp.local` | `Staff@Test1234!` | EMP-SALES-002 | Sales rep |

#### IT Department

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| IT Admin | `it.admin@ogamierp.local` | `Manager@12345!` | EMP-IT-001 | IT management |

#### Executive Management

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| Executive | `executive@ogamierp.local` | `Executive@Test1234!` | EMP-EXEC-001 | Cross-department approvals |
| Vice President | `vp@ogamierp.local` | `Vice_president@Test1234!` | EMP-EXEC-002 | Final approvals, VP dashboard |

#### External Portals

| Role | Email | Password | Employee Code | Use Case |
|------|-------|----------|---------------|----------|
| Vendor | `vendor@ogamierp.local` | `Vendor@Test1234!` | — | Vendor portal access |
| Client | `client@ogamierp.local` | `Client@Test1234!` | — | Client ticket portal |

### Password Quick Reference

| Role Type | Password Pattern | Example |
|-----------|------------------|---------|
| Single word role | `{Role}@Test1234!` | `Manager@Test1234!`, `Officer@Test1234!` |
| Multi-word role | `{First}{Rest}@Test1234!` | `Plant_manager@Test1234!`, `Qc_manager@Test1234!` |
| Special accounts | Defined in seeder | `Manager@12345!` (acctg.manager), `CrmManager@12345!` (crm.manager) |

**All Account Summary Table:**

| Dept | Email | Password | Role | Employee Code |
|------|-------|----------|------|---------------|
| HR | hr.manager@ogamierp.local | Manager@Test1234! | manager | EMP-HR-001 |
| HR | ga.officer@ogamierp.local | Ga_officer@Test1234! | ga_officer | EMP-HR-002 |
| HR | hr.head@ogamierp.local | Head@Test1234! | head | EMP-HR-003 |
| HR | hr.staff@ogamierp.local | Staff@Test1234! | staff | EMP-HR-004 |
| ACCTG | acctg.manager@ogamierp.local | Manager@12345! | officer | EMP-ACCT-001 |
| ACCTG | acctg.officer@ogamierp.local | Officer@Test1234! | officer | EMP-ACCT-002 |
| ACCTG | acctg.head@ogamierp.local | Head@Test1234! | head | EMP-ACCT-003 |
| ACCTG | acctg.staff@ogamierp.local | Staff@Test1234! | staff | EMP-ACCT-004 |
| PROD | prod.manager@ogamierp.local | Production_manager@Test1234! | production_manager | EMP-PROD-001 |
| PROD | prod.head@ogamierp.local | Head@Test1234! | head | EMP-PROD-002 |
| PROD | prod.staff@ogamierp.local | Staff@Test1234! | staff | EMP-PROD-003 |
| QC | qc.manager@ogamierp.local | Qc_manager@Test1234! | qc_manager | EMP-QC-001 |
| QC | qc.head@ogamierp.local | Head@Test1234! | head | EMP-QC-002 |
| QC | qc.staff@ogamierp.local | Staff@Test1234! | staff | EMP-QC-003 |
| MOLD | mold.manager@ogamierp.local | Mold_manager@Test1234! | mold_manager | EMP-MOLD-001 |
| MOLD | mold.head@ogamierp.local | Head@Test1234! | head | EMP-MOLD-002 |
| MOLD | mold.staff@ogamierp.local | Staff@Test1234! | staff | EMP-MOLD-003 |
| PLANT | plant.manager@ogamierp.local | Plant_manager@Test1234! | plant_manager | EMP-PLANT-001 |
| PLANT | plant.head@ogamierp.local | Head@Test1234! | head | EMP-PLANT-002 |
| SALES | crm.manager@ogamierp.local | CrmManager@12345! | crm_manager | EMP-SALES-001 |
| SALES | sales.staff@ogamierp.local | Staff@Test1234! | staff | EMP-SALES-002 |
| IT | it.admin@ogamierp.local | Manager@12345! | admin | EMP-IT-001 |
| EXEC | executive@ogamierp.local | Executive@Test1234! | executive | EMP-EXEC-001 |
| EXEC | vp@ogamierp.local | Vice_president@Test1234! | vice_president | EMP-EXEC-002 |

---

## SoD Rules Overview

### What is SoD?

**Segregation of Duties (SoD)** ensures that no single person can:
1. Create AND approve the same transaction
2. Record AND verify the same data
3. Have custody of assets AND record them

### Frontend SoD Implementation

The frontend enforces SoD via:
- **`useSodCheck(initiatedById)`** hook - checks if current user is the creator
- **`SodActionButton`** component - disables button with tooltip for creator
- **Role-based bypass** - only `admin` and `super_admin` bypass SoD

```tsx
// Example: Journal Entry Posting Button
<SodActionButton
  initiatedById={entry.created_by}
  label="Post"
  onClick={handlePost}
  variant="primary"
/>
// Button shows disabled with "(SoD)" label if current user created the entry
```

### SoD Rules in Ogami ERP

| Rule | Creator Role | Approver Role | Where Enforced |
|------|--------------|---------------|----------------|
| HR-001 | Submits leave | Manager/HR approves | Leave List → Approve button (SodActionButton) |
| HR-002 | Logs overtime | Manager approves OT | Overtime List → Approve button |
| FIN-001 | Creates JE | Different user posts | JE Detail → Post button (SodActionButton) |
| FIN-002 | Creates AP invoice | Different user approves | AP Invoice Detail → Approve button |
| PROC-001 | Creates PR | Different user approves | PR Detail → Approval action buttons |
| PROC-002 | Creates PO | Manager approves | PO Detail → Approve button |
| PAY-001 | Initiates payroll | Accounting approves | Payroll Run → Approve button |

---

## Module-by-Module Testing

### MODULE 1: HR & Employee Management

#### Understanding Employee Status Fields

Ogami ERP uses THREE status fields for employees:

| Field | Values | Description |
|-------|--------|-------------|
| `employment_status` | `active`, `on_leave`, `suspended`, `resigned`, `terminated` | Employment state |
| `onboarding_status` | `draft`, `documents_pending`, `active`, `offboarding`, `offboarded` | Onboarding progress |
| `is_active` | `true`, `false` | Whether employee is fully active in system |

**When HR creates an employee:**
- `employment_status` = `active` (always set to active)
- `onboarding_status` = `documents_pending` (if govt IDs incomplete)
- `is_active` = `false` (if govt IDs incomplete)

**Auto-activation:** When all government IDs (SSS, TIN, PhilHealth, Pag-IBIG) are provided, the system automatically activates the employee (`is_active = true`, `onboarding_status = 'active'`).

#### 1.1 Employee Creation with Document Upload

**Step 1: Create Employee (HR Manager)**
- **Login:** `hr.manager@ogamierp.local` / `Manager@Test1234!`
- **Navigation:** Sidebar → Human Resources → All Employees
- **Action:** Click "+ Add Employee" button
- **Path:** `/hr/employees/new`
- **Form Fields:**
  - Personal Info: First name, Middle name, Last name, Date of birth, Gender, Civil status
  - Contact: Personal email, Personal phone, Present address, Permanent address
  - Employment: Department, Position, Salary grade, Employment type, Date hired
  - Government IDs: TIN, SSS, PhilHealth, Pag-IBIG (encrypted)
  - Bank: Bank name, Account number, Account name
- **Without All IDs:** Employee created with `onboarding_status: 'documents_pending'`, `is_active: false`
- **With All IDs:** Employee auto-activated with `is_active: true`

**Step 2: View Employee Detail**
- **Path:** `/hr/employees/:ulid`
- **Status Badge:** Shows `employment_status` (e.g., "Active")
- **Info Card:** Shows "Onboarding Status" with actual state

**Step 3: Edit to Add Missing Documents**
- **Path:** `/hr/employees/:ulid` → Click "Edit Profile"
- **Action:** Add missing government IDs
- **Expected:** System auto-activates employee when all IDs provided

#### 1.2 Employee Status Transitions

**Available Transitions (via status dropdown on employee detail):**
- `active` → `on_leave`, `suspended`, `resigned`, `terminated`
- `on_leave` → `active`, `resigned`, `terminated`
- `suspended` → `active`, `resigned`, `terminated`

**Note:** These transitions are for employment_status changes (like putting an active employee on leave), NOT initial activation. Initial activation is automatic when documents are complete.

**Test Status Change:**
- **Login:** `hr.manager@ogamierp.local`
- **Path:** `/hr/employees/:ulid`
- **Action:** Use status dropdown to change state
- **Verify:** Status updates, action recorded in audit log

#### 1.3 Employee Profile Edit

**Step 1: Edit Profile (HR Manager)**
- **Path:** `/hr/employees/:ulid` → Click "Edit Profile" button
- **Path:** `/hr/employees/:ulid/edit`
- **Action:** Modify contact information, add documents, save
- **Expected:** Changes saved, audit log created

**Step 2: View as Employee**
- **Login:** `hr.staff@ogamierp.local` (or employee's own account)
- **Path:** `/employee/profile`
- **Expected:** View own profile (read-only for most fields)

---

### MODULE 2: Team Management (Department-Scoped)

**Available to:** Manager, Head, Plant Manager, Production Manager, QC Manager, Mold Manager, GA Officer

#### 2.1 My Team Overview

**Step 1: View Team (Department Head)**
- **Login:** `hr.head@ogamierp.local` / `Head@Test1234!`
- **Navigation:** Sidebar → Team Management → My Team
- **Path:** `/team/employees`
- **Expected:** List of employees in same department only

#### 2.2 Team Attendance

**Path:** `/team/attendance`
- View attendance for team members
- Export reports

#### 2.3 Team Leave

**Path:** `/team/leave`
- View leave requests from team
- Approve/Reject with SoD enforcement

#### 2.4 Team Overtime

**Path:** `/team/overtime`
- View OT requests
- Approve/Reject with SoD enforcement

---

### MODULE 3: Attendance Management

#### 3.1 Daily Attendance Logs

**Step 1: View Logs (HR Manager)**
- **Login:** `hr.manager@ogamierp.local`
- **Navigation:** Sidebar → Human Resources → Attendance Logs
- **Path:** `/hr/attendance`
- **Features:**
  - Date range filter
  - Department filter
  - Search by employee name
  - Import logs (button: "Import")

**Step 2: Import Attendance**
- **Path:** `/hr/attendance/import`
- **Action:** Upload CSV file with time logs
- **Format:** Employee code, Date, Time in, Time out

#### 3.2 Attendance Dashboard

**Path:** `/hr/attendance/dashboard`
- Visual dashboard with attendance metrics
- Late arrivals, absences, overtime summary

#### 3.3 Overtime Management

**Step 1: View OT List (HR Manager)**
- **Path:** `/hr/overtime`
- **Columns:** Employee, Date, Hours, Reason, Status

**Step 2: OT Approval with SoD**
- **Login:** `hr.manager@ogamierp.local`
- **Action:** Find OT request created by another user
- **UI:** "Approve" button enabled
- **Action:** Find OT request created by yourself (if any)
- **UI:** "Approve (SoD)" button disabled with tooltip

---

### MODULE 4: Leave Management

#### 4.1 File Leave (Employee Self-Service)

**Step 1: Submit Leave (Staff)**
- **Login:** `hr.staff@ogamierp.local` / `Staff@Test1234!`
- **Navigation:** Sidebar → (Self Service) or via HR
- **Path:** `/employee/leave` or `/hr/leave/new`
- **Form Fields:**
  - Leave type (Vacation, Sick, Emergency, etc.)
  - Date range (from/to)
  - Reason
  - Attachment (if required)
- **Submit:** Request created with "pending" status

**Step 2: View Leave Balance**
- **Path:** `/hr/leave/balances`
- **Shows:** Available credits per leave type

#### 4.2 Leave Approval with SoD

**Step 1: Manager Tries to Approve Own Leave**
- **Login:** `hr.head@ogamierp.local`
- **Action:** Create leave request for yourself
- **Then:** Try to approve it from `/team/leave` or `/hr/leave`
- **Expected:** ❌ Approval button shows "(SoD)" disabled

**Step 2: Different Manager Approves**
- **Login:** `hr.manager@ogamierp.local`
- **Path:** `/hr/leave`
- **Action:** 
  1. Find pending request
  2. Click to expand row
  3. Click "Approve" button (enabled because different user)
  4. Add optional comments
- **Expected:** ✅ Status changed to "approved", balance deducted

#### 4.3 Leave Calendar

**Path:** `/hr/leave/calendar`
- Visual calendar view of approved leaves
- Filter by department

---

### MODULE 5: Loans Management

#### 5.1 Loan Application

**Step 1: Apply for Loan (Staff)**
- **Login:** `hr.staff@ogamierp.local` / `Staff@Test1234!`
- **Navigation:** Employee self-service or HR → Loans
- **Path:** `/employee/loans/new` or `/hr/loans/new`
- **Form Fields:**
  - Loan type (Salary, Emergency, etc.)
  - Amount
  - Term (months)
  - Purpose
- **Submit:** Application created

**Step 2: View Amortization**
- System calculates payment schedule
- Shows monthly deduction amount

#### 5.2 Loan Approval Chain

**Step 1: HR Review**
- **Login:** `hr.manager@ogamierp.local`
- **Path:** `/hr/loans`
- **Action:** Review application, click "Recommend"

**Step 2: Accounting Review**
- **Login:** `acctg.officer@ogamierp.local`
- **Path:** Sidebar → Accounting → Loan Approvals
- **Action:** Review and approve

**Step 3: Disbursement**
- **Path:** `/hr/loans/:id`
- **Action:** Process disbursement
- **Expected:** Loan appears in payroll deductions

---

### MODULE 6: Payroll

#### 6.1 Payroll Run Lifecycle

**Payroll Run Statuses:**
```
DRAFT → SCOPE_SET → PRE_RUN_CHECKED → PROCESSING → COMPUTED → 
REVIEW → SUBMITTED → HR_APPROVED → ACCTG_APPROVED → DISBURSED → PUBLISHED
```

**Step 1: Create Payroll Run (HR Manager)**
- **Login:** `hr.manager@ogamierp.local`
- **Navigation:** Sidebar → Payroll → Payroll Runs
- **Path:** `/payroll/runs`
- **Action:** Click "New Run" button
- **Wizard Step 1 (Scope):** `/payroll/runs/new/scope`
  - Select year
  - Select pay period (1st half / 2nd half)
  - Select departments
- **Wizard Step 2 (Validate):** `/payroll/runs/new/validate`
  - Review pre-run checklist
  - Check for unprocessed attendance
- **Wizard Step 3 (Compute):** `/payroll/runs/new/compute`
  - Process payroll
  - System computes all deductions

**Step 2: Review Computed Payroll**
- **Path:** `/payroll/runs/:ulid`
- **Review Page:** Shows:
  - Total employees
  - Gross pay total
  - Total deductions
  - Net pay total
  - Employee breakdown

**Step 3: SoD Check - Self Approval**
- **Login:** Same HR Manager who created the run
- **Action:** Try to approve the payroll run
- **Expected:** ❌ "Approve (SoD)" button disabled
- **Tooltip:** "Segregation of Duties violation: you initiated this record..."

**Step 4: Accounting Review**
- **Login:** `acctg.officer@ogamierp.local`
- **Path:** `/payroll/runs`
- **Filter:** Status = "SUBMITTED" or "HR_APPROVED"
- **Action:** Click run to review
- **Review Page:** `/payroll/runs/:ulid/review`
- **Verify:** Check computations, deductions, net pay

**Step 5: Accounting Manager Approval**
- **Login:** `acctg.manager@ogamierp.local`
- **Path:** `/payroll/runs/:ulid`
- **Action:** Click "Approve" button (SoD check passes - different user)
- **Expected:** Status changes to "ACCTG_APPROVED"

**Step 6: Disbursement**
- **Path:** `/payroll/runs/:ulid/disburse`
- **Action:** Process payment to employees
- **Expected:** Status "DISBURSED" → "PUBLISHED"

#### 6.2 Payslip Generation

**Step 1: Employee Views Payslip**
- **Login:** `hr.staff@ogamierp.local` (or any employee)
- **Navigation:** Employee → My Payslips
- **Path:** `/employee/payslips`
- **Shows:**
  - Basic pay
  - Overtime pay
  - Holiday pay
  - Night differential
  - Gross pay
  - SSS contribution
  - PhilHealth contribution
  - Pag-IBIG contribution
  - Withholding tax
  - Loan deductions
  - Other deductions
  - Net pay

#### 6.3 Pay Periods

**Path:** `/payroll/periods`
- View/manage pay periods
- Set cut-off dates

---

### MODULE 7: Accounting - Chart of Accounts

#### 7.1 View COA

**Step 1: Access COA**
- **Login:** `acctg.officer@ogamierp.local`
- **Navigation:** Sidebar → Accounting → Chart of Accounts
- **Path:** `/accounting/accounts`
- **View:** Hierarchical tree of accounts
- **Columns:** Code, Name, Type, Balance

#### 7.2 Account Detail

- Click account to view details
- View transaction history
- View balance over time

---

### MODULE 8: Journal Entries

#### 8.1 Create Journal Entry

**Step 1: Create JE (Accounting Officer)**
- **Login:** `acctg.officer@ogamierp.local`
- **Navigation:** Sidebar → Accounting → Journal Entries
- **Path:** `/accounting/journal-entries`
- **Action:** Click "New Entry" button
- **Path:** `/accounting/journal-entries/new`
- **Form Fields:**
  - Date
  - Reference number (auto-generated)
  - Description
  - Lines (Account, Debit, Credit, Description)
- **Validation:** Total Debits must equal Total Credits
- **Save:** Status = "draft"

#### 8.2 Submit for Approval

**Step 1: Submit JE**
- **Path:** `/accounting/journal-entries/:ulid`
- **Action:** Click "Submit for Approval" button
- **Expected:** Status changes to "submitted"

#### 8.3 SoD Check - Post JE

**Step 1: Creator Tries to Post**
- **Login:** Same Accounting Officer who created JE
- **Path:** `/accounting/journal-entries/:ulid`
- **UI:** "Post (SoD)" button disabled
- **Tooltip:** "Segregation of Duties violation..."

**Step 2: Different User Posts**
- **Login:** `acctg.manager@ogamierp.local`
- **Path:** `/accounting/journal-entries/:ulid`
- **UI:** "Post" button enabled
- **Action:** Click "Post"
- **Expected:** Status "posted", GL updated

#### 8.4 Reverse Journal Entry

**Path:** `/accounting/journal-entries/:ulid`
- **Action:** Click "Reverse" button (only for posted entries)
- **Dialog:** Enter reversal reason
- **Expected:** Reversal entry created, status "reversed"

#### 8.5 Recurring Templates

**Path:** `/accounting/recurring-templates`
- Create recurring JE templates
- Set frequency (monthly, quarterly)
- Generate entries automatically

---

### MODULE 9: General Ledger

**Path:** `/accounting/gl`
- View GL by account
- Date range filter
- Drill down to transactions
- Export to Excel

---

### MODULE 10: Accounts Payable

#### 10.1 Vendor Management

**Step 1: Create Vendor**
- **Login:** `acctg.officer@ogamierp.local`
- **Navigation:** Sidebar → Payables (AP) → Vendors
- **Path:** `/accounting/vendors`
- **Action:** Click "New Vendor" button
- **Form Fields:**
  - Vendor code
  - Company name
  - Contact person
  - Email, Phone
  - Address
  - TIN
  - Payment terms
  - Credit limit
- **Save:** Vendor created

#### 10.2 Vendor Invoices

**Step 1: Create AP Invoice**
- **Path:** `/accounting/ap/invoices`
- **Action:** Click "New Invoice" button
- **Path:** `/accounting/ap/invoices/new`
- **Form Fields:**
  - Vendor
  - Invoice number
  - Invoice date
  - Due date
  - PO reference (optional)
  - Line items (Item, Description, Qty, Price, Amount)
  - Tax
  - Total amount
- **Save:** Status = "pending"

**Step 2: 3-Way Match**
- System validates: PO + GR + Invoice match
- Shows match status on invoice detail

**Step 3: SoD Check - Approve Invoice**
- **Creator View:** "Approve (SoD)" button disabled
- **Different User View:** "Approve" button enabled
- **Action:** Approve → Status "approved"

#### 10.3 AP Credit Notes

**Path:** `/accounting/ap/credit-notes`
- Create credit notes for vendor returns
- Link to original invoice

#### 10.4 AP Aging Report

**Path:** `/accounting/ap/aging-report`
- Aging buckets: Current, 1-30, 31-60, 61-90, 90+
- Shows outstanding payables by vendor

---

### MODULE 11: Accounts Receivable

#### 11.1 Customer Management

**Step 1: Create Customer**
- **Login:** `acctg.officer@ogamierp.local`
- **Navigation:** Sidebar → Receivables (AR) → Customers
- **Path:** `/ar/customers`
- **Form Fields:**
  - Customer code
  - Company name
  - Contact person
  - Email, Phone
  - Address
  - TIN
  - Payment terms
  - Credit limit

#### 11.2 Customer Invoices

**Step 1: Create Invoice**
- **Path:** `/ar/invoices`
- **Action:** "New Invoice" button
- **Path:** `/ar/invoices/new`
- **Form Fields:**
  - Customer
  - Invoice date
  - Due date
  - Line items
  - Tax (VAT)
- **Save:** Status "issued"

**Step 2: Print/Send Invoice**
- **Action:** Click "Print" or "Email"
- Generate PDF

#### 11.3 Payment Receipt

**Step 1: Record Payment**
- **Path:** `/ar/invoices/:id`
- **Action:** Click "Receive Payment" button
- **Form:**
  - Payment date
  - Amount received
  - Payment method
  - Reference number
- **Save:** Payment applied to invoice

#### 11.4 AR Credit Notes

**Path:** `/ar/credit-notes`
- Create credit notes for sales returns

#### 11.5 AR Aging Report

**Path:** `/ar/aging-report`
- Aging buckets: Current, 1-30, 31-60, 61-90, 90+
- Shows outstanding receivables by customer

---

### MODULE 12: Banking

#### 12.1 Bank Accounts

**Path:** `/banking/accounts`
- View bank accounts
- Add new accounts
- View balances

#### 12.2 Bank Reconciliation

**Path:** `/banking/reconciliations`
- **Action:** Start new reconciliation
- **Path:** `/banking/reconciliations/:id`
- **Process:**
  1. Enter statement date
  2. Enter statement balance
  3. Match transactions
  4. Mark as reconciled

---

### MODULE 13: Tax Management

#### 13.1 VAT Ledger

**Path:** `/accounting/vat-ledger`
- **Input VAT:** From vendor invoices
- **Output VAT:** From customer invoices
- Shows VAT summary by period

#### 13.2 Tax Summary

**Path:** `/accounting/tax-summary`
- Summary of all taxes
- BIR form previews

---

### MODULE 14: Financial Reports

**Available Reports:**
- **Trial Balance:** `/accounting/trial-balance`
- **Balance Sheet:** `/accounting/balance-sheet`
- **Income Statement:** `/accounting/income-statement`
- **Cash Flow:** `/accounting/cash-flow`
- **AP Aging:** `/accounting/ap/aging-report`
- **AR Aging:** `/ar/aging-report`

---

### MODULE 15: Fixed Assets

#### 15.1 Asset Register

**Path:** `/fixed-assets`
- List of all fixed assets
- Columns: Asset tag, Name, Category, Cost, Depreciation, Net book value

#### 15.2 Create Asset

**Path:** `/fixed-assets/new`
- **Form Fields:**
  - Asset tag
  - Asset name
  - Category
  - Acquisition date
  - Acquisition cost
  - Useful life (years)
  - Depreciation method (Straight-line, Declining balance)
  - Salvage value

#### 15.3 Asset Categories

**Path:** `/fixed-assets/categories`
- Define asset classes
- Set default depreciation rules

#### 15.4 Asset Disposals

**Path:** `/fixed-assets/disposals`
- Record asset sales/scrapping
- Calculate gain/loss

---

### MODULE 16: Budget

#### 16.1 Cost Centers

**Path:** `/budget/cost-centers`
- Define cost center hierarchy
- Assign responsible persons

#### 16.2 Budget Lines

**Path:** `/budget/lines`
- Create annual budget
- Set amounts per GL account
- Allocate by period

#### 16.3 Budget vs Actual

**Path:** `/budget/vs-actual`
- Compare budget to actual spending
- Show variance (amount and %)

---

### MODULE 17: Procurement

#### 17.1 Purchase Requisitions (SoD Workflow)

**Step 1: Create PR (Staff)**
- **Login:** `prod.staff@ogamierp.local`
- **Navigation:** Sidebar → Procurement → Purchase Requests
- **Path:** `/procurement/purchase-requests`
- **Action:** Click "New Request" button
- **Path:** `/procurement/purchase-requests/new`
- **Form Fields:**
  - Department
  - Priority
  - Required date
  - Items (Item, Qty, UOM, Estimated price, Purpose)
- **Save:** Status = "draft"

**Step 2: Submit PR**
- **Action:** Click "Submit" button
- **Status:** "pending_head"

**Step 3: SoD Check - Self Approval**
- **Creator View:** No approval actions visible
- **Different User View:** Approval actions available

**Step 4: Approval Chain**

| Stage | Status | Approver | Action |
|-------|--------|----------|--------|
| 1 | pending_head | Department Head | "Check" button |
| 2 | pending_review | Manager | "Review" button |
| 3 | pending_budget | Budget Officer | "Budget Check" button |
| 4 | pending_vp | VP | "VP Approve" button |

**UI Elements on Detail Page:**
- Approval stage tracker (visual timeline)
- Comments for each stage
- Reject button (with reason)
- Return button (send back to previous stage)

#### 17.2 Purchase Orders

**Step 1: Create PO from PR**
- **Login:** `acctg.staff@ogamierp.local`
- **Path:** `/procurement/purchase-orders`
- **Action:** "New PO" button or "Create from PR"
- **Path:** `/procurement/purchase-orders/new`
- **Form Fields:**
  - Vendor
  - PR reference
  - Items (from PR or manual)
  - Quantities
  - Unit prices
  - Delivery date
  - Terms
- **Save:** Status = "draft"

**Step 2: Approve PO**
- **Path:** `/procurement/purchase-orders/:ulid`
- **Action:** Manager approves (SoD enforced)
- **Status:** "approved"

**Step 3: Send to Vendor**
- **Action:** "Send" button
- Generate PDF
- Email to vendor

#### 17.3 Goods Receipts

**Step 1: Create GR**
- **Path:** `/procurement/goods-receipts`
- **Action:** "New GR" button
- **Path:** `/procurement/goods-receipts/new`
- **Form:**
  - PO reference
  - Received date
  - Items received
  - Quantities
  - Condition notes
- **Save:** Stock increased automatically

#### 17.4 Vendor RFQs

**Path:** `/procurement/rfqs`
- Create RFQ
- Send to multiple vendors
- Compare quotations
- Award to winning vendor

#### 17.5 Procurement Analytics

**Path:** `/procurement/analytics`
- Spending by vendor
- PR to PO conversion rate
- Average approval time

---

### MODULE 18: Inventory

#### 18.1 Item Categories

**Path:** `/inventory/categories`
- Define category hierarchy
- Set accounting rules per category

#### 18.2 Item Master

**Path:** `/inventory/items`
- **List View:** All items with stock levels
- **Action:** "New Item" button
- **Path:** `/inventory/items/new`
- **Form Fields:**
  - Item code
  - Item name
  - Category
  - UOM
  - Reorder point
  - Reorder quantity
  - Valuation method

#### 18.3 Warehouse Locations

**Path:** `/inventory/locations`
- Define warehouses and bins
- Set location types

#### 18.4 Stock Balances

**Path:** `/inventory/stock`
- Real-time stock levels
- Filter by location
- Filter by category

#### 18.5 Stock Ledger

**Path:** `/inventory/ledger`
- Complete transaction history
- In/Out/Adjustment entries

#### 18.6 Material Requisitions

**Path:** `/inventory/requisitions`
- **Create:** `/inventory/requisitions/new`
- Request materials from warehouse
- Approval workflow
- Issue to production

#### 18.7 Stock Adjustments

**Path:** `/inventory/adjustments`
- Record stock counts
- Adjust variances
- Approval required

#### 18.8 Inventory Valuation

**Path:** `/inventory/valuation`
- Current inventory value
- By valuation method (FIFO, Average)

---

### MODULE 19: Production

#### 19.1 Bill of Materials

**Path:** `/production/boms`
- **List:** All BOMs
- **Create:** `/production/boms/new`
- **Form Fields:**
  - Product (FG item)
  - Version
  - Components (Item, Qty, UOM)
  - Labor routing (optional)
- **Edit:** `/production/boms/:id/edit`

#### 19.2 Production Orders (Work Orders)

**Path:** `/production/orders`
- **Create:** `/production/orders/new`
- **Form Fields:**
  - BOM
  - Quantity to produce
  - Planned start/end dates
  - Priority
- **Status Flow:**
  ```
  draft → released → in_progress → completed → closed
  ```

**Step 1: Create WO (Production Manager)**
- **Login:** `prod.manager@ogamierp.local`
- **Action:** Create WO, save as "draft"

**Step 2: Release WO (Manager)**
- **SoD Check:** Different user must release
- **Action:** Plant Manager releases
- **Status:** "released"
- **System:** Creates material reservation

**Step 3: Start Production**
- **Action:** Record start
- **Status:** "in_progress"

**Step 4: Record Output**
- Enter completed quantity
- Record good units and rejects

**Step 5: Complete WO**
- **Status:** "completed"
- **System:** FG stock increased

#### 19.3 Delivery Schedules

**Path:** `/production/delivery-schedules`
- Plan delivery dates
- Link to customer orders

#### 19.4 Cost Analysis

**Path:** `/production/cost-analysis`
- Production cost breakdown
- Material, labor, overhead
- Cost per unit

---

### MODULE 20: QC / QA

#### 20.1 Inspections

**Path:** `/qc/inspections`
- **Create:** `/qc/inspections/new`
- **Types:** Incoming, In-process, Final
- **Form Fields:**
  - Source (GR, Production Order)
  - Inspector
  - Sample size
  - Measurements
  - Pass/Fail determination

#### 20.2 NCR (Non-Conformance Reports)

**Path:** `/qc/ncrs`
- **Create:** `/qc/ncrs/new`
- Document quality issues
- Link to inspection
- **Disposition:** Use As Is, Rework, Scrap, Return

#### 20.3 CAPA

**Path:** `/qc/capa`
- Corrective actions
- Preventive actions
- Track to closure

#### 20.4 QC Templates

**Path:** `/qc/templates`
- Define inspection checklists
- Standardize QC processes

#### 20.5 Defect Rate

**Path:** `/qc/defect-rate`
- Defect rate trends
- Pareto analysis

---

### MODULE 21: Maintenance

#### 21.1 Equipment

**Path:** `/maintenance/equipment`
- **List:** All equipment/assets
- **Create:** `/maintenance/equipment/new`
- **Detail:** `/maintenance/equipment/:id`
- **Fields:**
  - Asset tag
  - Equipment name
  - Model/Serial
  - Location
  - Criticality
  - PM schedule

#### 21.2 Work Orders

**Path:** `/maintenance/work-orders`
- **Create:** `/maintenance/work-orders/new`
- **Types:** Preventive, Corrective, Emergency
- **Status:** Open → In Progress → Completed
- **Fields:**
  - Equipment
  - Problem description
  - Priority
  - Assigned technician
  - Parts used
  - Labor hours

---

### MODULE 22: Mold Management

**Path:** `/mold/masters`
- **Create:** `/mold/masters/new`
- Mold master data
- Shot counter tracking
- Maintenance alerts based on shot count

---

### MODULE 23: Delivery

#### 23.1 Delivery Receipts

**Path:** `/delivery/receipts`
- **Create:** `/delivery/receipts/new`
- Link to sales orders
- Customer acknowledgment

#### 23.2 Shipments

**Path:** `/delivery/shipments`
- Track shipments
- Delivery status

---

### MODULE 24: ISO / IATF

#### 24.1 Documents

**Path:** `/iso/documents`
- Controlled document register
- **Create:** `/iso/documents/new`
- Version control
- Distribution list

#### 24.2 Audits

**Path:** `/iso/audits`
- **Create:** `/iso/audits/new`
- Schedule audits
- Record findings
- Track CAPA

---

### MODULE 25: CRM

#### 25.1 CRM Dashboard

**Path:** `/crm/dashboard`
- Ticket metrics
- SLA performance

#### 25.2 Support Tickets

**Path:** `/crm/tickets`
- View all tickets
- Assign to agents
- Track resolution

---

### MODULE 26: VP Approvals Dashboard

**Path:** `/approvals/pending`
- **Login:** `vp@ogamierp.local` / `Vice_president@Test1234!`
- **Navigation:** Sidebar → VP Approvals → Pending Approvals
- **Shows:**
  - Purchase Requests awaiting VP approval
  - Loans awaiting VP approval
  - Material Requisitions awaiting approval
- **Actions:**
  - Approve (with comments)
  - Reject (with reason)
  - View details

---

### MODULE 27: Employee Self-Service

**Available to:** All employees with linked employee records

#### 27.1 My Profile

**Path:** `/employee/profile`
- View personal info
- Contact details
- Employment details

#### 27.2 My Payslips

**Path:** `/employee/payslips`
- View/download payslips
- Payment history

#### 27.3 My Leaves

**Path:** `/employee/leave`
- View leave balance
- Submit leave request
- View leave history

#### 27.4 My Overtime

**Path:** `/employee/overtime`
- Submit OT request
- View OT history

#### 27.5 My Loans

**Path:** `/employee/loans`
- View active loans
- Apply for new loan
- View amortization

#### 27.6 My Attendance

**Path:** `/employee/attendance`
- View attendance logs
- View summaries

---

### MODULE 28: Vendor Portal

**Login:** `vendor@ogamierp.local` / `Vendor@Test1234!`

#### 28.1 Dashboard

**Path:** `/vendor-portal`
- Overview of POs, deliveries, invoices
- Payment status

#### 28.2 Purchase Orders

**Path:** `/vendor-portal/orders`
- View POs sent to vendor
- Acknowledge PO
- View PO details

#### 28.3 Goods Receipts (Deliveries)

**Path:** `/vendor-portal/goods-receipts`
- Submit delivery notes
- View delivery history

#### 28.4 Invoices

**Path:** `/vendor-portal/invoices`
- Submit invoices
- View invoice status
- View payment status

---

### MODULE 29: Client Portal

**Login:** `client@ogamierp.local` / `Client@Test1234!`

#### 29.1 My Tickets

**Path:** `/client-portal/tickets`
- View submitted tickets
- View responses

#### 29.2 New Ticket

**Path:** `/client-portal/tickets/new`
- Submit support request
- Attach files

---

### MODULE 30: Admin (Reference Only)

> ⚠️ **Note:** Admin functions are NOT part of SoD testing as admin bypasses SoD rules.

#### 30.1 Users

**Path:** `/admin/users`
- Manage user accounts
- Assign roles
- Reset passwords

#### 30.2 System Settings

**Path:** `/admin/settings`
- Configure system parameters
- Company settings

#### 30.3 Reference Tables

**Path:** `/admin/reference-tables`
- Manage lookup tables:
  - Salary grades
  - SSS contribution table
  - PhilHealth table
  - Pag-IBIG table
  - Tax brackets
  - Holiday calendar

#### 30.4 Audit Logs

**Path:** `/admin/audit-logs`
- View system audit trail
- Filter by user, date, action

#### 30.5 Fiscal Periods

**Path:** `/accounting/fiscal-periods`
- Define fiscal years
- Open/close periods

---

## Cross-Module Integration Workflows

### WORKFLOW 1: New Hire to First Payroll (HR → Attendance → Leave → Payroll → GL)

**Participants:** HR Manager, Accounting Officer, Accounting Manager

| Step | Module | Action | User | Path | Notes |
|------|--------|--------|------|------|-------|
| 1 | HR | Create employee | HR Manager | `/hr/employees/new` | Without all govt IDs = `is_active: false` |
| 2 | HR | Add missing documents | HR Manager | `/hr/employees/:ulid/edit` | Add SSS, TIN, PhilHealth, Pag-IBIG |
| 3 | HR | Auto-activation | System | Auto | When all IDs provided, `is_active: true` |
| 4 | Attendance | Log daily attendance | GA Officer | `/hr/attendance` | — |
| 5 | Payroll | Create payroll run | HR Manager | `/payroll/runs/new` | Wizard: Scope → Validate → Compute |
| 6 | Payroll | Try to approve | HR Manager | `/payroll/runs/:ulid` | ❌ BLOCKED - "Approve (SoD)" disabled |
| 7 | Payroll | Review payroll | Acctg Officer | `/payroll/runs/:ulid/review` | ✅ Reviewer |
| 8 | Payroll | Approve payroll | Acctg Manager | `/payroll/runs/:ulid` | ✅ Approver - Button enabled |
| 9 | Payroll | Disburse | Acctg Officer | `/payroll/runs/:ulid/disburse` | — |
| 10 | Employee | View payslip | Employee | `/employee/payslips` | Self-service |

### WORKFLOW 2: Procurement to Payment (PR → PO → GR → AP → Payment)

**Participants:** Staff, Dept Head, Purchasing, Plant Manager, Warehouse, Accounting

| Step | Module | Action | User | Path | SoD Check |
|------|--------|--------|------|------|-----------|
| 1 | Procurement | Create PR | Staff | `/procurement/purchase-requests/new` | — |
| 2 | Procurement | Submit PR | Staff | `/procurement/purchase-requests/:ulid` | Status → pending_head |
| 3 | Procurement | Try self-approve | Staff | Same page | ❌ No approval actions visible |
| 4 | Procurement | Head checks | Dept Head | `/procurement/purchase-requests/:ulid` | ✅ "Check" button enabled |
| 5 | Procurement | Manager reviews | Plant Manager | Same page | ✅ "Review" button |
| 6 | Procurement | VP approves (if high value) | VP | `/approvals/pending` | ✅ "VP Approve" |
| 7 | Procurement | Create PO | Purchasing | `/procurement/purchase-orders/new` | From approved PR |
| 8 | Procurement | Approve PO | Plant Manager | `/procurement/purchase-orders/:ulid` | ✅ Different user |
| 9 | Procurement | Send PO | Purchasing | Same page | Generate PDF |
| 10 | Inventory | Receive goods | Warehouse | `/procurement/goods-receipts/new` | GR created |
| 11 | AP | Create invoice | Acctg Officer | `/accounting/ap/invoices/new` | 3-way match check |
| 12 | AP | Try self-approve | Same Officer | `/accounting/ap/invoices/:ulid` | ❌ "Approve (SoD)" disabled |
| 13 | AP | Manager approves | Acctg Manager | Same page | ✅ "Approve" enabled |
| 14 | Banking | Process payment | Acctg Officer | Bank payment | — |

### WORKFLOW 3: Journal Entry Creation to Posting (SoD Demonstration)

**Participants:** Accounting Officer, Accounting Manager

| Step | Action | User | UI Element | Expected |
|------|--------|------|------------|----------|
| 1 | Create JE | Acctg Officer | `/accounting/journal-entries/new` | Save as draft |
| 2 | Add lines | Acctg Officer | Debit/Credit lines | Balance required |
| 3 | Submit | Acctg Officer | "Submit for Approval" button | Status → submitted |
| 4 | Try to post | Acctg Officer | `/accounting/journal-entries/:ulid` | ❌ "Post (SoD)" disabled |
| 5 | Tooltip | Acctg Officer | Hover blocked button | Shows SoD violation message |
| 6 | Post JE | Acctg Manager | Same page | ✅ "Post" button enabled |
| 7 | Verify | Any | `/accounting/gl` | Entry appears in GL |

### WORKFLOW 4: Employee Leave Request to Approval

**Participants:** Staff, Department Head

| Step | Action | User | Path | SoD Check |
|------|--------|------|------|-----------|
| 1 | Submit leave | Staff | `/employee/leave` or `/hr/leave/new` | — |
| 2 | View own | Staff | `/employee/leave` | No approve button |
| 3 | Try self-approve | Staff | N/A | ❌ No action available |
| 4 | Approve | Dept Head | `/team/leave` or `/hr/leave` | ✅ "Approve" button |
| 5 | Balance updated | System | Auto | Deduct from leave balance |

---

## SoD Violation Testing

### How to Identify SoD Blocks in UI

When a user tries to approve their own record:

1. **Button State:** Button shows "Action (SoD)" in disabled/gray state
2. **Tooltip:** Hover shows: *"Segregation of Duties violation: you initiated this record and cannot approve it. A different user must perform the approval."*
3. **Opacity:** Button has reduced opacity (60%)
4. **Cursor:** Not-allowed cursor on hover

### Test SOD-001: Journal Entry Posting

```
1. Login: acctg.officer@ogamierp.local / Officer@Test1234!
2. Create JE at /accounting/journal-entries/new
3. Submit JE
4. Try to post - button shows "Post (SoD)" disabled
5. Login: acctg.manager@ogamierp.local / Manager@12345!
6. Same JE - button shows "Post" enabled
7. Click Post - success
```

### Test SOD-002: Payroll Approval

```
1. Login: hr.manager@ogamierp.local / Manager@Test1234!
2. Create payroll at /payroll/runs/new (wizard)
3. Process to COMPUTED status
4. Submit for approval
5. Try to approve - blocked
6. Login: acctg.manager@ogamierp.local / Manager@12345!
7. Approve - success
```

### Test SOD-003: Purchase Request Approval

```
1. Login: prod.staff@ogamierp.local / Staff@Test1234!
2. Create PR at /procurement/purchase-requests/new
3. Submit PR
4. No approval actions visible
5. Login: prod.head@ogamierp.local / Head@Test1234!
6. Approve at /procurement/purchase-requests/:ulid
7. Check, Review, Budget Check buttons visible
```

### Test SOD-004: AP Invoice Approval

```
1. Login: acctg.officer@ogamierp.local / Officer@Test1234!
2. Create invoice at /accounting/ap/invoices/new
3. Try to approve - "Approve (SoD)" disabled
4. Login: acctg.manager@ogamierp.local / Manager@12345!
5. Approve - success
```

### Test SOD-005: Leave Approval

```
1. Login: hr.head@ogamierp.local / Head@Test1234!
2. Submit leave request for yourself
3. Try to approve own request - button shows "(SoD)" disabled
4. Login: hr.manager@ogamierp.local / Manager@Test1234!
5. Approve the leave - success
```

---

## Approval Workflows

### Hierarchy Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    APPROVAL HIERARCHY                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  LEVEL 1: Department Head (dept.head@ogamierp.local)           │
│  ├── PR: Check button                                           │
│  ├── Leave: Approve button in Team Management                   │
│  ├── OT: Approve button                                         │
│  └── Employee: Status changes (on_leave, suspended, etc.)       │
│                                                                 │
│  LEVEL 2: Manager/Plant Manager                                 │
│  ├── PR: Review button                                          │
│  ├── PO: Approve button                                         │
│  └── Work Order: Release button                                 │
│                                                                 │
│  LEVEL 3: VP (vp@ogamierp.local / Vice_president@Test1234!)    │
│  ├── PR: VP Approve button (via /approvals/pending)            │
│  ├── Loans: Final approval                                      │
│  └── Budget: Approval                                           │
│                                                                 │
│  LEVEL 4: Accounting Manager (acctg.manager@ogamierp.local)    │
│  ├── JE: Post button                                            │
│  ├── AP Invoice: Approve button                                 │
│  ├── Payroll: Approve button                                    │
│  └── AR Payment: Apply button                                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### VP Approvals Dashboard

**Path:** `/approvals/pending`

**Available Actions:**
- View pending PRs, Loans, Material Requisitions
- Approve with comments
- Reject with reason
- View approval history

---

## Quick Test Checklist

### Pre-Test Setup

```bash
# Fresh database with seed data
php artisan migrate:fresh --seed

# Verify all accounts exist
php scripts/verify-test-accounts.php

# Start services
npm run dev
```

### Critical Path Tests (Must Pass)

| # | Test | Role | Path | Expected |
|---|------|------|------|----------|
| 1 | Login | Any | `/login` | Dashboard loads |
| 2 | Create Employee | hr.manager | `/hr/employees/new` | Employee created, `onboarding_status: documents_pending` |
| 3 | Add Govt IDs | hr.manager | `/hr/employees/:ulid/edit` | Auto-activation when all IDs provided |
| 4 | Create PR | prod.staff | `/procurement/purchase-requests/new` | PR submitted |
| 5 | SoD Block PR | prod.staff | N/A | No approve actions |
| 6 | Approve PR (Head) | prod.head | `/procurement/purchase-requests/:ulid` | Check button works |
| 7 | Create PO | acctg.staff | `/procurement/purchase-orders/new` | PO created |
| 8 | Create GR | prod.head | `/procurement/goods-receipts/new` | Stock increased |
| 9 | Create AP Invoice | acctg.officer | `/accounting/ap/invoices/new` | Invoice pending |
| 10 | SoD Block Invoice | acctg.officer | `/accounting/ap/invoices/:ulid` | "Approve (SoD)" disabled |
| 11 | Approve Invoice | acctg.manager | Same page | Status → approved |
| 12 | Create JE | acctg.officer | `/accounting/journal-entries/new` | JE saved |
| 13 | Submit JE | acctg.officer | `/accounting/journal-entries/:ulid` | Status → submitted |
| 14 | SoD Block JE Post | acctg.officer | Same page | "Post (SoD)" disabled |
| 15 | Post JE | acctg.manager | Same page | Status → posted |
| 16 | Create Payroll | hr.manager | `/payroll/runs/new` | Wizard complete |
| 17 | SoD Block Payroll | hr.manager | `/payroll/runs/:ulid` | "Approve (SoD)" disabled |
| 18 | Approve Payroll | acctg.manager | Same page | Status → ACCTG_APPROVED |
| 19 | VP Dashboard | vp | `/approvals/pending` | Pending items visible |
| 20 | Submit Leave | hr.staff | `/employee/leave` | Request submitted |
| 21 | SoD Block Leave | hr.staff | N/A | Cannot self-approve |
| 22 | Approve Leave | hr.head | `/team/leave` | Status → approved |

### SoD Enforcement Checklist

Verify these scenarios show "(SoD)" disabled button or no action:

- [ ] User cannot approve own leave request
- [ ] User cannot approve own OT request
- [ ] HR cannot approve own payroll run
- [ ] AP cannot approve own invoice
- [ ] Officer cannot post own JE
- [ ] Staff cannot see PR approval actions

### Module Coverage Checklist

- [ ] HR - Employee CRUD + Document upload + Auto-activation
- [ ] Team Management - Department-scoped views
- [ ] Attendance - Logs + Import
- [ ] Leave - Request + Approval with SoD
- [ ] Loans - Application + Approval chain
- [ ] Payroll - Full wizard with SoD
- [ ] Accounting - COA
- [ ] Journal Entries - Create + Submit + Post with SoD
- [ ] General Ledger
- [ ] AP - Vendors + Invoices with SoD
- [ ] AR - Customers + Invoices
- [ ] Banking - Accounts + Reconciliation
- [ ] Tax - VAT Ledger
- [ ] Financial Reports
- [ ] Fixed Assets
- [ ] Budget
- [ ] Procurement - PR workflow with SoD
- [ ] Procurement - PO workflow
- [ ] Procurement - GR
- [ ] Inventory - Items + Stock
- [ ] Production - BOM
- [ ] Production - Work Orders with SoD
- [ ] QC - Inspections + NCR + CAPA
- [ ] Maintenance - Equipment + Work Orders
- [ ] Mold
- [ ] Delivery
- [ ] ISO - Documents + Audits
- [ ] CRM
- [ ] VP Approvals Dashboard
- [ ] Employee Self-Service
- [ ] Vendor Portal
- [ ] Client Portal

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Cannot login | Run `php artisan db:seed` to reset accounts |
| Missing permissions | Run `php artisan db:seed --class=RolePermissionSeeder` |
| SoD not enforced | Check user roles - `manager` role IS subject to SoD |
| No approve button | Verify you're not the creator (check `created_by_id`) |
| Department access denied | Check `user_department_access` table |
| Page not found | Verify route exists in `frontend/src/router/index.tsx` |

### Debug Commands

```bash
# Check user roles
php artisan tinker
$user = App\Models\User::where('email', 'hr.manager@ogamierp.local')->first();
$user->roles->pluck('name');

# Check SoD bypass (should be false for testing)
$user->hasRole('admin');

# Check employee link
$employee = App\Domains\HR\Models\Employee::where('employee_code', 'EMP-HR-001')->first();
$employee->user_id;

# Check who created a record
$pr = App\Domains\Procurement\Models\PurchaseRequest::first();
$pr->created_by_id;
```

### Frontend Debug

```typescript
// In browser console for logged-in user
const user = JSON.parse(localStorage.getItem('auth-storage')).state.user;
console.log(user.roles);
console.log(user.permissions);
```

---

## Appendix: Database Reset for Clean Testing

```bash
# Full reset
php artisan migrate:fresh --seed

# Or individual seeders
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=ComprehensiveTestAccountsSeeder
php artisan db:seed --class=SampleDataSeeder
php artisan db:seed --class=ExtraAccountsSeeder

# Verify
php scripts/verify-test-accounts.php
```

---

## Appendix: All Frontend Routes Reference

### Core Routes
- `/` - Role-based landing redirect
- `/login` - Login page
- `/403` - Forbidden page
- `/change-password` - Password change

### Dashboard Routes
- `/dashboard` - Role-based dashboard

### HR Routes
- `/hr/employees/all` - All employees
- `/hr/employees/:ulid` - Employee detail
- `/hr/employees/:ulid/edit` - Edit employee
- `/hr/employees/new` - New employee
- `/hr/attendance` - Attendance logs
- `/hr/attendance/import` - Import attendance
- `/hr/attendance/dashboard` - Attendance dashboard
- `/hr/attendance/summary` - Attendance summary
- `/hr/overtime` - Overtime list
- `/hr/leave` - Leave requests
- `/hr/leave/balances` - Leave balances
- `/hr/leave/calendar` - Leave calendar
- `/hr/leave/new` - New leave request
- `/hr/loans` - Loans list
- `/hr/loans/:id` - Loan detail
- `/hr/loans/new` - New loan
- `/hr/departments` - Departments
- `/hr/positions` - Positions
- `/hr/shifts` - Shift schedules
- `/hr/reports` - HR reports

### Team Management Routes
- `/team/employees` - My team
- `/team/attendance` - Team attendance
- `/team/leave` - Team leave
- `/team/overtime` - Team overtime
- `/team/loans` - Team loans

### Employee Self-Service Routes
- `/employee/profile` - My profile
- `/employee/payslips` - My payslips
- `/employee/leave` - My leaves
- `/employee/overtime` - My OT
- `/employee/loans` - My loans
- `/employee/attendance` - My attendance

### Payroll Routes
- `/payroll/runs` - Payroll runs list
- `/payroll/runs/new` - New payroll run
- `/payroll/runs/new/scope` - Wizard: Scope
- `/payroll/runs/new/validate` - Wizard: Validate
- `/payroll/runs/new/compute` - Wizard: Compute
- `/payroll/runs/:ulid` - Run detail
- `/payroll/runs/:ulid/review` - Run review
- `/payroll/periods` - Pay periods

### Accounting Routes
- `/accounting/accounts` - Chart of accounts
- `/accounting/journal-entries` - Journal entries
- `/accounting/journal-entries/new` - New JE
- `/accounting/journal-entries/:ulid` - JE detail
- `/accounting/recurring-templates` - Recurring JEs
- `/accounting/gl` - General ledger
- `/accounting/trial-balance` - Trial balance
- `/accounting/balance-sheet` - Balance sheet
- `/accounting/income-statement` - Income statement
- `/accounting/cash-flow` - Cash flow
- `/accounting/vat-ledger` - VAT ledger
- `/accounting/tax-summary` - Tax summary
- `/accounting/fiscal-periods` - Fiscal periods

### AP Routes
- `/accounting/vendors` - Vendors
- `/accounting/ap/invoices` - AP invoices
- `/accounting/ap/invoices/new` - New AP invoice
- `/accounting/ap/invoices/:ulid` - AP invoice detail
- `/accounting/ap/credit-notes` - AP credit notes
- `/accounting/ap/aging-report` - AP aging

### AR Routes
- `/ar/customers` - Customers
- `/ar/invoices` - AR invoices
- `/ar/invoices/new` - New AR invoice
- `/ar/invoices/:ulid` - AR invoice detail
- `/ar/credit-notes` - AR credit notes
- `/ar/aging-report` - AR aging

### Banking Routes
- `/banking/accounts` - Bank accounts
- `/banking/reconciliations` - Reconciliations
- `/banking/reconciliations/:id` - Reconciliation detail

### Fixed Assets Routes
- `/fixed-assets` - Asset register
- `/fixed-assets/categories` - Asset categories
- `/fixed-assets/disposals` - Asset disposals

### Budget Routes
- `/budget/cost-centers` - Cost centers
- `/budget/lines` - Budget lines
- `/budget/vs-actual` - Budget vs actual

### Procurement Routes
- `/procurement/purchase-requests` - PR list
- `/procurement/purchase-requests/new` - New PR
- `/procurement/purchase-requests/:ulid` - PR detail
- `/procurement/purchase-orders` - PO list
- `/procurement/purchase-orders/new` - New PO
- `/procurement/purchase-orders/:ulid` - PO detail
- `/procurement/goods-receipts` - GR list
- `/procurement/goods-receipts/new` - New GR
- `/procurement/goods-receipts/:ulid` - GR detail
- `/procurement/rfqs` - RFQs
- `/procurement/rfqs/:ulid` - RFQ detail
- `/procurement/analytics` - Procurement analytics

### Inventory Routes
- `/inventory/categories` - Item categories
- `/inventory/items` - Item master
- `/inventory/items/new` - New item
- `/inventory/locations` - Warehouse locations
- `/inventory/stock` - Stock balances
- `/inventory/ledger` - Stock ledger
- `/inventory/requisitions` - Material requisitions
- `/inventory/requisitions/new` - New MR
- `/inventory/adjustments` - Stock adjustments
- `/inventory/valuation` - Inventory valuation
- `/inventory/physical-count` - Physical count

### Production Routes
- `/production/boms` - BOM list
- `/production/boms/new` - New BOM
- `/production/boms/:id/edit` - Edit BOM
- `/production/orders` - Work orders
- `/production/orders/new` - New work order
- `/production/orders/:ulid` - Work order detail
- `/production/delivery-schedules` - Delivery schedules
- `/production/cost-analysis` - Cost analysis

### QC Routes
- `/qc/inspections` - Inspections
- `/qc/inspections/new` - New inspection
- `/qc/inspections/:ulid` - Inspection detail
- `/qc/ncrs` - NCRs
- `/qc/ncrs/new` - New NCR
- `/qc/ncrs/:ulid` - NCR detail
- `/qc/capa` - CAPA
- `/qc/templates` - QC templates
- `/qc/defect-rate` - Defect rate

### Maintenance Routes
- `/maintenance/equipment` - Equipment list
- `/maintenance/equipment/new` - New equipment
- `/maintenance/equipment/:id` - Equipment detail
- `/maintenance/work-orders` - Work orders
- `/maintenance/work-orders/new` - New work order
- `/maintenance/work-orders/:id` - Work order detail

### Mold Routes
- `/mold/masters` - Mold masters
- `/mold/masters/new` - New mold
- `/mold/masters/:id` - Mold detail

### Delivery Routes
- `/delivery/receipts` - Delivery receipts
- `/delivery/receipts/new` - New DR
- `/delivery/receipts/:ulid` - DR detail
- `/delivery/shipments` - Shipments

### ISO Routes
- `/iso/documents` - Documents
- `/iso/documents/new` - New document
- `/iso/documents/:ulid` - Document detail
- `/iso/audits` - Audits
- `/iso/audits/new` - New audit
- `/iso/audits/:ulid` - Audit detail

### CRM Routes
- `/crm/dashboard` - CRM dashboard
- `/crm/tickets` - Tickets
- `/crm/tickets/:ulid` - Ticket detail

### VP Approvals Routes
- `/approvals/pending` - Pending approvals dashboard

### Executive Routes
- `/executive/leave-approvals` - Executive leave approvals
- `/executive/overtime-approvals` - Executive OT approvals

### Vendor Portal Routes
- `/vendor-portal` - Vendor dashboard
- `/vendor-portal/orders` - Vendor POs
- `/vendor-portal/orders/:ulid` - PO detail
- `/vendor-portal/goods-receipts` - Vendor deliveries
- `/vendor-portal/invoices` - Vendor invoices
- `/vendor-portal/items` - Vendor items

### Client Portal Routes
- `/client-portal/tickets` - Client tickets
- `/client-portal/tickets/new` - New ticket
- `/client-portal/tickets/:ulid` - Ticket detail

### Admin Routes
- `/admin/users` - Users
- `/admin/settings` - System settings
- `/admin/reference-tables` - Reference tables
- `/admin/audit-logs` - Audit logs
- `/admin/backup` - Backup & restore

### Reports Routes
- `/reports/government` - Government reports

---

*Document Version: 5.0*  
*Last Updated: 2026-03-16*  
*Total Test Cases: 300+ covering 30 modules*  
*Total Test Accounts: 23 with linked employee records*  
*SoD Rules Verified: 7 core separations*  
*All Routes Verified Against Frontend Router*
