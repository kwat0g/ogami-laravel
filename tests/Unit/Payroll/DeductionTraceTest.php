<?php

declare(strict_types=1);

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Pipeline\Step17NetPayStep;
use App\Domains\Payroll\Services\PayrollComputationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| DeductionTraceTest
|--------------------------------------------------------------------------
| Sprint 10 — DED-004: deduction_stack_trace assembled by Step17.
|
| The trace must contain one entry per priority-stack slot, capturing:
|   step, type, amount_centavos, net_after_centavos, applied.
| Slot #5 is the min_wage_check; it has threshold/passed fields.
|--------------------------------------------------------------------------
*/

function buildCtxForTrace(int $grossPay = 2_000_000): PayrollComputationContext
{
    $employee = Employee::factory()->create([
        'employment_status' => 'active',
        'is_active' => true,
        'basic_monthly_rate' => $grossPay,
    ]);
    $run = PayrollRun::factory()->create([
        'status' => 'processing',
        'cutoff_start' => '2026-09-01',
        'cutoff_end' => '2026-09-15',
        'pay_date' => '2026-09-20',
    ]);

    $ctx = new PayrollComputationContext($employee, $run);
    $ctx->grossPayCentavos = $grossPay;
    $ctx->sssEeCentavos = 175_000;   // ₱1,750
    $ctx->philhealthEeCentavos = 67_500;    // ₱675
    $ctx->pagibigEeCentavos = 5_000;     // ₱50
    $ctx->withholdingTaxCentavos = 100_000;   // ₱1,000
    $ctx->loanDeductionsCentavos = 150_000;   // ₱1,500
    $ctx->loanDeductionDetail = [['loan_id' => 1, 'amount_centavos' => 150_000, 'applied' => true]];
    $ctx->otherDeductionsCentavos = 50_000;    // ₱500
    $ctx->daysWorked = 13;

    return $ctx;
}

beforeEach(fn () => $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0));

describe('DED-004 — deduction_stack_trace structure', function () {
    it('assembles a 7-slot trace with correct step numbers', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 2_000_000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2026-09-01',
            'cutoff_end' => '2026-09-15',
            'pay_date' => '2026-09-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = 2_000_000;
        $ctx->sssEeCentavos = 175_000;
        $ctx->philhealthEeCentavos = 67_500;
        $ctx->pagibigEeCentavos = 5_000;
        $ctx->withholdingTaxCentavos = 100_000;
        $ctx->loanDeductionsCentavos = 150_000;
        $ctx->loanDeductionDetail = [['loan_id' => 1, 'amount_centavos' => 150_000, 'applied' => true]];
        $ctx->otherDeductionsCentavos = 50_000;
        $ctx->daysWorked = 13;

        $step = new Step17NetPayStep;
        $step($ctx, fn ($c) => $c);

        $trace = $ctx->deductionTrace;

        expect($trace)->toHaveCount(7)
            ->and($trace[0]['step'])->toBe(1)
            ->and($trace[1]['step'])->toBe(2)
            ->and($trace[2]['step'])->toBe(3)
            ->and($trace[3]['step'])->toBe(4)
            ->and($trace[4]['step'])->toBe(5)  // min_wage_check
            ->and($trace[5]['step'])->toBe(6)  // loan_deductions
            ->and($trace[6]['step'])->toBe(7); // other_deductions
    });

    it('trace slot types match the priority-stack labels', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 2_000_000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2026-10-01',
            'cutoff_end' => '2026-10-15',
            'pay_date' => '2026-10-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = 2_000_000;
        $ctx->sssEeCentavos = 175_000;
        $ctx->philhealthEeCentavos = 67_500;
        $ctx->pagibigEeCentavos = 5_000;
        $ctx->withholdingTaxCentavos = 100_000;
        $ctx->loanDeductionsCentavos = 0;
        $ctx->otherDeductionsCentavos = 0;
        $ctx->daysWorked = 13;

        (new Step17NetPayStep)($ctx, fn ($c) => $c);

        $types = array_column($ctx->deductionTrace, 'type');
        expect($types)->toBe([
            'sss_employee',
            'philhealth_employee',
            'pagibig_employee',
            'withholding_tax',
            'min_wage_check',
            'loan_deductions',
            'other_deductions',
        ]);
    });

    it('slot 1–4 have applied=true and correct amount_centavos', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 2_000_000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2026-11-01',
            'cutoff_end' => '2026-11-15',
            'pay_date' => '2026-11-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = 1_500_000;
        $ctx->sssEeCentavos = 175_000;
        $ctx->philhealthEeCentavos = 67_500;
        $ctx->pagibigEeCentavos = 5_000;
        $ctx->withholdingTaxCentavos = 100_000;
        $ctx->loanDeductionsCentavos = 0;
        $ctx->otherDeductionsCentavos = 0;
        $ctx->daysWorked = 13;

        (new Step17NetPayStep)($ctx, fn ($c) => $c);

        expect($ctx->deductionTrace[0]['amount_centavos'])->toBe(175_000)
            ->and($ctx->deductionTrace[0]['applied'])->toBeTrue()
            ->and($ctx->deductionTrace[1]['amount_centavos'])->toBe(67_500)
            ->and($ctx->deductionTrace[3]['amount_centavos'])->toBe(100_000);
    });

    it('slot 5 (min_wage_check) has threshold_centavos and passed fields', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 2_000_000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2026-12-01',
            'cutoff_end' => '2026-12-15',
            'pay_date' => '2026-12-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = 2_000_000;
        $ctx->sssEeCentavos = 0;
        $ctx->philhealthEeCentavos = 0;
        $ctx->pagibigEeCentavos = 0;
        $ctx->withholdingTaxCentavos = 0;
        $ctx->loanDeductionsCentavos = 0;
        $ctx->otherDeductionsCentavos = 0;
        $ctx->daysWorked = 13;

        (new Step17NetPayStep)($ctx, fn ($c) => $c);

        $checkSlot = $ctx->deductionTrace[4];
        expect($checkSlot['type'])->toBe('min_wage_check')
            ->and(array_key_exists('threshold_centavos', $checkSlot))->toBeTrue()
            ->and(array_key_exists('passed', $checkSlot))->toBeTrue();
    });

    it('net_after_centavos cascades correctly across slots', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 2_000_000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2027-01-01',
            'cutoff_end' => '2027-01-15',
            'pay_date' => '2027-01-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = 1_000_000;  // ₱10,000
        $ctx->sssEeCentavos = 100_000;    // ₱1,000
        $ctx->philhealthEeCentavos = 50_000;     // ₱500
        $ctx->pagibigEeCentavos = 10_000;     // ₱100
        $ctx->withholdingTaxCentavos = 40_000;     // ₱400
        $ctx->loanDeductionsCentavos = 0;
        $ctx->otherDeductionsCentavos = 0;
        $ctx->daysWorked = 13;

        (new Step17NetPayStep)($ctx, fn ($c) => $c);

        $t = $ctx->deductionTrace;
        expect($t[0]['net_after_centavos'])->toBe(900_000)  // after SSS
            ->and($t[1]['net_after_centavos'])->toBe(850_000)  // after PhilHealth
            ->and($t[2]['net_after_centavos'])->toBe(840_000)  // after PagIBIG
            ->and($t[3]['net_after_centavos'])->toBe(800_000); // after WT
    });
});
