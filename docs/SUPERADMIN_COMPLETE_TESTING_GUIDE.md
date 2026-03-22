# Ogami ERP — SuperAdmin Complete Testing Guide

**Comprehensive Real-Life Testing Guide for SuperAdmin Users**  
*Test Every Module, Feature, and Workflow in the System*

---

## 📋 Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication & User Management](#1-authentication--user-management)
3. [HR Module](#2-hr-module)
4. [Attendance Module](#3-attendance-module)
5. [Leave Module](#4-leave-module)
6. [Payroll Module](#5-payroll-module)
7. [Loan Module](#6-loan-module)
8. [Accounting Module](#7-accounting-module)
9. [Accounts Payable (AP)](#8-accounts-payable-ap)
10. [Accounts Receivable (AR)](#9-accounts-receivable-ar)
11. [Tax Module](#10-tax-module)
12. [Budget Module](#11-budget-module)
13. [Fixed Assets Module](#12-fixed-assets-module)
14. [Inventory Module](#13-inventory-module)
15. [Procurement Module](#14-procurement-module)
16. [Production Module](#15-production-module)
17. [Quality Control (QC)](#16-quality-control-qc)
18. [Maintenance Module](#17-maintenance-module)
19. [Mold Module](#18-mold-module)
20. [Delivery Module](#19-delivery-module)
21. [ISO Module](#20-iso-module)
22. [CRM Module](#21-crm-module)
23. [Reports & Analytics](#22-reports--analytics)
24. [System Administration](#23-system-administration)
25. [End-to-End Workflows](#24-end-to-end-workflows)
26. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Prerequisites
```bash
# 1. Ensure all services are running
npm run dev              # Frontend (port 5173)
php artisan serve        # Backend (port 8000)
redis-server             # Redis for queues

# 2. Fresh database with seed data
php artisan migrate:fresh --seed

# 3. Queue worker (for payroll, notifications)
php artisan queue:work --queue=default,payroll,notifications
```

### SuperAdmin Login
| Field | Value |
|-------|-------|
| **Email** | `superadmin@ogamierp.local` |
| **Password** | `SuperAdmin@12345!` |
| **URL** | `http://localhost:5173` |

> **Note:** SuperAdmin has ALL permissions and bypasses all SoD (Segregation of Duties) checks.

---

## 1. Authentication & User Management

### 1.1 Login/Logout
- [ ] **Test:** Login with SuperAdmin credentials
- [ ] **Verify:** Dashboard loads with all sidebar menu items visible
- [ ] **Test:** Logout and verify redirect to login page
- [ ] **Test:** Attempt access to protected route while logged out (should redirect to login)

### 1.2 Profile Management
**Path:** `/profile`
- [ ] View profile information
- [ ] Update display name
- [ ] Change password (test with valid and invalid current password)
- [ ] Update notification preferences

### 1.3 User Management (Admin)
**Path:** `/admin/users`
- [ ] **Create User:**
  - Name: `Test User`
  - Email: `testuser@ogamierp.local`
  - Department: HR
  - Role: staff
- [ ] **Edit User:** Change department to ACCTG
- [ ] **Assign Roles:** Add `manager` role
- [ ] **Deactivate User:** Toggle active status
- [ ] **Impersonate User:** Click impersonate and verify limited permissions
- [ ] **Delete User:** Remove test user

### 1.4 Role & Permission Testing
**Path:** `/admin/roles`
- [ ] View all roles (admin, executive, vice_president, manager, officer, head, staff)
- [ ] View permissions for each role
- [ ] Test role assignment to users

### 1.5 Department Scoping Verification
- [ ] Login as `hr.officer@ogamierp.local` / `Officer@Test1234!`
- [ ] Verify only HR module is accessible
- [ ] Login back as SuperAdmin
- [ ] Verify ALL departments are accessible

---

## 2. HR Module

### 2.1 Dashboard
**Path:** `/hr/dashboard`
- [ ] View employee statistics (total, active, on leave, resigned)
- [ ] Verify chart displays correctly
- [ ] Test date range filters

### 2.2 Employees
**Path:** `/hr/employees`

#### List View
- [ ] View all employees (should show all departments as SuperAdmin)
- [ ] Search by name: `Garcia`
- [ ] Filter by department: HR
- [ ] Filter by status: Active
- [ ] Export to Excel

#### Create Employee
- [ ] Click "New Employee"
- [ ] Fill required fields:
  - Employee Code: (auto-generated or manual)
  - First Name: `Maria`
  - Last Name: `Santos`
  - Department: HR
  - Position: HR Officer
  - Salary Grade: SG-11
  - Date Hired: Today
  - Employment Status: regular
  - Basic Monthly Rate: ₱25,000
- [ ] **TIN Format Test:** Enter `123456789012` → should auto-format to `123-456-789-012`
- [ ] **Phone Format Test:** Enter `09171234567` → should auto-format to `0917 123 4567`
- [ ] Save and verify success message

#### View/Edit Employee
- [ ] Click on employee name
- [ ] Verify all tabs load:
  - Personal Info
  - Employment Details
  - Government IDs (TIN, SSS, PhilHealth, Pag-IBIG)
  - Documents
  - Audit Trail
- [ ] Edit basic salary
- [ ] Upload document (PDF or image)
- [ ] View audit trail changes

#### Employee Transition
- [ ] Select employee → Actions → Transition
- [ ] Test transitions:
  - Active → On Leave
  - Active → Suspended
  - Active → Resigned (set separation date)

### 2.3 Departments
**Path:** `/hr/departments`
- [ ] View all 13 departments
- [ ] Create new department:
  - Code: `RND`
  - Name: `Research & Development`
- [ ] Edit department head
- [ ] Verify department appears in employee dropdown

### 2.4 Positions
**Path:** `/hr/positions`
- [ ] List all positions
- [ ] Create position:
  - Title: `Senior Developer`
  - Department: IT
  - Salary Grade: SG-18
- [ ] Link to department

### 2.5 Salary Grades
**Path:** `/hr/salary-grades`
- [ ] View salary grade table (SG-1 to SG-33)
- [ ] Verify step increments display correctly
- [ ] Search by grade number

---

## 3. Attendance Module

### 3.1 Dashboard
**Path:** `/attendance/dashboard`
- [ ] View today's attendance summary
- [ ] Check late arrivals count
- [ ] View overtime summary

### 3.2 Attendance Logs
**Path:** `/attendance/logs`
- [ ] View logs for current date
- [ ] Filter by employee
- [ ] Filter by date range (last 7 days)
- [ ] Export logs
- [ ] Manually add attendance entry:
  - Employee: Select from dropdown
  - Date: Yesterday
  - Time In: 08:00
  - Time Out: 17:00
  - Status: present

### 3.3 Shift Schedules
**Path:** `/team/shifts` (Team Management)
- [ ] View all shift schedules
- [ ] Create new shift:
  - Name: `Night Shift`
  - Time In: 22:00
  - Time Out: 06:00
  - Break Duration: 60 minutes
  - Grace Period: 15 minutes
- [ ] Assign shift to employee
- [ ] Test shift override for specific date

### 3.4 Overtime Requests
**Path:** `/team/overtime` (Team Management)
- [ ] View pending overtime requests
- [ ] Create overtime request:
  - Employee: Select active employee
  - Date: Tomorrow
  - Start Time: 17:00
  - End Time: 20:00
  - Reason: `Inventory month-end`
  - Type: regular (or rest_day, holiday)
- [ ] Approve overtime request
- [ ] Verify overtime hours calculation

### 3.5 Team Attendance
**Path:** `/team/attendance`
- [ ] View team attendance calendar
- [ ] Check employee attendance patterns
- [ ] Identify habitual latecomers

---

## 4. Leave Module

### 4.1 Leave Balances
**Path:** `/leave/balances`
- [ ] View own leave balances
- [ ] As SuperAdmin, view all employee balances
- [ ] Verify leave types:
  - Vacation Leave (VL)
  - Sick Leave (SL)
  - Emergency Leave
  - Maternity/Paternity Leave

### 4.2 Leave Requests
**Path:** `/leave/requests`

#### Submit Leave Request
- [ ] Create new leave request:
  - Leave Type: Vacation Leave
  - Start Date: Next week Monday
  - End Date: Next week Wednesday
  - Days: 3
  - Reason: `Family vacation`
- [ ] Verify leave balance deduction (if approved)

#### Approve/Reject Leave
- [ ] View pending leave requests
- [ ] Approve one request
- [ ] Reject another with reason: `Peak season, insufficient staffing`
- [ ] Verify SoD: Cannot approve own request

### 4.3 Team Leave Calendar
**Path:** `/team/leave`
- [ ] View calendar with approved leaves
- [ ] Filter by department
- [ ] Check department coverage

### 4.4 Leave Reports
- [ ] Generate leave utilization report
- [ ] View employees with negative balances
- [ ] Check leave without pay (LWOP) summary

---

## 5. Payroll Module

### 5.1 Payroll Dashboard
**Path:** `/payroll/dashboard`
- [ ] View upcoming payroll schedule
- [ ] Check last payroll summary
- [ ] View pending approvals count

### 5.2 Payroll Runs
**Path:** `/payroll/runs`

#### Create Payroll Run
- [ ] Click "New Payroll Run"
- [ ] Select:
  - Year: 2026
  - Month: March
  - Period: 1st Half (1-15) or 2nd Half (16-30/31)
  - Payroll Type: Regular
- [ ] Set scope (All Departments or specific)
- [ ] Save as Draft

#### Payroll Processing Steps
1. **Set Scope** → Select employees
2. **Pre-Run Check** → Verify no missing data
3. **Process** → System computes all amounts
4. **Review** → Check computed payroll
5. **Submit** → For HR approval
6. **HR Approve** → HR manager review
7. **Accounting Approve** → Final approval
8. **Disburse** → Mark as paid
9. **Publish** → Release payslips

- [ ] Process a payroll run through all stages
- [ ] Verify calculations:
  - Basic Pay
  - Overtime Pay
  - Holiday Pay
  - Night Differential
  - Gross Pay
  - SSS Contribution (employee + employer)
  - PhilHealth Contribution
  - Pag-IBIG Contribution
  - Withholding Tax
  - Net Pay

### 5.3 Payroll Adjustments
**Path:** `/payroll/adjustments`
- [ ] Create adjustment:
  - Employee: Select employee
  - Type: Addition (or Deduction)
  - Amount: ₱1,000
  - Description: `Performance bonus`
- [ ] Link to payroll run
- [ ] Verify adjustment appears in computation

### 5.4 Payslips
**Path:** `/payroll/payslips`
- [ ] View own payslip
- [ ] View any employee's payslip (SuperAdmin)
- [ ] Download PDF payslip
- [ ] Verify YTD (Year-to-Date) totals

### 5.5 Government Reports
- [ ] **SSS R3 Report:** Monthly contribution report
- [ ] **PhilHealth RF1:** Contribution report
- [ ] **Pag-IBIG MCRF:** Contribution report
- [ ] **BIR 2316:** Certificate of Compensation

---

## 6. Loan Module

### 6.1 Employee Loans
**Path:** `/loans`

#### Create Loan
- [ ] Click "New Loan"
- [ ] Fill details:
  - Employee: Select employee
  - Loan Type: SSS Salary Loan (or Pag-IBIG, Company)
  - Principal Amount: ₱50,000
  - Interest Rate: 10% annually
  - Term: 12 months
  - Disbursement Date: Today
- [ ] Generate amortization schedule
- [ ] Verify monthly deduction amount

#### Loan Management
- [ ] View active loans
- [ ] Make loan payment (lump sum)
- [ ] View loan ledger
- [ ] Check remaining balance
- [ ] Mark loan as fully paid

### 6.2 Loan Reports
- [ ] Loan portfolio summary
- [ ] Monthly amortization report
- [ ] Delinquent loans report

---

## 7. Accounting Module

### 7.1 Fiscal Periods
**Path:** `/accounting/fiscal-periods`
- [ ] View fiscal periods
- [ ] Create new fiscal year:
  - Name: FY 2027
  - Start: 2027-01-01
  - End: 2027-12-31
- [ ] Close a period (prevent new entries)
- [ ] Reopen a period

### 7.2 Chart of Accounts
**Path:** `/accounting/chart-of-accounts`
- [ ] View account hierarchy
- [ ] Create new account:
  - Code: 1100-001
  - Name: Cash in Bank - BDO
  - Type: Asset
  - Normal Balance: Debit
- [ ] Archive unused account
- [ ] Search accounts

### 7.3 Journal Entries
**Path:** `/accounting/journal-entries`

#### Create Journal Entry
- [ ] New Journal Entry:
  - Date: Today
  - Reference: `JE-2026-0001`
  - Description: `Test journal entry`
- [ ] Add lines:
  - Line 1: Account (Cash), Debit: ₱10,000, Credit: 0
  - Line 2: Account (Sales), Debit: 0, Credit: ₱10,000
- [ ] Verify balanced entry (debits = credits)
- [ ] Save as Draft

#### Journal Entry Workflow
- [ ] Submit for approval
- [ ] Approve as different user (or SuperAdmin)
- [ ] Post to GL
- [ ] Verify balances updated
- [ ] Try to delete posted entry (should fail)
- [ ] Create reversing entry

### 7.4 General Ledger
**Path:** `/accounting/general-ledger`
- [ ] Select account
- [ ] View GL entries
- [ ] Filter by date range
- [ ] Check running balance
- [ ] Export GL

### 7.5 Financial Statements

#### Trial Balance
**Path:** `/accounting/trial-balance`
- [ ] View trial balance for period
- [ ] Verify debits = credits
- [ ] Export to Excel

#### Income Statement
**Path:** `/accounting/income-statement`
- [ ] View P&L for period
- [ ] Revenue section
- [ ] Expense section
- [ ] Net income/loss

#### Balance Sheet
**Path:** `/accounting/balance-sheet`
- [ ] Assets section
- [ ] Liabilities section
- [ ] Equity section
- [ ] Verify A = L + E

#### Cash Flow Statement
**Path:** `/accounting/cash-flow`
- [ ] Operating activities
- [ ] Investing activities
- [ ] Financing activities

### 7.6 Bank Reconciliation
**Path:** `/accounting/bank-reconciliation`
- [ ] Select bank account
- [ ] Enter statement balance
- [ ] Match transactions
- [ ] Identify unreconciled items
- [ ] Complete reconciliation

### 7.7 Recurring Templates
**Path:** `/accounting/recurring-templates`
- [ ] Create recurring template:
  - Name: `Monthly Rent`
  - Frequency: Monthly
  - Next Run: 1st of next month
  - Journal Entry template
- [ ] Activate template
- [ ] Manually generate entry from template

---

## 8. Accounts Payable (AP)

### 8.1 Vendors
**Path:** `/accounting/vendors`

#### Create Vendor
- [ ] New Vendor:
  - Name: `ABC Supplies Co.`
  - TIN: `123-456-789-012` (test auto-format)
  - Phone: `0917 123 4567` (test auto-format)
  - Address: `123 Business St., Makati`
  - Contact Person: `Juan Dela Cruz`
  - Email: `vendor@example.com`
  - Payment Terms: Net 30
  - ATC Code: WC-160 (for EWT)
  - Is EWT Subject: Yes
- [ ] Add bank details:
  - Bank: BDO
  - Account No: 1234567890
  - Account Name: ABC Supplies Co.

#### Vendor Management
- [ ] View vendor list
- [ ] Edit vendor details
- [ ] Archive vendor
- [ ] View vendor transaction history

### 8.2 AP Invoices
**Path:** `/accounting/ap/invoices`

#### Create AP Invoice
- [ ] New Invoice:
  - Vendor: Select created vendor
  - Invoice No: `INV-2026-001`
  - Date: Today
  - Due Date: 30 days from now
  - Reference PO: (optional)
- [ ] Add line items:
  - Item: Office Supplies
  - Qty: 10
  - Unit Price: ₱500
  - Amount: ₱5,000
  - VAT: 12%
  - Total: ₱5,600
- [ ] Verify EWT calculation (if applicable)
- [ ] Save as Draft

#### Invoice Workflow
- [ ] Submit for approval
- [ ] VP/Manager approve
- [ ] Post invoice
- [ ] Verify GL entries created

### 8.3 Vendor Payments
**Path:** `/accounting/ap/payments`

#### Record Payment
- [ ] New Payment:
  - Vendor: Select vendor
  - Payment Date: Today
  - Payment Method: Check / Bank Transfer / Cash
  - Reference No: `CK-0001`
  - Bank Account: Select cash/bank account
- [ ] Apply to open invoices
- [ ] Record payment
- [ ] Verify invoice marked as paid

#### Check Printing
- [ ] Generate check voucher
- [ ] Print check (simulated)
- [ ] Mark check as printed

### 8.4 Credit Notes
**Path:** `/accounting/ap/credit-notes`
- [ ] Create credit note for vendor
- [ ] Apply to invoice
- [ ] Verify reduction in payable

### 8.5 AP Reports
- [ ] **Aging Report:** 0-30, 31-60, 61-90, 90+ days
- [ ] **Vendor Balance:** Summary per vendor
- [ ] **Unpaid Invoices:** All open AP

---

## 9. Accounts Receivable (AR)

### 9.1 Customers
**Path:** `/ar/customers`

#### Create Customer
- [ ] New Customer:
  - Name: `XYZ Trading Inc.`
  - TIN: `987-654-321-098` (test auto-format)
  - Phone: `0918 987 6543` (test auto-format)
  - Address: `456 Commerce Ave., Pasig`
  - Credit Limit: ₱100,000
  - Payment Terms: Net 15
- [ ] Save and verify

#### Customer Management
- [ ] View customer list
- [ ] Edit customer
- [ ] View customer statement
- [ ] Check credit utilization

### 9.2 Customer Invoices
**Path:** `/ar/invoices`

#### Create Invoice
- [ ] New Invoice:
  - Customer: Select customer
  - Invoice No: `SI-2026-0001`
  - Date: Today
  - Due Date: 15 days
- [ ] Add line items:
  - Description: `Plastic Products - Batch A`
  - Qty: 1000
  - Unit Price: ₱25
  - Amount: ₱25,000
  - VAT: 12%
- [ ] Save and post

#### Invoice Workflow
- [ ] Submit for approval
- [ ] Approve invoice
- [ ] Print invoice
- [ ] Email invoice to customer

### 9.3 Customer Payments
**Path:** `/ar/payments`

#### Record Payment
- [ ] New Payment:
  - Customer: Select customer
  - Payment Date: Today
  - Amount: Partial or full
  - Payment Method: Check / Bank Transfer
  - Reference: `DEP-001`
- [ ] Apply to open invoices
- [ ] Record payment
- [ ] Verify invoice status updated

### 9.4 Credit Notes
**Path:** `/ar/credit-notes`
- [ ] Create credit note for customer
- [ ] Apply to invoice or keep as credit

### 9.5 AR Reports
- [ ] **Aging Report:** Overdue buckets
- [ ] **Customer Statement:** Detailed transactions
- [ ] **Overdue Invoices:** Collection focus

---

## 10. Tax Module

### 10.1 VAT Ledger
**Path:** `/tax/vat-ledger`
- [ ] View input VAT (from AP)
- [ ] View output VAT (from AR)
- [ ] Check VAT payable/refundable
- [ ] Filter by period

### 10.2 BIR Filings
**Path:** `/tax/bir-filings`

#### Generate Reports
- [ ] **2550M (Monthly VAT):**
  - Month: March 2026
  - Generate report
  - Verify calculations
- [ ] **2551M (Quarterly VAT):**
  - Q1 2026
- [ ] **1601-EQ (Quarterly EWT):**
  - Withholding taxes

### 10.3 Tax Settings
- [ ] View ATC (Alphanumeric Tax Code) list
- [ ] Verify EWT rates
- [ ] Check VAT rate (12%)

---

## 11. Budget Module

### 11.1 Cost Centers
**Path:** `/budget/cost-centers`
- [ ] View existing cost centers
- [ ] Create new cost center:
  - Code: `CC-IT-001`
  - Name: `IT Department Operations`
  - Department: IT
- [ ] Assign manager

### 11.2 Annual Budget
**Path:** `/budget/annual-budget`

#### Create Budget
- [ ] New Budget:
  - Fiscal Year: 2026
  - Cost Center: Select
- [ ] Add budget lines:
  - Account: 5100 - Salaries
  - Budget Amount: ₱1,000,000
  - Q1-Q4 breakdown
- [ ] Submit for approval
- [ ] Approve budget

### 11.3 Budget Utilization
- [ ] View utilization report
- [ ] Check % used per account
- [ ] Identify over-budget items
- [ ] Budget vs Actual comparison

### 11.4 Budget Enforcement Testing
- [ ] Create PR exceeding budget
- [ ] Verify warning/alert appears
- [ ] Test budget override (SuperAdmin)

---

## 12. Fixed Assets Module

### 12.1 Asset Categories
**Path:** `/fixed-assets/categories`
- [ ] View categories (Machinery, Equipment, Vehicles, etc.)
- [ ] Create category:
  - Name: `Computer Equipment`
  - Depreciation Method: Straight Line
  - Useful Life: 5 years

### 12.2 Asset Register
**Path:** `/fixed-assets`

#### Add Asset
- [ ] New Asset:
  - Asset Code: `COMP-001`
  - Description: `Dell Laptop`
  - Category: Computer Equipment
  - Acquisition Date: Today
  - Acquisition Cost: ₱50,000
  - Salvage Value: ₱5,000
  - Location: Main Office
  - Custodian: Select employee
- [ ] Save and verify

#### Asset Management
- [ ] View asset details
- [ ] Transfer asset (change location/custodian)
- [ ] Record maintenance
- [ ] Dispose asset

### 12.3 Depreciation
**Path:** `/fixed-assets/depreciation`
- [ ] View depreciation schedule
- [ ] Run monthly depreciation
- [ ] Verify GL entries created
- [ ] Check accumulated depreciation

### 12.4 Asset Reports
- [ ] Asset listing
- [ ] Depreciation report
- [ ] Asset movement history

---

## 13. Inventory Module

### 13.1 Item Master
**Path:** `/inventory/items`

#### Create Item
- [ ] New Item:
  - SKU: `RAW-PLASTIC-001`
  - Name: `Raw Plastic Pellets - ABS`
  - Type: Raw Material (or Finished Good, WIP)
  - Category: Raw Materials
  - Unit: kg
  - Unit Cost: ₱150
  - Reorder Level: 500
  - Reorder Qty: 1000
- [ ] Save item

#### Item Management
- [ ] View item list
- [ ] Edit item details
- [ ] View stock levels
- [ ] View transaction history

### 13.2 Stock Levels
**Path:** `/inventory/stock`
- [ ] View current stock per item
- [ ] Check warehouse location
- [ ] View reserved quantities
- [ ] Available vs On Hand

### 13.3 Stock Movements
**Path:** `/inventory/movements`
- [ ] View all transactions
- [ ] Filter by type (receipt, issue, adjustment)
- [ ] Export movement history

### 13.4 Stock Adjustments
**Path:** `/inventory/adjustments`
- [ ] Create adjustment:
  - Item: Select
  - Adjustment Type: Add/Subtract
  - Quantity: 100
  - Reason: `Physical count correction`
- [ ] Submit for approval
- [ ] Post adjustment

### 13.5 Material Requisitions
**Path:** `/inventory/requisitions`

#### Create MRQ
- [ ] New Requisition:
  - Department: Production
  - Required Date: Tomorrow
- [ ] Add items:
  - Item: Raw Plastic Pellets
  - Qty: 500
  - Purpose: `Production Order #123`
- [ ] Submit for approval

#### MRQ Workflow
- [ ] Approve MRQ
- [ ] Issue materials from warehouse
- [ ] Verify stock deducted

### 13.6 Inventory Reports
- [ ] Stock status report
- [ ] Low stock alerts
- [ ] Inventory valuation (FIFO/Weighted Average)
- [ ] Slow-moving items

---

## 14. Procurement Module

### 14.1 Purchase Requests
**Path:** `/procurement/purchase-requests`

#### Create PR
- [ ] New PR:
  - Department: Production
  - Required Date: Next week
  - Budget Reference: (select cost center)
- [ ] Add items:
  - Item: Raw Plastic Pellets
  - Qty: 2000
  - Estimated Unit Cost: ₱150
- [ ] Submit for approval

#### PR Workflow
- [ ] Department Head approval
- [ ] VP approval
- [ ] Convert to PO (auto or manual)
- [ ] Check budget deduction

### 14.2 Purchase Orders
**Path:** `/procurement/purchase-orders`

#### Create PO
- [ ] New PO:
  - Vendor: Select
  - PR Reference: (link to PR)
  - Delivery Date: Next week + 3 days
- [ ] Add line items:
  - Item: Raw Plastic Pellets
  - Qty: 2000
  - Unit Price: ₱145
  - VAT: 12%
- [ ] Save as Draft

#### PO Workflow
- [ ] Submit for approval
- [ ] Manager/VP approve
- [ ] Release PO
- [ ] Email to vendor

### 14.3 Goods Receipts
**Path:** `/procurement/goods-receipts`

#### Create GR
- [ ] New GR:
  - PO Reference: Select released PO
  - Receipt Date: Today
  - Received By: (select employee)
- [ ] Add items received:
  - Item: Raw Plastic Pellets
  - Qty Received: 2000
  - Condition: Good
- [ ] Post GR
- [ ] Verify stock updated
- [ ] Verify 3-way match initiated

### 14.4 Vendor RFQ
**Path:** `/procurement/rfq`
- [ ] Create RFQ:
  - Items needed
  - Deadline for quotes
- [ ] Send to multiple vendors
- [ ] Receive and compare quotes
- [ ] Select winning vendor

### 14.5 Procurement Reports
- [ ] PR status report
- [ ] PO status report
- [ ] Vendor performance
- [ ] Purchase history

---

## 15. Production Module

### 15.1 Bill of Materials
**Path:** `/production/boms`

#### Create BOM
- [ ] New BOM:
  - Product: `Plastic Container A`
  - Version: 1.0
  - Quantity: 1000 units
- [ ] Add components:
  - Item: Raw Plastic Pellets
  - Qty: 50 kg
  - Cost: ₱7,500
- [ ] Save BOM
- [ ] Activate BOM

### 15.2 Production Orders
**Path:** `/production/orders`

#### Create Production Order
- [ ] New PO:
  - Product: Select from BOM
  - BOM Version: 1.0
  - Quantity to Produce: 5000
  - Planned Start: Tomorrow
  - Planned End: Next week
- [ ] System auto-reserves materials
- [ ] Save as Planned

#### Production Workflow
1. **Planned** → Release
2. **Released** → Materials issued
3. **In Progress** → Start production
4. **Completed** → Record output
5. **Closed** → Finalize

- [ ] Run through full production cycle
- [ ] Verify materials consumed
- [ ] Verify finished goods received in inventory

### 15.3 Delivery Schedules
**Path:** `/production/delivery-schedules`
- [ ] View production schedule
- [ ] Create delivery commitment
- [ ] Link to production order
- [ ] Track on-time delivery

### 15.4 Production Reports
- [ ] Production efficiency
- [ ] Material usage variance
- [ ] Capacity planning
- [ ] OEE (Overall Equipment Effectiveness)

---

## 16. Quality Control (QC)

### 16.1 Inspection Templates
**Path:** `/qc/templates`

#### Create Template
- [ ] New Template:
  - Name: `Incoming Raw Material Inspection`
  - Stage: IQC (Incoming Quality Control)
- [ ] Add inspection items:
  - Criterion: `Color consistency`
  - Method: `Visual inspection`
  - Acceptable Range: `Uniform color`
  - Criterion: `Weight`
  - Method: `Scale measurement`
  - Acceptable Range: `±5% of standard`
- [ ] Save template

### 16.2 Inspections
**Path:** `/qc/inspections`

#### Perform Inspection
- [ ] New Inspection:
  - Template: Select
  - Source: Goods Receipt (link to GR)
  - Lot/Batch: `BATCH-001`
  - Qty Inspected: 2000
- [ ] Record results:
  - Pass/Fail per criterion
  - Actual measurements
  - Remarks
- [ ] Submit inspection

#### Inspection Results
- [ ] View passed inspections
- [ ] View failed inspections
- [ ] Check defect rates

### 16.3 Non-Conformance Reports
**Path:** `/qc/ncrs`

#### Create NCR
- [ ] New NCR:
  - Inspection Reference: Select failed inspection
  - Severity: Major (or Minor, Critical)
  - Description: `Color variation beyond tolerance`
  - Defect Category: `Appearance`
- [ ] Submit NCR

#### NCR Workflow
- [ ] Review NCR
- [ ] Issue CAPA (Corrective/Preventive Action)
- [ ] Close NCR after resolution

### 16.4 CAPA
**Path:** `/qc/capa`
- [ ] View open CAPA actions
- [ ] Assign responsible person
- [ ] Set due date
- [ ] Track completion
- [ ] Verify effectiveness

### 16.5 QC Reports
**Path:** `/qc/reports`
- [ ] **Defect Rate Trend:** Monthly defect rates
- [ ] **Top Defects:** By category/severity
- [ ] **Inspection Summary:** Pass/fail rates
- [ ] **Supplier Quality:** Vendor defect rates

---

## 17. Maintenance Module

### 17.1 Equipment Register
**Path:** `/maintenance/equipment`

#### Add Equipment
- [ ] New Equipment:
  - Asset Code: `MACHINE-001`
  - Name: `Injection Molding Machine A`
  - Type: Production Equipment
  - Location: Plant A
  - Manufacturer: `Toshiba`
  - Model: `IS220GN`
  - Serial No: `SN123456`
  - Purchase Date: 2020-01-15
- [ ] Save equipment

### 17.2 PM Schedules
**Path:** `/maintenance/pm-schedules`

#### Create PM Schedule
- [ ] New Schedule:
  - Equipment: Select
  - Maintenance Type: Preventive
  - Frequency: Monthly
  - Day of Month: 15
  - Description: `Lubrication and inspection`
- [ ] Save schedule
- [ ] Verify auto-generation of work orders

### 17.3 Work Orders
**Path:** `/maintenance/work-orders`

#### Create Work Order
- [ ] New Work Order:
  - Equipment: Select
  - Type: Corrective (Breakdown)
  - Priority: High
  - Description: `Machine making unusual noise`
  - Assigned To: Maintenance technician
- [ ] Save as Open

#### Work Order Workflow
- [ ] Start work
- [ ] Record labor hours
- [ ] Add parts used
- [ ] Complete work
- [ ] Close work order

### 17.4 Maintenance Reports
- [ ] Equipment downtime
- [ ] MTBF (Mean Time Between Failures)
- [ ] MTTR (Mean Time To Repair)
- [ ] PM compliance rate

---

## 18. Mold Module

### 18.1 Mold Master
**Path:** `/molds`

#### Register Mold
- [ ] New Mold:
  - Mold Code: `MOLD-CAP-001`
  - Description: `Bottle Cap Mold - 24 cavity`
  - Product: `Bottle Cap A`
  - Max Shots: 1,000,000
  - Warning Shots: 900,000
  - Current Shots: 500,000
  - Status: Active
- [ ] Save mold

### 18.2 Shot Logging
**Path:** `/molds/shot-log`
- [ ] Record shot count:
  - Mold: Select
  - Production Order: Select
  - Shots Produced: 5000
  - Date: Today
- [ ] Verify shot count updated

### 18.3 Mold Maintenance
- [ ] View molds nearing maintenance (warning shots)
- [ ] Create maintenance work order for mold
- [ ] Track mold repair history

### 18.4 Mold Reports
- [ ] Mold utilization
- [ ] Shot count history
- [ ] Maintenance schedule
- [ ] Mold lifespan projection

---

## 19. Delivery Module

### 19.1 Delivery Receipts
**Path:** `/delivery/receipts`

#### Create DR
- [ ] New DR:
  - Customer: Select
  - DR No: `DR-2026-0001`
  - Date: Today
  - Reference Invoice: Select
- [ ] Add items:
  - Product: Select from invoice
  - Qty: 1000
  - Condition: Good
- [ ] Save DR

### 19.2 Shipments
**Path:** `/delivery/shipments`
- [ ] Create shipment:
  - DRs to include
  - Vehicle: Select
  - Driver: Select
  - Delivery Date: Tomorrow
- [ ] Track shipment status

### 19.3 Vehicles
**Path:** `/delivery/vehicles`

#### Manage Fleet
- [ ] View vehicles
- [ ] Add vehicle:
  - Plate No: `ABC-123`
  - Type: Delivery Van
  - Capacity: 2000 kg
  - Driver: Assign
- [ ] Schedule maintenance
- [ ] Track vehicle availability

### 19.4 Delivery Reports
- [ ] Delivery performance
- [ ] On-time delivery rate
- [ ] Vehicle utilization
- [ ] Delivery cost analysis

---

## 20. ISO Module

### 20.1 Controlled Documents
**Path:** `/iso/documents`

#### Create Document
- [ ] New Document:
  - Document No: `QMS-001`
  - Title: `Quality Policy`
  - Revision: A
  - Effective Date: Today
  - Department: QC
  - Document Type: Policy
- [ ] Upload file (PDF)
- [ ] Set review date

#### Document Control
- [ ] View document list
- [ ] Revise document (create new revision)
- [ ] Archive obsolete documents
- [ ] Track document distribution

### 20.2 Internal Audits
**Path:** `/iso/audits`

#### Schedule Audit
- [ ] New Audit:
  - Audit No: `IA-2026-001`
  - Department: Production
  - Audit Date: Next week
  - Auditor: Assign internal auditor
  - Scope: `ISO 9001:2015 compliance`
- [ ] Save schedule

#### Conduct Audit
- [ ] Record findings
- [ ] Classify findings (Major/Minor/Observation)
- [ ] Assign corrective actions
- [ ] Close findings after verification

### 20.3 Audit Findings
**Path:** `/iso/findings`
- [ ] View open findings
- [ ] Track corrective action status
- [ ] Verify effectiveness
- [ ] Close findings

### 20.4 ISO Reports
- [ ] Audit schedule
- [ ] Finding summary
- [ ] Department compliance
- [ ] NC trend analysis

---

## 21. CRM Module

### 21.1 Dashboard
**Path:** `/crm/dashboard`
- [ ] View open tickets count
- [ ] View SLA compliance percentage
- [ ] Check tickets by priority
- [ ] View recent SLA breaches

### 21.2 Tickets
**Path:** `/crm/tickets`

#### Create Ticket
- [ ] New Ticket:
  - Customer: Select
  - Subject: `Product quality concern`
  - Description: `Detailed issue description`
  - Priority: High (or Low, Medium, Critical)
  - Category: Quality
- [ ] Save ticket

#### Ticket Workflow
- [ ] Assign to agent
- [ ] Add internal notes
- [ ] Post customer message
- [ ] Change status: Open → In Progress → Resolved
- [ ] Verify SLA tracking

### 21.3 Customer Portal
**Path:** `/client-portal` (as customer)
- [ ] View customer ticket history
- [ ] Create ticket from portal
- [ ] Reply to agent messages
- [ ] View invoice/payment history

### 21.4 CRM Reports
- [ ] Ticket volume
- [ ] Average resolution time
- [ ] Agent performance
- [ ] Customer satisfaction

---

## 22. Reports & Analytics

### 22.1 Executive Dashboard
**Path:** `/dashboard`
- [ ] View KPI widgets
- [ ] Check revenue trends
- [ ] Monitor expense breakdown
- [ ] View pending approvals

### 22.2 Custom Reports
**Path:** `/reports`
- [ ] Run pre-built reports
- [ ] Create custom report
- [ ] Schedule report delivery
- [ ] Export to PDF/Excel

### 22.3 Audit Trail
**Path:** `/admin/audit-trail`
- [ ] View system-wide changes
- [ ] Filter by user
- [ ] Filter by module
- [ ] Filter by date range
- [ ] Export audit log

---

## 23. System Administration

### 23.1 System Settings
**Path:** `/admin/settings`
- [ ] Company information
- [ ] Fiscal year settings
- [ ] Number format settings
- [ ] Email configuration

### 23.2 User Management
(See [Authentication & User Management](#1-authentication--user-management))

### 23.3 Permission Testing
- [ ] Verify role permissions
- [ ] Test department scoping
- [ ] Verify SoD enforcement
- [ ] Test permission overrides

### 23.4 Data Management
- [ ] Backup database
- [ ] Export data
- [ ] Import data (if applicable)
- [ ] Archive old data

### 23.5 Notifications
**Path:** `/notifications`
- [ ] View notification list
- [ ] Mark as read
- [ ] Configure notification preferences
- [ ] Test email notifications

---

## 24. End-to-End Workflows

### Workflow 1: Hire to Retire
1. **HR:** Create employee record
2. **HR:** Set salary grade and compensation
3. **Payroll:** Include in payroll run
4. **Loan:** Process salary loan
5. **Leave:** Apply for and approve leave
6. **Attendance:** Time in/out
7. **HR:** Process resignation
8. **Payroll:** Final pay computation
9. **Loan:** Check loan balance clearance
10. **Accounting:** Clearance posting

### Workflow 2: Procure to Pay
1. **Inventory:** Low stock alert triggers
2. **Procurement:** Auto-create PR
3. **Procurement:** Approve PR
4. **Procurement:** Convert to PO
5. **Procurement:** Approve and release PO
6. **Inventory:** Receive goods (GR)
7. **QC:** Inspect goods
8. **Accounting:** Match GR-PO-Invoice (3-way)
9. **AP:** Create invoice
10. **AP:** Approve and post
11. **AP:** Process payment
12. **Accounting:** Bank reconciliation

### Workflow 3: Order to Cash
1. **Sales:** Receive customer order
2. **AR:** Create customer invoice
3. **AR:** Approve and post
4. **Production:** Create production order (if make-to-order)
5. **QC:** Inspect finished goods
6. **Inventory:** Issue goods
7. **Delivery:** Create delivery receipt
8. **Delivery:** Ship to customer
9. **AR:** Record customer payment
10. **AR:** Apply payment to invoice
11. **Accounting:** Revenue recognition

### Workflow 4: Production Planning
1. **Sales:** Delivery schedule created
2. **Production:** Auto-create production order
3. **Production:** BOM explosion
4. **Inventory:** Check material availability
5. **Procurement:** Auto-create PR for missing materials
6. **Production:** Release production order
7. **Inventory:** Issue materials
8. **Production:** Record production output
9. **QC:** Final inspection
10. **Inventory:** Receive finished goods
11. **Delivery:** Schedule delivery

---

## Troubleshooting

### Common Issues

#### Issue: Payroll computation error
**Solution:**
```bash
# Check queue worker is running
php artisan queue:status

# Restart queue
php artisan queue:restart

# Retry failed jobs
php artisan queue:retry all
```

#### Issue: Cannot access module
**Solution:**
- Verify user has required permission
- Check department scoping
- As SuperAdmin, verify bypass is working

#### Issue: 500 error on reports
**Solution:**
- Check fiscal period is configured
- Verify chart of accounts is seeded
- Check database connection

#### Issue: TIN/Phone not auto-formatting
**Solution:**
- Clear browser cache
- Verify frontend build is current
- Check browser console for errors

#### Issue: Notification not received
**Solution:**
- Check notification settings
- Verify email configuration
- Check queue worker status

### Getting Help
- Check logs: `storage/logs/laravel.log`
- Frontend logs: Browser console (F12)
- Queue logs: `storage/logs/queue.log`

---

## Test Data Reference

### Sample Vendors
| Name | TIN | Type |
|------|-----|------|
| ABC Supplies Co. | 123-456-789-012 | Local |
| Global Materials Inc. | 234-567-890-123 | Import |

### Sample Customers
| Name | Credit Limit | Terms |
|------|--------------|-------|
| XYZ Trading Inc. | ₱100,000 | Net 15 |
| Mega Retail Corp. | ₱500,000 | Net 30 |

### Sample Items
| SKU | Name | Type |
|-----|------|------|
| RAW-PLASTIC-001 | Raw Plastic Pellets | Raw Material |
| FG-CONTAINER-001 | Plastic Container A | Finished Good |

### Test Amounts
- Small transaction: ₱1,000 - ₱10,000
- Medium transaction: ₱10,000 - ₱100,000
- Large transaction: ₱100,000+

---

## Completion Checklist

After completing all tests, verify:

- [ ] All 20 modules accessed and tested
- [ ] All CRUD operations working
- [ ] All workflows tested end-to-end
- [ ] Reports generated successfully
- [ ] No JavaScript errors in console
- [ ] No 500 errors from API
- [ ] All permissions working correctly
- [ ] SoD rules enforced (test with non-admin)

---

**Document Version:** 1.0  
**Last Updated:** March 2026  
**Author:** Ogami ERP Team
