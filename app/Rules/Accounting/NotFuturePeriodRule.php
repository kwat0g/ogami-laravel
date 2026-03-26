<?php

declare(strict_types=1);

namespace App\Rules\Accounting;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * JE-005: Journal entries cannot be posted to a future fiscal period unless
 * system_settings allow_future_period_posting = true.
 */
final class NotFuturePeriodRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $date = Carbon::parse($value)->startOfDay();
        $today = now()->startOfDay();

        if ($date->lte($today)) {
            return; // date is today or past — always allowed
        }

        // Date is in the future — check the system setting toggle
        $setting = DB::table('system_settings')
            ->where('key', 'accounting.allow_future_period_posting')
            ->value('value');

        $allowed = $setting !== null ? json_decode((string) $setting, true) : false;

        if (! $allowed) {
            $fail("Journal entries cannot be posted to a future date ({$value}). Enable 'allow_future_period_posting' in system settings to allow this. (JE-005)");
        }
    }
}
