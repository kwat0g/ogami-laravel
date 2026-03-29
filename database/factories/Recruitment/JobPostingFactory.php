<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobPosting> */
final class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    public function definition(): array
    {
        return [
            'job_requisition_id' => JobRequisitionFactory::new()->open(),
            'title' => fake()->jobTitle(),
            'description' => fake()->paragraphs(3, true),
            'requirements' => fake()->paragraphs(2, true),
            'location' => fake()->city(),
            'employment_type' => fake()->randomElement(['regular', 'contractual', 'project_based', 'part_time']),
            'is_internal' => false,
            'is_external' => true,
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
            'closes_at' => now()->addDays(30),
        ]);
    }
}
