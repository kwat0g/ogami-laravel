<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PreEmploymentChecklist> */
final class PreEmploymentChecklistFactory extends Factory
{
    protected $model = PreEmploymentChecklist::class;

    public function definition(): array
    {
        return [
            'application_id' => ApplicationFactory::new()->shortlisted(),
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
