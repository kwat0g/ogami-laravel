<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Represents a count of working days in a period, accounting for
 * Philippine holidays and the employee's shift schedule rest days.
 *
 * Used throughout the payroll engine (EARN-002, EARN-003, LN-007).
 * Never store raw integers for "days worked" — wrap them in this value object
 * so the derivation context is explicit.
 */
final readonly class WorkingDays
{
    private function __construct(public readonly int $count)
    {
        if ($count < 0) {
            throw new InvalidArgumentException(
                "WorkingDays: count cannot be negative, got {$count}."
            );
        }
    }

    // ─── Factory ─────────────────────────────────────────────────────────────

    public static function of(int $count): self
    {
        return new self($count);
    }

    /**
     * Compute working days for a DateRange, excluding holiday dates and
     * the employee's configured rest day numbers (1=Mon … 7=Sun, PHP ISO).
     *
     * @param  CarbonImmutable[]  $holidays  Holiday dates to exclude.
     * @param  int[]  $restDays  ISO day-of-week numbers (1=Mon, 7=Sun) to exclude.
     */
    public static function compute(
        DateRange $range,
        array $holidays = [],
        array $restDays = [],
    ): self {
        $holidayStrings = array_map(
            fn (CarbonImmutable $d): string => $d->toDateString(),
            $holidays,
        );

        $count = 0;
        $current = $range->from->startOfDay();
        $end = $range->to->startOfDay();

        while ($current->lte($end)) {
            $isRestDay = in_array($current->dayOfWeekIso, $restDays, true);
            $isHoliday = in_array($current->toDateString(), $holidayStrings, true);

            if (! $isRestDay && ! $isHoliday) {
                $count++;
            }

            $current = $current->addDay();
        }

        return new self($count);
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function isZero(): bool
    {
        return $this->count === 0;
    }

    public function add(WorkingDays $other): self
    {
        return new self($this->count + $other->count);
    }
}
