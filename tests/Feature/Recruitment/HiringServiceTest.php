<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Enums\HiringStatus;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Candidate;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use App\Domains\HR\Recruitment\Models\PreEmploymentRequirement;
use App\Domains\HR\Recruitment\Services\HiringService;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HiringServiceTest extends TestCase
{
    use RefreshDatabase;

    private HiringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder']);
        $this->service = app(HiringService::class);
    }

    private function createHireableApplication(): Application
    {
        $user = User::factory()->create();
        $dept = Department::factory()->create();
        $pos = Position::factory()->create(['department_id' => $dept->id]);

        $req = JobRequisition::factory()->open()->create([
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'requested_by' => $user->id,
            'headcount' => 1,
        ]);

        $posting = JobPosting::factory()->published()->create([
            'job_requisition_id' => $req->id,
        ]);

        $candidate = Candidate::factory()->create();

        $app = Application::factory()->shortlisted()->create([
            'job_posting_id' => $posting->id,
            'candidate_id' => $candidate->id,
        ]);

        // Create accepted offer
        JobOffer::factory()->accepted()->create([
            'application_id' => $app->id,
            'offered_position_id' => $pos->id,
            'offered_department_id' => $dept->id,
        ]);

        // Create completed pre-employment
        $checklist = PreEmploymentChecklist::factory()->completed()->create([
            'application_id' => $app->id,
        ]);
        PreEmploymentRequirement::factory()->create([
            'pre_employment_checklist_id' => $checklist->id,
            'status' => 'verified',
        ]);

        return $app;
    }

    private function hirePayload(Application $app): array
    {
        return [
            'start_date' => now()->addWeeks(2)->toDateString(),
            'first_name' => $app->candidate->first_name,
            'last_name' => $app->candidate->last_name,
            'date_of_birth' => '1990-01-01',
            'gender' => 'MALE',
            'civil_status' => 'SINGLE',
            'bir_status' => 'S',
            'present_address' => '123 Test Street, Makati City',
            'personal_email' => 'hire-test@example.com',
            'department_id' => $app->posting->requisition->department_id,
            'position_id' => $app->posting->requisition->position_id,
            'employment_type' => 'regular',
            'pay_frequency' => 'monthly',
            'base_salary_monthly_centavos' => 3_000_000,
        ];
    }

    public function test_hire_creates_employee_record(): void
    {
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $app = $this->createHireableApplication();

        $hiring = $this->service->hire($app, $this->hirePayload($app), $submitter);

        $this->assertEquals(HiringStatus::PendingVpApproval, $hiring->status);
        $this->assertNull($hiring->employee_id);
        $this->assertNull($hiring->hired_at);

        $hiring = $this->service->vpApprove($hiring->fresh(), $approver);
        $this->assertEquals(HiringStatus::Hired, $hiring->status);
        $this->assertNotNull($hiring->employee_id);
        $this->assertNotNull($hiring->hired_at);
    }

    public function test_hire_closes_requisition_when_headcount_fulfilled(): void
    {
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $app = $this->createHireableApplication();

        $hiring = $this->service->hire($app, $this->hirePayload($app), $submitter);

        $this->service->vpApprove($hiring->fresh(), $approver);

        $requisition = $app->posting->requisition->fresh();
        $this->assertEquals(RequisitionStatus::Closed, $requisition->status);
    }

    public function test_cannot_hire_without_accepted_offer(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->shortlisted()->create();
        // No offer created

        $this->expectException(DomainException::class);

        $this->service->hire($app, $this->hirePayload($app), $user);
    }

    public function test_cannot_hire_with_draft_offer(): void
    {
        $user = User::factory()->create();
        $dept = Department::factory()->create();
        $pos = Position::factory()->create(['department_id' => $dept->id]);
        $app = Application::factory()->shortlisted()->create();

        JobOffer::factory()->create([
            'application_id' => $app->id,
            'offered_position_id' => $pos->id,
            'offered_department_id' => $dept->id,
            'status' => 'draft',
        ]);

        $this->expectException(DomainException::class);

        $this->service->hire($app, $this->hirePayload($app), $user);
    }

    public function test_cannot_hire_with_incomplete_preemployment(): void
    {
        $user = User::factory()->create();
        $dept = Department::factory()->create();
        $pos = Position::factory()->create(['department_id' => $dept->id]);
        $app = Application::factory()->shortlisted()->create();

        JobOffer::factory()->accepted()->create([
            'application_id' => $app->id,
            'offered_position_id' => $pos->id,
            'offered_department_id' => $dept->id,
        ]);

        // Pre-employment in progress (not completed)
        PreEmploymentChecklist::create([
            'application_id' => $app->id,
            'status' => 'in_progress',
        ]);

        $this->expectException(DomainException::class);

        $this->service->hire($app, $this->hirePayload($app), $user);
    }
}
