<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Models\SalaryGrade;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobRequisition> */
final class JobRequisitionFactory extends Factory
{
    protected $model = JobRequisition::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'requested_by' => User::factory(),
            'employment_type' => fake()->randomElement(['regular', 'contractual', 'project_based', 'part_time']),
            'headcount' => fake()->numberBetween(1, 5),
            'reason' => fake()->paragraph(),
            'justification' => fake()->optional()->paragraph(),
            'salary_grade_id' => \App\Domains\HR\Models\SalaryGrade::query()->inRandomOrder()->value('id') ?? 1,
            'target_start_date' => fake()->dateTimeBetween('+1 month', '+3 months')->format('Y-m-d'),
            'status' => 'draft',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => 'open',
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(3),
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn () => [
            'status' => 'pending_approval',
        ]);
    }
}
