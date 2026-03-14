<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExtraAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEmployees();
        $this->seedUserAccounts();
        $this->seedLinks();
    }

    private function seedEmployees(): void
    {
        $employees = [
            [
                'code' => 'EMP-2026-0030',
                'first_name' => 'Amelia',
                'last_name' => 'Cordero', // Accounting Manager
                'pos' => 'ACCT-MGR',
                'dept' => 'ACCTG',
                'sg' => 'SG-11',
                'email' => 'amelia.cordero@email.com',
                'sss' => '33-1111111-1',
                'tin' => '111-222-333-000',
                'philhealth' => '01-111111111-1',
                'pagibig' => '1111-2222-3333',
            ],
            [
                'code' => 'EMP-2026-0031',
                'first_name' => 'Reynold',
                'last_name' => 'Techy', // IT Admin
                'pos' => 'IT-ADMIN',
                'dept' => 'IT',
                'sg' => 'SG-10',
                'email' => 'reynold.techy@email.com',
                'sss' => '33-9999999-9',
                'tin' => '999-888-777-000',
                'philhealth' => '01-999999999-9',
                'pagibig' => '9999-8888-7777',
            ],
        ];

        foreach ($employees as $emp) {
            $deptId = DB::table('departments')->where('code', $emp['dept'])->value('id');
            $posId = DB::table('positions')->where('code', $emp['pos'])->value('id');
            $sgId = DB::table('salary_grades')->where('code', $emp['sg'])->value('id');

            if (!$deptId || !$posId) continue;

            DB::table('employees')->insertOrIgnore([
                'employee_code' => $emp['code'],
                'ulid' => (string) Str::ulid(),
                'first_name' => $emp['first_name'],
                'last_name' => $emp['last_name'],
                'date_of_birth' => '1985-01-01',
                'gender' => 'female',
                'civil_status' => 'MARRIED',
                'citizenship' => 'Filipino',
                'present_address' => 'Test Address',
                'permanent_address' => 'Test Address',
                'qualified_dependents' => 0,
                'bir_status' => 'S',
                'personal_email' => $emp['email'],
                'personal_phone' => '09170000000',
                'bank_name' => 'Test Bank',
                'bank_account_no' => '0000000000',
                'department_id' => $deptId,
                'position_id' => $posId,
                'salary_grade_id' => $sgId,
                'employment_type' => 'regular',
                'employment_status' => 'active',
                'date_hired' => '2020-01-01',
                'basic_monthly_rate' => 5000000,
                'onboarding_status' => 'active',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
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

    private function seedUserAccounts(): void
    {
        $users = [
            [
                'email' => 'acctg.manager@ogamierp.local',
                'name' => 'Amelia Cordero',
                'role' => 'officer', // Using 'officer' role for full financial access
            ],
            [
                'email' => 'it.admin@ogamierp.local',
                'name' => 'Reynold Techy',
                'role' => 'admin', // IT Admin gets admin role
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => 'Manager@12345!', 
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                ]
            );
            $user->syncRoles([$u['role']]);
        }
    }

    private function seedLinks(): void
    {
        $links = [
            ['email' => 'acctg.manager@ogamierp.local', 'code' => 'EMP-2026-0030'],
            ['email' => 'it.admin@ogamierp.local', 'code' => 'EMP-2026-0031'],
        ];

        foreach ($links as $link) {
            $user = User::where('email', $link['email'])->first();
            $employee = Employee::where('employee_code', $link['code'])->first();

            if ($user && $employee) {
                $employee->user_id = $user->id;
                $employee->save();
                
                $user->department_id = $employee->department_id;
                $user->save();

                DB::table('user_department_access')->insertOrIgnore([
                    'user_id' => $user->id,
                    'department_id' => $employee->department_id,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('✓ Extra accounts seeded: acctg.manager, it.admin');
    }
}
