<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds TRAIN Law income tax brackets (RA 10963, effective January 1, 2023).
 *
 * TAX-004 reminder: This table has NO tax_status_group column.
 * TRAIN Law abolished personal exemptions — one universal bracket applies
 * to all employees. BIR codes are derived at report time only.
 *
 * Bracket source: BIR Revenue Regulations 8-2018 as amended by RR 2-2023.
 * Annual income thresholds in PHP.
 *
 * Annualize formula (TAX-001): annualized = taxable_income × 24 (semi-monthly)
 * Tax formula (TAX-003): annual_tax = base_tax + (annualized - income_from) × excess_rate
 * De-annualize (TAX-005): period_tax = annual_tax / 24
 */
class TrainTaxBracketSeeder extends Seeder
{
    public function run(): void
    {
        // TRAIN Law 2023 brackets (RR 2-2023, effective January 1, 2023)
        $brackets2023 = [
            // income_from, income_to, base_tax, excess_rate, notes
            [0.00,          250000.00, 0.00,      0.00,    'Zero tax — income below ₱250,000 annual threshold (RR 2-2023)'],
            [250000.01,     400000.00, 0.00,      0.15,    '15% on excess over ₱250,000'],
            [400000.01,     800000.00, 22500.00,  0.20,    '₱22,500 + 20% on excess over ₱400,000'],
            [800000.01,   2000000.00, 102500.00,  0.25,    '₱102,500 + 25% on excess over ₱800,000'],
            [2000000.01,  8000000.00, 402500.00,  0.30,    '₱402,500 + 30% on excess over ₱2,000,000'],
            [8000000.01,       null,  2202500.00, 0.35,    '₱2,202,500 + 35% on excess over ₱8,000,000'],
        ];

        foreach ($brackets2023 as [$from, $to, $baseTax, $excessRate, $notes]) {
            DB::table('train_tax_brackets')->insertOrIgnore([
                'effective_date' => '2023-01-01',
                'income_from' => $from,
                'income_to' => $to,
                'base_tax' => $baseTax,
                'excess_rate' => $excessRate,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
