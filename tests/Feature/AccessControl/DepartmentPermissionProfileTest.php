<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Models\DepartmentPermissionProfile;
use App\Models\User;
use App\Services\DepartmentPermissionServiceV3 as DepartmentPermissionService;
use Database\Seeders\ModulePermissionSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Department Permission Profile Tests — v2
|--------------------------------------------------------------------------
| Verifies the DB-backed department permission profile system:
|
|   1. HRD Manager    — gets full HR module, NOT accounting
|   2. ACCTG Manager  — gets full accounting, NOT HR module
|   3. Ops Manager    — self-service + team view only, NO module access
|   4. HRD Supervisor — limited HR (no approve/delete)
|   5. ACCTG Supervisor — limited accounting (no post/approve)
|   6. Staff          — self-service only, always, regardless of dept
|   7. Admin/Executive — bypass dept scoping entirely
|   8. Profile caching — cache hit avoids extra DB queries
|   9. Dept isolation  — permission NOT allowed if dept profile lacks it
|  10. getEffectivePermissions() — returns correct intersection for frontend
|
| All permission names use the EXISTING Spatie slugs (not v2 doc names)
| so this test suite stays compatible with all existing policies and tests.
--------------------------------------------------------------------------
*/

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a department with the given code (idempotent).
 */
function dppDept(string $code): Department
{
    return Department::firstOrCreate(
        ['code' => $code],
        ['name' => "DPP Test {$code}", 'is_active' => true],
    );
}

/**
 * Upsert a DepartmentPermissionProfile for the given dept + role.
 */
function dppProfile(Department $dept, string $role, array $permissions): DepartmentPermissionProfile
{
    return DepartmentPermissionProfile::updateOrCreate(
        ['department_id' => $dept->id, 'role' => $role],
        ['permissions' => $permissions, 'profile_label' => "{$dept->code} {$role}", 'is_active' => true],
    );
}

/**
 * Create a user with the given role, assigned to the given department.
 */
function dppUser(string $role, Department $dept): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $user = User::factory()->create(['password' => Hash::make('DppTest!999')]);
    $user->assignRole($role);

    DB::table('user_department_access')->insertOrIgnore([
        'user_id' => $user->id,
        'department_id' => $dept->id,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Bust caches so fresh DB reads are made
    $user->clearDepartmentCache();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

// HRD module permissions (manager level)
function hrdManagerPerms(): array
{
    return [
        'employees.view', 'employees.view_full_record', 'employees.view_salary',
        'employees.view_unmasked_gov_ids', 'employees.create', 'employees.update',
        'employees.update_salary', 'employees.activate', 'employees.suspend', 'employees.terminate',
        'attendance.import_csv', 'attendance.resolve_anomalies', 'attendance.view_team',
        'leaves.manager_approve', 'leaves.reject', 'leaves.adjust_balance',
        'overtime.approve', 'overtime.reject',
        'loans.hr_approve', 'loans.create', 'loans.approve',
        'payroll.initiate', 'payroll.hr_approve', 'payroll.hr_return',
        'payroll.compute', 'payroll.publish', 'payroll.download_register',
        'reports.bir_2316', 'reports.bir_alphalist', 'reports.bir_1601c',
        'reports.sss_sbr2', 'reports.philhealth_rf1', 'reports.pagibig_mc',
        // Self-service always included
        'payroll.view_own_payslip', 'payroll.download_own_payslip',
        'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
        'loans.view_own', 'loans.apply', 'attendance.view_own',
        'self.view_profile', 'self.submit_profile_update',
    ];
}

// ACCTG module permissions (manager level)
function acctgManagerPerms(): array
{
    return [
        'journal_entries.view', 'journal_entries.create', 'journal_entries.update',
        'journal_entries.submit', 'journal_entries.post', 'journal_entries.reverse',
        'chart_of_accounts.view', 'chart_of_accounts.manage',
        'fiscal_periods.view', 'fiscal_periods.manage',
        'vendors.view', 'vendors.manage',
        'vendor_invoices.view', 'vendor_invoices.create', 'vendor_invoices.approve',
        'vendor_invoices.reject', 'vendor_invoices.record_payment',
        'customers.view', 'customers.manage',
        'customer_invoices.view', 'customer_invoices.create', 'customer_invoices.approve',
        'bank_accounts.view', 'bank_reconciliations.view', 'bank_reconciliations.certify',
        'reports.financial_statements', 'reports.gl', 'reports.trial_balance',
        'reports.ap_aging', 'reports.ar_aging', 'reports.vat', 'reports.bank_reconciliation',
        'payroll.acctg_approve', 'payroll.acctg_reject', 'payroll.disburse',
        'payroll.download_bank_file', 'payroll.post',
        'loans.accounting_approve',
        // Self-service
        'payroll.view_own_payslip', 'payroll.download_own_payslip',
        'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
        'loans.view_own', 'loans.apply', 'attendance.view_own',
        'self.view_profile', 'self.submit_profile_update',
    ];
}

// Self-service only (ops dept) — per v2 doc, ops managers do NOT approve leave/OT yet.
// HR Manager is the approval authority for ALL departments currently.
// Leave/OT approval will be unlocked for ops managers in a future phase via profile update.
function selfServicePerms(): array
{
    return [
        'self.view_profile', 'self.submit_profile_update', 'self.view_attendance',
        'payroll.view_own_payslip', 'payroll.download_own_payslip',
        'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
        'loans.view_own', 'loans.apply', 'attendance.view_own',
        // Team view (names + positions; no approve authority)
        'employees.view', 'employees.view_full_record', 'employees.view_masked_gov_ids',
        'attendance.view_team', 'attendance.view_anomalies',
        'overtime.view', 'overtime.submit',
        'leaves.view_team', 'leaves.file_on_behalf',
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// Setup
// ──────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Cache::flush();

    foreach (['super_admin', 'admin', 'executive', 'vice_president', 'manager', 'officer', 'head', 'staff'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);

    Permission::findOrCreate('system.assign_roles', 'web');

    Role::findByName('admin', 'web')->givePermissionTo('system.assign_roles');
    Role::findByName('executive', 'web')->givePermissionTo([
        Permission::findOrCreate('employees.view', 'web'),
        Permission::findOrCreate('journal_entries.view', 'web'),
    ]);
});

// ══════════════════════════════════════════════════════════════════════════════
// 1. HRD Manager — gets HR module, NOT accounting
// ══════════════════════════════════════════════════════════════════════════════

describe('HRD Manager — profile permissions', function () {

    it('hasPermissionTo returns true for HR module permissions', function () {
        $dept = dppDept('DPP-HRD');
        dppProfile($dept, 'manager', hrdManagerPerms());
        $user = dppUser('manager', $dept);

        expect($user->hasPermissionTo('employees.create'))->toBeTrue();
        expect($user->hasPermissionTo('employees.view_salary'))->toBeTrue();
        expect($user->hasPermissionTo('payroll.initiate'))->toBeTrue();
        expect($user->hasPermissionTo('payroll.hr_approve'))->toBeTrue();
        expect($user->hasPermissionTo('leaves.manager_approve'))->toBeTrue();
        expect($user->hasPermissionTo('overtime.approve'))->toBeTrue();
        expect($user->hasPermissionTo('loans.hr_approve'))->toBeTrue();
        expect($user->hasPermissionTo('reports.bir_2316'))->toBeTrue();
    });

    it('hasPermissionTo denies accounting module permissions', function () {
        $dept = dppDept('DPP-HRD');
        dppProfile($dept, 'manager', hrdManagerPerms());
        $user = dppUser('manager', $dept);

        expect($user->hasPermissionTo('journal_entries.post'))->toBeFalse();
        expect($user->hasPermissionTo('journal_entries.view'))->toBeFalse();
        expect($user->hasPermissionTo('vendor_invoices.approve'))->toBeFalse();
        expect($user->hasPermissionTo('reports.financial_statements'))->toBeFalse();
        expect($user->hasPermissionTo('reports.gl'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.acctg_approve'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.disburse'))->toBeFalse();
    });

    it('DPP profile restricts permissions even when Spatie role grants more than the profile allows', function () {
        // Assign a manager (who has journal_entries.post via accounting module perms) to an HRD dept
        // with an HRD-only profile — DPP must block the accounting permission.
        $dept = dppDept('DPP-HRD-CROSS');
        dppProfile($dept, 'manager', hrdManagerPerms()); // profile excludes accounting perms
        $user = dppUser('manager', $dept);

        // Verify the manager Spatie ROLE genuinely has the permission
        $acctgRole = Role::findByName('manager');
        expect($acctgRole->hasPermissionTo('journal_entries.post'))->toBeTrue();

        // But User::hasPermissionTo() (our override) returns false — HRD profile lacks it
        expect($user->hasPermissionTo('journal_entries.post'))->toBeFalse();
    });

    it('getEffectivePermissions returns HR perms and excludes accounting', function () {
        $dept = dppDept('DPP-HRD');
        dppProfile($dept, 'manager', hrdManagerPerms());
        $user = dppUser('manager', $dept);

        $perms = $user->getEffectivePermissions()->all();

        expect($perms)->toContain('payroll.initiate');
        expect($perms)->toContain('leaves.manager_approve');
        expect($perms)->toContain('employees.view_salary');

        expect($perms)->not->toContain('journal_entries.post');
        expect($perms)->not->toContain('vendor_invoices.approve');
        expect($perms)->not->toContain('reports.financial_statements');
        expect($perms)->not->toContain('payroll.acctg_approve');
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 2. ACCTG Manager — gets accounting module, NOT HR
// ══════════════════════════════════════════════════════════════════════════════

describe('ACCTG Manager — profile permissions', function () {

    it('hasPermissionTo returns true for accounting module permissions', function () {
        $dept = dppDept('DPP-ACCTG');
        dppProfile($dept, 'officer', acctgManagerPerms());
        $user = dppUser('officer', $dept);

        expect($user->hasPermissionTo('journal_entries.view'))->toBeTrue();
        expect($user->hasPermissionTo('journal_entries.post'))->toBeTrue();
        expect($user->hasPermissionTo('vendor_invoices.approve'))->toBeTrue();
        expect($user->hasPermissionTo('reports.financial_statements'))->toBeTrue();
        expect($user->hasPermissionTo('payroll.acctg_approve'))->toBeTrue();
        expect($user->hasPermissionTo('payroll.disburse'))->toBeTrue();
        expect($user->hasPermissionTo('loans.accounting_approve'))->toBeTrue();
    });

    it('hasPermissionTo denies HR module permissions', function () {
        $dept = dppDept('DPP-ACCTG');
        dppProfile($dept, 'officer', acctgManagerPerms());
        $user = dppUser('officer', $dept);

        expect($user->hasPermissionTo('payroll.initiate'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.hr_approve'))->toBeFalse();
        expect($user->hasPermissionTo('employees.view_salary'))->toBeFalse();
        expect($user->hasPermissionTo('employees.view_unmasked_gov_ids'))->toBeFalse();
        expect($user->hasPermissionTo('employees.create'))->toBeFalse();
        expect($user->hasPermissionTo('leaves.adjust_balance'))->toBeFalse();
        expect($user->hasPermissionTo('loans.hr_approve'))->toBeFalse();
        expect($user->hasPermissionTo('reports.bir_2316'))->toBeFalse();
    });

    it('getEffectivePermissions returns accounting perms and excludes HR module', function () {
        $dept = dppDept('DPP-ACCTG');
        dppProfile($dept, 'officer', acctgManagerPerms());
        $user = dppUser('officer', $dept);

        $perms = $user->getEffectivePermissions()->all();

        expect($perms)->toContain('journal_entries.post');
        expect($perms)->toContain('vendor_invoices.approve');
        expect($perms)->toContain('reports.financial_statements');

        expect($perms)->not->toContain('payroll.initiate');
        expect($perms)->not->toContain('employees.view_salary');
        expect($perms)->not->toContain('employees.view_unmasked_gov_ids');
        expect($perms)->not->toContain('reports.bir_2316');
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 3. Ops Manager (PROD) — self-service + team only, no module access
// ══════════════════════════════════════════════════════════════════════════════

describe('Ops Manager (PROD) — self-service only profile', function () {

    it('hasPermissionTo allows self-service permissions', function () {
        $dept = dppDept('DPP-PROD');
        dppProfile($dept, 'manager', selfServicePerms());
        $user = dppUser('manager', $dept);

        expect($user->hasPermissionTo('payroll.view_own_payslip'))->toBeTrue();
        expect($user->hasPermissionTo('leaves.view_own'))->toBeTrue();
        expect($user->hasPermissionTo('loans.view_own'))->toBeTrue();
        expect($user->hasPermissionTo('attendance.view_own'))->toBeTrue();
        expect($user->hasPermissionTo('self.view_profile'))->toBeTrue();
    });

    it('hasPermissionTo denies HR module permissions', function () {
        $dept = dppDept('DPP-PROD');
        dppProfile($dept, 'manager', selfServicePerms());
        $user = dppUser('manager', $dept);

        expect($user->hasPermissionTo('employees.view_salary'))->toBeFalse();
        expect($user->hasPermissionTo('employees.create'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.initiate'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.hr_approve'))->toBeFalse();
        expect($user->hasPermissionTo('attendance.import_csv'))->toBeFalse();
        expect($user->hasPermissionTo('leaves.adjust_balance'))->toBeFalse();
    });

    it('hasPermissionTo denies accounting module permissions', function () {
        $dept = dppDept('DPP-PROD');
        dppProfile($dept, 'manager', selfServicePerms());
        $user = dppUser('manager', $dept);

        expect($user->hasPermissionTo('journal_entries.view'))->toBeFalse();
        expect($user->hasPermissionTo('journal_entries.post'))->toBeFalse();
        expect($user->hasPermissionTo('vendor_invoices.approve'))->toBeFalse();
        expect($user->hasPermissionTo('reports.financial_statements'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.acctg_approve'))->toBeFalse();
    });

    it('getEffectivePermissions contains only self-service + team perms', function () {
        $dept = dppDept('DPP-PROD');
        dppProfile($dept, 'manager', selfServicePerms());
        $user = dppUser('manager', $dept);

        $perms = $user->getEffectivePermissions()->all();

        // Self-service allowed
        expect($perms)->toContain('payroll.view_own_payslip');
        expect($perms)->toContain('leaves.view_own');

        // Module access denied
        expect($perms)->not->toContain('payroll.initiate');
        expect($perms)->not->toContain('employees.view_salary');
        expect($perms)->not->toContain('journal_entries.post');
        expect($perms)->not->toContain('vendor_invoices.approve');
        expect($perms)->not->toContain('reports.financial_statements');
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 4. HRD Supervisor — limited HR (no approve, no delete)
// ══════════════════════════════════════════════════════════════════════════════

describe('HRD Supervisor — limited HR profile', function () {
    beforeEach(function () {
        $this->dept = dppDept('DPP-HRD-SUP');
        dppProfile($this->dept, 'head', [
            'employees.view', 'employees.view_full_record', 'employees.view_unmasked_gov_ids',
            'employees.create', 'employees.upload_documents', 'employees.download_documents',
            'attendance.import_csv', 'attendance.view_team', 'attendance.view_anomalies',
            'attendance.resolve_anomalies', 'attendance.manage_shifts',
            'overtime.view', 'overtime.submit',
            'leaves.view_own', 'leaves.view_team', 'leaves.file_own',
            'leaves.file_on_behalf', 'leaves.cancel',
            'loans.view_own', 'loans.apply', 'loans.head_review',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'attendance.view_own', 'self.view_profile', 'self.submit_profile_update',
        ]);
        $this->user = dppUser('head', $this->dept);
    });

    it('can create employees and import attendance', function () {
        expect($this->user->hasPermissionTo('employees.create'))->toBeTrue();
        expect($this->user->hasPermissionTo('attendance.import_csv'))->toBeTrue();
        expect($this->user->hasPermissionTo('attendance.resolve_anomalies'))->toBeTrue();
    });

    it('cannot approve leave, overtime, or loans', function () {
        expect($this->user->hasPermissionTo('leaves.manager_approve'))->toBeFalse();
        expect($this->user->hasPermissionTo('overtime.approve'))->toBeFalse();
        expect($this->user->hasPermissionTo('loans.hr_approve'))->toBeFalse();
    });

    it('cannot access payroll initiation or accounting', function () {
        expect($this->user->hasPermissionTo('payroll.initiate'))->toBeFalse();
        expect($this->user->hasPermissionTo('payroll.hr_approve'))->toBeFalse();
        expect($this->user->hasPermissionTo('journal_entries.view'))->toBeFalse();
        expect($this->user->hasPermissionTo('vendor_invoices.approve'))->toBeFalse();
    });

    it('cannot update salary or terminate employees', function () {
        expect($this->user->hasPermissionTo('employees.update_salary'))->toBeFalse();
        expect($this->user->hasPermissionTo('employees.terminate'))->toBeFalse();
        expect($this->user->hasPermissionTo('employees.suspend'))->toBeFalse();
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 5. ACCTG Supervisor — limited accounting (no post, no approve)
// ══════════════════════════════════════════════════════════════════════════════

describe('ACCTG Supervisor — limited accounting profile', function () {
    beforeEach(function () {
        $this->dept = dppDept('DPP-ACCTG-SUP');
        dppProfile($this->dept, 'officer', [
            'journal_entries.view', 'journal_entries.create',
            'journal_entries.update', 'journal_entries.submit',
            'chart_of_accounts.view', 'fiscal_periods.view',
            'vendors.view',
            'vendor_invoices.view', 'vendor_invoices.create',
            'vendor_invoices.update', 'vendor_invoices.export',
            'vendor_payments.view',
            'customers.view',
            'customer_invoices.view', 'customer_invoices.create',
            'customer_invoices.update', 'customer_invoices.export',
            'reports.gl', 'reports.ap_aging',
            'payroll.view_own_payslip', 'payroll.download_own_payslip',
            'leaves.view_own', 'leaves.file_own', 'leaves.cancel',
            'loans.view_own', 'loans.apply', 'loans.head_review',
            'attendance.view_own', 'self.view_profile',
        ]);
        $this->user = dppUser('officer', $this->dept);
    });

    it('can create and submit journal entries', function () {
        expect($this->user->hasPermissionTo('journal_entries.create'))->toBeTrue();
        expect($this->user->hasPermissionTo('journal_entries.submit'))->toBeTrue();
        expect($this->user->hasPermissionTo('vendor_invoices.create'))->toBeTrue();
        expect($this->user->hasPermissionTo('customer_invoices.create'))->toBeTrue();
    });

    it('cannot post journal entries or approve invoices', function () {
        expect($this->user->hasPermissionTo('journal_entries.post'))->toBeFalse();
        expect($this->user->hasPermissionTo('journal_entries.reverse'))->toBeFalse();
        expect($this->user->hasPermissionTo('vendor_invoices.approve'))->toBeFalse();
        expect($this->user->hasPermissionTo('customer_invoices.approve'))->toBeFalse();
    });

    it('cannot access HR, payroll management, or financial reports', function () {
        expect($this->user->hasPermissionTo('employees.create'))->toBeFalse();
        expect($this->user->hasPermissionTo('leaves.manager_approve'))->toBeFalse();
        expect($this->user->hasPermissionTo('payroll.acctg_approve'))->toBeFalse();
        expect($this->user->hasPermissionTo('reports.financial_statements'))->toBeFalse();
        expect($this->user->hasPermissionTo('reports.trial_balance'))->toBeFalse();
        expect($this->user->hasPermissionTo('bank_reconciliations.certify'))->toBeFalse();
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 6. Staff — self-service only, always, regardless of department
// ══════════════════════════════════════════════════════════════════════════════

describe('Staff — self-service only regardless of department', function () {

    it('staff in HRD gets only their own access — no HR admin permissions', function () {
        $dept = dppDept('DPP-STAFF-HRD');
        // Even if a profile exists for the dept, staff bypasses dept scoping
        dppProfile($dept, 'manager', hrdManagerPerms());
        $user = dppUser('staff', $dept);

        // Staff has no module permissions at all (Spatie role)
        expect($user->hasPermissionTo('employees.view'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.initiate'))->toBeFalse();
        expect($user->hasPermissionTo('leaves.manager_approve'))->toBeFalse();
    });

    it('staff in ACCTG gets only their own access — no accounting permissions', function () {
        $dept = dppDept('DPP-STAFF-ACCTG');
        dppProfile($dept, 'officer', acctgManagerPerms());
        $user = dppUser('staff', $dept);

        expect($user->hasPermissionTo('journal_entries.view'))->toBeFalse();
        expect($user->hasPermissionTo('vendor_invoices.approve'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.acctg_approve'))->toBeFalse();
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 7. Admin / Executive — bypass dept scoping entirely
// ══════════════════════════════════════════════════════════════════════════════

describe('Admin and Executive bypass department scoping', function () {

    it('admin hasPermissionTo ignores dept profiles entirely', function () {
        // Admin has NO depts and NO module permissions in Spatie — bypasses by role check
        $user = User::factory()->create();
        $user->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Admin role has system.* permissions — spot check two
        expect($user->hasPermissionTo('system.assign_roles'))->toBeTrue();
        // Admin role does NOT have business permissions at all
        expect($user->hasPermissionTo('employees.view'))->toBeFalse();
        expect($user->hasPermissionTo('journal_entries.post'))->toBeFalse();
    });

    it('executive can call hasPermissionTo on any permission they have without dept filter', function () {
        $dept = dppDept('DPP-EXEC');
        // Even with a restrictive dept profile, executive bypasses it
        dppProfile($dept, 'manager', selfServicePerms());
        $user = User::factory()->create();
        $user->assignRole('executive');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Executive role has read-only perms — department scoping is bypassed
        expect($user->hasPermissionTo('employees.view'))->toBeTrue();
        expect($user->hasPermissionTo('journal_entries.view'))->toBeTrue();
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 8. Profile caching — result cached, DB not hit on second call
// ══════════════════════════════════════════════════════════════════════════════

describe('Profile permission caching', function () {

    it('caches permission resolution and returns same result on second call', function () {
        $dept = dppDept('DPP-CACHE');
        dppProfile($dept, 'manager', hrdManagerPerms());
        $user = dppUser('manager', $dept);
        Cache::flush();

        // First call — DB hit, result cached
        $result1 = $user->hasPermissionTo('payroll.initiate');
        // Second call — should come from cache
        $result2 = $user->hasPermissionTo('payroll.initiate');

        expect($result1)->toBeTrue();
        expect($result2)->toBeTrue();
    });

    it('clearDepartmentCache flushes the cache so next call re-reads DB', function () {
        $dept = dppDept('DPP-CACHE2');
        dppProfile($dept, 'manager', hrdManagerPerms());
        $user = dppUser('manager', $dept);
        Cache::flush();

        // Warm the cache
        $user->hasPermissionTo('payroll.initiate');

        // Update the profile behind the scenes
        DepartmentPermissionProfile::where('department_id', $dept->id)
            ->where('role', 'manager')
            ->update(['permissions' => json_encode(selfServicePerms())]);

        // Without cache clear — still cached, old result
        $cached = $user->hasPermissionTo('payroll.initiate');
        expect($cached)->toBeTrue();

        // After clearing cache — fresh DB read gives new result
        $user->clearDepartmentCache();
        $fresh = $user->fresh()->hasPermissionTo('payroll.initiate');
        expect($fresh)->toBeFalse();
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 9. Department isolation — wrong dept = no access
// ══════════════════════════════════════════════════════════════════════════════

describe('Department isolation', function () {

    it('manager in PROD cannot use HRD permissions even if HRD profile exists', function () {
        $hrd = dppDept('DPP-ISO-HRD');
        $prod = dppDept('DPP-ISO-PROD');

        dppProfile($hrd, 'manager', hrdManagerPerms());
        dppProfile($prod, 'manager', selfServicePerms()); // ops-only

        // User is assigned to PROD only — NOT HRD
        $user = dppUser('manager', $prod);

        expect($user->hasPermissionTo('payroll.initiate'))->toBeFalse();
        expect($user->hasPermissionTo('employees.view_salary'))->toBeFalse();
        expect($user->hasPermissionTo('leaves.manager_approve'))->toBeFalse();
    });

    it('manager in ACCTG cannot use HRD permissions', function () {
        $hrd = dppDept('DPP-ISO-HRD2');
        $acctg = dppDept('DPP-ISO-ACCTG');

        dppProfile($hrd, 'manager', hrdManagerPerms());
        dppProfile($acctg, 'officer', acctgManagerPerms());

        $user = dppUser('officer', $acctg);

        // ACCTG-specific allowed
        expect($user->hasPermissionTo('journal_entries.post'))->toBeTrue();
        expect($user->hasPermissionTo('payroll.acctg_approve'))->toBeTrue();

        // HRD-specific denied
        expect($user->hasPermissionTo('payroll.initiate'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.hr_approve'))->toBeFalse();
        expect($user->hasPermissionTo('employees.view_salary'))->toBeFalse();
    });

    it('is_active=false profile falls back to safe defaults instead of full module permissions', function () {
        $dept = dppDept('DPP-INACTIVE');
        $profile = dppProfile($dept, 'manager', hrdManagerPerms());

        // Disable the profile
        $profile->update(['is_active' => false]);

        $user = dppUser('manager', $dept);
        Cache::flush();
        $user->clearDepartmentCache();
        $user = $user->fresh();

        // Inactive profiles do not re-enable the full manager permission matrix.
        // The resolver stays in the department-scoped path and falls back to safe defaults.
        $result = $user->hasPermissionTo('payroll.initiate');
        expect($result)->toBeFalse();
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 10.  getEffectivePermissions() — correct intersection for frontend
// ══════════════════════════════════════════════════════════════════════════════

describe('getEffectivePermissions — frontend permission list', function () {

    it('returns only HR module perms for HRD Manager (not accounting)', function () {
        $dept = dppDept('DPP-EFF-HRD');
        dppProfile($dept, 'manager', hrdManagerPerms());
        $user = dppUser('manager', $dept);

        $perms = $user->getEffectivePermissions()->all();

        expect($perms)->toContain('payroll.initiate');
        expect($perms)->toContain('employees.view_salary');
        expect($perms)->not->toContain('journal_entries.post');
        expect($perms)->not->toContain('vendor_invoices.approve');
    });

    it('returns only accounting perms for ACCTG Manager (not HR)', function () {
        $dept = dppDept('DPP-EFF-ACCTG');
        dppProfile($dept, 'officer', acctgManagerPerms());
        $user = dppUser('officer', $dept);

        $perms = $user->getEffectivePermissions()->all();

        expect($perms)->toContain('journal_entries.post');
        expect($perms)->toContain('vendor_invoices.approve');
        expect($perms)->not->toContain('payroll.initiate');
        expect($perms)->not->toContain('employees.view_salary');
    });

    it('returns minimal perms for Ops Manager (PROD)', function () {
        $dept = dppDept('DPP-EFF-PROD');
        dppProfile($dept, 'manager', selfServicePerms());
        $user = dppUser('manager', $dept);

        $perms = $user->getEffectivePermissions()->all();

        expect($perms)->toContain('payroll.view_own_payslip');
        expect($perms)->toContain('leaves.view_own');
        expect($perms)->not->toContain('payroll.initiate');
        expect($perms)->not->toContain('journal_entries.post');
    });

    it('bypasses department scoping for admin', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perms = $user->getEffectivePermissions()->all();

        // Admin gets all their Spatie permissions unfiltered
        expect($perms)->toContain('system.assign_roles');
    });

    it('returns safe default scoped perms for staff without a department profile', function () {
        $dept = dppDept('DPP-EFF-STAFF');
        $user = dppUser('staff', $dept);

        $perms = $user->getEffectivePermissions()->all();

        expect($perms)->toContain('self.*');
        expect($perms)->not->toContain('employees.view');
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// Service-level unit tests
// ══════════════════════════════════════════════════════════════════════════════

describe('DepartmentPermissionService — DB-backed resolution', function () {

    it('isRoleDepartmentScoped returns true when active profile exists', function () {
        $dept = dppDept('DPP-SVC-SCOPED');
        dppProfile($dept, 'manager', hrdManagerPerms());

        // Flush the scoped cache
        Cache::forget('dept_scoped_v2:manager');

        expect(DepartmentPermissionService::isRoleDepartmentScoped('manager'))->toBeTrue();
    });

    it('getAllowedPermissions merges permissions from multiple departments', function () {
        $hrd = dppDept('DPP-MULTI-HRD');
        $acctg = dppDept('DPP-MULTI-ACCTG');

        dppProfile($hrd, 'manager', ['payroll.initiate', 'leaves.manager_approve', 'payroll.view_own_payslip']);
        dppProfile($acctg, 'manager', ['journal_entries.post', 'vendor_invoices.approve', 'payroll.view_own_payslip']);

        Cache::flush();

        $allowed = DepartmentPermissionService::getAllowedPermissions(
            'manager',
            [$hrd->code, $acctg->code]
        );

        // Should contain permissions from both profiles
        expect($allowed)->toContain('payroll.initiate');
        expect($allowed)->toContain('journal_entries.post');
        expect($allowed)->toContain('leaves.manager_approve');
        expect($allowed)->toContain('vendor_invoices.approve');

        // Duplicates removed
        $unique = array_unique($allowed);
        expect(count($unique))->toBe(count($allowed));
    });

    it('getSelfActionPreventionPermissions returns the SoD list', function () {
        $list = DepartmentPermissionService::getSelfActionPreventionPermissions();

        expect($list)->toContain('employees.update_salary');
        expect($list)->toContain('employees.terminate');
        expect($list)->toContain('leaves.manager_approve');
        expect($list)->toContain('overtime.approve');
        expect($list)->toContain('loans.hr_approve');
        expect($list)->toContain('payroll.hr_approve');
        expect($list)->toContain('payroll.acctg_approve');
        expect($list)->toContain('vendor_invoices.approve');
    });

    it('getAllowedPermissions returns empty array for unknown department code', function () {
        Cache::flush();

        $allowed = DepartmentPermissionService::getAllowedPermissions('manager', ['NONEXISTENT-999']);

        expect($allowed)->toBe([]);
    });
});
