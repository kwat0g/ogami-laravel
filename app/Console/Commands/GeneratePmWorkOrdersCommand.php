<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Models\PmSchedule;
use Illuminate\Console\Command;

/**
 * Auto-generates maintenance work orders from active PM schedules
 * whose next due date is today or before.
 * Designed to run daily via scheduler.
 */
final class GeneratePmWorkOrdersCommand extends Command
{
    protected $signature = 'maintenance:generate-pm-work-orders';

    protected $description = 'Auto-create maintenance work orders from due PM schedules';

    public function handle(): int
    {
        $today = now()->toDateString();

        $dueSchedules = PmSchedule::with('equipment')
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('last_done_on')
                    ->orWhereRaw("last_done_on + (frequency_days || ' days')::interval <= ?", [$today]);
            })
            ->get();

        $created = 0;

        foreach ($dueSchedules as $schedule) {
            // Check if a work order already exists for this schedule + equipment that isn't completed/cancelled
            $existing = MaintenanceWorkOrder::where('equipment_id', $schedule->equipment_id)
                ->where('type', 'preventive')
                ->where('title', "PM: {$schedule->task_name}")
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();

            if ($existing) {
                continue;
            }

            MaintenanceWorkOrder::create([
                'equipment_id' => $schedule->equipment_id,
                'type' => 'preventive',
                'priority' => 'medium',
                'status' => 'open',
                'title' => "PM: {$schedule->task_name}",
                'description' => "Auto-generated from PM schedule (frequency: every {$schedule->frequency_days} days). Equipment: ".($schedule->equipment->name ?? "#{$schedule->equipment_id}"),
                'scheduled_date' => now(),
                'created_by_id' => 1, // System user
            ]);

            $created++;
        }

        $this->info("Generated {$created} preventive maintenance work orders from {$dueSchedules->count()} due schedules.");

        return self::SUCCESS;
    }
}
