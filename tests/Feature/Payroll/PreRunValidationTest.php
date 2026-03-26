<?php

declare(strict_types=1);

use App\Domains\Payroll\Models\PayPeriod;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Validators\PayrollRunValidator;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| PayrollRunValidator — Pre-Run Validation Tests
|--------------------------------------------------------------------------
| Tests each PR-XXX rule individually.  Rules guard the create path before
| any computation batch is dispatched.
|
| PR-001  assertNonOverlapping   — no duplicate active run for same type/period
| PR-002  assertCutoffOrder      — cutoff_start ≤ cutoff_end
| PR-003  assertCutoffOrder      — pay_date ≥ cutoff_end
| PR-004  assertActiveEmployeesExist — at least 1 active employee in the system
| PR-005  assertOpenPayPeriodExists  — open pay period must cover the cutoff
--------------------------------------------------------------------------
*/

function makeValidator(): PayrollRunValidator
{
    return new PayrollRunValidator;
}

// ── PR-001: Non-overlapping runs ───────────────────────────────────────────

describe('PR-001 — assertNonOverlapping', function () {
    it('passes when no existing run overlaps the proposed range', function () {
        $validator = makeValidator();
        // No rows in DB → should not throw
        expect(fn () => $validator->assertNonOverlapping('2026-03-01', '2026-03-15', 'regular'))
            ->not->toThrow(DomainException::class);
    });

    it('throws PR_OVERLAP when an active run covers the same dates', function () {
        // Seed an existing run
        PayrollRun::factory()->create([
            'cutoff_start' => '2026-03-01',
            'cutoff_end' => '2026-03-15',
            'pay_date' => '2026-03-20',
            'run_type' => 'regular',
            'status' => 'draft',
        ]);

        $validator = makeValidator();

        expect(fn () => $validator->assertNonOverlapping('2026-03-05', '2026-03-20', 'regular'))
            ->toThrow(DomainException::class);
    });

    it('does not flag cancelled runs as overlapping (they are effectively void)', function () {
        PayrollRun::factory()->create([
            'cutoff_start' => '2026-03-01',
            'cutoff_end' => '2026-03-15',
            'pay_date' => '2026-03-20',
            'run_type' => 'regular',
            'status' => 'cancelled',
        ]);

        $validator = makeValidator();

        expect(fn () => $validator->assertNonOverlapping('2026-03-05', '2026-03-20', 'regular'))
            ->not->toThrow(DomainException::class);
    });

    it('does not flag a 13th_month run when checking regular overlap', function () {
        PayrollRun::factory()->create([
            'cutoff_start' => '2026-03-01',
            'cutoff_end' => '2026-03-31',
            'pay_date' => '2026-04-05',
            'run_type' => 'thirteenth_month',
            'status' => 'draft',
        ]);

        $validator = makeValidator();

        expect(fn () => $validator->assertNonOverlapping('2026-03-01', '2026-03-31', 'regular'))
            ->not->toThrow(DomainException::class);
    });
});

// ── PR-002: cutoff_start ≤ cutoff_end ─────────────────────────────────────

describe('PR-002 — assertCutoffOrder (start ≤ end)', function () {
    it('passes when cutoff_start equals cutoff_end', function () {
        $validator = makeValidator();
        expect(fn () => $validator->assertCutoffOrder('2026-03-15', '2026-03-15', '2026-03-20'))
            ->not->toThrow(DomainException::class);
    });

    it('passes with valid ordered dates', function () {
        $validator = makeValidator();
        expect(fn () => $validator->assertCutoffOrder('2026-03-01', '2026-03-15', '2026-03-20'))
            ->not->toThrow(DomainException::class);
    });

    it('throws PR_INVALID_CUTOFF when cutoff_start is after cutoff_end', function () {
        $validator = makeValidator();
        expect(fn () => $validator->assertCutoffOrder('2026-03-16', '2026-03-15', '2026-03-20'))
            ->toThrow(DomainException::class);
    });
});

// ── PR-003: pay_date ≥ cutoff_end ─────────────────────────────────────────

describe('PR-003 — assertCutoffOrder (pay_date ≥ cutoff_end)', function () {
    it('passes when pay_date equals cutoff_end', function () {
        $validator = makeValidator();
        expect(fn () => $validator->assertCutoffOrder('2026-03-01', '2026-03-15', '2026-03-15'))
            ->not->toThrow(DomainException::class);
    });

    it('passes when pay_date is after cutoff_end', function () {
        $validator = makeValidator();
        expect(fn () => $validator->assertCutoffOrder('2026-03-01', '2026-03-15', '2026-03-20'))
            ->not->toThrow(DomainException::class);
    });

    it('throws PR_PAYDATE_BEFORE_CUTOFF when pay_date is before cutoff_end', function () {
        $validator = makeValidator();
        expect(fn () => $validator->assertCutoffOrder('2026-03-01', '2026-03-15', '2026-03-10'))
            ->toThrow(DomainException::class);
    });
});

// ── PR-004: At least one active employee ──────────────────────────────────

describe('PR-004 — assertActiveEmployeesExist', function () {
    it('passes when at least one active employee exists', function () {
        DB::table('employees')->insert([
            'employee_code' => 'EMP-PR-001',
            'ulid' => (string) Str::ulid(),
            'first_name' => 'Active',
            'last_name' => 'Employee',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'civil_status' => 'SINGLE',
            'bir_status' => 'S',
            'employment_type' => 'regular',
            'date_hired' => '2022-01-01',
            'basic_monthly_rate' => 1500000,
            'employment_status' => 'active',
        ]);

        $validator = makeValidator();
        expect(fn () => $validator->assertActiveEmployeesExist())
            ->not->toThrow(DomainException::class);
    });

    it('throws PR_NO_ACTIVE_EMPLOYEES when no active employees exist', function () {
        // RefreshDatabase ensures table is empty
        $validator = makeValidator();
        expect(fn () => $validator->assertActiveEmployeesExist())
            ->toThrow(DomainException::class);
    });

    it('throws PR_NO_ACTIVE_EMPLOYEES when only terminated employees exist', function () {
        DB::table('employees')->insert([
            'employee_code' => 'EMP-PR-002',
            'ulid' => (string) Str::ulid(),
            'first_name' => 'Terminated',
            'last_name' => 'Person',
            'date_of_birth' => '1985-06-15',
            'gender' => 'female',
            'civil_status' => 'SINGLE',
            'bir_status' => 'S',
            'employment_type' => 'regular',
            'date_hired' => '2020-01-01',
            'basic_monthly_rate' => 1000000,
            'employment_status' => 'terminated',
        ]);

        $validator = makeValidator();
        expect(fn () => $validator->assertActiveEmployeesExist())
            ->toThrow(DomainException::class);
    });
});

// ── PR-005: Open pay period covers the cutoff range ───────────────────────

describe('PR-005 — assertOpenPayPeriodExists', function () {
    it('passes when an open pay period fully covers the cutoff range', function () {
        PayPeriod::factory()->create([
            'cutoff_start' => '2026-03-01',
            'cutoff_end' => '2026-03-31',
            'status' => 'open',
        ]);

        $validator = makeValidator();
        expect(fn () => $validator->assertOpenPayPeriodExists('2026-03-01', '2026-03-15'))
            ->not->toThrow(DomainException::class);
    });

    it('throws PR_NO_OPEN_PERIOD when no pay period exists', function () {
        $validator = makeValidator();
        expect(fn () => $validator->assertOpenPayPeriodExists('2026-03-01', '2026-03-15'))
            ->toThrow(DomainException::class);
    });

    it('throws PR_NO_OPEN_PERIOD when the only matching period is closed', function () {
        PayPeriod::factory()->create([
            'cutoff_start' => '2026-03-01',
            'cutoff_end' => '2026-03-31',
            'status' => 'closed',
        ]);

        $validator = makeValidator();
        expect(fn () => $validator->assertOpenPayPeriodExists('2026-03-01', '2026-03-15'))
            ->toThrow(DomainException::class);
    });

    it('throws PR_NO_OPEN_PERIOD when cutoff extends beyond every open period', function () {
        // Period only covers 03-01 to 03-15; run tries 03-01 to 03-20 (extends beyond)
        PayPeriod::factory()->create([
            'cutoff_start' => '2026-03-01',
            'cutoff_end' => '2026-03-15',
            'status' => 'open',
        ]);

        $validator = makeValidator();
        expect(fn () => $validator->assertOpenPayPeriodExists('2026-03-01', '2026-03-20'))
            ->toThrow(DomainException::class);
    });
});
