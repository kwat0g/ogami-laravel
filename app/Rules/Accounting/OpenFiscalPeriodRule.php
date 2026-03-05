<?php

declare(strict_types=1);

namespace App\Rules\Accounting;

use App\Domains\Accounting\Models\FiscalPeriod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * JE-004: Journal entries cannot be posted to a closed fiscal period.
 * Validates that the given date falls within an OPEN fiscal period.
 */
final class OpenFiscalPeriodRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // let `required` rule handle missing dates
        }

        $period = FiscalPeriod::whereDate('date_from', '<=', $value)
            ->whereDate('date_to', '>=', $value)
            ->first();

        if ($period === null) {
            $fail("No fiscal period exists for the date {$value}. Create a fiscal period before posting. (JE-004)");

            return;
        }

        if ($period->status !== 'open') {
            $fail("The fiscal period covering {$value} ({$period->name}) is closed. Reopen the period to post entries. (JE-004)");
        }
    }
}
