<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Payroll\Services\PayrollComputationService;
use App\Domains\Payroll\Services\PayrollPostingService;
use Tests\Support\PayrollTestHelper;

/*
|--------------------------------------------------------------------------
| Payroll → General Ledger Integration Tests
|--------------------------------------------------------------------------
| Verifies that locking a payroll run causes PayrollPostingService to
| generate a balanced, correctly-coded journal entry in the GL.
|
| GL account mapping (from chart of accounts seeder):
|   Salaries Expense (Dr)        — 5001
|   SSS Payable (Cr)             — 2100
|   PhilHealth Payable (Cr)      — 2101
|   PagIBIG Payable (Cr)         — 2102
|   Withholding Tax Payable (Cr) — 2103
|   Payroll Payable / Cash (Cr)  — 2200
--------------------------------------------------------------------------
*/

beforeEach(function () {
    PayrollTestHelper::seedRateTables();
    $this->artisan('db:seed', ['--class' => 'ChartOfAccountsSeeder'])->assertExitCode(0);

    $this->computeSvc = app(PayrollComputationService::class);
    $this->postSvc = app(PayrollPostingService::class);

    // Create fiscal periods for 2025
    \App\Domains\Accounting\Models\FiscalPeriod::create([
        'name' => 'FY 2025',
        'date_from' => '2025-01-01',
        'date_to' => '2025-12-31',
        'status' => 'open',
    ]);
});

// ---------------------------------------------------------------------------
// INT-PAY-GL-001: Payroll run creates a balanced JE in the GL
// ---------------------------------------------------------------------------

it('INT-PAY-GL-001 — locking a payroll run creates a balanced JE', function () {
    $employee = PayrollTestHelper::makeEmployee(25_000.00);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    $this->computeSvc->computeForEmployee($employee, $run);

    // Lock the run
    $run->status = 'locked';
    $run->save();

    // Post to GL
    $this->postSvc->postPayrollRun($run);

    $je = JournalEntry::where('source_type', 'payroll')
        ->where('source_id', $run->id)
        ->first();

    expect($je)->not->toBeNull();
    expect($je->status)->toBe('posted');

    // Double-entry: total debits = total credits
    $totalDebits = (float) $je->lines()->sum('debit');
    $totalCredits = (float) $je->lines()->sum('credit');
    expect($totalDebits)->toEqual($totalCredits);
});

// ---------------------------------------------------------------------------
// INT-PAY-GL-002: JE lines contain correct account codes
// ---------------------------------------------------------------------------

it('INT-PAY-GL-002 — JE contains salaries expense debit and payables credits', function () {
    $employee = PayrollTestHelper::makeEmployee(25_000.00);
    $run = PayrollTestHelper::makeRun('2025-11-16', '2025-11-30');
    PayrollTestHelper::makeAttendance($employee, '2025-11-16', '2025-11-30');

    $this->computeSvc->computeForEmployee($employee, $run);
    $run->status = 'locked';
    $run->save();
    $this->postSvc->postPayrollRun($run);

    $je = JournalEntry::where('source_type', 'payroll')
        ->where('source_id', $run->id)
        ->first();

    $accountCodes = $je->lines()
        ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
        ->pluck('chart_of_accounts.code')
        ->toArray();

    // Must have a salaries expense debit
    expect($accountCodes)->toContain('5001');
    // Must have at least the payroll payable credit
    expect($accountCodes)->toContain('2200');
});

// ---------------------------------------------------------------------------
// INT-PAY-GL-003: Re-posting a locked run is idempotent
// ---------------------------------------------------------------------------

it('INT-PAY-GL-003 — re-posting the same run does not create a duplicate JE', function () {
    $employee = PayrollTestHelper::makeEmployee(25_000.00);
    $run = PayrollTestHelper::makeRun('2025-09-16', '2025-09-30');
    PayrollTestHelper::makeAttendance($employee, '2025-09-16', '2025-09-30');

    $this->computeSvc->computeForEmployee($employee, $run);
    $run->status = 'locked';
    $run->save();

    $this->postSvc->postPayrollRun($run);
    $this->postSvc->postPayrollRun($run); // 2nd call should be a no-op

    $count = JournalEntry::where('source_type', 'payroll')
        ->where('source_id', $run->id)
        ->count();

    expect($count)->toBe(1); // only one JE regardless of how many times posted
});

// ---------------------------------------------------------------------------
// INT-PAY-GL-004: Multi-employee run aggregates all employees into one JE
// ---------------------------------------------------------------------------

it('INT-PAY-GL-004 — multi-employee run produces a single aggregated JE', function () {
    $employees = [
        PayrollTestHelper::makeEmployee(25_000.00),
        PayrollTestHelper::makeEmployee(40_000.00),
        PayrollTestHelper::makeEmployee(60_000.00),
    ];
    $run = PayrollTestHelper::makeRun('2025-08-16', '2025-08-31');

    foreach ($employees as $emp) {
        PayrollTestHelper::makeAttendance($emp, '2025-08-16', '2025-08-31');
        $this->computeSvc->computeForEmployee($emp, $run);
    }

    $run->status = 'locked';
    $run->save();
    $this->postSvc->postPayrollRun($run);

    $jeCount = JournalEntry::where('source_type', 'payroll')
        ->where('source_id', $run->id)
        ->count();

    // Single JE per run, not per employee
    expect($jeCount)->toBe(1);

    $je = JournalEntry::where('source_type', 'payroll')
        ->where('source_id', $run->id)
        ->first();

    // Balanced
    expect((float) $je->lines()->sum('debit'))
        ->toEqual((float) $je->lines()->sum('credit'));
});
