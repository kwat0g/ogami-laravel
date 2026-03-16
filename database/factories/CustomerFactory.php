<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\AR\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * CustomerFactory — provides sensible test defaults for AR customers.
 *
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'name' => 'Customer '.$this->faker->company().' '.$seq,
            'tin' => sprintf('%03d-%03d-%03d-%03d',
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(100, 999)
            ),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'contact_person' => $this->faker->name(),
            'address' => $this->faker->address(),
            'billing_address' => $this->faker->address(),
            'credit_limit' => 100000.00, // ₱100,000 default credit limit
            'is_active' => true,
            'notes' => null,
            'created_by' => 1, // System user or override in tests
            'ar_account_id' => null,
        ];
    }

    /**
     * Mark customer as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set unlimited credit (credit_limit = 0).
     */
    public function unlimitedCredit(): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_limit' => 0.00,
        ]);
    }

    /**
     * Set a specific credit limit.
     */
    public function withCreditLimit(float $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_limit' => $limit,
        ]);
    }
}
