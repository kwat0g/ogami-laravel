<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Candidate;
use App\Domains\HR\Recruitment\Models\InterviewSchedule;
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
        $interviewer = User::factory()->create();
        $candidate = Candidate::factory()->create(['email' => 'candidate@example.com']);
        $app = Application::factory()->shortlisted()->create([
            'candidate_id' => $candidate->id,
        ]);

        $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
            'location' => 'Conference Room A',
            'interviewer_id' => $interviewer->id,
        ], $user);

        Mail::assertSent(InterviewScheduledMail::class, function (InterviewScheduledMail $mail) {
            return $mail->hasTo('candidate@example.com');
        });
    }

    public function test_scheduling_interview_does_not_send_email_when_candidate_has_no_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $interviewer = User::factory()->create();
        $candidate = Candidate::factory()->create(['email' => '']);
        $app = Application::factory()->shortlisted()->create([
            'candidate_id' => $candidate->id,
        ]);

        $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
            'interviewer_id' => $interviewer->id,
        ], $user);

        Mail::assertNothingSent();
    }

    public function test_rescheduling_interview_sends_email_to_candidate(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $interviewer = User::factory()->create();
        $candidate = Candidate::factory()->create(['email' => 'candidate@example.com']);
        $app = Application::factory()->shortlisted()->create([
            'candidate_id' => $candidate->id,
        ]);

        $interview = $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
            'location' => 'Conference Room A',
            'interviewer_id' => $interviewer->id,
        ], $user);

        Mail::assertSentCount(1);

        $this->service->reschedule($interview->fresh(), [
            'scheduled_at' => now()->addDays(4)->toIso8601String(),
            'duration_minutes' => 45,
            'location' => 'Conference Room B',
            'interviewer_id' => $interviewer->id,
        ], $user);

        Mail::assertSentCount(2);
        Mail::assertSent(InterviewScheduledMail::class, 2);
    }

    public function test_scheduling_again_with_pending_interview_resends_email_and_reuses_interview(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $interviewer = User::factory()->create();
        $candidate = Candidate::factory()->create(['email' => 'candidate@example.com']);
        $app = Application::factory()->shortlisted()->create([
            'candidate_id' => $candidate->id,
        ]);

        $first = $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
            'location' => 'Conference Room A',
            'interviewer_id' => $interviewer->id,
        ], $user);

        $second = $this->service->schedule($app->fresh(), [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(4)->toIso8601String(),
            'duration_minutes' => 30,
            'location' => 'Conference Room B',
            'interviewer_id' => $interviewer->id,
        ], $user);

        expect($first->id)->toBe($second->id);
        expect(InterviewSchedule::where('application_id', $app->id)->count())->toBe(1);
        Mail::assertSent(InterviewScheduledMail::class, 2);
    }
}
