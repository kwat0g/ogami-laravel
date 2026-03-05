<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;

/**
 * TRAIN Law Civil Status → BIR Withholding Tax Status Code derivation.
 *
 * Maps an employee's civil_status + qualified_dependent_count to the BIR
 * alpha-numeric tax status code used on BIR Form 2316 and monthly remittances.
 *
 * BIR Tax Status Codes (TRAIN Law / RR 11-2018):
 *   S       — Single / Separted / Widow(er), no qualified dependents
 *   ME      — Married Employee (or Single parent head-of-family), no dependents
 *   HF      — Head of Family (single parent with dependents), 0 dependents
 *   ME1–ME4 — Married Employee with 1–4 qualified dependents
 *   HF1–HF4 — Head of Family with 1–4 qualified dependents
 *
 * @see https://www.bir.gov.ph/index.php/tax-information/withholding-tax.html
 */
final class TaxStatusDeriver implements ServiceContract
{
    /**
     * @param  string  $civilStatus  One of: single, married, widowed, separated,
     *                               legally_separated, head_of_family
     * @param  int  $dependentCount  Number of qualified dependents (0–4; capped at 4)
     * @return string BIR tax status code
     */
    public static function derive(string $civilStatus, int $dependentCount): string
    {
        // Cap at 4 — BIR only recognises up to ME4 / HF4
        $deps = min(max(0, $dependentCount), 4);

        return match (strtolower(trim($civilStatus))) {
            'head_of_family' => $deps === 0 ? 'HF' : "HF{$deps}",
            'married' => $deps === 0 ? 'ME' : "ME{$deps}",
            'single',
            'widowed',
            'widow',
            'separated',
            'legally_separated' => 'S',
            default => 'S',   // conservative fallback — lowest withholding bracket
        };
    }

    /**
     * Return whether the given tax status is exempt from withholding under
     * the TRAIN annual tax-free threshold (₱250,000 / year).
     *
     * This complements `TaxWithholdingService::isMinimumWageEarner()` — use
     * both checks before applying the withholding table.
     */
    public static function isExemptStatus(string $taxStatus): bool
    {
        // All status codes are subject to the ₱250k threshold — the STATUS
        // itself does not grant exemption; only total income level does.
        // Method retained as an explicit extension point for future BIR rulings.
        return false;
    }
}
