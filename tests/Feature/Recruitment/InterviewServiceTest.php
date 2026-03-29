<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Recruitment\Enums\InterviewStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Domains\HR\Recruitment\Services\InterviewService;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InterviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private InterviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->service = app(InterviewService::class);
    }

    public function test_can_schedule_interview_for_shortlisted_application(): void
    {
        $user = User::factory()->create();
        $interviewer = User::factory()->create();
        $app = Application::factory()->shortlisted()->create();

        $interview = $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
            'interviewer_id' => $interviewer->id,
            'location' => 'Conference Room A',
        ], $user);

        $this->assertNotNull($interview->id);
        $this->assertEquals(InterviewStatus::Scheduled, $interview->status);
        $this->assertEquals(1, $interview->round);
        $this->assertEquals($interviewer->id, $interview->interviewer_id);
    }

    public function test_cannot_schedule_interview_for_new_application(): void
    {
        $user = User::factory()->create();
        $interviewer = User::factory()->create();
        $app = Application::factory()->create(['status' => 'new']);

        $this->expectException(DomainException::class);

        $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'interviewer_id' => $interviewer->id,
        ], $user);
    }

    public function test_can_submit_evaluation(): void
    {
        $user = User::factory()->create();
        $interview = InterviewSchedule::factory()->create(['status' => 'scheduled']);

        $evaluation = $this->service->submitEvaluation($interview, [
            'scorecard' => [
                ['criterion' => 'Communication', 'score' => 4, 'comments' => 'Good'],
                ['criterion' => 'Technical', 'score' => 5, 'comments' => 'Excellent'],
            ],
            'recommendation' => 'endorse',
            'general_remarks' => 'Strong candidate.',
        ], $user);

        $this->assertNotNull($evaluation->id);
        $this->assertEquals(4.5, (float) $evaluation->overall_score);
        $this->assertEquals('endorse', $evaluation->recommendation->value);
        // Interview should be auto-completed
        $this->assertEquals(InterviewStatus::Completed, $interview->fresh()->status);
    }

    public function test_cannot_submit_evaluation_twice(): void
    {
        $user = User::factory()->create();
        $interview = InterviewSchedule::factory()->create(['status' => 'scheduled']);

        $this->service->submitEvaluation($interview, [
            'scorecard' => [['criterion' => 'Test', 'score' => 3, 'comments' => '']],
            'recommendation' => 'hold',
        ], $user);

        $this->expectException(DomainException::class);

        $this->service->submitEvaluation($interview, [
            'scorecard' => [['criterion' => 'Test', 'score' => 4, 'comments' => '']],
            'recommendation' => 'endorse',
        ], $user);
    }

    public function test_can_cancel_scheduled_interview(): void
    {
        $user = User::factory()->create();
        $interview = InterviewSchedule::factory()->create(['status' => 'scheduled']);

        $result = $this->service->cancel($interview, $user, 'Candidate unavailable');

        $this->assertEquals(InterviewStatus::Cancelled, $result->status);
    }

    public function test_can_mark_no_show(): void
    {
        $user = User::factory()->create();
        $interview = InterviewSchedule::factory()->create(['status' => 'scheduled']);

        $result = $this->service->markNoShow($interview, $user);

        $this->assertEquals(InterviewStatus::NoShow, $result->status);
    }

    public function test_cannot_cancel_completed_interview(): void
    {
        $user = User::factory()->create();
        $interview = InterviewSchedule::factory()->completed()->create();

        $this->expectException(DomainException::class);

        $this->service->cancel($interview, $user, 'Too late');
    }

    public function test_auto_increments_round_number(): void
    {
        $user = User::factory()->create();
        $interviewer = User::factory()->create();
        $app = Application::factory()->shortlisted()->create();

        $round1 = $this->service->schedule($app, [
            'type' => 'hr_screening',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'interviewer_id' => $interviewer->id,
        ], $user);

        $round2 = $this->service->schedule($app, [
            'type' => 'technical',
            'scheduled_at' => now()->addDays(5)->toIso8601String(),
            'interviewer_id' => $interviewer->id,
        ], $user);

        $this->assertEquals(1, $round1->round);
        $this->assertEquals(2, $round2->round);
    }
}
