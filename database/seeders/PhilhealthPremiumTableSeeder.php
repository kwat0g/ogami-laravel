<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PhilHealth premium table — current rate effective January 1, 2024.
 *
 * Source: PhilHealth Circular 2023-0009
 * Premium rate: 5% of basic salary
 * Min monthly premium: ₱500 (for basic salary ≤ ₱10,000)
 * Max monthly premium: ₱5,000 (for basic salary ≥ ₱100,000)
 *
 * PHL-002: Base = basic_salary ONLY (not gross pay, not allowances).
 * PHL-003: Employee share = total premium / 2. Employer = total premium / 2.
 * PHL-004: Semi-monthly deduction = employee_premium / 2.
 */
class PhilhealthPremiumTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('philhealth_premium_tables')->insertOrIgnore([
            'effective_date' => '2024-01-01',
            'salary_floor' => null,      // Applies to all salary levels
            'salary_ceiling' => null,      // min/max enforced via min_monthly_premium and max columns
            'premium_rate' => 0.05,      // 5% total premium (employee 2.5% + employer 2.5%)
            'min_monthly_premium' => 500.00,    // Minimum total monthly premium
            'max_monthly_premium' => 5000.00,   // Maximum total monthly premium
            'legal_basis' => 'PhilHealth Circular 2023-0009, effective January 1, 2024',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
