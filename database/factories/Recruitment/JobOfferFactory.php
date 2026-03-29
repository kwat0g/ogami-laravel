<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobOffer> */
final class JobOfferFactory extends Factory
{
    protected $model = JobOffer::class;

    public function definition(): array
    {
        return [
            'application_id' => ApplicationFactory::new()->shortlisted(),
            'offered_position_id' => Position::factory(),
            'offered_department_id' => Department::factory(),
            'offered_salary' => fake()->numberBetween(2500000, 5000000),
            'employment_type' => 'regular',
            'start_date' => fake()->dateTimeBetween('+2 weeks', '+2 months')->format('Y-m-d'),
            'status' => 'draft',
            'prepared_by' => User::factory(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'sent_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
            'sent_at' => now()->subDays(3),
            'responded_at' => now(),
        ]);
    }
}
