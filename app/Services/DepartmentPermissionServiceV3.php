<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\HR\Models\Department;
use App\Models\DepartmentPermissionProfile;
use App\Models\DepartmentPermissionTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Department Permission Service — v3
 *
 * NEW: Template-based permission assignment.
 * Departments can be assigned a permission template (e.g., 'accounting_full')
 * instead of relying on hardcoded department codes.
 *
 * Backward compatible: Falls back to v2 behavior if no template is assigned.
 *
 * Usage:
 *   1. Run DepartmentPermissionTemplateSeeder to create templates
 *   2. Set department.permission_profile_role = 'accounting_full' (or any template_key)
 *   3. Service will use template permissions instead of hardcoded profiles
 */
final class DepartmentPermissionServiceV3
{
    /** Cache TTL in minutes */
    private const CACHE_TTL_MINUTES = 15;

    /**
     * Permissions that are NEVER allowed when operating on one's own records.
     *
     * @var list<string>
     */
    private const SELF_ACTION_PREVENTION = [
        'employees.update_salary',
        'employees.terminate',
        'employees.suspend',
        'employees.activate',
        'leaves.head_approve',
        'leaves.manager_check',
        'overtime.approve',
        'loans.hr_approve',
        'loans.approve',
        'payroll.hr_approve',
        'payroll.acctg_approve',
        'system.assign_roles',
        'vendor_invoices.approve',
        'customer_invoices.approve',
    ];

    /**
     * Check whether a specific permission is allowed for the given role + departments.
     */
    public static function isPermissionAllowed(string $roleName, array $deptCodes, string $permission): bool
    {
        $allowed = self::getAllowedPermissions($roleName, $deptCodes);

        return in_array($permission, $allowed, true);
    }

    /**
     * Compute the full merged list of allowed permissions for a role + department(s).
     *
     * NEW: First checks for department.permission_profile_role (template-based).
     * Falls back to legacy DepartmentPermissionProfile if no template assigned.
     */
    public static function getAllowedPermissions(string $roleName, array $deptCodes): array
    {
        $sortedCodes = array_unique($deptCodes);
        sort($sortedCodes);
        $cacheKey = "dept_perms_v3:{$roleName}:".implode(',', $sortedCodes);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            static function () use ($roleName, $sortedCodes): array {
                try {
                    $allPermissions = [];

                    foreach ($sortedCodes as $deptCode) {
                        $dept = Department::where('code', $deptCode)->first();
                        if (! $dept) {
                            Log::warning("Department not found: {$deptCode}");

                            continue;
                        }

                        // NEW: Check if department has a template assigned
                        if ($dept->permission_profile_role) {
                            $perms = self::getPermissionsFromTemplate(
                                $dept->permission_profile_role,
                                $roleName
                            );
                            $allPermissions = array_merge($allPermissions, $perms);
                        } else {
                            // FALLBACK: Use legacy DepartmentPermissionProfile
                            $perms = self::getPermissionsFromLegacyProfile(
                                $roleName,
                                $dept->id
                            );
                            $allPermissions = array_merge($allPermissions, $perms);
                        }
                    }

                    return array_values(array_unique($allPermissions));
                } catch (\Exception $e) {
                    Log::error('Error computing department permissions: '.$e->getMessage());

                    return [];
                }
            }
        );
    }

    /**
     * Get permissions from a template (v3 approach).
     */
    private static function getPermissionsFromTemplate(string $templateKey, string $roleName): array
    {
        $template = DepartmentPermissionTemplate::where('template_key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            Log::warning("Permission template not found: {$templateKey}");

            return [];
        }

        $managerRoles = ['manager', 'officer', 'vice_president'];
        $field = in_array($roleName, $managerRoles, true) ? 'manager_permissions' : 'supervisor_permissions';

        return $template->{$field} ?? [];
    }

    /**
     * Get permissions from legacy profile (v2 approach).
     */
    private static function getPermissionsFromLegacyProfile(string $roleName, int $deptId): array
    {
        $profile = DepartmentPermissionProfile::active()
            ->forRole($roleName)
            ->where('department_id', $deptId)
            ->first();

        if (! $profile) {
            // No profile = role is not scoped for this dept
            return [];
        }

        return $profile->permissions ?? [];
    }

    /**
     * Check if a role is configured for department scoping.
     */
    public static function isRoleDepartmentScoped(string $roleName): bool
    {
        // Check if ANY department has a template or legacy profile
        $hasTemplates = Department::whereNotNull('permission_profile_role')->exists();
        $hasLegacyProfiles = DepartmentPermissionProfile::active()
            ->forRole($roleName)
            ->exists();

        return $hasTemplates || $hasLegacyProfiles;
    }

    /**
     * Get the self-action prevention permission list.
     *
     * @return list<string>
     */
    public static function getSelfActionPreventionPermissions(): array
    {
        return self::SELF_ACTION_PREVENTION;
    }

    /**
     * Clear cache for a specific user.
     */
    public static function clearCacheForUser(string $roleName, array $deptCodes): void
    {
        $sortedCodes = array_unique($deptCodes);
        sort($sortedCodes);
        $cacheKey = "dept_perms_v3:{$roleName}:".implode(',', $sortedCodes);
        Cache::forget($cacheKey);
        Cache::forget("dept_scoped_v3:{$roleName}");
    }

    /**
     * Clear ALL department permission caches.
     */
    public static function clearAllCaches(): void
    {
        foreach (['manager', 'officer', 'head', 'vice_president'] as $role) {
            Cache::forget("dept_scoped_v3:{$role}");
        }
    }

    /**
     * Assign a template to a department (convenience method).
     */
    public static function assignTemplateToDepartment(int $deptId, string $templateKey): bool
    {
        $dept = Department::find($deptId);
        if (! $dept) {
            return false;
        }

        $template = DepartmentPermissionTemplate::where('template_key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return false;
        }

        $dept->update(['permission_profile_role' => $templateKey]);

        // Clear caches for this department
        self::clearAllCaches();

        return true;
    }

    /**
     * Get available templates for dropdown.
     */
    public static function getAvailableTemplates(): array
    {
        return DepartmentPermissionTemplate::where('is_active', true)
            ->pluck('label', 'template_key')
            ->toArray();
    }
}
