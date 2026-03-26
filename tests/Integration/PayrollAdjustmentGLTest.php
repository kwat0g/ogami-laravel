<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Domains\Payroll\Services\PayrollComputationService;
use App\Domains\Payroll\Services\PayrollPostingService;
use App\Models\User;
use Tests\Support\PayrollTestHelper;

beforeEach(function () {
    PayrollTestHelper::seedRateTables();
    $this->artisan('db:seed', ['--class' => 'ChartOfAccountsSeeder'])->assertExitCode(0);

    // Seed open fiscal periods
    FiscalPeriod::create([
        'name' => 'Test Period',
        'date_from' => '2025-10-01',
        'date_to' => '2025-10-31',
        'status' => 'open',
    ]);

    $this->computeSvc = app(PayrollComputationService::class);
    $this->postSvc = app(PayrollPostingService::class);
});

it('INT-PAY-GL-005 — custom deduction maps to specific GL account', function () {
    $employee = PayrollTestHelper::makeEmployee(30_000.00);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    $user = User::first();
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    // Fetch a specific GL account to target (e.g., 6001 - Utilities Expense, though unusual for deduction, it proves mapping)
    $targetAccount = ChartOfAccount::where('code', '6001')->firstOrFail();

    // Create a deduction with this GL account
    PayrollAdjustment::create([
        'payroll_run_id' => $run->id,
        'employee_id' => $employee->id,
        'type' => 'deduction',
        'nature' => 'non_taxable',
        'description' => 'Utility Bill Deduction',
        'amount_centavos' => 500_00, // 500 pesos
        'gl_account_id' => $targetAccount->id,
        'created_by' => $user->id,
    ]);

    // Compute
    $this->computeSvc->computeForEmployee($employee, $run);

    // Verify status is applied (Step16 logic)
    $adjustment = PayrollAdjustment::where('payroll_run_id', $run->id)->first();
    expect($adjustment->status)->toBe('applied');

    // Lock and Post
    $run->status = 'locked';
    $run->save();
    $this->postSvc->postPayrollRun($run);

    $je = JournalEntry::where('source_type', 'payroll')
        ->where('source_id', $run->id)
        ->first();

    expect($je)->not->toBeNull();

    // Check if we have a credit line for account 6001
    $creditLine = $je->lines()->where('account_id', $targetAccount->id)->first();

    expect($creditLine)->not->toBeNull();
    expect((float) $creditLine->credit)->toBe(500.00);
    expect($creditLine->description)->toBe('Payroll deduction: Utility Bill Deduction');

    // Ensure JE is balanced
    $totalDebits = (float) $je->lines()->sum('debit');
    $totalCredits = (float) $je->lines()->sum('credit');
    expect($totalDebits)->toEqual($totalCredits);
});

it('INT-PAY-GL-006 — mixed deductions (custom GL and default)', function () {
    $employee = PayrollTestHelper::makeEmployee(30_000.00);
    $run = PayrollTestHelper::makeRun('2025-10-16', '2025-10-31');
    $user = User::first();
    PayrollTestHelper::makeAttendance($employee, '2025-10-16', '2025-10-31');

    $customAccount = ChartOfAccount::where('code', '6001')->firstOrFail();
    $defaultAccount = ChartOfAccount::where('code', '2001')->firstOrFail(); // Default AP

    // 1. Custom GL Deduction
    PayrollAdjustment::create([
        'payroll_run_id' => $run->id,
        'employee_id' => $employee->id,
        'type' => 'deduction',
        'nature' => 'non_taxable',
        'description' => 'Custom GL',
        'amount_centavos' => 500_00,
        'gl_account_id' => $customAccount->id,
        'created_by' => $user->id,
    ]);

    // 2. Default GL Deduction (null gl_account_id)
    PayrollAdjustment::create([
        'payroll_run_id' => $run->id,
        'employee_id' => $employee->id,
        'type' => 'deduction',
        'nature' => 'non_taxable',
        'description' => 'Default GL',
        'amount_centavos' => 300_00,
        'gl_account_id' => null,
        'created_by' => $user->id,
    ]);

    $this->computeSvc->computeForEmployee($employee, $run);

    $run->status = 'locked';
    $run->save();
    $this->postSvc->postPayrollRun($run);

    $je = JournalEntry::where('source_type', 'payroll')
        ->where('source_id', $run->id)
        ->first();

    // Verify custom line
    $customLine = $je->lines()->where('account_id', $customAccount->id)->first();
    expect((float) $customLine->credit)->toBe(500.00);

    // Verify default line
    $defaultLine = $je->lines()->where('account_id', $defaultAccount->id)->first();
    expect((float) $defaultLine->credit)->toBe(300.00);
    expect($defaultLine->description)->toBe('Other payroll deductions payable');

    // Balanced
    $totalDebits = (float) $je->lines()->sum('debit');
    $totalCredits = (float) $je->lines()->sum('credit');
    expect($totalDebits)->toEqual($totalCredits);
});
