<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deactivate legacy government loan types and upsert the two company loan types
 * (Company Loan and Cash Advance) at 0% interest.
 *
 * Government loan rows are deactivated rather than deleted to preserve FK
 * references from any existing loan records.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Deactivate all legacy government and emergency loan types
        DB::table('loan_types')
            ->whereIn('code', [
                'SSS_SALARY',
                'SSS_CALAMITY',
                'PAGIBIG_MPL',
                'PAGIBIG_CALAMITY',
                'COMPANY_EMERGENCY',
                'COMPANY_SALARY',
            ])
            ->update(['is_active' => false, 'updated_at' => now()]);

        $now = now();

        // Upsert the two authorised company loan types
        DB::table('loan_types')->upsert(
            [
                [
                    'code' => 'COMPANY_LOAN',
                    'name' => 'Company Loan',
                    'category' => 'company',
                    'description' => 'Company employee loan at 0% interest. Deducted over the agreed term.',
                    'interest_rate_annual' => 0.00,
                    'max_term_months' => 12,
                    'max_amount_centavos' => 15_000_000,   // ₱150,000
                    'min_amount_centavos' => 50_000,       // ₱500
                    'subject_to_min_wage_protection' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'CASH_ADVANCE',
                    'name' => 'Cash Advance',
                    'category' => 'company',
                    'description' => 'Short-term cash advance at 0% interest. Deducted over the agreed term.',
                    'interest_rate_annual' => 0.00,
                    'max_term_months' => 3,
                    'max_amount_centavos' => 5_000_000,    // ₱50,000
                    'min_amount_centavos' => 50_000,       // ₱500
                    'subject_to_min_wage_protection' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['code'],
            ['name', 'category', 'description', 'interest_rate_annual', 'max_term_months',
                'max_amount_centavos', 'min_amount_centavos', 'subject_to_min_wage_protection',
                'is_active', 'updated_at'],
        );
    }

    public function down(): void
    {
        // Re-activate legacy types (best-effort rollback)
        DB::table('loan_types')
            ->whereIn('code', [
                'SSS_SALARY',
                'SSS_CALAMITY',
                'PAGIBIG_MPL',
                'PAGIBIG_CALAMITY',
                'COMPANY_EMERGENCY',
                'COMPANY_SALARY',
            ])
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
