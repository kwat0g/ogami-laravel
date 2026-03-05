<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Seeds the minimum chart of accounts required by integration tests.
 *
 * Account codes used:
 *  1001 — Cash in Bank                (asset,     debit  normal)
 *  1200 — Loans Receivable - Employee (asset,     debit  normal)
 *  2001 — Accounts Payable            (liability, credit normal)
 *  2100 — SSS Contributions Payable   (liability, credit normal)
 *  2101 — PhilHealth Payable          (liability, credit normal)
 *  2102 — PagIBIG Payable             (liability, credit normal)
 *  2103 — Withholding Tax Payable     (liability, credit normal)
 *  2104 — Loans Payable - Employee    (liability, credit normal) — pending disbursement
 *  2200 — Payroll Payable             (liability, credit normal)
 *  5001 — Salaries and Wages Expense  (expense,   debit  normal)
 *  6001 — Utilities Expense           (expense,   debit  normal)
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1001', 'name' => 'Cash in Bank',                'account_type' => 'ASSET',     'normal_balance' => 'DEBIT'],
            ['code' => '1200', 'name' => 'Loans Receivable - Employee', 'account_type' => 'ASSET',     'normal_balance' => 'DEBIT'],
            ['code' => '2001', 'name' => 'Accounts Payable',            'account_type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '2100', 'name' => 'SSS Contributions Payable',   'account_type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '2101', 'name' => 'PhilHealth Payable',          'account_type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '2102', 'name' => 'PagIBIG Payable',             'account_type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '2103', 'name' => 'Withholding Tax Payable',     'account_type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '2104', 'name' => 'Loans Payable - Employee',    'account_type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '2200', 'name' => 'Payroll Payable',             'account_type' => 'LIABILITY', 'normal_balance' => 'CREDIT'],
            ['code' => '5001', 'name' => 'Salaries and Wages Expense',  'account_type' => 'OPEX',      'normal_balance' => 'DEBIT'],
            ['code' => '6001', 'name' => 'Utilities Expense',           'account_type' => 'OPEX',      'normal_balance' => 'DEBIT'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['code' => $account['code']],
                array_merge($account, ['is_active' => true, 'is_system' => true])
            );
        }
    }
}
