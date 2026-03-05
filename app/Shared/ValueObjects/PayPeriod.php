<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Represents a payroll period (semi-monthly: period 1 = 1st–15th, period 2 = 16th–EOM).
 *
 * The system uses 24 payroll cycles per year as configured in
 * system_settings.annual_payroll_periods_count. Each cycle is a PayPeriod.
 *
 * Invariant: periodNumber must be 1 or 2. The range must form a valid
 * DateRange (to >= from).
 */
final readonly class PayPeriod
{
    public readonly DateRange $range;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly int $periodNumber,  // 1 = first half, 2 = second half
    ) {
        if (! in_array($periodNumber, [1, 2], true)) {
            throw new InvalidArgumentException(
                "PayPeriod: periodNumber must be 1 or 2, got {$periodNumber}."
            );
        }

        $this->range = new DateRange($from, $to);
    }

    // ─── Factory ─────────────────────────────────────────────────────────────

    /**
     * Build the first-half period for a given month (1st–15th).
     */
    public static function firstHalf(int $year, int $month): self
    {
        return new self(
            CarbonImmutable::create($year, $month, 1)->startOfDay(),
            CarbonImmutable::create($year, $month, 15)->endOfDay(),
            1,
        );
    }

    /**
     * Build the second-half period for a given month (16th–last day).
     */
    public static function secondHalf(int $year, int $month): self
    {
        $lastDay = CarbonImmutable::create($year, $month, 1)->daysInMonth;

        return new self(
            CarbonImmutable::create($year, $month, 16)->startOfDay(),
            CarbonImmutable::create($year, $month, $lastDay)->endOfDay(),
            2,
        );
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function isDecemberSecondHalf(): bool
    {
        return $this->from->month === 12 && $this->periodNumber === 2;
    }

    /**
     * Human-readable label, e.g. "Feb 1–15, 2026 (Period 1)".
     */
    public function label(): string
    {
        return sprintf(
            '%s–%s, %d (Period %d)',
            $this->from->format('M j'),
            $this->to->format('j'),
            $this->from->year,
            $this->periodNumber,
        );
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $this->range->contains($date);
    }
}
