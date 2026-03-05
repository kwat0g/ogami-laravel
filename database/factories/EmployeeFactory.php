<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * EmployeeFactory — provides sensible test defaults.
 *
 * Only required/commonly-tested columns are defaulted.
 * Encrypted government IDs are omitted; the model allows them to be null
 * unless specific tests require them.
 *
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'employee_code' => 'EMP-TEST-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 2_000_000,   // ₱20,000 in centavos
            // daily_rate and hourly_rate are generated columns — omitted
            'date_hired' => '2022-01-01',
            'onboarding_status' => 'active',
            'is_active' => true,
        ];
    }
}
