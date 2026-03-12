<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master seeder — runs all application seeders in dependency order.
 *
 * Order matters:
 *  1. Config/rate tables (no FK dependencies)
 *  2. RBAC + sample user accounts
 *  3. HR + Accounting reference tables
 *  4. Organizational structure (departments, positions, fiscal periods)
 *  5. Transactional sample data (employees, JEs, attendance, leave balances)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Configuration tables (zero dependencies) ─────────────────────
            SystemSettingsSeeder::class,
            TrainTaxBracketSeeder::class,
            SssContributionTableSeeder::class,
            PhilhealthPremiumTableSeeder::class,
            PagibigContributionTableSeeder::class,
            MinimumWageRateSeeder::class,
            OvertimeMultiplierSeeder::class,
            HolidayCalendarSeeder::class,

            // ── RBAC (requires spatie/laravel-permission tables) ──────────────
            RolePermissionSeeder::class,
            SampleAccountsSeeder::class,

            // ── HR reference tables ───────────────────────────────────────────
            SalaryGradeSeeder::class,
            LeaveTypeSeeder::class,
            LoanTypeSeeder::class,
            ShiftScheduleSeeder::class,

            // ── Accounting reference tables ───────────────────────────────────
            ChartOfAccountsSeeder::class,

            // ── Organizational structure ──────────────────────────────────────
            FiscalPeriodSeeder::class,
            DepartmentPositionSeeder::class,
            // ── Department permission profiles (v2) ───────────────────────────
            // Must run AFTER DepartmentPositionSeeder (requires departments to exist)
            DepartmentPermissionProfileSeeder::class,
            DepartmentPermissionTemplateSeeder::class,
            // ── Transactional sample data ─────────────────────────────────────
            // Must come last — depends on employees, leave_types, COA, fiscal_periods
            SampleDataSeeder::class,
            // ── Leave balances ────────────────────────────────────────────────
            // Must run after SampleDataSeeder (requires employees to exist)
            LeaveBalanceSeeder::class,
            // ── Manufacturing org chart employees (Sprints A–F roles) ─────────
            // Must run after DepartmentPositionSeeder + RolePermissionSeeder
            ManufacturingEmployeeSeeder::class,
            // ── New modules (Sprints A-F) ─────────────────────────────────────
            // Must come last — depends on users, item_masters, vendors, customers
            NewModulesSeeder::class,
            // ── Fleet (delivery vehicles) ─────────────────────────────────────
            // Requires vehicles migration (000018) to have run
            FleetSeeder::class,
        ]);
    }
}
