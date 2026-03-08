---
name: payroll-debugger
description: "Debug payroll computation issues in Ogami ERP. Use when payroll amounts are wrong, a pipeline step produces unexpected output, net pay is negative, deductions are incorrect, or a payroll run fails. Covers the 17-step pipeline, PayrollComputationContext, golden suite, and government contribution tables."
argument-hint: "Describe the issue (e.g. 'wrong net pay for employee EMP-2025-042 in period 2025-01-2')"
---
# Payroll Debugger

Systematically trace payroll computation issues in Ogami ERP's 17-step pipeline.

## When to Use

- Payroll run produces wrong gross pay, net pay, or deductions
- A specific pipeline step is throwing an exception
- Government contributions (SSS, PhilHealth, Pag-IBIG) or withholding tax are incorrect
- `NegativeNetPayException` is thrown
- Payroll golden suite test fails
- A `PayrollRun` is stuck in a state or refuses to transition

## Pipeline Overview

```
Step01SnapshotsStep         — copies employee + salary data into context
Step02PeriodMetaStep        — sets pay period dates, working days
Step03AttendanceSummaryStep — aggregates attendance logs
Step04LoadYtdStep           — loads year-to-date figures for tax bracketing
Step05BasicPayStep          — computes basic pay (pro-rated if absences)
Step06OvertimePayStep       — applies OT multipliers
Step07HolidayPayStep        — holiday premium pay
Step08NightDiffStep         — night differential
Step09GrossPayStep          — sums Steps 05–08
Step10SssStep               — SSS contribution lookup
Step11PhilHealthStep        — PhilHealth premium lookup
Step12PagibigStep           — Pag-IBIG contribution
Step13TaxableIncomeStep     — gross minus non-taxable items
Step14WithholdingTaxStep    — TRAIN law bracket lookup
Step15LoanDeductionsStep    — scheduled loan amortizations
Step16OtherDeductionsStep   — manual adjustments, absences
Step17NetPayStep            — final net pay = gross − all deductions
```

Files: `app/Domains/Payroll/Pipeline/Step01SnapshotsStep.php` … `Step17NetPayStep.php`  
Shared state: `app/Domains/Payroll/Services/PayrollComputationContext.php`

## Debugging Procedure

### 1. Identify the failing step

Read `PayrollComputationContext` fields after each step. Key fields to check:

| Context field | Set by step |
|---|---|
| `basicPay` | Step05 |
| `overtimePay` | Step06 |
| `grossPay` | Step09 |
| `sssEmployee` / `sssEmployer` | Step10 |
| `philhealthEmployee` | Step11 |
| `pagibigEmployee` | Step12 |
| `taxableIncome` | Step13 |
| `withholdingTax` | Step14 |
| `loanDeductions` | Step15 |
| `otherDeductions` | Step16 |
| `netPay` | Step17 |

### 2. Check the Money arithmetic

All monetary values are **integer centavos** — never floats.  
`₱25,000.00 = 2_500_000 centavos`

- `Money::fromCentavos()` **throws** on negative input.
- `Money::subtract()` **throws** if result < 0.
- Always check for negative scenarios before subtracting.

### 3. Government contribution tables

Tables seeded by:
- `SssContributionTableSeeder` — `sss_contribution_tables`
- `PhilhealthPremiumTableSeeder` — `philhealth_premium_tables`
- `PagibigContributionTableSeeder` — `pagibig_contribution_tables`
- `TrainTaxBracketSeeder` — `train_tax_brackets`

If a lookup throws `ContributionTableNotFoundException` or `TaxTableNotFoundException`, the relevant table is empty. Re-run the seeder.

### 4. State machine issues

`PayrollRun` status flow:
```
DRAFT → SCOPE_SET → PRE_RUN_CHECKED → PROCESSING → COMPUTED →
REVIEW → SUBMITTED → HR_APPROVED → ACCTG_APPROVED → DISBURSED → PUBLISHED
```
Allowed reversals: `RETURNED → DRAFT`, `REJECTED → DRAFT`

Use `PayrollRunStateMachine` — never set `status` directly on the model.  
File: `app/Domains/Payroll/StateMachines/PayrollRunStateMachine.php`

### 5. Run the golden suite for regression checks

```bash
./vendor/bin/pest --testsuite=Unit --filter=GoldenSuite
```

24 canonical scenarios in `tests/Unit/Payroll/GoldenSuiteTest.php`. If a scenario breaks, compare:
- `expected_net_pay_centavos` vs actual `$ctx->netPay->centavos()`
- Each deduction field individually

### 6. Common root causes

| Symptom | Likely cause |
|---|---|
| `NegativeNetPayException` | Deductions exceed gross; check Step15/16 ordering |
| Wrong SSS amount | Contribution table not seeded; or salary bracket mismatch |
| Tax is zero | YTD not loaded (Step04); or taxable income below first bracket |
| Pro-rated basic pay wrong | Working days count in Step02/Step03 incorrect |
| `InvalidStateTransitionException` | Trying to transition to a non-allowed next state |
| `DuplicatePayrollRunException` | A run for this period/scope already exists |
| `LockedPeriodException` | Payroll period is closed for amendments |

## Key Files to Read

- `app/Domains/Payroll/Services/PayrollComputationContext.php` — all context fields
- `app/Domains/Payroll/Services/PayrollService.php` — pipeline dispatch
- `app/Shared/ValueObjects/Money.php` — centavo arithmetic rules
- `tests/Support/PayrollTestHelper.php` — factory helpers for tests
- `tests/Unit/Payroll/GoldenSuiteTest.php` — canonical expected values
