# Ogami ERP — Core Abilities Plan
**Date:** 2026-04-04  
**Scope:** Core process improvements only. No new modules, no fancy features.

---

## 1. Approval Notifications
**Why:** Every multi-step workflow (leave, loan, payroll, PR, MRQ, recruitment) requires approvers to know when something is waiting for them. Without this, testers must manually check each queue.

**What it does:** When a record enters an approval step, the next approver receives an in-app notification via the existing `NotificationBell` component.

**Affected workflows:** Leave, Overtime, Loan (5-stage), PR, MRQ, Payroll Run, Recruitment.

**Files:**
```
app/Domains/Shared/Notifications/PendingApprovalNotification.php  — fromModel() factory, queued
Trigger: fire after each status transition inside domain services
Frontend: NotificationBell already exists — just needs the count badge per pending item
```

---

## 2. 13th Month Pay Computation
**Why:** Philippine law (PD 851). Every company is required to pay it. A payroll system without 13th month is incomplete for a local ERP thesis.

**What it does:** Computes each employee's 13th month pay based on total basic pay earned during the calendar year, prorated for partial-year employees.

**Files:**
```
app/Domains/Payroll/Services/ThirteenthMonthService.php
frontend/src/pages/payroll/ThirteenthMonthPage.tsx
Route: GET /api/v1/payroll/13th-month
database/seeders/RolePermissionSeeder.php  — add payroll.compute_13th_month permission
```

---

## 3. Final Pay / Employee Clearance
**Why:** When an employee is separated (resigned, terminated), HR must compute their final pay: prorated salary, unused leave conversion, loan balance deduction, and return of accountabilities. `EmployeeClearancePage.tsx` already exists but the backend computation is incomplete.

**What it does:** Completes the clearance computation backend and wires it to the existing page.

**Files:**
```
app/Domains/HR/Services/ClearanceService.php  — extend existing, add computeFinalPay()
Route: POST /api/v1/hr/employees/{ulid}/clearance/compute
frontend/src/pages/hr/EmployeeClearancePage.tsx  — already exists, wire to backend
```

---

## 4. PDF Export — Payslips and Government Reports
**Why:** `payroll.download_own_payslip` and `payroll.gov_reports` permissions are seeded. Employees and HR need actual downloadable documents, not just screen views. Government reports (BIR 2316, SSS SBR-2) are legally required.

**What it does:** Generates properly formatted PDFs for payslips and all government-mandated reports.

**Files:**
```
app/Domains/Payroll/Services/PayslipPdfService.php
app/Domains/Payroll/Services/GovReportExportService.php  — BIR 2316, SSS SBR-2, PhilHealth RF-1, Pag-IBIG MC
Route: GET /api/v1/payroll/payslips/{ulid}/pdf
Route: GET /api/v1/reports/government/{type}/download
```

---

## 5. SoD Violation Report
**Why:** The entire RBAC system is built around Separation of Duties. For a thesis, you need to be able to demonstrate it is actually enforced — not just claim it. This report shows the committee that no user approved their own record.

**What it does:** Queries all approval-chain tables and surfaces any record where `created_by_id === approved_by_id`. Result should always be zero violations.

**Files:**
```
app/Domains/Shared/Services/SodComplianceService.php
frontend/src/pages/admin/SodComplianceReportPage.tsx
Route: GET /api/v1/admin/sod-violations
```

---

## 6. Excel Export on Major List Pages
**Why:** Permissions are already seeded (`employees.export`, `journal_entries.export`, `vendor_invoices.export`, `customer_invoices.export`, `attendance.export`). The `ExportButton` component already exists. Without export, the system feels incomplete for business use.

**What it does:** Wires the existing `ExportButton` component to each list page that has a corresponding export permission.

**Pages to wire:**
- Employee List → `employees.export`
- Attendance List → `attendance.export`
- Journal Entries → `journal_entries.export`
- Vendor Invoices → `vendor_invoices.export`
- Customer Invoices → `customer_invoices.export`

**Files:**
```
Each domain Service gets:  exportToExcel(Collection $rows): StreamedResponse
frontend/src/components/ui/ExportButton.tsx  — already exists, just needs endpoint prop per page
```

---

## Summary

| # | Feature | Core Reason |
|---|---------|-------------|
| 1 | Approval Notifications | Workflows are unusable without knowing when to act |
| 2 | 13th Month Pay | Philippine legal requirement |
| 3 | Final Pay / Clearance | Core HR offboarding process |
| 4 | PDF Payslips & Gov Reports | Legal documents employees and BIR require |
| 5 | SoD Violation Report | Proves the RBAC controls actually work |
| 6 | Excel Export | Standard expectation for any business system |
