<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Maintenance\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
final class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'equipment_code' => 'EQ-'.$seq,
            'name' => 'Equipment '.$this->faker->word().' '.$seq,
            'category' => 'production',
            'manufacturer' => $this->faker->company(),
            'model_number' => 'MODEL-'.$seq,
            'serial_number' => $this->faker->uuid(),
            'location' => 'Line '.$this->faker->numberBetween(1, 5),
            'commissioned_on' => now()->subYears(2),
            'status' => 'operational',
            'is_active' => true,
        ];
    }
}
