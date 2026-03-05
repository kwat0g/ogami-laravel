<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Represents a date range with inclusive start and end dates.
 *
 * Invariant: $to >= $from is enforced at construction time.
 * Using CarbonImmutable prevents accidental mutation of the stored dates.
 */
final readonly class DateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {
        if ($to->lt($from)) {
            throw new InvalidArgumentException(
                "DateRange: 'to' ({$to->toDateString()}) must be on or after 'from' ({$from->toDateString()})."
            );
        }
    }

    // ─── Factory ─────────────────────────────────────────────────────────────

    public static function fromStrings(string $from, string $to): self
    {
        return new self(
            CarbonImmutable::parse($from)->startOfDay(),
            CarbonImmutable::parse($to)->endOfDay(),
        );
    }

    public static function singleDay(CarbonImmutable $date): self
    {
        return new self($date->startOfDay(), $date->endOfDay());
    }

    // ─── Queries ─────────────────────────────────────────────────────────────

    /**
     * Number of calendar days (inclusive of both endpoints).
     */
    public function daysCount(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->between($this->from, $this->to);
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->from->lte($other->to) && $this->to->gte($other->from);
    }

    /**
     * Returns a new DateRange representing the intersection of two ranges,
     * or null if they do not overlap.
     */
    public function intersect(DateRange $other): ?self
    {
        if (! $this->overlaps($other)) {
            return null;
        }

        $start = $this->from->gt($other->from) ? $this->from : $other->from;
        $end = $this->to->lt($other->to) ? $this->to : $other->to;

        return new self($start, $end);
    }

    public function equals(DateRange $other): bool
    {
        return $this->from->eq($other->from) && $this->to->eq($other->to);
    }
}
