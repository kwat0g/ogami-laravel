<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pag-IBIG (HDMF) contribution table — current rate.
 *
 * Source: HDMF Circular 274 (RA 9679)
 * PAGIBIG-002: 1% if monthly basic ≤ ₱1,500; 2% if above.
 * PAGIBIG-003: Employee cap = ₱100/month (₱50 per semi-monthly period).
 * PAGIBIG-004: Employer rate = always 2%, no cap.
 */
class PagibigContributionTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('pagibig_contribution_tables')->insertOrIgnore([
            'effective_date' => '2024-01-01',
            'salary_threshold' => 1500.00,
            'employee_rate_below' => 0.01,     // 1% for salary ≤ ₱1,500
            'employee_rate_above' => 0.02,     // 2% for salary > ₱1,500
            'employee_cap_monthly' => 100.00,  // ₱100/month maximum; ₱50 per semi-monthly
            'employer_rate' => 0.02,     // Always 2%, no cap
            'legal_basis' => 'HDMF Circular 274 per Republic Act 9679',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
