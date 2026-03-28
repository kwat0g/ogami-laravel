<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive Test Accounts Seeder
 *
 * Creates employee records linked to user accounts for ALL departments.
 * Reversed Hierarchy: Officer (full) → Manager (oversight) → Head (team lead) → Staff (basic)
 *
 * Password Pattern: {Role}@Test1234! (first letter capitalized)
 *
 * Accounts created for ALL 13 departments with 4 roles each (Officer, Manager, Head, Staff)
 */
class ComprehensiveTestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  COMPREHENSIVE TEST ACCOUNTS SEEDER');
        $this->command->info('  Hierarchy: Officer → Manager → Head → Staff');
        $this->command->info('═══════════════════════════════════════════════════════════════');

        $this->seedHREmployees();
        $this->seedAccountingEmployees();
        $this->seedProductionEmployees();
        $this->seedQCEmployees();
        $this->seedMoldEmployees();
        $this->seedPlantEmployees();
        $this->seedWarehouseEmployees();
        $this->seedPPCEmployees();
        $this->seedMaintenanceEmployees();
        $this->seedISOEmployees();
        $this->seedPurchasingEmployees();
        $this->seedSalesEmployees();
        $this->seedITEmployees();
        $this->seedExecutiveEmployees();

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  ALL TEST ACCOUNTS SUMMARY (52 accounts)');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->printAccountSummary();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HR DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedHREmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ HR Department ───────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-HR-001', 'first_name' => 'Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'email' => 'hr.officer@ogamierp.local', 'role' => 'officer', 'position' => 'HR-OFF', 'salary' => 4500000, 'user_name' => 'Maria Santos (HR Officer)'],
            ['code' => 'EMP-COMP-HR-002', 'first_name' => 'Grace', 'middle_name' => 'Mendoza', 'last_name' => 'Torres', 'email' => 'hr.manager@ogamierp.local', 'role' => 'manager', 'position' => 'HR-MGR', 'salary' => 4000000, 'user_name' => 'Grace Torres (HR Manager)'],
            ['code' => 'EMP-COMP-HR-003', 'first_name' => 'Ricardo', 'middle_name' => 'Bautista', 'last_name' => 'Cruz', 'email' => 'hr.head@ogamierp.local', 'role' => 'head', 'position' => 'HR-HEAD', 'salary' => 2800000, 'user_name' => 'Ricardo Cruz (HR Head)'],
            ['code' => 'EMP-COMP-HR-004', 'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz', 'email' => 'hr.staff@ogamierp.local', 'role' => 'staff', 'position' => 'HR-STAFF', 'salary' => 1800000, 'user_name' => 'Juan Dela Cruz (HR Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'HR');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ACCOUNTING DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedAccountingEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ ACCTG Department ────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-ACCT-001', 'first_name' => 'Amelia', 'middle_name' => 'Dela Cruz', 'last_name' => 'Cordero', 'email' => 'acctg.officer@ogamierp.local', 'role' => 'officer', 'position' => 'ACCT-OFF', 'salary' => 5500000, 'user_name' => 'Amelia Cordero (Accounting Officer)'],
            ['code' => 'EMP-COMP-ACCT-002', 'first_name' => 'Anna Marie', 'middle_name' => 'Cruz', 'last_name' => 'Lim', 'email' => 'acctg.manager@ogamierp.local', 'role' => 'manager', 'position' => 'ACCT-MGR', 'salary' => 4800000, 'user_name' => 'Anna Marie Lim (Accounting Manager)'],
            ['code' => 'EMP-COMP-ACCT-003', 'first_name' => 'Roberto', 'middle_name' => ' Santos', 'last_name' => 'Garcia', 'email' => 'acctg.head@ogamierp.local', 'role' => 'head', 'position' => 'ACCT-HEAD', 'salary' => 3200000, 'user_name' => 'Roberto Garcia (Accounting Head)'],
            ['code' => 'EMP-COMP-ACCT-004', 'first_name' => 'Carmen', 'middle_name' => 'Reyes', 'last_name' => 'Diaz', 'email' => 'acctg.staff@ogamierp.local', 'role' => 'staff', 'position' => 'ACCT-STAFF', 'salary' => 2000000, 'user_name' => 'Carmen Diaz (Accounting Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'ACCTG');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PRODUCTION DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedProductionEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ PROD Department ─────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-PROD-001', 'first_name' => 'Elena', 'middle_name' => 'Diaz', 'last_name' => 'Rodriguez', 'email' => 'prod.officer@ogamierp.local', 'role' => 'officer', 'position' => 'PROD-OFF', 'salary' => 4500000, 'user_name' => 'Elena Rodriguez (Production Officer)'],
            ['code' => 'EMP-COMP-PROD-002', 'first_name' => 'Miguel', 'middle_name' => 'Santos', 'last_name' => 'Fernandez', 'email' => 'prod.manager@ogamierp.local', 'role' => 'manager', 'position' => 'PROD-MGR', 'salary' => 4000000, 'user_name' => 'Miguel Fernandez (Production Manager)'],
            ['code' => 'EMP-COMP-PROD-003', 'first_name' => 'Sofia', 'middle_name' => 'Cruz', 'last_name' => 'Reyes', 'email' => 'prod.head@ogamierp.local', 'role' => 'head', 'position' => 'PROD-HEAD', 'salary' => 3000000, 'user_name' => 'Sofia Reyes (Production Head)'],
            ['code' => 'EMP-COMP-PROD-004', 'first_name' => 'Jose', 'middle_name' => 'Garcia', 'last_name' => 'Martinez', 'email' => 'prod.staff@ogamierp.local', 'role' => 'staff', 'position' => 'PROD-STAFF', 'salary' => 1900000, 'user_name' => 'Jose Martinez (Production Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'PROD');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QC DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedQCEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ QC Department ───────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-QC-001', 'first_name' => 'Patricia', 'middle_name' => 'Lim', 'last_name' => 'Tan', 'email' => 'qc.officer@ogamierp.local', 'role' => 'officer', 'position' => 'QC-OFF', 'salary' => 4200000, 'user_name' => 'Patricia Tan (QC Officer)'],
            ['code' => 'EMP-COMP-QC-002', 'first_name' => 'Antonio', 'middle_name' => 'Reyes', 'last_name' => 'Wong', 'email' => 'qc.manager@ogamierp.local', 'role' => 'manager', 'position' => 'QC-MGR', 'salary' => 3800000, 'user_name' => 'Antonio Wong (QC Manager)'],
            ['code' => 'EMP-COMP-QC-003', 'first_name' => 'Diana', 'middle_name' => 'Cruz', 'last_name' => 'Liu', 'email' => 'qc.head@ogamierp.local', 'role' => 'head', 'position' => 'QC-HEAD', 'salary' => 2800000, 'user_name' => 'Diana Liu (QC Head)'],
            ['code' => 'EMP-COMP-QC-004', 'first_name' => 'Kevin', 'middle_name' => 'Tan', 'last_name' => 'Zhang', 'email' => 'qc.staff@ogamierp.local', 'role' => 'staff', 'position' => 'QC-STAFF', 'salary' => 1800000, 'user_name' => 'Kevin Zhang (QC Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'QC');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MOLD DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedMoldEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ MOLD Department ─────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-MOLD-001', 'first_name' => 'Fernando', 'middle_name' => 'Cruz', 'last_name' => 'Silva', 'email' => 'mold.officer@ogamierp.local', 'role' => 'officer', 'position' => 'MOLD-OFF', 'salary' => 4500000, 'user_name' => 'Fernando Silva (Mold Officer)'],
            ['code' => 'EMP-COMP-MOLD-002', 'first_name' => 'Isabella', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'email' => 'mold.manager@ogamierp.local', 'role' => 'manager', 'position' => 'MOLD-MGR', 'salary' => 4000000, 'user_name' => 'Isabella Santos (Mold Manager)'],
            ['code' => 'EMP-COMP-MOLD-003', 'first_name' => 'Rafael', 'middle_name' => 'Diaz', 'last_name' => 'Cruz', 'email' => 'mold.head@ogamierp.local', 'role' => 'head', 'position' => 'MOLD-HEAD', 'salary' => 3000000, 'user_name' => 'Rafael Cruz (Mold Head)'],
            ['code' => 'EMP-COMP-MOLD-004', 'first_name' => 'Monica', 'middle_name' => 'Garcia', 'last_name' => 'Lopez', 'email' => 'mold.staff@ogamierp.local', 'role' => 'staff', 'position' => 'MOLD-STAFF', 'salary' => 1900000, 'user_name' => 'Monica Lopez (Mold Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'MOLD');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PLANT DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedPlantEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ PLANT Department ────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-PLANT-001', 'first_name' => 'Gabriel', 'middle_name' => 'Santos', 'last_name' => 'Torres', 'email' => 'plant.officer@ogamierp.local', 'role' => 'officer', 'position' => 'PLANT-OFF', 'salary' => 5000000, 'user_name' => 'Gabriel Torres (Plant Officer)'],
            ['code' => 'EMP-COMP-PLANT-002', 'first_name' => 'Lucia', 'middle_name' => 'Cruz', 'last_name' => 'Fernandez', 'email' => 'plant.manager@ogamierp.local', 'role' => 'manager', 'position' => 'PLANT-MGR', 'salary' => 4500000, 'user_name' => 'Lucia Fernandez (Plant Manager)'],
            ['code' => 'EMP-COMP-PLANT-003', 'first_name' => 'Eduardo', 'middle_name' => 'Reyes', 'last_name' => 'Garcia', 'email' => 'plant.head@ogamierp.local', 'role' => 'head', 'position' => 'PLANT-HEAD', 'salary' => 3200000, 'user_name' => 'Eduardo Garcia (Plant Head)'],
            ['code' => 'EMP-COMP-PLANT-004', 'first_name' => 'Mariana', 'middle_name' => 'Diaz', 'last_name' => 'Silva', 'email' => 'plant.staff@ogamierp.local', 'role' => 'staff', 'position' => 'PLANT-STAFF', 'salary' => 2000000, 'user_name' => 'Mariana Silva (Plant Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'PLANT');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // WAREHOUSE DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedWarehouseEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ WH Department ───────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-WH-001', 'first_name' => 'Hector', 'middle_name' => 'Cruz', 'last_name' => 'Reyes', 'email' => 'wh.officer@ogamierp.local', 'role' => 'officer', 'position' => 'WH-OFF', 'salary' => 4000000, 'user_name' => 'Hector Reyes (Warehouse Officer)'],
            ['code' => 'EMP-COMP-WH-002', 'first_name' => 'Carmela', 'middle_name' => 'Santos', 'last_name' => 'Diaz', 'email' => 'wh.manager@ogamierp.local', 'role' => 'manager', 'position' => 'WH-MGR', 'salary' => 3500000, 'user_name' => 'Carmela Diaz (Warehouse Manager)'],
            ['code' => 'EMP-COMP-WH-003', 'first_name' => 'Rodrigo', 'middle_name' => 'Garcia', 'last_name' => 'Cruz', 'email' => 'wh.head@ogamierp.local', 'role' => 'head', 'position' => 'WH-HEAD', 'salary' => 2600000, 'user_name' => 'Rodrigo Cruz (Warehouse Head)'],
            ['code' => 'EMP-COMP-WH-004', 'first_name' => 'Nina', 'middle_name' => 'Reyes', 'last_name' => 'Torres', 'email' => 'wh.staff@ogamierp.local', 'role' => 'staff', 'position' => 'WH-STAFF', 'salary' => 1700000, 'user_name' => 'Nina Torres (Warehouse Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'WH');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PPC DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedPPCEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ PPC Department ──────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-PPC-001', 'first_name' => 'Ignacio', 'middle_name' => 'Diaz', 'last_name' => 'Santos', 'email' => 'ppc.officer@ogamierp.local', 'role' => 'officer', 'position' => 'PPC-OFF', 'salary' => 4200000, 'user_name' => 'Ignacio Santos (PPC Officer)'],
            ['code' => 'EMP-COMP-PPC-002', 'first_name' => 'Teresa', 'middle_name' => 'Cruz', 'last_name' => 'Fernandez', 'email' => 'ppc.manager@ogamierp.local', 'role' => 'manager', 'position' => 'PPC-MGR', 'salary' => 3800000, 'user_name' => 'Teresa Fernandez (PPC Manager)'],
            ['code' => 'EMP-COMP-PPC-003', 'first_name' => 'Samuel', 'middle_name' => 'Reyes', 'last_name' => 'Garcia', 'email' => 'ppc.head@ogamierp.local', 'role' => 'head', 'position' => 'PPC-HEAD', 'salary' => 2800000, 'user_name' => 'Samuel Garcia (PPC Head)'],
            ['code' => 'EMP-COMP-PPC-004', 'first_name' => 'Olivia', 'middle_name' => 'Santos', 'last_name' => 'Cruz', 'email' => 'ppc.staff@ogamierp.local', 'role' => 'staff', 'position' => 'PPC-STAFF', 'salary' => 1800000, 'user_name' => 'Olivia Cruz (PPC Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'PPC');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MAINTENANCE DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedMaintenanceEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ MAINT Department ────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-MAINT-001', 'first_name' => 'Julio', 'middle_name' => 'Garcia', 'last_name' => 'Reyes', 'email' => 'maint.officer@ogamierp.local', 'role' => 'officer', 'position' => 'MAINT-OFF', 'salary' => 4000000, 'user_name' => 'Julio Reyes (Maintenance Officer)'],
            ['code' => 'EMP-COMP-MAINT-002', 'first_name' => 'Rosa', 'middle_name' => 'Diaz', 'last_name' => 'Santos', 'email' => 'maint.manager@ogamierp.local', 'role' => 'manager', 'position' => 'MAINT-MGR', 'salary' => 3600000, 'user_name' => 'Rosa Santos (Maintenance Manager)'],
            ['code' => 'EMP-COMP-MAINT-003', 'first_name' => 'Victor', 'middle_name' => 'Cruz', 'last_name' => 'Fernandez', 'email' => 'maint.head@ogamierp.local', 'role' => 'head', 'position' => 'MAINT-HEAD', 'salary' => 2700000, 'user_name' => 'Victor Fernandez (Maintenance Head)'],
            ['code' => 'EMP-COMP-MAINT-004', 'first_name' => 'Paola', 'middle_name' => 'Reyes', 'last_name' => 'Garcia', 'email' => 'maint.staff@ogamierp.local', 'role' => 'staff', 'position' => 'MAINT-STAFF', 'salary' => 1750000, 'user_name' => 'Paola Garcia (Maintenance Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'MAINT');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ISO DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedISOEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ ISO Department ──────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-ISO-001', 'first_name' => 'Xavier', 'middle_name' => 'Santos', 'last_name' => 'Cruz', 'email' => 'iso.officer@ogamierp.local', 'role' => 'officer', 'position' => 'ISO-OFF', 'salary' => 4200000, 'user_name' => 'Xavier Cruz (ISO Officer)'],
            ['code' => 'EMP-COMP-ISO-002', 'first_name' => 'Andrea', 'middle_name' => 'Cruz', 'last_name' => 'Diaz', 'email' => 'iso.manager@ogamierp.local', 'role' => 'manager', 'position' => 'ISO-MGR', 'salary' => 3800000, 'user_name' => 'Andrea Diaz (ISO Manager)'],
            ['code' => 'EMP-COMP-ISO-003', 'first_name' => 'Bruno', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'email' => 'iso.head@ogamierp.local', 'role' => 'head', 'position' => 'ISO-HEAD', 'salary' => 2800000, 'user_name' => 'Bruno Santos (ISO Head)'],
            ['code' => 'EMP-COMP-ISO-004', 'first_name' => 'Clara', 'middle_name' => 'Garcia', 'last_name' => 'Fernandez', 'email' => 'iso.staff@ogamierp.local', 'role' => 'staff', 'position' => 'ISO-STAFF', 'salary' => 1800000, 'user_name' => 'Clara Fernandez (ISO Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'ISO');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PURCHASING DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedPurchasingEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ PURCH Department ────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-PURCH-001', 'first_name' => 'Yolanda', 'middle_name' => 'Diaz', 'last_name' => 'Reyes', 'email' => 'purch.officer@ogamierp.local', 'role' => 'officer', 'position' => 'PURCH-OFF', 'salary' => 4200000, 'user_name' => 'Yolanda Reyes (Purchasing Officer)'],
            ['code' => 'EMP-COMP-PURCH-002', 'first_name' => 'Diego', 'middle_name' => 'Santos', 'last_name' => 'Cruz', 'email' => 'purch.manager@ogamierp.local', 'role' => 'manager', 'position' => 'PURCH-MGR', 'salary' => 3800000, 'user_name' => 'Diego Cruz (Purchasing Manager)'],
            ['code' => 'EMP-COMP-PURCH-003', 'first_name' => 'Felicia', 'middle_name' => 'Cruz', 'last_name' => 'Garcia', 'email' => 'purch.head@ogamierp.local', 'role' => 'head', 'position' => 'PURCH-HEAD', 'salary' => 2800000, 'user_name' => 'Felicia Garcia (Purchasing Head)'],
            ['code' => 'EMP-COMP-PURCH-004', 'first_name' => 'Hugo', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'email' => 'purch.staff@ogamierp.local', 'role' => 'staff', 'position' => 'PURCH-STAFF', 'salary' => 1800000, 'user_name' => 'Hugo Santos (Purchasing Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'PURCH');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SALES DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedSalesEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ SALES Department ────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-SALES-001', 'first_name' => 'Zara', 'middle_name' => 'Garcia', 'last_name' => 'Cruz', 'email' => 'sales.officer@ogamierp.local', 'role' => 'officer', 'position' => 'SALES-OFF', 'salary' => 4000000, 'user_name' => 'Zara Cruz (Sales Officer)'],
            ['code' => 'EMP-COMP-SALES-002', 'first_name' => 'Alejandro', 'middle_name' => 'Diaz', 'last_name' => 'Reyes', 'email' => 'sales.manager@ogamierp.local', 'role' => 'manager', 'position' => 'SALES-MGR', 'salary' => 3600000, 'user_name' => 'Alejandro Reyes (Sales Manager)'],
            ['code' => 'EMP-COMP-SALES-003', 'first_name' => 'Bianca', 'middle_name' => 'Santos', 'last_name' => 'Fernandez', 'email' => 'sales.head@ogamierp.local', 'role' => 'head', 'position' => 'SALES-HEAD', 'salary' => 2700000, 'user_name' => 'Bianca Fernandez (Sales Head)'],
            ['code' => 'EMP-COMP-SALES-004', 'first_name' => 'Carlos', 'middle_name' => 'Cruz', 'last_name' => 'Garcia', 'email' => 'sales.staff@ogamierp.local', 'role' => 'staff', 'position' => 'SALES-STAFF', 'salary' => 1700000, 'user_name' => 'Carlos Garcia (Sales Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'SALES');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // IT DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedITEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ IT Department ───────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-IT-001', 'first_name' => 'Daniel', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'email' => 'it.officer@ogamierp.local', 'role' => 'officer', 'position' => 'IT-OFF', 'salary' => 5000000, 'user_name' => 'Daniel Santos (IT Officer)'],
            ['code' => 'EMP-COMP-IT-002', 'first_name' => 'Elena', 'middle_name' => 'Garcia', 'last_name' => 'Cruz', 'email' => 'it.manager@ogamierp.local', 'role' => 'manager', 'position' => 'IT-MGR', 'salary' => 4500000, 'user_name' => 'Elena Cruz (IT Manager)'],
            ['code' => 'EMP-COMP-IT-003', 'first_name' => 'Francisco', 'middle_name' => 'Diaz', 'last_name' => 'Reyes', 'email' => 'it.head@ogamierp.local', 'role' => 'head', 'position' => 'IT-HEAD', 'salary' => 3200000, 'user_name' => 'Francisco Reyes (IT Head)'],
            ['code' => 'EMP-COMP-IT-004', 'first_name' => 'Gina', 'middle_name' => 'Santos', 'last_name' => 'Fernandez', 'email' => 'it.staff@ogamierp.local', 'role' => 'staff', 'position' => 'IT-STAFF', 'salary' => 2000000, 'user_name' => 'Gina Fernandez (IT Staff)'],
        ];

        $this->createEmployeesWithUsers($employees, 'IT');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // EXECUTIVE DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedExecutiveEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ EXEC Department ─────────────────────────────────────────────');

        $employees = [
            ['code' => 'EMP-COMP-EXEC-001', 'first_name' => 'Antonio', 'middle_name' => 'Cruz', 'last_name' => 'Garcia', 'email' => 'executive@ogamierp.local', 'role' => 'executive', 'position' => 'PRES', 'salary' => 15000000, 'user_name' => 'Antonio Garcia (President)'],
            ['code' => 'EMP-COMP-EXEC-002', 'first_name' => 'Victoria', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'email' => 'vp@ogamierp.local', 'role' => 'vice_president', 'position' => 'VP', 'salary' => 12000000, 'user_name' => 'Victoria Santos (VP)'],
        ];

        $this->createEmployeesWithUsers($employees, 'EXEC');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════════════

    private function createEmployeesWithUsers(array $employees, string $deptCode): void
    {
        $deptId = \DB::table('departments')->where('code', $deptCode)->value('id');

        if (! $deptId) {
            $this->command->error("  ✗ Department {$deptCode} not found");

            return;
        }

        foreach ($employees as $emp) {
            $govIds = \Database\Seeders\Helpers\GovernmentIdHelper::generateCompleteGovIds();
            $bankDetails = \Database\Seeders\Helpers\GovernmentIdHelper::generateBankDetails($emp['first_name'], $emp['last_name']);

            // Create or update employee with all required fields
            $employee = Employee::firstOrCreate(
                ['employee_code' => $emp['code']],
                [
                    'first_name' => $emp['first_name'],
                    'middle_name' => $emp['middle_name'] ?? null,
                    'last_name' => $emp['last_name'],
                    'department_id' => $deptId,
                    'date_of_birth' => '1985-06-15',
                    'gender' => 'M',
                    'civil_status' => 'SINGLE',
                    'qualified_dependents' => 0,
                    'bir_status' => 'S',
                    'employment_type' => 'regular',
                    'employment_status' => 'active',
                    'date_hired' => now()->subYears(2),
                    'pay_basis' => 'monthly',
                    'basic_monthly_rate' => $emp['salary'] ?? 2000000,
                    'is_minimum_wage_earner' => false,
                    'is_active' => true,
                    'onboarding_status' => 'active',
                    // Government IDs
                    'sss_no_encrypted' => $govIds['sss_no_encrypted'],
                    'sss_no_hash' => $govIds['sss_no_hash'],
                    'tin_encrypted' => $govIds['tin_encrypted'],
                    'tin_hash' => $govIds['tin_hash'],
                    'philhealth_no_encrypted' => $govIds['philhealth_no_encrypted'],
                    'philhealth_no_hash' => $govIds['philhealth_no_hash'],
                    'pagibig_no_encrypted' => $govIds['pagibig_no_encrypted'],
                    'pagibig_no_hash' => $govIds['pagibig_no_hash'],
                    // Bank details
                    'bank_name' => $bankDetails['bank_name'],
                    'bank_account_no' => $bankDetails['bank_account_number'],
                    'bank_account_name' => $bankDetails['bank_account_name'],
                ]
            );

            // Generate password
            $password = $emp['password'] ?? $this->generatePassword($emp['role']);

            // Create or update user (always sync password so re-seeding doesn't break logins)
            $user = User::updateOrCreate(
                ['email' => $emp['email']],
                [
                    'name' => $emp['user_name'],
                    'password' => $password,
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                    'department_id' => $deptId,
                ]
            );

            $user->syncRoles([$emp['role']]);

            // Link employee to user
            $employee->user_id = $user->id;
            $employee->save();

            // Link user to employee
            $user->employee_id = $employee->id;
            $user->save();

            // Add department access
            DB::table('user_department_access')->insertOrIgnore([
                'user_id' => $user->id,
                'department_id' => $deptId,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign default shift and baseline leave balances
            \Database\Seeders\Helpers\EmployeeContextHelper::assignDefaultShift($employee->id);
            \Database\Seeders\Helpers\EmployeeContextHelper::allocateLeaveBalances($employee->id);

            $this->command->info("  ✓ {$emp['code']}: {$emp['email']} / {$password}");
        }
    }

    private function generatePassword(string $role): string
    {
        $rolePasswords = [
            'officer' => 'Officer@Test1234!',
            'manager' => 'Manager@Test1234!',
            'head' => 'Head@Test1234!',
            'staff' => 'Staff@Test1234!',
            'executive' => 'Executive@Test1234!',
            'vice_president' => 'Vice_president@Test1234!',
        ];

        return $rolePasswords[$role] ?? ucfirst($role).'@Test1234!';
    }

    private function printAccountSummary(): void
    {
        // Admin and SuperAdmin accounts (system-level, not linked to employees)
        $this->command->info('');
        $this->command->info('─ SYSTEM ADMIN ACCOUNTS ───────────────────────────────────────');
        $this->command->info('  ✓ admin@ogamierp.local / Admin@1234567890!');
        $this->command->info('  ✓ superadmin@ogamierp.local / SuperAdmin@12345! (ALL ACCESS + SoD BYPASS)');

        $accounts = [
            ['**ADMIN**', 'admin@ogamierp.local', 'Admin@1234567890!', 'System Admin', 'N/A'],
            ['**SUPER**', 'superadmin@ogamierp.local', 'SuperAdmin@12345!', 'Super Admin (ALL)', 'N/A'],
            ['HR', 'hr.officer@ogamierp.local', 'Officer@Test1234!', 'HR Officer', 'EMP-COMP-HR-001'],
            ['HR', 'hr.manager@ogamierp.local', 'Manager@Test1234!', 'HR Manager', 'EMP-COMP-HR-002'],
            ['HR', 'hr.head@ogamierp.local', 'Head@Test1234!', 'HR Head', 'EMP-COMP-HR-003'],
            ['HR', 'hr.staff@ogamierp.local', 'Staff@Test1234!', 'HR Staff', 'EMP-COMP-HR-004'],
            ['ACCTG', 'acctg.officer@ogamierp.local', 'Officer@Test1234!', 'Accounting Officer', 'EMP-COMP-ACCT-001'],
            ['ACCTG', 'acctg.manager@ogamierp.local', 'Manager@Test1234!', 'Accounting Manager', 'EMP-COMP-ACCT-002'],
            ['ACCTG', 'acctg.head@ogamierp.local', 'Head@Test1234!', 'Accounting Head', 'EMP-COMP-ACCT-003'],
            ['ACCTG', 'acctg.staff@ogamierp.local', 'Staff@Test1234!', 'Accounting Staff', 'EMP-COMP-ACCT-004'],
            ['PROD', 'prod.officer@ogamierp.local', 'Officer@Test1234!', 'Production Officer', 'EMP-COMP-PROD-001'],
            ['PROD', 'prod.manager@ogamierp.local', 'Manager@Test1234!', 'Production Manager', 'EMP-COMP-PROD-002'],
            ['PROD', 'prod.head@ogamierp.local', 'Head@Test1234!', 'Production Head', 'EMP-COMP-PROD-003'],
            ['PROD', 'prod.staff@ogamierp.local', 'Staff@Test1234!', 'Production Staff', 'EMP-COMP-PROD-004'],
            ['QC', 'qc.officer@ogamierp.local', 'Officer@Test1234!', 'QC Officer', 'EMP-COMP-QC-001'],
            ['QC', 'qc.manager@ogamierp.local', 'Manager@Test1234!', 'QC Manager', 'EMP-COMP-QC-002'],
            ['QC', 'qc.head@ogamierp.local', 'Head@Test1234!', 'QC Head', 'EMP-COMP-QC-003'],
            ['QC', 'qc.staff@ogamierp.local', 'Staff@Test1234!', 'QC Staff', 'EMP-COMP-QC-004'],
            ['MOLD', 'mold.officer@ogamierp.local', 'Officer@Test1234!', 'Mold Officer', 'EMP-COMP-MOLD-001'],
            ['MOLD', 'mold.manager@ogamierp.local', 'Manager@Test1234!', 'Mold Manager', 'EMP-COMP-MOLD-002'],
            ['MOLD', 'mold.head@ogamierp.local', 'Head@Test1234!', 'Mold Head', 'EMP-COMP-MOLD-003'],
            ['MOLD', 'mold.staff@ogamierp.local', 'Staff@Test1234!', 'Mold Staff', 'EMP-COMP-MOLD-004'],
            ['PLANT', 'plant.officer@ogamierp.local', 'Officer@Test1234!', 'Plant Officer', 'EMP-COMP-PLANT-001'],
            ['PLANT', 'plant.manager@ogamierp.local', 'Manager@Test1234!', 'Plant Manager', 'EMP-COMP-PLANT-002'],
            ['PLANT', 'plant.head@ogamierp.local', 'Head@Test1234!', 'Plant Head', 'EMP-COMP-PLANT-003'],
            ['PLANT', 'plant.staff@ogamierp.local', 'Staff@Test1234!', 'Plant Staff', 'EMP-COMP-PLANT-004'],
            ['WH', 'wh.officer@ogamierp.local', 'Officer@Test1234!', 'Warehouse Officer', 'EMP-COMP-WH-001'],
            ['WH', 'wh.manager@ogamierp.local', 'Manager@Test1234!', 'Warehouse Manager', 'EMP-COMP-WH-002'],
            ['WH', 'wh.head@ogamierp.local', 'Head@Test1234!', 'Warehouse Head', 'EMP-COMP-WH-003'],
            ['WH', 'wh.staff@ogamierp.local', 'Staff@Test1234!', 'Warehouse Staff', 'EMP-COMP-WH-004'],
            ['PPC', 'ppc.officer@ogamierp.local', 'Officer@Test1234!', 'PPC Officer', 'EMP-COMP-PPC-001'],
            ['PPC', 'ppc.manager@ogamierp.local', 'Manager@Test1234!', 'PPC Manager', 'EMP-COMP-PPC-002'],
            ['PPC', 'ppc.head@ogamierp.local', 'Head@Test1234!', 'PPC Head', 'EMP-COMP-PPC-003'],
            ['PPC', 'ppc.staff@ogamierp.local', 'Staff@Test1234!', 'PPC Staff', 'EMP-COMP-PPC-004'],
            ['MAINT', 'maint.officer@ogamierp.local', 'Officer@Test1234!', 'Maintenance Officer', 'EMP-COMP-MAINT-001'],
            ['MAINT', 'maint.manager@ogamierp.local', 'Manager@Test1234!', 'Maintenance Manager', 'EMP-COMP-MAINT-002'],
            ['MAINT', 'maint.head@ogamierp.local', 'Head@Test1234!', 'Maintenance Head', 'EMP-COMP-MAINT-003'],
            ['MAINT', 'maint.staff@ogamierp.local', 'Staff@Test1234!', 'Maintenance Staff', 'EMP-COMP-MAINT-004'],
            ['ISO', 'iso.officer@ogamierp.local', 'Officer@Test1234!', 'ISO Officer', 'EMP-COMP-ISO-001'],
            ['ISO', 'iso.manager@ogamierp.local', 'Manager@Test1234!', 'ISO Manager', 'EMP-COMP-ISO-002'],
            ['ISO', 'iso.head@ogamierp.local', 'Head@Test1234!', 'ISO Head', 'EMP-COMP-ISO-003'],
            ['ISO', 'iso.staff@ogamierp.local', 'Staff@Test1234!', 'ISO Staff', 'EMP-COMP-ISO-004'],
            ['PURCH', 'purch.officer@ogamierp.local', 'Officer@Test1234!', 'Purchasing Officer', 'EMP-COMP-PURCH-001'],
            ['PURCH', 'purch.manager@ogamierp.local', 'Manager@Test1234!', 'Purchasing Manager', 'EMP-COMP-PURCH-002'],
            ['PURCH', 'purch.head@ogamierp.local', 'Head@Test1234!', 'Purchasing Head', 'EMP-COMP-PURCH-003'],
            ['PURCH', 'purch.staff@ogamierp.local', 'Staff@Test1234!', 'Purchasing Staff', 'EMP-COMP-PURCH-004'],
            ['SALES', 'sales.officer@ogamierp.local', 'Officer@Test1234!', 'Sales Officer', 'EMP-COMP-SALES-001'],
            ['SALES', 'sales.manager@ogamierp.local', 'Manager@Test1234!', 'Sales Manager', 'EMP-COMP-SALES-002'],
            ['SALES', 'sales.head@ogamierp.local', 'Head@Test1234!', 'Sales Head', 'EMP-COMP-SALES-003'],
            ['SALES', 'sales.staff@ogamierp.local', 'Staff@Test1234!', 'Sales Staff', 'EMP-COMP-SALES-004'],
            ['IT', 'it.officer@ogamierp.local', 'Officer@Test1234!', 'IT Officer', 'EMP-COMP-IT-001'],
            ['IT', 'it.manager@ogamierp.local', 'Manager@Test1234!', 'IT Manager', 'EMP-COMP-IT-002'],
            ['IT', 'it.head@ogamierp.local', 'Head@Test1234!', 'IT Head', 'EMP-COMP-IT-003'],
            ['IT', 'it.staff@ogamierp.local', 'Staff@Test1234!', 'IT Staff', 'EMP-COMP-IT-004'],
            ['EXEC', 'executive@ogamierp.local', 'Executive@Test1234!', 'Executive', 'EMP-COMP-EXEC-001'],
            ['EXEC', 'vp@ogamierp.local', 'Vice_president@Test1234!', 'VP', 'EMP-COMP-EXEC-002'],
        ];

        $this->command->table(
            ['Dept', 'Email', 'Password', 'Role', 'Code'],
            $accounts
        );

        $this->command->info('');
        $this->command->info('Total accounts: '.(count($accounts) + 2).' (54 department + 2 system admin)');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  SPECIAL ACCOUNTS');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  admin@ogamierp.local');
        $this->command->info('    Role: admin');
        $this->command->info('    Pass: Admin@1234567890!');
        $this->command->info('    Access: System settings, user management, reference tables');
        $this->command->info('');
        $this->command->info('  superadmin@ogamierp.local');
        $this->command->info('    Role: super_admin');
        $this->command->info('    Pass: SuperAdmin@12345!');
        $this->command->info('    Access: ALL PERMISSIONS (all modules + features)');
        $this->command->info('    Bypass: SoD checks, workflow approvals, department restrictions');
        $this->command->info('    Use: Demo/testing - can do ANYTHING in the system');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('Password Pattern: {Role}@Test1234! (first letter capitalized)');
        $this->command->info('Hierarchy: Officer (full) → Manager (oversight) → Head (team lead) → Staff (basic)');
    }
}
