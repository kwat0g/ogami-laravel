<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Models\SalaryGrade;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecruitmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $hrManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder']);

        $this->hrManager = User::factory()->create();
        $this->hrManager->assignRole('admin');
        // Grant recruitment permissions
        $this->hrManager->givePermissionTo([
            'recruitment.requisitions.view',
            'recruitment.requisitions.create',
            'recruitment.requisitions.edit',
            'recruitment.requisitions.submit',
            'recruitment.requisitions.approve',
            'recruitment.postings.view',
            'recruitment.postings.create',
            'recruitment.postings.publish',
            'recruitment.applications.view',
            'recruitment.applications.create',
            'recruitment.applications.review',
            'recruitment.applications.shortlist',
            'recruitment.applications.reject',
            'recruitment.interviews.view',
            'recruitment.interviews.schedule',
            'recruitment.interviews.evaluate',
            'recruitment.offers.view',
            'recruitment.offers.create',
            'recruitment.offers.send',
            'recruitment.candidates.view',
            'recruitment.reports.view',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_recruitment(): void
    {
        $this->getJson('/api/v1/recruitment/requisitions')
            ->assertStatus(401);
    }

    public function test_can_list_requisitions(): void
    {
        JobRequisition::factory()->count(3)->create();

        $this->actingAs($this->hrManager)
            ->getJson('/api/v1/recruitment/requisitions')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_create_requisition(): void
    {
        $dept = Department::factory()->create();
        $pos = Position::factory()->create(['department_id' => $dept->id]);

        $this->actingAs($this->hrManager)
            ->postJson('/api/v1/recruitment/requisitions', [
                'department_id' => $dept->id,
                'position_id' => $pos->id,
                'salary_grade_id' => \App\Domains\HR\Models\SalaryGrade::query()->inRandomOrder()->value('id') ?? 1,
                'employment_type' => 'regular',
                'headcount' => 2,
                'reason' => 'Need more staff for expanding operations in the department.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_can_view_requisition_detail(): void
    {
        $req = JobRequisition::factory()->create();

        $this->actingAs($this->hrManager)
            ->getJson("/api/v1/recruitment/requisitions/{$req->ulid}")
            ->assertOk()
            ->assertJsonPath('data.requisition_number', $req->requisition_number);
    }

    public function test_can_submit_requisition(): void
    {
        $req = JobRequisition::factory()->create([
            'requested_by' => $this->hrManager->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->hrManager)
            ->postJson("/api/v1/recruitment/requisitions/{$req->ulid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval');
    }

    public function test_can_list_postings(): void
    {
        JobPosting::factory()->count(2)->create();

        $this->actingAs($this->hrManager)
            ->getJson('/api/v1/recruitment/postings')
            ->assertOk();
    }

    public function test_can_create_direct_posting_without_requisition(): void
    {
        $department = Department::query()->inRandomOrder()->firstOrFail();
        $position = Position::query()->where('department_id', $department->id)->inRandomOrder()->firstOrFail();
        $salaryGrade = SalaryGrade::query()->inRandomOrder()->firstOrFail();

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/recruitment/postings', [
                'department_id' => $department->id,
                'position_id' => $position->id,
                'salary_grade_id' => $salaryGrade->id,
                'headcount' => 2,
                'title' => 'Assembly Operator',
                'description' => 'This posting is for direct recruitment to support increasing production demand this quarter.',
                'requirements' => 'At least one year manufacturing experience and good attendance record.',
                'employment_type' => 'regular',
                'is_internal' => false,
                'is_external' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.job_requisition_id', null)
            ->assertJsonPath('data.department.id', $department->id)
            ->assertJsonPath('data.position.id', $position->id)
            ->assertJsonPath('data.salary_grade.id', $salaryGrade->id)
            ->assertJsonPath('data.headcount', 2);

        $postingUlid = (string) $response->json('data.ulid');

        $this->actingAs($this->hrManager)
            ->getJson("/api/v1/recruitment/postings/{$postingUlid}")
            ->assertOk()
            ->assertJsonPath('data.job_requisition_id', null)
            ->assertJsonPath('data.department.id', $department->id)
            ->assertJsonPath('data.position.id', $position->id);
    }

    public function test_can_list_applications(): void
    {
        Application::factory()->count(3)->create();

        $this->actingAs($this->hrManager)
            ->getJson('/api/v1/recruitment/applications')
            ->assertOk();
    }

    public function test_can_create_application_for_direct_posting(): void
    {
        $posting = JobPosting::factory()->direct()->published()->create();

        $this->actingAs($this->hrManager)
            ->postJson('/api/v1/recruitment/applications', [
                'job_posting_id' => $posting->id,
                'candidate' => [
                    'first_name' => 'Jose',
                    'last_name' => 'Dela Cruz',
                    'email' => 'jose.delacruz.direct@example.test',
                    'phone' => '09171234567',
                    'source' => 'walk_in',
                ],
                'source' => 'walk_in',
                'cover_letter' => 'Experienced in production line setup and machine operation.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.posting.ulid', $posting->ulid)
            ->assertJsonPath('data.posting.requisition.ulid', null)
            ->assertJsonPath('data.posting.requisition.department', $posting->department?->name)
            ->assertJsonPath('data.posting.requisition.position', $posting->position?->title);
    }

    public function test_can_view_application_detail(): void
    {
        $app = Application::factory()->create();

        $this->actingAs($this->hrManager)
            ->getJson("/api/v1/recruitment/applications/{$app->ulid}")
            ->assertOk()
            ->assertJsonPath('data.application_number', $app->application_number);
    }

    public function test_can_shortlist_application(): void
    {
        $app = Application::factory()->create(['status' => 'new']);

        $this->actingAs($this->hrManager)
            ->postJson("/api/v1/recruitment/applications/{$app->ulid}/shortlist")
            ->assertOk()
            ->assertJsonPath('data.status', 'shortlisted');
    }

    public function test_can_reject_application_with_reason(): void
    {
        $app = Application::factory()->create(['status' => 'under_review']);

        $this->actingAs($this->hrManager)
            ->postJson("/api/v1/recruitment/applications/{$app->ulid}/reject", [
                'reason' => 'Does not meet minimum qualifications.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_reject_without_reason_fails_validation(): void
    {
        $app = Application::factory()->create(['status' => 'under_review']);

        $this->actingAs($this->hrManager)
            ->postJson("/api/v1/recruitment/applications/{$app->ulid}/reject", [])
            ->assertStatus(422);
    }

    public function test_can_access_dashboard(): void
    {
        $this->actingAs($this->hrManager)
            ->getJson('/api/v1/recruitment/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['kpis', 'pipeline_funnel', 'source_mix', 'recent_requisitions', 'upcoming_interviews'],
            ]);
    }

    public function test_can_access_pipeline_report(): void
    {
        $this->actingAs($this->hrManager)
            ->getJson('/api/v1/recruitment/reports/pipeline')
            ->assertOk();
    }

    public function test_can_list_interviewer_options_for_scheduler_dropdown(): void
    {
        $hrDepartment = Department::query()->where('code', 'HR')->first();
        if ($hrDepartment === null) {
            $hrDepartment = Department::factory()->create(['code' => 'HR', 'name' => 'Human Resources']);
        }

        $eligibleInterviewer = User::factory()->create(['name' => 'HR Officer Interviewer']);
        $eligibleInterviewer->assignRole('officer');
        $eligibleInterviewer->departments()->attach($hrDepartment->id, ['is_primary' => true]);

        $nonEligibleInterviewer = User::factory()->create(['name' => 'Production Manager']);
        $nonEligibleInterviewer->assignRole('manager');

        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/recruitment/interviewers/options?search=HR')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($eligibleInterviewer->id, $ids);
        $this->assertNotContains($nonEligibleInterviewer->id, $ids);
    }
}
