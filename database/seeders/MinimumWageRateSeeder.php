<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Minimum wage rate for NCR — current rate per Wage Order NCR-25.
 *
 * The system looks up the prevailing rate on any given date by region:
 *   MinimumWageRate::effectiveOn($date)->forRegion($region)->daily_rate
 *
 * Add additional regions as needed. Each new wage order becomes a new row.
 * EMP-012 enforces basic_salary >= minimum_wage at employee creation/update.
 */
class MinimumWageRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            [
                'effective_date' => '2023-07-17',
                'region' => 'NCR',
                'daily_rate' => 610.00,
                'wage_order_reference' => 'NCR-24',
            ],
            [
                'effective_date' => '2024-07-05',
                'region' => 'NCR',
                'daily_rate' => 645.00,
                'wage_order_reference' => 'NCR-25',
            ],
        ];

        foreach ($rates as $rate) {
            DB::table('minimum_wage_rates')->insertOrIgnore(array_merge($rate, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
