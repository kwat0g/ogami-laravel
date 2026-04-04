<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates test user accounts for RBAC v2 (7-role system).
 *
 * Usage:
 *   php artisan db:seed --class=TestAccountsSeeder
 *
 * Prerequisites:
 *   - RolePermissionSeeder must be run first
 *   - DepartmentPositionSeeder must be run first (for department links)
 *   - Modules must be assigned to departments
 *
 * RBAC v2: Permissions are determined by Role + Department Module
 *   Example: manager + HR dept = HR permissions
 *            manager + PROD dept = Production permissions
 *
 * All passwords follow the pattern: {RoleName}@Test1234!
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // RBAC v2: 7 Core Roles + Portal Roles
        // Department determines the module permissions
        // Note: super_admin and admin are created by RolePermissionSeeder with their own passwords.
        //       Do NOT re-create them here to avoid misleading password output.
        $accounts = [
            // Role               Email                                 Name                              Dept
            ['executive',         'executive@ogamierp.local',          'Roberto Reyes (Executive)',      'EXEC'],
            ['vice_president',    'vp@ogamierp.local',                 'Elena Cruz (Vice President)',    'EXEC'],

            // Managers - department determines their module access
            ['manager',           'hr.manager@ogamierp.local',         'Maria Santos (HR Manager)',      'HR'],
            ['manager',           'acctg.manager@ogamierp.local',      'Patricia Dela Cruz (Acctg Mgr)', 'ACCTG'],
            ['manager',           'plant.manager@ogamierp.local',      'Carlos Rivera (Plant Manager)',  'PLANT'],
            ['manager',           'prod.manager@ogamierp.local',       'Jose Garcia (Prod Manager)',     'PROD'],
            ['manager',           'qc.manager@ogamierp.local',         'Linda Tan (QC Manager)',         'QC'],
            ['manager',           'mold.manager@ogamierp.local',       'Ramon Aquino (Mold Manager)',    'MOLD'],
            ['manager',           'wh.manager@ogamierp.local',         'Ernesto Santos (WH Manager)',    'WH'],
            ['manager',           'sales.manager@ogamierp.local',      'Diana Cruz (Sales Manager)',     'SALES'],

            // Officers - department determines their module access
            ['officer',           'acctg.officer@ogamierp.local',      'Grace Mendoza (Acctg Officer)',  'ACCTG'],
            ['officer',           'purchasing.officer@ogamierp.local', 'Mark Villanueva (Purchasing)',   'PURCH'],
            ['officer',           'impex.officer@ogamierp.local',      'Diana Ramos (ImpEx Officer)',    'SALES'],

            // Heads - department determines their module access
            ['head',              'dept.head@ogamierp.local',          'Ricardo Bautista (Dept Head)',   'PROD'],
            ['head',              'warehouse.head@ogamierp.local',     'Josefa Bautista (WH Head)',      'WH'],
            ['head',              'ppc.head@ogamierp.local',           'Jerome Florido (PPC Head)',      'PPC'],
            ['head',              'sales.head@ogamierp.local',         'Lorna Reyes (Sales Head)',       'SALES'],

            // Staff
            ['staff',             'staff@ogamierp.local',              'Juan dela Cruz (Staff)',         'PROD'],
            ['staff',             'hr.staff@ogamierp.local',           'Juan Dela Cruz (HR Staff)',      'HR'],

            // Portal accounts
            ['vendor',            'vendor@ogamierp.local',             'Vendor User (ABC Supplier)',     null],
            ['client',            'client@ogamierp.local',             'Client User (XYZ Corp)',         null],
        ];

        $created = 0;

        foreach ($accounts as [$role, $email, $name, $deptCode]) {
            $mustChangePassword = in_array($role, ['vendor', 'client'], true);
            $passwordChangedAt = $mustChangePassword ? null : now();

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => ucfirst($role).'@Test1234!',
                    'email_verified_at' => now(),
                    'password_changed_at' => $passwordChangedAt,
                ]
            );

            if ($mustChangePassword && $user->password_changed_at !== null) {
                $user->update(['password_changed_at' => null]);
            }

            $user->syncRoles([$role]);

            // Link to department if available
            if ($deptCode) {
                $deptId = DB::table('departments')->where('code', $deptCode)->value('id');
                if ($deptId && ! $user->department_id) {
                    $user->update(['department_id' => $deptId]);
                    DB::table('user_department_access')->insertOrIgnore([
                        'user_id' => $user->id,
                        'department_id' => $deptId,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $created++;
        }

        $this->command->info("✓ {$created} test accounts provisioned (RBAC v2).");
        $this->command->newLine();
        $this->command->info('Note: Permissions are determined by Role + Department Module');
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Password', 'Dept'],
            collect($accounts)->map(fn ($a) => [$a[0], $a[1], ucfirst($a[0]).'@Test1234!', $a[3] ?? '-'])->toArray()
        );
    }
}
