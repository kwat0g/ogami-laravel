<?php

declare(strict_types=1);

namespace App\Domains\HR\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * MinimumAgeAtHireRule — enforces the DOLE minimum working age of 15 years.
 *
 * The employee must be at least 15 years old on the date of hire.
 *
 * Usage (in a FormRequest):
 *   'date_hired' => [new MinimumAgeAtHireRule($request->input('date_of_birth'))]
 */
final class MinimumAgeAtHireRule implements Rule
{
    private const DOLE_MINIMUM_AGE = 15;

    public function __construct(
        private readonly string $dateOfBirth,
    ) {}

    public function passes(mixed $attribute, mixed $value): bool
    {
        try {
            $birth = new \DateTimeImmutable($this->dateOfBirth);
            $hire = new \DateTimeImmutable((string) $value);
            $ageAtHire = (int) $birth->diff($hire)->y;

            return $ageAtHire >= self::DOLE_MINIMUM_AGE;
        } catch (\Throwable) {
            // If dates are invalid, let other validators catch them
            return true;
        }
    }

    public function message(): string
    {
        return 'The employee must be at least '.self::DOLE_MINIMUM_AGE.' years old at the date of hire (DOLE RA 7610).';
    }
}
