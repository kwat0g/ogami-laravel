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
            // ── Accounting Manager ────────────────────────────────────────────
            [
                'code'           => 'EMP-2026-0030',
                'first_name'     => 'Amelia',
                'middle_name'    => 'Dela Cruz',
                'last_name'      => 'Cordero',
                'dob'            => '1982-07-14',
                'gender'         => 'female',
                'civil_status'   => 'MARRIED',
                'dependents'     => 2,
                'bir_status'     => 'ME2',
                'address'        => '42 Emerald Ave., Brgy. Kapitolyo, Pasig City',
                'phone'          => '09175550030',
                'pos'            => 'ACCT-MGR',
                'dept'           => 'ACCTG',
                'sg'             => 'SG-11',
                'hired'          => '2019-03-01',
                'regularization' => '2019-09-01',
                'salary'         => 5500000, // ₱55,000
                'email'          => 'amelia.cordero@email.com',
                'bank_name'      => 'UnionBank',
                'bank_account_no'=> '109200123456',
            ],
            // ── IT Admin ──────────────────────────────────────────────────────
            [
                'code'           => 'EMP-2026-0031',
                'first_name'     => 'Reynold',
                'middle_name'    => 'Santos',
                'last_name'      => 'Tecson',
                'dob'            => '1990-11-20',
                'gender'         => 'male',
                'civil_status'   => 'SINGLE',
                'dependents'     => 0,
                'bir_status'     => 'S',
                'address'        => '15 Sunset Drive, Brgy. San Roque, Antipolo City, Rizal',
                'phone'          => '09175550031',
                'pos'            => 'IT-ADMIN',
                'dept'           => 'IT',
                'sg'             => 'SG-10',
                'hired'          => '2021-06-15',
                'regularization' => '2021-12-15',
                'salary'         => 3500000, // ₱35,000
                'email'          => 'reynold.tecson@email.com',
                'bank_name'      => 'BDO',
                'bank_account_no'=> '00234567890123',
            ],
        ];

        foreach ($employees as $emp) {
            $deptId = DB::table('departments')->where('code', $emp['dept'])->value('id');
            $posId = DB::table('positions')->where('code', $emp['pos'])->value('id');
            $sgId = DB::table('salary_grades')->where('code', $emp['sg'])->value('id');

            if (!$deptId || !$posId) continue;

            DB::table('employees')->insertOrIgnore([
                'employee_code'       => $emp['code'],
                'ulid'                => (string) Str::ulid(),
                'first_name'          => $emp['first_name'],
                'middle_name'         => $emp['middle_name'] ?? null,
                'last_name'           => $emp['last_name'],
                'date_of_birth'       => $emp['dob'],
                'gender'              => $emp['gender'],
                'civil_status'        => $emp['civil_status'],
                'citizenship'         => 'Filipino',
                'present_address'     => $emp['address'],
                'permanent_address'   => $emp['address'],
                'qualified_dependents'=> $emp['dependents'],
                'bir_status'          => $emp['bir_status'],
                'personal_email'      => $emp['email'],
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
                'regularization_date' => $emp['regularization'] ?? null,
                'basic_monthly_rate'  => $emp['salary'],
                'onboarding_status'   => 'documents_pending',
                'is_active'           => false,
                'pay_basis'           => 'monthly',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
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
                'name' => 'Reynold Tecson',
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

        $this->command->info('✓ Extra accounts seeded: acctg.manager@ogamierp.local, it.admin@ogamierp.local');
    }
}
