<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds fiscal periods for the ERP demo environment.
 *
 * Periods created:
 *   Nov 2025  — closed  (historical)
 *   Dec 2025  — closed  (historical)
 *   Jan 2026  — closed  (payroll processing complete)
 *   Feb 2026  — open    (current active period)
 *   Mar 2026  — open    (upcoming)
 */
class FiscalPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $periods = [
            [
                'name' => 'Nov 2025',
                'date_from' => '2025-11-01',
                'date_to' => '2025-11-30',
                'status' => 'closed',
                'closed_at' => '2025-12-05 09:00:00',
            ],
            [
                'name' => 'Dec 2025',
                'date_from' => '2025-12-01',
                'date_to' => '2025-12-31',
                'status' => 'closed',
                'closed_at' => '2026-01-06 09:00:00',
            ],
            [
                'name' => 'Jan 2026',
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
                'status' => 'closed',
                'closed_at' => '2026-02-05 09:00:00',
            ],
            [
                'name' => 'Feb 2026',
                'date_from' => '2026-02-01',
                'date_to' => '2026-02-28',
                'status' => 'open',
                'closed_at' => null,
            ],
            [
                'name' => 'Mar 2026',
                'date_from' => '2026-03-01',
                'date_to' => '2026-03-31',
                'status' => 'open',
                'closed_at' => null,
            ],
        ];

        foreach ($periods as $period) {
            DB::table('fiscal_periods')->insertOrIgnore([
                'name' => $period['name'],
                'date_from' => $period['date_from'],
                'date_to' => $period['date_to'],
                'status' => $period['status'],
                'closed_at' => $period['closed_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ Fiscal periods seeded (Nov 2025 – Mar 2026).');
    }
}
