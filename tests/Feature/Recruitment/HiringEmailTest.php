<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Candidate;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use App\Domains\HR\Recruitment\Models\PreEmploymentRequirement;
use App\Domains\HR\Recruitment\Services\HiringService;
use App\Mail\Recruitment\HiredCongratulationsMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class HiringEmailTest extends TestCase
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

    private function createHireableApplication(string $candidateEmail = 'hired@example.com'): Application
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

        $candidate = Candidate::factory()->create(['email' => $candidateEmail]);

        $app = Application::factory()->shortlisted()->create([
            'job_posting_id' => $posting->id,
            'candidate_id' => $candidate->id,
        ]);

        JobOffer::factory()->accepted()->create([
            'application_id' => $app->id,
            'offered_position_id' => $pos->id,
            'offered_department_id' => $dept->id,
        ]);

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

    public function test_vp_approve_sends_hired_email_to_candidate(): void
    {
        Mail::fake();

        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $app = $this->createHireableApplication('hired@example.com');

        $hiring = $this->service->hire($app, $this->hirePayload($app), $submitter);
        $this->service->vpApprove($hiring->fresh(), $approver);

        Mail::assertQueued(HiredCongratulationsMail::class, function (HiredCongratulationsMail $mail) {
            return $mail->hasTo('hired@example.com');
        });
    }

    public function test_vp_approve_does_not_send_email_when_candidate_has_no_email(): void
    {
        Mail::fake();

        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        $app = $this->createHireableApplication('');

        $hiring = $this->service->hire($app, $this->hirePayload($app), $submitter);
        $this->service->vpApprove($hiring->fresh(), $approver);

        Mail::assertNotQueued(HiredCongratulationsMail::class);
    }
}
