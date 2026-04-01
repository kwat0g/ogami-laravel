<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Services\OfferService;
use App\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OfferServiceTest extends TestCase
{
    use RefreshDatabase;

    private OfferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder']);
        $this->service = app(OfferService::class);
    }

    public function test_can_prepare_offer(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->shortlisted()->create();
        $pos = Position::factory()->create();
        $dept = Department::factory()->create();

        $offer = $this->service->prepareOffer($app, [
            'offered_position_id' => $pos->id,
            'offered_department_id' => $dept->id,
            'offered_salary' => 3500000, // 35,000 PHP in centavos
            'employment_type' => 'regular',
            'start_date' => now()->addMonth()->toDateString(),
        ], $user);

        $this->assertNotNull($offer->id);
        $this->assertEquals(OfferStatus::Draft, $offer->status);
        $this->assertEquals(3500000, $offer->offered_salary);
        $this->assertStringStartsWith('OFR-', $offer->offer_number);
    }

    public function test_sending_offer_transitions_to_sent(): void
    {
        $user = User::factory()->create();
        $offer = JobOffer::factory()->create(['status' => 'draft']);

        $result = $this->service->sendOffer($offer, $user);

        $this->assertEquals(OfferStatus::Sent, $result->status);
        $this->assertNotNull($result->sent_at);
        $this->assertNotNull($result->expires_at);
    }

    public function test_accepting_offer_transitions_to_accepted(): void
    {
        $offer = JobOffer::factory()->sent()->create();

        $result = $this->service->acceptOffer($offer);

        $this->assertEquals(OfferStatus::Accepted, $result->status);
        $this->assertNotNull($result->responded_at);
    }

    public function test_expired_offer_cannot_be_accepted(): void
    {
        $offer = JobOffer::factory()->create(['status' => 'expired']);

        $this->expectException(InvalidStateTransitionException::class);

        $this->service->acceptOffer($offer);
    }

    public function test_offer_salary_stored_as_centavos(): void
    {
        $user = User::factory()->create();
        $app = Application::factory()->shortlisted()->create();
        $pos = Position::factory()->create();
        $dept = Department::factory()->create();

        $offer = $this->service->prepareOffer($app, [
            'offered_position_id' => $pos->id,
            'offered_department_id' => $dept->id,
            'offered_salary' => 2500000,
            'employment_type' => 'regular',
            'start_date' => now()->addMonth()->toDateString(),
        ], $user);

        $this->assertIsInt($offer->offered_salary);
        $this->assertEquals(2500000, $offer->offered_salary);
    }
}
