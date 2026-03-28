# Ogami ERP — Complete Real-Life Testing & Demo Guide

**Comprehensive Guide for Understanding and Demonstrating All 20 ERP Modules**

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [System Overview](#system-overview)
3. [Test Accounts Reference](#test-accounts-reference)
4. [Module-by-Module Demo Guide](#module-by-module-demo-guide)
5. [End-to-End Workflows](#end-to-end-workflows)
6. [New Workflow Automations](#new-workflow-automations)
7. [Troubleshooting](#troubleshooting)

---

## Quick Start

### Prerequisites
```bash
# 1. Start all services
npm run dev          # Frontend (port 5173)
php artisan serve    # Backend (port 8000)

# 2. Ensure database is seeded
php artisan migrate:fresh --seed

# 3. Access the application
URL: http://localhost:5173
```

### Default Login Credentials

| Role | Email | Password | Department |
|------|-------|----------|------------|
| **Super Admin** | superadmin@ogamierp.local | SuperAdmin@12345! | — |
| **Admin** | admin@ogamierp.local | Admin@12345! | — |
| **VP** | vp@ogamierp.local | VicePresident@1! | EXEC |
| **Executive** | president@ogamierp.local | Executive@12345! | EXEC |
| **HR Manager** | hr.manager@ogamierp.local | Manager@12345! | HR |
| **Accounting Manager** | acctg.manager@ogamierp.local | Manager@12345! | ACCTG |
| **Production Manager** | prod.manager@ogamierp.local | Manager@12345! | PROD |
| **QC Manager** | qc.manager@ogamierp.local | Manager@12345! | QC |
| **Plant Manager** | plant.manager@ogamierp.local | Manager@12345! | PLANT |
| **Purchasing Officer** | purchasing.officer@ogamierp.local | Officer@12345! | PURCH |
| **Accounting Officer** | accounting@ogamierp.local | Officer@12345! | ACCTG |
| **HR Officer** | hr.officer@ogamierp.local | Officer@12345! | HR |
| **QC Officer** | qc.officer@ogamierp.local | Officer@12345! | QC |
| **Sales Officer** | sales.officer@ogamierp.local | Officer@12345! | SALES |
| **Warehouse Head** | warehouse.head@ogamierp.local | Head@123456789! | WH |
| **Production Head** | production.head@ogamierp.local | Head@123456789! | PROD |
| **Maintenance Head** | maintenance.head@ogamierp.local | Head@123456789! | MAINT |
| **Production Staff** | prod.staff@ogamierp.local | Staff@123456789! | PROD |
| **Warehouse Staff** | wh.staff@ogamierp.local | Staff@123456789! | WH |

---

## System Overview

### 20 ERP Modules

| Module | Key Features | Users |
|--------|-------------|-------|
| **HR** | Employee management, departments, positions, salary grades | HR Manager |
| **Attendance** | Time logs, shift schedules, overtime requests | All employees |
| **Leave** | Leave requests, balances, approvals | All employees |
| **Payroll** | Payroll runs, government contributions, payslips | HR Manager |
| **Loans** | Employee loans, amortization schedules | HR, Accounting |
| **Accounting** | Chart of accounts, journal entries, GL | Accounting |
| **AP** | Vendors, invoices, payments | Accounting |
| **AR** | Customers, invoices, receipts | Accounting, Sales |
| **Tax** | VAT ledger, BIR filings | Accounting |
| **Budget** | Cost centers, annual budgets, utilization | Accounting |
| **Inventory** | Items, stock, requisitions, adjustments | Warehouse |
| **Procurement** | Purchase requests, orders, goods receipts | Purchasing |
| **Production** | BOMs, production orders, delivery schedules | Production |
| **QC** | Inspections, NCRs, CAPA | QC |
| **Maintenance** | Equipment, PM schedules, work orders | Maintenance |
| **Mold** | Mold tracking, shot counts | Production |
| **Delivery** | Delivery receipts, shipments, vehicles | Warehouse |
| **ISO** | Documents, audits, findings | QC |
| **CRM** | Support tickets, customer portal | Sales |

### Automated Workflows (19 Total)

| # | Automation | Trigger | Result |
|---|-----------|---------|--------|
| 1 | **PR → PO** | PR VP-approved | Auto-create PO draft |
| 2 | **PO → MRQ** | Production Order released | Auto-create material requisition |
| 3 | **Payroll → GL** | Payroll approved | Auto-post journal entries |
| 4 | **Loan → GL** | Loan disbursed | Auto-post payable entry |
| 5 | **Invoice Payment → GL** | Payment recorded | Auto-post cash entry |
| 6 | **Credit Note → GL** | CN created | Auto-post adjustment |
| 7 | **Low Stock Alerts** | Daily 07:00 AM | Notify purchasing |
| 8 | **PM Work Orders** | Daily 06:00 AM | Auto-create from schedules |
| 9 | **Mold Shot Alerts** | Daily 06:30 AM | Alert near max shots |
| 10 | **AR Overdue Alerts** | Daily 08:30 AM | Notify AR officers |
| 11 | **Auto-PR from Low Stock** | Daily 07:00 AM | Create draft PR |
| 12 | **DS → Auto Production** | DS confirmed | Auto-create PO if no stock |
| 13 | **Employee Clearance** | Employee resigned | Generate checklist |
| 14 | **Leave Auto-Accrual** | 1st of month | Accrue leave balances |
| 15 | **Stock Reservations** | PO released | Reserve materials |
| 16 | **Expire Reservations** | Daily 01:00 AM | Clean expired reservations |
| 17 | **Bank Reconciliation** | Manual | Auto-match transactions |
| 18 | **3-Way Match** | GR posted | Match GR+PO+Invoice |
| 19 | **Budget Enforcement** | PR submitted | Block if over budget |

---

## Test Accounts Reference

### Test Credentials File
Location: `storage/app/test-credentials.md`

Generated after seeding with all account details.

---

## Pre-Test Setup (Required)

Before starting the demo, ensure these are configured:

### 1. Database Seeded
```bash
php artisan migrate:fresh --seed
```

### 2. Fiscal Period (Required for all Accounting modules)
**User:** acctg.manager@ogamierp.local  
**Path:** `/accounting/fiscal-periods`

1. Create Fiscal Year 2026:
   - Name: "FY 2026"
   - Start Date: 2026-01-01
   - End Date: 2026-12-31
   - Status: Open

### 3. Cost Centers (Required for Budget module)
**User:** acctg.manager@ogamierp.local  
**Path:** `/budget/cost-centers`

1. Create at least one cost center:
   - Code: CC-PROD-001
   - Name: Production Department
   - Department: PROD

### 4. Chart of Accounts (Auto-seeded)
✅ Pre-loaded with standard Philippine COA

### 5. Government Rate Tables (Auto-seeded)
✅ SSS, PhilHealth, Pag-IBIG, Tax tables pre-loaded

---

## Module-by-Module Demo Guide

### MODULE 1: HR (Human Resources)

#### 1.1 View All Employees
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/employees/all`

**Demo Steps:**
1. Login as HR Manager
2. Click "Human Resources" → "All Employees"
3. Show employee list with filters:
   - Filter by department
   - Filter by employment status
   - Search by name or code
4. Click on an employee to view full profile

**Key Features to Highlight:**
- Employee code auto-generation (EMP-YYYY-NNNNNN)
- Encrypted government IDs (SSS, TIN, PhilHealth, Pag-IBIG)
- Complete employment history
- Document attachments

#### 1.2 Create New Employee
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/employees/new`

**Demo Steps:**
1. Click "New Employee"
2. Fill basic info:
   - Name: Juan Dela Cruz
   - Date Hired: 2026-03-15
   - Department: PROD
   - Position: Production Staff
   - Salary Grade: SG-05
3. Add government IDs:
   - SSS: 03-1234567-8
   - TIN: 123-456-789-000
   - PhilHealth: 12-345678901-2
   - Pag-IBIG: 1234-5678-9012
4. Save and show auto-generated employee code

#### 1.3 Employee State Transitions
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/employees/{ulid}`

**Demo Steps:**
1. Open an employee record
2. Show state machine:
   - draft → active (onboarding complete)
   - active → on_leave → active
   - active → resigned (generates clearance checklist)
3. Transition employee to "resigned"
4. Show auto-generated clearance checklist (21 items)

**Automated Feature:**
- Clearance checklist auto-created with 21 items across 5 departments

---

### MODULE 2: Attendance

#### 2.1 View Attendance Logs
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/attendance`

**Demo Steps:**
1. View daily attendance logs
2. Show filters:
   - Date range
   - Department
   - Employee
3. Click on log to view details

#### 2.2 Shift Schedules
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/shifts`

**Demo Steps:**
1. View existing shift schedules
2. Show shift assignments:
   - Day shift: 8:00 AM - 5:00 PM
   - Night shift: 10:00 PM - 6:00 AM
3. Assign shift to employee

#### 2.3 Overtime Requests
**User:** prod.staff@ogamierp.local (Request)  
**User:** production.head@ogamierp.local (Approve)  
**Path:** `/hr/overtime`

**Demo Steps:**
1. Login as Production Staff
2. Submit OT request:
   - Date: 2026-03-20
   - Hours: 4
   - Reason: Production rush
3. Login as Production Head
4. Approve OT request
5. Show approval workflow (Head → Manager)

---

### MODULE 3: Leave

#### 3.1 Submit Leave Request
**User:** prod.staff@ogamierp.local  
**Path:** `/me/leaves`

**Demo Steps:**
1. Login as Production Staff
2. Click "My Leaves"
3. Click "New Leave Request"
4. Fill form:
   - Leave Type: Vacation Leave
   - Start Date: 2026-04-01
   - End Date: 2026-04-03
   - Days: 3
   - Reason: Family vacation
5. Submit and show pending status

#### 3.2 Approve Leave Request
**User:** production.head@ogamierp.local  
**Path:** `/hr/leave`

**Demo Steps:**
1. Login as Production Head
2. View "Team Leave" section
3. Find pending request
4. Review leave balance
5. Approve request

#### 3.3 View Leave Balances
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/leave/balances`

**Demo Steps:**
1. View all employee leave balances
2. Show auto-accrual (monthly on 1st)
3. Filter by leave type

**Automated Feature:**
- Leave balances auto-accrue monthly based on tenure
- Vacation: 0.83 days/month (×1.5 after 5 years, ×2 after 10 years)
- Sick: 15 days/year reset on January 1
- SIL: 5 days on work anniversary (after 1 year)

---

### MODULE 4: Payroll

#### 4.1 Create Payroll Run
**User:** hr.manager@ogamierp.local  
**Path:** `/payroll/runs`

**Demo Steps:**
1. Click "New Payroll Run"
2. Select:
   - Pay Period: March 1-15, 2026
   - Pay Date: March 20, 2026
3. Add employees to scope
4. Run pre-validation
5. Compute payroll

#### 4.2 Review Payroll Computations
**User:** hr.manager@ogamierp.local  
**Path:** `/payroll/runs/{ulid}`

**Demo Steps:**
1. View computed payroll details
2. Show breakdown per employee:
   - Basic Pay
   - Overtime Pay
   - Gross Pay
   - SSS Contribution (EE + ER)
   - PhilHealth (EE + ER)
   - Pag-IBIG (EE + ER)
   - Withholding Tax
   - Net Pay
3. Show 17-step computation pipeline

#### 4.3 Approve and Post Payroll
**User:** hr.manager@ogamierp.local (HR Approval)  
**User:** acctg.manager@ogamierp.local (Accounting Approval)  
**User:** vp@ogamierp.local (VP Approval)  
**Path:** `/payroll/runs/{ulid}`

**Demo Steps:**
1. HR Manager submits for approval
2. Accounting Manager reviews and approves
3. VP gives final approval
4. System auto-posts to GL

**Automated Feature:**
- Payroll auto-posts to General Ledger upon approval
- Creates JE: DR Salaries Expense, CR Cash/Payables

---

### MODULE 5: Loans

#### 5.1 Apply for Loan
**User:** prod.staff@ogamierp.local  
**Path:** `/me/loans`

**Demo Steps:**
1. Click "My Loans"
2. Click "Apply for Loan"
3. Fill form:
   - Loan Type: SSS Salary Loan
   - Amount: ₱25,000
   - Terms: 24 months
4. Submit application

#### 5.2 Process Loan Application
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/loans`

**Demo Steps:**
1. View pending loan applications
2. Check employee eligibility
3. Review loan history
4. Approve loan
5. System generates amortization schedule

#### 5.3 Loan Disbursement
**User:** hr.manager@ogamierp.local  
**Path:** `/hr/loans/{ulid}`

**Demo Steps:**
1. Process disbursement
2. Show amortization schedule
3. **Automated:** Loan posts to GL
   - DR Loans Receivable
   - CR Cash in Bank

---

### MODULE 6: Accounting (Chart of Accounts & Journal Entries)

#### 6.1 View Chart of Accounts
**User:** acctg.manager@ogamierp.local  
**Path:** `/accounting/accounts`

**Demo Steps:**
1. View hierarchical chart of accounts
2. Show account types:
   - Assets (1000-1999)
   - Liabilities (2000-2999)
   - Equity (3000-3999)
   - Revenue (4000-4999)
   - Expenses (5000-5999)
3. Click on account to view ledger

#### 6.2 Create Journal Entry
**User:** acctg.manager@ogamierp.local  
**Path:** `/accounting/journal-entries/new`

**Demo Steps:**
1. Click "New Journal Entry"
2. Fill header:
   - Date: 2026-03-15
   - Description: Monthly depreciation
3. Add lines:
   - DR: Depreciation Expense 5000 - ₱5,000
   - CR: Accumulated Depreciation 1900 - ₱5,000
4. Verify balanced (DR = CR)
5. Submit for approval

### MODULE 7: Accounts Payable (AP)

#### 7.1 Setup Vendors
**User:** acctg.manager@ogamierp.local  
**Path:** `/accounting/vendors`

**Demo Steps:**
1. Click "New Vendor"
2. Fill vendor profile:
   - Code: VEND-001
   - Name: ABC Plastics Supply
   - TIN: 123-456-789-000
   - Payment Terms: Net 30
   - Credit Limit: ₱500,000
3. Add vendor items with prices

#### 7.2 Create Vendor Invoice
**User:** acctg.officer@ogamierp.local  
**Path:** `/accounting/ap/invoices`

**Demo Steps:**
1. Click "New Invoice"
2. Select vendor
3. Link to Goods Receipt (3-way match)
4. Add line items:
   - Item: RM-PP-001
   - Qty: 1000 KG
   - Price: ₱85.00
5. Add VAT (12%)
6. Submit for approval

#### 7.3 Vendor Payment
**User:** acctg.manager@ogamierp.local  
**Path:** `/accounting/ap/invoices`

**Demo Steps:**
1. View approved invoices
2. Select for payment
3. Choose bank account
4. Process payment
5. **Automated:** Payment posts to GL
   - DR Accounts Payable
   - CR Cash in Bank

---

### MODULE 8: Accounts Receivable (AR)

#### 8.1 Setup Customers
**User:** acctg.manager@ogamierp.local  
**Path:** `/ar/customers`

**Demo Steps:**
1. Click "New Customer"
2. Fill customer profile:
   - Code: CUST-001
   - Name: Toyota Motor Philippines
   - TIN: 345-678-901-002
   - Payment Terms: Net 45
   - Credit Limit: ₱2,000,000

#### 8.2 Create Customer Invoice
**User:** acctg.officer@ogamierp.local  
**Path:** `/ar/invoices`

**Demo Steps:**
1. Click "New Invoice"
2. Select customer
3. Add line items:
   - Item: FG-DASH-001
   - Qty: 180 PCS
   - Price: ₱1,500.00
4. Add VAT (12%)
5. Submit for VP approval

#### 8.3 Record Customer Payment
**User:** acctg.officer@ogamierp.local  
**Path:** `/ar/invoices`

**Demo Steps:**
1. Find invoice
2. Click "Record Payment"
3. Enter:
   - Amount: ₱302,400.00
   - Date: 2026-04-15
   - Reference: Check #12345
4. Save
5. **Automated:** Payment posts to GL

---

### MODULE 9: Tax

#### 9.1 View VAT Ledger
**User:** acctg.manager@ogamierp.local  
**Path:** `/accounting/vat-ledger`

**Demo Steps:**
1. View VAT transactions
2. Filter by period
3. Show:
   - Input VAT (from purchases)
   - Output VAT (from sales)
   - VAT Payable/(Creditable)

#### 9.2 BIR Reports
**User:** acctg.manager@ogamierp.local  
**Path:** `/reports/government`

**Demo Steps:**
1. Generate BIR reports:
   - 2550M (Monthly VAT)
   - 1601C (Withholding Tax)
   - Alphalist
2. Export for filing

---

### MODULE 10: Budget

> **⚠️ Setup Required:** Before using Budget, you must create Cost Centers first. See setup steps below.

#### 10.1 Create Cost Centers
**User:** acctg.manager@ogamierp.local  
**Path:** `/budget/cost-centers`

**Demo Steps:**
1. View existing cost centers
2. Create new cost center:
   - Code: CC-PROD-001
   - Name: Production Line 1
   - Department: PROD

#### 10.2 Set Annual Budget
**User:** acctg.manager@ogamierp.local  
**Path:** `/budget/lines`

**Demo Steps:**
1. Select fiscal year: 2026
2. Add budget lines:
   - Raw Materials: ₱10,000,000
   - Labor: ₱5,000,000
   - Overhead: ₱2,000,000
3. Save budget

#### 10.3 Budget vs Actual Report
**User:** acctg.manager@ogamierp.local  
**Path:** `/budget/vs-actual`

**Demo Steps:**
1. Generate BvA report
2. Show variance analysis
3. Highlight over-budget items

---

### MODULE 11: Inventory

#### 11.1 Item Master
**User:** warehouse.head@ogamierp.local  
**Path:** `/inventory/items`

**Demo Steps:**
1. View item list
2. Show item details:
   - Raw Materials
   - Finished Goods
   - Packaging
3. Set reorder points
4. Show low stock alerts

#### 11.2 Stock Balances
**User:** warehouse.head@ogamierp.local  
**Path:** `/inventory/stock`

**Demo Steps:**
1. View stock by location
2. Show columns:
   - On Hand
   - Reserved
   - Available (On Hand - Reserved)
3. Filter by low stock
4. **Automated:** Auto-PR created for low stock items

#### 11.3 Material Requisition
**User:** production.head@ogamierp.local  
**Path:** `/inventory/requisitions`

**Demo Steps:**
1. Create MR for production
2. Add items needed
3. Submit for approval
4. Warehouse fulfills MR

#### 11.4 Stock Adjustments
**User:** warehouse.head@ogamierp.local  
**Path:** `/inventory/adjustments`

**Demo Steps:**
1. Create adjustment (damaged goods)
2. Enter:
   - Item: RM-PP-001
   - Adjustment: -50 KG
   - Reason: Damaged in storage
3. Submit for approval

---

### MODULE 12: Procurement

#### 12.1 Purchase Request (PR)
**User:** purchasing.officer@ogamierp.local  
**Path:** `/procurement/purchase-requests`

**Demo Steps:**
1. Click "New PR"
2. Add items needed:
   - RM-PP-001: 1000 KG
   - RM-ABS-001: 500 KG
3. Submit for approval chain:
   - Head → Manager → Officer → VP

#### 12.2 Purchase Order (PO)
**User:** purchasing.officer@ogamierp.local  
**Path:** `/procurement/purchase-orders`

**Demo Steps:**
1. **Automated:** PO created from approved PR
2. Review auto-created PO
3. Assign vendor
4. Send to vendor

#### 12.3 Goods Receipt (GR)
**User:** warehouse.head@ogamierp.local  
**Path:** `/procurement/goods-receipts`

**Demo Steps:**
1. Receive delivery
2. Create GR from PO
3. Enter received quantities
4. Post to inventory
5. **Automated:** 3-way match check

---

### MODULE 13: Production

#### 13.1 Bill of Materials (BOM)
**User:** prod.manager@ogamierp.local  
**Path:** `/production/boms`

**Demo Steps:**
1. Create BOM:
   - Product: FG-DASH-001
   - Version: 1.0
2. Add components:
   - RM-PP-001: 2.5 KG
   - RM-ABS-001: 1.0 KG
   - PKG-BOX-001: 1 PCS
3. Add scrap percentages
4. Activate BOM

#### 13.2 Delivery Schedule
**User:** prod.manager@ogamierp.local  
**Path:** `/production/delivery-schedules`

**Demo Steps:**
1. Create delivery schedule:
   - Customer: Toyota
   - Product: FG-DASH-001
   - Qty: 200 PCS
   - Delivery Date: 2026-03-30
2. Change status to "Confirmed"
3. **Automated:** PO created if insufficient stock

#### 13.3 Production Order
**User:** prod.manager@ogamierp.local  
**Path:** `/production/orders`

**Demo Steps:**
1. View auto-created PO
2. Check material availability
3. Release to production
4. **Automated:** Materials reserved, MRQ created
5. Record production output
6. Complete order

---

### MODULE 14: Quality Control (QC)

#### 14.1 Incoming Inspection
**User:** qc.manager@ogamierp.local  
**Path:** `/qc/inspections`

**Demo Steps:**
1. Create inspection for received materials
2. Add inspection points:
   - Visual check
   - Dimension check
   - Weight verification
3. Record results
4. Pass/Fail decision

#### 14.2 In-Process Inspection
**User:** qc.officer@ogamierp.local  
**Path:** `/qc/inspections`

**Demo Steps:**
1. Inspect production output
2. Record sample size
3. Add measurements
4. Calculate acceptance

#### 14.3 Non-Conformance Report (NCR)
**User:** qc.manager@ogamierp.local  
**Path:** `/qc/ncrs`

**Demo Steps:**
1. Create NCR for defects:
   - Defect Type: Surface scratches
   - Quantity: 20 PCS
   - Severity: Minor
2. Add root cause analysis
3. Define corrective action
4. Define preventive action
5. Close NCR

#### 14.4 CAPA (Corrective and Preventive Action)
**User:** qc.manager@ogamierp.local  
**Path:** `/qc/capa`

**Demo Steps:**
1. Create CAPA from NCR
2. Assign action items
3. Set due dates
4. Track completion
5. Verify effectiveness

---

### MODULE 15: Maintenance

#### 15.1 Equipment Register
**User:** maintenance.head@ogamierp.local  
**Path:** `/maintenance/equipment`

**Demo Steps:**
1. View equipment list
2. Add new equipment:
   - Code: MACH-001
   - Name: Injection Molding Machine #1
   - Location: Production Floor A
   - Criticality: High
3. Set PM schedule

#### 15.2 Preventive Maintenance (PM)
**User:** maintenance.head@ogamierp.local  
**Path:** `/maintenance/work-orders`

**Demo Steps:**
1. View PM schedule
2. **Automated:** PM work orders auto-generated daily at 06:00 AM
3. Assign technician
4. Record completion
5. Update equipment history

#### 15.3 Work Orders
**User:** maintenance.head@ogamierp.local  
**Path:** `/maintenance/work-orders`

**Demo Steps:**
1. Create corrective work order
2. Add spare parts used
3. Record labor hours
4. Close work order

---

### MODULE 16: Mold

#### 16.1 Mold Master
**User:** prod.manager@ogamierp.local  
**Path:** `/mold/masters`

**Demo Steps:**
1. Register mold:
   - Code: MOLD-001
   - Description: Dashboard Panel Mold
   - Cavities: 2
   - Max Shots: 100,000
2. Set maintenance schedule

#### 16.2 Shot Count Tracking
**User:** prod.staff@ogamierp.local  
**Path:** `/production/orders`

**Demo Steps:**
1. Log production output
2. System tracks mold shots
3. **Automated:** Alert when approaching max shots
4. Trigger mold maintenance

---

### MODULE 17: Delivery

#### 17.1 Delivery Receipts
**User:** warehouse.head@ogamierp.local  
**Path:** `/delivery/receipts`

**Demo Steps:**
1. Create delivery receipt
2. Link to customer invoice
3. Assign vehicle and driver
4. Record delivery
5. Customer signs receipt

#### 17.2 Shipments
**User:** warehouse.head@ogamierp.local  
**Path:** `/delivery/shipments`

**Demo Steps:**
1. View shipment schedule
2. Track delivery status
3. Record delivery confirmation
4. Handle returns if any

---

### MODULE 18: ISO

#### 18.1 Document Control
**User:** qc.manager@ogamierp.local  
**Path:** `/iso/documents`

**Demo Steps:**
1. View controlled documents
2. Upload new document:
   - Type: Work Instruction
   - Department: Production
   - Version: 1.0
3. Set review date
4. Distribute to users

#### 18.2 Internal Audits
**User:** qc.manager@ogamierp.local  
**Path:** `/iso/audits`

**Demo Steps:**
1. Schedule internal audit
2. Assign auditors
3. Record findings
4. Track corrective actions

---

### MODULE 19: CRM

#### 19.1 Support Tickets
**User:** sales.officer@ogamierp.local  
**Path:** `/crm/tickets`

**Demo Steps:**
1. View customer tickets
2. Create ticket:
   - Customer: Toyota
   - Issue: Delivery delay
   - Priority: High
3. Assign to team
4. Track resolution

#### 19.2 CRM Dashboard
**User:** sales.manager@ogamierp.local  
**Path:** `/crm/dashboard`

**Demo Steps:**
1. View ticket statistics
2. Show resolution times
3. Customer satisfaction metrics

---

## End-to-End Workflows

### WORKFLOW 1: Complete Procurement Cycle

**Participants:** Purchasing Officer → Department Head → Accounting Officer → VP → Warehouse

**Flow:**
1. **Low Stock Alert** (Automated at 07:00 AM)
   - System detects RM-PP-001 below reorder point
   - Auto-creates draft PR

2. **Purchase Request**
   - Purchasing Officer reviews and submits PR
   - Goes through approval chain (Head → Manager → Officer)

3. **Budget Check** (Automated)
   - System verifies against department budget
   - Blocks if over budget

4. **VP Approval**
   - VP gives final approval
   - **Automated:** PO created from PR

5. **Purchase Order**
   - Purchasing Officer assigns vendor
   - Sends PO to vendor

6. **Goods Receipt**
   - Warehouse receives delivery
   - Creates GR, posts to inventory
   - **Automated:** 3-way match verification

7. **Vendor Invoice**
   - Accounting creates invoice from GR
   - VP approves
   - Posts to GL

8. **Payment**
   - Accounting processes payment
   - **Automated:** Payment posts to GL

---

### WORKFLOW 2: Production to Delivery

**Participants:** Production Manager → Warehouse → QC → Accounting → VP

**Flow:**
1. **Delivery Schedule**
   - Customer orders 200 PCS dashboard panels
   - Production creates DS
   - Change status to "Confirmed"
   - **Automated:** PO created if insufficient stock

2. **Production Order**
   - Review auto-created PO
   - Check BOM: RM-PP-001 (2.5 KG), RM-ABS-001 (1.0 KG)
   - Release to production
   - **Automated:** Materials reserved, MRQ created

3. **Material Requisition**
   - Warehouse fulfills MRQ
   - Issues materials to production floor
   - Stock deducted

4. **Production Execution**
   - Record daily output
   - Log rejected items

5. **Quality Control**
   - QC performs final inspection
   - 180 PCS pass, 20 PCS rejected
   - Create NCR for rejects

6. **Goods Receipt (FG)**
   - 180 PCS received to WH-B1
   - Stock updated

7. **Customer Invoice**
   - Accounting creates invoice
   - VP approves
   - Posts to GL

8. **Delivery**
   - Warehouse creates DR
   - Delivers to customer
   - Customer signs receipt

---

### WORKFLOW 3: Employee Separation

**Participants:** HR Manager → IT → Finance → Department → Warehouse

**Flow:**
1. **Resignation**
   - Employee submits resignation
   - HR Manager processes
   - Change status to "resigned"
   - **Automated:** Clearance checklist created (21 items)

2. **Clearance Process**
   | Department | Items |
   |------------|-------|
   | IT | Laptop return, account revocation |
   | HR | ID return, exit interview |
   | Finance | Loans cleared, final pay computed |
   | Department | Handover completed |
   | Warehouse | Materials accountability cleared |

3. **Final Pay Release**
   - System blocks until all clearance items cleared
   - Once cleared, final pay released

---

## New Workflow Automations

### Recently Implemented (March 18, 2026)

| # | Feature | Command | Schedule |
|---|---------|---------|----------|
| 11 | Auto-PR from Low Stock | `inventory:check-reorder-points --auto-create-pr` | Daily 07:00 AM |
| 12 | DS → Auto Production | — | On DS confirm |
| 13 | Employee Clearance | — | On resign/terminate |
| 14 | Leave Auto-Accrual | `leave:accrue-balances` | Monthly 1st 02:00 AM |
| 15 | Stock Reservations | — | On PO release |
| 16 | Expire Reservations | `inventory:expire-reservations` | Daily 01:00 AM |

---

## Troubleshooting

### Common Issues

**1. Can't login**
- Check if user exists: `php artisan tinker` → `User::where('email', 'xxx')->first()`
- Reset password in database

**2. Sidebar menu not showing**
- Check role/permissions: User must have required role
- Check department: Some modules department-scoped

**3. Workflow not triggering**
- Check scheduler: `php artisan schedule:list`
- Run manually: `php artisan schedule:run`

**4. Stock not updating**
- Check Goods Receipt is posted
- Verify stock ledger entries

**5. Payroll computation errors**
- Verify government contribution tables are seeded
- Check employee salary grade assignment

### Useful Commands

```bash
# Check scheduler
php artisan schedule:list

# Run specific command
php artisan inventory:check-reorder-points --auto-create-pr --dry-run
php artisan leave:accrue-balances --dry-run
php artisan assets:depreciate-monthly

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Check database
php artisan migrate:status

# View logs
tail -f storage/logs/laravel.log
```

---

## Demo Tips for Professor

### Opening (2 minutes)
1. Login as Super Admin
2. Show dashboard with all modules
3. Explain RBAC: 7 roles, department-scoped access

### Key Highlights (10 minutes)
1. **SoD Enforcement**: Same user cannot create AND approve
2. **Workflow Automation**: Show auto-PR from low stock
3. **3-Way Match**: GR + PO + Invoice matching
4. **Payroll Pipeline**: 17-step computation with auto-GL posting
5. **Employee Clearance**: Auto-generated checklist on resignation

### Live Demo (15 minutes)
1. Create Purchase Request → Watch auto-approve flow
2. Release Production Order → See material reservation
3. Run Payroll → Show auto-GL entries
4. Transition Employee to Resigned → Show clearance checklist

### Closing (3 minutes)
1. Show financial reports (Trial Balance, Income Statement)
2. Highlight 19 automated workflows
3. Emphasize audit trail (all actions logged)

---

**Document Version:** 2.0  
**Last Updated:** March 18, 2026  
**Modules Covered:** All 19 ERP Modules  
**Workflows Documented:** 19 Automated Workflows
