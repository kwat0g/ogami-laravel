<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\Hiring;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Hiring> */
final class HiringFactory extends Factory
{
    protected $model = Hiring::class;

    public function definition(): array
    {
        return [
            'application_id' => ApplicationFactory::new()->shortlisted(),
            'job_requisition_id' => JobRequisitionFactory::new()->open(),
            'employee_id' => null,
            'status' => 'pending',
            'hired_at' => null,
            'start_date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'hired_by' => User::factory(),
        ];
    }

    public function hired(): static
    {
        return $this->state(fn () => [
            'status' => 'hired',
            'hired_at' => now(),
        ]);
    }
}
