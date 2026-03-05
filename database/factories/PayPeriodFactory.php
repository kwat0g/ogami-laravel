<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Payroll\Models\PayPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayPeriod>
 */
class PayPeriodFactory extends Factory
{
    protected $model = PayPeriod::class;

    public function definition(): array
    {
        $cutoffStart = $this->faker->dateTimeBetween('-6 months', 'now');
        $cutoffEnd = (clone $cutoffStart)->modify('+14 days');
        $payDate = (clone $cutoffEnd)->modify('+5 days');

        return [
            'label' => $this->faker->unique()->words(3, true).' Period',
            'cutoff_start' => $cutoffStart->format('Y-m-d'),
            'cutoff_end' => $cutoffEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
            'frequency' => 'semi_monthly',
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed']);
    }
}
