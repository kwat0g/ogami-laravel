<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Services\ApplicationService;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->service = app(ApplicationService::class);
    }

    public function test_candidate_can_apply_to_open_posting(): void
    {
        $posting = JobPosting::factory()->published()->create();

        $application = $this->service->apply(
            $posting,
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan@example.com'],
            ['source' => 'walk_in'],
        );

        $this->assertNotNull($application->id);
        $this->assertEquals(ApplicationStatus::New, $application->status);
        $this->assertStringStartsWith('APP-', $application->application_number);
    }

    public function test_duplicate_application_is_rejected(): void
    {
        $posting = JobPosting::factory()->published()->create();

        $this->service->apply(
            $posting,
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan@example.com'],
            [],
        );

        $this->expectException(DomainException::class);

        $this->service->apply(
            $posting,
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan@example.com'],
            [],
        );
    }

    public function test_application_to_closed_posting_is_rejected(): void
    {
        $posting = JobPosting::factory()->create(['status' => 'closed']);

        $this->expectException(DomainException::class);

        $this->service->apply(
            $posting,
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan2@example.com'],
            [],
        );
    }

    public function test_hr_can_shortlist_application(): void
    {
        $user = User::factory()->create();
        $application = Application::factory()->create(['status' => 'new']);

        $result = $this->service->shortlist($application, $user);

        $this->assertEquals(ApplicationStatus::Shortlisted, $result->status);
    }

    public function test_hr_can_reject_application_with_reason(): void
    {
        $user = User::factory()->create();
        $application = Application::factory()->create(['status' => 'under_review']);

        $result = $this->service->reject($application, $user, 'Does not meet requirements.');

        $this->assertEquals(ApplicationStatus::Rejected, $result->status);
        $this->assertEquals('Does not meet requirements.', $result->rejection_reason);
    }

    public function test_candidate_can_withdraw(): void
    {
        $application = Application::factory()->create(['status' => 'new']);

        $result = $this->service->withdraw($application, 'Found another job.');

        $this->assertEquals(ApplicationStatus::Withdrawn, $result->status);
        $this->assertEquals('Found another job.', $result->withdrawn_reason);
    }
}
