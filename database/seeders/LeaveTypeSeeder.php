<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Standard leave types matching the physical Leave of Absence Request Form (AD-084-00).
 * Types: Vacation, Maternity, Birthday, Bereavement, Paternity, Others.
 * References: RA 11210 (Maternity), Labor Code (Paternity/Vacation).
 */
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'VL',
                'name' => 'Vacation Leave',
                'category' => 'vacation',
                'is_paid' => false,
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
                'code' => 'ML',
                'name' => 'Maternity Leave',
                'category' => 'maternity',
                'is_paid' => false,
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
                'code' => 'BDAY',
                'name' => 'Birthday Leave',
                'category' => 'other',
                'is_paid' => false,
                'max_days_per_year' => 1,
                'requires_approval' => true,
                'requires_documentation' => false,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
            [
                'code' => 'BL',
                'name' => 'Bereavement Leave',
                'category' => 'bereavement',
                'is_paid' => false,
                'max_days_per_year' => 3,
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
                'is_paid' => false,
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
                'code' => 'OTH',
                'name' => 'Others',
                'category' => 'other',
                'is_paid' => false,
                'max_days_per_year' => 0,   // discretionary — no fixed entitlement
                'requires_approval' => true,
                'requires_documentation' => false,
                'monthly_accrual_days' => null,
                'max_carry_over_days' => 0,
                'can_be_monetized' => false,
                'deducts_absent_on_lwop' => false,
                'is_active' => true,
            ],
        ];

        $now = now();
        foreach ($types as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::table('leave_types')->upsert(
            $types,
            ['code'],
            [
                'name',
                'category',
                'is_paid',
                'max_days_per_year',
                'requires_approval',
                'requires_documentation',
                'monthly_accrual_days',
                'max_carry_over_days',
                'can_be_monetized',
                'deducts_absent_on_lwop',
                'is_active',
                'updated_at',
            ],
        );
    }
}
