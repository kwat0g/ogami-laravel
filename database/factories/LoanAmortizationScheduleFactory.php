<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Loan\Models\LoanAmortizationSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanAmortizationSchedule>
 */
class LoanAmortizationScheduleFactory extends Factory
{
    protected $model = LoanAmortizationSchedule::class;

    public function definition(): array
    {
        return [
            // loan_id must be supplied via create([...]) call in the test
            'installment_no' => 1,
            'due_date' => now()->toDateString(),
            'principal_portion_centavos' => 100_000,  // ₱1,000
            'interest_portion_centavos' => 0,
            'total_due_centavos' => 100_000,  // must equal principal + interest (CHECK constraint)
            'status' => 'pending',
        ];
    }
}
