<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Intentionally empty — all business data (vendors, customers, items, BOM,
 * equipment, molds, bank accounts, etc.) is created manually during testing.
 *
 * System reference data that IS seeded by dedicated seeders:
 *   - Chart of Accounts  → ChartOfAccountsSeeder  (16 accounts; hardcoded in GL services)
 *   - Fiscal Periods     → FiscalPeriodSeeder      (Nov 2025 – Mar 2026)
 *   - Rate tables, RBAC, salary grades, departments, etc. → their own seeders
 */
class NewModulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('NewModulesSeeder: nothing to seed — all data is created manually.');
    }
}
