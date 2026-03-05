<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        $creator = User::firstOrCreate(
            ['email' => 'factory-hr@ogami.test'],
            ['name' => 'Factory HR', 'password' => bcrypt('secret')],
        );

        $cutoffStart = $this->faker->dateTimeBetween('-3 months', '-1 month');
        $cutoffEnd = (clone $cutoffStart)->modify('+14 days');
        $payDate = (clone $cutoffEnd)->modify('+5 days');
        $year = $cutoffStart->format('Y');
        $seq = str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'reference_no' => "PR-{$year}-{$seq}",
            'pay_period_label' => $cutoffStart->format('M Y').' 1st',
            'cutoff_start' => $cutoffStart->format('Y-m-d'),
            'cutoff_end' => $cutoffEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
            'status' => 'draft',
            'run_type' => 'regular',
            'total_employees' => 0,
            'gross_pay_total_centavos' => 0,
            'total_deductions_centavos' => 0,
            'net_pay_total_centavos' => 0,
            'created_by' => $creator->id,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function locked(): static
    {
        return $this->state(['status' => 'locked']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }
}
