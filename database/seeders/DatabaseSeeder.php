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
            // ── Modules (v2 permission system) ───────────────────────────────
            // Must run AFTER RolePermissionSeeder (creates module + role matrix)
            ModuleSeeder::class,
            ModulePermissionSeeder::class,
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
            // ── Department module assignments (RBAC v2) ───────────────────────
            // Must run AFTER ModuleSeeder + DepartmentPositionSeeder
            DepartmentModuleAssignmentSeeder::class,
            // ── Department permission profiles (v2) ───────────────────────────
            // Must run AFTER DepartmentPositionSeeder (requires departments to exist)
            DepartmentPermissionProfileSeeder::class,
            DepartmentPermissionTemplateSeeder::class,
            // ── Reversed Hierarchy Permissions ────────────────────────────────
            // Officer (full) → Manager → Head → Staff (minimal)
            // Run BEFORE employee seeders so users get correct permissions
            ReversedHierarchyPermissionSeeder::class,
            // ── Consolidated employee seeder ──────────────────────────────────
            // Creates Executive/Manager/Officer/Head/Staff for each department
            // with properly linked user accounts and employee records
            ConsolidatedEmployeeSeeder::class,
            // ── Comprehensive test accounts ────────────────────────────────────
            // Creates standardized {dept}.{role}@ogamierp.local accounts
            // for consistent testing across all departments
            ComprehensiveTestAccountsSeeder::class,
            // ── Transactional sample data ─────────────────────────────────────
            // Must come last — depends on employees, leave_types, COA, fiscal_periods
            SampleDataSeeder::class,
            // ── Leave balances ────────────────────────────────────────────────
            // Must run after SampleDataSeeder (requires employees to exist)
            LeaveBalanceSeeder::class,
            // ── Attendance data (Jan-Feb 2026) ────────────────────────────────
            // Must run after employees are seeded
            SampleAttendanceJanFeb2026Seeder::class,
            // ── New modules (Sprints A-F) ─────────────────────────────────────
            // Must come last — depends on users, item_masters, vendors, customers
            NewModulesSeeder::class,
            // ── Fleet (delivery vehicles) ─────────────────────────────────────
            // Requires vehicles migration (000018) to have run
            FleetSeeder::class,
            // ── Comprehensive test data for all modules ───────────────────────
            // Inventory, Production, QC, Maintenance, Delivery test data
            ComprehensiveTestDataSeeder::class,

            // ── Client Order Workflow Test Data ───────────────────────────────
            // Client orders, delivery schedules, production orders for testing
            ClientOrderWorkflowTestSeeder::class,

            // ── Manual / exploratory testing enrichment ───────────────────────
            // Vendor portal users for all vendors, client portal users for all
            // customers, FG standard prices, FG stock, extra vendor catalog items.
            // Safe to re-run independently: php artisan db:seed --class=ManualTestingSeeder
            ManualTestingSeeder::class,
        ]);
    }
}
