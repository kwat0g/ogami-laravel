<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\InterviewEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InterviewEvaluation> */
final class InterviewEvaluationFactory extends Factory
{
    protected $model = InterviewEvaluation::class;

    public function definition(): array
    {
        $scorecard = [
            ['criterion' => 'Communication', 'score' => fake()->numberBetween(1, 5), 'comments' => fake()->sentence()],
            ['criterion' => 'Technical Skills', 'score' => fake()->numberBetween(1, 5), 'comments' => fake()->sentence()],
            ['criterion' => 'Problem Solving', 'score' => fake()->numberBetween(1, 5), 'comments' => fake()->sentence()],
            ['criterion' => 'Culture Fit', 'score' => fake()->numberBetween(1, 5), 'comments' => fake()->sentence()],
        ];

        $scores = array_column($scorecard, 'score');
        $overall = round(array_sum($scores) / count($scores), 2);

        return [
            'interview_schedule_id' => InterviewScheduleFactory::new()->completed(),
            'submitted_by' => User::factory(),
            'scorecard' => $scorecard,
            'overall_score' => $overall,
            'recommendation' => fake()->randomElement(['endorse', 'reject', 'hold']),
            'general_remarks' => fake()->optional()->paragraph(),
            'submitted_at' => now(),
        ];
    }
}
