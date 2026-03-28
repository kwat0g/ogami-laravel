<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleBasedTestingSeeder extends Seeder
{
    public function run(): void
    {
        $usersToCreate = [
            [
                'email' => 'prod.staff@ogamierp.local',
                'name' => 'Production Staff',
                'password' => 'Staff@Test1234!',
                'role' => 'staff',
                'dept_code' => 'PROD',
            ],
            [
                'email' => 'prod.head@ogamierp.local',
                'name' => 'Production Head',
                'password' => 'Head@Test1234!',
                'role' => 'head',
                'dept_code' => 'PROD',
            ],
            [
                'email' => 'purch.manager@ogamierp.local',
                'name' => 'Purchasing Manager',
                'password' => 'Manager@Test1234!',
                'role' => 'manager',
                'dept_code' => 'PURCH',
            ],
            [
                'email' => 'acctg.officer@ogamierp.local',
                'name' => 'Accounting Officer',
                'password' => 'Officer@Test1234!',
                'role' => 'officer',
                'dept_code' => 'ACCTG',
            ],
            [
                'email' => 'vp@ogamierp.local',
                'name' => 'Vice President',
                'password' => 'Vice_president@Test1234!',
                'role' => 'vice_president', // Global supervisor role
                'dept_code' => 'EXEC',
            ],
            [
                'email' => 'wh.staff@ogamierp.local',
                'name' => 'Warehouse Staff',
                'password' => 'Staff@Test1234!',
                'role' => 'staff',
                'dept_code' => 'WH',
            ],
        ];

        foreach ($usersToCreate as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => $u['password'],
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                ]
            );

            // Assign role
            if (!$user->hasRole($u['role'])) {
                $user->assignRole($u['role']);
            }

            // Attempt to link to department
            $deptId = DB::table('departments')->where('code', $u['dept_code'])->value('id');
            if ($deptId) {
                // Denormalized department assign
                $user->update(['department_id' => $deptId]);

                // Access provision
                DB::table('user_department_access')->insertOrIgnore([
                    'user_id' => $user->id,
                    'department_id' => $deptId,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✓ Role-based testing users seeded.');
    }
}
