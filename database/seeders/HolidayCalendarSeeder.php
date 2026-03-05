<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Philippine holidays for 2026 — Regular and Special Non-Working.
 *
 * Source: Proclamation No. 727 (President of the Philippines, 2025)
 * ATT-009: Holiday eligibility is always determined from this table.
 * EARN-008: type column is the authoritative source — never infer from name.
 *
 * An Admin must seed each year's proclamation before payroll runs begin.
 */
class HolidayCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $holidays2026 = [
            // [date, name, type]
            ['2026-01-01', "New Year's Day", 'REGULAR'],
            ['2026-04-02', 'Maundy Thursday', 'REGULAR'],
            ['2026-04-03', 'Good Friday', 'REGULAR'],
            ['2026-04-04', 'Black Saturday', 'SPECIAL_NON_WORKING'],
            ['2026-04-09', 'Araw ng Kagitingan (Day of Valor)', 'REGULAR'],
            ['2026-05-01', 'Labor Day', 'REGULAR'],
            ['2026-06-12', 'Independence Day', 'REGULAR'],
            ['2026-08-24', 'Ninoy Aquino Day', 'SPECIAL_NON_WORKING'],
            ['2026-08-31', 'National Heroes Day', 'REGULAR'],
            ['2026-11-01', "All Saints' Day", 'SPECIAL_NON_WORKING'],
            ['2026-11-02', "All Souls' Day", 'SPECIAL_NON_WORKING'],
            ['2026-11-30', 'Bonifacio Day', 'REGULAR'],
            ['2026-12-08', 'Immaculate Conception Day', 'SPECIAL_NON_WORKING'],
            ['2026-12-24', 'Christmas Eve', 'SPECIAL_NON_WORKING'],
            ['2026-12-25', 'Christmas Day', 'REGULAR'],
            ['2026-12-30', 'Rizal Day', 'REGULAR'],
            ['2026-12-31', "New Year's Eve", 'SPECIAL_NON_WORKING'],
        ];

        foreach ($holidays2026 as [$date, $name, $type]) {
            DB::table('holiday_calendars')->insertOrIgnore([
                'holiday_date' => $date,
                'year' => 2026,
                'name' => $name,
                'type' => $type,
                'is_nationwide' => true,
                'region' => null,
                'proclamation_reference' => 'Proclamation No. 727 (2025)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
