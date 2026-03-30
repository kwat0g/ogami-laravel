<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\DepartmentModuleAssignmentSeeder;
use Database\Seeders\DepartmentPositionSeeder;
use Database\Seeders\ModulePermissionSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| RBAC Access Control — Feature Tests
|--------------------------------------------------------------------------
| Verifies that each of the 6 canonical roles can only perform actions
| within its authority level (§4 of roadmap).
|
| 6-Role model (Option B — department-scoped manager split):
|   admin              — full access
|   executive          — view + export only
|   manager         — full HR/payroll CRUD + approve (HR/payroll modules, dept-scoped)
|   officer — full accounting CRUD + post (finance modules, dept-scoped)
|   head         — view + create + submit; no approve/post/delete
|   staff              — self-service only
|
| Cross-domain isolation is handled by RDAC (department scope), not by
| role names. manager/officer can only see their own
| department's data, but routes are open to those roles regardless of department.
--------------------------------------------------------------------------
*/

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['super_admin', 'admin', 'executive', 'vice_president', 'manager', 'officer', 'head', 'staff'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);
});

/**
 * Create an authenticated user with the given role.
 */
function rbacUser(string $role, ?string $deptCode = null, array $extraPermissions = []): User
{
    $user = User::factory()->create(['password' => Hash::make('RbacPass!789')]);
    $user->assignRole($role);

    if ($deptCode !== null) {
        $dept = Department::where('code', $deptCode)->firstOrFail();
        $user->departments()->attach($dept->id, ['is_primary' => true]);
        $user->update(['department_id' => $dept->id]);
    }

    foreach ($extraPermissions as $perm) {
        $user->givePermissionTo($perm);
    }

    return $user->fresh();
}

// ---------------------------------------------------------------------------
// Admin — unrestricted access to all domains
// ---------------------------------------------------------------------------

describe('Admin unrestricted access', function () {
    it('can access HR, Payroll, and Finance endpoints', function () {
        $user = rbacUser('admin');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/employees')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/payroll/runs')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertStatus(200);
    });
});

// ---------------------------------------------------------------------------
// manager — full CRUD for HR & payroll modules (dept-scoped by RDAC)
// officer — full CRUD for finance modules (dept-scoped by RDAC)
// ---------------------------------------------------------------------------

describe('Manager authorised access', function () {
    it('can list employees', function () {
        $user = rbacUser('manager', 'HR');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/employees')
            ->assertStatus(200);
    });

    it('can create an employee', function () {
        $user = rbacUser('manager', 'HR');
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/employees', [
                'employee_code' => 'RBAC-001',
                'first_name' => 'Rbac',
                'last_name' => 'Tester',
                'date_of_birth' => '1990-05-15',
                'gender' => 'male',
                'employment_type' => 'regular',
                'employment_status' => 'active',
                'pay_basis' => 'monthly',
                'basic_monthly_rate' => 2_500_000,
                'civil_status' => 'SINGLE',
                'date_hired' => '2025-01-01',
            ])
            ->assertStatus(201);
    });

    it('can list journal entries', function () {
        $user = rbacUser('officer', 'ACCTG');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertStatus(200);
    });

    it('can access payroll runs', function () {
        $user = rbacUser('manager', 'HR');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/payroll/runs')
            ->assertStatus(200);
    });
});

// ---------------------------------------------------------------------------
// Supervisor — view + create; cannot approve/post/delete
// ---------------------------------------------------------------------------

describe('Supervisor restricted access', function () {
    it('can list employees', function () {
        $user = rbacUser('head', 'HR');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/employees')
            ->assertStatus(200);
    });

    it('cannot update system settings — system_settings.update denied', function () {
        $user = rbacUser('head');
        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/admin/settings/company_name', ['value' => 'test'])
            ->assertStatus(403);
    });
});

// ---------------------------------------------------------------------------
// Executive — view-only across all modules
// ---------------------------------------------------------------------------

describe('Executive view-only access', function () {
    it('can list employees (read)', function () {
        $user = rbacUser('executive');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/employees')
            ->assertStatus(200);
    });

    it('receives 403 when trying to create an employee', function () {
        $user = rbacUser('executive');
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/hr/employees', [
                'employee_code' => 'EXEC-001',
                'first_name' => 'Exec',
                'last_name' => 'Test',
            ])
            ->assertStatus(403);
    });

    it('receives 403 when trying to initiate a payroll run', function () {
        $user = rbacUser('executive');
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payroll/runs', [
                'cutoff_start' => '2025-10-01',
                'cutoff_end' => '2025-10-15',
                'run_type' => 'regular',
            ])
            ->assertStatus(403);
    });
});

// ---------------------------------------------------------------------------
// Staff — self-service only; blocked from all management endpoints
// ---------------------------------------------------------------------------

describe('Staff self-service restrictions', function () {
    it('receives 403 when accessing employee management', function () {
        $user = rbacUser('staff');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hr/employees')
            ->assertStatus(403);
    });

    it('receives 403 when accessing journal entries', function () {
        $user = rbacUser('staff');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertStatus(403);
    });

    it('receives 403 when accessing payroll run list', function () {
        $user = rbacUser('staff');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/payroll/runs')
            ->assertStatus(403);
    });

    it('can view their own leave requests (self-service)', function () {
        $user = rbacUser('staff', 'HR');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/leave/requests')
            ->assertStatus(200);
    });
});

// ---------------------------------------------------------------------------
// SoD — same manager cannot create AND approve the same payroll run
// SOD-005 / SOD-006
// ---------------------------------------------------------------------------

describe('Payroll SoD enforcement', function () {
    it('manager cannot HR-approve a payroll run they created — SOD-006', function () {
        $creator = rbacUser('manager', 'HR');
        $run = PayrollRun::create([
            'reference_no' => 'PR-RBAC-SOD001',
            'pay_period_label' => 'RBAC SoD Test',
            'cutoff_start' => '2025-10-01',
            'cutoff_end' => '2025-10-15',
            'pay_date' => '2025-10-31',
            'status' => 'SUBMITTED',
            'run_type' => 'regular',
            'created_by' => $creator->id,
        ]);
        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/payroll/runs/{$run->ulid}/hr-approve", [
                'action' => 'APPROVED',
                'checkboxes_checked' => ['totals_verified', 'exceptions_reviewed', 'attachments_checked'],
            ])
            ->assertStatus(403);
    });

    it('a different accounting manager can approve a run created by HR — SOD passes', function () {
        $creator = rbacUser('manager', 'HR');
        $approver = rbacUser('manager', 'ACCTG');
        $run = PayrollRun::create([
            'reference_no' => 'PR-RBAC-SOD002',
            'pay_period_label' => 'RBAC SoD Pass Test',
            'cutoff_start' => '2025-10-16',
            'cutoff_end' => '2025-10-31',
            'pay_date' => '2025-11-15',
            'status' => 'HR_APPROVED',
            'run_type' => 'regular',
            'created_by' => $creator->id,
        ]);
        $this->actingAs($approver, 'sanctum')
            ->postJson("/api/v1/payroll/runs/{$run->ulid}/acctg-approve", [
                'action' => 'APPROVED',
                'checkboxes_checked' => ['totals_verified', 'gl_preview_checked', 'bank_file_reviewed'],
            ])
            ->assertStatus(200);
    });
});

// ---------------------------------------------------------------------------
// Unauthenticated access — all domains reject 401
// ---------------------------------------------------------------------------

describe('Unauthenticated requests', function () {
    it('returns 401 for unauthenticated HR access', function () {
        $this->getJson('/api/v1/hr/employees')->assertStatus(401);
    });

    it('returns 401 for unauthenticated payroll access', function () {
        $this->getJson('/api/v1/payroll/runs')->assertStatus(401);
    });

    it('returns 401 for unauthenticated finance access', function () {
        $this->getJson('/api/v1/accounting/journal-entries')->assertStatus(401);
    });
});
