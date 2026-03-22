<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceWorkOrder>
 */
final class MaintenanceWorkOrderFactory extends Factory
{
    protected $model = MaintenanceWorkOrder::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'equipment_id' => Equipment::factory(),
            'mold_master_id' => null,
            'type' => 'corrective',
            'priority' => 'normal',
            'status' => 'open',
            'title' => 'Work Order ' . $seq,
            'description' => 'Test work order description',
            'reported_by_id' => null,
            'assigned_to_id' => null,
            'scheduled_date' => now()->addDays(3),
            'completed_at' => null,
            'completion_notes' => null,
            'labor_hours' => null,
            'mwo_reference' => 'MWO-' . $seq,
        ];
    }
}
