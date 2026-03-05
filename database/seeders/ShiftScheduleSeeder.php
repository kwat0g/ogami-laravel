<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the four standard company work schedules.
 * Uses upsert on `code` so the seeder is idempotent.
 *
 * is_night_shift is a plain boolean — set explicitly here per app convention.
 */
class ShiftScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $schedules = [
            [
                'code' => 'SHIFT-0600-1400',
                'name' => 'Day Shift (6AM–2PM)',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'break_minutes' => 60,
                'work_days' => '1,2,3,4,5',
                'crosses_midnight' => false,
                'is_night_shift' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'SHIFT-0600-1800',
                'name' => 'Extended Day Shift (6AM–6PM)',
                'start_time' => '06:00:00',
                'end_time' => '18:00:00',
                'break_minutes' => 60,
                'work_days' => '1,2,3,4,5',
                'crosses_midnight' => false,
                'is_night_shift' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'SHIFT-0800-1700',
                'name' => 'Regular Day Shift (8AM–5PM)',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'work_days' => '1,2,3,4,5',
                'crosses_midnight' => false,
                'is_night_shift' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'SHIFT-1800-0600',
                'name' => 'Night Shift (6PM–6AM)',
                'start_time' => '18:00:00',
                'end_time' => '06:00:00',
                'break_minutes' => 60,
                'work_days' => '1,2,3,4,5',
                'crosses_midnight' => true,
                'is_night_shift' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('shift_schedules')->upsert(
            $schedules,
            ['code'],
            ['name', 'start_time', 'end_time', 'break_minutes', 'work_days',
                'crosses_midnight', 'is_night_shift', 'is_active', 'updated_at'],
        );
    }
}
