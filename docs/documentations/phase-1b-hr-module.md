# Phase 1B — HR Module
## Sprints 5–8 · Weeks 9–16

**Goal:** Deliver the complete Employee, Attendance, Leave, and Loan management modules — the data producers that feed the payroll engine in Phase 1C.

---

## Sprint 5 — Employee Management
### Weeks 9–10

### What was built

**Employee CRUD**

Full create/read/update lifecycle for employee records. The `Employee` model belongs to `Department`, `Position`, and `EmploymentType` (regular, probationary, contractual, part-time).

**EMP Validation Rules (14 rules enforced)**

All rules are enforced at the `EmployeeService` layer (not just FormRequest) with machine-readable exception codes:

| Code | Rule |
|---|---|
| EMP-001 | Employee ID must be unique across the company |
| EMP-002 | Hire date cannot be in the future |
| EMP-003 | Probation end date must be after hire date |
| EMP-004 | SSS number must match 10-digit SSS format and be unique |
| EMP-005 | PhilHealth number must be 12 digits and unique |
| EMP-006 | Pag-IBIG MID must be 12 digits and unique |
| EMP-007 | TIN must match BIR format (XXX-XXX-XXX or XXX-XXX-XXX-XXX) and be unique |
| EMP-008 | Tax status (ME, S, S1…S4) must be a valid BIR status code |
| EMP-009 | Basic monthly salary cannot be below current regional minimum wage |
| EMP-010 | Daily rate must be consistent with monthly rate (daily = monthly / 26) |
| EMP-011 | Department must exist and be active |
| EMP-012 | Position must belong to the assigned department |
| EMP-013 | Immediate supervisor must be an active employee in the same or parent department |
| EMP-014 | Status transitions must follow the onboarding state machine |

**Government ID Encryption**

SSS number, PhilHealth number, Pag-IBIG MID, and TIN are **encrypted at rest** using PHP `openssl_encrypt` (AES-256-CBC) with a key stored in `.env`. The `Employee` model uses the `Encryptable` trait for transparent encrypt-on-store / decrypt-on-read. Display in the UI shows masked values (e.g., `XXX-XXX-1234`) to all roles except `admin`.

**Document Uploads (Spatie Medialibrary)**

Documents are organized into named collections:
- `government_ids` — SSS card, PhilHealth card, Pag-IBIG card, TIN card
- `employment_contracts` — signed employment contract PDF
- `201_file` — personal data sheet and supporting documents

Each collection has a `disk` (local disk, not public S3) and a `retention_years` policy stored in `system_settings`. Document access is gated by Laravel Storage authorization — staff can only access their own documents.

**Onboarding State Machine (`EmployeeStateMachine`)**

```
pending_docs → docs_submitted → hr_verified → active
                                            ↓
                              probationary_period → regularized
```

Each transition records the actor and timestamp in `audits`. Attempting to run payroll for an employee in `pending_docs` state throws `EMP-013: Employee not yet active`.

**Employee List Table (frontend)**

Built on TanStack Table 8:
- Server-side pagination (25/50/100 per page)
- Multi-column sorting
- Filters: department, status, employment type
- Column visibility toggle
- Excel/CSV export via `POST /api/employees/export` → Maatwebsite Excel

---

## Sprint 6 — Attendance & Shift Management
### Weeks 11–12

### What was built

**Shift Schedule Management**

`shifts` table stores named shift templates: `name` (e.g., "Day Shift"), `start_time`, `end_time`, `break_minutes`, and flags for `is_flexible`, `is_night_differential`. `employee_shifts` assigns a shift to an employee for a `date_from`/`date_to` range — supports mid-period shift changes.

**Attendance CSV Import Pipeline**

Biometric time-device exports are uploaded as CSV files via `POST /api/attendance/import`. The import runs as a queued job through a multi-step pipeline:

```
ParseCsv → NormalizeTimestamps → MatchEmployees → DetectAnomalies → PersistRecords → GenerateReport
```

Each step is a discrete class implementing `PipelineStep`. If any step fails, the job is marked `failed` and the partial results are discarded — no half-imported batches.

**ATT Validation Rules (10 rules)**

| Code | Rule |
|---|---|
| ATT-001 | Employee ID in CSV must match an active employee |
| ATT-002 | Timestamp must be a valid datetime — rejects malformed rows |
| ATT-003 | Time-in must precede time-out for the same day |
| ATT-004 | Duplicate punch (same employee, same timestamp ±2 min) is deduplicated |
| ATT-005 | Missing time-out is flagged as anomaly (not rejected — payroll uses shift end) |
| ATT-006 | Missing time-in is flagged as anomaly |
| ATT-007 | Attendance outside assigned shift window by >30 min triggers anomaly |
| ATT-008 | Night differential hours are auto-calculated (10 PM – 6 AM cross-shift) |
| ATT-009 | Rest day attendance requires supervisor approval before payroll |
| ATT-010 | Holiday attendance is tagged with holiday type (regular / special / double) from `holiday_calendars` |

**Anomaly Dashboard**

HR Supervisors see a real-time table of unflagged attendance anomalies per employee per day. Each anomaly has a `resolution` (approve, override with manual time, absent). Unresolved anomalies block the payroll pre-run checklist (PR-003).

---

## Sprint 7 — Leave Management
### Weeks 13–14

### What was built

**Leave Type Configuration**

`leave_types` table: `name`, `code`, `paid`, `accrual_basis` (monthly/annual/tenure), `accrual_amount`, `max_balance`, `carry_forward_allowed`, `carry_forward_max`, `convertible_to_cash` (SIL), `requires_document`.

System ships with 4 standard types pre-configured:
- **VL** (Vacation Leave) — 15 days/year, carry-forward up to 15 days
- **SL** (Sick Leave) — 15 days/year, requires medical certificate for ≥2 consecutive days
- **SIL** (Service Incentive Leave) — 5 days/year, convertible to cash if unused (RA 9715)
- **ML/PL** (Maternity/Paternity Leave) — statutory, non-accrual, document required

**Leave Balance Accrual Engine**

Scheduled job (`LeaveAccrualJob`) runs monthly. For each active employee:
1. Reads applicable `leave_type` accrual rules
2. Calculates earned days for the period (prorated for mid-month hires)
3. Applies tenure multipliers if configured
4. Inserts `leave_balance_transactions` row (type=`accrual`)
5. Updates `leave_balances.balance`

Carry-forward runs on January 1: balances above `carry_forward_max` are forfeited; the forfeited amount is logged as an `adjustment` transaction.

**Leave Request State Machine (`LeaveRequestStateMachine`)**

```
draft → submitted → supervisor_approved → hr_approved → active
                 ↓                     ↓             ↓
              rejected              rejected       cancelled
```

SoD-002: the employee who submitted the request cannot be the same user who approves it (checked at transition).

Supervisor can approve first-level; Manager performs final approval. Each transition sends an in-app notification (Reverb) and an email.

**Leave Calendar View**

Department-scoped calendar (React + Recharts) showing all approved leaves for the month. Managers see all departments they are scoped to. Supervisors see their direct reports only.

**SIL Monetization**

`SilMonetizationService::compute(Employee $employee, Carbon $year)`:
1. Gets SIL balance as of December 31
2. Unused days up to 5 are payable at daily rate
3. Daily rate = current basic monthly salary ÷ 26
4. Result is posted as `OTHER_ALLOWANCE` income component on December payroll run

---

## Sprint 8 — Loan Management
### Weeks 15–16

### What was built

**Loan Type Configuration**

`loan_types` table: `name` (SSS Salary Loan, Pag-IBIG MP2, Company Emergency Loan, etc.), `max_loanable_factor` (multiple of monthly salary), `max_term_months`, `annual_interest_rate`, `amortization_method` (`flat` or `diminishing`), `requires_government_benefit`.

**Amortization Schedule Generator (`AmortizationService`)**

Inputs: principal, annual rate, term months, start date.
Output: array of `{ period, due_date, principal, interest, total_payment, balance }`.

For flat-rate loans: monthly payment = (principal + total interest) ÷ term.  
For diminishing-balance loans: standard EMI formula.

The schedule is persisted to `loan_amortization_schedules` immediately on loan approval — it serves as the authoritative deduction plan for the payroll engine.

**Loan Approval Workflow (`LoanStateMachine`)**

```
draft → submitted → supervisor_reviewed → hr_approved → accounting_approved → active → closed
                                                                            ↓
                                                                         rejected
```

SoD-004: the employee who submitted the application cannot approve it at any stage.

**LN-007 — Minimum Wage Protection**

When a loan deduction would bring a staff member's net pay below the regional minimum wage for the period, the deduction is **partial** — only the amount that keeps net pay ≥ minimum wage is deducted. The unpaid balance carries forward to the next period. This is enforced in the deduction pipeline, not here (Sprint 10), but the flag `ln_007_flag` is set on the amortization row by `AmortizationService` on creation when risk is detected.

**Payroll Handoff**

`LoanDeductionExportService::toPayrollComponents(int $employeeId, Carbon $cutoff)` returns a typed array of `DeductionComponent` objects consumed by the payroll computation pipeline. No duplication of logic — the amortization schedule is the single source of truth.

---

## Phase 1B Summary

| Module | Delivered |
|---|---|
| Employee CRUD + 14 validation rules | ✅ |
| Government ID encryption at rest | ✅ |
| Document upload (Medialibrary, 3 collections) | ✅ |
| Onboarding state machine | ✅ |
| Employee list table (pagination, export) | ✅ |
| Shift management + employee shift assignment | ✅ |
| Attendance CSV import pipeline (5 steps) | ✅ |
| ATT-001–010 validation rules | ✅ |
| Anomaly dashboard + resolution workflow | ✅ |
| Leave type configuration (4 types) | ✅ |
| Monthly leave accrual engine | ✅ |
| Leave request state machine + SoD | ✅ |
| Leave calendar (department-scoped) | ✅ |
| SIL monetization service | ✅ |
| Loan type configuration | ✅ |
| Amortization schedule generator (flat + diminishing) | ✅ |
| Loan approval workflow + SoD | ✅ |
| LN-007 minimum wage protection flag | ✅ |
| Payroll deduction handoff | ✅ |

---

*Previous: [Phase 1A — Foundation](phase-1a-foundation.md) · Next: [Phase 1C — Payroll Engine](phase-1c-payroll-engine.md)*
