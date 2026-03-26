<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\HR\Models\Department;
use App\Models\DepartmentPermissionProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Department Permission Service — v2
 *
 * Resolves effective permissions for department-scoped roles (Manager, Supervisor)
 * by reading from the `department_permission_profiles` database table instead of
 * a PHP config file.  Profiles are cached for 15 minutes to avoid repeated DB hits.
 *
 * One profile row = (department × role) → list of Spatie permission names.
 *
 * Public interface is unchanged from v1 so the User model requires no edits:
 *   - isPermissionAllowed(role, deptCodes, permission): bool
 *   - getAllowedPermissions(role, deptCodes): array
 *   - isRoleDepartmentScoped(role): bool
 *   - getSelfActionPreventionPermissions(): array
 *   - clearCacheForUser(role, deptCodes): void
 *
 * Admin, Executive, and Staff roles bypass department filtering entirely
 * (handled at the User model level, not here).
 */
final class DepartmentPermissionService
{
    /** Cache TTL in minutes */
    private const CACHE_TTL_MINUTES = 15;

    /**
     * Permissions that are NEVER allowed when operating on one's own records.
     * Enforced in policies via SoD checks (SELF-001…004, SOD-001…007).
     *
     * @var list<string>
     */
    private const SELF_ACTION_PREVENTION = [
        'employees.update_salary',      // SELF-001
        'employees.terminate',          // SELF-002
        'employees.suspend',            // SELF-002
        'employees.activate',           // SOD-001 (related)
        'leaves.approve',               // SOD-002
        'overtime.approve',             // SOD-003
        'loans.hr_approve',             // SOD-004
        'loans.approve',                // SOD-004 (legacy)
        'payroll.hr_approve',           // SOD-005
        'payroll.acctg_approve',        // SOD-007
        'system.assign_roles',          // SELF-003
        'vendor_invoices.approve',      // SELF-004
        'customer_invoices.approve',    // SELF-004
    ];

    // ──────────────────────────────────────────────────────────────────────
    // Core permission resolution
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Check whether a specific permission is allowed for the given role + departments.
     *
     * @param  string  $roleName  e.g. 'manager', 'supervisor'
     * @param  list<string>  $deptCodes  e.g. ['HRD'], ['ACCTG', 'HRD']
     * @param  string  $permission  e.g. 'journal_entries.view'
     */
    public static function isPermissionAllowed(string $roleName, array $deptCodes, string $permission): bool
    {
        $allowed = self::getAllowedPermissions($roleName, $deptCodes);

        return in_array($permission, $allowed, true);
    }

    /**
     * Compute the full merged list of allowed permissions for a role + department(s).
     *
     * Fetches active profiles from DB for every dept code, merges their permission
     * arrays, and caches the result for CACHE_TTL_MINUTES minutes.
     *
     * Returns an empty array when the role has no active profiles at all — the
     * User model interprets an empty array as "role is not DB-scoped → allow all".
     *
     * @param  list<string>  $deptCodes
     * @return list<string>
     */
    public static function getAllowedPermissions(string $roleName, array $deptCodes): array
    {
        $sortedCodes = array_unique($deptCodes);
        sort($sortedCodes);
        $cacheKey = "dept_perms_v2:{$roleName}:".implode(',', $sortedCodes);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            static function () use ($roleName, $sortedCodes): array {
                try {
                    // Look up dept IDs by code
                    $deptIds = Department::whereIn('code', $sortedCodes)
                        ->pluck('id')
                        ->all();

                    if (empty($deptIds)) {
                        return [];
                    }

                    // Fetch active profiles for the given role + department IDs
                    $profiles = DepartmentPermissionProfile::active()
                        ->forRole($roleName)
                        ->whereIn('department_id', $deptIds)
                        ->get(['permissions']);

                    if ($profiles->isEmpty()) {
                        // No profiles found → role is not DB-scoped for these depts
                        return [];
                    }

                    // Merge all permission arrays from all matched profiles
                    $merged = [];
                    foreach ($profiles as $profile) {
                        $merged = array_merge($merged, $profile->permissions ?? []);
                    }

                    return array_values(array_unique($merged));
                } catch (QueryException) {
                    // Table does not exist yet (e.g. during initial migration/seeding).
                    // Return empty — User model treats this as "not scoped, allow all".
                    return [];
                }
            }
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Check if a role is configured for department scoping.
     *
     * A role is "scoped" when at least one active profile exists for it.
     * Roles with no profiles (admin, executive, staff) bypass filtering entirely.
     */
    public static function isRoleDepartmentScoped(string $roleName): bool
    {
        $cacheKey = "dept_scoped_v2:{$roleName}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            static function () use ($roleName): bool {
                try {
                    return DepartmentPermissionProfile::active()
                        ->forRole($roleName)
                        ->exists();
                } catch (QueryException) {
                    // Table does not exist yet (e.g. during initial migration/seeding).
                    // Return false — treats role as not department-scoped.
                    return false;
                }
            }
        );
    }

    /**
     * Get the self-action prevention permission list.
     * These permissions must never be exercised on the user's own records.
     *
     * @return list<string>
     */
    public static function getSelfActionPreventionPermissions(): array
    {
        return self::SELF_ACTION_PREVENTION;
    }

    /**
     * Forget cached department permissions (call when a user's department changes
     * or when a DepartmentPermissionProfile is updated).
     */
    public static function clearCacheForUser(string $roleName, array $deptCodes): void
    {
        $sortedCodes = array_unique($deptCodes);
        sort($sortedCodes);
        $cacheKey = "dept_perms_v2:{$roleName}:".implode(',', $sortedCodes);
        Cache::forget($cacheKey);
        Cache::forget("dept_scoped_v2:{$roleName}");
    }

    /**
     * Clear ALL department permission caches (call when profiles are bulk-updated
     * via admin UI).
     */
    public static function clearAllCaches(): void
    {
        foreach (['manager', 'officer', 'head', 'vice_president'] as $role) {
            // Flush scoped flag
            Cache::forget("dept_scoped_v2:{$role}");
        }
        // Individual dept-combo caches will expire naturally within CACHE_TTL_MINUTES
        // For an immediate flush, use Cache::flush() or tag-based cache if available.
    }
}
