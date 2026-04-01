<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Domains\HR\Recruitment\Services\RequisitionService;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RequisitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RequisitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder']);
        $this->service = app(RequisitionService::class);
    }

    public function test_can_create_draft_requisition(): void
    {
        $user = User::factory()->create();
        $dept = Department::factory()->create();
        $pos = Position::factory()->create(['department_id' => $dept->id]);

        $requisition = $this->service->create([
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'employment_type' => 'regular',
            'headcount' => 2,
            'reason' => 'Business expansion requires additional staff.',
        ], $user);

        $this->assertNotNull($requisition->id);
        $this->assertEquals(RequisitionStatus::Draft, $requisition->status);
        $this->assertStringStartsWith('REQ-', $requisition->requisition_number);
        $this->assertEquals($user->id, $requisition->requested_by);
    }

    public function test_can_submit_draft_requisition(): void
    {
        $user = User::factory()->create();
        $requisition = JobRequisition::factory()->create([
            'requested_by' => $user->id,
            'status' => 'draft',
        ]);

        $result = $this->service->submit($requisition, $user);

        $this->assertEquals(RequisitionStatus::PendingApproval, $result->status);
    }

    public function test_cannot_submit_already_pending_requisition(): void
    {
        $user = User::factory()->create();
        $requisition = JobRequisition::factory()->create([
            'requested_by' => $user->id,
            'status' => 'pending_approval',
        ]);

        $this->expectException(InvalidStateTransitionException::class);

        $this->service->submit($requisition, $user);
    }

    public function test_hr_manager_can_approve_pending_requisition(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $requisition = JobRequisition::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending_approval',
        ]);

        $result = $this->service->approve($requisition, $approver, 'Approved for hiring.');

        $this->assertEquals(RequisitionStatus::Approved, $result->status);
        $this->assertEquals($approver->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_hr_manager_can_reject_with_reason(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $requisition = JobRequisition::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending_approval',
        ]);

        $result = $this->service->reject($requisition, $approver, 'Budget constraints.');

        $this->assertEquals(RequisitionStatus::Rejected, $result->status);
        $this->assertEquals('Budget constraints.', $result->rejection_reason);
    }

    public function test_self_approval_is_blocked(): void
    {
        $user = User::factory()->create();
        $requisition = JobRequisition::factory()->create([
            'requested_by' => $user->id,
            'status' => 'pending_approval',
        ]);

        $this->expectException(DomainException::class);

        $this->service->approve($requisition, $user);
    }

    public function test_cancelled_requisition_cannot_be_approved(): void
    {
        $user = User::factory()->create();
        $approver = User::factory()->create();
        $requisition = JobRequisition::factory()->create([
            'requested_by' => $user->id,
            'status' => 'cancelled',
        ]);

        $this->expectException(InvalidStateTransitionException::class);

        $this->service->approve($requisition, $approver);
    }

    public function test_can_cancel_draft_requisition(): void
    {
        $user = User::factory()->create();
        $requisition = JobRequisition::factory()->create([
            'requested_by' => $user->id,
            'status' => 'draft',
        ]);

        $result = $this->service->cancel($requisition, $user, 'No longer needed.');

        $this->assertEquals(RequisitionStatus::Cancelled, $result->status);
    }
}
