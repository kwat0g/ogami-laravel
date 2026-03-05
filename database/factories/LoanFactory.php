<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $user = User::firstOrCreate(
            ['email' => 'system-test@ogami.test'],
            ['name' => 'System Test', 'password' => bcrypt('secret')]
        );

        $loanType = LoanType::firstOrCreate(
            ['code' => 'SSS-GOV'],
            [
                'name' => 'SSS Salary Loan',
                'category' => 'government',
                'max_term_months' => 24,
            ]
        );

        return [
            'reference_no' => 'LN-'.date('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'loan_type_id' => $loanType->id,
            'principal_centavos' => 1_000_000,   // ₱10,000
            'term_months' => 12,
            'loan_date' => now()->toDateString(),
            'requested_by' => $user->id,
            'status' => 'active',
            'outstanding_balance_centavos' => 1_000_000,
        ];
    }
}
