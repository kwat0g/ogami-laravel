<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\AP\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * VendorFactory — provides sensible test defaults for AP vendors.
 *
 * @extends Factory<Vendor>
 */
final class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'name' => 'Vendor '.$this->faker->company().' '.$seq,
            'tin' => sprintf('%03d-%03d-%03d-%03d',
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(100, 999)
            ),
            'is_ewt_subject' => false,
            'is_active' => true,
            'accreditation_status' => 'accredited',
            'address' => $this->faker->address(),
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'payment_terms' => 'Net 30',
            'created_by' => 1, // System user or override in tests
        ];
    }

    /**
     * Mark vendor as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Mark vendor as EWT subject.
     */
    public function ewtSubject(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ewt_subject' => true,
            'atc_code' => 'WI010',
        ]);
    }

    /**
     * Set vendor as pending accreditation.
     */
    public function pendingAccreditation(): static
    {
        return $this->state(fn (array $attributes) => [
            'accreditation_status' => 'pending',
        ]);
    }
}
