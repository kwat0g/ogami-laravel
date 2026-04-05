<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Candidate;
use App\Domains\HR\Recruitment\Services\InterviewService;
use App\Mail\Recruitment\InterviewScheduledMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class InterviewEmailTest extends TestCase
{
    use RefreshDatabase;

    private InterviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder']);
        $this->service = app(InterviewService::class);
    }

    public function test_scheduling_interview_sends_email_to_candidate(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $candidate = Candidate::factory()->create(['email' => 'candidate@example.com']);
        $app = Application::factory()->shortlisted()->create([
            'candidate_id' => $candidate->id,
        ]);

        $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
            'location' => 'Conference Room A',
        ], $user);

        Mail::assertQueued(InterviewScheduledMail::class, function (InterviewScheduledMail $mail) {
            return $mail->hasTo('candidate@example.com');
        });
    }

    public function test_scheduling_interview_does_not_send_email_when_candidate_has_no_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $candidate = Candidate::factory()->create(['email' => '']);
        $app = Application::factory()->shortlisted()->create([
            'candidate_id' => $candidate->id,
        ]);

        $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
        ], $user);

        Mail::assertNothingQueued();
    }
}
