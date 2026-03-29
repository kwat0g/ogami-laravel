<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Candidate;
use App\Domains\HR\Recruitment\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Application> */
final class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'job_posting_id' => JobPostingFactory::new()->published(),
            'candidate_id' => CandidateFactory::new(),
            'cover_letter' => fake()->optional()->paragraphs(2, true),
            'application_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'source' => fake()->randomElement(['referral', 'walk_in', 'job_board', 'agency', 'internal']),
            'status' => 'new',
        ];
    }

    public function shortlisted(): static
    {
        return $this->state(fn () => ['status' => 'shortlisted']);
    }

    public function underReview(): static
    {
        return $this->state(fn () => ['status' => 'under_review']);
    }
}
