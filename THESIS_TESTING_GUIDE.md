# Ogami ERP — Thesis Presentation Testing Guide

> **Database Setup:** Run `php artisan migrate:fresh --seed` before the presentation.
> **Start servers:** Terminal 1: `npm run dev` | Terminal 2 (optional): `php artisan queue:work`
> **Frontend URL:** http://localhost:5173

---

## 🔑 Login Credentials (Key Demo Accounts)

| Role | Email | Password | Use For |
|------|-------|----------|---------|
| **Admin** | `admin@ogamierp.local` | `Admin@1234567890!` | Full system access |
| **VP** | `demo.approver@ogamierp.local` | `DemoVP@1234!` | Final approvals (payroll, PRs, loans) |
| **HR Manager** | `demo.hr@ogamierp.local` | `DemoHr@1234!` | HR, recruitment, leave, payroll |
| **HR Head** | `demo.hr.head@ogamierp.local` | `DemoHrHead@1234!` | Leave/loan first-level approval |
| **HR Staff** | `demo.hr.staff@ogamierp.local` | `DemoHrStaff@1234!` | File leave/loan requests |
| **Accounting Manager** | `demo.acctg@ogamierp.local` | `DemoAcctg@1234!` | JEs, vendor invoices, payroll approval |
| **Production Manager** | `demo.prod@ogamierp.local` | `DemoProd@1234!` | Production, BOMs |
| **Purchasing Officer** | `demo.proc.officer@ogamierp.local` | `DemoProcOfficer@1234!` | Purchase requests, POs |
| **QC Manager** | `demo.qc@ogamierp.local` | `DemoQc@1234!` | QC inspections |
| **Warehouse Manager** | `demo.wh@ogamierp.local` | `DemoWh@1234!` | Inventory, MRQs |
| **Sales Manager** | `demo.sales@ogamierp.local` | `DemoSales@1234!` | Client orders, CRM |
| **Vendor Portal** | `vendor.chemlube@ogamierp.local` | `DemoUser@1234!` | Vendor self-service |
| **Client Portal** | `client@ogami.test` | `DemoUser@1234!` | Client self-service |

---

## 📋 Demo Scripts

### 1. HR & Employee Management
**Login as:** HR Manager (`demo.hr@ogamierp.local`)

1. Go to **HR → Employees** — view the list of 25 employees across 14 departments
2. Click any employee to view their **201 file** (profile, government IDs, bank info)
3. Go to **HR → Departments** — show the organizational structure
4. Go to **HR → Positions** — show the position hierarchy and salary grades

---

### 2. Recruitment Lifecycle (Full Chain)
**Login as:** HR Manager (`demo.hr@ogamierp.local`)

1. Go to **Recruitment → Dashboard** — show pipeline overview
2. Go to **Recruitment → Requisitions** — 5 requisitions in different statuses (open/approved/closed)
3. Go to **Recruitment → Job Postings** — 10 published postings tied to requisitions
4. Go to **Recruitment → Applications** — 30 applications in various stages:
   - Filter by `new` → show fresh applications
   - Filter by `shortlisted` → show screened candidates
5. **LIVE DEMO: Schedule an Interview**
   - Click a `shortlisted` application
   - Click **Schedule Interview** → fill in date/time/interviewer
   - Show the email notification sent to candidate
6. Go to **Recruitment → Offers** — 5 offers (3 accepted, 1 sent, 1 draft)
7. Go to **Recruitment → Hirings** — 3 completed hirings

---

### 3. Leave Management (Approval Chain)
**Login as:** HR Staff (`demo.hr.staff@ogamierp.local`)

1. Go to **Leave → My Requests** — show existing leave requests at various stages
2. **LIVE DEMO: File a Leave Request**
   - Click **New Leave Request**
   - Select leave type (Vacation/Sick), dates, reason
   - Submit

**Switch to:** HR Head (`demo.hr.head@ogamierp.local`)

3. Go to **Leave → Approvals** — see the pending request
4. **Approve** the leave request — show the approval chain flow

**Switch to:** HR Manager (`demo.hr@ogamierp.local`)

5. Show the request now waiting for manager approval
6. **Approve** → show it moves to approved status

---

### 4. Loan Management (Approval Chain)
**Login as:** HR Staff (`demo.hr.staff@ogamierp.local`)

1. Go to **Loans → My Loans** — 5 loans at various stages (pending → active)
2. **LIVE DEMO:** Show how a loan progresses through approval
   - Pending loans await head → manager → VP approval

**Switch through approvers** to demonstrate multi-level approval

---

### 5. Payroll (Full Pipeline)
**Login as:** HR Manager (`demo.hr@ogamierp.local`)

1. Go to **Payroll → Payroll Runs** — 7 runs at every stage:
   - Draft, Scope Set, Processing, Computed, Submitted, HR Approved, Accounting Approved
2. **Walk through the payroll pipeline:**
   - Show a DRAFT run → explain scope setting
   - Show a COMPUTED run → explain computation results
   - Show HR APPROVED → explain segregation of duties
   - Show ACCTG APPROVED → explain VP final approval
3. **LIVE DEMO (if time allows):** Create a new payroll run
   - Set scope (department, date range)
   - Run computation
   - Submit for approval

**Switch to:** VP (`demo.approver@ogamierp.local`)

4. Show **VP Approval** on the ACCTG APPROVED run
5. Show **Disburse** and **Publish** actions

---

### 6. Procurement (Full Chain)
**Login as:** Purchasing Officer (`demo.proc.officer@ogamierp.local`)

1. Go to **Procurement → Purchase Requests** — 8 PRs at every stage:
   - Draft → Pending Review → Reviewed → Budget Verified → Approved
2. **LIVE DEMO:** Create a new PR
   - Add items, quantities, justification
   - Submit for review
3. Show the **SoD (Segregation of Duties)** — VP cannot approve their own PR
4. Go to **Procurement → Purchase Orders** — show approved PR → PO conversion
5. Go to **Procurement → Goods Receipts** — show receiving process

---

### 7. Inventory & Warehouse
**Login as:** Warehouse Manager (`demo.wh@ogamierp.local`)

1. Go to **Inventory → Items** — 14 item masters (raw materials, finished goods)
2. Go to **Inventory → Material Requisitions** — 6 MRQs at various stages
3. Show stock levels and warehouse locations
4. **LIVE DEMO:** Create a new MRQ for production consumption

---

### 8. Accounting & Finance
**Login as:** Accounting Manager (`demo.acctg@ogamierp.local`)

1. Go to **Accounting → Chart of Accounts** — 16 accounts (Assets, Liabilities, Revenue, OPEX)
2. Go to **Accounting → Journal Entries** — 3 JEs (draft, submitted, posted)
3. **LIVE DEMO:** Create a manual JE
   - Add debit/credit lines
   - Submit → Post (SoD: poster must differ from creator)
4. Go to **AP → Vendor Invoices** — 5 invoices at various stages
5. Go to **AP → Vendors** — 4 vendors with contact info
6. Go to **AR → Customers** — 4 customers

---

### 9. Production & Quality Control
**Login as:** Production Manager (`demo.prod@ogamierp.local`)

1. Go to **Production → BOMs** — 3 bill of materials
2. Go to **Production → Production Orders** — show the manufacturing workflow

**Switch to:** QC Manager (`demo.qc@ogamierp.local`)

3. Go to **QC → Inspections** — show quality control workflow
4. Go to **QC → NCRs** — show non-conformance reports

---

### 10. RBAC & Segregation of Duties (Key Feature)
**Login as:** Admin (`admin@ogamierp.local`)

1. Show **System → Roles & Permissions** — hierarchy: Staff → Head → Officer → Manager → VP
2. Show **department-scoped permissions** — each department has its own permission profile
3. **DEMO SoD constraints:**
   - VP cannot approve their own purchase request (PR-WF-SOD)
   - JE poster must differ from creator
   - Payroll HR approver must differ from Accounting approver

---

### 11. Audit Trail
**Login as:** Admin (`admin@ogamierp.local`)

1. Go to **System → Audit Logs** — show all tracked changes
2. Filter by module (HR, Accounting, etc.)
3. Show that every create/update/delete is logged with user, timestamp, and old/new values

---

### 12. Vendor & Client Portals
**Login as:** Vendor (`vendor.chemlube@ogamierp.local` / `DemoUser@1234!`)

1. Show vendor self-service portal
2. View POs assigned to them, submit invoices

**Login as:** Client (`client@ogami.test` / `DemoUser@1234!`)

3. Show client self-service portal
4. View orders, delivery status

---

## 🔧 Quick Recovery Commands

```bash
# Full reset (if data gets messed up during demo)
php artisan migrate:fresh --seed

# Clear caches
php artisan config:clear && php artisan cache:clear

# Restart servers
# Terminal 1:
npm run dev
# Terminal 2 (optional queue worker for emails/notifications):
php artisan queue:work
```

## 📊 Data Summary

| Module | Records | Details |
|--------|---------|---------|
| Employees | 25 | Across 14 departments, full hierarchy |
| Attendance | 587 | Jan-Feb 2026 daily logs |
| Leave Requests | 8 | Draft → Submitted → Approved → Rejected |
| Loans | 5 | Pending → Approved → Active |
| Payroll Runs | 7 | Draft → ACCTG Approved (full pipeline) |
| Purchase Requests | 8 | Draft → Approved (with SoD test) |
| Items | 14 | Raw materials + finished goods |
| Material Reqs | 6 | Various stages |
| Journal Entries | 3 | Draft → Submitted → Posted |
| Vendor Invoices | 5 | Various stages |
| Candidates | 10 | Filipino names, varied sources |
| Applications | 30 | New → Shortlisted → Interviewed |
| Interviews | 12 | With evaluations and scorecards |
| Job Offers | 5 | 3 accepted, 1 sent, 1 draft |
| Hirings | 3 | Complete with pre-employment checks |
