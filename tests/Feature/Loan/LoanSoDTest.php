<?php

namespace Tests\Feature\Loan;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\HR\Models\Employee;
use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanType;
use App\Domains\Loan\Services\LoanRequestService;
use App\Models\User;
use App\Shared\Exceptions\SodViolationException;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoanSoDTest extends TestCase
{
    use RefreshDatabase;

    private User $accountingManager;

    private User $vp;

    private Employee $employee;

    private LoanType $loanType;

    private LoanRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        // Setup Fiscal Period for accounting ops
        FiscalPeriod::create([
            'name' => 'Current Period',
            'date_from' => now()->startOfMonth(),
            'date_to' => now()->endOfMonth(),
            'status' => 'open',
        ]);

        $this->accountingManager = User::factory()->create();
        $this->accountingManager->assignRole('officer');

        $this->vp = User::factory()->create();
        $this->vp->assignRole('vice_president');

        $this->employee = Employee::factory()->create([
            'user_id' => $this->accountingManager->id, // Employee linked to Accounting Manager
        ]);

        $this->loanType = LoanType::create([
            'name' => 'Emergency Loan',
            'code' => 'EMERGENCY',
            'interest_rate_annual' => 0,
            'max_term_months' => 12,
            'min_amount_centavos' => 100000,
            'max_amount_centavos' => 5000000,
            'category' => 'company',
        ]);

        $this->service = app(LoanRequestService::class);
    }

    #[Test]
    public function officer_cannot_approve_own_loan_accounting_stage()
    {
        // 1. Manually create a v1 loan request (bypass submit() which forces v2)
        $loan = Loan::create([
            'workflow_version' => 1,
            'reference_no' => 'LN-2026-TEST01',
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'requested_by' => $this->accountingManager->id,
            'principal_centavos' => 500000,
            'term_months' => 6,
            'interest_rate_annual' => 0,
            'monthly_amortization_centavos' => 0,
            'total_payable_centavos' => 0,
            'outstanding_balance_centavos' => 500000,
            'loan_date' => now()->toDateString(),
            'status' => 'pending',
            'purpose' => 'Emergency',
        ]);

        // 2. HR Approves (by another user)
        $hrManager = User::factory()->create();
        $hrManager->givePermissionTo('loans.approve');

        $loan = $this->service->approve(
            $loan,
            $hrManager->id,
            'Approved by HR',
            now()->addMonth()->toDateString()
        );

        $this->assertEquals('approved', $loan->status);

        // 3. Accounting Manager tries to approve their own loan (Accounting Stage)
        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Accounting approver must differ from requester (SoD).');

        $this->service->accountingApprove($loan, $this->accountingManager->id);
    }

    #[Test]
    public function officer_cannot_review_own_loan_v2_workflow()
    {
        // Setup Officer as employee
        $officer = User::factory()->create();
        $officer->assignRole('officer');
        $officerEmployee = Employee::factory()->create(['user_id' => $officer->id]);

        // 1. Officer submits loan (v2)
        $loan = $this->service->submit(
            $officerEmployee,
            [
                'loan_type_id' => $this->loanType->id,
                'principal_centavos' => 500000,
                'term_months' => 6,
                'purpose' => 'Officer Loan',
            ],
            $officer->id
        );

        // 2. Head Note & Manager Check
        $head = User::factory()->create();
        $this->service->headNote($loan, $head->id);

        $manager = User::factory()->create();
        $this->service->managerCheck($loan, $manager->id);

        $this->assertEquals('manager_checked', $loan->status);

        // 3. Officer tries to review their own loan
        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Officer reviewer must differ from requester (SoD).');

        $this->service->officerReview($loan, $officer->id);
    }

    #[Test]
    public function vp_cannot_approve_own_loan_v2_workflow()
    {
        // Setup VP as employee
        $vpEmployee = Employee::factory()->create([
            'user_id' => $this->vp->id,
        ]);

        // 1. VP submits loan (v2)
        $loan = $this->service->submit(
            $vpEmployee,
            [
                'loan_type_id' => $this->loanType->id,
                'principal_centavos' => 500000,
                'term_months' => 6,
                'purpose' => 'VP Loan',
            ],
            $this->vp->id
        );

        // 2. Approvals up to Officer
        $head = User::factory()->create();
        $this->service->headNote($loan, $head->id);

        $manager = User::factory()->create();
        $this->service->managerCheck($loan, $manager->id);

        $officer = User::factory()->create();
        $this->service->officerReview($loan, $officer->id);

        $this->assertEquals('officer_reviewed', $loan->status);

        // 3. VP tries to approve their own loan
        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('VP approver must differ from requester (SoD).');

        $this->service->vpApprove($loan, $this->vp->id);
    }
}
