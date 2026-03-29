<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InterviewSchedule> */
final class InterviewScheduleFactory extends Factory
{
    protected $model = InterviewSchedule::class;

    public function definition(): array
    {
        return [
            'application_id' => ApplicationFactory::new()->shortlisted(),
            'round' => 1,
            'type' => fake()->randomElement(['panel', 'one_on_one', 'technical', 'hr_screening', 'final']),
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'location' => fake()->optional()->address(),
            'interviewer_id' => User::factory(),
            'status' => 'scheduled',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'scheduled_at' => now()->subDays(2)]);
    }
}
