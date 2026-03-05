# Manager Workflow Guide — HR Department

**System:** Ogami Manufacturing Philippines Corp. ERP  
**Role:** `manager` (assigned to HR Department via RDAC)  
**Last Updated:** 2026-02  

---

## Table of Contents

1. [Employee Onboarding](#1-employee-onboarding)
2. [Attendance Management](#2-attendance-management)
3. [Leave Approval Workflow](#3-leave-approval-workflow)
4. [Payroll Pre-Run Checklist](#4-payroll-pre-run-checklist)
5. [Leave Balance Management](#5-leave-balance-management)
6. [SIL Monetization](#6-sil-monetization)
7. [Reports](#7-reports)

---

## 1. Employee Onboarding

### 1.1 Create the Employee Record

1. Navigate to **HR › Employees › New Employee**.
2. Fill in all required fields:
   - **Personal Info:** Legal name, date of birth, civil status, TIN, PhilHealth No., SSS No., PagIBIG MID.
   - **Employment Info:** Hire date, department, position, employment type (Regular / Probationary / Contractual), and cost center.
   - **Compensation:** Monthly rate and payment mode (semi-monthly).
3. Click **Save as Draft** — the employee record is created with status `inactive`.

> **Important:** An inactive employee does not accrue leave and is excluded from payroll runs.

### 1.2 Upload Supporting Documents

1. Open the employee record → **Documents** tab.
2. Upload required documents (PDF/JPEG, max 10 MB each):
   - Pre-employment medical certificate
   - Government IDs (TIN card, SSS card, PhilHealth card, PagIBIG card)
   - Signed employment contract
   - NBI clearance / Police clearance
3. Each upload is audit-logged with your name and timestamp.

### 1.3 Assign Work Shift

1. On the employee record → **Schedule** tab.
2. Select a shift template (e.g., *Regular Shift Mon–Fri 08:00–17:00*) or define a custom schedule.
3. Set the **effective date** (usually the same as hire date).

### 1.4 Activate the Employee

1. Click the **⋮ Actions** menu → **Activate**.
2. Confirm the dialog — the status changes to `active`.
3. The system automatically:
   - Creates leave balance buckets (VL, SL, SIL, Emergency Leave) for the current year.
   - Pro-rates initial balances based on remaining months in the year.
4. An audit log entry records the activation with actor and timestamp.

---

## 2. Attendance Management

### 2.1 Import Attendance via CSV

1. Navigate to **HR › Attendance › Import**.
2. Download the **CSV template** (columns: `employee_code`, `date`, `time_in`, `time_out`).
3. Populate the file from biometric device data.
4. Upload the CSV → click **Validate**.
5. Review the **Validation Report**:
   - ✅ Green rows — clean records ready to import.
   - ⚠️ Yellow rows — warnings (e.g., missing time-out; system will flag as half-day).
   - ❌ Red rows — errors (e.g., unknown employee code, future dates) — must be corrected.
6. Fix errors in your CSV and re-upload, or use the **inline editor** for minor corrections.
7. Click **Confirm Import** to commit records.

### 2.2 Manual Attendance Entry

For employees without biometric access (e.g., remote staff):

1. **HR › Attendance › Manual Entry**.
2. Select employee, date, time-in, time-out.
3. Add a justification note — required for audit.
4. Your supervisor role allows up to **7 days** retroactive entry.

### 2.3 Holiday Calendar

1. **HR › Calendars › Holidays**.
2. Declare public holidays:
   - **Regular Holiday** — 200% of daily rate. Examples: Rizal Day, Christmas Day.
   - **Special Non-Working Day** — 130% if worked. Example: EDSA Revolution Day.
   - **Special Working Day** — treated as an ordinary work day.
3. Holiday types must be set **before** payroll computation.

---

## 3. Leave Approval Workflow

### 3.1 Reviewing Pending Requests

1. Navigate to **HR › Leave › Requests** → filter **Status = Pending**.
2. Each card shows: employee name, leave type, dates, days consumed, running balance.
3. Click a request to open the detail view.

### 3.2 Approving a Leave Request

1. Review the reason and attached supporting documents (if required for sick leave ≥ 4 days).
2. Click **Approve**.
3. The employee's leave balance is immediately deducted.
4. An e-mail notification is sent to the employee (if mail is configured).

### 3.3 Rejecting a Leave Request

1. Click **Reject**.
2. Enter a **rejection reason** (required — shown to the employee).
3. The leave balance is not affected.

### 3.4 Cancelling an Approved Leave

Applicable before the leave start date:

1. Find the approved request → **⋮ Actions → Cancel**.
2. Provide a cancellation reason.
3. The deducted leave days are restored to the employee's balance.

> **Note:** Leave that has already started cannot be cancelled. Contact your system administrator.

---

## 4. Payroll Pre-Run Checklist

Run through this checklist **before** every payroll computation to avoid corrections:

| # | Item | Where to verify |
|---|------|----------------|
| 1 | All active employees have rates defined | HR › Employees — filter by missing `monthly_rate` |
| 2 | Attendance import complete for the cutoff | HR › Attendance › Import History |
| 3 | Holiday calendar updated | HR › Calendars › Holidays |
| 4 | Pending leave requests resolved | HR › Leave › Requests — filter Pending |
| 5 | One-time earning/deduction adjustments entered | Payroll › Adjustments |
| 6 | No employee in status `probationary` without confirmed SSS/PhilHealth/PagIBIG IDs | HR › Employees |
| 7 | Previous payroll run status is `approved` or `cancelled` (no dangling `draft` runs) | Payroll › Runs |
| 8 | Fiscal period for cutoff is `open` (not locked) | Accounting › Fiscal Periods |

### 4.1 Initiating the Payroll Run

1. **Payroll › Runs › New Run**.
2. Set **Cutoff From** and **Cutoff To** (e.g., 2026-02-01 → 2026-02-15).
3. Select **Payroll Type** = Regular (or 13th Month / Final Pay).
4. Click **Compute** — the payroll engine processes all active employees.
5. Review the **computation summary**: gross pay, statutory deductions, net pay per employee.
6. Flag individual records for **manual review** if the net pay appears anomalous.
7. When satisfied, click **Submit for Approval** → route to Payroll Approver.

---

## 5. Leave Balance Management

### 5.1 Viewing an Employee's Balance

1. **HR › Leave › Balances** → search by employee name or code.
2. Balances breakdown: earned, used, forfeited, carry-forward.

### 5.2 Manual Balance Adjustment

For corrections (e.g., erroneous deduction):

1. **HR › Leave › Balances** → open employee → **Adjust**.
2. Enter: adjustment amount (positive = add, negative = deduct), leave type, effective date, and reason.
3. All adjustments are audit-logged.

### 5.3 Year-End Leave Reset

Run this process **every December** before payroll:

1. **HR › Leave › Year-End Reset** (available Dec 1–31).
2. Choose: **Carry-forward** (max 5 VL days per DOLE) or **Reset to zero**.
3. SL unused days are forfeited unless policy states otherwise.
4. VL >= 5 days unused → eligible for monetization.

---

## 6. SIL Monetization

Service Incentive Leave (SIL) unused after January of the next year can be monetized.

### Process

1. **HR › Leave › SIL Monetization**.
2. The system lists all employees with unused SIL (minimum 5 days required to encash per DOLE rules).
3. Review the list → click **Generate Adjustment**.
4. The system creates a taxable **Earnings Adjustment** in the current payroll cycle.
5. Submit for payroll approval — the cash equivalent is included in the next run.

> **Formula:** `SIL Cash = (Monthly Rate / 26) × Unused SIL Days`

---

## 7. Reports

| Report | Location | Description |
|--------|----------|-------------|
| Employee Masterlist | HR › Reports › Masterlist | All active employees with compensation details |
| Attendance Summary | HR › Reports › Attendance | Late/absent/OT summary per cutoff |
| Leave Utilization | HR › Reports › Leave | Consumed vs. remaining balances |
| Headcount by Department | HR › Reports › Headcount | Current staffing per cost center |
| New Hires / Separations | HR › Reports › Movement | Employee movement for a date range |

All reports can be exported to **CSV** or **PDF**.

---

*For technical issues or access problems, contact your system administrator.*
