<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * TRAIN Law Income Tax Withholding Service.
 *
 * Implements the BIR cumulative withholding method (Revenue Regulations 2-2023).
 *
 * TAX-001: Annualize formula = period_taxable_income × 24 (semi-monthly).
 * TAX-002: Tax base = gross_pay − non-taxable items.
 *          Non-taxable: SSS EE + PhilHealth EE + Pag-IBIG EE contributions.
 *          (13th month exemption handled separately as a year-end adjustment.)
 * TAX-003: annual_tax = base_tax + (annualized − income_from) × excess_rate.
 * TAX-004: Universal bracket — TRAIN abolished personal exemptions.
 * TAX-005: Period withholding = de-annualized tax − YTD_withheld.
 * TAX-006: Income below ₱250,000/year → zero tax (bracket base_tax = 0, excess_rate = 0).
 * TAX-007: Minimum wage earners (daily_rate × 26 ≤ prevailing min wage × 26) → exempt.
 * TAX-008: Final December run reconciles any under/over + 13th month exclusion.
 * TAX-009: Cannot withhold more than the employee earned this period (floor = 0).
 *
 * All inputs/outputs are integer centavos.
 */
final class TaxWithholdingService implements ServiceContract
{
    /** Number of semi-monthly periods in a year. */
    private const PERIODS_PER_YEAR = 24;

    /**
     * Compute the withholding tax for the current period using the cumulative method.
     *
     * @param  int  $periodTaxableIncomeCentavos  Taxable earnings this period (gross − non-taxable deductions)
     * @param  int  $ytdTaxableIncomeCentavos  YTD taxable income BEFORE this period
     * @param  int  $ytdTaxWithheldCentavos  YTD tax withheld BEFORE this period
     * @param  bool  $isMinimumWageEarner  TAX-007 exemption flag
     * @return int Tax to withhold this period (≥ 0)
     */
    public function computePeriodWithholding(
        int $periodTaxableIncomeCentavos,
        int $ytdTaxableIncomeCentavos = 0,
        int $ytdTaxWithheldCentavos = 0,
        bool $isMinimumWageEarner = false,
    ): int {
        // TAX-007: MWE exemption
        if ($isMinimumWageEarner) {
            return 0;
        }

        if ($periodTaxableIncomeCentavos <= 0) {
            return 0;
        }

        // Cumulative YTD including this period
        $cumulativeTaxableIncomeCentavos = $ytdTaxableIncomeCentavos + $periodTaxableIncomeCentavos;

        // TAX-001: Annualize
        $annualizedCentavos = $this->annualize($cumulativeTaxableIncomeCentavos);

        // TAX-003: Compute annual tax on the annualized YTD income
        $annualTaxCentavos = $this->computeAnnualTax($annualizedCentavos);

        // TAX-005: De-annualize to get cumulative YTD tax liability
        $cumulativeTaxLiabilityCentavos = $this->deAnnualize($annualTaxCentavos);

        // This period = cumulative liability − what we already withheld
        $periodTax = $cumulativeTaxLiabilityCentavos - $ytdTaxWithheldCentavos;

        // TAX-009: Never negative
        return max(0, $periodTax);
    }

    /**
     * Compute the taxable income for the period.
     * Deducts mandatory government contributions from gross pay.
     *
     * @param  int  $nonTaxableAdjustmentsCentavos  Additional non-taxable allowances
     * @return int Taxable income (≥ 0)
     */
    public function computeTaxableIncome(
        int $grossPayCentavos,
        int $sssEeCentavos,
        int $philhealthEeCentavos,
        int $pagibigEeCentavos,
        int $nonTaxableAdjustmentsCentavos = 0,
    ): int {
        $taxable = $grossPayCentavos
            - $sssEeCentavos
            - $philhealthEeCentavos
            - $pagibigEeCentavos
            - $nonTaxableAdjustmentsCentavos;

        return max(0, $taxable);
    }

    /**
     * Determine if an employee qualifies as a minimum wage earner (TAX-007).
     *
     * @param  string  $region  e.g. 'NCR'
     * @param  string  $asOfDate  YYYY-MM-DD
     */
    public function isMinimumWageEarner(
        int $basicMonthlyCentavos,
        string $region = 'NCR',
        string $asOfDate = '',
    ): bool {
        if ($asOfDate === '') {
            $asOfDate = now()->toDateString();
        }

        $minDailyRate = DB::table('minimum_wage_rates')
            ->where('region', $region)
            ->where('effective_date', '<=', $asOfDate)
            ->orderByDesc('effective_date')
            ->value('daily_rate');

        if ($minDailyRate === null) {
            return false;
        }

        // Convert daily minimum wage to monthly (×26 working days)
        $minMonthlyPesos = (float) $minDailyRate * 26;
        $basicMonthlyPesos = $basicMonthlyCentavos / 100;

        return $basicMonthlyPesos <= $minMonthlyPesos;
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /** TAX-001: Annualize a semi-monthly income (×24). */
    private function annualize(int $semiMonthlyIncomeCentavos): int
    {
        return $semiMonthlyIncomeCentavos * self::PERIODS_PER_YEAR;
    }

    /** TAX-005: De-annualize (÷24). Integer-safe rounding. */
    private function deAnnualize(int $annualTaxCentavos): int
    {
        return (int) round($annualTaxCentavos / self::PERIODS_PER_YEAR, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * TAX-003: Compute annual income tax from the TRAIN brackets table.
     * Income is in centavos; bracket table stores values in pesos (float).
     */
    private function computeAnnualTax(int $annualIncomeCentavos): int
    {
        $annualIncomePesos = $annualIncomeCentavos / 100;

        $bracket = DB::table('train_tax_brackets')
            ->where('income_from', '<=', $annualIncomePesos)
            ->where(function ($q) use ($annualIncomePesos) {
                $q->where('income_to', '>=', $annualIncomePesos)
                    ->orWhereNull('income_to'); // top bracket has no ceiling
            })
            ->orderByDesc('income_from')
            ->first();

        if ($bracket === null) {
            return 0;
        }

        $baseTax = (float) $bracket->base_tax;
        $excessRate = (float) $bracket->excess_rate;
        $incomeFrom = (float) $bracket->income_from;

        $annualTaxPesos = $baseTax + (($annualIncomePesos - $incomeFrom) * $excessRate);

        return (int) round(max(0, $annualTaxPesos) * 100, 0, PHP_ROUND_HALF_UP);
    }
}
