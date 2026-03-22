<?php

namespace Tests\Feature\Procurement;

use App\Domains\HR\Models\Department;
use App\Domains\Procurement\Models\PurchaseRequest;
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
    public function purchasing_officer_cannot_review_own_pr()
    {
        // Create PR in pending_review status (as if just submitted by creator)
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD01',
            'department_id' => $this->department->id,
            'requested_by_id' => $this->user->id,
            'status' => 'pending_review',
            'total_estimated_cost' => 100000,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Purchasing reviewer cannot be the same person who created the PR (SoD).');

        $this->service->review($pr, $this->user);
    }

    #[Test]
    public function accounting_officer_cannot_verify_budget_for_own_pr()
    {
        $otherUser = User::factory()->create();
        
        // Create PR in reviewed status (approved by Purchasing, now at Accounting)
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD02',
            'department_id' => $this->department->id,
            'requested_by_id' => $this->user->id,
            'status' => 'reviewed',
            'total_estimated_cost' => 100000,
            'reviewed_by_id' => $otherUser->id, // Different user reviewed it
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('Budget verifier cannot be the same person who created the PR (SoD).');

        $this->service->budgetCheck($pr, $this->user);
    }

    #[Test]
    public function vp_cannot_approve_own_pr()
    {
        $otherUser = User::factory()->create();
        
        // Create PR in budget_verified status (approved by Accounting, now at VP)
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD03',
            'department_id' => $this->department->id,
            'requested_by_id' => $this->user->id,
            'status' => 'budget_verified',
            'total_estimated_cost' => 100000,
            'reviewed_by_id' => $otherUser->id,
            'budget_checked_by_id' => $otherUser->id,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        $this->expectException(SodViolationException::class);
        $this->expectExceptionMessage('VP approver must differ from requester (SoD).');

        $this->service->vpApprove($pr, $this->user);
    }

    #[Test]
    public function budget_verifier_cannot_be_reviewer()
    {
        $creator = User::factory()->create();
        $reviewer = User::factory()->create();
        
        // Create PR where reviewer is set (SoD: budget verifier cannot be same as reviewer)
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD04',
            'department_id' => $this->department->id,
            'requested_by_id' => $creator->id,
            'status' => 'reviewed',
            'total_estimated_cost' => 100000,
            'reviewed_by_id' => $reviewer->id,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        // The reviewer tries to also verify budget - should fail
        $this->expectException(\App\Shared\Exceptions\DomainException::class);
        $this->expectExceptionMessage('Budget verifier cannot be the same person who reviewed the PR.');

        $this->service->budgetCheck($pr, $reviewer);
    }

    #[Test]
    public function vp_cannot_be_budget_verifier()
    {
        $creator = User::factory()->create();
        $budgetVerifier = User::factory()->create();
        
        // Create PR in budget_verified status
        $pr = PurchaseRequest::create([
            'pr_reference' => 'PR-2026-SOD05',
            'department_id' => $this->department->id,
            'requested_by_id' => $creator->id,
            'status' => 'budget_verified',
            'total_estimated_cost' => 100000,
            'reviewed_by_id' => User::factory()->create()->id,
            'budget_checked_by_id' => $budgetVerifier->id,
            'justification' => 'Test',
            'urgency' => 'normal',
        ]);

        // The budget verifier tries to also approve as VP - should fail
        $this->expectException(\App\Shared\Exceptions\DomainException::class);
        $this->expectExceptionMessage('VP cannot be the same person who verified the budget.');

        $this->service->vpApprove($pr, $budgetVerifier);
    }
}
