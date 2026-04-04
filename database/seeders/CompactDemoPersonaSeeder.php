<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CompactDemoPersonaSeeder
 *
 * Purpose:
 * - Create a small, deterministic non-superadmin account pack for demos/tests.
 * - Reduce context switching across dozens of seeded users.
 * - Preserve SoD by keeping requester/approver personas separate.
 *
 * Run:
 *   php artisan db:seed --class=CompactDemoPersonaSeeder
 */
class CompactDemoPersonaSeeder extends Seeder
{
    public function run(): void
    {
        $personas = [
            [
                'email' => 'demo.client@ogami.test',
                'name' => 'Demo Client Buyer',
                'password' => 'DemoPortal@1234!',
                'role' => 'client',
                'primary_dept' => null,
                'extra_depts' => [],
                'client_id' => 1,
            ],
            [
                'email' => 'demo.sales@ogamierp.local',
                'name' => 'Demo Sales Coordinator',
                'password' => 'DemoSales@1234!',
                'role' => 'manager',
                'primary_dept' => 'SALES',
                'extra_depts' => ['SALES'],
                'client_id' => null,
            ],
            [
                'email' => 'demo.requester@ogamierp.local',
                'name' => 'Demo Procurement Requester',
                'password' => 'DemoReq@1234!',
                'role' => 'officer',
                'primary_dept' => 'PURCH',
                'extra_depts' => ['PURCH'],
                'client_id' => null,
            ],
            [
                'email' => 'demo.ops@ogamierp.local',
                'name' => 'Demo Operations Executor',
                'password' => 'DemoOps@1234!',
                'role' => 'head',
                'primary_dept' => 'PROD',
                'extra_depts' => ['PROD', 'WH', 'QC'],
                'client_id' => null,
            ],
            [
                'email' => 'demo.finance@ogamierp.local',
                'name' => 'Demo Finance Officer',
                'password' => 'DemoFin@1234!',
                'role' => 'manager',
                'primary_dept' => 'ACCTG',
                'extra_depts' => ['ACCTG'],
                'client_id' => null,
            ],
            [
                'email' => 'demo.approver@ogamierp.local',
                'name' => 'Demo VP Approver',
                'password' => 'DemoVP@1234!',
                'role' => 'vice_president',
                'primary_dept' => 'EXEC',
                'extra_depts' => ['EXEC'],
                'client_id' => null,
            ],
        ];

        foreach ($personas as $p) {
            $deptId = $p['primary_dept'] ? (int) (DB::table('departments')->where('code', $p['primary_dept'])->value('id') ?? 0) : null;

            $user = User::updateOrCreate(
                ['email' => $p['email']],
                [
                    'name' => $p['name'],
                    'password' => Hash::make($p['password']),
                    'department_id' => $deptId,
                    'client_id' => $p['client_id'],
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                ]
            );

            $user->syncRoles([$p['role']]);

            foreach ($p['extra_depts'] as $index => $deptCode) {
                $id = DB::table('departments')->where('code', $deptCode)->value('id');
                if (! $id) {
                    continue;
                }

                DB::table('user_department_access')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'department_id' => $id,
                    ],
                    [
                        'is_primary' => $index === 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('');
        $this->command->info('Compact Demo Persona Pack (no superadmin)');
        $this->command->info('  demo.client@ogami.test        / DemoPortal@1234!');
        $this->command->info('  demo.sales@ogamierp.local     / DemoSales@1234!');
        $this->command->info('  demo.requester@ogamierp.local / DemoReq@1234!');
        $this->command->info('  demo.ops@ogamierp.local       / DemoOps@1234!');
        $this->command->info('  demo.finance@ogamierp.local   / DemoFin@1234!');
        $this->command->info('  demo.approver@ogamierp.local  / DemoVP@1234!');
    }
}
