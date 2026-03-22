# Ogami ERP - Manual RBAC & Workflow Testing Guide

Complete manual testing guide for verifying RBAC and workflows by role.

## Quick Start

1. Start servers: `php artisan serve` and `pnpm dev`
2. Login at: http://localhost:5173/login
3. Use test accounts below to verify each role

---

## Test Accounts Reference

| Role | Email | Password | Department |
|------|-------|----------|------------|
| **Production Manager** | prod.manager@ogamierp.local | Manager@12345! | PROD |
| **QC Manager** | qc.manager@ogamierp.local | Manager@12345! | QC |
| **Warehouse Head** | warehouse.head@ogamierp.local | Head@123456789! | WH |
| **HR Manager** | hr.manager@ogamierp.local | HrManager@12345! | HR |
| **Accounting Manager** | acctg.manager@ogamierp.local | Manager@12345! | ACCTG |
| **Purchasing Officer** | purchasing.officer@ogamierp.local | Officer@12345! | PROC |
| **VP** | vp@ogamierp.local | VicePresident@1! | ALL |
| **Plant Manager** | plant.manager@ogamierp.local | Manager@12345! | PLANT |
| **Production Head** | production.head@ogamierp.local | Head@123456789! | PROD |
| **Admin** | admin@ogamierp.local | Admin@12345! | SYSTEM |

---

## SECTION 1: Production Department Tests

### 1.1 Production Manager - prod.manager@ogamierp.local

**Login and verify sidebar shows:**
- ✅ Production
- ✅ QC / QA
- ✅ Inventory (MRQ only)
- ✅ Maintenance
- ✅ Mold
- ✅ Delivery
- ✅ Team Management
- ✅ Self Service

**CRITICAL - Should NOT see:**
- ❌ Payroll
- ❌ Human Resources (except Self Service)
- ❌ Accounting
- ❌ Banking
- ❌ Item Categories (Inventory)
- ❌ Stock Ledger (Inventory)

**Create Actions Test:**
1. Go to Production → Orders
   - ✅ Should see "New Order" or "Create" button
   - ✅ Can create production order

2. Go to Inventory → Items
   - ❌ Should NOT see "New Item" button
   - ❌ Should see 403 Forbidden OR no create button

3. Try direct URL access:
   - http://localhost:5173/hr/payroll → Should redirect to 403 or Dashboard
   - http://localhost:5173/inventory/categories → Should show 403 Forbidden

---

### 1.2 Production Head - production.head@ogamierp.local

**Login and verify sidebar shows:**
- ✅ Production
- ✅ Team Management
- ✅ QC / QA
- ✅ Self Service

**Should NOT see:**
- ❌ Inventory (except Self Service)
- ❌ Payroll
- ❌ Accounting
- ❌ Banking

**Permissions:**
- Can view production orders
- Can view team attendance
- Can approve/reject team leave requests
- CANNOT create production orders (view only)

---

## SECTION 2: Warehouse Department Tests

### 2.1 Warehouse Head - warehouse.head@ogamierp.local

**Login and verify sidebar shows:**
- ✅ Team Management
- ✅ Inventory (FULL ACCESS)
- ✅ Delivery
- ✅ Self Service

**CRITICAL - Inventory Access:**
1. Go to Inventory → Items
   - ✅ Should see list of items
   - ✅ Should see "New Item" or "Add Item" button
   - ✅ Can create new item

2. Go to Inventory → Categories
   - ✅ Should see categories list
   - ✅ Can create/edit categories

3. Go to Inventory → Stock Ledger
   - ✅ Should see stock movements

4. Go to Inventory → Adjustments
   - ✅ Should see adjustments page
   - ✅ Can create stock adjustments

**Should NOT see:**
- ❌ Payroll
- ❌ Production
- ❌ Accounting (except AP invoices for received goods)

---

## SECTION 3: QC Department Tests

### 3.1 QC Manager - qc.manager@ogamierp.local

**Login and verify sidebar shows:**
- ✅ QC / QA
- ✅ Production (view only)
- ✅ Maintenance
- ✅ Mold
- ✅ Delivery
- ✅ ISO / IATF
- ✅ Team Management
- ✅ Self Service

**CRITICAL - Can access Production:**
1. Go to Production → Orders
   - ✅ Can view production orders
   - ✅ Can see QC inspection links
   - ❌ Cannot create/modify orders

2. Go to QC → Inspections
   - ✅ Should see "New Inspection" button
   - ✅ Can create quality inspections

3. Go to QC → NCR (Non-Conformance Reports)
   - ✅ Can create NCRs
   - ✅ Can close NCRs

**Should NOT see:**
- ❌ Payroll
- ❌ Accounting
- ❌ Inventory (except view MRQ)

---

## SECTION 4: HR Department Tests

### 4.1 HR Manager - hr.manager@ogamierp.local

**Login and verify sidebar shows:**
- ✅ Human Resources
- ✅ Team Management
- ✅ Attendance
- ✅ Payroll
- ✅ Leave Management
- ✅ Loans
- ✅ Recruitment
- ✅ Self Service

**CRITICAL - Full HR Access:**
1. Go to HR → Employees
   - ✅ Should see employee list
   - ✅ Should see "New Employee" button
   - ✅ Can create employees
   - ✅ Can view full employee records

2. Go to Payroll → Runs
   - ✅ Should see payroll runs
   - ✅ Can create payroll runs
   - ✅ Can process payroll

3. Go to Attendance → Logs
   - ✅ Can view all attendance logs
   - ✅ Can generate reports

**Should NOT see:**
- ❌ Production
- ❌ Inventory
- ❌ Accounting (except payroll-related)
- ❌ QC / QA

---

### 4.2 HR Staff - hr.staff@ogamierp.local

**Login and verify:**
- ✅ Self Service only
- ✅ Can view own payslip
- ✅ Can file leave requests
- ✅ Can view own attendance

**Should NOT see:**
- ❌ Other employees' data
- ❌ Payroll processing
- ❌ Any admin functions

---

## SECTION 5: Accounting Department Tests

### 5.1 Accounting Manager - acctg.manager@ogamierp.local

**Login and verify sidebar shows:**
- ✅ Team Management
- ✅ Accounting
- ✅ Payables (AP)
- ✅ Receivables (AR)
- ✅ Banking
- ✅ Fixed Assets
- ✅ Budget
- ✅ Financial Reports
- ✅ Tax
- ✅ Self Service

**CRITICAL - Full Accounting Access:**
1. Go to Accounting → Journals
   - ✅ Can view journal entries
   - ✅ Can create journal entries
   - ✅ Can post journals

2. Go to AP → Invoices
   - ✅ Can view vendor invoices
   - ✅ Can create vendor invoices
   - ✅ Can process payments

3. Go to Banking → Accounts
   - ✅ Can view bank accounts
   - ✅ Can record transactions
   - ✅ Can reconcile accounts

4. Go to Financial Reports
   - ✅ Can generate Balance Sheet
   - ✅ Can generate Income Statement
   - ✅ Can generate Cash Flow

**Should NOT see:**
- ❌ Production
- ❌ Inventory (except item costs)
- ❌ QC / QA
- ❌ Payroll (except accounting view)

---

### 5.2 Accounting Officer - accounting@ogamierp.local

**Login and verify:**
- ✅ Banking (primary access)
- ✅ AP (limited)
- ✅ Can record transactions
- ❌ Cannot approve journal entries
- ❌ Cannot generate financial reports

---

## SECTION 6: Procurement Department Tests

### 6.1 Purchasing Officer - purchasing.officer@ogamierp.local

**Login and verify sidebar shows:**
- ✅ Procurement
- ✅ Payables (AP)
- ✅ Self Service

**CRITICAL - Procurement Workflow:**
1. Go to Procurement → Purchase Requests
   - ✅ Can view PRs
   - ✅ Can create PRs
   - ✅ Can submit for approval

2. Go to Procurement → Purchase Orders
   - ✅ Can view POs
   - ✅ Can create POs (from approved PRs)

3. Go to AP → Vendor Invoices
   - ✅ Can view invoices
   - ✅ Can match invoices to POs/GRNs

**Should NOT see:**
- ❌ Payroll
- ❌ Production
- ❌ Inventory management
- ❌ Accounting journals

---

## SECTION 7: Executive Roles Tests

### 7.1 VP - vp@ogamierp.local

**Login and verify sidebar shows:**
- ✅ VP Approvals (special module)
- ✅ Dashboard (all departments view)
- ✅ Production
- ✅ QC / QA
- ✅ Maintenance
- ✅ Delivery
- ✅ Inventory
- ✅ Reports
- ✅ Self Service

**CRITICAL - VP Approval Authority:**
1. Go to VP Approvals
   - ✅ Should see pending approvals from all departments
   - ✅ Can approve high-value PRs (>100k)
   - ✅ Can approve payroll runs
   - ✅ Can approve journal entries

2. Go to Reports
   - ✅ Can view executive reports
   - ✅ Can view cross-department analytics

**Should NOT see:**
- ❌ Detailed HR records (salaries)
- ❌ Individual payroll details
- ❌ System admin functions

---

### 7.2 Plant Manager - plant.manager@ogamierp.local

**Login and verify sidebar shows:**
- ✅ Production
- ✅ QC / QA
- ✅ Maintenance
- ✅ Mold
- ✅ Delivery
- ✅ Inventory
- ✅ PPC (Production Planning)
- ✅ Team Management
- ✅ Reports
- ✅ Self Service

**CRITICAL - Multi-Department Access:**
- Can view all production-related modules
- Can approve production orders
- Can approve maintenance work orders
- Can view inventory levels
- CANNOT approve payroll
- CANNOT access accounting

---

## SECTION 8: Admin Role Tests

### 8.1 Admin - admin@ogamierp.local

**Login and verify sidebar shows:**
- ✅ System Administration ONLY
- ✅ Users
- ✅ Roles & Permissions
- ✅ Audit Log
- ✅ System Settings
- ✅ Backup/Restore

**CRITICAL - System Only:**
1. Go to Admin → Users
   - ✅ Can create users
   - ✅ Can assign roles
   - ✅ Can reset passwords

2. Try accessing business modules:
   - http://localhost:5173/hr/employees → 403 Forbidden
   - http://localhost:5173/production/orders → 403 Forbidden
   - http://localhost:5173/accounting/journals → 403 Forbidden

**Should NOT see:**
- ❌ Any business modules (HR, Production, Accounting, etc.)
- ❌ Payroll
- ❌ Financial data

---

## SECTION 9: Cross-Department Access Blocking

### Critical Tests - Direct URL Access

**Test 1: Production accessing Payroll**
1. Login as prod.manager@ogamierp.local
2. Navigate to: http://localhost:5173/hr/payroll
3. **Expected:** 403 Forbidden page OR redirect to Dashboard

**Test 2: Production accessing Inventory Categories**
1. Login as prod.manager@ogamierp.local
2. Navigate to: http://localhost:5173/inventory/categories
3. **Expected:** 403 Forbidden page

**Test 3: HR accessing Production**
1. Login as hr.manager@ogamierp.local
2. Navigate to: http://localhost:5173/production/orders
3. **Expected:** 403 Forbidden OR "Access Denied" message

**Test 4: Accounting accessing Production**
1. Login as acctg.manager@ogamierp.local
2. Navigate to: http://localhost:5173/production/orders
3. **Expected:** 403 Forbidden

**Test 5: Warehouse accessing Payroll**
1. Login as warehouse.head@ogamierp.local
2. Navigate to: http://localhost:5173/hr/payroll
3. **Expected:** 403 Forbidden

**Test 6: QC accessing Accounting Journals**
1. Login as qc.manager@ogamierp.local
2. Navigate to: http://localhost:5173/accounting/journals
3. **Expected:** 403 Forbidden

---

## SECTION 10: Workflow Approval Hierarchy

### 10.1 Purchase Request Approval Workflow

**Step 1: Create PR (Purchasing Officer)**
1. Login: purchasing.officer@ogamierp.local
2. Go to: Procurement → Purchase Requests
3. Click: "New Purchase Request"
4. Fill in:
   - Department: Production
   - Items: [Raw Material X, 100 units]
   - Justification: "For production batch #1234"
5. Submit PR
6. **Status:** PENDING_APPROVAL
7. **Expected:** Officer CANNOT approve their own PR (SoD)

**Step 2: Department Head Review**
1. Login: production.head@ogamierp.local
2. Go to: Procurement → Purchase Requests → Pending
3. Find the PR created above
4. **Action:** Can APPROVE or REJECT
5. Approve PR
6. **Status:** HEAD_APPROVED

**Step 3: VP Approval (if >100k)**
1. Login: vp@ogamierp.local
2. Go to: VP Approvals → Purchase Requests
3. Find the PR
4. **Action:** Can APPROVE or RETURN
5. Approve PR
6. **Status:** VP_APPROVED

**Step 4: Create PO (Purchasing Officer)**
1. Login: purchasing.officer@ogamierp.local
2. Go to: Procurement → Purchase Orders
3. Click: "Create from PR"
4. Select approved PR
5. Add vendor details
6. Submit PO
7. **Expected:** SoD check - same officer who created PR can create PO (verify if allowed)

---

### 10.2 Payroll Approval Workflow

**Step 1: HR Manager Creates Payroll Run**
1. Login: hr.manager@ogamierp.local
2. Go to: Payroll → Runs
3. Click: "New Payroll Run"
4. Select: Period (1-15 March 2026)
5. Compute payroll
6. **Status:** COMPUTED

**Step 2: HR Manager Submits for Approval**
1. Click: "Submit for Approval"
2. **Status:** SUBMITTED

**Step 3: VP Approves Payroll**
1. Login: vp@ogamierp.local
2. Go to: VP Approvals → Payroll
3. Review payroll summary
4. **Action:** Approve
5. **Status:** VP_APPROVED

**Step 4: Accounting Posts to GL**
1. Login: acctg.manager@ogamierp.local
2. Go to: Payroll → Approved Runs
3. Find approved payroll
4. Click: "Post to General Ledger"
5. **Expected:** Journal entries created automatically

**SoD Checks:**
- ✅ HR Manager CANNOT approve own payroll run
- ✅ VP approval required before posting to GL
- ✅ Accounting posts to GL (separate from HR)

---

### 10.3 Leave Request Approval Workflow

**Step 1: Employee Submits Leave**
1. Login: prod.staff@ogamierp.local
2. Go to: Self Service → Leave
3. Click: "New Leave Request"
4. Fill in:
   - Type: Vacation
   - Dates: March 20-22, 2026
   - Days: 3
5. Submit
6. **Status:** PENDING

**Step 2: Manager Approval**
1. Login: prod.manager@ogamierp.local
2. Go to: Leave → Team Requests
3. Find staff's leave request
4. **Action:** Approve
5. **Status:** MANAGER_APPROVED

**Step 3: HR Records**
1. Login: hr.manager@ogamierp.local
2. Go to: Leave → Approved Requests
3. Verify leave is recorded
4. Check leave balance updated

---

### 10.4 Production Order Workflow

**Step 1: Create Production Order**
1. Login: prod.manager@ogamierp.local
2. Go to: Production → Orders
3. Click: "New Production Order"
4. Fill in:
   - Product: [Finished Good SKU]
   - Quantity: 1000 units
   - Target Date: March 25, 2026
5. Save as DRAFT

**Step 2: Add BOM**
1. Select BOM for product
2. System shows required raw materials
3. Check inventory availability

**Step 3: Manager Approval**
1. Submit for approval
2. **SoD Check:** Production Manager can approve own order? (Verify)
   - If YES: Manager approves
   - If NO: Plant Manager approves
3. **Status:** APPROVED

**Step 4: Release to Production**
1. Click: "Release Order"
2. **Status:** RELEASED
3. Shop floor can start production

**Step 5: Record Output**
1. Production staff record daily output
2. QC inspections triggered automatically

**Step 6: Complete Order**
1. When quantity reached, mark as COMPLETED
2. Inventory updated automatically
3. Cost calculation triggered

---

## SECTION 11: Action Button Visibility Matrix

| Role | Production Orders | Inventory Items | PR/PO | Journal Entries | Employees |
|------|-------------------|-----------------|-------|-----------------|-----------|
| Production Manager | ✅ Create | ❌ No Create | ❌ No Access | ❌ No Access | ❌ No Access |
| Warehouse Head | ❌ View Only | ✅ Create | ❌ No Access | ❌ No Access | ❌ No Access |
| HR Manager | ❌ No Access | ❌ No Access | ❌ No Access | ❌ No Access | ✅ Create |
| Accounting Manager | ❌ No Access | ❌ View Only | ❌ View Only | ✅ Create | ❌ No Access |
| Purchasing Officer | ❌ No Access | ❌ View Only | ✅ Create | ❌ No Access | ❌ No Access |
| Plant Manager | ✅ Approve | ✅ View | ✅ Approve | ❌ No Access | ❌ View Team |
| VP | ✅ Approve | ❌ No Access | ✅ Approve | ✅ Approve | ❌ No Access |

---

## Test Result Tracking Sheet

Copy this template to track your manual tests:

```
Date: ___________
Tester: ___________

PRODUCTION DEPARTMENT
[ ] Production Manager - Sidebar correct
[ ] Production Manager - No Payroll access
[ ] Production Manager - No Inventory Categories access
[ ] Production Manager - Can create Production Orders
[ ] Production Manager - Cannot create Inventory items
[ ] Production Head - View only access

WAREHOUSE DEPARTMENT
[ ] Warehouse Head - Full Inventory access
[ ] Warehouse Head - Can create items/categories
[ ] Warehouse Head - No Payroll access
[ ] Warehouse Head - No Production access

QC DEPARTMENT
[ ] QC Manager - Can access QC and Production
[ ] QC Manager - Can create inspections
[ ] QC Manager - No Payroll access
[ ] QC Manager - No Accounting access

HR DEPARTMENT
[ ] HR Manager - Full HR access
[ ] HR Manager - Can create employees
[ ] HR Manager - Can process payroll
[ ] HR Manager - No Production access
[ ] HR Staff - Self Service only

ACCOUNTING DEPARTMENT
[ ] Accounting Manager - Full accounting access
[ ] Accounting Manager - Can create journals
[ ] Accounting Manager - Can generate reports
[ ] Accounting Manager - No Production access
[ ] Accounting Officer - Banking only

PROCUREMENT DEPARTMENT
[ ] Purchasing Officer - Can create PR/PO
[ ] Purchasing Officer - SoD - Cannot approve own PR
[ ] Purchasing Officer - No Payroll access

EXECUTIVE ROLES
[ ] VP - Can approve across departments
[ ] VP - VP Approvals module visible
[ ] Plant Manager - Multi-department view
[ ] Plant Manager - Can approve production orders

ADMIN ROLE
[ ] Admin - System admin only
[ ] Admin - Cannot access business modules
[ ] Admin - 403 on HR/Production/Accounting URLs

CROSS-CUTTING BLOCKS
[ ] Production → Payroll = 403
[ ] Production → Inventory Categories = 403
[ ] HR → Production = 403
[ ] Accounting → Production = 403
[ ] Warehouse → Payroll = 403
[ ] QC → Accounting = 403

WORKFLOW TESTS
[ ] PR Workflow: Officer → Head → VP (if >100k)
[ ] Payroll Workflow: HR → VP → Accounting
[ ] Leave Workflow: Staff → Manager → HR
[ ] Production Order: Create → Approve → Release → Complete

TOTAL PASSED: ___/___
```

---

## Quick Test Checklist (15 minutes)

If short on time, test these critical scenarios:

1. **Production Manager Login**
   - [ ] Sidebar shows Production, QC, Inventory (MRQ only)
   - [ ] No Payroll module
   - [ ] Direct URL to /hr/payroll = 403

2. **Warehouse Head Login**
   - [ ] Sidebar shows full Inventory
   - [ ] Can create inventory items
   - [ ] No Payroll module

3. **Cross-Department Block**
   - [ ] Production cannot access Payroll
   - [ ] Production cannot access Inventory Categories
   - [ ] HR cannot access Production

4. **Workflow Test (PR)**
   - [ ] Create PR as Purchasing Officer
   - [ ] Try to approve own PR (should fail SoD)
   - [ ] Approve as Production Head

5. **Admin Restricted**
   - [ ] Admin sees only System Admin
   - [ ] 403 on all business modules

---

## Troubleshooting

### Account Locked
```bash
php artisan tinker --execute="App\Models\User::where('email', 'prod.manager@ogamierp.local')->update(['failed_login_attempts' => 0, 'locked_until' => null]);"
```

### Reset All Manufacturing Accounts
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan db:seed --class=ManufacturingEmployeeSeeder
```

### Wrong Password Error
If login fails with correct password, re-run the seeder to update password hashes:
