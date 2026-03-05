<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Employee code in the format EMP-{YYYY}-{NNNN}.
 *
 * Rule EMP-001: auto-generated on activation by EmployeeService.
 * Never accepts user input for the code format — issued by the system only.
 */
final readonly class EmployeeCode
{
    private const PATTERN = '/^EMP-\d{4}-\d{4}$/';

    public function __construct(public readonly string $value)
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "EmployeeCode: '{$value}' does not match the required format EMP-YYYY-NNNN."
            );
        }
    }

    // ─── Factory ─────────────────────────────────────────────────────────────

    /**
     * Generate a new code from year and sequential number.
     *
     * @param  int  $year  4-digit year (e.g. 2026)
     * @param  int  $sequence  1-based sequence number for the year (padded to 4 digits)
     */
    public static function generate(int $year, int $sequence): self
    {
        if ($year < 2000 || $year > 9999) {
            throw new InvalidArgumentException("EmployeeCode: year must be 2000–9999, got {$year}.");
        }

        if ($sequence < 1 || $sequence > 9999) {
            throw new InvalidArgumentException("EmployeeCode: sequence must be 1–9999, got {$sequence}.");
        }

        return new self(sprintf('EMP-%04d-%04d', $year, $sequence));
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(EmployeeCode $other): bool
    {
        return $this->value === $other->value;
    }
}
