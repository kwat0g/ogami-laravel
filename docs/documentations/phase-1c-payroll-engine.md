# Phase 1C — Payroll Engine
## Sprints 9–12 · Weeks 17–24

**Goal:** Build a fully automated, auditable, Philippine-law-compliant payroll computation engine that produces payslips, government remittance reports, bank disbursement files, and BIR statutory filings.

---

## Sprint 9 — Value Objects, Computation Pipeline & Tax Engine
### Weeks 17–18

### What was built

**Value Objects**

All payroll math uses immutable value objects — no raw `float` arithmetic in services:

| Class | Description |
|---|---|
| `Money` | Wraps BCMath `bcadd`/`bcmul`/`bcsub` (6 decimal precision); PHP `float` prohibited in payroll code |
| `PayPeriod` | Encapsulates cutoff start/end dates + pay date; validates period type (semi-monthly, monthly) |
| `WorkingDays` | Given a `PayPeriod` and a `ShiftSchedule`, computes working days after subtracting holidays, leaves, and absences |
| `OvertimeMultiplier` | Reads applicable multiplier from `overtime_multiplier_configs` for a given day type and time range |

**Pre-Run Checklist Validation (PR-001–008)**

Before a payroll run transitions from `draft` to `processing`, `PayrollPreRunValidator` checks 8 conditions. Any failure blocks the run:

| Code | Check |
|---|---|
| PR-001 | Cutoff period does not overlap any existing completed run for the same employee group |
| PR-002 | All employees in scope are in `active` status |
| PR-003 | No unresolved attendance anomalies for the period |
| PR-004 | All approved leaves for the period are reconciled with attendance |
| PR-005 | Bank account details exist for all employees (for disbursement file) |
| PR-006 | Government contribution tables for the cutoff period are present |
| PR-007 | TRAIN tax brackets for the cutoff year are present |
| PR-008 | System setting `payroll_cutoff_day` matches the period being processed |

**17-Step Computation Pipeline**

Each step is a class implementing `PayrollPipelineStep`. Steps execute sequentially via Laravel Pipeline; any step can halt the pipeline by throwing `PayrollComputationException`:

| Step | Description |
|---|---|
| 1. `LoadEmployeeData` | Fetch employee record, salary, pay type, tax status |
| 2. `LoadAttendance` | Aggregate attendance records for the cutoff: present days, absences, late minutes, undertime |
| 3. `ComputeBasicPay` | Daily rate × actual working days (or fixed monthly if salaried) |
| 4. `ComputeAbsenceDeductions` | Absent days × daily rate; late/undertime converted to hours × hourly rate |
| 5. `ComputeOvertimePay` | Each OT record × hours × `OvertimeMultiplier` → grouped by type |
| 6. `ComputeHolidayPay` | Special holiday pay (+ premium if worked), regular holiday pay |
| 7. `ComputeNightDifferential` | Night diff hours × hourly rate × 0.10 (RA 9514 minimum) |
| 8. `ComputeAllowances` | Read fixed allowances from employee record + one-time adjustments |
| 9. `ComputeSSS` | Look up MSC bracket for this month's salary; split EE/ER |
| 10. `ComputePhilHealth` | Apply current year's premium rate; cap at maximum |
| 11. `ComputePagIBIG` | Apply tier rate; cap at PHP 200 EE + PHP 200 ER |
| 12. `ComputeTaxableIncome` | Gross pay − non-taxable allowances − EE govt contributions |
| 13. `ComputeWithholdingTax` | TRAIN Law formula on YTD taxable income, annualized method |
| 14. `ComputeLoanDeductions` | Read from amortization schedules; apply LN-007 protection |
| 15. `ComputeOtherDeductions` | Cash advances, uniform deductions, etc. |
| 16. `ApplyDeductionStack` | DED-001–004 priority ordering (see Sprint 10) |
| 17. `ComputeNetPay` | Gross − all deductions; final minimum wage floor check |

**TRAIN Law Tax Engine (TAX-001–009)**

Annualized withholding method (BIR Revenue Regulation 11-2018):

1. Annualize YTD taxable income based on months elapsed
2. Apply 2023 TRAIN brackets (0%, 15%, 20%, 25%, 30%, 35%)
3. Back-calculate monthly required withholding
4. Subtract taxes already withheld this year → monthly tax due

| Code | Rule |
|---|---|
| TAX-001 | Annual income ≤ PHP 250,000 → 0% |
| TAX-002 | PHP 250k–400k → PHP 0 + 15% of excess over 250k |
| TAX-003 | PHP 400k–800k → PHP 22,500 + 20% of excess over 400k |
| TAX-004 | PHP 800k–2M → PHP 102,500 + 25% of excess over 800k |
| TAX-005 | PHP 2M–8M → PHP 402,500 + 30% of excess over 2M |
| TAX-006 | PHP 8M+ → PHP 2,202,500 + 35% of excess over 8M |
| TAX-007 | 13th month pay and other statutory bonuses exempt up to PHP 90,000 |
| TAX-008 | De minimis benefits do not form part of gross compensation |
| TAX-009 | ME (Married Employee) additional exemption applied to tax status |

**Government Contributions**

SSS (05 rules), PhilHealth (04 rules), Pag-IBIG (04 rules) — each rule set mirrors the BIR/agency circular requirements. Employer share is tracked separately for accounting GL auto-posting.

---

## Sprint 10 — Deductions, Edge Cases & Payroll Run Workflow
### Weeks 19–20

### What was built

**Deduction Priority Stack (DED-001–004)**

When total deductions would exceed net pay, the stack determines which deductions survive:

| Priority | Category | Rule |
|---|---|---|
| 1 (highest) | Statutory (SSS, PhilHealth, Pag-IBIG) | DED-001: Never reduced |
| 2 | Withholding Tax | DED-002: Never reduced |
| 3 | Government loan deductions (SSS Salary Loan, Pag-IBIG MP2) | DED-003: Reduced pro-rata if floor breached |
| 4 (lowest) | Company loans and other deductions | DED-004: First to be cut; LN-007 applies |

**14 Edge Cases (EDGE-001–014)**

| Code | Scenario |
|---|---|
| EDGE-001 | New hire mid-period — basic pay prorated from hire date |
| EDGE-002 | Resigned employee — final pay computed to last working day; tax annualization still applies |
| EDGE-003 | Employee on unpaid leave — basic pay = 0; govt contributions still due on minimum basis |
| EDGE-004 | Employee transferred mid-period — department cost split |
| EDGE-005 | Salary change mid-period — prorate at two rates |
| EDGE-006 | 13th month pay — December run adds PHP 1/12 of annual basic × months worked |
| EDGE-007 | Retroactive pay — prior period correction posted as separate adjustment run |
| EDGE-008 | Night differential employee — ND computed per calendar day not per shift |
| EDGE-009 | Hazard pay for compressed workweek employees |
| EDGE-010 | More than one OT type in a single day — each computed separately |
| EDGE-011 | Holiday falling on rest day — double premium (200% of daily rate) |
| EDGE-012 | Employee with zero tax (tax-exempt MWE) — min wage earner exemption applied |
| EDGE-013 | Prior-year tax correction in January — reads prior year's bracket |
| EDGE-014 | Annualization December adjustment — true-up withholding to match annual liability |

**Payroll Run State Machine**

```
draft → pre_run_validated → processing → computed → locked → approved → disbursed
                                    ↓                       ↓
                                 failed                  rejected (→ draft)
```

- `draft → pre_run_validated`: PR-001–008 checks pass
- `validated → processing`: batch jobs dispatched
- `computed → locked`: Manager locks run (prevents edits)
- `locked → approved`: Manager approves (SoD: cannot be same user as the Manager who locked the run)
- `approved → disbursed`: bank disbursement file generated

**Batch Processing (Laravel Queue + Horizon)**

Each payroll run dispatches one `ProcessEmployeePayrollJob` per employee into the `payroll` queue. Horizon `supervisor-payroll` worker processes them with concurrency 5.

`PayrollRunBatch` (Laravel Bus Batch) collects all jobs; the batch completion callback transitions the run from `processing` to `computed` and sends HR notification.

**Post-Computation Integrity Checks**

After all employee jobs complete:
- Total ER govt contributions cross-checked against sum of individual rows
- Debit = Credit on payroll GL posting (enforced by DB trigger)
- No employee has negative net pay (circuit breaker)

---

## Sprint 11 — Payroll UI, Payslips, and Exports
### Weeks 21–22

### What was built

**Payroll Run UI**

Three-tab interface:
1. **Pre-Run Checklist** — live status of PR-001–008 with ✅/❌ and drill-down links
2. **Employee Summary** — TanStack Table with computed breakdown per employee; exportable; filterable by department
3. **Exception Report** — employees where LN-007 applied, partial deductions, or zero net pay

**Payslip PDF (DOMPDF)**

Generated via `PayslipPdfService::generate(int $runId, int $employeeId)`. PDF layout:
- Company letterhead (logo + info from `system_settings`)
- Employee info (name, position, department, tax status)
- Earnings section (basic pay, OT breakdown, allowances, 13th month if applicable)
- Government contributions (SSS, PhilHealth, Pag-IBIG — EE share)
- Tax (monthly WHT)
- Other deductions (loans, cash advance)
- **Net pay (large)**, YTD gross, YTD tax

**Payroll Register Excel (Maatwebsite)**

Multi-sheet Excel workbook export:
- Sheet 1: Summary per employee (all income + deduction columns)
- Sheet 2: Government contributions (SSS, PhilHealth, Pag-IBIG — both EE and ER)
- Sheet 3: Tax register (monthly WHT per employee + YTD)

**Bank Disbursement File**

`BankDisbursementService::generate(int $runId)` produces a flat-pipe-delimited text file in the format required by UnionBank/BDO/BPI (configurable in `system_settings`).

Columns: `account_number`, `account_name`, `amount`, `reference_number`.

**December Reconciliation (13TH-006)**

On the December run, `YearEndReconciliationService`:
1. Computes total annual tax due using the final annualized income
2. Subtracts total tax withheld Jan–Nov
3. Difference is collected/refunded on the December payslip
4. Ensures no employee over-withheld beyond BIR tolerance

---

## Sprint 12 — Government Reports & Self-Service
### Weeks 23–24

### What was built

**BIR Form 1601-C (Monthly Remittance)**

`BirForm1601CService::generate(int $year, int $month)` — PDF following BIR prescribed layout:
- Total employees, total gross compensation, total WHT remitted
- Supporting schedule listing each employee's monthly WHT

**BIR Form 2316 (Certificate of Compensation per Employee, Annual)**

`BirForm2316Service::generate(int $employeeId, int $year)` — PDF per employee:
- All 8 income sections filled from payroll records
- Total tax withheld + over/under withholding declaration
- Downloadable by employee via self-service

**BIR Alphalist (Annual)**

Excel in BIR-prescribed column format. Columns map to Schedule 1 (MWEs), Schedule 4 (non-MWEs), validated against BIR Data Entry & Validation Module column specification.

**Government Remittance Reports**

| Report | Filing Frequency | Generated by |
|---|---|---|
| SSS SBR2 | Monthly | `SssRemittanceService` |
| PhilHealth RF-1 | Monthly | `PhilHealthRemittanceService` |
| Pag-IBIG monthly | Monthly | `PagibigRemittanceService` |

Each report is generated as Excel and stored in Spatie Medialibrary (`payroll-reports` collection) for redownload.

**Employee Self-Service Payslip Portal**

`staff` role can access `/my-payslip`:
- List of all payroll runs they were included in
- Download PDF payslip per run
- YTD summary card: gross, tax, contributions, net
- Running 12-month earnings chart (Recharts line chart)

---

## Phase 1C Summary

| Item | Delivered |
|---|---|
| 4 immutable value objects (`Money`, `PayPeriod`, `WorkingDays`, `OvertimeMultiplier`) | ✅ |
| Pre-run checklist PR-001–008 | ✅ |
| 17-step computation pipeline | ✅ |
| TRAIN Law tax engine TAX-001–009 | ✅ |
| SSS, PhilHealth, Pag-IBIG contribution rules | ✅ |
| Deduction priority stack DED-001–004 | ✅ |
| 14 edge case handlers EDGE-001–014 | ✅ |
| Payroll run state machine | ✅ |
| Queue + Horizon batch processing | ✅ |
| Post-computation integrity checks | ✅ |
| Payroll run UI (3 tabs) | ✅ |
| Payslip PDF (DOMPDF) | ✅ |
| Payroll register Excel export | ✅ |
| Bank disbursement file | ✅ |
| December reconciliation / 13th month | ✅ |
| BIR Form 1601-C | ✅ |
| BIR Form 2316 per employee | ✅ |
| BIR Alphalist | ✅ |
| SSS SBR2 / PhilHealth RF-1 / Pag-IBIG reports | ✅ |
| Employee self-service payslip portal | ✅ |

---

*Previous: [Phase 1B — HR Module](phase-1b-hr-module.md) · Next: [Phase 1D — Accounting Module](phase-1d-accounting-module.md)*
