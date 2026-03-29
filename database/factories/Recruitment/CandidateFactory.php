<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Candidate> */
final class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'source' => fake()->randomElement(['referral', 'walk_in', 'job_board', 'agency', 'internal']),
            'resume_path' => null,
            'linkedin_url' => fake()->optional()->url(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
