<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Represents a duration in whole minutes.
 *
 * Used for OT minutes, night-differential minutes, and tardiness/undertime
 * durations throughout the payroll engine. EARN-006 requires that OT minutes
 * be stored and computed as integers; decimal conversion happens only at the
 * final multiplication step.
 */
final readonly class Minutes
{
    private function __construct(public readonly int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "Minutes: value cannot be negative, got {$value}."
            );
        }
    }

    public static function of(int $minutes): self
    {
        return new self($minutes);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    // ─── Arithmetic ──────────────────────────────────────────────────────────

    public function add(Minutes $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(Minutes $other): self
    {
        return new self(max(0, $this->value - $other->value));
    }

    // ─── Conversion ──────────────────────────────────────────────────────────

    /**
     * Convert to fractional hours for rate multiplication.
     * Example: 90 minutes → 1.5 hours.
     */
    public function toHours(): float
    {
        return $this->value / 60;
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }
}
