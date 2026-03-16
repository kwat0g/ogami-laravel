# Ogami ERP - Role-Based Manual Testing Guide

## 📋 Test Account Reference

### Employee Record Types

The seeders create three types of test accounts:

1. **Full Employee Accounts** - Have both User and Employee records (linked)
   - Created by: `SampleDataSeeder`, `ExtraAccountsSeeder`
   - Can be used for payroll, attendance, leave testing

2. **User-Only Test Accounts** - Have User record only (no Employee record)
   - Created by: `TestAccountsSeeder`
   - Used for role-based permission testing

3. **Portal Accounts** - External users (vendor/client portal access)
   - Created by: `TestAccountsSeeder`
   - No internal system access beyond their portal

### System Administrator Accounts

| Role | Email | Password | Employee Code | Department |
|------|-------|----------|---------------|------------|
| admin | `admin@ogamierp.local` | `Admin@1234567890!` | — | — |
| super_admin | `superadmin@ogamierp.local` | `SuperAdmin@12345!` | — | — |
| it.admin | `it.admin@ogamierp.local` | `Manager@12345!` | EMP-2026-0031 | IT |

### Executive & VP Accounts

| Role | Email | Password | Employee Code | Department |
|------|-------|----------|---------------|------------|
| executive | `executive@ogamierp.local` | `Executive@Test1234!` | — | EXEC |
| vice_president | `vp@ogamierp.local` | `Vp@Test1234!` | — | EXEC |

### Manager Accounts

| Role | Email | Password | Employee Code | Department |
|------|-------|----------|---------------|------------|
| manager (HR)* | `hr.manager@ogamierp.local` | `Manager@Test1234!` | EMP-2026-0001 | HR |
| plant_manager | `plant.manager@ogamierp.local` | `Plant_manager@Test1234!` | — | PROD |
| production_manager | `prod.manager@ogamierp.local` | `Production_manager@Test1234!` | — | PROD |
| qc_manager | `qc.manager@ogamierp.local` | `Qc_manager@Test1234!` | — | QC |
| mold_manager | `mold.manager@ogamierp.local` | `Mold_manager@Test1234!` | — | MOLD |
| crm_manager* | `crm.manager@ogamierp.local` | `CrmManager@12345!` | EMP-CRM-001 | SALES |

*Has linked Employee record (can be used for payroll testing)

### Officer Accounts

| Role | Email | Password | Employee Code | Department |
|------|-------|----------|---------------|------------|
| officer (Acctg)* | `acctg.officer@ogamierp.local` | `Officer@Test1234!` | EMP-2026-0003 | ACCTG |
| acctg.manager* | `acctg.manager@ogamierp.local` | `Manager@12345!` | EMP-2026-0030 | ACCTG |
| ga_officer | `ga.officer@ogamierp.local` | `Ga_officer@Test1234!` | — | HR |
| purchasing_officer | `purchasing@ogamierp.local` | `Purchasing_officer@Test1234!` | — | ACCTG |
| impex_officer | `impex@ogamierp.local` | `Impex_officer@Test1234!` | — | ACCTG |

*Has linked Employee record (can be used for payroll testing)

### Head & Staff Accounts

| Role | Email | Password | Employee Code | Department |
|------|-------|----------|---------------|------------|
| head | `dept.head@ogamierp.local` | `Head@Test1234!` | — | PROD |
| staff | `staff@ogamierp.local` | `Staff@Test1234!` | — | PROD |

### Portal Accounts (External)

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| vendor | `vendor@ogamierp.local` | `Vendor@Test1234!` | External vendor portal |
| client | `client@ogamierp.local` | `Client@Test1234!` | External client portal |

---

## 🧪 Cross-Module Integration Tests by Role

### TEST SUITE 1: Super Admin (Full System Access)

**Account:** `superadmin@ogamierp.local` / `SuperAdmin@12345!`

#### 1.1 System Administration
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 1.1.1 | Login as Super Admin | 1. Navigate to login page<br>2. Enter credentials<br>3. Click login | Dashboard loads with all modules visible |
| 1.1.2 | Access User Management | 1. Go to Admin → Users<br>2. View user list<br>3. Create new user | User created successfully |
| 1.1.3 | Assign Roles | 1. Edit a user<br>2. Change role assignment<br>3. Save | Role updated, user permissions change |
| 1.1.4 | System Settings | 1. Go to Admin → Settings<br>2. Modify a setting<br>3. Save | Setting persisted, reflected in app |
| 1.1.5 | Audit Log Review | 1. Go to Admin → Audit Logs<br>2. Filter by event type<br>3. View details | All system actions logged |
| 1.1.6 | Backup Management | 1. Go to Admin → Backups<br>2. Create backup<br>3. Download backup | Backup created and downloadable |

#### 1.2 Cross-Module Data Access
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 1.2.1 | View All Departments | 1. Access HR → Employees<br>2. Switch department filter<br>3. View each department | All employee data visible regardless of department |
| 1.2.2 | Override Department Scope | 1. View Payroll for Dept A<br>2. View Payroll for Dept B<br>3. Compare data | Can view payroll across all departments |
| 1.2.3 | SoD Bypass Verification | 1. Create a record as superadmin<br>2. Try to approve same record<br>3. Verify approval works | SoD rules bypassed for superadmin |

---

### TEST SUITE 2: HR Manager (HR + Attendance + Leave + Loans)

**Account:** `hr.manager@ogamierp.local` / `Manager@Test1234!`  
**Employee:** EMP-2026-0001 (Maria Santos)

#### 2.1 HR Module - Employee Management
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 2.1.1 | View Employee List | 1. Login as HR Manager<br>2. Go to HR → Employees | See employees in HR department |
| 2.1.2 | Create New Employee | 1. Click "Add Employee"<br>2. Fill in all required fields<br>3. Save | Employee created with employee code generated |
| 2.1.3 | Employee Onboarding | 1. Open new employee record<br>2. Upload documents<br>3. Complete onboarding steps | Onboarding status updated |
| 2.1.4 | Update Employee Info | 1. Edit existing employee<br>2. Change salary/position<br>3. Save | Changes saved, audit log created |
| 2.1.5 | Employee Offboarding | 1. Select employee<br>2. Set separation date<br>3. Process clearance | Employee status changed to inactive |

#### 2.2 Attendance Module
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 2.2.1 | View Attendance Logs | 1. Go to Attendance → Logs<br>2. Select date range<br>3. Filter by department | Attendance records displayed |
| 2.2.2 | Manual Time Entry | 1. Click "Add Log"<br>2. Select employee<br>3. Enter time in/out<br>4. Save | Attendance log created |
| 2.2.3 | Process Attendance | 1. Go to Attendance → Process<br>2. Select period<br>3. Run processing | Attendance calculated, exceptions flagged |
| 2.2.4 | Overtime Approval | 1. Go to Attendance → Overtime<br>2. View pending requests<br>3. Approve/reject | Request status updated |

#### 2.3 Leave Module
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 2.3.1 | View Leave Requests | 1. Go to Leave → Requests<br>2. View team requests | All pending requests visible |
| 2.3.2 | Approve Leave Request | 1. Open pending request<br>2. Review details<br>3. Click approve | Request approved, balance updated |
| 2.3.3 | Check Leave Balance | 1. Go to Leave → Balances<br>2. Select employee<br>3. View balance | Accurate leave balance displayed |
| 2.3.4 | Leave Balance Adjustment | 1. Select employee<br>2. Adjust balance<br>3. Add reason<br>4. Save | Balance adjusted, audit trail created |

#### 2.4 Loans Module
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 2.4.1 | View Loan Requests | 1. Go to Loans → Requests<br>2. Filter by status | Pending loans visible |
| 2.4.2 | HR Loan Approval | 1. Open loan request<br>2. Review details<br>3. Approve with comments | Status updated to HR-approved |
| 2.4.3 | View Loan Ledger | 1. Go to Loans → Ledger<br>2. Select employee | Complete loan history visible |

#### 2.5 Cross-Module: Payroll Integration
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 2.5.1 | Verify Attendance → Payroll | 1. Process attendance<br>2. Run payroll<br>3. Check calculations | Attendance data reflected in payroll |
| 2.5.2 | Verify Leave → Payroll | 1. Employee has approved leave<br>2. Run payroll<br>3. Check leave days | Leave days properly counted |
| 2.5.3 | Verify Loans → Payroll | 1. Employee has active loan<br>2. Run payroll<br>3. Check deductions | Loan deduction applied |

---

### TEST SUITE 3: Accounting Officer (Accounting + AP + AR + Tax)

**Account:** `acctg.officer@ogamierp.local` / `Officer@Test1234!`  
**Employee:** EMP-2026-0003 (Anna Marie Lim)

#### 3.1 Chart of Accounts
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 3.1.1 | View COA | 1. Login as Accounting Officer<br>2. Go to Accounting → Chart of Accounts | Full COA tree displayed |
| 3.1.2 | Add Account | 1. Click "Add Account"<br>2. Fill account details<br>3. Set parent<br>4. Save | Account created in correct position |
| 3.1.3 | Archive Account | 1. Select account<br>2. Archive<br>3. Confirm | Account archived, transactions preserved |

#### 3.2 Journal Entries
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 3.2.1 | Create JE | 1. Go to Accounting → Journal Entries<br>2. Click "New JE"<br>3. Add balanced lines<br>4. Submit | JE created with status "submitted" |
| 3.2.2 | Post JE | 1. Open JE list<br>2. Find submitted JE<br>3. Review and post | JE posted, GL updated |
| 3.2.3 | Recurring JE | 1. Create recurring template<br>2. Set schedule<br>3. Generate entries | Entries generated automatically |

#### 3.3 Accounts Payable (AP)
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 3.3.1 | Vendor Management | 1. Go to AP → Vendors<br>2. Add new vendor<br>3. Complete profile | Vendor created with code |
| 3.3.2 | Create Vendor Invoice | 1. Go to AP → Invoices<br>2. Create new invoice<br>3. Add line items<br>4. Submit | Invoice created, JE preview shown |
| 3.3.3 | Approve Invoice | 1. Open pending invoice<br>2. Review details<br>3. Approve | Invoice approved, JE posted |
| 3.3.4 | Record Payment | 1. Go to AP → Payments<br>2. Create payment<br>3. Apply to invoices<br>4. Process | Payment recorded, AP balance updated |
| 3.3.5 | AP Aging Report | 1. Go to AP → Reports → Aging<br>2. Generate report | Aging buckets displayed correctly |

#### 3.4 Accounts Receivable (AR)
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 3.4.1 | Customer Management | 1. Go to AR → Customers<br>2. Add new customer<br>3. Set credit limit | Customer created |
| 3.4.2 | Create Customer Invoice | 1. Go to AR → Invoices<br>2. Create invoice<br>3. Add items<br>4. Issue | Invoice issued, JE posted |
| 3.4.3 | Record Payment | 1. Go to AR → Payments<br>2. Receive payment<br>3. Apply to invoice | Payment applied, AR updated |
| 3.4.4 | AR Aging Report | 1. Go to AR → Reports → Aging<br>2. Generate | Aging buckets (current, 1-30, 31-60, etc.) |

#### 3.5 Tax Module
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 3.5.1 | View VAT Ledger | 1. Go to Tax → VAT Ledger<br>2. Select period | Input/output VAT summary |
| 3.5.2 | BIR Filing | 1. Go to Tax → BIR Filing<br>2. Generate Form 2550M<br>3. Export | Form generated with correct data |

#### 3.6 Cross-Module: Payroll → GL
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 3.6.1 | Approve Payroll | 1. Go to Payroll → Runs<br>2. Open pending run<br>3. Review and approve | Payroll approved, JE created |
| 3.6.2 | Verify Payroll JE | 1. Go to Accounting → Journal Entries<br>2. Find payroll JE<br>3. Verify lines | Dr Salaries Expense, Cr Payables |
| 3.6.3 | Post Payroll JE | 1. Open payroll JE<br>2. Review<br>3. Post to GL | GL updated with payroll amounts |

#### 3.7 Cross-Module: AP → GL
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 3.7.1 | Verify AP Invoice JE | 1. Create AP invoice<br>2. Check JE created<br>3. Verify account codes | Dr Expense, Cr AP |
| 3.7.2 | Verify Payment JE | 1. Record AP payment<br>2. Check JE<br>3. Verify | Dr AP, Cr Cash |

---

### TEST SUITE 4: Production Manager (Production + Inventory + Maintenance)

**Account:** `prod.manager@ogamierp.local` / `Production_manager@Test1234!`

#### 4.1 Bill of Materials (BOM)
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 4.1.1 | Create BOM | 1. Login as Prod Manager<br>2. Go to Production → BOM<br>3. Create new BOM<br>4. Add components | BOM created with version |
| 4.1.2 | View BOM | 1. Open BOM list<br>2. Select product BOM<br>3. View tree | Component hierarchy displayed |
| 4.1.3 | Update BOM Version | 1. Open active BOM<br>2. Revise<br>3. Update components<br>4. Activate new version | New version active, old archived |

#### 4.2 Production Orders
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 4.2.1 | Create Production Order | 1. Go to Production → Orders<br>2. Create new order<br>3. Select BOM<br>4. Set quantity<br>5. Save | Production order created |
| 4.2.2 | Release Order | 1. Open draft order<br>2. Check material availability<br>3. Release order | Status changed to "released" |
| 4.2.3 | Record Production Output | 1. Open released order<br>2. Record completion qty<br>3. Save | Order updated, FG stock increased |
| 4.2.4 | Close Order | 1. Open completed order<br>2. Close<br>3. Confirm | Order closed, variances recorded |

#### 4.3 Inventory Management
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 4.3.1 | View Stock Balances | 1. Go to Inventory → Stock<br>2. View by location | Real-time stock levels |
| 4.3.2 | Material Requisition | 1. Create MR<br>2. Add items<br>3. Submit for approval | MR created, pending approval |
| 4.3.3 | Goods Receipt | 1. Go to Inventory → Goods Receipts<br>2. Create GR from PO<br>3. Confirm receipt | Stock increased, ledger updated |
| 4.3.4 | Stock Adjustment | 1. Go to Inventory → Adjustments<br>2. Create adjustment<br>3. Add reason<br>4. Post | Stock adjusted, ledger entry |
| 4.3.5 | Stock Ledger | 1. Go to Inventory → Ledger<br>2. Filter by item<br>3. View history | All transactions visible |

#### 4.4 Maintenance
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 4.4.1 | Equipment List | 1. Go to Maintenance → Equipment<br>2. View list | Equipment with status |
| 4.4.2 | Create Work Order | 1. Click "New Work Order"<br>2. Select equipment<br>3. Describe issue<br>4. Submit | Work order created |
| 4.4.3 | PM Schedule | 1. Go to Maintenance → PM Schedule<br>2. View upcoming<br>3. Mark complete | PM completed, next date updated |

#### 4.5 Cross-Module: Production → Inventory
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 4.5.1 | Material Issue | 1. Release production order<br>2. Create material issue<br>3. Deduct from stock | Raw materials reduced |
| 4.5.2 | Production Output | 1. Complete production<br>2. Record output<br>3. Verify stock | Finished goods increased |
| 4.5.3 | Cost Analysis | 1. Go to Production → Reports → Cost Analysis<br>2. Select period<br>3. Generate | Cost per unit calculated |

---

### TEST SUITE 5: Purchasing Officer (Procurement + AP + Inventory)

**Account:** `purchasing@ogamierp.local` / `Purchasing_officer@Test1234!`

#### 5.1 Purchase Requisitions
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 5.1.1 | Create PR | 1. Login as Purchasing Officer<br>2. Go to Procurement → PR<br>3. Create new PR<br>4. Add items<br>5. Submit | PR created with reference number |
| 5.1.2 | Track PR Status | 1. View PR list<br>2. Open submitted PR<br>3. Check approval chain | Current status visible |

#### 5.2 Purchase Orders
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 5.2.1 | Create PO from PR | 1. Go to approved PR<br>2. Click "Create PO"<br>3. Select vendor<br>4. Confirm | PO created, linked to PR |
| 5.2.2 | Send PO to Vendor | 1. Open PO<br>2. Review terms<br>3. Send to vendor | Status updated to "sent" |
| 5.2.3 | Track PO Delivery | 1. View PO list<br>2. Check delivery status<br>3. Update as received | Status reflects delivery |

#### 5.3 Vendor RFQ
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 5.3.1 | Create RFQ | 1. Go to Procurement → RFQ<br>2. Create new RFQ<br>3. Add items<br>4. Select vendors | RFQ sent to vendors |
| 5.3.2 | Compare Quotations | 1. Open RFQ<br>2. View vendor responses<br>3. Compare prices | Side-by-side comparison |
| 5.3.3 | Award RFQ | 1. Select winning vendor<br>2. Award<br>3. Create PO | PO created from RFQ |

#### 5.4 Cross-Module: Procurement → Inventory → AP
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 5.4.1 | Goods Receipt → Stock | 1. Receive goods<br>2. Create GR<br>3. Post GR | Stock increased |
| 5.4.2 | GR → AP Invoice | 1. Three-way match<br>2. Create vendor invoice<br>3. Verify amounts | AP invoice created |
| 5.4.3 | Full Procurement Cycle | 1. PR → PO → GR → Invoice → Payment | Complete workflow successful |

---

### TEST SUITE 6: Executive/VP (Dashboard + Approvals)

**Account:** `vp@ogamierp.local` / `Vp@Test1234!`

#### 6.1 Approvals Dashboard
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 6.1.1 | View Pending Approvals | 1. Login as VP<br>2. Go to Approvals → Pending | All pending items across modules |
| 6.1.2 | Approve Leave Request | 1. Find leave request<br>2. Review details<br>3. Approve | Status updated, notification sent |
| 6.1.3 | Approve Purchase Request | 1. Find PR<br>2. Review justification<br>3. Approve | PR approved for PO creation |
| 6.1.4 | Approve Loan | 1. Find loan request<br>2. Review capacity<br>3. Approve | Loan approved for disbursement |
| 6.1.5 | Bulk Approval | 1. Select multiple items<br>2. Bulk approve<br>3. Confirm | All selected approved |

#### 6.2 Executive Dashboard
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 6.2.1 | View Company Metrics | 1. Go to Dashboard<br>2. View executive view | Headcount, costs, trends |
| 6.2.2 | Department Comparison | 1. Select departments<br>2. Compare metrics | Side-by-side comparison |
| 6.2.3 | Pending Approvals Widget | 1. View dashboard<br>2. Check approvals widget | Count of pending approvals |
| 6.2.4 | Financial Summary | 1. View financial widget<br>2. Check AP/AR aging | Current financial position |

---

### TEST SUITE 7: Staff Employee (Self-Service)

**Account:** `staff@ogamierp.local` / `Staff@Test1234!`

#### 7.1 Employee Self-Service
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 7.1.1 | View Own Profile | 1. Login as Staff<br>2. Go to My Profile | Personal info, employment details |
| 7.1.2 | Submit Leave Request | 1. Go to Leave → Apply<br>2. Select dates<br>3. Add reason<br>4. Submit | Request submitted, pending approval |
| 7.1.3 | View Leave Balance | 1. Go to Leave → Balance | Current balances displayed |
| 7.1.4 | Submit Overtime Request | 1. Go to Attendance → Overtime<br>2. Request OT<br>3. Submit | OT request pending |
| 7.1.5 | View Payslips | 1. Go to Payroll → Payslips<br>2. Select period<br>3. View/Download | Payslip displayed |
| 7.1.6 | Update Personal Info | 1. Go to Profile<br>2. Edit contact info<br>3. Save | Changes saved (if allowed) |

---

### TEST SUITE 8: Vendor Portal (External)

**Account:** `vendor@ogamierp.local` / `Vendor@Test1234!`

#### 8.1 Vendor Self-Service
| # | Test Case | Steps | Expected Result |
|---|-----------|-------|-----------------|
| 8.1.1 | Login to Portal | 1. Navigate to vendor portal<br>2. Login with credentials | Vendor dashboard loads |
| 8.1.2 | View Purchase Orders | 1. Go to POs section | List of POs sent to vendor |
| 8.1.3 | Acknowledge PO | 1. Open PO<br>2. Acknowledge receipt<br>3. Confirm | Status updated |
| 8.1.4 | Submit Delivery Note | 1. Create delivery note<br>2. Add items<br>3. Submit | Delivery note created |
| 8.1.5 | Submit Invoice | 1. Create invoice<br>2. Reference PO<br>3. Submit | Invoice submitted for approval |
| 8.1.6 | View Payment Status | 1. Go to Payments section | Payment history and status |

---

## 🔀 Complex Cross-Module Workflows

### WORKFLOW 1: New Hire to First Payroll
**Roles Involved:** HR Manager, Accounting Officer  
**Duration:** Full payroll cycle

| Step | Module | Action | Role | Verification |
|------|--------|--------|------|--------------|
| 1 | HR | Create employee record | HR Manager | Employee code generated |
| 2 | HR | Complete onboarding | HR Manager | Documents uploaded |
| 3 | HR | Set salary and bank info | HR Manager | Rate saved |
| 4 | HR | Create user account | Admin | Account linked to employee |
| 5 | Attendance | Record attendance | System | Daily logs created |
| 6 | Payroll | Add to payroll scope | Accounting | Employee in payroll run |
| 7 | Payroll | Process payroll | Accounting | Computed successfully |
| 8 | Accounting | Review payroll JE | Accounting | JE balanced |
| 9 | Accounting | Post to GL | Accounting | GL updated |
| 10 | Payroll | Generate payslip | System | Payslip available |

### WORKFLOW 2: Procurement to Payment
**Roles Involved:** Purchasing Officer, VP, Receiving Staff, Accounting Officer  
**Duration:** 2-4 weeks

| Step | Module | Action | Role | Verification |
|------|--------|--------|------|--------------|
| 1 | Procurement | Create PR | Staff | PR submitted |
| 2 | Procurement | Dept Head approves | Head | Status: approved |
| 3 | Procurement | Manager reviews | Manager | Status: reviewed |
| 4 | Procurement | VP approves | VP | Status: VP approved |
| 5 | Procurement | Create PO | Purchasing | PO from PR |
| 6 | Procurement | Send to vendor | Purchasing | Vendor notified |
| 7 | Inventory | Receive goods | Receiving | GR created |
| 8 | Inventory | Stock updated | System | Balance increased |
| 9 | AP | Create invoice | Vendor/AP | Invoice linked to GR |
| 10 | AP | Approve invoice | Accounting | Status: approved |
| 11 | AP | Schedule payment | Accounting | Payment scheduled |
| 12 | AP | Process payment | Accounting | Payment recorded |
| 13 | GL | Verify entries | Accounting | Dr Expense, Cr Cash |

### WORKFLOW 3: Production Order to Finished Goods
**Roles Involved:** Production Manager, Warehouse Staff, Accounting Officer

| Step | Module | Action | Role | Verification |
|------|--------|--------|------|--------------|
| 1 | Production | Create BOM | Production | BOM version active |
| 2 | Production | Create work order | Production | Order created |
| 3 | Production | Release order | Production | Materials reserved |
| 4 | Inventory | Issue materials | Warehouse | Stock deducted |
| 5 | Production | Record output | Production | FG produced |
| 6 | Inventory | Receive FG | Warehouse | FG stock increased |
| 7 | Inventory | Stock ledger | System | Both entries recorded |
| 8 | Costing | Calculate cost | Accounting | Unit cost computed |

---

## ✅ Testing Checklist Template

### Before Testing
- [ ] Database freshly seeded with `php artisan db:seed`
- [ ] All test accounts verified accessible
- [ ] Cache cleared
- [ ] Test data verified present

### During Testing
- [ ] Login with each role successfully
- [ ] Verify permissions (what should be visible/hidden)
- [ ] Test CRUD operations where applicable
- [ ] Verify cross-module data flow
- [ ] Check audit trails

### After Testing
- [ ] No critical errors in logs
- [ ] All test data clean (or reset)
- [ ] Performance acceptable
- [ ] SoD rules enforced correctly

---

## 📞 Issue Reporting Template

When reporting issues found during testing:

```
**Module:** [e.g., HR, Payroll, etc.]
**Role:** [e.g., hr.manager@ogamierp.local]
**Test Case:** [Reference number from this guide]
**Steps to Reproduce:**
1. Step one
2. Step two

**Expected Result:** 
**Actual Result:**
**Error Message:** [if any]
**Screenshots:** [attach if applicable]
**Severity:** [Critical/High/Medium/Low]
```

---

*Last Updated: 2026-03-16*  
*Test Accounts: Verified in RolePermissionSeeder, TestAccountsSeeder, SampleDataSeeder, ExtraAccountsSeeder*
