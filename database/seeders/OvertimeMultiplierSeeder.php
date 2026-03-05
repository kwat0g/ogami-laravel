<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OT multiplier configs — all 11 DOLE scenarios, effective January 1, 2024.
 *
 * Source: Labor Code of the Philippines (Presidential Decree 442 as amended),
 * Republic Act 9492 (holiday law).
 *
 * EARN-004: Payroll engine ALWAYS reads from this table — never hardcodes multipliers.
 */
class OvertimeMultiplierSeeder extends Seeder
{
    public function run(): void
    {
        $scenarios = [
            [
                'scenario' => 'REGULAR_DAY_OT',
                'multiplier' => 1.25,
                'description' => 'Overtime on a regular working day',
                'dole_legal_basis' => 'Labor Code Art. 87',
            ],
            [
                'scenario' => 'REST_DAY_WORK',
                'multiplier' => 1.30,
                'description' => 'Work performed on a scheduled rest day',
                'dole_legal_basis' => 'Labor Code Art. 93(a)',
            ],
            [
                'scenario' => 'REST_DAY_OT',
                'multiplier' => 1.69,
                'description' => 'Overtime on a rest day (1.30 × 1.30 computed rate)',
                'dole_legal_basis' => 'Labor Code Art. 93(c)',
            ],
            [
                'scenario' => 'SPECIAL_HOLIDAY_WORK',
                'multiplier' => 1.30,
                'description' => 'Work on a special non-working holiday',
                'dole_legal_basis' => 'Labor Code Art. 94 / RA 9492',
            ],
            [
                'scenario' => 'SPECIAL_HOLIDAY_OT',
                'multiplier' => 1.69,
                'description' => 'Overtime on a special non-working holiday',
                'dole_legal_basis' => 'Labor Code Art. 94 / RA 9492',
            ],
            [
                'scenario' => 'SPECIAL_HOLIDAY_REST',
                'multiplier' => 1.50,
                'description' => 'Work on a special holiday that falls on a rest day',
                'dole_legal_basis' => 'DOLE Department Order / RA 9492',
            ],
            [
                'scenario' => 'SPECIAL_HOLIDAY_REST_OT',
                'multiplier' => 1.95,
                'description' => 'Overtime on a special holiday that falls on a rest day',
                'dole_legal_basis' => 'DOLE Department Order / RA 9492',
            ],
            [
                'scenario' => 'REGULAR_HOLIDAY_WORK',
                'multiplier' => 2.00,
                'description' => 'Work on a regular holiday',
                'dole_legal_basis' => 'Labor Code Art. 94',
            ],
            [
                'scenario' => 'REGULAR_HOLIDAY_OT',
                'multiplier' => 2.60,
                'description' => 'Overtime on a regular holiday',
                'dole_legal_basis' => 'Labor Code Art. 94 + Art. 87',
            ],
            [
                'scenario' => 'REST_DAY_REGULAR_HOLIDAY',
                'multiplier' => 2.60,
                'description' => 'Work on a regular holiday that falls on a scheduled rest day (EDGE-006)',
                'dole_legal_basis' => 'Labor Code Art. 94 + DOLE Omnibus Rules',
            ],
            [
                'scenario' => 'REST_DAY_REGULAR_HOLIDAY_OT',
                'multiplier' => 3.38,
                'description' => 'Overtime on a regular holiday that falls on a rest day',
                'dole_legal_basis' => 'Labor Code Art. 94 + Art. 87 + DOLE Omnibus Rules',
            ],
        ];

        foreach ($scenarios as $row) {
            DB::table('overtime_multiplier_configs')->insertOrIgnore([
                'effective_date' => '2024-01-01',
                'scenario' => $row['scenario'],
                'multiplier' => $row['multiplier'],
                'dole_legal_basis' => $row['dole_legal_basis'],
                'description' => $row['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
