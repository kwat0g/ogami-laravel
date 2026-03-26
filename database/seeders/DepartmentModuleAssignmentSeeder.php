<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Assigns module keys to departments for RBAC v2.
 *
 * This seeder must run AFTER:
 *   - ModuleSeeder (creates the modules)
 *   - DepartmentPositionSeeder (creates the departments)
 *
 * Maps departments to their primary functional module:
 *   HR → hr module (HR, payroll, attendance)
 *   PROD → production module (production, QC, maintenance)
 *   ACCTG → accounting module (GL, AP, AR)
 *   etc.
 */
class DepartmentModuleAssignmentSeeder extends Seeder
{
    /**
     * Department to module mapping.
     */
    private const DEPARTMENT_MODULE_MAP = [
        'HR' => 'hr',
        'IT' => 'operations',
        'ACCTG' => 'accounting',
        'PROD' => 'production',
        'SALES' => 'sales',
        'EXEC' => 'hr',           // Executive uses hr module for basic access
        'PLANT' => 'production',
        'QC' => 'production',
        'MOLD' => 'production',
        'WH' => 'warehouse',
        'PPC' => 'production',
        'MAINT' => 'production',
        'ISO' => 'operations',
        'PURCH' => 'purchasing',
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENT_MODULE_MAP as $deptCode => $moduleKey) {
            DB::table('departments')
                ->where('code', $deptCode)
                ->update(['module_key' => $moduleKey]);
        }

        $count = count(self::DEPARTMENT_MODULE_MAP);
        $this->command->info("✓ Department module assignments: {$count} departments linked to modules.");

        foreach (self::DEPARTMENT_MODULE_MAP as $deptCode => $moduleKey) {
            $this->command->info("  - {$deptCode} → {$moduleKey}");
        }
    }
}
