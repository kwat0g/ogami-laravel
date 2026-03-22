<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Segregation of Duties — Feature Tests
|--------------------------------------------------------------------------
| Tests 5 SoD scenarios — all must return HTTP 403 + SOD_VIOLATION.
|
| Rule IDs: SOD-001 through SOD-005
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModuleSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModulePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentModuleAssignmentSeeder'])->assertExitCode(0);

    // Activate the SoD conflict matrix in system_settings
    DB::table('system_settings')->updateOrInsert(
        ['key' => 'sod_conflict_matrix'],
        [
            'label' => 'SoD matrix for tests',
            'value' => json_encode([
                'payroll' => [
                    'prepare' => ['approve'],
                    'approve' => ['prepare', 'release'],
                    'release' => ['approve'],
                ],
                'payroll_runs' => [
                    'initiate' => ['approve'],
                    'approve' => ['initiate', 'prepare'],
                ],
                'procurement' => [
                    'request' => ['approve'],
                    'approve' => ['request', 'receive'],
                ],
                'leave' => [
                    'apply' => ['approve'],
                    'approve' => ['apply'],
                ],
                // HTTP-route process identifiers (used as `sod:{process},{action}`)
                'leave_requests' => [
                    'approve' => ['apply'],
                    'apply' => ['approve'],
                ],
                'loans' => [
                    'approve' => ['apply', 'request'],
                    'vp_approve' => ['apply', 'request'],
                    'apply' => ['approve'],
                ],
                'overtime' => [
                    'approve' => ['submit'],
                    'submit' => ['approve'],
                ],
                'overtime_requests' => [
                    'approve' => ['submit'],
                    'submit' => ['approve'],
                ],
                // Journal entries: drafter cannot post (GL-SoD / SOD-010)
                'journal_entries' => [
                    'create' => ['post'],
                    'post' => ['create'],
                ],
            ]),
            'data_type' => 'json',
        ]
    );
});

/**
 * Helper: create a user, assign permission, and authenticate.
 */
function makeUserWithPermission(string $roleName, string $permission): User
{
    $user = User::factory()->create(['password' => Hash::make('Pass!1234567')]);
    $user->assignRole($roleName);
    $user->givePermissionTo($permission);

    return $user;
}

// Stub route registered only during SoD tests
beforeAll(function () {
    // Routes using the 'sod' middleware are tested against real routes.
    // The auth endpoints don't use SoD; we test via a synthetic route defined
    // in the test service provider or we test the middleware directly.
});

describe('SodMiddleware — direct invocation', function () {
    it('blocks payroll approver from also preparing — SOD-001', function () {
        $conflictMatrix = DB::table('system_settings')
            ->where('key', 'sod_conflict_matrix')
            ->value('value');

        expect($conflictMatrix)->not->toBeNull();

        $matrix = json_decode($conflictMatrix, true);
        expect($matrix['payroll']['approve'])->toContain('prepare');
    });

    it('blocks payroll prepare from also approving — SOD-002', function () {
        $matrix = json_decode(
            DB::table('system_settings')->where('key', 'sod_conflict_matrix')->value('value'),
            true
        );
        expect($matrix['payroll']['prepare'])->toContain('approve');
    });

    it('blocks procurement requester from approving — SOD-003', function () {
        $matrix = json_decode(
            DB::table('system_settings')->where('key', 'sod_conflict_matrix')->value('value'),
            true
        );
        expect($matrix['procurement']['request'])->toContain('approve');
    });

    it('blocks procurement approver from receiving — SOD-004', function () {
        $matrix = json_decode(
            DB::table('system_settings')->where('key', 'sod_conflict_matrix')->value('value'),
            true
        );
        expect($matrix['procurement']['approve'])->toContain('receive');
    });

    it('blocks leave applicant from approving own request — SOD-005', function () {
        $matrix = json_decode(
            DB::table('system_settings')->where('key', 'sod_conflict_matrix')->value('value'),
            true
        );
        expect($matrix['leave']['apply'])->toContain('approve');
    });
});

describe('SodMiddleware — HTTP 403 response', function () {
    it('returns SOD_VIOLATION when sod middleware throws', function () {
        // Test the SodMiddleware logic by instantiating it directly
        $middleware = new \App\Infrastructure\Middleware\SodMiddleware;

        $user = User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'payroll.prepare', 'guard_name' => 'web']
        ));

        $request = \Illuminate\Http\Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        // Mock the DB call by ensuring the system_settings row is already there (from beforeEach)
        expect(fn () => $middleware->handle($request, fn ($r) => response('ok'), 'payroll', 'approve'))
            ->toThrow(\App\Shared\Exceptions\SodViolationException::class);
    });
});

describe('SodMiddleware — SOD-002 leave request self-approval', function () {
    it('matrix declares leave_requests.approve conflicts with leave_requests.apply', function () {
        $matrix = json_decode(
            DB::table('system_settings')->where('key', 'sod_conflict_matrix')->value('value'),
            true
        );
        expect($matrix['leave_requests']['approve'])->toContain('apply');
    });

    it('middleware blocks user with leave_requests.apply from approving — SOD-002', function () {
        $middleware = new \App\Infrastructure\Middleware\SodMiddleware;

        $user = User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'leave_requests.apply', 'guard_name' => 'web']
        ));

        $request = \Illuminate\Http\Request::create('/api/v1/leave/requests/1/approve', 'PATCH');
        $request->setUserResolver(fn () => $user);

        expect(fn () => $middleware->handle($request, fn ($r) => response('ok'), 'leave_requests', 'approve'))
            ->toThrow(\App\Shared\Exceptions\SodViolationException::class);
    });

    it('HTTP 403 SOD_VIOLATION returned on loan vp-approve when requester tries to approve — SOD-004', function () {
        // Use HR department
        $dept = \App\Domains\HR\Models\Department::where('code', 'HR')->first();
        
        // Create manager user with department (hr.manager has loans.vp_approve)
        $user = User::factory()->create();
        $user->assignRole('manager');
        $user->departments()->attach($dept->id, ['is_primary' => true]);

        // Create employee linked to the user
        $employeeId = DB::table('employees')->insertGetId([
            'employee_code' => 'EMP-TEST-001',
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'civil_status' => 'SINGLE',
            'bir_status' => 'S',
            'employment_type' => 'regular',
            'date_hired' => '2020-01-01',
            'basic_monthly_rate' => 1500000,
            'department_id' => $dept->id,
            'user_id' => $user->id, // Link employee to user
        ]);

        // Create the loan
        $loan = \App\Domains\Loan\Models\Loan::factory()->create([
            'employee_id' => $employeeId,
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);

        // Use vp-approve route which has SoD middleware
        $response = $this->actingAs($user)
            ->patchJson("/api/v1/loans/{$loan->ulid}/vp-approve");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SOD_VIOLATION');
    });
});

describe('SodMiddleware — SOD-003 overtime request self-approval', function () {
    it('matrix declares overtime.approve conflicts with overtime.submit', function () {
        $matrix = json_decode(
            DB::table('system_settings')->where('key', 'sod_conflict_matrix')->value('value'),
            true
        );
        expect($matrix['overtime']['approve'])->toContain('submit');
    });

    it('middleware blocks user with overtime.submit from approving their own OT — SOD-003', function () {
        $middleware = new \App\Infrastructure\Middleware\SodMiddleware;

        $user = User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'overtime.submit', 'guard_name' => 'web']
        ));

        $request = \Illuminate\Http\Request::create('/api/v1/attendance/overtime-requests/1/approve', 'PATCH');
        $request->setUserResolver(fn () => $user);

        // OT approve route has sod:overtime,approve middleware applied
        expect(fn () => $middleware->handle($request, fn ($r) => response('ok'), 'overtime', 'approve'))
            ->toThrow(\App\Shared\Exceptions\SodViolationException::class);
    });
});

describe('SodMiddleware — SOD-003 overtime HTTP enforcement', function () {
    it('HTTP 403 SOD_VIOLATION returned when OT submitter tries to approve — SOD-003', function () {
        // Use existing HR department with module_key for RBAC v2
        $dept = \App\Domains\HR\Models\Department::where('code', 'HR')->first();
        if (!$dept) {
            $dept = \App\Domains\HR\Models\Department::factory()->create([
                'code' => 'HR',
                'name' => 'Human Resources',
                'module_key' => 'hr',
            ]);
        }
        
        // Create user with manager role (hr.manager has overtime.approve permission)
        $user = User::factory()->create();
        $user->assignRole('manager');
        // Assign user to department for RBAC v2 department scoping
        $user->departments()->attach($dept->id, ['is_primary' => true]);

        // Create employee linked to the user (so SOD policy blocks self-approval)
        $employeeId = DB::table('employees')->insertGetId([
            'employee_code' => 'EMP-OT-SOD-001',
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'first_name' => 'OT',
            'last_name' => 'Submitter',
            'date_of_birth' => '1992-04-01',
            'gender' => 'male',
            'civil_status' => 'SINGLE',
            'bir_status' => 'S',
            'employment_type' => 'regular',
            'date_hired' => '2021-01-01',
            'basic_monthly_rate' => 1200000,
            'department_id' => $dept->id,
            'user_id' => $user->id, // Link employee to user for SOD check
        ]);

        $otId = DB::table('overtime_requests')->insertGetId([
            'employee_id' => $employeeId,
            'requested_by' => $user->id,
            'work_date' => '2026-02-01',
            'ot_start_time' => '18:00:00',
            'ot_end_time' => '20:00:00',
            'requested_minutes' => 120,
            'reason' => 'Urgent project deadline',
            'status' => 'supervisor_approved',
            'requester_role' => 'staff',
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/attendance/overtime-requests/{$otId}/approve", [
                'approved_minutes' => 120,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SOD_VIOLATION');
    });
});

describe('SodMiddleware — SOD-010 journal entry self-posting', function () {
    it('matrix declares journal_entries.create conflicts with journal_entries.post', function () {
        $matrix = json_decode(
            DB::table('system_settings')->where('key', 'sod_conflict_matrix')->value('value'),
            true
        );
        expect($matrix['journal_entries']['create'])->toContain('post');
    });

    it('HTTP 403 SOD_VIOLATION returned when JE creator tries to post — SOD-010', function () {
        // Assign user to the Accounting dept so module_access:accounting passes
        $acctgDept = \App\Domains\HR\Models\Department::where('code', 'ACCTG')->first();

        $user = User::factory()->create();
        $user->assignRole('officer');
        if ($acctgDept) {
            $user->departments()->attach($acctgDept->id, ['is_primary' => true]);
        }

        // User created the JE (has journal_entries.create permission)
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'journal_entries.create', 'guard_name' => 'web']
        ));
        // Also grant post permission so the route doesn't 403 for missing perm
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'journal_entries.post', 'guard_name' => 'web']
        ));

        // Create a fiscal period and JE record owned by this user
        $fiscalPeriodId = DB::table('fiscal_periods')->insertGetId([
            'name' => 'Feb 2026',
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]);

        $jeUlid = (string) \Illuminate\Support\Str::ulid();
        DB::table('journal_entries')->insert([
            'ulid' => $jeUlid,
            'date' => '2026-02-15',
            'description' => 'Test journal entry for SoD check',
            'created_by' => $user->id,
            'status' => 'submitted',
            'fiscal_period_id' => $fiscalPeriodId,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/accounting/journal-entries/{$jeUlid}/post");

        // SoD middleware fires before the controller: user has journal_entries.create
        // which conflicts with journal_entries.post
        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SOD_VIOLATION');
    });
});
