<?php

declare(strict_types=1);

use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Pipeline\Step17NetPayStep;
use App\Domains\Payroll\Services\PayrollComputationContext;
use App\Shared\Exceptions\ContributionTableNotFoundException;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\NegativeNetPayException;
use App\Shared\Exceptions\TaxTableNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| EdgeCaseHandlerTest
|--------------------------------------------------------------------------
| Sprint 10 — EDGE-011, EDGE-012, DED-002: Exception class shape contracts.
|
| These are pure-PHP unit tests — no DB access.
|--------------------------------------------------------------------------
*/

// ── EDGE-011 — ContributionTableNotFoundException ─────────────────────────────

describe('EDGE-011 — ContributionTableNotFoundException', function () {
    it('has error code CONTRIBUTION_TABLE_NOT_FOUND', function () {
        $ex = new ContributionTableNotFoundException('SSS', '2026-02-01', 42);
        expect($ex->errorCode)->toBe('CONTRIBUTION_TABLE_NOT_FOUND');
    });

    it('has http status 500 (configuration error)', function () {
        $ex = new ContributionTableNotFoundException('PhilHealth', '2026-02-01', 7);
        expect($ex->httpStatus)->toBe(500);
    });

    it('captures table_type, effective_date, employee_id in context', function () {
        $ex = new ContributionTableNotFoundException('Pag-IBIG', '2026-06-01', 99);
        expect($ex->context['table_type'])->toBe('Pag-IBIG')
            ->and($ex->context['effective_date'])->toBe('2026-06-01')
            ->and($ex->context['employee_id'])->toBe(99);
    });

    it('message contains the table type and effective date', function () {
        $ex = new ContributionTableNotFoundException('SSS', '2026-01-01', 5);
        expect($ex->getMessage())->toContain('SSS')
            ->and($ex->getMessage())->toContain('2026-01-01');
    });

    it('is a subclass of DomainException', function () {
        $ex = new ContributionTableNotFoundException('PhilHealth', '2026-01-01', 1);
        expect($ex)->toBeInstanceOf(DomainException::class);
    });
});

// ── EDGE-012 — TaxTableNotFoundException ─────────────────────────────────────

describe('EDGE-012 — TaxTableNotFoundException', function () {
    it('has error code TAX_TABLE_NOT_FOUND', function () {
        $ex = new TaxTableNotFoundException(33, 2_400_000, '2026');
        expect($ex->errorCode)->toBe('TAX_TABLE_NOT_FOUND');
    });

    it('has http status 500', function () {
        $ex = new TaxTableNotFoundException(33, 2_400_000, '2026');
        expect($ex->httpStatus)->toBe(500);
    });

    it('captures employee_id, annualised_income_centavos, tax_year in context', function () {
        $ex = new TaxTableNotFoundException(33, 2_400_000, '2026');
        expect($ex->context['employee_id'])->toBe(33)
            ->and($ex->context['annualised_income_centavos'])->toBe(2_400_000)
            ->and($ex->context['tax_year'])->toBe('2026');
    });

    it('message mentions BIR and the tax year', function () {
        $ex = new TaxTableNotFoundException(33, 2_400_000, '2026');
        expect($ex->getMessage())->toContain('BIR')
            ->and($ex->getMessage())->toContain('2026');
    });

    it('is a subclass of DomainException', function () {
        $ex = new TaxTableNotFoundException(1, 100_000, '2026');
        expect($ex)->toBeInstanceOf(DomainException::class);
    });
});

// ── DED-002 — NegativeNetPayException ────────────────────────────────────────

describe('DED-002 — NegativeNetPayException', function () {
    it('has error code NEGATIVE_NET_PAY', function () {
        $ex = new NegativeNetPayException(10, 'EMP-010', -250.00);
        expect($ex->errorCode)->toBe('NEGATIVE_NET_PAY');
    });

    it('has http status 422', function () {
        $ex = new NegativeNetPayException(10, 'EMP-010', -250.00);
        expect($ex->httpStatus)->toBe(422);
    });

    it('captures employee_id in context', function () {
        $ex = new NegativeNetPayException(55, 'EMP-055', -100.00);
        expect($ex->context['employee_id'])->toBe(55);
    });

    it('message mentions employee code', function () {
        $ex = new NegativeNetPayException(55, 'EMP-055', -100.00);
        expect($ex->getMessage())->toContain('EMP-055');
    });
});

// ── EDGE-003/010 — zero_pay flag ──────────────────────────────────────────────

describe('EDGE-003/010 — zero_pay flag (Step17)', function () {
    uses(RefreshDatabase::class);

    beforeEach(fn () => $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0));

    it('sets isZeroPay=true when gross_pay = 0 (full LWOP / zero attendance)', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 100_000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2026-07-01',
            'cutoff_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = 0;
        $ctx->sssEeCentavos = 0;
        $ctx->philhealthEeCentavos = 0;
        $ctx->pagibigEeCentavos = 0;
        $ctx->withholdingTaxCentavos = 0;
        $ctx->loanDeductionsCentavos = 0;
        $ctx->otherDeductionsCentavos = 0;
        $ctx->daysWorked = 0;

        (new Step17NetPayStep)($ctx, fn ($c) => $c);

        expect($ctx->isZeroPay)->toBeTrue()
            ->and($ctx->netPayCentavos)->toBe(0);
    });

    it('does not set isZeroPay when employee has some pay', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
            'basic_monthly_rate' => 500_000,
        ]);
        $run = PayrollRun::factory()->create([
            'status' => 'processing',
            'cutoff_start' => '2026-08-01',
            'cutoff_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
        ]);

        $ctx = new PayrollComputationContext($employee, $run);
        $ctx->grossPayCentavos = 100_000;
        $ctx->sssEeCentavos = 0;
        $ctx->philhealthEeCentavos = 0;
        $ctx->pagibigEeCentavos = 0;
        $ctx->withholdingTaxCentavos = 0;
        $ctx->loanDeductionsCentavos = 0;
        $ctx->otherDeductionsCentavos = 0;
        $ctx->daysWorked = 5;

        (new Step17NetPayStep)($ctx, fn ($c) => $c);

        expect($ctx->isZeroPay)->toBeFalse();
    });
});
