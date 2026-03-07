<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds all departments and positions needed for the application.
 *
 * Departments:
 *   HR     — Human Resources
 *   IT     — Information Technology
 *   ACCTG  — Accounting and Finance
 *   PROD   — Production
 *   SALES  — Sales & Marketing
 *
 * Positions:
 *   HR-MGR      — HR Manager
 *   IT-ADMIN    — IT Admin
 *   ACCT-MGR    — Accounting Manager
 *   ACCT-CLK    — Accounting Clerk
 *   PROD-MGR    — Production Manager
 *   PROD-SUP    — Production Supervisor
 *   PROD-OP     — Production Operator
 *   SALES-MGR   — Sales Manager
 *   SALES-REP   — Sales Representative
 */
class DepartmentPositionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Seed all departments first ───────────────────────────────────────
        $departments = [
            [
                'code' => 'HR',
                'name' => 'Human Resources',
                'cost_center_code' => 'CC-001',
                'is_active' => true,
            ],
            [
                'code' => 'IT',
                'name' => 'Information Technology',
                'cost_center_code' => 'CC-002',
                'is_active' => true,
            ],
            [
                'code' => 'ACCTG',
                'name' => 'Accounting and Finance',
                'cost_center_code' => 'CC-003',
                'is_active' => true,
            ],
            [
                'code' => 'PROD',
                'name' => 'Production',
                'cost_center_code' => 'CC-004',
                'is_active' => true,
            ],
            [
                'code' => 'SALES',
                'name' => 'Sales & Marketing',
                'cost_center_code' => 'CC-005',
                'is_active' => true,
            ],
            // ── New manufacturing departments ─────────────────────────────────
            [
                'code' => 'EXEC',
                'name' => 'Executive Management',
                'cost_center_code' => 'CC-006',
                'is_active' => true,
            ],
            [
                'code' => 'PLANT',
                'name' => 'Plant Operations',
                'cost_center_code' => 'CC-007',
                'is_active' => true,
            ],
            [
                'code' => 'QC',
                'name' => 'Quality Control & Assurance',
                'cost_center_code' => 'CC-008',
                'is_active' => true,
            ],
            [
                'code' => 'MOLD',
                'name' => 'Mold Department',
                'cost_center_code' => 'CC-009',
                'is_active' => true,
            ],
            [
                'code' => 'WH',
                'name' => 'Warehouse & Logistics',
                'cost_center_code' => 'CC-010',
                'is_active' => true,
            ],
            [
                'code' => 'PPC',
                'name' => 'Production Planning & Control',
                'cost_center_code' => 'CC-011',
                'is_active' => true,
            ],
            [
                'code' => 'MAINT',
                'name' => 'Maintenance',
                'cost_center_code' => 'CC-012',
                'is_active' => true,
            ],
            [
                'code' => 'ISO',
                'name' => 'Management Systems & ISO',
                'cost_center_code' => 'CC-013',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->insertOrIgnore([
                'code' => $dept['code'],
                'name' => $dept['name'],
                'cost_center_code' => $dept['cost_center_code'],
                'is_active' => $dept['is_active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Re-fetch inserted IDs by code
        $deptIds = DB::table('departments')
            ->whereIn('code', ['HR', 'IT', 'ACCTG', 'PROD', 'SALES',
                               'EXEC', 'PLANT', 'QC', 'MOLD', 'WH', 'PPC', 'MAINT', 'ISO'])
            ->pluck('id', 'code');

        // ── Seed all positions ───────────────────────────────────────────────
        $positions = [
            // HR
            ['HR-MGR',    'HR Manager',             'HR',    'SG-10'],
            ['HR-SUP',    'HR Supervisor',           'HR',    'SG-07'],
            ['HR-ASST',   'HR Assistant',           'HR',    'SG-05'],
            // IT
            ['IT-ADMIN',  'IT Administrator',       'IT',    'SG-10'],
            ['IT-SUPP',   'IT Support',             'IT',    'SG-06'],
            // Accounting
            ['ACCT-MGR',  'Accounting Manager',     'ACCTG', 'SG-11'],
            ['ACCT-OFF',  'Accounting Officer',     'ACCTG', 'SG-10'],
            ['ACCT-CLK',  'Accounting Clerk',       'ACCTG', 'SG-06'],
            ['ACCT-ANL',  'Financial Analyst',      'ACCTG', 'SG-08'],
            // Production
            ['PROD-MGR',  'Production Manager',     'PROD',  'SG-12'],
            ['PROD-SUP',  'Production Supervisor',  'PROD',  'SG-08'],
            ['PROD-OP',   'Production Operator',    'PROD',  'SG-05'],
            // Sales
            ['SALES-MGR', 'Sales Manager',          'SALES', 'SG-11'],
            ['SALES-REP', 'Sales Representative',   'SALES', 'SG-07'],
            // Executive Management
            ['CHAIRMAN',  'Chairman',                'EXEC',  'SG-15'],
            ['PRESIDENT', 'President',               'EXEC',  'SG-15'],
            ['VP',        'Vice President',          'EXEC',  'SG-14'],
            // Plant Operations
            ['PLANT-MGR', 'Plant Manager',           'PLANT', 'SG-13'],
            // Production (new positions alongside existing)
            ['PROD-HEAD', 'Production Head',         'PROD',  'SG-09'],
            ['PROC-HEAD', 'Processing Head',         'PROD',  'SG-09'],
            // QC / QA
            ['QC-MGR',   'QC/QA Manager',            'QC',    'SG-12'],
            ['QC-HEAD',  'QC/QA Head',               'QC',    'SG-09'],
            ['QC-STAFF', 'QC Inspector',             'QC',    'SG-05'],
            // Mold
            ['MOLD-MGR', 'Mold Manager',             'MOLD',  'SG-12'],
            ['MOLD-HEAD','Mold Head',                'MOLD',  'SG-09'],
            ['MOLD-TECH','Mold Technician',          'MOLD',  'SG-06'],
            // Warehouse / Logistics
            ['WH-HEAD',  'Warehouse Head',           'WH',    'SG-08'],
            ['WH-STAFF', 'Warehouse Staff',          'WH',    'SG-05'],
            // PPC
            ['PPC-HEAD', 'PPC Head',                 'PPC',   'SG-09'],
            ['PPC-STAFF','PPC Staff',                'PPC',   'SG-05'],
            // Maintenance
            ['MAINT-HEAD','Maintenance Head',        'MAINT', 'SG-09'],
            ['MAINT-TECH','Maintenance Technician',  'MAINT', 'SG-06'],
            // ISO / Management Systems
            ['ISO-HEAD', 'Management System Head',   'ISO',   'SG-09'],
            ['ISO-STAFF','Management System Staff',  'ISO',   'SG-05'],
            // Additional Accounting/Admin officers
            ['GA-OFF',   'General Administration Officer', 'HR',    'SG-10'],
            ['PURCH-OFF','Purchasing Officer',       'ACCTG', 'SG-10'],
            ['IMPEX-OFF','Import/Export Officer',    'ACCTG', 'SG-10'],
        ];

        foreach ($positions as [$code, $title, $deptCode, $payGrade]) {
            $deptId = $deptIds[$deptCode] ?? null;
            if (! $deptId) {
                continue;
            }

            DB::table('positions')->insertOrIgnore([
                'code' => $code,
                'title' => $title,
                'department_id' => $deptId,
                'pay_grade' => $payGrade,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ Departments (13: HR, IT, ACCTG, PROD, SALES, EXEC, PLANT, QC, MOLD, WH, PPC, MAINT, ISO) seeded.');
        $this->command->info('✓ Positions (40+) seeded for all departments.');
    }
}
