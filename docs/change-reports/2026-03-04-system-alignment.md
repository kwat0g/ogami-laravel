# Change Report — System Configuration Alignment
**Date:** March 4, 2026  
**Branch:** `main` (commit `dc8e6e9`)  
**Status:** ✅ Local only — pending QA/testing before push to origin

---

## Overview

This batch of changes aligns Ogami ERP with the company's actual operational configuration:
- Replace government/third-party loan types with company-only types at 0% interest
- Seed the four official work shift schedules
- Enforce the company's overtime policy (minimum 30 min, maximum 4 hours)
- Add a shift schedule selector to the employee creation form
- Wire up the backend API for managing employee shift assignments
- Fix the interest rate display bug in the employee loan portal

---

## 1. Loan Types — Replace & Cleanup

### Affected Files
| File | Change |
|------|--------|
| `database/seeders/LoanTypeSeeder.php` | **Rewritten** — 6 old types → 2 company types |
| `database/migrations/2026_03_04_110000_cleanup_loan_types.php` | **Created** — deactivates legacy types, upserts new ones |

### Before
The system had 6 loan types:
- SSS Salary Loan (with real SSS interest rate)
- SSS Calamity Loan
- Pag-IBIG MP2 Loan
- Pag-IBIG Calamity Loan
- Company Emergency Loan
- Company Salary Advance

### After
Only 2 loan types remain:

| Code | Name | Interest | Max Term | Max Amount |
|------|------|----------|----------|------------|
| `COMPANY_LOAN` | Company Loan | **0%** | 12 months | ₱150,000 |
| `CASH_ADVANCE` | Cash Advance | **0%** | 3 months | ₱50,000 |

### Notes
- Legacy loan types are **deactivated, not deleted** — existing loan records that reference them are preserved.
- The seeder uses `upsert` on `code`, making it idempotent (safe to re-run).
- Running `php artisan migrate` will execute the cleanup migration automatically.

---

## 2. Shift Schedules — Seed Company Schedules

### Affected Files
| File | Change |
|------|--------|
| `database/seeders/ShiftScheduleSeeder.php` | **Created** — seeds 4 official shifts |
| `database/seeders/DatabaseSeeder.php` | **Modified** — registered `ShiftScheduleSeeder` |

### Schedules Seeded

| Code | Name | Hours | Day/Night |
|------|------|-------|-----------|
| `SHIFT-0600-1400` | Day Shift (6AM–2PM) | 06:00–14:00 | Day |
| `SHIFT-0600-1800` | Extended Day Shift (6AM–6PM) | 06:00–18:00 | Day |
| `SHIFT-0800-1700` | Regular Day Shift (8AM–5PM) | 08:00–17:00 | Day |
| `SHIFT-1800-0600` | Night Shift (6PM–6AM) | 18:00–06:00 | Night ✓ |

All schedules:
- Mon–Fri (`work_days = "1,2,3,4,5"`)
- 60-minute break
- Night Shift has `crosses_midnight = true` and `is_night_shift = true`

### Notes
- Use `php artisan db:seed --class=ShiftScheduleSeeder` to seed independently.
- Upsert on `code` — safe to re-run.

---

## 3. Overtime Policy — Min/Max Enforcement

### Affected File
`app/Domains/Attendance/Services/OvertimeRequestService.php`

### Change

| Policy | Before | After |
|--------|--------|-------|
| Minimum OT | 1 minute | **30 minutes** |
| Maximum OT | 480 minutes (8 hours) | **240 minutes (4 hours)** |
| Error message | "between 1 and 480 minutes (8 hours)" | "between 30 and 240 minutes (4 hours)" |

### Code (line ~94)
```php
// Before
if ($requestedMinutes < 1 || $requestedMinutes > 480) {

// After
if ($requestedMinutes < 30 || $requestedMinutes > 240) {
```

---

## 4. Interest Rate Display Fix

### Affected File
`frontend/src/pages/employee/MyLoansPage.tsx` (line ~203)

### Problem
Loan interest rates are stored as decimals (e.g., `0.06` = 6%). The display was showing the raw decimal value, so `0%` loans appeared as `0%` (correct) but non-zero loans would show `0.06%` instead of `6%`.

### Fix
```tsx
// Before
{loan.interest_rate_annual}%

// After
{(loan.interest_rate_annual * 100).toFixed(0)}%
```

---

## 5. Shift Assignment API Routes

### Affected File
`routes/api/v1/attendance.php`

### New Endpoints (prefix: `/api/v1/attendance`)

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| `GET` | `employees/{employee}/shift-assignments` | `attendance.manage_shifts` | List all shift assignments for an employee |
| `POST` | `employees/{employee}/shift-assignments` | `attendance.manage_shifts` | Create a new shift assignment |
| `DELETE` | `shift-assignments/{assignment}` | `attendance.manage_shifts` | Delete a shift assignment |

### POST Payload
```json
{
  "shift_schedule_id": 1,
  "effective_from": "2026-03-04",
  "notes": "Optional note"
}
```

### Notes
- `created_by` is automatically set to the authenticated user's ID.
- The `employees` table has no direct `shift_schedule_id` column — assignments are managed through the `employee_shift_assignments` join table with a PostgreSQL GIST exclusion constraint preventing overlapping assignments.

---

## 6. EmployeeResource — `current_shift` Field

### Affected Files
| File | Change |
|------|--------|
| `app/Http/Resources/HR/EmployeeResource.php` | Added `current_shift` field |
| `app/Http/Controllers/HR/EmployeeController.php` | Eager-loads `shiftAssignments.shiftSchedule` in `show()` |

### New Field in Response
```json
{
  "current_shift": {
    "id": 5,
    "shift_schedule_id": 2,
    "shift_name": "Regular Day Shift (8AM–5PM)",
    "start_time": "08:00:00",
    "end_time": "17:00:00",
    "effective_from": "2026-01-15"
  }
}
```

Returns `null` if no active assignment exists. Only included when the `shiftAssignments` relation is loaded (i.e., on detail/show endpoints — not the list endpoint).

---

## 7. Frontend — TypeScript Types

### Affected File
`frontend/src/types/hr.ts`

### Changes
1. **Added** `EmployeeShiftAssignment` interface (after `ShiftSchedule`):
```ts
export interface EmployeeShiftAssignment {
  id: number
  employee_id: number
  shift_schedule_id: number
  effective_from: string
  effective_to: string | null
  notes: string | null
  created_by: number
  shift_schedule?: ShiftSchedule
}
```

2. **Extended** `Employee` interface with optional `current_shift` field:
```ts
current_shift?: {
  id: number
  shift_schedule_id: number
  shift_name: string | null
  start_time: string | null
  end_time: string | null
  effective_from: string
} | null
```

---

## 8. Frontend — Shift Assignment Hooks

### Affected File
`frontend/src/hooks/useAttendance.ts`

### New Hooks

| Hook | Method | Endpoint |
|------|--------|----------|
| `useEmployeeShiftAssignments(employeeId)` | GET | `/attendance/employees/{id}/shift-assignments` |
| `useAssignShift()` | POST | `/attendance/employees/{id}/shift-assignments` |
| `useDeleteShiftAssignment()` | DELETE | `/attendance/shift-assignments/{id}` |

- `useAssignShift` automatically invalidates both `['shift-assignments', employeeId]` and `['employee']` query keys so the employee detail refetches and shows the new `current_shift`.
- `useDeleteShiftAssignment` invalidates all `shift-assignments` and `employee` queries.

---

## 9. Employee Form — Work Schedule Section

### Affected File
`frontend/src/pages/hr/EmployeeFormPage.tsx`

### Changes

1. **Imports:** Added `useShifts` and `useAssignShift` from `@/hooks/useAttendance`.
2. **Schema:** Added optional `shift_schedule_id: z.coerce.number().int().positive().optional()` field.
3. **Hooks:** `useShifts(true)` (active only) and `useAssignShift()` instantiated.
4. **New section** "Work Schedule" inserted between Employment Details and Government IDs.
5. **`onSubmit` logic:**
   - On **create**: after employee is created, if a shift is selected, fires `useAssignShift` with the new employee's ID and the selected `shift_schedule_id`, using `date_hired` as `effective_from`.
   - On **edit**: shows read-only current shift info (name, time, effective date) with a note to use the Shift Assignments section for changes.
6. `shift_schedule_id` is stripped from the employee creation payload (it goes to the separate assignment endpoint, not the employee record).

### Create Mode UI
Dropdown showing all active shift schedules formatted as `{name} ({start_time}–{end_time})`.

### Edit Mode UI
Displays current active shift (if any) as read-only text. Includes a note pointing to the Shift Assignments panel for changes.

---

## How to Test Locally

### 1. Run the migration + seeders
```bash
php artisan migrate
php artisan db:seed --class=ShiftScheduleSeeder
php artisan db:seed --class=LoanTypeSeeder
```

Or fresh:
```bash
php artisan migrate:fresh --seed
```

### 2. Test Loan Types
- Navigate to **Loans → Loan Types** (admin panel)
- Verify only "Company Loan" and "Cash Advance" are active
- Check that existing employee loan records with old types are unaffected

### 3. Test Shift Assignment (Employee Create)
- Go to **HR → Employees → Add Employee**
- Scroll to the new **Work Schedule** section
- Select a shift (e.g., "Regular Day Shift (8AM–5PM)")
- Submit — verify the employee is created and the shift assignment is visible on their profile

### 4. Test Shift Assignment (Employee Edit)
- Open an existing employee
- The Work Schedule section should show their current shift (read-only)
- If no shift, nothing is shown

### 5. Test Overtime Policy
- File an OT request with duration < 30 minutes → expect rejection with updated error message
- File an OT request with duration > 4 hours (240 min) → expect rejection

### 6. Test Interest Rate Display
- Go to an employee's **My Loans** page
- Company loans at 0% should show "0%"
- Any historical loans at other rates should now display correctly (e.g., 6% not 0.06%)

---

## Files Changed Summary

| File | Type | Description |
|------|------|-------------|
| `app/Domains/Attendance/Services/OvertimeRequestService.php` | Modified | OT limits: 30–240 min |
| `app/Http/Controllers/HR/EmployeeController.php` | Modified | Eager-load shiftAssignments in show() |
| `app/Http/Resources/HR/EmployeeResource.php` | Modified | Add current_shift field |
| `database/migrations/2026_03_04_110000_cleanup_loan_types.php` | **New** | Deactivate legacy loan types |
| `database/seeders/DatabaseSeeder.php` | Modified | Register ShiftScheduleSeeder |
| `database/seeders/LoanTypeSeeder.php` | Modified | Only Company Loan + Cash Advance |
| `database/seeders/ShiftScheduleSeeder.php` | **New** | 4 company shift schedules |
| `frontend/src/hooks/useAttendance.ts` | Modified | 3 new shift assignment hooks |
| `frontend/src/pages/employee/MyLoansPage.tsx` | Modified | Fix interest rate display |
| `frontend/src/pages/hr/EmployeeFormPage.tsx` | Modified | Work Schedule section + assignment on create |
| `frontend/src/types/hr.ts` | Modified | EmployeeShiftAssignment type + current_shift on Employee |
| `routes/api/v1/attendance.php` | Modified | 3 new shift assignment API routes |
