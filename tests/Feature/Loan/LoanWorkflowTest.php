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

    // Create loan type
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

    $this->head = User::factory()->create();
    $this->head->syncRoles(['head']);

    $this->manager = User::factory()->create();
    $this->manager->syncRoles(['manager']);

    $this->officer = User::factory()->create();
    $this->officer->syncRoles(['officer']);

    $this->vp = User::factory()->create();
    $this->vp->syncRoles(['vice_president']);
    
    $this->disburser = User::factory()->create();
    // Use an officer role but a different user for SoD
    $this->disburser->syncRoles(['officer']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Full workflow test
// ─────────────────────────────────────────────────────────────────────────────

it('completes full loan v2 workflow with different approvers', function () {
    // 1. Employee submits loan (V2 workflow)
    $response = $this->actingAs($this->employeeUser)
        ->postJson('/api/v1/loans', [
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'principal_centavos' => 500000,
            'term_months' => 12,
            'deduction_cutoff' => '1st',
            'purpose' => 'Test V2 Workflow',
        ]);
        
    $response->assertStatus(201);
    $loanUlid = $response->json('data.ulid') ?? $response->json('data.id');
    $loan = Loan::where('ulid', $loanUlid)->first();
    
    expect($loan)->not->toBeNull();
    
    expect($loan->workflow_version)->toBe(2);
    expect($loan->status)->toBe('pending');

    // 2. Head notes
    $this->actingAs($this->head)
        ->patchJson("/api/v1/loans/{$loan->ulid}/head-note", [
            'remarks' => 'Head noted',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'head_noted');

    $loan->refresh();
    expect($loan->head_noted_by)->toBe($this->head->id);

    // 3. Manager checks
    $this->actingAs($this->manager)
        ->patchJson("/api/v1/loans/{$loan->ulid}/manager-check", [
            'remarks' => 'Manager checked',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'manager_checked');

    $loan->refresh();
    expect($loan->manager_checked_by)->toBe($this->manager->id);

    // 4. Officer reviews
    $this->actingAs($this->officer)
        ->patchJson("/api/v1/loans/{$loan->ulid}/officer-review", [
            'remarks' => 'Officer reviewed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'officer_reviewed');

    $loan->refresh();
    expect($loan->officer_reviewed_by)->toBe($this->officer->id);

    // 5. VP approves
    $this->actingAs($this->vp)
        ->patchJson("/api/v1/loans/{$loan->ulid}/vp-approve", [
            'remarks' => 'VP approved',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_for_disbursement');

    $loan->refresh();
    expect($loan->vp_approved_by)->toBe($this->vp->id);

    // 6. Disbursement
    $this->actingAs($this->disburser)
        ->patchJson("/api/v1/loans/{$loan->ulid}/disburse")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $loan->refresh();
    expect($loan->status)->toBe('active');
    expect($loan->disbursed_by)->toBe($this->disburser->id);
    expect($loan->disbursed_at)->not->toBeNull();
});

it('creates GL entry on vp approval in v2 workflow', function () {
    $loan = Loan::create([
        'workflow_version' => 2,
        'reference_no' => 'LN-2026-0001',
        'employee_id' => $this->employee->id,
        'loan_type_id' => $this->loanType->id,
        'requested_by' => $this->employeeUser->id,
        'principal_centavos' => 500000, // 5,000 PHP
        'term_months' => 12,
        'interest_rate_annual' => 0.06,
        'status' => 'officer_reviewed',
        'officer_reviewed_by' => $this->officer->id,
        'officer_reviewed_at' => now(),
        'loan_date' => now()->toDateString(),
    ]);

    $this->actingAs($this->vp)
        ->patchJson("/api/v1/loans/{$loan->ulid}/vp-approve")
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

