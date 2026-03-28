# Ogami ERP - Comprehensive Manual Testing Guide

**Version:** 1.0  
**Date:** 2026-03-17  
**Estimated Testing Time:** 4-6 hours

---

## Table of Contents

1. [Test Environment Setup](#1-test-environment-setup)
2. [Testing by Module](#2-testing-by-module)
3. [Cross-Module Workflows](#3-cross-module-workflows)
4. [Approval Hierarchy Tests](#4-approval-hierarchy-tests)
5. [SoD (Segregation of Duties) Tests](#5-sod-tests)
6. [Role-Based Access Tests](#6-role-based-access-tests)
7. [Test Data Requirements](#7-test-data-requirements)
8. [Test Sign-off Sheet](#8-test-sign-off-sheet)

---

## 1. Test Environment Setup

### Required Accounts

| Role | Email | Password | Department |
|------|-------|----------|------------|
| Admin | admin@ogamierp.local | Admin@1234567890! | — |
| VP | vp@ogamierp.local | VicePresident@1! | All |
| HR Manager | hr.manager@ogamierp.local | HrManager@1234! | HR |
| Plant Manager | plant.manager@ogamierp.local | Manager@12345! | PLANT |
| Accounting Officer | acctg.officer@ogamierp.local | AcctgManager@1234! | ACCTG |
| GA Officer | ga.officer@ogamierp.local | Officer@12345! | HR |
| Warehouse Head | warehouse.head@ogamierp.local | Head@123456789! | WH |
| Production Head | production.head@ogamierp.local | Head@123456789! | PROD |
| Staff | prod.staff@ogamierp.local | Staff@123456789! | PROD |

### Pre-Test Checklist
- [ ] Database freshly seeded: `php artisan migrate:fresh --seed`
- [ ] Backend running: `php artisan serve`
- [ ] Frontend running: `pnpm dev`
- [ ] Clear browser cache

---

## 2. Testing by Module

### MODULE 1: HR & Employee Management

#### 1.1 Employee Creation & Lifecycle

**Test 1.1.1: Create Employee (HR Manager)**
1. Login: `hr.manager@ogamierp.local`
2. Navigate: HR → All Employees → New Employee
3. Fill required fields:
   - Employee Code: `EMP-TEST-001`
   - Name: `Test Employee`
   - Department: HR
   - Position: HR Assistant
   - Salary Grade: SG-5
   - Basic Monthly Rate: ₱25,000
4. Save
5. **Expected:** Employee created with status "Draft"

**Test 1.1.2: Activate Employee (SoD Test)**
1. As HR Manager, find employee `EMP-TEST-001`
2. Try to activate
3. **Expected:** SoD block - "You created this record, cannot activate"
4. Login as Admin
5. Activate employee
6. **Expected:** Activation successful

**Test 1.1.3: Employee Status Transitions**
1. Test: Draft → Active
2. Test: Active → On Leave
3. Test: On Leave → Active
4. Test: Active → Resigned
5. **Expected:** Each transition updates status and logs audit trail

---

#### 1.2 Attendance Management

**Test 1.2.1: Biometric Import (HR Manager)**
1. Navigate: HR → Attendance → Import
2. Upload CSV with format:
   ```
   employee_code,date,time_in,time_out
   EMP-2026-0001,2026-03-15,08:00,17:00
   ```
3. **Expected:** Import successful, attendance logs created

**Test 1.2.2: Manual Attendance Entry (GA Officer)**
1. Login: `ga.officer@ogamierp.local`
2. Navigate: HR → Attendance → Manual Entry
3. Select employee, date, time
4. Save
5. **Expected:** Attendance log created

**Test 1.2.3: Attendance Anomaly Detection**
1. Create attendance with:
   - Late arrival (>15 min grace period)
   - Early departure
   - Missing time_out
2. **Expected:** Anomaly flagged in dashboard

**Test 1.2.4: Overtime Request Workflow**

| Step | Role | Action | Status |
|------|------|--------|--------|
| 1 | Staff | Submit OT request | `pending` |
| 2 | Head | Supervisor endorse | `supervisor_approved` |
| 3 | Manager | Approve OT | `approved` |
| 4 | HR | Process payroll | OT included |

**SoD Test:**
- Staff cannot approve own OT
- Head cannot approve own filed OT
- Manager cannot approve own filed OT

---

#### 1.3 Leave Management

**Test 1.3.1: Leave Request Workflow (AD-084-00)**

```
Staff Submits
    ↓
Head Approves (Step 2)
    ↓
Manager Checks (Step 3)
    ↓
GA Processes (Step 4) - Action Taken
    ↓
VP Notes (Step 5) - Final Approval
    ↓
Balance Deducted
```

**Detailed Steps:**
1. **Staff** submits leave: 2026-03-20 to 2026-03-21 (2 days)
2. **Head** reviews:
   - Can see in "Team Leave" page
   - Approve button available
   - Can add remarks
3. **Manager** reviews head-approved leave:
   - Can check (approve) or reject
4. **GA Officer** processes:
   - Sets action taken: `approved_with_pay` or `approved_without_pay`
5. **VP** final notation:
   - Views in VP Approvals Dashboard
   - Final approve or reject

**Test 1.3.2: Leave Balance Check**
1. Staff with 15 days balance requests 5 days
2. After approval, balance = 10 days
3. **Expected:** Balance updated correctly

**Test 1.3.3: SoD - Cannot Approve Own Leave**
1. Head submits leave request
2. Head navigates to Team Leave
3. **Expected:** Approve button disabled with SoD warning

---

#### 1.4 Loan Management

**Test 1.4.1: Loan Application Workflow (v2 - 5 Stage)**

```
Staff Applies
    ↓
Head Notes (Step 1) - Recommendation
    ↓
Manager Checks (Step 2) - Verification
    ↓
Officer Reviews (Step 3) - Terms finalization
    ↓
VP Approves (Step 4) - Final approval
    ↓
Accounting Disburses
```

**Detailed Steps:**
1. **Staff** applies:
   - Loan Type: SSS Salary Loan
   - Amount: ₱50,000
   - Term: 24 months
2. **Head** adds notation
3. **Manager** checks and verifies
4. **Accounting Officer** reviews terms
5. **VP** approves
6. **Accounting** disburses, creates journal entry

**Test 1.4.2: Loan Amortization**
1. After disbursement
2. **Expected:** Monthly deduction appears in payroll

---

### MODULE 2: Payroll

#### 2.1 Payroll Run Workflow

**Test 2.1.1: Complete Payroll Run (14-State Workflow)**

```
1. DRAFT → Manager creates payroll run
2. SCOPE_SET → Employees selected
3. PRE_RUN_CHECKED → Validation complete
4. PROCESSING → Computation running
5. COMPUTED → Calculations done
6. REVIEW → HR review
7. SUBMITTED → To HR
8. HR_APPROVED → HR approved
9. ACCTG_APPROVED → Accounting approved
10. DISBURSED → Bank file generated
11. PUBLISHED → Payslips available
```

**Detailed Steps:**
1. **HR Manager** initiates:
   - Pay Period: March 1-15, 2026
   - Type: Regular
2. **System** computes:
   - Basic pay
   - Overtime
   - Allowances
   - Deductions (SSS, PhilHealth, Pag-IBIG, Tax)
   - Loans
3. **HR Manager** reviews breakdown
4. **Accounting Manager** approves
5. **System** generates:
   - Bank file for upload
   - Payslips
   - Government reports

**Test 2.1.2: SoD - Manager Cannot Approve Own Payroll**
1. Manager creates payroll run
2. Try to approve
3. **Expected:** SoD block

**Test 2.1.3: Payroll Computation Verification**
1. Employee: Monthly ₱25,000
2. 2 days absent
3. **Expected Calculation:**
   ```
   Daily Rate = 25,000 / 22 = ₱1,136.36
   Absent Deduction = 1,136.36 × 2 = ₱2,272.72
   Gross Pay = 25,000 - 2,272.72 = ₱22,727.28
   SSS = ₱1,125
   PhilHealth = ₱363.64
   Pag-IBIG = ₱100
   Taxable Income = 22,727.28 - 1,588.64 = ₱21,138.64
   Withholding Tax = ₱0 (below threshold)
   Net Pay = 22,727.28 - 1,588.64 = ₱21,138.64
   ```

---

### MODULE 3: Accounting & Finance

#### 3.1 Chart of Accounts

**Test 3.1.1: Account Management (Accounting Officer)**
1. Navigate: Accounting → Chart of Accounts
2. Create new account:
   - Code: 5100-TEST
   - Name: Test Expense Account
   - Type: Expense
3. **Expected:** Account created, appears in reports

**Test 3.1.2: Account Hierarchy**
1. Create parent account
2. Create child accounts
3. **Expected:** Proper nesting in COA tree

---

#### 3.2 Journal Entries

**Test 3.2.1: Journal Entry Creation & Posting**

**Step 1: Create (Accounting Officer)**
```
Date: 2026-03-15
Description: Test Journal Entry

Line 1: 1100 - Cash          Debit  ₱10,000
Line 2: 4100 - Revenue      Credit ₱10,000
```
**Expected:** JE in "Draft" status

**Step 2: Submit for Approval**
1. Accounting Officer submits
2. **Expected:** Status = "Submitted"

**Step 3: Post (Different User - Manager)**
1. Manager reviews
2. Posts journal entry
3. **SoD Test:** Accounting Officer cannot post own JE

**Test 3.2.2: Auto-Reversal**
1. Create accrual entry with "Auto-reverse" flag
2. Set reversal date
3. **Expected:** System creates reversing entry on date

---

#### 3.3 Accounts Payable (AP)

**Test 3.3.1: Vendor Accreditation Workflow**

```
Purchasing Officer → Creates Vendor (Draft)
    ↓
Purchasing Manager → Accredits (Pending Docs)
    ↓
Vendor → Uploads documents
    ↓
Purchasing Manager → Approves (Accredited)
```

**Test 3.3.2: Vendor Invoice Workflow**

**Step 1: Create Invoice (Accounting Officer)**
- Vendor: Test Vendor
- Amount: ₱50,000
- Date: 2026-03-15
- Due: 2026-04-15

**Step 2: Approval Process**
| Step | Role | Action |
|------|------|--------|
| 1 | Accounting Officer | Submit |
| 2 | Accounting Manager | Review |
| 3 | VP | Approve (if > threshold) |

**Step 3: Payment**
1. Accounting generates payment batch
2. VP approves disbursement
3. Bank file generated
4. Payment recorded

**Test 3.3.3: SoD - Cannot Approve Own Invoice**
1. Accounting Officer creates invoice
2. Try to approve
3. **Expected:** SoD block

---

#### 3.4 Bank Reconciliation

**Test 3.4.1: Reconciliation Process**

**Step 1: Create Reconciliation (Accounting Officer)**
- Bank Account: BPI Checking
- Period: March 2026
- Starting Balance: ₱100,000

**Step 2: Import Bank Statement**
1. Upload CSV
2. System matches transactions
3. Unmatched items flagged

**Step 3: Manual Matching**
1. Match unmatched items
2. Add adjustments for bank charges

**Step 4: Certification (Different User)**
1. Manager reviews
2. Certifies reconciliation
3. **SoD Test:** Creator cannot certify

---

### MODULE 4: Procurement

#### 4.1 Purchase Request (PR) Workflow

```
Staff/Dept Head → Creates PR (Draft)
    ↓
Staff → Submits PR
    ↓
Dept Head → Notes/Approves
    ↓
Purchasing Officer → Checks specifications
    ↓
Purchasing Manager → Reviews
    ↓
Budget Officer → Budget check
    ↓
VP → Approves (if > threshold)
    ↓
Purchasing Officer → Creates RFQ/PO
```

**Test 4.1.1: Complete PR to PO Flow**

**Step 1: Create PR (Production Head)**
- Items: Raw materials
- Estimated Cost: ₱100,000
- Urgency: Normal

**Step 2: Submit**
1. Production Head submits
2. **Expected:** Status = "Submitted"

**Step 3: Approval Chain**
| Step | Role | Status After |
|------|------|--------------|
| Dept Head | Production Head | `noted` |
| Check | Purchasing Officer | `checked` |
| Review | Purchasing Manager | `reviewed` |
| Budget | Budget Officer | `budget_checked` |
| Approve | VP | `approved` |

**Step 4: PO Creation**
1. Purchasing Officer creates PO from PR
2. Selects vendor
3. PO sent to vendor

**Test 4.1.2: SoD - Cannot Approve Own PR**
1. Production Head creates PR
2. Try to approve at VP level
3. **Expected:** SoD block

---

#### 4.2 Goods Receipt

**Test 4.2.1: Receive Against PO**
1. PO status: "Sent"
2. Warehouse Head receives goods
3. Create Goods Receipt (GR)
4. **Expected:** 
   - Inventory updated
   - PO status: "Partially Received" or "Fully Received"
   - GR linked to PO

---

### MODULE 5: Inventory

#### 5.1 Material Requisition (MRQ)

**Test 5.1.1: MRQ Workflow**

```
Staff/Head → Creates MRQ (Draft)
    ↓
Staff/Head → Submits
    ↓
Warehouse Head → Notes availability
    ↓
Warehouse Manager → Checks
    ↓
Requesting Dept Manager → Reviews
    ↓
VP → Approves (if > threshold)
    ↓
Warehouse → Fulfills MRQ
```

**Test 5.1.2: SoD in MRQ**
- Production Head creates MRQ
- Try to approve at VP level
- **Expected:** SoD block

---

#### 5.2 Stock Management

**Test 5.2.1: Stock In (GR)**
1. Receive 100 units of Item A
2. **Expected:** Stock +100

**Test 5.2.2: Stock Out (MRQ)**
1. Fulfill MRQ for 50 units
2. **Expected:** Stock -50

**Test 5.2.3: Stock Adjustment**
1. Physical count shows 45 units (system shows 50)
2. Create adjustment: -5 units
3. **Expected:** System updated, reason logged

---

### MODULE 6: Production

#### 6.1 Production Order

**Test 6.1.1: Create Production Order**
1. Navigate: Production → Orders → New
2. Select BOM (Bill of Materials)
3. Set quantity: 100 units
4. Set deadline
5. **Expected:** PO created with status "Planned"

**Test 6.1.2: Production Workflow**

```
PPC → Creates PO (Planned)
    ↓
PPC → Releases PO (Released)
    ↓
Production → Starts production (In Progress)
    ↓
Production → Logs output (Partial)
    ↓
Production → Completes (Completed)
    ↓
QC → Inspects (Passed/Failed)
    ↓
Inventory → Stock updated
```

---

### MODULE 7: Quality Control (QC)

#### 7.1 Inspection

**Test 7.1.1: Incoming Inspection**
1. Goods received from vendor
2. QC Inspector creates inspection
3. Record measurements
4. **Result:** Pass / Fail / Conditional

**Test 7.2.2: Non-Conformance Report (NCR)**
1. Inspection fails
2. Auto-create NCR
3. Assign to responsible party
4. Track CAPA (Corrective Action)

---

### MODULE 8: Fixed Assets

#### 8.1 Asset Management

**Test 8.1.1: Asset Acquisition**
1. Create asset record
2. Link to AP Invoice
3. **Expected:** Asset capitalized

**Test 8.1.2: Depreciation**
1. Run monthly depreciation
2. **Expected:** 
   - Depreciation entry created
   - Asset book value updated

**Test 8.1.3: Asset Disposal**
1. Record disposal
2. **Expected:** Gain/loss calculated

---

### MODULE 9: Budget

#### 9.1 Budget vs Actual

**Test 9.1.1: Budget Utilization**
1. Set department budget: ₱1,000,000
2. Create PR for ₱100,000
3. **Expected:** Budget shows 10% utilized

**Test 9.1.2: Over Budget Block**
1. Budget remaining: ₱50,000
2. Try to approve PR for ₱100,000
3. **Expected:** Warning or block

---

### MODULE 10: ISO / Document Control

#### 10.1 Controlled Documents

**Test 10.1.1: Document Lifecycle**
```
Draft → Review → Approved → Published → Obsolete
```

**Test 10.1.2: Document Revision**
1. Current: Rev A
2. Update content
3. New: Rev B
4. **Expected:** Rev A archived, Rev B active

---

## 3. Cross-Module Workflows

### Workflow 1: Purchase to Payment

```
Procurement (PR) 
    ↓
Procurement (PO)
    ↓
Inventory (GR)
    ↓
AP (Invoice)
    ↓
AP (Payment)
    ↓
GL (Journal Entries)
```

**Test Steps:**
1. Create PR → Approve → Create PO
2. Receive goods → GR created
3. Vendor sends invoice
4. Match invoice to GR and PO (3-way match)
5. Approve invoice
6. Generate payment
7. Verify GL entries

---

### Workflow 2: Employee to Payroll to GL

```
HR (Employee Hired)
    ↓
HR (Attendance + OT)
    ↓
HR (Leave deducted)
    ↓
Payroll (Compute)
    ↓
Payroll (Disburse)
    ↓
GL (Post payroll entries)
```

**Test Steps:**
1. New employee hired
2. 2 weeks attendance + OT logged
3. 1 day leave taken
4. Payroll run includes:
   - Basic pay
   - OT premium
   - Leave deduction
   - Government contributions
5. Payslip generated
6. GL entry: Debit Salaries Expense, Credit Cash/Payables

---

### Workflow 3: Production to Inventory to AR

```
Production (Order)
    ↓
Production (Complete)
    ↓
Inventory (Stock In)
    ↓
AR (Invoice Customer)
    ↓
AR (Receive Payment)
```

---

## 4. Approval Hierarchy Tests

### 4.1 Leave Approval Chain

| Role | Can Submit | Can Head Approve | Can Manager Check | Can GA Process | Can VP Note |
|------|------------|------------------|-------------------|----------------|-------------|
| Staff | ✅ | ❌ | ❌ | ❌ | ❌ |
| Head | ✅ | ✅ (own team) | ❌ | ❌ | ❌ |
| Manager | ✅ | ❌ | ✅ | ❌ | ❌ |
| GA Officer | ✅ | ❌ | ❌ | ✅ | ❌ |
| VP | ✅ | ❌ | ❌ | ❌ | ✅ |

### 4.2 Purchase Request Approval

| Amount | Dept Head | Purchasing | Budget | VP |
|--------|-----------|------------|--------|----|
| < ₱10K | ✅ | — | — | — |
| ₱10K - 50K | ✅ | ✅ | — | — |
| ₱50K - 100K | ✅ | ✅ | ✅ | — |
| > ₱100K | ✅ | ✅ | ✅ | ✅ |

### 4.3 Payroll Approval

| Step | HR Manager | Accounting | VP |
|------|------------|------------|----|
| Prepare | ✅ | — | — |
| Review | ✅ | — | — |
| Approve | ❌ (SoD) | ✅ | — |
| Disburse | — | ✅ | ✅ |

---

## 5. SoD (Segregation of Duties) Tests

### 5.1 SoD Matrix

| Action | Creator | Approver | SoD Enforced |
|--------|---------|----------|--------------|
| Employee Activation | HR | Admin/Manager | ✅ |
| Leave Approval | Staff | Head/Manager/VP | ✅ |
| OT Approval | Staff | Head/Manager | ✅ |
| Loan Approval | Staff | Head/Manager/Officer/VP | ✅ |
| Payroll Approval | HR Manager | Accounting Manager | ✅ |
| Journal Entry Post | Accountant | Manager | ✅ |
| Invoice Approval | Accountant | Manager | ✅ |
| Bank Reconciliation | Accountant | Manager | ✅ |
| PR Approval | Requester | VP | ✅ |
| PO Approval | Purchasing | Manager | ✅ |

### 5.2 Testing SoD Blocks

For each workflow above:

1. **Login as Creator** (e.g., Staff)
2. **Create record** (e.g., Leave request)
3. **Try to approve**
4. **Expected:** Button disabled, "(SoD)" label, tooltip explanation
5. **Login as Approver** (e.g., Head)
6. **Approve same record**
7. **Expected:** Approval successful

---

## 6. Role-Based Access Tests

### 6.1 Module Access by Role

| Module | Staff | Head | Officer | Manager | VP | Executive |
|--------|-------|------|---------|---------|----|----|
| Employee (view own) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Employee (view team) | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Employee (view all) | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Employee (create) | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Payroll (view own) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Payroll (view team) | ❌ | ✅ | ❌ | ✅ | ✅ | ✅ |
| Payroll (manage) | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Accounting (view) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| Accounting (create) | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Procurement (create PR) | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Procurement (approve) | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Inventory (view) | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Inventory (manage) | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Reports (view) | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Settings | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

*Admin has Settings only, Super Admin has all*

---

## 7. Test Data Requirements

### 7.1 Minimum Test Data

| Entity | Count | Details |
|--------|-------|---------|
| Employees | 10+ | Various roles, departments |
| Vendors | 5+ | Different accreditation statuses |
| Customers | 5+ | Various credit limits |
| Items | 20+ | Raw materials, finished goods |
| Chart of Accounts | 50+ | Complete COA structure |
| Bank Accounts | 2+ | Checking, savings |
| Payroll Runs | 3+ | Different periods |
| PRs/POs | 10+ | Various statuses |
| Invoices | 10+ | AP and AR |

### 7.2 Test Transactions

Create these for comprehensive testing:

1. **Leave Requests:** 10+ (various statuses)
2. **OT Requests:** 10+ (pending, approved, rejected)
3. **Loans:** 5+ (different stages)
4. **Journal Entries:** 20+ (draft, submitted, posted)
5. **Invoices:** 20+ (various due dates)
6. **Production Orders:** 5+ (planned to completed)

---

## 8. Test Sign-off Sheet

### Module Testing

| Module | Tester | Date | Status | Notes |
|--------|--------|------|--------|-------|
| HR - Employee | | | ☐ Pass ☐ Fail | |
| HR - Attendance | | | ☐ Pass ☐ Fail | |
| HR - Leave | | | ☐ Pass ☐ Fail | |
| HR - Loan | | | ☐ Pass ☐ Fail | |
| Payroll | | | ☐ Pass ☐ Fail | |
| Accounting - GL | | | ☐ Pass ☐ Fail | |
| Accounting - AP | | | ☐ Pass ☐ Fail | |
| Accounting - AR | | | ☐ Pass ☐ Fail | |
| Banking | | | ☐ Pass ☐ Fail | |
| Procurement | | | ☐ Pass ☐ Fail | |
| Inventory | | | ☐ Pass ☐ Fail | |
| Production | | | ☐ Pass ☐ Fail | |
| QC | | | ☐ Pass ☐ Fail | |
| Fixed Assets | | | ☐ Pass ☐ Fail | |
| Budget | | | ☐ Pass ☐ Fail | |
| ISO/Docs | | | ☐ Pass ☐ Fail | |

### Workflow Testing

| Workflow | Tester | Date | Status | Notes |
|----------|--------|------|--------|-------|
| PR to Payment | | | ☐ Pass ☐ Fail | |
| Employee to Payroll | | | ☐ Pass ☐ Fail | |
| Production to AR | | | ☐ Pass ☐ Fail | |
| Loan Application | | | ☐ Pass ☐ Fail | |
| Leave Request | | | ☐ Pass ☐ Fail | |

### SoD Testing

| SoD Rule | Tester | Date | Status | Notes |
|----------|--------|------|--------|-------|
| SOD-001 Employee Activation | | | ☐ Pass ☐ Fail | |
| SOD-002 Leave Approval | | | ☐ Pass ☐ Fail | |
| SOD-003 OT Approval | | | ☐ Pass ☐ Fail | |
| SOD-004 Loan Approval | | | ☐ Pass ☐ Fail | |
| SOD-005/006 Payroll | | | ☐ Pass ☐ Fail | |
| SOD-007 Journal Entry | | | ☐ Pass ☐ Fail | |
| SOD-008 Invoice Approval | | | ☐ Pass ☐ Fail | |
| SOD-009 Bank Reconciliation | | | ☐ Pass ☐ Fail | |
| SOD-010 PR/PO Approval | | | ☐ Pass ☐ Fail | |

### Role Access Testing

| Role | Tester | Date | Status | Notes |
|------|--------|------|--------|-------|
| Admin | | | ☐ Pass ☐ Fail | |
| Super Admin | | | ☐ Pass ☐ Fail | |
| Executive | | | ☐ Pass ☐ Fail | |
| VP | | | ☐ Pass ☐ Fail | |
| Manager | | | ☐ Pass ☐ Fail | |
| Officer | | | ☐ Pass ☐ Fail | |
| Head | | | ☐ Pass ☐ Fail | |
| Staff | | | ☐ Pass ☐ Fail | |

### Final Sign-off

**Lead Tester:** _________________ **Date:** _________________

**QA Manager:** _________________ **Date:** _________________

**Project Manager:** _________________ **Date:** _________________

**Overall Status:** ☐ PASS ☐ FAIL ☐ CONDITIONAL PASS

**Notes:**
```
















```

---

## Appendix A: Quick Test Credentials

```
Admin:        admin@ogamierp.local / Admin@1234567890!
Super Admin:  superadmin@ogamierp.local / SuperAdmin@12345!
VP:           vp@ogamierp.local / VicePresident@1!
HR Manager:   hr.manager@ogamierp.local / HrManager@1234!
Plant Mgr:    plant.manager@ogamierp.local / Manager@12345!
Acctg Officer: acctg.officer@ogamierp.local / AcctgManager@1234!
GA Officer:   ga.officer@ogamierp.local / Officer@12345!
WH Head:      warehouse.head@ogamierp.local / Head@123456789!
Prod Head:    production.head@ogamierp.local / Head@123456789!
Staff:        prod.staff@ogamierp.local / Staff@123456789!
```

## Appendix B: Common Issues

| Issue | Solution |
|-------|----------|
| "SoD violation" | Normal - someone else must approve |
| "403 Forbidden" | Check role permissions |
| "Department not found" | User not assigned to department |
| "Record not found" | Check department scoping |
| Rate limit | Wait 60 seconds |
| Session expired | Log in again |

---

**End of Manual Testing Guide**
