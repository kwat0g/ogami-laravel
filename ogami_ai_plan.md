# Ogami ERP — Refactor & New Modules Plan
## AI-Generated Implementation Plan · v2.0 · March 2026 (Codebase-Aligned Revision)
## Stack: Laravel 11 · React 18 + TypeScript · PostgreSQL 16 · Docker
## Revised against actual codebase — all file paths, table names, column names, and service names verified

---

# TASK 1 — REFACTOR PLAN

---

## 1A — Role Migration

### New Role Model (7 Roles Final)

```
admin           System custodian — zero business data access
executive       Chairman, President — read-only board observers
vice_president  Vice President — final approver of all financial requests
manager         HR/Plant/Production/QC/Mold Managers — Step 3 approver
officer         Accounting/GA/Purchasing/ImpEx Officers — Step 4 approver
head            All Department Heads — Step 2 approver (renamed from supervisor)
staff           All rank-and-file — creates and submits requests
```

---

### Step 1 — Add New Roles to RolePermissionSeeder

**File:** `database/seeders/RolePermissionSeeder.php`

The seeder uses `Role::findOrCreate()` per variable, not a foreach loop. The current code (lines 253–263) is:

```php
$admin             = Role::findOrCreate('admin',             self::GUARD);
$executive         = Role::findOrCreate('executive',         self::GUARD);
$hrManager         = Role::findOrCreate('hr_manager',        self::GUARD);
$accountingManager = Role::findOrCreate('accounting_manager',self::GUARD);
$supervisor        = Role::findOrCreate('supervisor',        self::GUARD);
$staff             = Role::findOrCreate('staff',             self::GUARD);

// PROBLEM: this line deletes the generic 'manager' role that we now need
Role::where('name', 'manager')->where('guard_name', self::GUARD)->delete();
```

New (replace the entire block):

```php
$admin         = Role::findOrCreate('admin',          self::GUARD);
$executive     = Role::findOrCreate('executive',      self::GUARD);
$vicePresident = Role::findOrCreate('vice_president', self::GUARD);
$manager       = Role::findOrCreate('manager',        self::GUARD);
$officer       = Role::findOrCreate('officer',        self::GUARD);
$head          = Role::findOrCreate('head',           self::GUARD);
$staff         = Role::findOrCreate('staff',          self::GUARD);

// Remove the delete guard — 'manager' is now a real role, not a legacy stub
// (the line Role::where('name','manager')->delete() must be removed)
```

Also rename the matching `syncPermissions` variable references:
- All `$supervisor->syncPermissions([...])` → `$head->syncPermissions([...])`
- All `$hrManager->syncPermissions([...])` → `$manager->syncPermissions([...])`
- All `$accountingManager->syncPermissions([...])` → `$officer->syncPermissions([...])`
- Add `$vicePresident->syncPermissions([...])` block — see permission list in 1B.

---

### Step 2 — Database Migration: Rename Existing Roles

**File:** `database/migrations/2026_03_05_000001_rename_roles_v2.php`

Renames three existing roles in the `roles` table. Because `model_has_roles` uses `role_id` (FK to `roles.id`), renaming the row automatically reassigns all users — no changes needed in `model_has_roles`.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'supervisor')
            ->where('guard_name', 'web')
            ->update(['name' => 'head']);

        DB::table('roles')
            ->where('name', 'hr_manager')
            ->where('guard_name', 'web')
            ->update(['name' => 'manager']);

        DB::table('roles')
            ->where('name', 'accounting_manager')
            ->where('guard_name', 'web')
            ->update(['name' => 'officer']);
        // No changes to model_has_roles — FK uses role_id, not name
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'head')   ->where('guard_name', 'web')->update(['name' => 'supervisor']);
        DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->update(['name' => 'hr_manager']);
        DB::table('roles')->where('name', 'officer')->where('guard_name', 'web')->update(['name' => 'accounting_manager']);
    }
};
```

---

### Step 3 — Database Migration: Add vice_president Role

**File:** `database/migrations/2026_03_05_000002_add_vice_president_role.php`

Only `vice_president` needs to be inserted as a brand-new role. `manager`, `officer`, and `head` are covered by the renames in Step 2.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'name'       => 'vice_president',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'vice_president')
            ->where('guard_name', 'web')
            ->delete();
    }
};
```

---

### Step 4---

### Step 4 — Update department_permission_profiles role column data

**File:** `database/migrations/2026_03_05_000002_rename_roles_in_dept_permission_profiles.php`

The `role` column is a plain `varchar`, not a PostgreSQL enum type, so no `ALTER TYPE` is needed — only data updates. Three renames are required: `supervisor`→`head`, `hr_manager`→`manager`, `accounting_manager`→`officer`.

Also update the cache flush loop in `DepartmentPermissionProfileSeeder` (line 547):
```php
// Old:
foreach (['hr_manager', 'accounting_manager', 'supervisor'] as $role) { ... }
// New:
foreach (['manager', 'officer', 'head', 'vice_president'] as $role) { ... }
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('department_permission_profiles')
            ->where('role', 'supervisor')
            ->update(['role' => 'head']);

        DB::table('department_permission_profiles')
            ->where('role', 'hr_manager')
            ->update(['role' => 'manager']);

        DB::table('department_permission_profiles')
            ->where('role', 'accounting_manager')
            ->update(['role' => 'officer']);
    }

    public function down(): void
    {
        DB::table('department_permission_profiles')
            ->where('role', 'head')
            ->update(['role' => 'supervisor']);

        DB::table('department_permission_profiles')
            ->where('role', 'manager')
            ->update(['role' => 'hr_manager']);

        DB::table('department_permission_profiles')
            ->where('role', 'officer')
            ->update(['role' => 'accounting_manager']);
    }
};
```

---

### Step 5 — SoD Conflict Matrix (No Changes Required)

The SoD conflict matrix stored in `system_settings` (key `sod_conflict_matrix`) uses **permission process slugs** as keys (`employees`, `leaves`, `overtime`, `payroll`, `vendor_invoices`, `customer_invoices`), not role names. There is no `supervisor` key in this matrix.

The existing matrix is role-agnostic — it checks whether the current user holds conflicting permissions, not conflicting roles. **No changes are needed to this matrix.**

The `SodMiddleware` (`app/Infrastructure/Middleware/SodMiddleware.php`) reads this matrix and bypasses admin only. The `manager` role is explicitly not bypassed (confirmed in middleware source).

---

### Step 6 — Update DepartmentPermissionService

**File:** `app/Services/DepartmentPermissionService.php` (not under Domains — it is a shared service)

The actual service does pure DB lookups against `department_permission_profiles`. There is no switch statement. The only role-array that needs updating is the department-scoped roles list at line 195:

```php
// Old (line 195):
foreach (['manager', 'hr_manager', 'accounting_manager', 'supervisor'] as $role) {

// New:
foreach (['manager', 'officer', 'head', 'vice_president'] as $role) {
```

The same change applies to the identical line in `app/Services/DepartmentPermissionServiceV3.php` line 197.

Also update the `hasDepartmentAccess()` bypass list in `frontend/src/stores/authStore.ts` if needed — currently it bypasses `admin` and `executive`. Add `vice_president` to the bypass (VP needs cross-department visibility for approvals):

```typescript
// authStore.ts — hasDepartmentAccess()
if (user.roles.some((r) => ['admin', 'executive', 'vice_president'].includes(r))) return true
```

---

### Step 7 — Update All Laravel Policy Files

All policy files live under `app/Domains/*/Policies/`, not `app/Policies/`. The exact files that reference `'supervisor'`:

**`app/Domains/HR/Policies/EmployeePolicy.php` — line 74:**
```php
// Old:
if ($user->hasAnyRole(['hr_manager', 'accounting_manager', 'supervisor'])) {

// New:
if ($user->hasAnyRole(['manager', 'officer', 'head'])) {
```

**`app/Domains/Loan/Policies/LoanPolicy.php` — lines 75–80 and 132:**
```php
// Line 75 — method name stays (policy method, not a role check):
public function supervisorReview(User $user, Loan $loan): bool
{
    return $user->hasPermissionTo('loans.supervisor_review') // old slug
// → update slug to 'loans.head_note' after 1C is complete:
    return $user->hasPermissionTo('loans.head_note')

// Line 132 — status check:
// Old:
if (! in_array($loan->status, ['pending', 'supervisor_approved'], true)) {
// New (v2 loans; after migration 1C):
if (! in_array($loan->status, ['pending', 'head_noted'], true)) {
```

> Note: Other policy files (`LeaveRequestPolicy`, `OvertimeRequestPolicy`) do not hardcode role strings — they check permission slugs. No changes needed in the policy bodies.\n\n**Additional: `OvertimeRequest` requester_role column**\n\nThe `overtime_requests` table has a `requester_role` column with a CHECK constraint that only allows `staff`, `supervisor`, `manager`. This must be updated:\n\n```sql\n-- Include in migration 2026_03_05_000001 or a separate migration:\nALTER TABLE overtime_requests\n  DROP CONSTRAINT IF EXISTS overtime_requests_requester_role_check;\nALTER TABLE overtime_requests\n  ADD CONSTRAINT overtime_requests_requester_role_check\n  CHECK (requester_role IN ('staff', 'head', 'manager', 'officer', 'vice_president'));\n```\n\nAlso update `app/Domains/Attendance/Services/OvertimeRequestService.php` in the method that sets `requester_role`:\n```php\n// Old (line ~150):\n$requesterRole = $user->hasRole('supervisor') ? 'supervisor' : ($user->hasRole('manager') ? 'manager' : 'staff');\n\n// New:\n$requesterRole = match(true) {\n    $user->hasRole('vice_president') => 'vice_president',\n    $user->hasRole('officer')        => 'officer',\n    $user->hasRole('manager')        => 'manager',\n    $user->hasRole('head')           => 'head',\n    default                          => 'staff',\n};\n```\n\nAlso update the `OvertimeRequest` model docblock (line 21) and the TypeScript type in `frontend/src/types/hr.ts` (line 169):\n```typescript\n// Old:\nrequester_role: 'staff' | 'supervisor' | 'manager' | null\n// New:\nrequester_role: 'staff' | 'head' | 'manager' | 'officer' | 'vice_president' | null\n```

Run this to audit remaining references before closing:
```bash
grep -rl "'supervisor'\|\"supervisor\"" app/Domains/ app/Services/ routes/
```

### Step 7b — Update `database/seeders/SampleDataSeeder.php`

Line 302 explicitly assigns the `supervisor` role to the demo user:

```php
// Old (line 302):
$supervisorUser->syncRoles(['supervisor']);

// New:
$supervisorUser->syncRoles(['head']);
```

Also update the demo user's `name` / `email` if they still say "supervisor" in the seeder to avoid confusing the demo data.

---

### Step 8 — Frontend: Update AuthUser TypeScript Type

**File:** `frontend/src/types/api.ts` (not `auth.types.ts` — this file does not exist)

The actual `AuthUser` interface has `roles: string[]` (an array, not a single role union). Add a named `AppRole` type above it:

Old:
```typescript
export interface AuthUser {
  id: number
  name: string
  email: string
  roles: string[]          // plain string array
  permissions: string[]
  department_ids: number[]
  primary_department_id: number | null
  timezone: string
  employee_id?: number | null
}
```

New:
```typescript
export type AppRole =
  | 'admin' | 'executive' | 'vice_president'
  | 'manager' | 'officer' | 'head' | 'staff'

export interface AuthUser {
  id: number
  name: string
  email: string
  roles: AppRole[]         // typed array replaces string[]
  permissions: string[]
  department_ids: number[]
  primary_department_id: number | null
  timezone: string
  employee_id?: number | null
}

---

### Step 9 — Frontend: Update Role Badge

There is **no separate `RoleBadge.tsx` component**. The role badge is an inline `Record<string, string>` defined at line 24 of `frontend/src/pages/admin/UsersPage.tsx`.

**File:** `frontend/src/pages/admin/UsersPage.tsx` — lines 24–31

Old:
```typescript
const roleBadgeClass: Record<string, string> = {
  admin:               'bg-red-100 text-red-700',
  executive:           'bg-purple-100 text-purple-700',
  hr_manager:          'bg-blue-100 text-blue-700',
  accounting_manager:  'bg-teal-100 text-teal-700',
  supervisor:          'bg-indigo-100 text-indigo-700',
  staff:               'bg-gray-100 text-gray-600',
}
```

New:
```typescript
const roleBadgeClass: Record<string, string> = {
  admin:          'bg-red-100 text-red-700',
  executive:      'bg-purple-100 text-purple-700',
  vice_president: 'bg-amber-100 text-amber-700',
  manager:        'bg-blue-100 text-blue-700',
  officer:        'bg-teal-100 text-teal-700',
  head:           'bg-indigo-100 text-indigo-700',
  staff:          'bg-gray-100 text-gray-600',
}
```

Optionally extract this into a reusable `frontend/src/components/ui/RoleBadge.tsx` component later, but the current codebase inlines it — extract only if more pages need it.

---

### Step 10 — Frontend: Update Sidebar Nav

**File:** `frontend/src/components/layout/AppLayout.tsx` (not `Sidebar.tsx` — this file does not exist)

The nav sections are already **permission-gated, not role-gated**. There are no hardcoded `'supervisor'` strings in the nav config. No existing nav items need renaming.

The only required change is adding a new VP Approvals section:
```typescript
// Add to the nav SECTIONS array in AppLayout.tsx:
{
  label: 'Pending Approvals',
  icon: ClipboardCheck,
  permission: 'loans.vp_approve',   // VP-only permission slug
  items: [
    { label: 'Loans Awaiting VP Sign-off', to: '/approvals/loans', permission: 'loans.vp_approve' },
    // future: procurement approvals
  ]
}
```

The existing `Executive` section (gated by `leaves.executive_approve`) stays unchanged for Chairman/President read-only access.

---

### Step 11 — Frontend: Zod Schemas

**No changes required.** A full search of `frontend/src/schemas/` found no Zod schemas with hardcoded role enum strings. Role strings only appear in `authStore.ts` and `usePermission.ts` (covered in Steps 8 and 12). Skip this step.

---

### Step 12 — Frontend: Update authStore

**File:** `frontend/src/stores/authStore.ts`

The actual `hasPermission` method does a **direct permission slug lookup** only — there is no executive write-block logic in the codebase. Executives are read-only because the seeder only assigns them read permissions. Do not add a write-block guard.

The changes needed are:

**1. `isManager()` — update role array (line ~65):**
```typescript
// Old:
return roles.some((r) => ['manager', 'hr_manager', 'accounting_manager'].includes(r))

// New (also include officer and vice_president in the "above head" group):
return roles.some((r) => ['manager', 'officer', 'vice_president'].includes(r))
```

**2. `isSupervisor()` — rename to `isHead()` and update role string (line ~69):**
```typescript
// Old:
isSupervisor: () => get().user?.roles.includes('supervisor') ?? false,

// New:
isHead: () => get().user?.roles.includes('head') ?? false,
```

**3. Update `AuthState` interface** to reflect the renamed method and add new helpers:
```typescript
// Replace isSupervisor with isHead; add new ones:
isHead:          () => boolean
isOfficer:       () => boolean
isVicePresident: () => boolean
```

**4. Add `isOfficer()` and `isVicePresident()` implementations:**
```typescript
isOfficer:       () => get().user?.roles.includes('officer')        ?? false,
isVicePresident: () => get().user?.roles.includes('vice_president') ?? false,
```

**5. Update `hasDepartmentAccess()` bypass list:**
```typescript
// Old:
if (user.roles.some((r) => ['admin', 'executive'].includes(r))) return true

// New (VP needs cross-department visibility for approvals):
if (user.roles.some((r) => ['admin', 'executive', 'vice_president'].includes(r))) return true
```

---

### Step 13 — Update Playwright E2E Fixtures

**File:** `frontend/e2e/setup/auth.setup.ts` (not `e2e/fixtures/users.ts` — that path does not exist)

Search `auth.setup.ts` and any other files in `frontend/e2e/` for `roles: ['supervisor']` or `'supervisor'` string literals and update to `'head'`. Also add fixture users for `officer` and `vice_president` roles if not present:

```typescript
// Old:
export const supervisorUser = { username: 'hr_supervisor', role: 'supervisor', ... }

// New:
export const headUser       = { username: 'hr_head',        role: 'head',          ... }
export const officerUser    = { username: 'acctg_officer',  role: 'officer',       ... }
export const vpUser         = { username: 'vice_president', role: 'vice_president', ... }
```

Also update `frontend/src/hooks/useSodCheck.test.ts` lines 19, 39, 46, 60, 67 where `roles: ['supervisor']` appears:

---

## 1B — Permission Profile Updates

### Vice President Permissions

```php
// In DepartmentPermissionService::getVpPermissions()
private function getVpPermissions(): array
{
    return [
        // Final approval across all financial modules
        'approvals.vp.view',
        'approvals.vp.approve',
        'approvals.vp.reject',

        // Read-only visibility across all modules (for context when approving)
        'employees.view',
        'procurement.purchase-request.view',
        'procurement.purchase-order.view',
        'loans.view.team',
        'payroll.breakdown.view.readonly',
        'reports.trial-balance.view',
        'reports.balance-sheet.view',
        'reports.income-statement.view',
        'reports.executive-dashboard.view',

        // Self-service
        'payroll.payslips.view.own',
        'payroll.payslips.download.own',
        'leave.view.own',
        'leave.request.create',
        'loans.view.own',
        'attendance.view.own',
    ];
}
```

### Officer Permission Profiles (new profiles in seeder)

```php
// Accounting Officer
[
    'gl.view', 'gl.create', 'gl.submit', 'gl.report.view',
    'ap.vendors.view', 'ap.invoices.view', 'ap.invoices.create',
    'ap.invoices.submit', 'ap.aging.view',
    'ar.invoices.view', 'ar.aging.view',
    'procurement.purchase-request.review',    // Step 4 of approval chain
    'procurement.purchase-order.view',
    'loans.review',                           // Step 4 of loan approval
    'reports.trial-balance.view',
    'payroll.payslips.view.own',
    'leave.view.own', 'leave.request.create',
    'loans.view.own', 'attendance.view.own',
],

// Purchasing Officer
[
    'procurement.purchase-request.review',    // Step 4 of approval chain
    'procurement.purchase-order.create',
    'procurement.purchase-order.manage',
    'procurement.vendor.manage',
    'procurement.vendor.view',
    'procurement.goods-receipt.view',
    'ap.vendors.view',
    'payroll.payslips.view.own',
    'leave.view.own', 'leave.request.create',
    'loans.view.own', 'attendance.view.own',
],

// General Administration Officer
[
    'procurement.purchase-request.review',    // Step 4 of approval chain
    'employees.view',
    'hr.admin-support.manage',
    'loans.review',
    'payroll.payslips.view.own',
    'leave.view.own', 'leave.request.create',
    'loans.view.own', 'attendance.view.own',
],

// ImpEx Officer
[
    'procurement.purchase-request.review',    // Step 4
    'procurement.shipment.manage',
    'procurement.goods-receipt.view',
    'payroll.payslips.view.own',
    'leave.view.own', 'leave.request.create',
    'loans.view.own', 'attendance.view.own',
],
```

---

## 1C — Loan Approval Workflow Refactor

### New loans Table Columns (workflow_version discriminator)

**IMPORTANT — Correct table name:** The table is `loans`, not `loan_applications`. The model is `App\Domains\Loan\Models\Loan`, the service is `App\Domains\Loan\Services\LoanRequestService`.

Existing relevant columns in `loans` (already migrated via `2026_03_01_125224_add_accounting_approval_to_loans_table.php`):
- `supervisor_approved_by` (FK users.id)
- `supervisor_approved_at`
- `supervisor_remarks`
- `accounting_approved_by` (FK users.id)
- `accounting_approved_at`
- `accounting_remarks`
- `disbursed_by`, `journal_entry_id`

The creator column is `requested_by` (not `submitted_by_id`). The existing status CHECK constraint only covers: `pending, approved, active, fully_paid, cancelled, written_off`. The statuses `supervisor_approved`, `ready_for_disbursement`, `rejected` are used in application code but are **not in the DB constraint** — the constraint needs to be fixed as part of this migration.

**File:** `database/migrations/2026_03_05_000003_add_5stage_approval_to_loans.php`

```php
public function up(): void
{
    // Extend status CHECK to cover all existing app statuses + new v2 statuses
    DB::statement("ALTER TABLE loans DROP CONSTRAINT IF EXISTS loans_status_check");
    DB::statement("
        ALTER TABLE loans ADD CONSTRAINT loans_status_check
        CHECK (status IN (
            -- v1 statuses (preserve all, including ones missing from original constraint)
            'pending','supervisor_approved','approved','ready_for_disbursement',
            'active','fully_paid','cancelled','written_off','rejected',
            -- v2 new statuses
            'head_noted','manager_checked','officer_reviewed','vp_approved','disbursing'
        ))
    ");

    Schema::table('loans', function (Blueprint $table) {
        $table->unsignedSmallInteger('workflow_version')->default(1)->after('deduction_cutoff');

        // v2 approval chain
        $table->foreignId('head_noted_by')
              ->nullable()->constrained('users')->nullOnDelete()->after('workflow_version');
        $table->timestamp('head_noted_at')->nullable()->after('head_noted_by');
        $table->text('head_remarks')->nullable()->after('head_noted_at');

        $table->foreignId('manager_checked_by')
              ->nullable()->constrained('users')->nullOnDelete()->after('head_remarks');
        $table->timestamp('manager_checked_at')->nullable()->after('manager_checked_by');
        $table->text('manager_remarks')->nullable()->after('manager_checked_at');

        $table->foreignId('officer_reviewed_by')
              ->nullable()->constrained('users')->nullOnDelete()->after('manager_remarks');
        $table->timestamp('officer_reviewed_at')->nullable()->after('officer_reviewed_by');
        $table->text('officer_remarks')->nullable()->after('officer_reviewed_at');

        $table->foreignId('vp_approved_by')
              ->nullable()->constrained('users')->nullOnDelete()->after('officer_remarks');
        $table->timestamp('vp_approved_at')->nullable()->after('vp_approved_by');
        $table->text('vp_remarks')->nullable()->after('vp_approved_at');
    });

    // SoD DB-level constraints for v2 chain
    DB::statement("
        ALTER TABLE loans
        ADD CONSTRAINT chk_sod_loan_head
            CHECK (head_noted_by IS NULL OR head_noted_by <> requested_by),
        ADD CONSTRAINT chk_sod_loan_manager
            CHECK (manager_checked_by IS NULL OR manager_checked_by <> head_noted_by),
        ADD CONSTRAINT chk_sod_loan_officer
            CHECK (officer_reviewed_by IS NULL OR officer_reviewed_by <> manager_checked_by),
        ADD CONSTRAINT chk_sod_loan_vp
            CHECK (vp_approved_by IS NULL OR vp_approved_by <> officer_reviewed_by)
    ");
}

public function down(): void
{
    DB::statement("ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_head");
    DB::statement("ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_manager");
    DB::statement("ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_officer");
    DB::statement("ALTER TABLE loans DROP CONSTRAINT IF EXISTS chk_sod_loan_vp");

    Schema::table('loans', function (Blueprint $table) {
        $table->dropColumn([
            'workflow_version',
            'head_noted_by', 'head_noted_at', 'head_remarks',
            'manager_checked_by', 'manager_checked_at', 'manager_remarks',
            'officer_reviewed_by', 'officer_reviewed_at', 'officer_remarks',
            'vp_approved_by', 'vp_approved_at', 'vp_remarks',
        ]);
    });

    // Restore original (incomplete) constraint
    DB::statement("ALTER TABLE loans DROP CONSTRAINT IF EXISTS loans_status_check");
    DB::statement("
        ALTER TABLE loans ADD CONSTRAINT loans_status_check
        CHECK (status IN ('pending','approved','active','fully_paid','cancelled','written_off'))
    ");
}
```

### New Loan API Routes (add to `routes/api/v1/loans.php`)

```php
// v2 approval chain — add inside the existing auth:sanctum group
Route::patch('{loan}/head-note',       [LoanController::class, 'headNote'])
    ->middleware('throttle:api-action')->name('headNote');
Route::patch('{loan}/manager-check',   [LoanController::class, 'managerCheck'])
    ->middleware('throttle:api-action')->name('managerCheck');
Route::patch('{loan}/officer-review',  [LoanController::class, 'officerReview'])
    ->middleware('throttle:api-action')->name('officerReview');
Route::patch('{loan}/vp-approve',      [LoanController::class, 'vpApprove'])
    ->middleware('throttle:api-action')->name('vpApprove');
```

All four controller methods follow the same pattern as the existing `approve()` controller method: resolve via `LoanPolicy`, call `LoanRequestService`, return `LoanResource`.

### New LoanPolicy Gates (add to `app/Domains/Loan/Policies/LoanPolicy.php`)

```php
public function headNote(User $user, Loan $loan): bool
{
    return $user->hasPermissionTo('loans.head_note')
        && $loan->workflow_version === 2
        && $loan->status === 'pending'
        && $user->id !== $loan->requested_by;  // SoD
}

public function managerCheck(User $user, Loan $loan): bool
{
    return $user->hasPermissionTo('loans.manager_check')
        && $loan->workflow_version === 2
        && $loan->status === 'head_noted'
        && $user->id !== $loan->head_noted_by;  // SoD
}

public function officerReview(User $user, Loan $loan): bool
{
    return $user->hasPermissionTo('loans.officer_review')
        && $loan->workflow_version === 2
        && $loan->status === 'manager_checked'
        && $user->id !== $loan->manager_checked_by;  // SoD
}

public function vpApprove(User $user, Loan $loan): bool
{
    return $user->hasPermissionTo('loans.vp_approve')
        && $loan->workflow_version === 2
        && $loan->status === 'officer_reviewed'
        && $user->id !== $loan->officer_reviewed_by;  // SoD
}
```

### Register new permission slugs in `RolePermissionSeeder`

Add to the `PERMISSIONS` constant array and assign to the appropriate roles:

```php
// Add to PERMISSIONS array:
'loans.head_note',
'loans.manager_check',
'loans.officer_review',
'loans.vp_approve',

// Assign:
$head->syncPermissions([..., 'loans.head_note']);
$manager->syncPermissions([..., 'loans.manager_check']);
$officer->syncPermissions([..., 'loans.officer_review']);
$vicePresident->syncPermissions([..., 'loans.vp_approve']);
```

**File:** `app/Domains/Loan/Services/LoanRequestService.php`

In `store()`: set `workflow_version = 2` for new loans going forward.

Add four new service methods (each mirrors the pattern of `approve()`). Use the correct column names from the actual `loans` table (`requested_by`, `head_noted_by`, `manager_checked_by`, `officer_reviewed_by`, `vp_approved_by`):

```php
public function headNote(Loan $loan, User $actor, string $remarks = ''): Loan
{
    $this->assertWorkflowVersion($loan, 2);
    $this->assertStatus($loan, 'pending');
    // SoD: head cannot be the same person who requested the loan
    if ($actor->id === $loan->requested_by) {
        throw new SodViolationException('SOD-011');
    }
    $this->assertPermission($actor, 'loans.head_note');

    $loan->update([
        'status'         => 'head_noted',
        'head_noted_by'  => $actor->id,
        'head_noted_at'  => now(),
        'head_remarks'   => $remarks,
    ]);
    return $loan->refresh();
}

public function managerCheck(Loan $loan, User $actor, string $remarks = ''): Loan
{
    $this->assertWorkflowVersion($loan, 2);
    $this->assertStatus($loan, 'head_noted');
    if ($actor->id === $loan->head_noted_by) {
        throw new SodViolationException('SOD-012');
    }
    $this->assertPermission($actor, 'loans.manager_check');

    $loan->update([
        'status'              => 'manager_checked',
        'manager_checked_by'  => $actor->id,
        'manager_checked_at'  => now(),
        'manager_remarks'     => $remarks,
    ]);
    return $loan->refresh();
}

public function officerReview(Loan $loan, User $actor, string $remarks = ''): Loan
{
    $this->assertWorkflowVersion($loan, 2);
    $this->assertStatus($loan, 'manager_checked');
    if ($actor->id === $loan->manager_checked_by) {
        throw new SodViolationException('SOD-013');
    }
    $this->assertPermission($actor, 'loans.officer_review');

    $loan->update([
        'status'               => 'officer_reviewed',
        'officer_reviewed_by'  => $actor->id,
        'officer_reviewed_at'  => now(),
        'officer_remarks'      => $remarks,
    ]);
    return $loan->refresh();
}

public function vpApprove(Loan $loan, User $actor, string $remarks = ''): Loan
{
    $this->assertWorkflowVersion($loan, 2);
    $this->assertStatus($loan, 'officer_reviewed');
    if ($actor->id === $loan->officer_reviewed_by) {
        throw new SodViolationException('SOD-014');
    }
    $this->assertPermission($actor, 'loans.vp_approve');

    $loan->update([
        'status'          => 'vp_approved',
        'vp_approved_by'  => $actor->id,
        'vp_approved_at'  => now(),
        'vp_remarks'      => $remarks,
    ]);
    return $loan->refresh();
}

private function assertWorkflowVersion(Loan $loan, int $version): void
{
    if ($loan->workflow_version !== $version) {
        throw new DomainException("Loan uses workflow v{$loan->workflow_version}; expected v{$version}.");
    }
}
```

---

## 1D — Frontend Role Updates

### VP Pending Approvals Dashboard

**New Route:** `/approvals/pending`
**Permission:** `approvals.vp.view`

This page aggregates all records awaiting VP approval across all modules:

```typescript
// pages/VpApprovalsDashboardPage.tsx

// Tabs: Purchase Requests | Loans | Leave (if applicable)
// Each tab shows a DataTable of records in 'reviewed' status
// Each row has: [Approve] [Reject] SodActionButton

const tabs = [
  {
    label: 'Purchase Requests',
    queryKey: ['vp-approvals', 'purchase-requests'],
    endpoint: '/api/v1/procurement/purchase-requests?status=reviewed',
    permission: 'procurement.purchase-request.vp-approve',
  },
  {
    label: 'Loans',
    queryKey: ['vp-approvals', 'loans'],
    endpoint: '/api/v1/hr/loans?status=reviewed',
    permission: 'approvals.vp.approve',
  },
]
```

---

## Rollback Strategy

If any migration causes issues, execute in reverse order:

```bash
# Rollback in exact reverse order
php artisan migrate:rollback --step=4   # Reverts loan approval chain extension
php artisan migrate:rollback --step=3   # Reverts dept_permission_profiles enum
php artisan migrate:rollback --step=2   # Removes officer and vice_president roles
php artisan migrate:rollback --step=1   # Renames head back to supervisor
```

Additionally, keep a DB snapshot before running migrations:
```bash
docker exec ogami_postgres pg_dump -U ogami_app ogami_erp > backup_pre_refactor_$(date +%Y%m%d).sql
```

### Regression Tests to Run After Task 1

Before merging Task 1 to main, verify all of the following still pass:

| Feature | Test Type | What to Verify |
|---|---|---|
| Login (all roles) | E2E | All 7 roles can log in and reach their default page |
| HR Manager creates employee | E2E | SOD-001 still enforced |
| HR Head imports attendance | E2E | head role can still import CSV |
| Leave approval (SOD-002) | E2E | HR Manager cannot approve own request |
| OT approval (SOD-003) | E2E | HR Manager cannot approve own OT |
| Payroll run Steps 1–8 | E2E | Full payroll workflow end-to-end |
| Payroll HR approval (SOD-005/006) | Unit | Blocked when initiator = approver |
| Payroll Acctg approval (SOD-007) | Unit | Blocked when initiator = approver |
| JE posting (SOD-008) | Unit | Creator cannot post own JE |
| AP invoice approval (SOD-009) | Unit | Creator cannot approve own invoice |
| Admin cannot see employee list | E2E | 403 redirect |
| Staff cannot see other payslips | E2E | Own payslip only |
| Permission cache invalidation | Unit | Redis cache busted on dept assignment change |

---

---

# TASK 2 — PROCUREMENT MODULE

---

## 2A — Purchase Request

### Database Schema

```sql
CREATE TABLE purchase_requests (
    id                      BIGSERIAL PRIMARY KEY,
    pr_reference            VARCHAR(30) UNIQUE NOT NULL,
    -- format: PR-YYYY-MM-NNNNN (e.g. PR-2026-03-00001)
    -- Generated by PostgreSQL sequence

    department_id           BIGINT NOT NULL REFERENCES departments(id),
    requested_by_id         BIGINT NOT NULL REFERENCES users(id),
    urgency                 VARCHAR(20) NOT NULL DEFAULT 'normal',
    -- urgency: normal, urgent, critical
    justification           TEXT NOT NULL,
    notes                   TEXT,

    status                  VARCHAR(30) NOT NULL DEFAULT 'draft',
    -- draft, submitted, noted, checked, reviewed, approved, rejected, cancelled, converted_to_po

    -- Approval chain actors (SOD enforced at DB + service layer)
    submitted_by_id         BIGINT REFERENCES users(id),
    submitted_at            TIMESTAMP,

    noted_by_id             BIGINT REFERENCES users(id),        -- Head
    noted_at                TIMESTAMP,
    noted_comments          TEXT,

    checked_by_id           BIGINT REFERENCES users(id),        -- Manager
    checked_at              TIMESTAMP,
    checked_comments        TEXT,

    reviewed_by_id          BIGINT REFERENCES users(id),        -- Officer
    reviewed_at             TIMESTAMP,
    reviewed_comments       TEXT,

    vp_approved_by_id       BIGINT REFERENCES users(id),        -- Vice President
    vp_approved_at          TIMESTAMP,
    vp_comments             TEXT,

    rejected_by_id          BIGINT REFERENCES users(id),
    rejected_at             TIMESTAMP,
    rejection_reason        TEXT,
    rejection_stage         VARCHAR(20),   -- which stage rejected it

    converted_to_po_id      BIGINT,        -- FK set after PO created (circular, set post-insert)
    converted_at            TIMESTAMP,

    total_estimated_cost    NUMERIC(15,2) NOT NULL DEFAULT 0,
    -- Updated by trigger (trg_pr_total) when items are inserted/updated/deleted
    -- NOTE: GENERATED ALWAYS AS cannot reference other tables in PostgreSQL;
    --       this is a plain column kept in sync by the trigger below.

    created_at              TIMESTAMP DEFAULT NOW(),
    updated_at              TIMESTAMP DEFAULT NOW(),

    -- SoD constraints
    CONSTRAINT chk_pr_sod_noted
        CHECK (noted_by_id IS NULL OR noted_by_id <> submitted_by_id),
    CONSTRAINT chk_pr_sod_checked
        CHECK (checked_by_id IS NULL OR checked_by_id <> noted_by_id),
    CONSTRAINT chk_pr_sod_reviewed
        CHECK (reviewed_by_id IS NULL OR reviewed_by_id <> checked_by_id),
    CONSTRAINT chk_pr_sod_vp
        CHECK (vp_approved_by_id IS NULL OR vp_approved_by_id <> reviewed_by_id)
);

-- PR Reference Sequence
CREATE SEQUENCE purchase_request_seq START 1;

-- PR Line Items
CREATE TABLE purchase_request_items (
    id                  BIGSERIAL PRIMARY KEY,
    purchase_request_id BIGINT NOT NULL REFERENCES purchase_requests(id) ON DELETE CASCADE,
    item_description    VARCHAR(255) NOT NULL,
    unit_of_measure     VARCHAR(30) NOT NULL,        -- pcs, kg, liters, meters, etc.
    quantity            NUMERIC(12,3) NOT NULL,
    estimated_unit_cost NUMERIC(12,2) NOT NULL,
    estimated_total     NUMERIC(15,2) GENERATED ALWAYS AS (quantity * estimated_unit_cost) STORED,
    specifications      TEXT,
    line_order          SMALLINT DEFAULT 1,
    created_at          TIMESTAMP DEFAULT NOW()
);

-- Trigger: update purchase_requests.total_estimated_cost when items change
CREATE OR REPLACE FUNCTION update_pr_total()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    UPDATE purchase_requests
    SET total_estimated_cost = (
        SELECT COALESCE(SUM(estimated_total), 0)
        FROM purchase_request_items
        WHERE purchase_request_id = COALESCE(NEW.purchase_request_id, OLD.purchase_request_id)
    ),
    updated_at = NOW()
    WHERE id = COALESCE(NEW.purchase_request_id, OLD.purchase_request_id);
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_pr_total
AFTER INSERT OR UPDATE OR DELETE ON purchase_request_items
FOR EACH ROW EXECUTE FUNCTION update_pr_total();

-- Indexes
CREATE INDEX idx_pr_status ON purchase_requests(status);
CREATE INDEX idx_pr_department ON purchase_requests(department_id);
CREATE INDEX idx_pr_requested_by ON purchase_requests(requested_by_id);
CREATE INDEX idx_pr_reference ON purchase_requests(pr_reference);
```

### State Machine

```
DRAFT
  └─ [Staff submits] ──────────────────────────────► SUBMITTED
       └─ [Head notes — SOD-011] ──────────────────► NOTED
            └─ [Manager checks — SOD-012] ──────────► CHECKED
                 └─ [Officer reviews — SOD-013] ─────► REVIEWED
                      └─ [VP approves — SOD-014] ─────► APPROVED ──► CONVERTED_TO_PO
                      └─ [VP rejects] ───────────────► REJECTED
                 └─ [Officer rejects] ───────────────► REJECTED
            └─ [Manager rejects] ────────────────────► REJECTED
       └─ [Head rejects] ────────────────────────────► REJECTED
  └─ [Staff cancels (only in DRAFT or SUBMITTED)] ──► CANCELLED
```

Any rejection includes `rejection_reason` and `rejection_stage` fields.
Rejected PRs cannot be re-submitted — a new PR must be created.

### Validation Rules

| Code | Rule | Enforced At |
|---|---|---|
| PR-001 | `pr_reference` must be unique (auto-generated — never manual) | DB Unique |
| PR-002 | At least one line item is required before submission | Service |
| PR-003 | `quantity` must be > 0 | DB CHECK + FormRequest |
| PR-004 | `estimated_unit_cost` must be > 0 | DB CHECK + FormRequest |
| PR-005 | `department_id` must be an active department | FormRequest |
| PR-006 | `justification` is required (min 20 characters) | FormRequest |
| PR-007 | Cannot submit if status is not `draft` | Service |
| PR-008 | Cannot cancel if status is past `submitted` | Service |
| PR-009 | `urgency` must be one of: normal, urgent, critical | DB CHECK |
| PR-010 | Rejection requires `rejection_reason` (min 10 chars) | FormRequest |

### SoD Rules

| Code | Rule | Enforced At |
|---|---|---|
| SOD-011 | Head (noter) cannot be the same as the Staff who submitted | DB + Service |
| SOD-012 | Manager (checker) cannot be the same as the Head who noted | DB + Service |
| SOD-013 | Officer (reviewer) cannot be the same as the Manager who checked | DB + Service |
| SOD-014 | VP (approver) cannot be the same as the Officer who reviewed | DB + Service |

### API Endpoints

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/v1/procurement/purchase-requests` | `procurement.purchase-request.view` | List PRs (RDAC-scoped, paginated) |
| POST | `/api/v1/procurement/purchase-requests` | `procurement.purchase-request.create` | Create PR draft with items |
| GET | `/api/v1/procurement/purchase-requests/{id}` | `procurement.purchase-request.view` | PR detail with items + approval timeline |
| PATCH | `/api/v1/procurement/purchase-requests/{id}` | `procurement.purchase-request.create` | Edit draft PR |
| POST | `/api/v1/procurement/purchase-requests/{id}/submit` | `procurement.purchase-request.create` | Submit draft → SUBMITTED |
| POST | `/api/v1/procurement/purchase-requests/{id}/note` | `procurement.purchase-request.note` | Head notes → NOTED |
| POST | `/api/v1/procurement/purchase-requests/{id}/check` | `procurement.purchase-request.check` | Manager checks → CHECKED |
| POST | `/api/v1/procurement/purchase-requests/{id}/review` | `procurement.purchase-request.review` | Officer reviews → REVIEWED |
| POST | `/api/v1/procurement/purchase-requests/{id}/vp-approve` | `approvals.vp.approve` | VP approves → APPROVED |
| POST | `/api/v1/procurement/purchase-requests/{id}/reject` | `procurement.purchase-request.note` | Reject at current stage |
| POST | `/api/v1/procurement/purchase-requests/{id}/cancel` | `procurement.purchase-request.create` | Cancel draft/submitted only |

**POST /purchase-requests request body:**
```json
{
  "department_id": 3,
  "urgency": "normal",
  "justification": "Required for production line maintenance scheduled next week.",
  "notes": "Optional additional notes",
  "items": [
    {
      "item_description": "Hydraulic Oil ISO 46",
      "unit_of_measure": "liters",
      "quantity": 20,
      "estimated_unit_cost": 450.00,
      "specifications": "Must be food-grade certified"
    }
  ]
}
```

### Frontend Screens

**PurchaseRequestListPage** — `/procurement/purchase-requests`
Permission: `procurement.purchase-request.view`

DataTable columns:
```
PR Reference | Department | Urgency | Total Est. Cost | Status | Current Actor | Date | Actions
```

Filters: Status | Department | Date Range | Urgency

**CreatePurchaseRequestPage** — `/procurement/purchase-requests/new`
Permission: `procurement.purchase-request.create`

Form sections:
```
Header:
  Department        DepartmentSelect (user's accessible depts)
  Urgency           Select: Normal / Urgent / Critical
  Justification     textarea, min 20 chars
  Notes             textarea optional

Line Items (dynamic rows):
  Item Description  text required
  UoM               text (pcs, kg, liters, etc.)
  Quantity          number > 0
  Est. Unit Cost    ₱ number > 0
  Est. Total        auto-computed, read-only
  Specifications    text optional
  [+ Add Item]  [Remove]

Footer:
  Total Estimated Cost  ₱ X,XXX,XXX.XX (sum of all items, read-only)
  [Save Draft]  [Submit for Approval]
```

**PurchaseRequestDetailPage** — `/procurement/purchase-requests/:id`
Permission: `procurement.purchase-request.view`

Layout:
- Header with PR reference, status badge, urgency badge
- Line items table (read-only)
- Total cost summary
- Approval timeline (who did what at each stage + comments + timestamp)
- Action buttons using SodActionButton:

```typescript
// Note button — Head only
<SodActionButton
  permission="procurement.purchase-request.note"
  sodBlocked={pr.submitted_by_id === currentUser.id}
  blockedTooltip="You submitted this PR. A different Department Head must note it."
  onClick={() => handleNote(pr.id)}
>
  Note (Acknowledge)
</SodActionButton>

// Check button — Manager only
<SodActionButton
  permission="procurement.purchase-request.check"
  sodBlocked={pr.noted_by_id === currentUser.id}
  blockedTooltip="You noted this PR. A different Manager must check it."
  onClick={() => handleCheck(pr.id)}
>
  Check (Verify)
</SodActionButton>

// Review button — Officer only
<SodActionButton
  permission="procurement.purchase-request.review"
  sodBlocked={pr.checked_by_id === currentUser.id}
  blockedTooltip="You checked this PR. A different Officer must review it."
  onClick={() => handleReview(pr.id)}
>
  Review
</SodActionButton>

// VP Approve button — VP only
<SodActionButton
  permission="approvals.vp.approve"
  sodBlocked={pr.reviewed_by_id === currentUser.id}
  blockedTooltip="You reviewed this PR. A different VP must approve it."
  onClick={() => handleVpApprove(pr.id)}
>
  Final Approve
</SodActionButton>
```

---

## 2B — Purchase Order

### Database Schema

```sql
CREATE TABLE purchase_orders (
    id                      BIGSERIAL PRIMARY KEY,
    po_reference            VARCHAR(30) UNIQUE NOT NULL,
    -- format: PO-YYYY-MM-NNNNN

    purchase_request_id     BIGINT NOT NULL REFERENCES purchase_requests(id),
    vendor_id               BIGINT NOT NULL REFERENCES vendors(id),

    po_date                 DATE NOT NULL DEFAULT CURRENT_DATE,
    delivery_date           DATE NOT NULL,
    payment_terms           VARCHAR(50) NOT NULL,   -- e.g. "NET 30", "COD", "50% DP 50% on delivery"
    delivery_address        TEXT,

    status                  VARCHAR(30) NOT NULL DEFAULT 'draft',
    -- draft, sent, partially_received, fully_received, closed, cancelled

    total_po_amount         NUMERIC(15,2) NOT NULL DEFAULT 0,
    -- updated by trigger from po_line_items

    created_by_id           BIGINT NOT NULL REFERENCES users(id),   -- Purchasing Officer
    sent_at                 TIMESTAMP,
    closed_at               TIMESTAMP,
    cancellation_reason     TEXT,

    notes                   TEXT,
    created_at              TIMESTAMP DEFAULT NOW(),
    updated_at              TIMESTAMP DEFAULT NOW(),

    CONSTRAINT chk_po_delivery_after_po_date
        CHECK (delivery_date >= po_date)
);

CREATE SEQUENCE purchase_order_seq START 1;

CREATE TABLE purchase_order_items (
    id                      BIGSERIAL PRIMARY KEY,
    purchase_order_id       BIGINT NOT NULL REFERENCES purchase_orders(id) ON DELETE CASCADE,
    pr_item_id              BIGINT REFERENCES purchase_request_items(id),
    -- Links back to the original PR item for three-way match

    item_description        VARCHAR(255) NOT NULL,
    unit_of_measure         VARCHAR(30) NOT NULL,
    quantity_ordered        NUMERIC(12,3) NOT NULL,
    agreed_unit_cost        NUMERIC(12,2) NOT NULL,
    total_cost              NUMERIC(15,2) GENERATED ALWAYS AS (quantity_ordered * agreed_unit_cost) STORED,

    quantity_received       NUMERIC(12,3) NOT NULL DEFAULT 0,
    -- updated as goods receipts come in
    quantity_pending        NUMERIC(12,3) GENERATED ALWAYS AS (quantity_ordered - quantity_received) STORED,

    line_order              SMALLINT DEFAULT 1,
    created_at              TIMESTAMP DEFAULT NOW(),
    updated_at              TIMESTAMP DEFAULT NOW(),

    CONSTRAINT chk_po_item_qty_positive    CHECK (quantity_ordered > 0),
    CONSTRAINT chk_po_item_cost_positive   CHECK (agreed_unit_cost > 0),
    CONSTRAINT chk_po_item_received_valid  CHECK (quantity_received >= 0 AND quantity_received <= quantity_ordered)
);

-- Trigger: update PO total when items change
CREATE OR REPLACE FUNCTION update_po_total()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    UPDATE purchase_orders
    SET total_po_amount = (
        SELECT COALESCE(SUM(total_cost), 0)
        FROM purchase_order_items
        WHERE purchase_order_id = COALESCE(NEW.purchase_order_id, OLD.purchase_order_id)
    ),
    updated_at = NOW()
    WHERE id = COALESCE(NEW.purchase_order_id, OLD.purchase_order_id);
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_po_total
AFTER INSERT OR UPDATE OR DELETE ON purchase_order_items
FOR EACH ROW EXECUTE FUNCTION update_po_total();

CREATE INDEX idx_po_status ON purchase_orders(status);
CREATE INDEX idx_po_vendor ON purchase_orders(vendor_id);
CREATE INDEX idx_po_pr ON purchase_orders(purchase_request_id);
```

### State Machine

```
DRAFT
  └─ [Purchasing Officer sends to vendor] ──────► SENT
       └─ [Goods Receipt partial] ───────────────► PARTIALLY_RECEIVED
       └─ [Goods Receipt all items] ────────────► FULLY_RECEIVED ──► CLOSED (auto)
  └─ [Cancel before sending] ──────────────────► CANCELLED
```

### Validation Rules

| Code | Rule | Enforced At |
|---|---|---|
| PO-001 | `purchase_request_id` must be in `approved` status | Service |
| PO-002 | `vendor_id` must be an active, accredited vendor | FormRequest |
| PO-003 | `delivery_date` must be on or after `po_date` | DB CHECK + FormRequest |
| PO-004 | At least one line item required | Service |
| PO-005 | PO items must reference PR items (for three-way match) | Service |
| PO-006 | `agreed_unit_cost` must be > 0 | DB CHECK |
| PO-007 | Cannot cancel a PO that has received any goods | Service |
| PO-008 | `payment_terms` required | FormRequest |

### API Endpoints

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/v1/procurement/purchase-orders` | `procurement.purchase-order.view` | List POs |
| POST | `/api/v1/procurement/purchase-orders` | `procurement.purchase-order.create` | Create PO from approved PR |
| GET | `/api/v1/procurement/purchase-orders/{id}` | `procurement.purchase-order.view` | PO detail with items |
| PATCH | `/api/v1/procurement/purchase-orders/{id}` | `procurement.purchase-order.manage` | Edit draft PO |
| POST | `/api/v1/procurement/purchase-orders/{id}/send` | `procurement.purchase-order.manage` | Mark as sent to vendor |
| POST | `/api/v1/procurement/purchase-orders/{id}/cancel` | `procurement.purchase-order.manage` | Cancel PO |

---

## 2C — Vendor Management

> **IMPORTANT:** The `vendors` table and `Vendor` model already exist in the AP domain (`app/Domains/AP/Models/Vendor.php`, `app/Domains/AP/Services/VendorService.php`). The existing table has: `name`, `tin`, `ewt_rate_id`, `atc_code`, `is_ewt_subject`, `is_active`, `address`, `contact_person`, `email`, `phone`, `notes`, `created_by`.
>
> **Do NOT create a new vendors table.** The Procurement module reuses the existing AP vendors. The Purchasing Officer manages vendors through the existing AP vendor management UI. If additional fields are needed (e.g. `accreditation_status`, `bank_account_no`), add them via a new migration on the existing `vendors` table.

### Additive Migration — extend existing vendors table

**File:** `database/migrations/2026_03_05_000004_add_procurement_fields_to_vendors.php`

```php
public function up(): void
{
    Schema::table('vendors', function (Blueprint $table) {
        $table->string('accreditation_status', 20)->default('pending')->after('is_active');
        // pending, accredited, suspended, blacklisted
        $table->text('accreditation_notes')->nullable()->after('accreditation_status');
        $table->string('bank_name', 80)->nullable()->after('accreditation_notes');
        $table->string('bank_account_no', 50)->nullable()->after('bank_name');   // consistent with existing codebase convention (_no not _number)
        $table->string('bank_account_name', 150)->nullable()->after('bank_account_no');
        $table->string('payment_terms', 50)->nullable()->after('bank_account_name');
    });

    DB::statement("
        ALTER TABLE vendors ADD CONSTRAINT chk_vendor_accreditation_status
        CHECK (accreditation_status IN ('pending','accredited','suspended','blacklisted'))
    ");
}

public function down(): void
{
    DB::statement('ALTER TABLE vendors DROP CONSTRAINT IF EXISTS chk_vendor_accreditation_status');
    Schema::table('vendors', function (Blueprint $table) {
        $table->dropColumn(['accreditation_status','accreditation_notes','bank_name','bank_account_no','bank_account_name','payment_terms']);
    });
}
```

### API Endpoints

The existing AP vendor endpoints are at `/api/v1/ap/vendors`. Procurement reuses them — no new vendor routes are needed. The Purchasing Officer permission `vendors.manage` is already defined in the seeder. Add procurement-specific vendor list endpoint only if a separate filtered view is required:

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| *(reuse)* | `/api/v1/ap/vendors` | `vendors.view` | List vendors (AP module, already exists) |
| *(reuse)* | `/api/v1/ap/vendors/{id}` | `vendors.view` | Vendor detail (already exists) |
| PATCH | `/api/v1/ap/vendors/{id}/accredit` | `vendors.manage` | Mark accredited (new action on existing endpoint) |
| PATCH | `/api/v1/ap/vendors/{id}/suspend` | `vendors.manage` | Suspend vendor (new action on existing endpoint) |

### Frontend Screen: VendorListPage

The existing AP Vendors page (`/accounting/vendors`) already covers this. Update it to show the new `accreditation_status` column and add Accredit/Suspend action buttons. **Do not create a duplicate page.**

Add to the existing vendor form:
```
Accreditation Status  Select: Pending / Accredited / Suspended / Blacklisted
Bank Name             text
Bank Account No.      text  (field name: bank_account_no)
Bank Account Name     text
Payment Terms         text or select
```

---

## 2D — Goods Receipt

### Database Schema

```sql
CREATE TABLE goods_receipts (
    id                      BIGSERIAL PRIMARY KEY,
    gr_reference            VARCHAR(30) UNIQUE NOT NULL,
    -- format: GR-YYYY-MM-NNNNN

    purchase_order_id       BIGINT NOT NULL REFERENCES purchase_orders(id),
    received_by_id          BIGINT NOT NULL REFERENCES users(id),   -- Warehouse Head
    received_date           DATE NOT NULL DEFAULT CURRENT_DATE,
    delivery_note_number    VARCHAR(100),   -- vendor's delivery note / DR number
    condition_notes         TEXT,
    status                  VARCHAR(20) NOT NULL DEFAULT 'draft',
    -- draft, confirmed

    confirmed_by_id         BIGINT REFERENCES users(id),
    confirmed_at            TIMESTAMP,

    three_way_match_passed  BOOLEAN NOT NULL DEFAULT false,
    -- set to true by system when PR + PO + GR all reconcile
    ap_invoice_created      BOOLEAN NOT NULL DEFAULT false,
    ap_invoice_id           BIGINT,   -- FK to ap_invoices once created

    created_at              TIMESTAMP DEFAULT NOW(),
    updated_at              TIMESTAMP DEFAULT NOW()
);

CREATE SEQUENCE goods_receipt_seq START 1;

CREATE TABLE goods_receipt_items (
    id                      BIGSERIAL PRIMARY KEY,
    goods_receipt_id        BIGINT NOT NULL REFERENCES goods_receipts(id) ON DELETE CASCADE,
    po_item_id              BIGINT NOT NULL REFERENCES purchase_order_items(id),

    quantity_received       NUMERIC(12,3) NOT NULL,
    unit_of_measure         VARCHAR(30) NOT NULL,
    condition               VARCHAR(20) NOT NULL DEFAULT 'good',
    -- good, damaged, partial, rejected
    remarks                 TEXT,
    created_at              TIMESTAMP DEFAULT NOW(),

    CONSTRAINT chk_gr_item_qty_positive CHECK (quantity_received > 0),
    CONSTRAINT chk_gr_item_condition
        CHECK (condition IN ('good','damaged','partial','rejected'))
);
```

### Three-Way Match Logic

```php
// GoodsReceiptService::checkThreeWayMatch(GoodsReceipt $gr): bool

// Match passes when:
// 1. GR references a valid, SENT PO
// 2. PO references a valid, APPROVED PR
// 3. All GR item quantities <= PO item ordered quantities
// 4. Condition = 'good' or 'partial' for all items (not 'rejected')

// If match passes:
//   - Set gr.three_way_match_passed = true
//   - Update po_item.quantity_received for each item
//   - If all PO items fully received → update PO status to FULLY_RECEIVED
//   - If partial → update PO status to PARTIALLY_RECEIVED
//   - Trigger AP invoice auto-creation (2E)
```

### API Endpoints

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| GET | `/api/v1/procurement/goods-receipts` | `procurement.goods-receipt.view` | List GRs |
| POST | `/api/v1/procurement/goods-receipts` | `procurement.goods-receipt.create` | Create GR draft |
| GET | `/api/v1/procurement/goods-receipts/{id}` | `procurement.goods-receipt.view` | GR detail |
| POST | `/api/v1/procurement/goods-receipts/{id}/confirm` | `procurement.goods-receipt.confirm` | Confirm receipt + trigger three-way match |

### Validation Rules

| Code | Rule | Enforced At |
|---|---|---|
| GR-001 | `purchase_order_id` must be in `sent` or `partially_received` status | Service |
| GR-002 | `quantity_received` per item cannot exceed `quantity_pending` on the PO item | Service |
| GR-003 | At least one item line required | Service |
| GR-004 | `received_date` cannot be in the future | FormRequest |
| GR-005 | Cannot confirm a GR that has `rejected` condition items without remarks | Service |

---

## 2E — AP Invoice Auto-Creation

### Service: ThreeWayMatchService

```php
// app/Domains/Procurement/Services/ThreeWayMatchService.php

class ThreeWayMatchService
{
    public function __construct(
        private readonly ApInvoiceService $apInvoiceService,
        private readonly GlAutoPostingService $glPostingService,
    ) {}

    public function runMatch(GoodsReceipt $gr): ThreeWayMatchResult
    {
        $po = $gr->purchaseOrder;
        $pr = $po->purchaseRequest;

        // Validate all three records exist and are in correct states
        $this->assertPrApproved($pr);         // PR-001
        $this->assertPoSent($po);              // PO-001
        $this->assertQuantitiesMatch($gr, $po); // GR-002

        // Mark match as passed
        $gr->update(['three_way_match_passed' => true]);

        // Update PO item received quantities
        foreach ($gr->items as $grItem) {
            $grItem->poItem->increment('quantity_received', $grItem->quantity_received);
        }

        // Update PO status
        $allReceived = $po->items->every(fn($i) => $i->quantity_received >= $i->quantity_ordered);
        $po->update(['status' => $allReceived ? 'fully_received' : 'partially_received']);

        // Auto-create AP invoice draft
        $apInvoice = $this->apInvoiceService->createFromPo($po, $gr);
        $gr->update(['ap_invoice_created' => true, 'ap_invoice_id' => $apInvoice->id]);

        return new ThreeWayMatchResult(passed: true, apInvoice: $apInvoice);
    }
}
```

### AP Invoice creation from PO

```php
// ApInvoiceService::createFromPo(PurchaseOrder $po, GoodsReceipt $gr): ApInvoice

public function createFromPo(PurchaseOrder $po, GoodsReceipt $gr): ApInvoice
{
    return ApInvoice::create([
        'vendor_id'          => $po->vendor_id,
        'invoice_date'       => now()->toDateString(),
        'due_date'           => now()->addDays($this->parseDueDays($po->payment_terms))->toDateString(),
        'amount'             => $po->total_po_amount,
        'description'        => "Auto-created from PO {$po->po_reference} / GR {$gr->gr_reference}",
        'status'             => 'draft',
        'source'             => 'auto_procurement',
        'purchase_order_id'  => $po->id,
        'goods_receipt_id'   => $gr->id,
        'created_by_id'      => auth()->id(),   // system/background job
    ]);
}
```

The auto-created AP invoice then follows the **existing AP workflow**:
Accounting Officer reviews → submits → Accounting Manager approves (SOD-009 applies).

---

---

# TASK 3 — IMPLIED MODULES ANALYSIS

---

## Module 1 — Inventory Management

**Status:** Critical Now
**Scope:** Tracks stock levels for raw materials, work-in-progress, and finished goods across the Warehouse. Integrates with Procurement (goods received → stock in) and Production (materials consumed → stock out). PPC Head uses this for material ordering decisions and inventory control.

**Top 5 Tables:**
```
inventory_items          Master list of all stock-keeping units (SKUs) with current balance
inventory_transactions   Every stock movement (receipt, issue, adjustment, return)
warehouses               Physical storage locations (Warehouse A, B, Cold Storage, etc.)
stock_levels             Current quantity per item per warehouse (denormalized for speed)
inventory_reorder_rules  Minimum stock levels and reorder quantities per item
```

**Roles Involved:**
- Warehouse Head: receives stock, issues stock, adjusts inventory
- PPC Head: views stock levels, sets reorder rules, triggers purchase requests when stock is low
- Production Head: issues materials to production lines
- Purchasing Officer: views stock to determine reorder quantities on POs
- Officer/Manager: view reports only

**Dependencies:** Procurement module (Goods Receipt creates inventory transactions)
**Estimated Complexity:** Large (20–30 dev-days)

---

## Module 2 — Production Planning

**Status:** Critical Now
**Scope:** Manages customer orders, production scheduling, and delivery timelines for both local and export customers. PPC Head uses this to plan what to produce, when, and in what quantity based on customer forecasts (Honda PH, Mitsuba PH delivery schedules). Feeds material requirements to Procurement and production targets to the Production Head.

**Top 5 Tables:**
```
customer_orders          Customer delivery schedules and order quantities
production_plans         Planned production runs per product per period
production_schedules     Daily/weekly machine and line assignments
bill_of_materials        Recipe: what raw materials are needed per product unit
material_requirements    Computed material needs based on production plan vs. stock
```

**Roles Involved:**
- PPC Head: creates and manages all production plans and delivery schedules
- Plant Manager: approves production plans
- Production Head: receives production schedule, executes
- Warehouse Head: issues materials per production plan
- VP: approves plans that require significant procurement or overtime

**Dependencies:** Inventory Management, Procurement
**Estimated Complexity:** Large (25–35 dev-days)

---

## Module 3 — Quality Control

**Status:** Phase 2
**Scope:** Manages incoming inspection (raw materials from Goods Receipt), in-process inspection (during production), and outgoing inspection (before customer delivery). QC/QA Head records inspection results and issues non-conformance reports (NCRs). QC/QA Manager signs off on delivery clearances.

**Top 5 Tables:**
```
inspection_plans         What to inspect, at which stage, with what criteria
inspection_records       Results of each inspection (pass/fail per criterion)
non_conformance_reports  NCR tracking: issue found, root cause, corrective action
quality_standards        Acceptance criteria per product and customer
customer_complaints      Customer-reported quality issues linked back to production batches
```

**Roles Involved:**
- QC/QA Head: conducts inspections, raises NCRs
- QC/QA Manager: approves NCRs, signs delivery clearances
- Production Head: responds to NCRs, implements corrective actions
- Warehouse Head: hold flagged stock pending QC clearance

**Dependencies:** Inventory Management, Production Planning
**Estimated Complexity:** Large (20–25 dev-days)

---

## Module 4 — Mold Management

**Status:** Phase 2
**Scope:** Tracks all injection molds owned or leased by the company. Each mold has a shot count limit; the Mold Manager schedules maintenance and records repairs. Molds are linked to production runs so quality issues can be traced back to mold condition. Integrates with Maintenance for repair work orders.

**Top 5 Tables:**
```
molds                    Mold master list (mold ID, product, owner — company or customer)
mold_shot_logs           Per-production-run shot count updates
mold_maintenance_plans   Scheduled maintenance at shot count intervals
mold_repair_records      Repair history with cost and downtime
mold_assignments         Which mold is currently in which machine
```

**Roles Involved:**
- Mold Manager: full CRUD, maintenance planning, repair records
- Mold Head: executes maintenance, records results
- Production Head: requests mold assignment for production runs
- Maintenance Head: executes repair work orders

**Dependencies:** Production Planning
**Estimated Complexity:** Medium (10–15 dev-days)

---

## Module 5 — Maintenance Management

**Status:** Phase 2
**Scope:** Manages preventive and corrective maintenance for all machines and equipment. Maintenance Head creates work orders from machine breakdown reports or scheduled PM schedules. Tracks downtime, spare parts used, and maintenance costs. Integrates with Inventory for spare parts stock.

**Top 5 Tables:**
```
equipment                Master list of all machines and equipment with specs
maintenance_schedules    PM plans (daily, weekly, monthly, hours-based)
work_orders              Corrective and preventive maintenance jobs
maintenance_logs         Completed work records with downtime and parts used
spare_parts_usage        Links work orders to inventory items consumed
```

**Roles Involved:**
- Maintenance Head: creates work orders, logs completed maintenance
- Plant Manager: approves major repair work orders
- Purchasing Officer: orders spare parts via Procurement
- Warehouse Head: issues spare parts from inventory

**Dependencies:** Inventory Management
**Estimated Complexity:** Medium (12–18 dev-days)

---

## Module 6 — ISO/IATF Compliance

**Status:** Phase 3
**Scope:** Manages document control, internal audits, corrective actions, and management review for ISO 9001 and IATF 16949 compliance. Management System Head maintains the document register and audit calendar. NCRs from QC feed into the CAPA (Corrective and Preventive Action) system. Generates audit reports and compliance dashboards.

**Top 5 Tables:**
```
controlled_documents     Document register with revision history and approval status
audit_plans              Internal audit schedule per department per year
audit_findings           Non-conformances found during audits with severity
capa_records             Corrective and Preventive Action tracking
management_review_records Annual management review meeting records and decisions
```

**Roles Involved:**
- Management System Head: full module ownership
- All Managers: respond to audit findings in their departments
- VP/President: reviews management review reports
- All Staff: read-only access to controlled documents relevant to their dept

**Dependencies:** QC Module (feeds NCRs into CAPA), all other modules (for document linkage)
**Estimated Complexity:** Medium (15–20 dev-days)

---

## Module 7 — Customer Delivery Management

**Status:** Phase 2
**Scope:** Manages the outbound logistics process from finished goods confirmation to customer delivery. ImpEx Officer handles export documentation (commercial invoice, packing list, bill of lading for Honda/Mitsuba shipments). Warehouse Head confirms dispatch. Tracks delivery status and generates delivery receipts. Links to AR for invoice generation.

**Top 5 Tables:**
```
delivery_orders          Customer delivery instructions with quantities and dates
shipment_records         Actual shipment details (truck, driver, plates, departure)
export_documents         Commercial invoices, packing lists, B/L for export shipments
delivery_receipts        Signed proof-of-delivery records
customer_master          Customer list (Honda PH, Mitsuba PH, local customers)
```

**Roles Involved:**
- ImpEx Officer: manages all export documentation
- Warehouse Head: confirms goods dispatched, attaches delivery receipts
- PPC Head: provides delivery schedule to guide shipment planning
- Accounting Officer: uses confirmed deliveries to trigger AR invoices

**Dependencies:** Production Planning, Inventory Management
**Estimated Complexity:** Medium (12–18 dev-days)

---

---

# TASK 4 — IMPLEMENTATION ROADMAP

---

## Prioritized Task Table

| # | Task | Description | Depends On | Dev-Days | Parallel With | Risk | Mitigation |
|---|---|---|---|---|---|---|---|
| 1 | Role Migration (1A) | Rename supervisor→head, add officer + VP roles, update Spatie seeder + migrations | None | 2 | — | Medium | DB snapshot before migration, rollback migration ready |
| 2 | Permission Profiles (1B) | New officer and VP permission sets, update seeder | Task 1 | 2 | — | Low | Feature flag: deploy profiles, activate per user manually first |
| 3 | Loan Workflow Refactor (1C) | Extend loan approval to 5-stage chain | Tasks 1–2 | 3 | — | Medium | Keep old 3-stage data intact; new columns nullable |
| 4 | Frontend Role Updates (1D) | TypeScript types, RoleBadge, sidebar, authStore | Tasks 1–3 | 2 | — | Low | Type errors are compile-time — caught before deploy |
| 5 | Regression Test Run | Full E2E + unit test pass after all Task 1 changes | Task 4 | 2 | — | High | Block deployment to production until all tests pass |
| 6 | Vendor Management (2C) | Vendor master CRUD | Tasks 1–5 | 3 | Task 7 | Low | Standalone module, no complex dependencies |
| 7 | PR Module (2A) | Purchase Request full workflow + SoD | Tasks 1–5 | 5 | Task 6 | Medium | 5-stage approval is new pattern; test all SoD paths |
| 8 | PO Module (2B) | Purchase Order creation from approved PR | Tasks 6–7 | 4 | — | Low | Depends on Vendor + PR being stable |
| 9 | Goods Receipt (2D) | Delivery confirmation + three-way match | Task 8 | 4 | — | Medium | Three-way match logic needs thorough unit tests |
| 10 | AP Auto-Creation (2E) | Auto-create AP invoice draft from confirmed GR | Task 9 | 3 | — | Medium | Ensure existing AP SOD-009 still enforced on auto-created invoices |
| 11 | VP Dashboard | Pending approvals page aggregating all modules | Tasks 7–10 | 2 | — | Low | Simple read aggregation; no write logic |
| 12 | Inventory Management | Stock tracking, transactions, reorder rules | Task 9 (GR creates stock-in) | 25 | — | Large | Scope separately; design data model carefully before any code |
| 13 | Production Planning | Customer orders, production schedules, BOM | Task 12 | 30 | — | Large | Largest module; may require dedicated sprint planning session |
| 14 | Customer Delivery | Outbound logistics + export docs | Task 13 | 15 | — | Medium | ImpEx-specific requirements need user interviews |
| 15 | Quality Control | Inspections, NCRs, delivery clearance | Tasks 12–13 | 22 | — | Medium | QC criteria need input from QC/QA Manager |
| 16 | Mold Management | Mold tracking, shot counts, maintenance | Task 13 | 12 | Task 17 | Low | Self-contained; Mold Manager is primary user |
| 17 | Maintenance Management | PM schedules, work orders, downtime | Task 12 | 15 | Task 16 | Low | Well-understood domain |
| 18 | ISO/IATF Compliance | Document control, audits, CAPA | Tasks 15–17 | 18 | — | Medium | Compliance rules need Management System Head involvement |

---

## Sprint Breakdown (2-Week Sprints)

### Sprint 1 (Weeks 1–2) — Role Refactor Foundation
**Goal:** 7-role model live in staging. Zero regressions.

```
Day 1–2:   DB snapshot. Run migrations (Tasks 1A, 1B steps 1–4).
           Verify all 7 roles exist. Verify head role works for existing users.
Day 3–4:   Update DepartmentPermissionService + Policy files + SoD matrix.
Day 5–6:   Update frontend TypeScript types, authStore, RoleBadge, Zod schemas.
Day 7–8:   Extend loan approval workflow to 5-stage chain + migrations.
Day 9–10:  Full regression test run. Fix any failures. Update Playwright fixtures.
```

**Go/No-Go Checklist — Sprint 1:**
- [ ] All 7 roles exist in `roles` table
- [ ] Existing users with `supervisor` role now have `head` role — verified in DB
- [ ] No user lost their permissions — spot-check 5 Head-role users in staging
- [ ] Loan submission still works for Staff
- [ ] Loan approval chain now shows 5 stages in UI
- [ ] Full regression test suite: 0 failures
- [ ] Rollback migration tested and confirmed working on staging clone

---

### Sprint 2 (Weeks 3–4) — Procurement Core
**Goal:** Vendor Management + Purchase Request live. Staff can submit PRs. Full approval chain works.

```
Day 1–2:   Vendor Management backend (schema + API endpoints).
Day 3–4:   Vendor Management frontend (VendorListPage, add/edit form).
Day 5–7:   Purchase Request backend (schema + state machine + SoD + all 6 endpoints).
Day 8–9:   Purchase Request frontend (list page + create page + detail page with all SodActionButtons).
Day 10:    Integration test: Staff creates PR → Head notes → Manager checks → Officer reviews → VP approves.
```

**Go/No-Go Checklist — Sprint 2:**
- [ ] Vendor can be created and accredited
- [ ] PR can be created by Staff with line items
- [ ] PR flows through all 5 stages correctly
- [ ] SOD-011 to SOD-014 all enforced: same person cannot do two consecutive steps
- [ ] VP sees pending PRs in Approvals Dashboard
- [ ] Rejected PR at any stage records `rejection_reason` and `rejection_stage`
- [ ] PR total_estimated_cost trigger fires correctly

---

### Sprint 3 (Weeks 5–6) — Purchase Order + Goods Receipt
**Goal:** Purchasing Officer can generate POs and Warehouse Head can confirm deliveries.

```
Day 1–3:   Purchase Order backend (schema + state machine + API).
Day 4–5:   Purchase Order frontend (list page + create from approved PR).
Day 6–7:   Goods Receipt backend (schema + three-way match logic).
Day 8–9:   Goods Receipt frontend (create GR from PO + confirm).
Day 10:    Integration test: Approved PR → PO created → GR confirms → three-way match passes.
```

**Go/No-Go Checklist — Sprint 3:**
- [ ] PO can only be created from an APPROVED PR
- [ ] PO reference format `PO-YYYY-MM-NNNNN` auto-generated correctly
- [ ] Partial delivery: GR for 60 of 100 items → PO status = PARTIALLY_RECEIVED
- [ ] Full delivery: GR for all items → PO status = FULLY_RECEIVED
- [ ] Three-way match passes when PR + PO + GR all reconcile
- [ ] Three-way match fails gracefully when quantities don't match

---

### Sprint 4 (Weeks 7–8) — AP Auto-Creation + VP Dashboard + Hardening
**Goal:** Confirmed GRs auto-create AP invoice drafts. VP has a unified approvals view. Full procurement E2E tested.

```
Day 1–2:   AP Invoice auto-creation service + integration with existing AP module.
Day 3–4:   VP Pending Approvals Dashboard (aggregates PRs + Loans awaiting VP).
Day 5–6:   Procurement notifications (all 5 approval stages send in-app + email notifications).
Day 7–8:   Full procurement E2E Playwright tests (happy path + SoD violation paths).
Day 9–10:  Performance testing (ensure PR list with 500+ records still loads in < 2s).
           Security: verify Accounting Officer cannot create PRs, Staff cannot approve.
```

**Go/No-Go Checklist — Sprint 4:**
- [ ] Confirmed GR auto-creates AP invoice in `draft` status
- [ ] Auto-created AP invoice is pre-filled with vendor and amount from PO
- [ ] SOD-009 still applies on auto-created AP invoice (Officer cannot approve own invoice)
- [ ] VP Dashboard shows all pending PRs and Loans in one view
- [ ] All 5 approval stage notifications delivered correctly
- [ ] Full Playwright E2E: Staff → Head → Manager → Officer → VP → PO → GR → AP Invoice
- [ ] SoD violation attempt correctly blocked and tooltip shown

---

### Sprint 5–8 (Weeks 9–16) — Inventory Management
Dedicated sprint series for the Inventory module (most complex non-payroll module).
Requires separate detailed spec before development begins — user interviews with Warehouse Head and PPC Head recommended.

### Sprint 9–12 (Weeks 17–24) — Production Planning
Largest module. Requires BOM data gathering from Production Manager before schema design.

### Sprint 13–16 (Weeks 25–32) — QC, Mold, Maintenance, Delivery
Can be parallelized between two developers once Inventory and Production are stable.

### Sprint 17–18 (Weeks 33–36) — ISO/IATF Compliance
Final phase — depends on all other modules being stable for document linkage.

---

## Regression Test Scope After Task 1 Refactor

The following tests must all pass before deploying Task 1 to production:

| Test | Type | Owner | Expected Result |
|---|---|---|---|
| Login — all 7 roles | E2E Playwright | Dev | Each role reaches correct default page |
| HR Head imports attendance CSV | E2E | Dev | Renamed role still has `attendance.import` permission |
| HR Head cannot approve leave | Unit | Dev | `head` role blocked from `leave.approve` |
| HR Manager approves leave (SOD-002) | E2E | Dev | Cannot approve own request |
| Payroll run full 8-step workflow | E2E | Dev | End-to-end passes with renamed roles |
| Admin gets 403 on /hr/employees | E2E | Dev | Admin redirect to /admin/users |
| Staff sees only own payslips | E2E | Dev | Cannot access /hr/employees |
| Loan old data integrity | DB Query | Dev | All loan records have valid status after migration |
| VP can access /approvals/pending | E2E | Dev | New page renders, no 403 |
| Officer can access GL view | E2E | Dev | `gl.view` permission in officer's profile |
| Permission cache invalidation | Unit | Dev | Redis cache busts on dept assignment change |
| `workflow_version=1` loans still process on old chain | Feature Test | Dev | `PATCH /loans/{ulid}/approve` still works for v1 loans |

---

*Ogami ERP — AI-Generated Refactor & New Modules Plan*
*Generated: March 2026 · Stack: Laravel 11 · React 18 · PostgreSQL 16 · Docker*
*Based on: changes.md (client org structure) · ogami_erp_system_roadmap.md (v3.0)*
