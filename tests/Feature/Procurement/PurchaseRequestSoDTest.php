<?php

namespace Tests\Feature\Procurement;

use App\Domains\HR\Models\Department;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Domains\Procurement\Services\PurchaseRequestService;
use App\Models\User;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurchaseRequestSoDTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Department $department;
    private PurchaseRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        
        $this->user = User::factory()->create();
        $this->department = Department::create([
            'name' => 'Test Department',
            'code' => 'TEST-DEPT',
            'annual_budget_centavos' => 10000000,
            'fiscal_year_start_month' => 1,
            'is_active' => true,
        ]);
        
        $this->service = app(PurchaseRequestService::class);
    }

    #[Test]
    public function manager_cannot_check_own_pr()
    {
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD01',
            'department_id' => $this->department->id,
            'requested_by_id' => $this->user->id,
            'status' => 'noted',
            'total_estimated_cost' => 100000,
            'noted_by_id' => User::factory()->create()->id,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Manager checker must differ from requester (SoD).');

        $this->service->check($pr, $this->user);
    }

    #[Test]
    public function officer_cannot_review_own_pr()
    {
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD02',
            'department_id' => $this->department->id,
            'requested_by_id' => $this->user->id,
            'status' => 'checked',
            'total_estimated_cost' => 100000,
            'checked_by_id' => User::factory()->create()->id,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Officer reviewer must differ from requester (SoD).');

        $this->service->review($pr, $this->user);
    }

    #[Test]
    public function budget_officer_cannot_check_own_pr()
    {
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD03',
            'department_id' => $this->department->id,
            'requested_by_id' => $this->user->id,
            'status' => 'reviewed',
            'total_estimated_cost' => 100000,
            'reviewed_by_id' => User::factory()->create()->id,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Budget checker must differ from requester (SoD).');

        $this->service->budgetCheck($pr, $this->user);
    }

    #[Test]
    public function vp_cannot_approve_own_pr()
    {
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD04',
            'department_id' => $this->department->id,
            'requested_by_id' => $this->user->id,
            'status' => 'budget_checked',
            'total_estimated_cost' => 100000,
            'budget_checked_by_id' => User::factory()->create()->id,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('VP approver must differ from requester (SoD).');

        $this->service->vpApprove($pr, $this->user);
    }
}
