# Ogami ERP - Individual Role Breakdown

Detailed breakdown of each role and their department-specific responsibilities.

---

## 1. MANAGER (HR Manager)

**Department:** Human Resources (HR)  
**Email:** hr.manager@ogamierp.local  
**Password:** Manager@Test1234!  
**Employee Code:** EMP-HR-001

### Primary Responsibility
Full HR department management and payroll preparation (but NOT payroll approval - that's Accounting).

### What This Role Can Do:

**Employee Management:**
- Create new employees
- Update employee information
- Upload/download employee documents
- View salary and government IDs
- Suspend or terminate employees
- Manage organizational structure

**Attendance:**
- Import attendance CSV files
- Manage shift schedules
- View and resolve attendance anomalies
- Approve/reject overtime requests

**Leave Management:**
- View all leave requests
- Approve leaves (Step 2 in approval chain)
- Process leaves through GA (Step 4)
- Adjust leave balances
- Configure leave types

**Payroll:**
- Create/initiate payroll runs
- Set pay periods
- Process payroll computation
- **CANNOT approve payroll** (SoD rule - must be Accounting)

**Loans:**
- HR review and approval (Step 2)
- Manager check in v2 approval chain

**Procurement:**
- Step 2 checker for Purchase Requests

### What This Role CANNOT Do:
- ❌ Approve own payroll runs (SoD violation)
- ❌ Post journal entries
- ❌ Approve AP/AR invoices
- ❌ Access financial reports beyond payroll

---

## 2. GA_OFFICER (General Affairs Officer)

**Department:** Human Resources (HR)  
**Email:** ga.officer@ogamierp.local  
**Password:** Ga_officer@Test1234!  
**Employee Code:** EMP-HR-002

### Primary Responsibility
HR administrative support - handles day-to-day HR operations without financial access.

### What This Role Can Do:

**HR Support:**
- View team employee records
- Upload employee documents
- Download employee files

**Attendance:**
- Import attendance CSV
- Resolve attendance anomalies
- Manage shift schedules
- Supervise overtime

**Leave Processing:**
- GA process leaves (Step 4 in chain)
- View team leave requests
- File leaves on behalf of others

**Self-Service:**
- View own payslip
- Submit own leave requests
- Log own overtime

### What This Role CANNOT Do:
- ❌ Approve payroll
- ❌ Access accounting/finance modules
- ❌ Approve loans beyond GA step
- ❌ Create journal entries
- ❌ View vendor/customer financial data

---

## 3. HR_HEAD (Department Head - HR)

**Department:** Human Resources (HR)  
**Email:** hr.head@ogamierp.local  
**Password:** Head@Test1234!  
**Employee Code:** EMP-HR-003

### Primary Responsibility
First-level approver for HR department team.

### What This Role Can Do:

**Team Management:**
- View team employees
- Create new employees (initial entry)
- Upload documents

**Approvals (Step 2):**
- Approve leaves for team members
- Approve overtime for team
- Head note for loans
- Check Purchase Requests

**Attendance:**
- View team attendance
- Import attendance
- Manage shifts

### What This Role CANNOT Do:
- ❌ Approve own leave/OT (SoD)
- ❌ Access financial modules
- ❌ Final approval for loans/payroll

---

## 4. HR_STAFF

**Department:** Human Resources (HR)  
**Email:** hr.staff@ogamierp.local  
**Password:** Staff@Test1234!  
**Employee Code:** EMP-HR-004

### Primary Responsibility
Rank-and-file HR staff - creates requests, no approval authority.

### What This Role Can Do:

**Self-Service:**
- View own payslip
- File leave requests
- Submit overtime requests
- Apply for loans
- View own attendance

**Limited Work:**
- Create Material Requisitions (MRQ)
- Log production output (if assigned)

### What This Role CANNOT Do:
- ❌ Approve anything
- ❌ View other employees' salaries
- ❌ Access management functions

---

## 5. OFFICER (Accounting Officer)

**Department:** Accounting & Finance (ACCTG)  
**Email:** acctg.officer@ogamierp.local  
**Password:** Officer@Test1234!  
**Employee Code:** EMP-ACCT-002

### Primary Responsibility
Full financial management - GL, AP, AR, Payroll, Banking.

### What This Role Can Do:

**Journal Entries:**
- Create journal entries
- Submit for approval
- **CANNOT post own JE** (SoD - needs different user)

**Accounts Payable:**
- Create vendor invoices
- Submit invoices
- **CANNOT approve own invoice** (SoD)
- Record payments

**Accounts Receivable:**
- Create customer invoices
- Apply payments
- Write off bad debts

**Banking:**
- Manage bank accounts
- Create reconciliations
- Certify reconciliations

**Payroll:**
- Accounting approval (after HR submits)
- Disburse payments
- Publish payroll
- Download bank files

**Budget:**
- Full budget management

**Fixed Assets:**
- Manage asset register
- Calculate depreciation

**Inventory:**
- Stock adjustments
- Review MRQs

### What This Role CANNOT Do:
- ❌ Post own journal entries
- ❌ Approve own invoices
- ❌ Create vendors/customers (Purchasing does this)

---

## 6. ACCTG_MANAGER (Accounting Manager)

**Department:** Accounting & Finance (ACCTG)  
**Email:** acctg.manager@ogamierp.local  
**Password:** Manager@12345!  
**Employee Code:** EMP-ACCT-001

### Primary Responsibility
Senior accounting role - approves what officers create.

### What This Role Can Do:

**All Officer Permissions PLUS:**
- Post journal entries created by officers
- Approve vendor invoices
- Approve customer invoices
- Final payroll approval
- Review accounting work

### Key Difference from Officer:
Can **approve** what Accounting Officers create, enabling SoD separation.

---

## 7. ACCTG_HEAD (Department Head - Accounting)

**Department:** Accounting & Finance (ACCTG)  
**Email:** acctg.head@ogamierp.local  
**Password:** Head@Test1234!  
**Employee Code:** EMP-ACCT-003

### Primary Responsibility
First-level approver for Accounting team.

### What This Role Can Do:
- View financial reports
- Approve team leaves/OT
- Context viewing for approvals

### What This Role CANNOT Do:
- ❌ Post journal entries (needs Manager role or different user)
- ❌ Final financial approvals (needs VP or Manager)

---

## 8. ACCTG_STAFF

**Department:** Accounting & Finance (ACCTG)  
**Email:** acctg.staff@ogamierp.local  
**Password:** Staff@Test1234!  
**Employee Code:** EMP-ACCT-004

### Primary Responsibility
Junior accounting staff - data entry, no approvals.

### What This Role Can Do:
- Data entry (invoices, basic transactions)
- View own payslip
- Create MRQs
- Self-service functions

### What This Role CANNOT Do:
- ❌ Post to GL
- ❌ Approve transactions
- ❌ Access sensitive financial approvals

---

## 9. PLANT_MANAGER

**Department:** Plant Operations  
**Email:** plant.manager@ogamierp.local  
**Password:** Plant_manager@Test1234!  
**Employee Code:** EMP-PLANT-001

### Primary Responsibility
Oversees ALL plant operations: Production, QC, Maintenance, Mold, Delivery, ISO.

### What This Role Can Do:

**Production:**
- Full BOM management
- Production schedules
- Work order release
- Complete work orders

**QC:**
- Override QC decisions
- View/manage inspections

**Maintenance:**
- Full maintenance management

**Mold:**
- Full mold management

**Delivery:**
- Full logistics management

**ISO:**
- Full ISO management
- Conduct audits

**Inventory:**
- View stock
- Create MRQs

### What This Role CANNOT Do:
- ❌ Access accounting/finance modules
- ❌ Approve payroll
- ❌ Post journal entries

---

## 10. PRODUCTION_MANAGER

**Department:** Production (PROD)  
**Email:** prod.manager@ogamierp.local  
**Password:** Production_manager@Test1234!  
**Employee Code:** EMP-PROD-001

### Primary Responsibility
Supervises production floor activities only.

### What This Role Can Do:

**Production:**
- Create BOMs
- Manage production schedules
- Create work orders
- Release work orders
- Complete work orders
- Log production output

**Inventory:**
- View items/stock (material availability)

### What This Role CANNOT Do:
- ❌ Access QC, Maintenance, Mold modules
- ❌ Financial functions
- ❌ Approve procurement beyond production context

---

## 11. PROD_HEAD (Production Head)

**Department:** Production (PROD)  
**Email:** prod.head@ogamierp.local  
**Password:** Head@Test1234!  
**Employee Code:** EMP-PROD-002

### Primary Responsibility
First-level approver for Production team.

### What This Role Can Do:
- Approve team leaves/OT
- Step 2 approver for PRs
- Production supervision

---

## 12. PROD_STAFF

**Department:** Production (PROD)  
**Email:** prod.staff@ogamierp.local  
**Password:** Staff@Test1234!  
**Employee Code:** EMP-PROD-003

### Primary Responsibility
Production floor staff - executes work orders.

### What This Role Can Do:
- Log production output
- Create MRQs for materials
- Self-service (leaves, OT)

---

## 13. QC_MANAGER

**Department:** Quality Control (QC)  
**Email:** qc.manager@ogamierp.local  
**Password:** Qc_manager@Test1234!  
**Employee Code:** EMP-QC-001

### Primary Responsibility
Manages quality control and assurance operations.

### What This Role Can Do:

**QC/QA:**
- Manage inspection templates
- Create inspections (incoming, in-process, final)
- Create/manage NCRs
- Close NCRs
- QC override on production

**Inventory:**
- View items/stock (QC context)

### What This Role CANNOT Do:
- ❌ Production management
- ❌ Financial access

---

## 14. QC_HEAD (QC Head)

**Department:** Quality Control (QC)  
**Email:** qc.head@ogamierp.local  
**Password:** Head@Test1234!  
**Employee Code:** EMP-QC-002

### Primary Responsibility
First-level approver for QC team.

### What This Role Can Do:
- Approve team leaves/OT
- QC supervision

---

## 15. QC_STAFF

**Department:** Quality Control (QC)  
**Email:** qc.staff@ogamierp.local  
**Password:** Staff@Test1234!  
**Employee Code:** EMP-QC-003

### Primary Responsibility
QC inspector - performs inspections.

### What This Role Can Do:
- Conduct inspections
- Create NCRs
- Self-service functions

---

## 16. MOLD_MANAGER

**Department:** Mold Department  
**Email:** mold.manager@ogamierp.local  
**Password:** Mold_manager@Test1234!  
**Employee Code:** EMP-MOLD-001

### Primary Responsibility
Oversees mold department operations.

### What This Role Can Do:

**Mold Management:**
- View/manage mold masters
- Log shot counts
- Full mold department control

---

## 17. MOLD_HEAD (Mold Head)

**Department:** Mold Department  
**Email:** mold.head@ogamierp.local  
**Password:** Head@Test1234!  
**Employee Code:** EMP-MOLD-002

### Primary Responsibility
First-level approver for Mold team.

---

## 18. MOLD_STAFF

**Department:** Mold Department  
**Email:** mold.staff@ogamierp.local  
**Password:** Staff@Test1234!  
**Employee Code:** EMP-MOLD-003

### Primary Responsibility
Mold technician - maintains and operates molds.

---

## 19. PLANT_HEAD (Plant Head)

**Department:** Plant Operations  
**Email:** plant.head@ogamierp.local  
**Password:** Head@Test1234!  
**Employee Code:** EMP-PLANT-002

### Primary Responsibility
First-level approver for Plant operations team.

---

## 20. CRM_MANAGER

**Department:** Sales & Marketing (SALES)  
**Email:** crm.manager@ogamierp.local  
**Password:** CrmManager@12345!  
**Employee Code:** EMP-SALES-001

### Primary Responsibility
Manages customer relationships and support tickets.

### What This Role Can Do:

**CRM:**
- View all support tickets
- Create tickets
- Reply to customers
- Assign tickets to agents
- Close tickets
- Manage CRM dashboard

### What This Role CANNOT Do:
- ❌ Access accounting/sales financial data
- ❌ Approve invoices

---

## 21. SALES_STAFF

**Department:** Sales & Marketing (SALES)  
**Email:** sales.staff@ogamierp.local  
**Password:** Staff@Test1234!  
**Employee Code:** EMP-SALES-002

### Primary Responsibility
Sales representative - handles customer inquiries.

---

## 22. VICE_PRESIDENT

**Department:** Executive (EXEC)  
**Email:** vp@ogamierp.local  
**Password:** Vice_president@Test1234!  
**Employee Code:** EMP-EXEC-002

### Primary Responsibility
Final approver for all financial requests.

### What This Role Can Do:

**Approvals Dashboard:**
- View all pending approvals
- Approve/reject with comments

**Final Approval Authority:**
- Loans (Step 5)
- Purchase Requests (high value)
- Material Requisitions
- Budget approvals
- Payroll (if configured)

**View-Only Access:**
- All modules (for approval context)
- Financial reports
- Employee data
- Vendor/Customer data

### What This Role CANNOT Do:
- ❌ Create transactions (create-only in approvals)
- ❌ Data entry
- ❌ Modify posted entries

---

## 23. EXECUTIVE

**Department:** Executive (EXEC)  
**Email:** executive@ogamierp.local  
**Password:** Executive@Test1234!  
**Employee Code:** EMP-EXEC-001

### Primary Responsibility
Chairman/President - board-level oversight (read-only).

### What This Role Can Do:
- View all modules
- Executive overtime approval
- View financial reports
- Self-service functions

### What This Role CANNOT Do:
- ❌ Create/modify transactions
- ❌ Approve financial transactions (VP does this)

---

## 24. VENDOR (External)

**Portal:** Vendor Portal  
**Email:** vendor@ogamierp.local  
**Password:** Vendor@Test1234!

### Access:
- View purchase orders sent to them
- Update fulfillment status
- Submit delivery notes
- Submit invoices
- View payment status

---

## 25. CLIENT (External)

**Portal:** Client Portal  
**Email:** client@ogamierp.local  
**Password:** Client@Test1234!

### Access:
- View own support tickets
- Create new tickets
- Reply to ticket responses

---

## Summary Table

| # | Role | Dept | Main Function | Approves? |
|---|------|------|---------------|-----------|
| 1 | Manager | HR | HR + Payroll prep | Leaves, OT, PR (Step 2) |
| 2 | GA Officer | HR | HR admin support | GA processing |
| 3 | HR Head | HR | Team supervisor | Leaves/OT (Step 2) |
| 4 | HR Staff | HR | Data entry | ❌ No |
| 5 | Officer | ACCTG | Accounting operations | ❌ No (creates only) |
| 6 | Acctg Manager | ACCTG | Senior accounting | JE, invoices, payroll |
| 7 | Acctg Head | ACCTG | Team supervisor | Leaves/OT (Step 2) |
| 8 | Acctg Staff | ACCTG | Junior accounting | ❌ No |
| 9 | Plant Manager | PLANT | Plant-wide oversight | Plant operations |
| 10 | Production Manager | PROD | Production only | Production orders |
| 11 | Prod Head | PROD | Team supervisor | Leaves/OT (Step 2) |
| 12 | Prod Staff | PROD | Floor worker | ❌ No |
| 13 | QC Manager | QC | Quality management | QC decisions |
| 14 | QC Head | QC | Team supervisor | Leaves/OT (Step 2) |
| 15 | QC Staff | QC | Inspector | ❌ No |
| 16 | Mold Manager | MOLD | Mold operations | Mold decisions |
| 17 | Mold Head | MOLD | Team supervisor | Leaves/OT (Step 2) |
| 18 | Mold Staff | MOLD | Technician | ❌ No |
| 19 | Plant Head | PLANT | Team supervisor | Leaves/OT (Step 2) |
| 20 | CRM Manager | SALES | Customer support | Tickets |
| 21 | Sales Staff | SALES | Sales rep | ❌ No |
| 22 | Vice President | EXEC | Final approver | ALL final approvals |
| 23 | Executive | EXEC | Board oversight | ❌ Read-only |
| 24 | Vendor | External | Supplier | PO acknowledgment |
| 25 | Client | External | Customer | Ticket creation |

---

*Document Version: 1.0*  
*Last Updated: 2026-03-16*  
*Total Roles Documented: 25*
