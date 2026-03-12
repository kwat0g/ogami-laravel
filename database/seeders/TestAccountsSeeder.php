<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates one user account per role for full-system manual testing.
 *
 * Usage:
 *   php artisan db:seed --class=TestAccountsSeeder
 *
 * Prerequisites:
 *   - RolePermissionSeeder must be run first
 *   - DepartmentPositionSeeder must be run first (for department links)
 *
 * All passwords follow the pattern: {RoleName}@Test1234!
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Role               Email                                 Name                        Dept
            ['executive',         'executive@ogamierp.local',          'Roberto Reyes (Executive)',      'EXEC'],
            ['vice_president',    'vp@ogamierp.local',                 'Elena Cruz (Vice President)',    'EXEC'],
            ['manager',           'hr.manager@ogamierp.local',         'Maria Santos (HR Manager)',      'HR'],
            ['plant_manager',     'plant.manager@ogamierp.local',      'Carlos Rivera (Plant Manager)',  'PROD'],
            ['production_manager','prod.manager@ogamierp.local',       'Jose Garcia (Prod Manager)',     'PROD'],
            ['qc_manager',        'qc.manager@ogamierp.local',         'Linda Tan (QC Manager)',         'QC'],
            ['mold_manager',      'mold.manager@ogamierp.local',       'Ramon Aquino (Mold Manager)',    'MOLD'],
            ['officer',           'acctg.officer@ogamierp.local',      'Anna Marie Lim (Acctg Officer)','ACCTG'],
            ['ga_officer',        'ga.officer@ogamierp.local',         'Grace Mendoza (GA Officer)',     'HR'],
            ['purchasing_officer','purchasing@ogamierp.local',          'Mark Villanueva (Purchasing)',   'ACCTG'],
            ['impex_officer',     'impex@ogamierp.local',              'Diana Ramos (ImpEx Officer)',    'ACCTG'],
            ['head',              'dept.head@ogamierp.local',          'Ricardo Bautista (Dept Head)',   'PROD'],
            ['staff',             'staff@ogamierp.local',              'Juan dela Cruz (Staff)',         'PROD'],
            ['vendor',            'vendor@ogamierp.local',             'Vendor User (ABC Supplier)',     null],
            ['client',            'client@ogamierp.local',             'Client User (XYZ Corp)',         null],
        ];

        $created = 0;

        foreach ($accounts as [$role, $email, $name, $deptCode]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'                => $name,
                    'password'            => ucfirst($role) . '@Test1234!',
                    'email_verified_at'   => now(),
                    'password_changed_at' => now(),
                ]
            );

            $user->syncRoles([$role]);

            // Link to department if available
            if ($deptCode) {
                $deptId = DB::table('departments')->where('code', $deptCode)->value('id');
                if ($deptId && ! $user->department_id) {
                    $user->update(['department_id' => $deptId]);
                    DB::table('user_department_access')->insertOrIgnore([
                        'user_id'       => $user->id,
                        'department_id' => $deptId,
                        'is_primary'    => true,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }

            $created++;
        }

        $this->command->info("✓ {$created} test accounts provisioned.");
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Password'],
            collect($accounts)->map(fn ($a) => [$a[0], $a[1], ucfirst($a[0]) . '@Test1234!'])->toArray()
        );
    }
}
