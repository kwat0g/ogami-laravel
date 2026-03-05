<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Pipeline;

use App\Domains\Payroll\Services\PayrollComputationContext;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Step 08 — Night Differential Pay.
 *
 * DOLE Article 86 premium: paid to employees who work between 10 PM and 6 AM.
 * The premium rate is stored in `system_settings` as `payroll.night_diff_rate`
 * (a decimal fraction, e.g. 0.10 for 10 %).
 *
 * Formula: (night_diff_minutes / 60) × hourly_rate_centavos × night_diff_rate
 *
 * Falls back to 0.10 (10 %) if no system-settings row exists.
 */
final class Step08NightDiffStep
{
    public function __invoke(PayrollComputationContext $ctx, Closure $next): PayrollComputationContext
    {
        if ($ctx->nightDiffMinutes <= 0) {
            return $next($ctx);
        }

        $ndMultiplier = DB::table('system_settings')
            ->where('key', 'payroll.night_diff_rate')
            ->value('value');

        $rate = $ndMultiplier !== null ? (float) json_decode((string) $ndMultiplier) : 0.10;

        $ctx->nightDiffPayCentavos = (int) round(
            ($ctx->nightDiffMinutes / 60) * $ctx->hourlyRateCentavos * $rate,
            0,
            PHP_ROUND_HALF_UP,
        );

        return $next($ctx);
    }
}
