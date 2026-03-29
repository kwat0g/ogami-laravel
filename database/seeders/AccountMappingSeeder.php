<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the account_mappings table with current defaults (F-002).
 *
 * These match the previously hardcoded GL account codes throughout the codebase.
 * Finance can update these via the admin UI without code deployments.
 */
class AccountMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            // ── Payroll ─────────────────────────────────────────────────────
            ['module' => 'payroll', 'event' => 'PAYROLL_POST', 'sub_key' => null, 'side' => 'debit', 'account_code' => '5001', 'description' => 'Salaries and Wages Expense'],
            ['module' => 'payroll', 'event' => 'PAYROLL_POST', 'sub_key' => 'sss', 'side' => 'credit', 'account_code' => '2100', 'description' => 'SSS Payable (EE share)'],
            ['module' => 'payroll', 'event' => 'PAYROLL_POST', 'sub_key' => 'philhealth', 'side' => 'credit', 'account_code' => '2101', 'description' => 'PhilHealth Payable (EE share)'],
            ['module' => 'payroll', 'event' => 'PAYROLL_POST', 'sub_key' => 'pagibig', 'side' => 'credit', 'account_code' => '2102', 'description' => 'Pag-IBIG Payable (EE share)'],
            ['module' => 'payroll', 'event' => 'PAYROLL_POST', 'sub_key' => 'wht', 'side' => 'credit', 'account_code' => '2103', 'description' => 'Withholding Tax Payable'],
            ['module' => 'payroll', 'event' => 'PAYROLL_POST', 'sub_key' => 'net_pay', 'side' => 'credit', 'account_code' => '2104', 'description' => 'Wages Payable (net pay)'],

            // ── Procurement (GR) ─────────────────────────────────────────────
            ['module' => 'procurement', 'event' => 'GR_POST', 'sub_key' => null, 'side' => 'debit', 'account_code' => '1300', 'description' => 'Raw Materials Inventory'],
            ['module' => 'procurement', 'event' => 'GR_POST', 'sub_key' => null, 'side' => 'credit', 'account_code' => '2001', 'description' => 'GR/IR Clearing (AP accrual)'],
            ['module' => 'procurement', 'event' => 'GR_REVERSAL', 'sub_key' => null, 'side' => 'debit', 'account_code' => '2001', 'description' => 'AP (decrease on return)'],
            ['module' => 'procurement', 'event' => 'GR_REVERSAL', 'sub_key' => null, 'side' => 'credit', 'account_code' => '1300', 'description' => 'Inventory (decrease on return)'],

            // ── AP ──────────────────────────────────────────────────────────
            ['module' => 'ap', 'event' => 'INVOICE_POST', 'sub_key' => null, 'side' => 'debit', 'account_code' => '6001', 'description' => 'General Expense'],
            ['module' => 'ap', 'event' => 'INVOICE_POST', 'sub_key' => null, 'side' => 'credit', 'account_code' => '2001', 'description' => 'Accounts Payable'],
            ['module' => 'ap', 'event' => 'PAYMENT_POST', 'sub_key' => null, 'side' => 'debit', 'account_code' => '2001', 'description' => 'Accounts Payable (decrease)'],
            ['module' => 'ap', 'event' => 'PAYMENT_POST', 'sub_key' => null, 'side' => 'credit', 'account_code' => '1001', 'description' => 'Cash in Bank'],

            // ── AR ──────────────────────────────────────────────────────────
            ['module' => 'ar', 'event' => 'INVOICE_POST', 'sub_key' => null, 'side' => 'debit', 'account_code' => '1100', 'description' => 'Accounts Receivable'],
            ['module' => 'ar', 'event' => 'INVOICE_POST', 'sub_key' => null, 'side' => 'credit', 'account_code' => '4001', 'description' => 'Revenue'],

            // ── Production ──────────────────────────────────────────────────
            ['module' => 'production', 'event' => 'COST_POST', 'sub_key' => 'wip', 'side' => 'debit', 'account_code' => '1400', 'description' => 'Work in Process'],
            ['module' => 'production', 'event' => 'COST_POST', 'sub_key' => 'raw_material', 'side' => 'credit', 'account_code' => '1300', 'description' => 'Raw Materials Inventory'],
            ['module' => 'production', 'event' => 'COST_POST', 'sub_key' => 'variance', 'side' => 'debit', 'account_code' => '5900', 'description' => 'Manufacturing Variance'],

            // ── Tax ─────────────────────────────────────────────────────────
            ['module' => 'tax', 'event' => 'VAT_CLOSE', 'sub_key' => 'output', 'side' => 'debit', 'account_code' => '2105', 'description' => 'Output VAT'],
            ['module' => 'tax', 'event' => 'VAT_CLOSE', 'sub_key' => 'remittable', 'side' => 'credit', 'account_code' => '2106', 'description' => 'VAT Remittable'],

            // ── Loan ────────────────────────────────────────────────────────
            ['module' => 'loan', 'event' => 'LOAN_APPROVAL', 'sub_key' => null, 'side' => 'debit', 'account_code' => '1200', 'description' => 'Loans Receivable - Employee'],
            ['module' => 'loan', 'event' => 'LOAN_APPROVAL', 'sub_key' => null, 'side' => 'credit', 'account_code' => '2104', 'description' => 'Loans Payable - Employee'],
            ['module' => 'loan', 'event' => 'LOAN_DISBURSE', 'sub_key' => null, 'side' => 'debit', 'account_code' => '2104', 'description' => 'Loans Payable (decrease)'],
            ['module' => 'loan', 'event' => 'LOAN_DISBURSE', 'sub_key' => null, 'side' => 'credit', 'account_code' => '1001', 'description' => 'Cash in Bank'],
        ];

        $now = now();

        foreach ($mappings as $mapping) {
            $accountId = DB::table('chart_of_accounts')
                ->where('code', $mapping['account_code'])
                ->whereNull('deleted_at')
                ->value('id');

            // Also try account_code column (some models use 'account_code' instead of 'code')
            if ($accountId === null) {
                $accountId = DB::table('chart_of_accounts')
                    ->where('account_code', $mapping['account_code'])
                    ->whereNull('deleted_at')
                    ->value('id');
            }

            if ($accountId === null) {
                $this->command?->warn("Skipping mapping: {$mapping['module']}.{$mapping['event']}.{$mapping['side']} — account code '{$mapping['account_code']}' not found in chart_of_accounts.");

                continue;
            }

            DB::table('account_mappings')->updateOrInsert(
                [
                    'module' => $mapping['module'],
                    'event' => $mapping['event'],
                    'sub_key' => $mapping['sub_key'],
                    'side' => $mapping['side'],
                ],
                [
                    'account_id' => $accountId,
                    'description' => $mapping['description'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
