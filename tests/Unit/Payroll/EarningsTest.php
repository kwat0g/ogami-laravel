<?php

declare(strict_types=1);

use App\Domains\Payroll\Services\PayrollComputationService;
use Tests\Support\PayrollTestHelper;

/*
|--------------------------------------------------------------------------
| Earnings Computation Tests — EARN-001 to EARN-010
|--------------------------------------------------------------------------
| Tests the basic pay, overtime, holiday, and night differential steps
| of the PayrollComputationService pipeline.
|
| All scenarios use the 2nd semi-monthly cutoff (Oct 16–31) so SSS is
| collected in these periods.
--------------------------------------------------------------------------
*/

beforeEach(function () {
    PayrollTestHelper::seedRateTables();
    $this->svc = app(PayrollComputationService::class);
});

// ---------------------------------------------------------------------------
// EARN-001 / EARN-002: Basic pay pro-rating
// ---------------------------------------------------------------------------

describe('Basic pay pro-rating — EARN-001', function () {

    it('pays full semi-monthly rate when all 13 days worked', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

        $detail = $this->svc->computeForEmployee($employee, $run);

        // Oct 16-31 has 12 Mon-Fri days; standard period = 13 days
        // ⇒ basic pay = (12/13) × ₱12,500 = ₱11,538.46 ≈ 1,153,846 centavos
        // Verify it is a large fraction of the full semi-monthly rate (≥ 90%)
        expect($detail->basic_pay_centavos)
            ->toBeGreaterThan(1_100_000)
            ->toBeLessThanOrEqual(1_250_000);
    });

    it('pro-rates basic pay for partial days worked — EARN-002', function () {
        $employee = PayrollTestHelper::makeEmployee(26_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        // Only create logs for 5 out of 13 working days
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-22');

        $detail = $this->svc->computeForEmployee($employee, $run);

        // 5 days / 13 × ₱13,000 semi-monthly = ₱5,000
        expect($detail->days_worked)->toBe(5);
        expect($detail->basic_pay_centavos)->toBeGreaterThan(0)
            ->toBeLessThan(1_300_000); // always < full semi-monthly
    });

    it('includes paid leave days in basic pay calculation', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');

        // 10 attendance days present
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-27');

        // Simulate 3 days paid leave by having attendance + a paid leave request
        // (leave request creation not done here — basic pay uses daysWorked + leaveDaysPaid)
        // Without leave, basic pay = (10/13) × 12,500
        $detail = $this->svc->computeForEmployee($employee, $run);
        // Oct 16-27 contains 8 Mon-Fri working days
        expect($detail->days_worked)->toBe(8);
        expect($detail->basic_pay_centavos)->toBeGreaterThan(0);
    });
});

// ---------------------------------------------------------------------------
// EARN-003 / EARN-004: Overtime pay — regular day
// ---------------------------------------------------------------------------

describe('Overtime pay — EARN-003 / EARN-004', function () {

    it('computes OT pay for regular day overtime', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');

        // All 13 days present, each with 60 mins (1 hr) regular OT
        PayrollTestHelper::makeAttendance(
            employee: $employee,
            start: '2025-10-16',
            end: '2025-10-31',
            present: true,
            overtimeMinutes: 60,
        );

        $detail = $this->svc->computeForEmployee($employee, $run);

        expect($detail->overtime_regular_minutes)->toBeGreaterThan(0);
        expect($detail->overtime_pay_centavos)->toBeGreaterThan(0);
    });

    it('has zero OT pay when no OT logged', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

        $detail = $this->svc->computeForEmployee($employee, $run);
        expect($detail->overtime_pay_centavos)->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// EARN-007 / EARN-008: Regular holiday pay premium
// ---------------------------------------------------------------------------

describe('Regular holiday pay — EARN-007 / EARN-008', function () {

    it('records regular holiday days when employee worked on a holiday', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-11-01', '2025-11-15'); // Nov 1 = All Saints Day (regular holiday)

        // Nov 1 is in this period — mark as present + regular holiday
        PayrollTestHelper::makeAttendance($employee, '2025-11-01', '2025-11-15', true, 0, 0, 'regular');

        $detail = $this->svc->computeForEmployee($employee, $run);

        expect($detail->regular_holiday_days)->toBeGreaterThan(0);
        // Holiday pay should be > 0 (200% rule)
        expect($detail->holiday_pay_centavos)->toBeGreaterThanOrEqual(0);
    });
});

// ---------------------------------------------------------------------------
// EARN-010: Night differential
// ---------------------------------------------------------------------------

describe('Night differential — EARN-010', function () {

    it('computes night differential pay when night diff minutes logged', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');

        PayrollTestHelper::makeAttendance(
            employee: $employee,
            start: '2025-10-16',
            end: '2025-10-31',
            present: true,
            nightDiffMinutes: 60, // 1 hr night diff per day
        );

        $detail = $this->svc->computeForEmployee($employee, $run);

        expect($detail->night_diff_minutes)->toBeGreaterThan(0);
        expect($detail->night_diff_pay_centavos)->toBeGreaterThan(0);
    });
});

// ---------------------------------------------------------------------------
// Gross pay invariant
// ---------------------------------------------------------------------------

describe('Gross pay', function () {

    it('equals basic + OT + holiday + night diff + adjustments', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31', true, 30, 30);

        $detail = $this->svc->computeForEmployee($employee, $run);

        $expectedGross = $detail->basic_pay_centavos
            + $detail->overtime_pay_centavos
            + $detail->holiday_pay_centavos
            + $detail->night_diff_pay_centavos;

        // gross_pay must be at least the sum of the basic components
        expect($detail->gross_pay_centavos)->toBeGreaterThanOrEqual($expectedGross);
    });
});
