<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Represents an OT multiplier loaded from overtime_multiplier_configs.
 *
 * Invariant: multiplier >= 1.0 (zero-hour workers still get their base rate).
 * Loaded from DB — never hardcoded. The 9 DOLE scenarios are seeded in
 * OvertimeMultiplierSeeder with effective_date versioning.
 */
final readonly class OvertimeMultiplier
{
    private function __construct(
        public readonly float $value,
        public readonly string $scenario,
    ) {
        if ($value < 1.0) {
            throw new InvalidArgumentException(
                "OvertimeMultiplier: value must be >= 1.0 for scenario '{$scenario}', got {$value}."
            );
        }
    }

    public static function of(float $value, string $scenario): self
    {
        return new self($value, $scenario);
    }

    /**
     * Apply this multiplier to an hourly rate and duration to produce the OT pay.
     *
     * Formula (EARN-004):
     *   ot_pay = (minutes / 60) × hourly_rate × multiplier
     *
     * The -1 in the formula is because the base rate (1.0×) is already included
     * in the multiplier definition. For a 1.25× multiplier:
     *   extra pay = hours × rate × (multiplier - 1)
     *   total     = hours × rate × multiplier
     *
     * This method returns the total OT pay (base + premium), matching
     * the DOLE computation method.
     */
    public function applyTo(Money $hourlyRate, Minutes $duration): Money
    {
        // Convert minutes to fractional hours, multiply by rate and multiplier
        return $hourlyRate->multiply($duration->toHours() * $this->value);
    }
}
