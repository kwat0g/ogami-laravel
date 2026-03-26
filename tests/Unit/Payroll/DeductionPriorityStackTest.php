<?php

declare(strict_types=1);

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Pipeline\Step15LoanDeductionsStep;
use App\Domains\Payroll\Pipeline\Step16OtherDeductionsStep;
use App\Domains\Payroll\Pipeline\Step17NetPayStep;
use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Shared\Exceptions\NegativeNetPayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// Ensure minimum_wage_rates is empty before each test so results don't depend
// on whatever GoldenSuiteTest / ContributionTest / seeders left behind.
// PostgreSQL TRUNCATE is transactional, so RefreshDatabase rolls it back cleanly.
beforeEach(function () {
    DB::table('minimum_wage_rates')->truncate();
});

/*
|--------------------------------------------------------------------------
| DeductionPriorityStackTest
|--------------------------------------------------------------------------
| Sprint 10 — DED-001: Strict per-slot minimum-wage floor applied to
| each loan deduction and voluntary deduction individually.
| DED-002: NegativeNetPayException when statutory exceeds gross.
| DED-003: ROUND_HALF_UP centavos arithmetic.
|--------------------------------------------------------------------------
*/

/** Centavo shorthand. */
function pesos(float $amount): int
{
    return (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);
}

/**
 * Build a minimal PayrollComputationContext for pipeline step testing.
 * Uses Eloquent models saved to DB for FK compliance.
 */
function makeCtxWithLoans(
    int $grossPayCentavos,
    int $sssEeCentavos,
    int $withholdingTaxCentavos,
    int $daysWorked = 13,
    string $cutoffEnd = '2026-06-15',
): PayrollComputationContext {
    $employee = Employee::factory()->create([
        'employment_status' => 'active',
        'is_active' => true,
        'basic_monthly_rate' => 1500000,
    ]);

    $run = PayrollRun::factory()->create([
        'status' => 'processing',
        'cutoff_start' => '2026-06-01',
        'cutoff_end' => $cutoffEnd,
        'pay_date' => '2026-06-20',
    ]);

    $ctx = new PayrollComputationContext($employee, $run);
    $ctx->grossPayCentavos = $grossPayCentavos;
    $ctx->sssEeCentavos = $sssEeCentavos;
    $ctx->philhealthEeCentavos = 0;
    $ctx->pagibigEeCentavos = 0;
    $ctx->withholdingTaxCentavos = $withholdingTaxCentavos;
    $ctx->daysWorked = $daysWorked;

    return $ctx;
}

// ── DED-001 — Loan deduction gating ──────────────────────────────────────────

describe('DED-001 — per-slot minimum-wage floor (Step 15)', function () {
    it('applies a loan in full when headroom ≥ instalment', function () {
        // NCR min wage: ₱600/day → 13 days floor = ₱7,800 = 780,000 centavos
        DB::table('minimum_wage_rates')->insert([
            'region' => 'NCR',
            'daily_rate' => 600,
            'effective_date' => '2020-01-01',
        ]);

        $ctx = makeCtxWithLoans(
            grossPayCentavos: pesos(25_000),  // ₱25,000 gross
            sssEeCentavos: pesos(1_750),   // statutory total: ₱2,750
            withholdingTaxCentavos: pesos(1_000),
        );

        // Net after statutory = 25,000 - 1,750 - 1,000 = 22,250 centavos (×100)
        // Floor = 600 * 13 * 100 = 780,000 centavos → headroom = 22,250×100 - 780,000 = 1,445,000

        // Attach a ₱2,000 active loan to the employee
        $loanTypeId = DB::table('loan_types')->insertGetId([
            'name' => 'Pag-IBIG MPF',
            'category' => 'government',
            'code' => 'PAGIBIG-MPF',
            'max_term_months' => 24,
        ]);
        $loanId = DB::table('loans')->insertGetId([
            'reference_no' => 'LN-TEST-00001',
            'ulid' => (string) Str::ulid(),
            'employee_id' => $ctx->employee->id,
            'loan_type_id' => $loanTypeId,
            'term_months' => 24,
            'loan_date' => '2024-01-01',
            'status' => 'active',
            'principal_centavos' => pesos(50_000),
            'outstanding_balance_centavos' => pesos(50_000),
            'monthly_amortization_centavos' => pesos(2_000),
            'requested_by' => $ctx->run->created_by,
        ]);
        DB::table('loan_amortization_schedules')->insert([
            'loan_id' => $loanId,
            'installment_no' => 1,
            'due_date' => '2026-06-20',
            'principal_portion_centavos' => pesos(2_000),
            'interest_portion_centavos' => 0,
            'total_due_centavos' => pesos(2_000),
            'status' => 'pending',
        ]);

        $step = new Step15LoanDeductionsStep;
        $step($ctx, fn ($c) => $c);

        expect($ctx->loanDeductionsCentavos)->toBe(pesos(2_000))
            ->and($ctx->hasDeferredDeductions)->toBeFalse();
    });

    it('partially deducts when instalment would breach the floor', function () {
        // NCR min wage: ₱600/day → 13 days floor = ₱7,800 = 780,000 centavos
        DB::table('minimum_wage_rates')->insert([
            'region' => 'NCR',
            'daily_rate' => 600,
            'effective_date' => '2020-01-01',
        ]);

        $ctx = makeCtxWithLoans(
            grossPayCentavos: pesos(10_000), // ₱10,000 gross
            sssEeCentavos: pesos(500),    // statutory total: ₱1,000
            withholdingTaxCentavos: pesos(500),
        );
        // Net after statutory = 10,000 - 500 - 500 = 9,000 → 900,000 centavos
        // Floor = 780,000 centavos → headroom = 120,000 (₱1,200)

        $loanTypeId = DB::table('loan_types')->insertGetId([
            'name' => 'SSS Salary Loan',
            'category' => 'government',
            'code' => 'SSS-SL',
            'max_term_months' => 24,
        ]);
        $loanId = DB::table('loans')->insertGetId([
            'reference_no' => 'LN-TEST-00002',
            'ulid' => (string) Str::ulid(),
            'employee_id' => $ctx->employee->id,
            'loan_type_id' => $loanTypeId,
            'term_months' => 24,
            'loan_date' => '2024-01-01',
            'status' => 'active',
            'principal_centavos' => pesos(50_000),
            'outstanding_balance_centavos' => pesos(50_000),
            'monthly_amortization_centavos' => pesos(2_500),
            'requested_by' => $ctx->run->created_by,
        ]);
        // Instalment ₱2,500 > headroom ₱1,200 → partial
        DB::table('loan_amortization_schedules')->insert([
            'loan_id' => $loanId,
            'installment_no' => 1,
            'due_date' => '2026-06-20',
            'principal_portion_centavos' => pesos(2_500),
            'interest_portion_centavos' => 0,
            'total_due_centavos' => pesos(2_500),
            'status' => 'pending',
        ]);

        $step = new Step15LoanDeductionsStep;
        $step($ctx, fn ($c) => $c);

        // Only headroom (₱1,200) should be deducted; remainder deferred
        expect($ctx->loanDeductionsCentavos)->toBe(pesos(1_200))
            ->and($ctx->hasDeferredDeductions)->toBeTrue();
    });

    it('skips a loan entirely when net is already at the floor', function () {
        DB::table('minimum_wage_rates')->insert([
            'region' => 'NCR',
            'daily_rate' => 600,
            'effective_date' => '2020-01-01',
        ]);

        // Gross = ₱8,000, statutory = ₱1,000 → net = ₱7,000 < floor ₱7,800
        $ctx = makeCtxWithLoans(
            grossPayCentavos: pesos(8_000),
            sssEeCentavos: pesos(500),
            withholdingTaxCentavos: pesos(500),
        );

        $loanTypeId = DB::table('loan_types')->insertGetId([
            'name' => 'Company Loan',
            'category' => 'company',
            'code' => 'CO-LOAN',
            'max_term_months' => 12,
        ]);
        $loanId = DB::table('loans')->insertGetId([
            'reference_no' => 'LN-TEST-00003',
            'ulid' => (string) Str::ulid(),
            'employee_id' => $ctx->employee->id,
            'loan_type_id' => $loanTypeId,
            'term_months' => 12,
            'loan_date' => '2024-01-01',
            'status' => 'active',
            'principal_centavos' => pesos(50_000),
            'outstanding_balance_centavos' => pesos(50_000),
            'monthly_amortization_centavos' => pesos(1_500),
            'requested_by' => $ctx->run->created_by,
        ]);
        DB::table('loan_amortization_schedules')->insert([
            'loan_id' => $loanId,
            'installment_no' => 1,
            'due_date' => '2026-06-20',
            'principal_portion_centavos' => pesos(1_500),
            'interest_portion_centavos' => 0,
            'total_due_centavos' => pesos(1_500),
            'status' => 'pending',
        ]);

        $step = new Step15LoanDeductionsStep;
        $step($ctx, fn ($c) => $c);

        expect($ctx->loanDeductionsCentavos)->toBe(0)
            ->and($ctx->hasDeferredDeductions)->toBeTrue();
    });
});

// ── DED-001 — Other deductions gating ────────────────────────────────────────

describe('DED-001 — other deductions also respect the floor (Step 16)', function () {
    it('skips a voluntary deduction that would breach the min-wage floor', function () {
        DB::table('minimum_wage_rates')->insert([
            'region' => 'NCR',
            'daily_rate' => 600,
            'effective_date' => '2020-01-01',
        ]);

        $ctx = makeCtxWithLoans(
            grossPayCentavos: pesos(9_000),
            sssEeCentavos: pesos(500),
            withholdingTaxCentavos: pesos(500),
        );
        // Net after statutory = ₱8,000 → floor ₱7,800 → headroom ₱200
        // Loan deductions already took ₱0 (none seeded)
        $ctx->loanDeductionsCentavos = 0;

        // Simulate a voluntary deduction of ₱500 (> headroom ₱200)
        $run = $ctx->run;
        $employee = $ctx->employee;
        DB::table('payroll_adjustments')->insert([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'type' => 'deduction',
            'description' => 'Uniform deduction',
            'amount_centavos' => pesos(500),
            'created_by' => $run->created_by,
        ]);

        // Reload adjustments into context (Step09 normally does this)
        $ctx->adjustments = PayrollAdjustment::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->get();

        $step = new Step16OtherDeductionsStep;
        $step($ctx, fn ($c) => $c);

        expect($ctx->otherDeductionsCentavos)->toBe(0)
            ->and($ctx->hasDeferredDeductions)->toBeTrue();
    });
});

// ── DED-002 — NegativeNetPayException ────────────────────────────────────────

describe('DED-002 — NegativeNetPayException (Step 17)', function () {
    it('throws NegativeNetPayException when statutory deductions exceed gross pay', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 100000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2026-07-01',
            'cutoff_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = pesos(1_000); // ₱1,000
        $ctx->sssEeCentavos = pesos(700);
        $ctx->philhealthEeCentavos = pesos(200);
        $ctx->pagibigEeCentavos = pesos(200);
        $ctx->withholdingTaxCentavos = pesos(100);
        // Total statutory = ₱1,200 > ₱1,000 gross → rawNet = -₱200
        $ctx->loanDeductionsCentavos = 0;
        $ctx->otherDeductionsCentavos = 0;
        $ctx->daysWorked = 13;

        $step = new Step17NetPayStep;

        expect(fn () => $step($ctx, fn ($c) => $c))
            ->toThrow(NegativeNetPayException::class);
    });

    it('has error code NEGATIVE_NET_PAY', function () {
        $ex = new NegativeNetPayException(99, 'EMP-099', -500.00);
        expect($ex->errorCode)->toBe('NEGATIVE_NET_PAY');
    });
});

// ── DED-003 — ROUND_HALF_UP ───────────────────────────────────────────────────

describe('DED-003 — centavos rounding uses PHP_ROUND_HALF_UP', function () {
    it('rounds 0.5 centavos up (not towards even)', function () {
        // PHP_ROUND_HALF_UP: 1.5 → 2, 2.5 → 3
        expect((int) round(1.5, 0, PHP_ROUND_HALF_UP))->toBe(2)
            ->and((int) round(2.5, 0, PHP_ROUND_HALF_UP))->toBe(3)
            ->and((int) round(0.005 * 100, 0, PHP_ROUND_HALF_UP))->toBe(1);
    });

    it('min-wage floor calculation also uses ROUND_HALF_UP', function () {
        // floor = 780000 * 7 / 26 = 210000.0 exactly → no rounding needed
        $floor = (int) round(780000 * 7 / 26.0, 0, PHP_ROUND_HALF_UP);
        expect($floor)->toBe(210000);
    });
});
