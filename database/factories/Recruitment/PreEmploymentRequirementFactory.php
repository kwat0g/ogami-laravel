<?php

declare(strict_types=1);

namespace Database\Factories\Recruitment;

use App\Domains\HR\Recruitment\Models\PreEmploymentRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PreEmploymentRequirement> */
final class PreEmploymentRequirementFactory extends Factory
{
    protected $model = PreEmploymentRequirement::class;

    public function definition(): array
    {
        return [
            'pre_employment_checklist_id' => PreEmploymentChecklistFactory::new(),
            'requirement_type' => fake()->randomElement(['nbi_clearance', 'medical_certificate', 'tin', 'sss', 'philhealth', 'pagibig', 'birth_certificate', 'id_photo']),
            'label' => fake()->sentence(3),
            'is_required' => true,
            'status' => 'pending',
        ];
    }
}
