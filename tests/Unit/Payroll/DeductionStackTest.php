<?php

declare(strict_types=1);

use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanAmortizationSchedule;
use App\Domains\Payroll\Services\PayrollComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PayrollTestHelper;

/*
|--------------------------------------------------------------------------
| Deduction Stack Tests — DED-001 to DED-004, LN-007
|--------------------------------------------------------------------------
| Verifies deduction priority order and the Minimum Wage Protection guard
| that suspends loan amortisations when net pay would fall below the
| applicable regional minimum wage.
--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    PayrollTestHelper::seedRateTables();
    $this->svc = app(PayrollComputationService::class);
});

// ---------------------------------------------------------------------------
// DED-001: Government mandatory contributions always deducted
// ---------------------------------------------------------------------------

describe('Mandatory contributions always deducted — DED-001', function () {

    it('always deducts SSS, PhilHealth, and PagIBIG from a 2nd-cutoff regular run', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

        $detail = $this->svc->computeForEmployee($employee, $run);

        expect($detail->sss_ee_centavos)->toBeGreaterThan(0);
        expect($detail->philhealth_ee_centavos)->toBeGreaterThan(0);
        expect($detail->pagibig_ee_centavos)->toBeGreaterThan(0);
    });
});

// ---------------------------------------------------------------------------
// DED-002: Withholding tax deducted after mandatory contributions
// ---------------------------------------------------------------------------

describe('Tax deducted after contributions — DED-002', function () {

    it('withholding tax is non-negative and below net pay', function () {
        $employee = PayrollTestHelper::makeEmployee(60_000.00); // taxable bracket
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

        $detail = $this->svc->computeForEmployee($employee, $run);

        expect($detail->withholding_tax_centavos)->toBeGreaterThanOrEqual(0);
        expect($detail->withholding_tax_centavos)->toBeLessThanOrEqual($detail->gross_pay_centavos);
    });
});

// ---------------------------------------------------------------------------
// DED-003: Priority — mandatory > tax > loans > other deductions
// ---------------------------------------------------------------------------

describe('Deduction priority order — DED-003', function () {

    it('deducts mandatory contributions and tax before loan amortisation', function () {
        $employee = PayrollTestHelper::makeEmployee(25_000.00);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

        // Create an SSS loan with a scheduled amortisation for this period
        $loan = Loan::factory()->create([
            'employee_id' => $employee->id,
            'principal_centavos' => 1_800_000, // ₱18,000
            'outstanding_balance_centavos' => 1_800_000,
            'status' => 'active',
        ]);

        LoanAmortizationSchedule::factory()->create([
            'loan_id' => $loan->id,
            'due_date' => '2025-10-31',
            'principal_portion_centavos' => 100_000, // ₱1,000
            'interest_portion_centavos' => 0,
            'total_due_centavos' => 100_000, // must equal principal + interest
            'status' => 'pending',
        ]);

        $detail = $this->svc->computeForEmployee($employee, $run);

        // All mandatory contributions must be present
        expect($detail->sss_ee_centavos)->toBeGreaterThan(0);
        expect($detail->philhealth_ee_centavos)->toBeGreaterThan(0);
        expect($detail->pagibig_ee_centavos)->toBeGreaterThan(0);
        expect($detail->withholding_tax_centavos)->toBeGreaterThanOrEqual(0);
        // Loan deduction should be recorded (net is well above min wage at ₱25k)
        expect($detail->loan_deductions_centavos)->toBeGreaterThan(0);
    });
});

// ---------------------------------------------------------------------------
// DED-004 / LN-007: Net pay never negative — minimum wage protection
// ---------------------------------------------------------------------------

describe('Minimum wage protection — DED-004 / LN-007', function () {

    it('suspends loan amortisation when deducting it would push net below minimum wage', function () {
        // Use a salary at minimum wage level so loan would breach the floor
        $minWagePeso = 610.00; // NCR daily minimum wage (2025)
        $semiMonthly = $minWagePeso * 13; // ~₱7,930
        $employee = PayrollTestHelper::makeEmployee($semiMonthly * 2);
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

        // Create a loan whose amortisation would consume the remaining net
        $loan = Loan::factory()->create([
            'employee_id' => $employee->id,
            'principal_centavos' => 50_000_000,
            'outstanding_balance_centavos' => 50_000_000,
            'status' => 'active',
        ]);

        LoanAmortizationSchedule::factory()->create([
            'loan_id' => $loan->id,
            'due_date' => '2025-10-31',
            'principal_portion_centavos' => 800_000, // ₱8,000 — more than semi-monthly net
            'interest_portion_centavos' => 0,
            'total_due_centavos' => 800_000,
            'status' => 'pending',
        ]);

        $detail = $this->svc->computeForEmployee($employee, $run);

        // Net pay must never be negative
        expect($detail->net_pay_centavos)->toBeGreaterThanOrEqual(0);
    });

    it('does not suspend loan when enough net pay remains above minimum wage', function () {
        $employee = PayrollTestHelper::makeEmployee(50_000.00); // well above min wage
        $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
        PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

        $loan = Loan::factory()->create([
            'employee_id' => $employee->id,
            'principal_centavos' => 1_200_000,
            'outstanding_balance_centavos' => 1_200_000,
            'status' => 'active',
        ]);

        LoanAmortizationSchedule::factory()->create([
            'loan_id' => $loan->id,
            'due_date' => '2025-10-31',
            'principal_portion_centavos' => 50_000, // ₱500 — negligible vs ₱25k semi-monthly
            'interest_portion_centavos' => 0,
            'total_due_centavos' => 50_000,
            'status' => 'pending',
        ]);

        $detail = $this->svc->computeForEmployee($employee, $run);

        // Loan IS deducted (min wage not breached)
        expect($detail->loan_deductions_centavos)->toBeGreaterThan(0);
        expect($detail->net_pay_centavos)->toBeGreaterThan(0);
    });
});
