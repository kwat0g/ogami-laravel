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

class LoanSoDAdditionalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private LoanType $loanType;

    private LoanRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        // Setup Fiscal Period
        FiscalPeriod::create([
            'name' => 'Current Period',
            'date_from' => now()->startOfMonth(),
            'date_to' => now()->endOfMonth(),
            'status' => 'open',
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('manager');

        $this->employee = Employee::factory()->create([
            'user_id' => $this->user->id,
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
    public function manager_cannot_check_own_loan_request()
    {
        // 1. Create a pending loan (v2 workflow)
        // We manually create because submit() might enforce validation logic we don't want to setup fully
        $loan = Loan::create([
            'workflow_version' => 2,
            'reference_no' => 'LN-2026-SOD01',
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'requested_by' => $this->user->id,
            'principal_centavos' => 500000,
            'term_months' => 6,
            'interest_rate_annual' => 0,
            'monthly_amortization_centavos' => 0,
            'total_payable_centavos' => 0,
            'outstanding_balance_centavos' => 500000,
            'loan_date' => now()->toDateString(),
            'status' => 'head_noted', // Ready for manager check
            'head_noted_by' => User::factory()->create()->id,
            'head_noted_at' => now(),
        ]);

        // 2. Manager tries to check their own loan
        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Manager checker must differ from requester (SoD).');

        $this->service->managerCheck($loan, $this->user->id);
    }

    #[Test]
    public function disburser_cannot_be_accounting_approver()
    {
        // 1. Create a loan ready for disbursement
        $accountingApprover = User::factory()->create();

        $loan = Loan::create([
            'workflow_version' => 2,
            'reference_no' => 'LN-2026-SOD02',
            'employee_id' => $this->employee->id,
            'loan_type_id' => $this->loanType->id,
            'requested_by' => $this->user->id,
            'principal_centavos' => 500000,
            'term_months' => 6,
            'interest_rate_annual' => 0,
            'monthly_amortization_centavos' => 0,
            'total_payable_centavos' => 0,
            'outstanding_balance_centavos' => 500000,
            'loan_date' => now()->toDateString(),
            'status' => 'ready_for_disbursement',
            'approved_by' => User::factory()->create()->id,
            'approved_at' => now(),
            'accounting_approved_by' => $accountingApprover->id,
            'accounting_approved_at' => now(),
        ]);

        // 2. Accounting approver tries to disburse
        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Disburser must differ from Accounting approver (SoD).');

        $this->service->disburse($loan, $accountingApprover->id);
    }
}
