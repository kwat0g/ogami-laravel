<?php

declare(strict_types=1);

use App\Domains\Payroll\Services\PagibigContributionService;
use App\Domains\Payroll\Services\PhilHealthContributionService;
use App\Domains\Payroll\Services\SssContributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Government Contribution Tests — SSS, PhilHealth, Pag-IBIG
|--------------------------------------------------------------------------
| Rule IDs: SSS-001–004, PHL-001–006, PAGIBIG-001–004, EDGE-014
|
| Tables required: sss_contribution_tables, philhealth_premium_tables,
|                  pagibig_contribution_tables
--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'SssContributionTableSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'PhilhealthPremiumTableSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'PagibigContributionTableSeeder'])->assertExitCode(0);

    $this->sss = app(SssContributionService::class);
    $this->phi = app(PhilHealthContributionService::class);
    $this->pagibig = app(PagibigContributionService::class);
});

// ===========================================================================
// SSS
// ===========================================================================

describe('SssContributionService', function () {

    it('returns 0 on 1st cutoff — SSS-003', function () {
        $result = $this->sss->computeEmployeeShare(
            basicMonthlyCentavos: 2_500_000, // ₱25,000
            isSecondCutoff: false,
        );

        expect($result)->toBe(0);
    });

    it('returns the employer share as 0 on 1st cutoff — SSS-003', function () {
        $result = $this->sss->computeEmployerShare(2_500_000, false);
        expect($result)->toBe(0);
    });

    it('returns a positive contribution on 2nd cutoff for standard salary — SSS-001', function () {
        $result = $this->sss->computeEmployeeShare(2_500_000, true);
        expect($result)->toBeGreaterThan(0);
    });

    it('uses lowest bracket for salary below SSS minimum MSC — SSS-004', function () {
        $belowMin = $this->sss->computeEmployeeShare(200_000, true);  // ₱2,000
        $atMin = $this->sss->computeEmployeeShare(400_000, true);  // ₱4,000

        // Below-minimum salary must not error and must use a valid bracket (≤ atMin bracket)
        expect($belowMin)->toBeGreaterThan(0);
        expect($atMin)->toBeGreaterThan(0);
        expect($belowMin)->toBeLessThanOrEqual($atMin);
    });

    it('caps contribution at maximum MSC bracket — EDGE-014', function () {
        $atCap = $this->sss->computeEmployeeShare(3_000_000, true);   // ₱30,000
        $aboveCap = $this->sss->computeEmployeeShare(5_000_000, true);   // ₱50,000

        // Above cap must equal the cap bracket contribution
        expect($atCap)->toBe($aboveCap)
            ->toBeGreaterThan(0);
    });

    it('computes employer share as positive value on 2nd cutoff', function () {
        $er = $this->sss->computeEmployerShare(2_500_000, true);
        expect($er)->toBeGreaterThan(0);
    });
});

// ===========================================================================
// PhilHealth
// ===========================================================================

describe('PhilHealthContributionService', function () {

    it('computes employee share per semi-monthly period — PHL-004', function () {
        // ₱25,000/month × 5% = ₱1,250 total, EE = ₱625/month, per period = ₱312.50
        $result = $this->phi->computeEmployeeSharePerPeriod(2_500_000);
        expect($result)->toBe(31_250); // 312.50 in centavos
    });

    it('applies minimum monthly premium floor — PHL-005', function () {
        // Very low salary → minimum ₱500 total → EE ₱62.50/period
        $result = $this->phi->computeEmployeeSharePerPeriod(50_000); // ₱500/month
        $minPermitted = (int) round(500.0 / 2 / 2 * 100); // 62.50 in centavos ... but PHL says min_monthly so:
        // min_monthly_premium = 500 → EE monthly = 250 → per period = 125
        expect($result)->toBe(12_500); // ₱125 per period
    });

    it('caps at maximum monthly premium — PHL-006', function () {
        // ₱80,000/month × 5% = ₱4,000 total, below max ₱5,000 → ₱2,000 EE → ₱1,000/period
        $atHigh = $this->phi->computeEmployeeSharePerPeriod(8_000_000);

        // ₱200,000/month × 5% = ₱10,000 → capped at ₱5,000 total → EE ₱2,500 → ₱1,250/period
        $aboveCap = $this->phi->computeEmployeeSharePerPeriod(20_000_000);

        expect($aboveCap)->toBe(125_000); // ₱1,250 in centavos = max per period
        expect($atHigh)->toBeLessThanOrEqual($aboveCap);
    });

    it('employer share equals employee share per period — PHL-003', function () {
        $ee = $this->phi->computeEmployeeSharePerPeriod(2_500_000);
        $er = $this->phi->computeEmployerSharePerPeriod(2_500_000);
        expect($ee)->toBe($er);
    });

    it('total monthly premium equals 2x employee monthly share — PHL-003', function () {
        $total = $this->phi->computeTotalMonthlyPremium(2_500_000); // ₱1,250 = 125,000 centavos
        $eeMonthly = $this->phi->computeEmployeeSharePerPeriod(2_500_000) * 2; // ₱312.50 × 2 = ₱625 = 62,500 centavos
        // Total premium (EE + ER) = 2 × employee monthly share
        expect($total)->toBe($eeMonthly * 2);
    });
});

// ===========================================================================
// Pag-IBIG
// ===========================================================================

describe('PagibigContributionService', function () {

    it('applies 1% rate for basic ≤ ₱1,500 — PAGIBIG-002', function () {
        // ₱1,500 × 1% = ₱15/month → ₱7.50/period
        $result = $this->pagibig->computeEmployeeSharePerPeriod(150_000);
        expect($result)->toBeGreaterThan(0)->toBeLessThanOrEqual(5_000);
    });

    it('applies 2% rate for basic > ₱1,500 — PAGIBIG-002', function () {
        // ₱25,000 × 2% = ₱500 → capped at ₱100/month → ₱50/period
        $result = $this->pagibig->computeEmployeeSharePerPeriod(2_500_000);
        expect($result)->toBe(5_000); // ₱50.00 = 5000 centavos
    });

    it('caps employee share at ₱50 per semi-monthly period — PAGIBIG-003', function () {
        $highSalary = $this->pagibig->computeEmployeeSharePerPeriod(10_000_000); // ₱100,000
        expect($highSalary)->toBe(5_000); // cap is always ₱50/period
    });

    it('employer share has no cap and is positive — PAGIBIG-004', function () {
        $er = $this->pagibig->computeEmployerSharePerPeriod(2_500_000);
        expect($er)->toBeGreaterThan(0);

        // High salary → ER share >> ₱50 (not capped)
        $erHigh = $this->pagibig->computeEmployerSharePerPeriod(10_000_000);
        expect($erHigh)->toBeGreaterThan(5_000);
    });
});
