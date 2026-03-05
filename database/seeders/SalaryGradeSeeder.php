<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Philippine government-inspired salary grade tiers.
 * All monetary values stored as centavos (1 peso = 100 centavos).
 * Ranges reflect realistic Philippine private-sector monthly pay as of 2025.
 */
class SalaryGradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            // code, name, level, employment_type, min_monthly PHP, max_monthly PHP
            ['SG-01', 'Entry Level I',      1,  'regular', 18_000, 22_000],
            ['SG-02', 'Entry Level II',     2,  'regular', 22_001, 27_000],
            ['SG-03', 'Junior I',           3,  'regular', 27_001, 33_000],
            ['SG-04', 'Junior II',          4,  'regular', 33_001, 40_000],
            ['SG-05', 'Mid-Level I',        5,  'regular', 40_001, 50_000],
            ['SG-06', 'Mid-Level II',       6,  'regular', 50_001, 63_000],
            ['SG-07', 'Senior I',           7,  'regular', 63_001, 80_000],
            ['SG-08', 'Senior II',          8,  'regular', 80_001, 100_000],
            ['SG-09', 'Lead / Specialist',  9,  'regular', 100_001, 130_000],
            ['SG-10', 'Manager I',          10, 'regular', 130_001, 160_000],
            ['SG-11', 'Manager II',         11, 'regular', 160_001, 200_000],
            ['SG-12', 'Senior Manager',     12, 'regular', 200_001, 250_000],
            ['SG-13', 'Director I',         13, 'regular', 250_001, 310_000],
            ['SG-14', 'Director II',        14, 'regular', 310_001, 380_000],
            ['SG-15', 'Vice President',     15, 'regular', 380_001, 500_000],

            // Contractual / probationary tiers (levels 1-5 mirrored)
            ['CT-01', 'Contractual I',      1,  'contractual', 15_000, 19_000],
            ['CT-02', 'Contractual II',     2,  'contractual', 19_001, 24_000],
            ['CT-03', 'Contractual III',    3,  'contractual', 24_001, 30_000],

            // Project-based
            ['PB-01', 'Project-Based I',    1,  'project_based', 15_000, 22_000],
            ['PB-02', 'Project-Based II',   2,  'project_based', 22_001, 35_000],
        ];

        $now = now();
        $rows = [];

        foreach ($grades as [$code, $name, $level, $type, $minPhp, $maxPhp]) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'level' => $level,
                'employment_type' => $type,
                'min_monthly_rate' => $minPhp * 100,   // to centavos
                'max_monthly_rate' => $maxPhp * 100,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('salary_grades')->insertOrIgnore($rows);
    }
}
