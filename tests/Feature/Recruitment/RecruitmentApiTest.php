<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
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

    public function test_can_list_applications(): void
    {
        Application::factory()->count(3)->create();

        $this->actingAs($this->hrManager)
            ->getJson('/api/v1/recruitment/applications')
            ->assertOk();
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
}
