<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Standard Philippine labor-law leave types.
 * References: Labor Code (SL/VL), RA 11210 (ML), RA 8972 (Solo Parent),
 *             RA 9710 (Gynecological), RA 11058 (VAWC), RA 11642 (DL).
 */
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'SL',
                'name' => 'Sick Leave',
                'category' => 'sick',
                'is_paid' => true,
                'max_days_per_year' => 5,
                'requires_approval' => true,
                'requires_documentation' => false,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'VL',
                'name' => 'Vacation Leave',
                'category' => 'vacation',
                'is_paid' => true,
                'max_days_per_year' => 5,
                'requires_approval' => true,
                'requires_documentation' => false,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 5,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'SIL',
                'name' => 'Service Incentive Leave',
                'category' => 'service_incentive',
                'is_paid' => true,
                'max_days_per_year' => 5,
                'requires_approval' => true,
                'requires_documentation' => false,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 5,
                'can_be_monetized' => true,   // LV-007 — convertible to cash
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'ML',
                'name' => 'Maternity Leave',
                'category' => 'maternity',
                'is_paid' => true,
                'max_days_per_year' => 105,    // RA 11210
                'requires_approval' => true,
                'requires_documentation' => true,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PL',
                'name' => 'Paternity Leave',
                'category' => 'paternity',
                'is_paid' => true,
                'max_days_per_year' => 7,
                'requires_approval' => true,
                'requires_documentation' => true,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'SPL',
                'name' => 'Solo Parent Leave',
                'category' => 'solo_parent',
                'is_paid' => true,
                'max_days_per_year' => 7,      // RA 8972
                'requires_approval' => true,
                'requires_documentation' => true,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'VAWCL',
                'name' => 'VAWC Leave',
                'category' => 'vawc',
                'is_paid' => true,
                'max_days_per_year' => 10,     // RA 9262
                'requires_approval' => true,
                'requires_documentation' => true,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'LWOP',
                'name' => 'Leave Without Pay',
                'category' => 'lwop',
                'is_paid' => false,
                'max_days_per_year' => 30,
                'requires_approval' => true,
                'requires_documentation' => false,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => true,   // LV-006
                'is_active' => true,
            ],
        ];

        $now = now();
        foreach ($types as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::table('leave_types')->insertOrIgnore($types);
    }
}
