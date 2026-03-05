<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Company loan products — Company Loan and Cash Advance, both at 0% interest.
 * Uses upsert on `code` so the seeder is idempotent.
 */
class LoanTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $types = [
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
        ];

        DB::table('loan_types')->upsert(
            $types,
            ['code'],
            ['name', 'category', 'description', 'interest_rate_annual', 'max_term_months',
                'max_amount_centavos', 'min_amount_centavos', 'subject_to_min_wage_protection',
                'is_active', 'updated_at'],
        );
    }
}
