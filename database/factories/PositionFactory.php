<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * PositionFactory — provides sensible test defaults for HR positions.
 *
 * @extends Factory<Position>
 */
final class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        $titles = ['Software Engineer', 'Project Manager', 'HR Specialist', 'Accountant', 'Production Supervisor', 'Quality Inspector', 'Warehouse Clerk', 'Maintenance Technician'];
        $title = $titles[array_rand($titles)];

        return [
            'code' => 'POS-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'title' => $title.' '.$seq,
            'department_id' => Department::factory(),
            'pay_grade' => 'G'.rand(1, 15),
            'is_active' => true,
        ];
    }

    /**
     * Mark position as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
