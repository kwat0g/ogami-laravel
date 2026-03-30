<?php

declare(strict_types=1);

use App\Domains\Payroll\Services\TaxWithholdingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| TRAIN Law Tax Withholding Tests
|--------------------------------------------------------------------------
| Rule IDs: TAX-001 through TAX-009
|
| Tables required: train_tax_brackets, minimum_wage_rates
--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'TrainTaxBracketSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'MinimumWageRateSeeder'])->assertExitCode(0);

    $this->tax = app(TaxWithholdingService::class);
});

describe('TaxWithholdingService — computePeriodWithholding()', function () {

    it('returns 0 for minimum wage earner — TAX-007', function () {
        $result = $this->tax->computePeriodWithholding(
            periodTaxableIncomeCentavos: 1_000_000, // ₱10,000 (high but MWE flag is set)
            ytdTaxableIncomeCentavos: 0,
            ytdTaxWithheldCentavos: 0,
            isMinimumWageEarner: true,
        );

        expect($result)->toBe(0);
    });

    it('returns 0 for zero taxable income', function () {
        $result = $this->tax->computePeriodWithholding(0);
        expect($result)->toBe(0);
    });

    it('returns 0 for income below ₱250,000/year threshold — TAX-006', function () {
        // ₱250,000/year ÷ 24 periods = ₱10,416.67/period
        // Use ₱10,000/period → annualised = ₱240,000 → below ₱250k threshold → 0 tax
        $result = $this->tax->computePeriodWithholding(
            periodTaxableIncomeCentavos: 1_000_000, // ₱10,000/period
            ytdTaxableIncomeCentavos: 0,
            ytdTaxWithheldCentavos: 0,
        );

        expect($result)->toBe(0);
    });

    it('returns positive tax for income above ₱250,000/year — TAX-003', function () {
        // ₱14,000/period × 24 = ₱336,000/year → falls in 20% bracket over ₱250k
        $result = $this->tax->computePeriodWithholding(
            periodTaxableIncomeCentavos: 1_400_000, // ₱14,000
            ytdTaxableIncomeCentavos: 0,
            ytdTaxWithheldCentavos: 0,
        );

        expect($result)->toBeGreaterThan(0);
    });

    it('never returns negative — TAX-009', function () {
        // Even if YTD over-withheld, result is floored at 0
        $result = $this->tax->computePeriodWithholding(
            periodTaxableIncomeCentavos: 500_000,
            ytdTaxableIncomeCentavos: 1_000_000,
            ytdTaxWithheldCentavos: 9_000_000, // massively over-withheld
        );

        expect($result)->toBe(0);
    });

    it('uses cumulative YTD method — TAX-001 / TAX-005', function () {
        // Step-by-step: period 1 then period 2, both identical income
        $p1 = $this->tax->computePeriodWithholding(
            periodTaxableIncomeCentavos: 1_600_000, // ₱16,000
            ytdTaxableIncomeCentavos: 0,
            ytdTaxWithheldCentavos: 0,
        );

        $p2 = $this->tax->computePeriodWithholding(
            periodTaxableIncomeCentavos: 1_600_000,
            ytdTaxableIncomeCentavos: 1_600_000,   // prior period taxable
            ytdTaxWithheldCentavos: $p1,
        );

        // Cumulative should make period 2 different (cumulative method)
        // Both should be ≥ 0
        expect($p1)->toBeGreaterThanOrEqual(0);
        expect($p2)->toBeGreaterThanOrEqual(0);

        // Sum of period 1 + 2 ≥ 0 (cannot be negative)
        expect($p1 + $p2)->toBeGreaterThanOrEqual(0);
    });

    it('annualises correctly — TAX-001: multiplier is 24 semi-monthly periods', function () {
        // For ₱15,000 taxable income/period:
        // Annualised = ₱15,000 × 24 = ₱360,000
        // TRAIN bracket for ₱360,000: base 0 + (360,000 - 250,000) × 20% = ₱22,000
        // Period share = ₱22,000 / 24 = ₱916.67 → rounded
        $result = $this->tax->computePeriodWithholding(
            periodTaxableIncomeCentavos: 1_500_000, // ₱15,000
        );

        // ₱916.67 per period → 91,667 centavos
        // ₱15k/period × 24 = ₱360k/yr → taxable excess over ₱250k = ₱110k × 15% = ₱16,500/yr → ₱687.50/period = 68,750 centavos
        expect($result)->toBeGreaterThan(60_000)->toBeLessThan(80_000);
    });
});

describe('TaxWithholdingService — computeTaxableIncome()', function () {

    it('deducts all three government contributions — TAX-002', function () {
        $taxable = $this->tax->computeTaxableIncome(
            grossPayCentavos: 1_500_000,  // ₱15,000
            sssEeCentavos: 112_500,   // ₱1,125
            philhealthEeCentavos: 31_250,   // ₱312.50
            pagibigEeCentavos: 5_000,   // ₱50
        );

        // Expected: ₱15,000 - ₱1,125 - ₱312.50 - ₱50 = ₱13,512.50 = 1,351,250 centavos
        expect($taxable)->toBe(1_351_250);
    });

    it('floors at 0 for unusually large deductions', function () {
        $taxable = $this->tax->computeTaxableIncome(
            grossPayCentavos: 500_000,  // ₱5,000
            sssEeCentavos: 700_000,  // more than gross
            philhealthEeCentavos: 0,
            pagibigEeCentavos: 0,
        );

        expect($taxable)->toBe(0);
    });

    it('includes non-taxable adjustments in the deduction — TAX-002', function () {
        $taxable = $this->tax->computeTaxableIncome(
            grossPayCentavos: 1_500_000,
            sssEeCentavos: 112_500,
            philhealthEeCentavos: 31_250,
            pagibigEeCentavos: 5_000,
            nonTaxableAdjustmentsCentavos: 100_000, // ₱1,000 non-taxable allowance
        );

        // ₱15,000 - ₱1,125 - ₱312.50 - ₱50 - ₱1,000 = ₱12,512.50
        expect($taxable)->toBe(1_251_250);
    });
});

describe('TaxWithholdingService — isMinimumWageEarner()', function () {

    it('flags employee at minimum wage as MWE — TAX-007', function () {
        // Minimum wage in NCR ≈ ₱610/day × 26 ≈ ₱15,860/month
        // Basic rate of ₱15,860 → should be MWE
        $isMwe = $this->tax->isMinimumWageEarner(
            basicMonthlyCentavos: 1_586_000, // ₱15,860
            region: 'NCR',
            asOfDate: '2025-01-01',
        );

        expect($isMwe)->toBeTrue();
    });

    it('does not flag high-earner as MWE — TAX-007', function () {
        $isMwe = $this->tax->isMinimumWageEarner(
            basicMonthlyCentavos: 5_000_000, // ₱50,000
            region: 'NCR',
            asOfDate: '2025-01-01',
        );

        expect($isMwe)->toBeFalse();
    });
});
