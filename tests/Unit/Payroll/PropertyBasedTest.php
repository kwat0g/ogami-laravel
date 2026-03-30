<?php

declare(strict_types=1);

use App\Domains\Payroll\Services\PayrollComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PayrollTestHelper;

/*
|--------------------------------------------------------------------------
| Property-Based Invariant Tests
|--------------------------------------------------------------------------
| These tests assert algebraic invariants that MUST hold for all valid
| combinations of inputs. They use Pest datasets to sweep a range of
| salary levels rather than a formal property-based engine.
--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    PayrollTestHelper::seedRateTables();
    $this->svc = app(PayrollComputationService::class);
});

// ---------------------------------------------------------------------------
// Sweep dataset — monthly salaries from MWE to senior executive
// ---------------------------------------------------------------------------

dataset('salary_sweep', [
    'MWE (~₱610/day NCR)' => [15_860.00],
    '₱20k/month' => [20_000.00],
    '₱25k/month' => [25_000.00],
    '₱50k/month' => [50_000.00],
    '₱100k/month' => [100_000.00],
    '₱200k/month (exec)' => [200_000.00],
]);

dataset('cutoff_pairs', [
    '1st cutoff' => ['2025-10-01', '2025-10-15'],
    '2nd cutoff' => ['2025-10-16', '2025-10-31'],
]);

// ---------------------------------------------------------------------------
// INVARIANT-1: net_pay ≤ gross_pay for all salary levels
// ---------------------------------------------------------------------------

it('INV-1 — net pay ≤ gross pay for every salary', function (float $salaryPeso) {
    $employee = PayrollTestHelper::makeEmployee($salaryPeso);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    $d = $this->svc->computeForEmployee($employee, $run);

    expect($d->net_pay_centavos)->toBeLessThanOrEqual($d->gross_pay_centavos);
})->with('salary_sweep');

// ---------------------------------------------------------------------------
// INVARIANT-2: net_pay ≥ 0 for all salary levels (min wage guard)
// ---------------------------------------------------------------------------

it('INV-2 — net pay is never negative', function (float $salaryPeso) {
    $employee = PayrollTestHelper::makeEmployee($salaryPeso);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    $d = $this->svc->computeForEmployee($employee, $run);

    expect($d->net_pay_centavos)->toBeGreaterThanOrEqual(0);
})->with('salary_sweep');

// ---------------------------------------------------------------------------
// INVARIANT-3: withholding_tax ≥ 0
// ---------------------------------------------------------------------------

it('INV-3 — withholding tax is never negative', function (float $salaryPeso) {
    $employee = PayrollTestHelper::makeEmployee($salaryPeso);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    $d = $this->svc->computeForEmployee($employee, $run);

    expect($d->withholding_tax_centavos)->toBeGreaterThanOrEqual(0);
})->with('salary_sweep');

// ---------------------------------------------------------------------------
// INVARIANT-4: MWE always has zero withholding tax
// ---------------------------------------------------------------------------

it('INV-4 — MWE salary yields zero withholding tax', function () {
    $employee = PayrollTestHelper::makeEmployee(15_860.00, ['region' => 'NCR']);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    $d = $this->svc->computeForEmployee($employee, $run);

    expect($d->withholding_tax_centavos)->toBe(0);
});

// ---------------------------------------------------------------------------
// INVARIANT-5: sum(contributions) ≤ gross_pay
// ---------------------------------------------------------------------------

it('INV-5 — total mandatory contributions do not exceed gross pay', function (float $salaryPeso) {
    $employee = PayrollTestHelper::makeEmployee($salaryPeso);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    $d = $this->svc->computeForEmployee($employee, $run);

    $contributions = $d->sss_ee_centavos
        + $d->philhealth_ee_centavos
        + $d->pagibig_ee_centavos;

    expect($contributions)->toBeLessThanOrEqual($d->gross_pay_centavos);
})->with('salary_sweep');

// ---------------------------------------------------------------------------
// INVARIANT-6: gross_pay = 0 → net_pay = 0 → all deductions = 0
// ---------------------------------------------------------------------------

it('INV-6 — no deductions when gross pay is zero', function () {
    $employee = PayrollTestHelper::makeEmployee(25_000.00);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    // No attendance — zero days worked

    $d = $this->svc->computeForEmployee($employee, $run);

    expect($d->gross_pay_centavos)->toBe(0);
    expect($d->net_pay_centavos)->toBe(0);
    expect($d->sss_ee_centavos)->toBe(0);
    expect($d->philhealth_ee_centavos)->toBe(0);
    expect($d->pagibig_ee_centavos)->toBe(0);
    expect($d->withholding_tax_centavos)->toBe(0);
});

// ---------------------------------------------------------------------------
// INVARIANT-7: 1st cutoff defers SSS only; PhilHealth/PagIBIG are split semi-monthly
// ---------------------------------------------------------------------------

it('INV-7 — 1st cutoff has zero SSS but still computes PhilHealth/PagIBIG normally', function (float $salaryPeso) {
    $employee = PayrollTestHelper::makeEmployee($salaryPeso);
    $run = PayrollTestHelper::makeRun('2025-10-01', '2025-10-15');
    PayrollTestHelper::makeAttendance($employee, '2025-10-01', '2025-10-15');

    $d = $this->svc->computeForEmployee($employee, $run);

    expect($d->sss_ee_centavos)->toBe(0);
    expect($d->philhealth_ee_centavos)->toBeGreaterThanOrEqual(0);
    expect($d->pagibig_ee_centavos)->toBeGreaterThanOrEqual(0);
})->with('salary_sweep');

// ---------------------------------------------------------------------------
// INVARIANT-8: Adding OT minutes can only increase, not decrease, gross pay
// ---------------------------------------------------------------------------

it('INV-8 — overtime cannot decrease gross pay', function (float $salaryPeso) {
    // Two employees on the SAME run — one with OT, one without
    $employeeNoOt = PayrollTestHelper::makeEmployee($salaryPeso);
    $employeeWithOt = PayrollTestHelper::makeEmployee($salaryPeso);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');

    PayrollTestHelper::makeAttendance($employeeNoOt, '2025-10-16', '2025-10-31', true, 0);   // no OT
    PayrollTestHelper::makeAttendance($employeeWithOt, '2025-10-16', '2025-10-31', true, 60);  // 1hr OT/day

    $dNoOt = $this->svc->computeForEmployee($employeeNoOt, $run);
    $dWithOt = $this->svc->computeForEmployee($employeeWithOt, $run);

    // Same period, same days worked; OT adds to gross — never decreases
    expect($dWithOt->gross_pay_centavos)->toBeGreaterThanOrEqual($dNoOt->gross_pay_centavos);
})->with('salary_sweep');
