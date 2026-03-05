<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\HR\Models\Employee;
use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses()->group('feature', 'loan');
uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed required data
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

    // Create fiscal period for current date
    FiscalPeriod::create([
        'name' => 'Current Period',
        'date_from' => Carbon::now()->startOfYear()->toDateString(),
        'date_to' => Carbon::now()->endOfYear()->toDateString(),
        'status' => 'open',
    ]);

    // Create roles and permissions
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    // Create loan type (interest rate as decimal, e.g., 0.06 = 6%)
    $this->loanType = LoanType::create([
        'code' => 'COMPANY',
        'name' => 'Company Loan',
        'category' => 'company',
        'interest_rate_annual' => 0.06,
        'min_amount_centavos' => 100000,
        'max_amount_centavos' => 5000000,
        'max_term_months' => 24,
        'is_active' => true,
    ]);

    // Create users with different roles
    $this->employeeUser = User::factory()->create();
    $this->employeeUser->syncRoles(['staff']);
    $this->employee = Employee::factory()->create(['user_id' => $this->employeeUser->id]);

    $this->supervisor = User::factory()->create();
    $this->supervisor->syncRoles(['supervisor']);

    $this->hrManager = User::factory()->create();
    $this->hrManager->syncRoles(['hr_manager']);

    $this->accountingManager = User::factory()->create();
    $this->accountingManager->syncRoles(['hr_manager']);

    $this->disburser = User::factory()->create();
    $this->disburser->syncRoles(['hr_manager']);
});

// ─────────────────────────────────────────────────────────────────────────────
// LN-010: SoD — Each approver must be different person
// ─────────────────────────────────────────────────────────────────────────────

it('prevents supervisor from approving their own loan request', function () {
    // Employee submits loan
    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'pending',
        'loan_date' => now()->toDateString(),
    ]);

    // Employee tries to approve as supervisor (same person)
    $response = $this->actingAs($this->employee->user)
        ->patchJson("/api/v1/loans/{$loan->ulid}/supervisor-approve", [
            'remarks' => 'Self approval',
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error_code', 'SOD_VIOLATION');
});

it('allows supervisor to approve loan after employee submits', function () {
    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'pending',
        'loan_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->supervisor)
        ->patchJson("/api/v1/loans/{$loan->ulid}/supervisor-approve", [
            'remarks' => 'Supervisor approved',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'supervisor_approved')
        ->assertJsonPath('data.supervisor_remarks', 'Supervisor approved');
});

it('prevents HR manager from approving if they were the supervisor', function () {
    // Create scenario where supervisor and HR manager are the same person
    $samePerson = User::factory()->create();
    $samePerson->syncRoles(['supervisor', 'hr_manager']);
    $samePerson->givePermissionTo(['loans.supervisor_review', 'loans.approve']);

    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'pending',
        'loan_date' => now()->toDateString(),
        'supervisor_approved_by' => $samePerson->id,
        'supervisor_approved_at' => now(),
    ]);

    // Same person tries to HR approve
    $response = $this->actingAs($samePerson)
        ->patchJson("/api/v1/loans/{$loan->ulid}/approve");

    $response->assertStatus(403)
        ->assertJsonPath('error_code', 'SOD_VIOLATION');
});

it('prevents accounting manager from approving if they were HR manager', function () {
    $samePerson = User::factory()->create();
    $samePerson->syncRoles(['hr_manager']);
    $samePerson->givePermissionTo(['loans.approve', 'loans.accounting_approve']);

    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'approved',
        'approved_by' => $samePerson->id,
        'approved_at' => now(),
        'loan_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($samePerson)
        ->patchJson("/api/v1/loans/{$loan->ulid}/accounting-approve");

    $response->assertStatus(403)
        ->assertJsonPath('error_code', 'SOD_VIOLATION');
});

it('prevents disburser from disbursing if they were accounting manager', function () {
    $samePerson = User::factory()->create();
    $samePerson->syncRoles(['hr_manager']);
    $samePerson->givePermissionTo(['loans.accounting_approve']);

    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'ready_for_disbursement',
        'approved_by' => $this->hrManager->id,
        'approved_at' => now(),
        'accounting_approved_by' => $samePerson->id,
        'accounting_approved_at' => now(),
        'loan_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($samePerson)
        ->patchJson("/api/v1/loans/{$loan->ulid}/disburse");

    $response->assertStatus(403)
        ->assertJsonPath('error_code', 'SOD_VIOLATION');
});

// ─────────────────────────────────────────────────────────────────────────────
// Full workflow test
// ─────────────────────────────────────────────────────────────────────────────

it('completes full loan workflow with different approvers', function () {
    // 1. Employee submits loan
    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'pending',
        'loan_date' => now()->toDateString(),
    ]);

    expect($loan->requested_by)->toBe($this->employeeUser->id);

    // 2. Supervisor approves
    $this->actingAs($this->supervisor)
        ->patchJson("/api/v1/loans/{$loan->ulid}/supervisor-approve", [
            'remarks' => 'Supervisor review passed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'supervisor_approved');

    $loan->refresh();
    expect($loan->supervisor_approved_by)->toBe($this->supervisor->id);
    expect($loan->supervisor_remarks)->toBe('Supervisor review passed');

    // 3. HR Manager approves
    $this->actingAs($this->hrManager)
        ->patchJson("/api/v1/loans/{$loan->ulid}/approve", [
            'remarks' => 'HR approved',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $loan->refresh();
    expect($loan->approved_by)->toBe($this->hrManager->id);

    // 4. Accounting Manager approves (creates GL entry)
    $this->actingAs($this->accountingManager)
        ->patchJson("/api/v1/loans/{$loan->ulid}/accounting-approve", [
            'remarks' => 'Funds verified',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_for_disbursement')
        ->assertJsonPath('data.accounting_remarks', 'Funds verified');

    $loan->refresh();
    expect($loan->accounting_approved_by)->toBe($this->accountingManager->id);
    expect($loan->journal_entry_id)->not->toBeNull();

    // 5. Disbursement
    $this->actingAs($this->disburser)
        ->patchJson("/api/v1/loans/{$loan->ulid}/disburse")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.disbursed_by', $this->disburser->id);

    $loan->refresh();
    expect($loan->status)->toBe('active');
    expect($loan->disbursed_by)->toBe($this->disburser->id);
    expect($loan->disbursed_at)->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// GL Integration tests
// ─────────────────────────────────────────────────────────────────────────────

it('creates GL entry on accounting approval', function () {
    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000, // 5,000 PHP
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'approved',
        'approved_by' => $this->hrManager->id,
        'approved_at' => now(),
    ]);

    $this->actingAs($this->accountingManager)
        ->patchJson("/api/v1/loans/{$loan->ulid}/accounting-approve")
        ->assertOk();

    $loan->refresh();

    // Verify GL entry was created
    expect($loan->journal_entry_id)->not->toBeNull();

    $je = \App\Domains\Accounting\Models\JournalEntry::find($loan->journal_entry_id);
    expect($je)->not->toBeNull();
    expect($je->source_type)->toBe('loan');
    expect($je->source_id)->toBe($loan->id);
    expect($je->status)->toBe('posted');

    // Verify JE lines (debit = credit)
    $totalDebit = $je->lines->sum('debit');
    $totalCredit = $je->lines->sum('credit');
    expect($totalDebit)->toBe(5000.0); // 5000 PHP
    expect($totalCredit)->toBe(5000.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Status transition tests
// ─────────────────────────────────────────────────────────────────────────────

it('prevents HR approval without supervisor approval first', function () {
    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'pending',
        'loan_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->hrManager)
        ->patchJson("/api/v1/loans/{$loan->ulid}/approve");

    $response->assertStatus(422)
        ->assertJsonPath('error_code', 'LN_NOT_SUPERVISOR_APPROVED');
});

it('prevents accounting approval without HR approval first', function () {
    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'supervisor_approved',
        'supervisor_approved_by' => $this->supervisor->id,
        'supervisor_approved_at' => now(),
    ]);

    // Try accounting approval (should fail - needs HR approval first)
    $response = $this->actingAs($this->accountingManager)
        ->patchJson("/api/v1/loans/{$loan->ulid}/accounting-approve");

    $response->assertStatus(422)
        ->assertJsonPath('error_code', 'LN_NOT_HR_APPROVED');
});

it('prevents disbursement without accounting approval first', function () {
    $loan = Loan::create([
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000,
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'approved',
        'approved_by' => $this->hrManager->id,
        'approved_at' => now(),
    ]);

    $response = $this->actingAs($this->disburser)
        ->patchJson("/api/v1/loans/{$loan->ulid}/disburse");

    $response->assertStatus(422)
        ->assertJsonPath('error_code', 'LN_NOT_ACCOUNTING_APPROVED');
});
