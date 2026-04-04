<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicRecruitmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder']);
    }

    public function test_public_can_list_active_recruitment_postings(): void
    {
        JobPosting::factory()->direct()->published()->create();
        JobPosting::factory()->direct()->published()->create(['closes_at' => now()->subDay()]);
        JobPosting::factory()->direct()->create(['status' => 'draft']);

        $this->getJson('/api/v1/public/recruitment/postings')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_public_can_submit_application_with_pdf_resume(): void
    {
        Storage::fake('local');

        $posting = JobPosting::factory()->direct()->published()->create();

        $response = $this->post('/api/v1/public/recruitment/applications', [
            'posting_ulid' => $posting->ulid,
            'candidate' => [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria.santos.public@example.test',
                'phone' => '09171234567',
                'address' => 'Dasmarinas, Cavite',
            ],
            'cover_letter' => 'I am excited to apply for this role and contribute to your production team.',
            'resume' => UploadedFile::fake()->create('resume.pdf', 150, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'new');

        $applicationUlid = (string) $response->json('data.application_ulid');

        $application = Application::query()->where('ulid', $applicationUlid)->firstOrFail();

        expect($application->candidate->resume_path)->not->toBeNull();
        Storage::disk('local')->assertExists((string) $application->candidate->resume_path);
    }

    public function test_hr_can_download_submitted_application_resume(): void
    {
        Storage::fake('local');

        $posting = JobPosting::factory()->direct()->published()->create();

        $this->post('/api/v1/public/recruitment/applications', [
            'posting_ulid' => $posting->ulid,
            'candidate' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan.delacruz.public@example.test',
                'phone' => '09181231234',
            ],
            'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $application = Application::query()->latest('id')->firstOrFail();

        $hrManager = User::factory()->create();
        $hrManager->assignRole('admin');

        $this->actingAs($hrManager)
            ->get("/api/v1/recruitment/applications/{$application->ulid}/resume")
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
