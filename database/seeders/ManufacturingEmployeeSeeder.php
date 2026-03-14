<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds all employees and user accounts for the manufacturing roles defined in
 * the organisational chart (changes.md):
 *
 * Executive Management
 *   Chairman            → executive         chairman@ogamierp.local
 *   President           → executive         president@ogamierp.local
 *   Vice President      → vice_president    vp@ogamierp.local
 *
 * Department Managers
 *   HR Manager          → manager           (already seeded by SampleDataSeeder)
 *   Plant Manager       → plant_manager       plant.manager@ogamierp.local
 *   Production Manager  → production_manager  prod.manager@ogamierp.local
 *   QC/QA Manager       → qc_manager          qc.manager@ogamierp.local
 *   Mold Manager        → mold_manager        mold.manager@ogamierp.local
 *
 * Officers
 *   Accounting Officer  → officer           (already seeded by SampleDataSeeder)
 *   GA Officer          → ga_officer         ga.officer@ogamierp.local
 *   Purchasing Officer  → purchasing_officer  purchasing.officer@ogamierp.local
 *   ImpEx Officer       → impex_officer       impex.officer@ogamierp.local
 *
 * Department Heads
 *   Warehouse Head      → head              warehouse.head@ogamierp.local
 *   PPC Head            → head              ppc.head@ogamierp.local
 *   Maintenance Head    → head              maintenance.head@ogamierp.local
 *   Production Head     → head              production.head@ogamierp.local
 *   Processing Head     → head              processing.head@ogamierp.local
 *   QC/QA Head          → head              qcqa.head@ogamierp.local
 *   Management System Head → head           iso.head@ogamierp.local
 *
 * Prerequisites: DepartmentPositionSeeder, SalaryGradeSeeder, RolePermissionSeeder
 */
class ManufacturingEmployeeSeeder extends Seeder
{
    private const DEFAULT_SHIFT_START = '08:00:00';

    public function run(): void
    {
        $this->seedEmployees();
        $this->seedUserAccounts();
        $this->seedUserEmployeeLinks();
        $this->seedMultiDepartmentAccess();
        $this->seedShiftAssignments();

        $this->command->info('✓ Manufacturing employee accounts seeded.');
        $this->command->table(
            ['Email', 'Password', 'Role', 'Position'],
            $this->getSeedTable()
        );
    }

    // ── Employee master records ───────────────────────────────────────────────

    private function seedEmployees(): void
    {
        $employees = $this->getEmployeeData();

        foreach ($employees as $emp) {
            $deptId = DB::table('departments')->where('code', $emp['dept'])->value('id');
            $posId  = DB::table('positions')->where('code', $emp['pos'])->value('id');
            $sgId   = DB::table('salary_grades')->where('code', $emp['sg'])->value('id');

            if (! $deptId || ! $posId) {
                $this->command->warn("  Skipping {$emp['code']} — dept '{$emp['dept']}' or pos '{$emp['pos']}' not found.");
                continue;
            }

            DB::table('employees')->insertOrIgnore([
                'employee_code'       => $emp['code'],
                'ulid'                => (string) Str::ulid(),
                'first_name'          => $emp['first_name'],
                'last_name'           => $emp['last_name'],
                'date_of_birth'       => $emp['dob'],
                'gender'              => $emp['gender'],
                'civil_status'        => $emp['civil_status'],
                'citizenship'         => 'Filipino',
                'present_address'     => $emp['address'],
                'permanent_address'   => $emp['address'],
                'qualified_dependents'=> $emp['dependents'],
                'bir_status'          => $emp['bir_status'],
                'personal_email'      => $emp['personal_email'],
                'personal_phone'      => $emp['phone'],
                'bank_name'           => $emp['bank_name'],
                'bank_account_no'     => $emp['bank_account_no'],
                'bank_account_name'   => $emp['first_name'].' '.$emp['last_name'],
                'department_id'       => $deptId,
                'position_id'         => $posId,
                'salary_grade_id'     => $sgId,
                'employment_type'     => 'regular',
                'employment_status'   => 'active',
                'date_hired'          => $emp['hired'],
                'basic_monthly_rate'  => $emp['salary'],
                'onboarding_status'   => 'active',
                'is_active'           => true,
                'pay_basis'           => 'monthly',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // Activate employee via model (sets encrypted gov IDs)
            $employee = Employee::where('employee_code', $emp['code'])->first();
            if ($employee && ! $employee->sss_no_encrypted) {
                $employee->setSssNo($emp['sss']);
                $employee->setTin($emp['tin']);
                $employee->setPhilhealthNo($emp['philhealth']);
                $employee->setPagibigNo($emp['pagibig']);
                $employee->onboarding_status       = 'active';
                $employee->is_active               = true;
                $employee->_fire_activated_event   = true;
                $employee->save();
            }
        }
    }

    // ── User accounts ─────────────────────────────────────────────────────────

    private function seedUserAccounts(): void
    {
        foreach ($this->getUserAccountData() as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'               => $account['name'],
                    'password'           => $account['password'],
                    'email_verified_at'  => now(),
                    'password_changed_at'=> now(),
                ]
            );
            $user->syncRoles([$account['role']]);
        }
    }

    // ── User ↔ Employee links ─────────────────────────────────────────────────

    private function seedUserEmployeeLinks(): void
    {
        foreach ($this->getLinkData() as $link) {
            $user     = DB::table('users')->where('email', $link['email'])->first();
            $employee = DB::table('employees')->where('employee_code', $link['code'])->first();

            if (! $user || ! $employee) {
                $this->command->warn("  Link skip: {$link['email']} or {$link['code']} not found.");
                continue;
            }

            DB::table('employees')
                ->where('id', $employee->id)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            DB::table('users')
                ->where('id', $user->id)
                ->whereNull('department_id')
                ->update(['department_id' => $employee->department_id]);

            DB::table('user_department_access')->insertOrIgnore([
                'user_id'       => $user->id,
                'department_id' => $employee->department_id,
                'is_primary'    => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    // ── Shift assignments ─────────────────────────────────────────────────────

    private function seedShiftAssignments(): void
    {
        $shift = DB::table('shift_schedules')
            ->where('start_time', self::DEFAULT_SHIFT_START)
            ->where('is_active', true)
            ->first();

        if (! $shift) {
            $this->command->warn('  Shift assignments skipped — regular shift not found.');
            return;
        }

        $assignedBy = DB::table('users')->first()?->id ?? 1;

        foreach ($this->getEmployeeData() as $emp) {
            $employee = DB::table('employees')->where('employee_code', $emp['code'])->first();
            if (! $employee) {
                continue;
            }

            $exists = DB::table('employee_shift_assignments')
                ->where('employee_id', $employee->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('employee_shift_assignments')->insert([
                'employee_id'       => $employee->id,
                'shift_schedule_id' => $shift->id,
                'effective_from'    => $employee->date_hired,
                'effective_to'      => null,
                'notes'             => 'Initial shift (manufacturing seeder)',
                'assigned_by'       => $assignedBy,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    // ── Multi-department access ──────────────────────────────────────────

    /**
     * Grant cross-department visibility to roles that need it.
     *
     * - Vice President: needs ALL departments so the approvals queue surfaces
     *   requests from every department (vice_president is NOT auto-exempt from
     *   dept_scope middleware — only executive/admin/super_admin bypass it).
     *
     * - Plant Manager: oversees Production, QC, Mold, Warehouse, PPC,
     *   Maintenance and ISO — all plant operations departments.
     *
     * Uses insertOrIgnore so this is safe to re-run on existing data.
     */
    private function seedMultiDepartmentAccess(): void
    {
        // All 13 department codes
        $allDeptCodes = ['HR', 'IT', 'ACCTG', 'PROD', 'SALES', 'EXEC', 'PLANT', 'QC', 'MOLD', 'WH', 'PPC', 'MAINT', 'ISO'];

        // Plant-operations departments (Plant Manager scope)
        $plantDeptCodes = ['PROD', 'QC', 'MOLD', 'WH', 'PPC', 'MAINT', 'ISO'];

        $deptIds = DB::table('departments')
            ->whereIn('code', $allDeptCodes)
            ->pluck('id', 'code');

        $grants = [
            'vp@ogamierp.local'           => $allDeptCodes,
            'plant.manager@ogamierp.local' => $plantDeptCodes,
        ];

        $grantCount = 0;
        foreach ($grants as $email => $deptCodes) {
            $userId = DB::table('users')->where('email', $email)->value('id');
            if (! $userId) {
                $this->command->warn("  Multi-dept skip: {$email} not found.");
                continue;
            }

            foreach ($deptCodes as $code) {
                $deptId = $deptIds[$code] ?? null;
                if (! $deptId) {
                    continue;
                }

                $inserted = DB::table('user_department_access')->insertOrIgnore([
                    'user_id'       => $userId,
                    'department_id' => $deptId,
                    'is_primary'    => false,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $grantCount += $inserted;
            }
        }

        $this->command->info("\u2713 Multi-department access granted ({$grantCount} new entries).");
        $this->command->info('  vp@ogamierp.local         → all 13 departments');
        $this->command->info('  plant.manager@ogamierp.local → PROD, QC, MOLD, WH, PPC, MAINT, ISO');
    }

    // ── Data tables ───────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function getEmployeeData(): array
    {
        return [
            // ── Executive Management ──────────────────────────────────────────
            [
                'code' => 'EMP-2026-0006', 'first_name' => 'Roberto',     'last_name' => 'Ogami',
                'dob' => '1960-04-10', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 3,
                'bir_status' => 'ME3', 'personal_email' => 'roberto.ogami@email.com', 'phone' => '09171110001',
                'address' => '1 Executive Tower, BGC, Taguig City',
                'dept' => 'EXEC', 'pos' => 'CHAIRMAN', 'sg' => 'SG-15',
                'hired' => '2010-01-04', 'salary' => 45000000,
                'sss' => '33-0000001-1', 'tin' => '600-000-001-000',
                'philhealth' => '01-000000001-1', 'pagibig' => '0001-0001-0001',
                'bank_name' => 'BPI', 'bank_account_no' => '8000000001',
            ],
            [
                'code' => 'EMP-2026-0007', 'first_name' => 'Eduardo',     'last_name' => 'Ogami',
                'dob' => '1965-08-22', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 2,
                'bir_status' => 'ME2', 'personal_email' => 'eduardo.ogami@email.com', 'phone' => '09171110002',
                'address' => '1 Executive Tower, BGC, Taguig City',
                'dept' => 'EXEC', 'pos' => 'PRESIDENT', 'sg' => 'SG-15',
                'hired' => '2010-01-04', 'salary' => 45000000,
                'sss' => '33-0000002-2', 'tin' => '600-000-002-000',
                'philhealth' => '01-000000002-2', 'pagibig' => '0002-0002-0002',
                'bank_name' => 'BDO', 'bank_account_no' => '8000000002',
            ],
            [
                'code' => 'EMP-2026-0008', 'first_name' => 'Lorenzo',     'last_name' => 'Ogami',
                'dob' => '1972-12-05', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 2,
                'bir_status' => 'ME2', 'personal_email' => 'lorenzo.ogami@email.com', 'phone' => '09171110003',
                'address' => '2 Skyline Residences, Makati City',
                'dept' => 'EXEC', 'pos' => 'VP', 'sg' => 'SG-14',
                'hired' => '2012-06-01', 'salary' => 35000000,
                'sss' => '33-0000003-3', 'tin' => '600-000-003-000',
                'philhealth' => '01-000000003-3', 'pagibig' => '0003-0003-0003',
                'bank_name' => 'UnionBank', 'bank_account_no' => '8000000003',
            ],
            // ── Department Managers ───────────────────────────────────────────
            [
                'code' => 'EMP-2026-0009', 'first_name' => 'Carlos',      'last_name' => 'Reyes',
                'dob' => '1978-03-15', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 2,
                'bir_status' => 'ME2', 'personal_email' => 'carlos.reyes@email.com', 'phone' => '09181110001',
                'address' => '45 Industrial Ave., Cabuyao, Laguna',
                'dept' => 'PLANT', 'pos' => 'PLANT-MGR', 'sg' => 'SG-13',
                'hired' => '2015-03-01', 'salary' => 28000000,
                'sss' => '33-1000001-1', 'tin' => '700-001-001-000',
                'philhealth' => '01-100000001-1', 'pagibig' => '1001-1001-1001',
                'bank_name' => 'BDO', 'bank_account_no' => '7001000001',
            ],
            [
                'code' => 'EMP-2026-0010', 'first_name' => 'Renaldo',     'last_name' => 'Mendoza',
                'dob' => '1981-07-20', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 1,
                'bir_status' => 'ME1', 'personal_email' => 'renaldo.mendoza@email.com', 'phone' => '09181110002',
                'address' => '12 Sampaguita St., Biñan, Laguna',
                'dept' => 'PROD', 'pos' => 'PROD-MGR', 'sg' => 'SG-12',
                'hired' => '2017-08-15', 'salary' => 22000000,
                'sss' => '33-1000002-2', 'tin' => '700-001-002-000',
                'philhealth' => '01-100000002-2', 'pagibig' => '1002-1002-1002',
                'bank_name' => 'Metrobank', 'bank_account_no' => '7001000002',
            ],
            [
                'code' => 'EMP-2026-0011', 'first_name' => 'Josephine',   'last_name' => 'Villanueva',
                'dob' => '1983-01-30', 'gender' => 'female', 'civil_status' => 'SINGLE', 'dependents' => 0,
                'bir_status' => 'S', 'personal_email' => 'josephine.villanueva@email.com', 'phone' => '09181110003',
                'address' => '8 Orchid Lane, Sta. Rosa, Laguna',
                'dept' => 'QC', 'pos' => 'QC-MGR', 'sg' => 'SG-12',
                'hired' => '2016-02-01', 'salary' => 22000000,
                'sss' => '33-1000003-3', 'tin' => '700-001-003-000',
                'philhealth' => '01-100000003-3', 'pagibig' => '1003-1003-1003',
                'bank_name' => 'BPI', 'bank_account_no' => '7001000003',
            ],
            [
                'code' => 'EMP-2026-0012', 'first_name' => 'Victor',      'last_name' => 'Castillo',
                'dob' => '1979-09-14', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 2,
                'bir_status' => 'ME2', 'personal_email' => 'victor.castillo@email.com', 'phone' => '09181110004',
                'address' => '33 Marigold St., Calamba, Laguna',
                'dept' => 'MOLD', 'pos' => 'MOLD-MGR', 'sg' => 'SG-12',
                'hired' => '2018-11-01', 'salary' => 22000000,
                'sss' => '33-1000004-4', 'tin' => '700-001-004-000',
                'philhealth' => '01-100000004-4', 'pagibig' => '1004-1004-1004',
                'bank_name' => 'UnionBank', 'bank_account_no' => '7001000004',
            ],
            // ── Officers ──────────────────────────────────────────────────────
            [
                'code' => 'EMP-2026-0013', 'first_name' => 'Rachel',      'last_name' => 'Garcia',
                'dob' => '1988-06-17', 'gender' => 'female', 'civil_status' => 'SINGLE', 'dependents' => 0,
                'bir_status' => 'S', 'personal_email' => 'rachel.garcia@email.com', 'phone' => '09191110001',
                'address' => '56 Commonwealth Ave., Quezon City',
                'dept' => 'HR', 'pos' => 'GA-OFF', 'sg' => 'SG-10',
                'hired' => '2019-05-06', 'salary' => 15000000,
                'sss' => '33-2000001-1', 'tin' => '800-001-001-000',
                'philhealth' => '01-200000001-1', 'pagibig' => '2001-2001-2001',
                'bank_name' => 'BDO', 'bank_account_no' => '6001000001',
            ],
            [
                'code' => 'EMP-2026-0014', 'first_name' => 'Marlon',      'last_name' => 'Torres',
                'dob' => '1985-02-28', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 1,
                'bir_status' => 'ME1', 'personal_email' => 'marlon.torres@email.com', 'phone' => '09191110002',
                'address' => '78 Antipolo St., Cainta, Rizal',
                'dept' => 'ACCTG', 'pos' => 'PURCH-OFF', 'sg' => 'SG-10',
                'hired' => '2020-07-01', 'salary' => 15000000,
                'sss' => '33-2000002-2', 'tin' => '800-001-002-000',
                'philhealth' => '01-200000002-2', 'pagibig' => '2002-2002-2002',
                'bank_name' => 'Metrobank', 'bank_account_no' => '6001000002',
            ],
            [
                'code' => 'EMP-2026-0015', 'first_name' => 'Cristina',    'last_name' => 'Aquino',
                'dob' => '1987-11-11', 'gender' => 'female', 'civil_status' => 'SINGLE', 'dependents' => 0,
                'bir_status' => 'S', 'personal_email' => 'cristina.aquino@email.com', 'phone' => '09191110003',
                'address' => '14 Kamagong St., Parañaque City',
                'dept' => 'ACCTG', 'pos' => 'IMPEX-OFF', 'sg' => 'SG-10',
                'hired' => '2021-01-18', 'salary' => 15000000,
                'sss' => '33-2000003-3', 'tin' => '800-001-003-000',
                'philhealth' => '01-200000003-3', 'pagibig' => '2003-2003-2003',
                'bank_name' => 'BPI', 'bank_account_no' => '6001000003',
            ],
            // ── Department Heads ──────────────────────────────────────────────
            [
                'code' => 'EMP-2026-0016', 'first_name' => 'Ernesto',     'last_name' => 'Bautista',
                'dob' => '1984-05-03', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 2,
                'bir_status' => 'ME2', 'personal_email' => 'ernesto.bautista@email.com', 'phone' => '09161110001',
                'address' => '21 Narra St., Cabuyao, Laguna',
                'dept' => 'WH', 'pos' => 'WH-HEAD', 'sg' => 'SG-08',
                'hired' => '2019-09-01', 'salary' => 9000000,
                'sss' => '33-3000001-1', 'tin' => '900-001-001-000',
                'philhealth' => '01-300000001-1', 'pagibig' => '3001-3001-3001',
                'bank_name' => 'BDO', 'bank_account_no' => '5001000001',
            ],
            [
                'code' => 'EMP-2026-0017', 'first_name' => 'Jerome',      'last_name' => 'Florido',
                'dob' => '1986-10-19', 'gender' => 'male', 'civil_status' => 'SINGLE', 'dependents' => 0,
                'bir_status' => 'S', 'personal_email' => 'jerome.florido@email.com', 'phone' => '09161110002',
                'address' => '5 Lauan St., Biñan, Laguna',
                'dept' => 'PPC', 'pos' => 'PPC-HEAD', 'sg' => 'SG-09',
                'hired' => '2020-02-24', 'salary' => 12000000,
                'sss' => '33-3000002-2', 'tin' => '900-001-002-000',
                'philhealth' => '01-300000002-2', 'pagibig' => '3002-3002-3002',
                'bank_name' => 'UnionBank', 'bank_account_no' => '5001000002',
            ],
            [
                'code' => 'EMP-2026-0018', 'first_name' => 'Armando',     'last_name' => 'Dela Torre',
                'dob' => '1982-03-25', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 3,
                'bir_status' => 'ME3', 'personal_email' => 'armando.delatorre@email.com', 'phone' => '09161110003',
                'address' => '88 Acacia Ave., Sta. Rosa, Laguna',
                'dept' => 'MAINT', 'pos' => 'MAINT-HEAD', 'sg' => 'SG-09',
                'hired' => '2016-07-11', 'salary' => 12000000,
                'sss' => '33-3000003-3', 'tin' => '900-001-003-000',
                'philhealth' => '01-300000003-3', 'pagibig' => '3003-3003-3003',
                'bank_name' => 'Metrobank', 'bank_account_no' => '5001000003',
            ],
            [
                'code' => 'EMP-2026-0019', 'first_name' => 'Danilo',      'last_name' => 'Espiritu',
                'dob' => '1985-12-01', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 1,
                'bir_status' => 'ME1', 'personal_email' => 'danilo.espiritu@email.com', 'phone' => '09161110004',
                'address' => '3 Molave St., Calamba, Laguna',
                'dept' => 'PROD', 'pos' => 'PROD-HEAD', 'sg' => 'SG-09',
                'hired' => '2018-04-16', 'salary' => 12000000,
                'sss' => '33-3000004-4', 'tin' => '900-001-004-000',
                'philhealth' => '01-300000004-4', 'pagibig' => '3004-3004-3004',
                'bank_name' => 'BDO', 'bank_account_no' => '5001000004',
            ],
            [
                'code' => 'EMP-2026-0020', 'first_name' => 'Eliza',       'last_name' => 'Navarro',
                'dob' => '1990-07-07', 'gender' => 'female', 'civil_status' => 'SINGLE', 'dependents' => 0,
                'bir_status' => 'S', 'personal_email' => 'eliza.navarro@email.com', 'phone' => '09161110005',
                'address' => '17 Ilang-Ilang St., Canlubang, Laguna',
                'dept' => 'PROD', 'pos' => 'PROC-HEAD', 'sg' => 'SG-09',
                'hired' => '2019-11-04', 'salary' => 12000000,
                'sss' => '33-3000005-5', 'tin' => '900-001-005-000',
                'philhealth' => '01-300000005-5', 'pagibig' => '3005-3005-3005',
                'bank_name' => 'BPI', 'bank_account_no' => '5001000005',
            ],
            [
                'code' => 'EMP-2026-0021', 'first_name' => 'Rhodora',     'last_name' => 'Salazar',
                'dob' => '1988-09-12', 'gender' => 'female', 'civil_status' => 'MARRIED', 'dependents' => 1,
                'bir_status' => 'ME1', 'personal_email' => 'rhodora.salazar@email.com', 'phone' => '09161110006',
                'address' => '9 Jasmine St., Biñan, Laguna',
                'dept' => 'QC', 'pos' => 'QC-HEAD', 'sg' => 'SG-09',
                'hired' => '2017-03-20', 'salary' => 12000000,
                'sss' => '33-3000006-6', 'tin' => '900-001-006-000',
                'philhealth' => '01-300000006-6', 'pagibig' => '3006-3006-3006',
                'bank_name' => 'Metrobank', 'bank_account_no' => '5001000006',
            ],
            [
                'code' => 'EMP-2026-0022', 'first_name' => 'Bernard',     'last_name' => 'Pineda',
                'dob' => '1983-04-18', 'gender' => 'male', 'civil_status' => 'MARRIED', 'dependents' => 2,
                'bir_status' => 'ME2', 'personal_email' => 'bernard.pineda@email.com', 'phone' => '09161110007',
                'address' => '22 IATF Lane, Cabuyao, Laguna',
                'dept' => 'ISO', 'pos' => 'ISO-HEAD', 'sg' => 'SG-09',
                'hired' => '2020-10-05', 'salary' => 12000000,
                'sss' => '33-3000007-7', 'tin' => '900-001-007-000',
                'philhealth' => '01-300000007-7', 'pagibig' => '3007-3007-3007',
                'bank_name' => 'UnionBank', 'bank_account_no' => '5001000007',
            ],
            // ── Production Staff (self-service test account) ───────────────────
            [
                'code' => 'EMP-2026-0023', 'first_name' => 'Pedro',       'last_name' => 'dela Cruz',
                'dob' => '1995-06-15', 'gender' => 'male', 'civil_status' => 'SINGLE', 'dependents' => 0,
                'bir_status' => 'S', 'personal_email' => 'pedro.delacruz@email.com', 'phone' => '09151110001',
                'address' => '10 Rizal St., Biñan, Laguna',
                'dept' => 'PROD', 'pos' => 'PROD-OP', 'sg' => 'SG-05',
                'hired' => '2023-06-01', 'salary' => 4200000,
                'sss' => '33-4000001-1', 'tin' => '950-001-001-000',
                'philhealth' => '01-400000001-1', 'pagibig' => '4001-4001-4001',
                'bank_name' => 'BDO', 'bank_account_no' => '4001000001',
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function getUserAccountData(): array
    {
        return [
            ['email' => 'chairman@ogamierp.local',        'name' => 'Roberto Ogami',       'password' => 'Executive@12345!', 'role' => 'executive'],
            ['email' => 'president@ogamierp.local',       'name' => 'Eduardo Ogami',        'password' => 'Executive@12345!', 'role' => 'executive'],
            ['email' => 'vp@ogamierp.local',              'name' => 'Lorenzo Ogami',        'password' => 'VicePresident@1!', 'role' => 'vice_president'],
            ['email' => 'plant.manager@ogamierp.local',   'name' => 'Carlos Reyes',         'password' => 'Manager@12345!',   'role' => 'plant_manager'],
            ['email' => 'prod.manager@ogamierp.local',    'name' => 'Renaldo Mendoza',      'password' => 'Manager@12345!',   'role' => 'production_manager'],
            ['email' => 'qc.manager@ogamierp.local',      'name' => 'Josephine Villanueva', 'password' => 'Manager@12345!',   'role' => 'qc_manager'],
            ['email' => 'mold.manager@ogamierp.local',    'name' => 'Victor Castillo',      'password' => 'Manager@12345!',   'role' => 'mold_manager'],
            ['email' => 'ga.officer@ogamierp.local',      'name' => 'Rachel Garcia',        'password' => 'Officer@12345!',   'role' => 'ga_officer'],
            ['email' => 'purchasing.officer@ogamierp.local', 'name' => 'Marlon Torres',     'password' => 'Officer@12345!',   'role' => 'purchasing_officer'],
            ['email' => 'impex.officer@ogamierp.local',   'name' => 'Cristina Aquino',      'password' => 'Officer@12345!',   'role' => 'impex_officer'],
            ['email' => 'warehouse.head@ogamierp.local',  'name' => 'Ernesto Bautista',     'password' => 'Head@123456789!',  'role' => 'warehouse_head'],
            ['email' => 'ppc.head@ogamierp.local',        'name' => 'Jerome Florido',       'password' => 'Head@123456789!',  'role' => 'ppc_head'],
            ['email' => 'maintenance.head@ogamierp.local','name' => 'Armando Dela Torre',   'password' => 'Head@123456789!',  'role' => 'head'],
            ['email' => 'production.head@ogamierp.local', 'name' => 'Danilo Espiritu',      'password' => 'Head@123456789!',  'role' => 'head'],
            ['email' => 'processing.head@ogamierp.local', 'name' => 'Eliza Navarro',        'password' => 'Head@123456789!',  'role' => 'head'],
            ['email' => 'qcqa.head@ogamierp.local',       'name' => 'Rhodora Salazar',      'password' => 'Head@123456789!',  'role' => 'head'],
            ['email' => 'iso.head@ogamierp.local',        'name' => 'Bernard Pineda',       'password' => 'Head@123456789!',  'role' => 'head'],
            ['email' => 'prod.staff@ogamierp.local',        'name' => 'Pedro dela Cruz',       'password' => 'Staff@123456789!', 'role' => 'staff'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function getLinkData(): array
    {
        return [
            ['email' => 'chairman@ogamierp.local',           'code' => 'EMP-2026-0006'],
            ['email' => 'president@ogamierp.local',          'code' => 'EMP-2026-0007'],
            ['email' => 'vp@ogamierp.local',                 'code' => 'EMP-2026-0008'],
            ['email' => 'plant.manager@ogamierp.local',      'code' => 'EMP-2026-0009'],
            ['email' => 'prod.manager@ogamierp.local',       'code' => 'EMP-2026-0010'],
            ['email' => 'qc.manager@ogamierp.local',         'code' => 'EMP-2026-0011'],
            ['email' => 'mold.manager@ogamierp.local',       'code' => 'EMP-2026-0012'],
            ['email' => 'ga.officer@ogamierp.local',         'code' => 'EMP-2026-0013'],
            ['email' => 'purchasing.officer@ogamierp.local', 'code' => 'EMP-2026-0014'],
            ['email' => 'impex.officer@ogamierp.local',      'code' => 'EMP-2026-0015'],
            ['email' => 'warehouse.head@ogamierp.local',     'code' => 'EMP-2026-0016'],
            ['email' => 'ppc.head@ogamierp.local',           'code' => 'EMP-2026-0017'],
            ['email' => 'maintenance.head@ogamierp.local',   'code' => 'EMP-2026-0018'],
            ['email' => 'production.head@ogamierp.local',    'code' => 'EMP-2026-0019'],
            ['email' => 'processing.head@ogamierp.local',    'code' => 'EMP-2026-0020'],
            ['email' => 'qcqa.head@ogamierp.local',          'code' => 'EMP-2026-0021'],
            ['email' => 'iso.head@ogamierp.local',           'code' => 'EMP-2026-0022'],
            ['email' => 'prod.staff@ogamierp.local',           'code' => 'EMP-2026-0023'],
        ];
    }

    /** @return array<int, array<string>> */
    private function getSeedTable(): array
    {
        return [
            ['chairman@ogamierp.local',           'Executive@12345!', 'executive',      'Chairman'],
            ['president@ogamierp.local',          'Executive@12345!', 'executive',      'President'],
            ['vp@ogamierp.local',                 'VicePresident@1!', 'vice_president', 'Vice President'],
            ['plant.manager@ogamierp.local',      'Manager@12345!',   'plant_manager',      'Plant Manager'],
            ['prod.manager@ogamierp.local',       'Manager@12345!',   'production_manager', 'Production Manager'],
            ['qc.manager@ogamierp.local',         'Manager@12345!',   'qc_manager',         'QC/QA Manager'],
            ['mold.manager@ogamierp.local',       'Manager@12345!',   'mold_manager',       'Mold Manager'],
            ['ga.officer@ogamierp.local',         'Officer@12345!',   'ga_officer',         'GA Officer'],
            ['purchasing.officer@ogamierp.local', 'Officer@12345!',   'purchasing_officer', 'Purchasing Officer'],
            ['impex.officer@ogamierp.local',      'Officer@12345!',   'impex_officer',      'ImpEx Officer'],
            ['warehouse.head@ogamierp.local',     'Head@123456789!',  'warehouse_head', 'Warehouse Head'],
            ['ppc.head@ogamierp.local',           'Head@123456789!',  'ppc_head',       'PPC Head'],
            ['maintenance.head@ogamierp.local',   'Head@123456789!',  'head',           'Maintenance Head'],
            ['production.head@ogamierp.local',    'Head@123456789!',  'head',           'Production Head'],
            ['processing.head@ogamierp.local',    'Head@123456789!',  'head',           'Processing Head'],
            ['qcqa.head@ogamierp.local',          'Head@123456789!',  'head',           'QC/QA Head'],
            ['iso.head@ogamierp.local',           'Head@123456789!',  'head',           'Management System Head'],
            ['prod.staff@ogamierp.local',           'Staff@123456789!', 'staff',          'Production Operator'],
        ];
    }
}
