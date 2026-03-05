<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo data for development and payroll testing.
 *
 * Creates a focused set of 4 employees and their linked user accounts across
 * HR, IT, and Accounting departments.  Government IDs are fake but realistic;
 * TIN and bank details are intentionally left null.
 *
 * Demo accounts:
 *
 * | Email                       | Password             | Role       | Dept  |
 * |-----------------------------|----------------------|------------|-------|
 * | admin@ogamierp.local        | Admin@1234567890!    | admin      | IT    |
 * | hr.manager@ogamierp.local   | HrManager@1234!      | manager    | HR    |
 * | acctg.officer@ogamierp.local| AcctgManager@1234!   | officer    | ACCTG |
 * | hr.supervisor@ogamierp.local| HrSupervisor@1234!   | head       | HR    |
 * | hr.staff@ogamierp.local     | HrStaff@1234!        | staff      | HR    |
 *
 * Depends on: DepartmentPositionSeeder, SalaryGradeSeeder, LeaveTypeSeeder,
 *             FiscalPeriodSeeder, RolePermissionSeeder
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDemoEmployees();
        $this->seedUserAccounts();
        $this->seedEmployeeUserLinks();
        $this->seedPayPeriods();
        $this->seedAttendanceRecords();
        $this->seedShiftAssignments();
    }

    // ── 1. Demo Employees ─────────────────────────────────────────────────────

    private function seedDemoEmployees(): void
    {
        // 4 employees: HR Manager, IT Admin (system administrator), Accounting Manager, HR Staff.
        $employees = [
            [
                'code' => 'EMP-2026-0001',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'dob' => '1990-03-15',
                'gender' => 'female',
                'civil_status' => 'MARRIED',
                'dependents' => 2,
                'bir_status' => 'ME2',
                'email' => 'maria.santos@email.com',
                'phone' => '09171234567',
                'address' => '123 Sampaguita St., Brgy. San Antonio, Quezon City',
                'dept' => 'HR',
                'pos' => 'HR-MGR',
                'sg' => 'SG-05',  // ₱40,001–₱50,000 covers ₱45,000
                'hired' => '2020-01-06',
                'salary' => 4500000, // ₱45,000
                'sss' => '03-1234567-8',
                'tin' => '123-456-789-000',
                'philhealth' => '01-234567890-1',
                'pagibig' => '1234-5678-9012',
                'bank_name' => 'BDO',
                'bank_account_no' => '00001234567890',
            ],
            [
                'code' => 'EMP-2026-0002',
                'first_name' => 'Juan Carlos',
                'last_name' => 'Dela Cruz',
                'dob' => '1985-06-01',
                'gender' => 'male',
                'civil_status' => 'SINGLE',
                'dependents' => 0,
                'bir_status' => 'S',
                'email' => 'juan.delacruz@email.com',
                'phone' => '09189876543',
                'address' => '456 Dahlia St., Brgy. Pinyahan, Quezon City',
                'dept' => 'IT',
                'pos' => 'IT-ADMIN',
                'sg' => 'SG-06',  // ₱50,001–₱63,000 covers ₱52,000
                'hired' => '2020-01-06',
                'salary' => 5200000, // ₱52,000
                'sss' => '03-9876543-2',
                'tin' => '234-567-890-001',
                'philhealth' => '01-987654321-0',
                'pagibig' => '9876-5432-1098',
                'bank_name' => 'BPI',
                'bank_account_no' => '1234567890',
            ],
            [
                'code' => 'EMP-2026-0003',
                'first_name' => 'Anna Marie',
                'last_name' => 'Lim',
                'dob' => '1989-09-25',
                'gender' => 'female',
                'civil_status' => 'MARRIED',
                'dependents' => 2,
                'bir_status' => 'ME2',
                'email' => 'anna.lim@email.com',
                'phone' => '09171234571',
                'address' => '147 Escolta St., Binondo, Manila',
                'dept' => 'ACCTG',
                'pos' => 'ACCT-MGR',
                'sg' => 'SG-06',  // ₱50,001–₱63,000 covers ₱55,000
                'hired' => '2018-05-20',
                'salary' => 5500000, // ₱55,000
                'sss' => '03-4444444-4',
                'tin' => '345-678-901-002',
                'philhealth' => '01-444444444-4',
                'pagibig' => '4444-5555-6666',
                'bank_name' => 'UnionBank',
                'bank_account_no' => '109212345678',
            ],
            [
                'code' => 'EMP-2026-0004',
                'first_name' => 'Francisco',
                'last_name' => 'Ramos',
                'dob' => '1996-07-14',
                'gender' => 'male',
                'civil_status' => 'SINGLE',
                'dependents' => 0,
                'bir_status' => 'S',
                'email' => 'francisco.ramos@email.com',
                'phone' => '09171234568',
                'address' => '789 Mabini St., Brgy. Maligaya, Caloocan City',
                'dept' => 'HR',
                'pos' => 'HR-ASST',
                'sg' => 'SG-01',  // ₱18,000–₱22,000 covers ₱22,000
                'hired' => '2022-03-01',
                'salary' => 2200000, // ₱22,000
                'sss' => '03-5555555-5',
                'tin' => '456-789-012-003',
                'philhealth' => '01-555555555-5',
                'pagibig' => '5555-6666-7777',
                'bank_name' => 'Metrobank',
                'bank_account_no' => '09876543210',
            ],
            [
                'code' => 'EMP-2026-0005',
                'first_name' => 'Pedro',
                'last_name' => 'Cruz',
                'dob' => '1993-11-08',
                'gender' => 'male',
                'civil_status' => 'MARRIED',
                'dependents' => 1,
                'bir_status' => 'ME1',
                'email' => 'pedro.cruz@email.com',
                'phone' => '09178881234',
                'address' => '22 Rosal St., Brgy. Pinagsama, Taguig City',
                'dept' => 'HR',
                'pos' => 'HR-SUP',
                'sg' => 'SG-03',  // ₱27,001–₱33,000 covers ₱30,000
                'hired' => '2021-06-15',
                'salary' => 3000000, // ₱30,000
                'sss' => '03-6666666-7',
                'tin' => '567-890-123-004',
                'philhealth' => '01-666666666-6',
                'pagibig' => '6666-7777-8888',
                'bank_name' => 'UnionBank',
                'bank_account_no' => '109299887766',
            ],
        ];

        $count = 0;
        foreach ($employees as $emp) {
            $deptId = DB::table('departments')->where('code', $emp['dept'])->value('id');
            $posId = DB::table('positions')->where('code', $emp['pos'])->value('id');
            $sgId = DB::table('salary_grades')->where('code', $emp['sg'])->value('id');

            if (! $deptId || ! $posId) {
                $this->command->warn("  Skipping {$emp['code']} — dept '{$emp['dept']}' or position '{$emp['pos']}' not found.");

                continue;
            }

            DB::table('employees')->insertOrIgnore([
                'employee_code' => $emp['code'],
                'ulid' => (string) Str::ulid(),
                'first_name' => $emp['first_name'],
                'last_name' => $emp['last_name'],
                'date_of_birth' => $emp['dob'],
                'gender' => $emp['gender'],
                'civil_status' => $emp['civil_status'],
                'citizenship' => 'Filipino',
                'present_address' => $emp['address'],
                'permanent_address' => $emp['address'],
                'qualified_dependents' => $emp['dependents'],
                'bir_status' => $emp['bir_status'],
                'personal_email' => $emp['email'],
                'personal_phone' => $emp['phone'],
                'bank_name' => $emp['bank_name'],
                'bank_account_no' => $emp['bank_account_no'],
                'department_id' => $deptId,
                'position_id' => $posId,
                'salary_grade_id' => $sgId,
                'employment_type' => 'regular',
                'employment_status' => 'active',
                'date_hired' => $emp['hired'],
                'basic_monthly_rate' => $emp['salary'],
                'onboarding_status' => 'active',
                'is_active' => true,
                'pay_basis' => 'monthly',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Set government IDs and activate via model (encrypted + hashed).
            $employee = Employee::where('employee_code', $emp['code'])->first();
            if ($employee && ! $employee->has_sss_no) {
                $employee->setSssNo($emp['sss']);
                $employee->setTin($emp['tin']);
                $employee->setPhilhealthNo($emp['philhealth']);
                $employee->setPagibigNo($emp['pagibig']);
                $employee->onboarding_status = 'active';
                $employee->is_active = true;
                $employee->_fire_activated_event = true;
                $employee->save();
            }

            $count++;
        }

        // Establish reporting lines: HR Supervisor → HR Manager, HR Staff → HR Supervisor
        $hrManagerId = DB::table('employees')->where('employee_code', 'EMP-2026-0001')->value('id');
        $hrSupervisorId = DB::table('employees')->where('employee_code', 'EMP-2026-0005')->value('id');
        $hrStaffId = DB::table('employees')->where('employee_code', 'EMP-2026-0004')->value('id');
        if ($hrManagerId && $hrSupervisorId) {
            DB::table('employees')
                ->where('id', $hrSupervisorId)
                ->whereNull('reports_to')
                ->update(['reports_to' => $hrManagerId]);
        }
        if ($hrSupervisorId && $hrStaffId) {
            DB::table('employees')
                ->where('id', $hrStaffId)
                ->whereNull('reports_to')
                ->update(['reports_to' => $hrSupervisorId]);
        }

        $this->command->info("✓ {$count} demo employees seeded (with complete gov IDs and bank details).");
    }

    // ── 2. User Accounts ──────────────────────────────────────────────────────

    private function seedUserAccounts(): void
    {
        // Admin — already bootstrapped by RolePermissionSeeder; firstOrCreate is safe.
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@ogamierp.local'],
            [
                'name' => 'Juan Carlos Dela Cruz',
                'password' => 'Admin@1234567890!',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $adminUser->syncRoles(['admin']);

        // HR Manager
        $hrUser = User::firstOrCreate(
            ['email' => 'hr.manager@ogamierp.local'],
            [
                'name' => 'Maria Santos',
                'password' => 'HrManager@1234!',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $hrUser->syncRoles(['manager']);

        // Accounting Officer
        $acctgUser = User::firstOrCreate(
            ['email' => 'acctg.officer@ogamierp.local'],
            [
                'name' => 'Anna Marie Lim',
                'password' => 'AcctgManager@1234!',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $acctgUser->syncRoles(['officer']);

        // HR Supervisor (test account for the supervisor-endorse workflow)
        $supervisorUser = User::firstOrCreate(
            ['email' => 'hr.supervisor@ogamierp.local'],
            [
                'name' => 'Pedro Cruz',
                'password' => 'HrSupervisor@1234!',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $supervisorUser->syncRoles(['head']);

        // HR Staff
        $staffUser = User::firstOrCreate(
            ['email' => 'hr.staff@ogamierp.local'],
            [
                'name' => 'Francisco Ramos',
                'password' => 'HrStaff@1234!',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $staffUser->syncRoles(['staff']);

        $this->command->info('✓ User accounts ready:');
        $this->command->info('  admin      admin@ogamierp.local          Admin@1234567890!');
        $this->command->info('  hr_manager hr.manager@ogamierp.local     HrManager@1234!');
        $this->command->info('  officer    acctg.officer@ogamierp.local  AcctgManager@1234!');
        $this->command->info('  supervisor hr.supervisor@ogamierp.local  HrSupervisor@1234!');
        $this->command->info('  staff      hr.staff@ogamierp.local       HrStaff@1234!');
    }

    // ── 3. User ↔ Employee Links ──────────────────────────────────────────────

    private function seedEmployeeUserLinks(): void
    {
        $links = [
            ['user' => 'hr.manager@ogamierp.local',    'employee' => 'EMP-2026-0001'],
            ['user' => 'admin@ogamierp.local',          'employee' => 'EMP-2026-0002'],
            ['user' => 'acctg.officer@ogamierp.local',  'employee' => 'EMP-2026-0003'],
            ['user' => 'hr.staff@ogamierp.local',       'employee' => 'EMP-2026-0004'],
            ['user' => 'hr.supervisor@ogamierp.local',  'employee' => 'EMP-2026-0005'],
        ];

        foreach ($links as $link) {
            $user = DB::table('users')->where('email', $link['user'])->first();
            $employee = DB::table('employees')->where('employee_code', $link['employee'])->first();

            if (! $user || ! $employee) {
                $this->command->warn("  Skipping link for {$link['user']} — user or employee not found.");

                continue;
            }

            // Link employee → user
            DB::table('employees')
                ->where('id', $employee->id)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            // Keep denormalised users.department_id in sync
            DB::table('users')
                ->where('id', $user->id)
                ->whereNull('department_id')
                ->update(['department_id' => $employee->department_id]);

            // Provision RDAC: user gets primary access to their own department
            DB::table('user_department_access')->insertOrIgnore([
                'user_id' => $user->id,
                'department_id' => $employee->department_id,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),

            ]);
        }

        $this->command->info('✓ User–employee links created.');
    }

    // ── 4. Pay Periods ────────────────────────────────────────────────────────

    private function seedPayPeriods(): void
    {
        $periods = [
            // January 2026 — closed (already paid out)
            ['label' => 'Jan 2026 1st', 'cutoff_start' => '2026-01-01', 'cutoff_end' => '2026-01-15', 'pay_date' => '2026-01-20', 'status' => 'closed'],
            ['label' => 'Jan 2026 2nd', 'cutoff_start' => '2026-01-16', 'cutoff_end' => '2026-01-31', 'pay_date' => '2026-01-31', 'status' => 'closed'],
            // February 2026 — closed
            ['label' => 'Feb 2026 1st', 'cutoff_start' => '2026-02-01', 'cutoff_end' => '2026-02-15', 'pay_date' => '2026-02-20', 'status' => 'closed'],
            ['label' => 'Feb 2026 2nd', 'cutoff_start' => '2026-02-16', 'cutoff_end' => '2026-02-28', 'pay_date' => '2026-02-28', 'status' => 'closed'],
            // March 2026 — open (current month)
            ['label' => 'Mar 2026 1st', 'cutoff_start' => '2026-03-01', 'cutoff_end' => '2026-03-15', 'pay_date' => '2026-03-20', 'status' => 'open'],
            ['label' => 'Mar 2026 2nd', 'cutoff_start' => '2026-03-16', 'cutoff_end' => '2026-03-31', 'pay_date' => '2026-03-31', 'status' => 'open'],
            // April 2026 — open
            ['label' => 'Apr 2026 1st', 'cutoff_start' => '2026-04-01', 'cutoff_end' => '2026-04-15', 'pay_date' => '2026-04-20', 'status' => 'open'],
            ['label' => 'Apr 2026 2nd', 'cutoff_start' => '2026-04-16', 'cutoff_end' => '2026-04-30', 'pay_date' => '2026-04-30', 'status' => 'open'],
            // June 2026 — open (May skipped per business schedule)
            ['label' => 'Jun 2026 1st', 'cutoff_start' => '2026-06-01', 'cutoff_end' => '2026-06-15', 'pay_date' => '2026-06-20', 'status' => 'open'],
            ['label' => 'Jun 2026 2nd', 'cutoff_start' => '2026-06-16', 'cutoff_end' => '2026-06-30', 'pay_date' => '2026-06-30', 'status' => 'open'],
        ];

        $inserted = 0;
        foreach ($periods as $period) {
            $exists = DB::table('pay_periods')
                ->where('cutoff_start', $period['cutoff_start'])
                ->where('cutoff_end', $period['cutoff_end'])
                ->where('frequency', 'semi_monthly')
                ->exists();

            if (! $exists) {
                DB::table('pay_periods')->insert(array_merge($period, [
                    'frequency' => 'semi_monthly',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $inserted++;
            }
        }

        $this->command->info("✓ Pay periods seeded ({$inserted} periods for Jan–Apr + Jun 2026).");
    }

    // ── 6. Attendance Records (full months: Jan, Feb, Mar, Apr, Jun 2026) ─────

    private function seedAttendanceRecords(): void
    {
        $employeeCodes = ['EMP-2026-0001', 'EMP-2026-0002', 'EMP-2026-0003', 'EMP-2026-0004', 'EMP-2026-0005'];

        // All Mon–Fri working days for Jan, Feb, Mar, Apr, Jun 2026.
        // Jan 1 (New Year) excluded. Weekends excluded. May skipped per business schedule.
        $workDays = [
            // January 2026 — 21 days (Jan 1 New Year excluded)
            '2026-01-02', '2026-01-05', '2026-01-06', '2026-01-07', '2026-01-08', '2026-01-09',
            '2026-01-12', '2026-01-13', '2026-01-14', '2026-01-15', '2026-01-16',
            '2026-01-19', '2026-01-20', '2026-01-21', '2026-01-22', '2026-01-23',
            '2026-01-26', '2026-01-27', '2026-01-28', '2026-01-29', '2026-01-30',
            // February 2026 — 20 days
            '2026-02-02', '2026-02-03', '2026-02-04', '2026-02-05', '2026-02-06',
            '2026-02-09', '2026-02-10', '2026-02-11', '2026-02-12', '2026-02-13',
            '2026-02-16', '2026-02-17', '2026-02-18', '2026-02-19', '2026-02-20',
            '2026-02-23', '2026-02-24', '2026-02-25', '2026-02-26', '2026-02-27',
            // March 2026 — 22 days
            '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06',
            '2026-03-09', '2026-03-10', '2026-03-11', '2026-03-12', '2026-03-13',
            '2026-03-16', '2026-03-17', '2026-03-18', '2026-03-19', '2026-03-20',
            '2026-03-23', '2026-03-24', '2026-03-25', '2026-03-26', '2026-03-27',
            '2026-03-30', '2026-03-31',
            // April 2026 — 22 days
            '2026-04-01', '2026-04-02', '2026-04-03', '2026-04-06', '2026-04-07',
            '2026-04-08', '2026-04-09', '2026-04-10', '2026-04-13', '2026-04-14',
            '2026-04-15', '2026-04-16', '2026-04-17', '2026-04-20', '2026-04-21',
            '2026-04-22', '2026-04-23', '2026-04-24', '2026-04-27', '2026-04-28',
            '2026-04-29', '2026-04-30',
            // June 2026 — 22 days (May skipped per business schedule)
            '2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04', '2026-06-05',
            '2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11', '2026-06-12',
            '2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19',
            '2026-06-22', '2026-06-23', '2026-06-24', '2026-06-25', '2026-06-26',
            '2026-06-29', '2026-06-30',
        ];

        $totalInserted = 0;

        foreach ($employeeCodes as $code) {
            $empId = DB::table('employees')->where('employee_code', $code)->value('id');
            if (! $empId) {
                continue;
            }

            foreach ($workDays as $date) {
                $exists = DB::table('attendance_logs')
                    ->where('employee_id', $empId)
                    ->where('work_date', $date)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Realistic 8AM–5PM logs with minor tardiness variation (0–15 min)
                $lateMinutes = rand(0, 15);
                $timeIn = $date.sprintf(' 08:%02d:00', $lateMinutes);
                $timeOut = $date.' 17:00:00';
                $workedMinutes = 480 - $lateMinutes; // 8 hrs minus late

                DB::table('attendance_logs')->insert([
                    'employee_id' => $empId,
                    'work_date' => $date,
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'source' => 'manual',
                    'is_present' => true,
                    'is_absent' => false,
                    'is_rest_day' => false,
                    'is_holiday' => false,
                    'holiday_type' => null,
                    'late_minutes' => $lateMinutes,
                    'undertime_minutes' => 0,
                    'worked_minutes' => $workedMinutes,
                    'night_diff_minutes' => 0,
                    'overtime_minutes' => 0,
                    'overtime_request_id' => null,
                    'is_processed' => true,
                    'processed_at' => now(),
                    'processing_notes' => 'Auto-seeded for payroll testing',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $totalInserted++;
            }
        }

        $count = count($workDays);
        $this->command->info("✓ Attendance seeded ({$totalInserted} records — {$count} days × 5 employees).");
        $this->command->info('  Months covered: Jan (21d), Feb (20d), Mar (22d), Apr (22d), Jun (22d) — May skipped.');
    }

    // ── 6. Shift Assignments ──────────────────────────────────────────────────
    private function seedShiftAssignments(): void
    {
        // Look up the Regular Day Shift (8AM–5PM) as the default office schedule
        $regularShift = DB::table('shift_schedules')
            ->where('start_time', '08:00:00')
            ->where('is_active', true)
            ->first();

        if (! $regularShift) {
            $this->command->warn('  Shift assignments skipped — Regular Day Shift not found.');

            return;
        }

        // Use the admin user as the assigner
        $assignedBy = DB::table('users')->first()?->id ?? 1;

        $employeeCodes = [
            'EMP-2026-0001', 'EMP-2026-0002', 'EMP-2026-0003',
            'EMP-2026-0004', 'EMP-2026-0005',
        ];

        $inserted = 0;
        foreach ($employeeCodes as $code) {
            $employee = DB::table('employees')->where('employee_code', $code)->first();
            if (! $employee) {
                continue;
            }

            // Skip if already has an assignment
            $exists = DB::table('employee_shift_assignments')
                ->where('employee_id', $employee->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('employee_shift_assignments')->insert([
                'employee_id' => $employee->id,
                'shift_schedule_id' => $regularShift->id,
                'effective_from' => $employee->date_hired,
                'effective_to' => null,
                'notes' => 'Initial shift assignment (seeded)',
                'assigned_by' => $assignedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        $this->command->info("✓ Shift assignments seeded ({$inserted} records).");
    }
}
