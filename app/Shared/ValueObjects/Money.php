<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use App\Shared\Exceptions\ValidationException;
use InvalidArgumentException;
use Stringable;

/**
 * Represents a monetary amount in the smallest unit (centavos).
 *
 * Rule: Never use float for currency — IEEE 754 floating-point cannot represent
 * decimal fractions exactly. All computations use integer centavos internally.
 * Division uses PHP's bcmath or explicit rounding with ROUND_HALF_UP.
 *
 * Example:
 *   $salary = Money::fromFloat(15000.00);   // stores 1_500_000 centavos
 *   $salary->toFloat()                       // 15000.0
 *   $salary->format()                        // "₱15,000.00"
 */
final readonly class Money implements Stringable
{
    /** Amount in centavos (integer to avoid float precision loss). */
    public readonly int $centavos;

    private function __construct(int $centavos, public readonly string $currency = 'PHP')
    {
        $this->centavos = $centavos;
    }

    // ─── Factory Methods ─────────────────────────────────────────────────────

    public static function fromFloat(float $amount, string $currency = 'PHP'): self
    {
        // Round to 2 decimal places then convert to centavos
        $rounded = (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);

        return new self($rounded, $currency);
    }

    public static function fromCentavos(int $centavos, string $currency = 'PHP'): self
    {
        if ($centavos < 0) {
            throw new ValidationException('Money: centavos cannot be negative.', 'MONEY_NEGATIVE');
        }

        return new self($centavos, $currency);
    }

    public static function zero(string $currency = 'PHP'): self
    {
        return new self(0, $currency);
    }

    // ─── Arithmetic ──────────────────────────────────────────────────────────

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->centavos + $other->centavos, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        $result = $this->centavos - $other->centavos;
        if ($result < 0) {
            throw new ValidationException('Money: subtraction result cannot be negative.', 'MONEY_NEGATIVE');
        }

        return new self($result, $this->currency);
    }

    /**
     * Multiply by a scalar (e.g. OT multiplier, rate factor).
     * Uses ROUND_HALF_UP — required for payroll compliance (DED-003).
     */
    public function multiply(float $factor): self
    {
        $result = (int) round($this->centavos * $factor, 0, PHP_ROUND_HALF_UP);

        return new self($result, $this->currency);
    }

    /**
     * Divide by a scalar (e.g. splitting into periods).
     * Uses ROUND_HALF_UP.
     */
    public function divide(float $divisor): self
    {
        if ($divisor == 0.0) {
            throw new InvalidArgumentException('Money: cannot divide by zero.');
        }

        $result = (int) round($this->centavos / $divisor, 0, PHP_ROUND_HALF_UP);

        return new self($result, $this->currency);
    }

    // ─── Comparisons ─────────────────────────────────────────────────────────

    public function isNegative(): bool
    {
        return $this->centavos < 0;
    }

    public function isZero(): bool
    {
        return $this->centavos === 0;
    }

    public function isPositive(): bool
    {
        return $this->centavos > 0;
    }

    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->centavos > $other->centavos;
    }

    public function greaterThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->centavos >= $other->centavos;
    }

    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->centavos < $other->centavos;
    }

    public function lessThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->centavos <= $other->centavos;
    }

    public function equals(Money $other): bool
    {
        return $this->centavos === $other->centavos && $this->currency === $other->currency;
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function toCentavos(): int
    {
        return $this->centavos;
    }

    public function toFloat(): float
    {
        return round($this->centavos / 100, 2);
    }

    /**
     * Format as Philippine Peso string for display.
     * Uses JetBrains Mono font in the frontend — this is just the string value.
     */
    public function format(): string
    {
        return '₱'.number_format($this->toFloat(), 2);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: cannot operate on {$this->currency} and {$other->currency}."
            );
        }
    }
}
