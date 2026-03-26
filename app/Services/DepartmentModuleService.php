<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\HR\Models\Department;
use App\Models\DepartmentPermissionProfile;
use App\Models\RBAC\DepartmentModuleException;
use App\Models\RBAC\Module;
use App\Models\RBAC\ModulePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Department Module Service - Scalable RBAC Permission Resolution
 *
 * This service replaces hardcoded 18 roles with 7 generic roles + department modules.
 *
 * Permission Resolution: Role + Department Module = Effective Permissions
 * Example: manager + HR module = Full HR access
 *          manager + Accounting module = Full Accounting access
 */
final class DepartmentModuleService
{
    /** Cache TTL in minutes */
    private const CACHE_TTL_MINUTES = 15;

    /** Core business roles (7 roles) */
    private const CORE_ROLES = ['manager', 'officer', 'head', 'staff'];

    /** System roles (4 roles) */
    private const SYSTEM_ROLES = ['super_admin', 'admin', 'executive', 'vice_president'];

    /**
     * Get effective permissions for a user.
     *
     * Merges permissions from all departments the user belongs to.
     *
     * @return list<string>
     */
    public static function getUserPermissions(User $user): array
    {
        // System roles bypass module system
        if ($user->hasAnyRole(self::SYSTEM_ROLES)) {
            return self::getSystemRolePermissions($user);
        }

        // Get user's primary role (should be one of core roles)
        $role = self::getUserPrimaryRole($user);
        if (! $role) {
            Log::warning('User has no recognized role', ['user_id' => $user->id]);

            return self::getSafeDefaultPermissions('staff');
        }

        // Get all departments the user has access to
        $departments = $user->departments()->active()->get();
        if ($departments->isEmpty()) {
            Log::warning('User has no department assignments', ['user_id' => $user->id]);

            return self::getSafeDefaultPermissions($role);
        }

        // Merge permissions from all departments
        $allPermissions = [];
        foreach ($departments as $department) {
            $perms = self::getPermissionsForDepartment($role, $department);
            $allPermissions = array_merge($allPermissions, $perms);
        }

        return array_values(array_unique($allPermissions));
    }

    /**
     * Get permissions for a specific role + department combination.
     *
     * @return list<string>
     */
    public static function getPermissionsForDepartment(string $role, Department $department): array
    {
        $cacheKey = "dept_mod_perms:{$role}:{$department->id}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($role, $department) {
                // CASE 1: Department has no module assigned - check for DepartmentPermissionProfile
                if (! $department->hasModule()) {
                    // Try to get permissions from DepartmentPermissionProfile (v3 system)
                    $profilePerms = self::getDepartmentProfilePermissions($role, $department);
                    if (! empty($profilePerms)) {
                        return $profilePerms;
                    }

                    // No active profile found - use safe defaults
                    // User model will fall back to Spatie if needed via hasPermissionTo
                    return self::getSafeDefaultPermissions($role);
                }

                // CASE 2: Check for department-specific exceptions
                $exception = $department->getExceptionForRole($role);
                if ($exception) {
                    return self::applyException(
                        self::getModulePermissions($role, $department->module_key),
                        $exception
                    );
                }

                // CASE 3: Use standard module permissions
                return self::getModulePermissions($role, $department->module_key);
            }
        );
    }

    /**
     * Get permissions from module_permissions table.
     *
     * @return list<string>
     */
    public static function getModulePermissions(string $role, string $moduleKey): array
    {
        // Validate module exists
        if (! Module::exists($moduleKey)) {
            Log::error('Invalid module_key referenced', [
                'module_key' => $moduleKey,
                'role' => $role,
            ]);

            return self::getSafeDefaultPermissions($role);
        }

        // Get permissions from database
        $permissions = ModulePermission::getPermissions($moduleKey, $role);

        // If no permissions defined, use safe defaults
        if (empty($permissions)) {
            Log::warning('No permissions defined for role+module', [
                'role' => $role,
                'module_key' => $moduleKey,
            ]);

            return self::getSafeDefaultPermissions($role);
        }

        return $permissions;
    }

    /**
     * Check if a user has a specific permission.
     */
    public static function userHasPermission(User $user, string $permission): bool
    {
        // Superadmin bypass
        if ($user->hasRole('superadmin')) {
            return true;
        }

        $permissions = self::getUserPermissions($user);

        // Check for wildcard permissions (e.g., 'employees.*')
        foreach ($permissions as $userPerm) {
            if (self::permissionMatches($userPerm, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a permission matches (supports wildcards).
     *
     * Examples:
     *   - 'employees.*' matches 'employees.view', 'employees.create'
     *   - 'hr.*' matches 'hr.employees.view', 'hr.attendance.import'
     *   - '*' matches everything
     */
    public static function permissionMatches(string $pattern, string $permission): bool
    {
        // Exact match
        if ($pattern === $permission) {
            return true;
        }

        // Universal wildcard
        if ($pattern === '*') {
            return true;
        }

        // Wildcard match (e.g., 'employees.*' matches 'employees.view')
        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -1);

            return str_starts_with($permission, $prefix);
        }

        return false;
    }

    /**
     * Get the user's primary role (one of the 7 core roles).
     */
    public static function getUserPrimaryRole(User $user): ?string
    {
        $allValidRoles = array_merge(self::CORE_ROLES, self::SYSTEM_ROLES);

        foreach ($user->getRoleNames() as $role) {
            if (in_array($role, $allValidRoles, true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Get permissions for system roles.
     *
     * @return list<string>
     */
    private static function getSystemRolePermissions(User $user): array
    {
        if ($user->hasRole('super_admin')) {
            return ['*']; // All permissions
        }

        if ($user->hasRole('admin')) {
            return [
                'system.*',
                'users.*',
                'roles.*',
                'permissions.*',
                'modules.*',
                'reports.view',
            ];
        }

        if ($user->hasRole('executive')) {
            return [
                'reports.view',
                'reports.executive',
                'approvals.final',
                'employees.view',
                'payroll.view_runs',
                'dashboard.executive',
            ];
        }

        if ($user->hasRole('vice_president')) {
            return [
                'reports.view',
                'approvals.final',
                'employees.view',
                'payroll.view_runs',
                'loans.vp_approve',
                'procurement.vp_approve',
                'inventory.mrq.vp_approve',
            ];
        }

        return self::getSafeDefaultPermissions('staff');
    }

    /**
     * Get safe default permissions when configuration is missing.
     *
     * These are minimal permissions that won't break the user experience
     * but also won't grant excessive access.
     *
     * @return list<string>
     */
    public static function getSafeDefaultPermissions(string $role): array
    {
        $defaults = [
            'super_admin' => ['*'],
            'admin' => ['system.*', 'users.*', 'self.*'],
            'executive' => ['reports.view', 'approvals.final', 'self.*'],
            'vice_president' => ['reports.view', 'approvals.final', 'self.*'],
            'manager' => ['self.*', 'team.view', 'approvals.level2'],
            'officer' => ['self.*', 'team.view'],
            'head' => ['self.*', 'team.view', 'approvals.level1'],
            'staff' => ['self.*'],
        ];

        return $defaults[$role] ?? ['self.view_profile'];
    }

    /**
     * Apply permission exception to base permissions.
     *
     * @param  list<string>  $basePermissions
     * @return list<string>
     */
    private static function applyException(array $basePermissions, DepartmentModuleException $exception): array
    {
        $result = $basePermissions;

        // Remove permissions
        if ($exception->permissions_remove) {
            $result = array_diff($result, $exception->permissions_remove);
        }

        // Add permissions
        if ($exception->permissions_add) {
            $result = array_merge($result, $exception->permissions_add);
        }

        return array_values(array_unique($result));
    }

    /**
     * Clear permission cache for a user.
     */
    public static function clearUserCache(User $user): void
    {
        $departments = $user->departments()->pluck('departments.id');
        $role = self::getUserPrimaryRole($user);

        foreach ($departments as $deptId) {
            Cache::forget("dept_mod_perms:{$role}:{$deptId}");
        }
    }

    /**
     * Clear all permission caches.
     */
    public static function clearAllCaches(): void
    {
        // Clear all dept_mod_perms keys
        // Note: In production, use a cache prefix or tags for more efficient clearing
        foreach (self::CORE_ROLES as $role) {
            $departments = Department::active()->pluck('id');
            foreach ($departments as $deptId) {
                Cache::forget("dept_mod_perms:{$role}:{$deptId}");
            }
        }
    }

    /**
     * Get all available modules for dropdown.
     *
     * @return array<string, string> [module_key => label]
     */
    public static function getAvailableModules(): array
    {
        return Module::active()
            ->pluck('label', 'module_key')
            ->toArray();
    }

    /**
     * Get modules that need attention (missing permissions, etc.)
     *
     * @return list<array>
     */
    public static function getModulesNeedingAttention(): array
    {
        $issues = [];

        foreach (Module::active()->get() as $module) {
            foreach (self::CORE_ROLES as $role) {
                if (! ModulePermission::exists($module->module_key, $role)) {
                    $issues[] = [
                        'module_key' => $module->module_key,
                        'module_label' => $module->label,
                        'role' => $role,
                        'issue' => 'missing_permissions',
                        'fix' => "php artisan module:seed-permissions {$module->module_key}",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Get permissions from DepartmentPermissionProfile when no module is assigned.
     * This provides backward compatibility with the v3 permission profile system.
     *
     * @return list<string>
     */
    private static function getDepartmentProfilePermissions(string $role, Department $department): array
    {
        $cacheKey = "dept_mod_perms:{$role}:{$department->id}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($role, $department): array {
                try {
                    $profile = DepartmentPermissionProfile::where('department_id', $department->id)
                        ->where('role', $role)
                        ->where('is_active', true)
                        ->first();

                    if ($profile && ! empty($profile->permissions)) {
                        return $profile->permissions;
                    }
                } catch (\Exception $e) {
                    Log::debug('Could not load DepartmentPermissionProfile', [
                        'department_id' => $department->id,
                        'role' => $role,
                        'error' => $e->getMessage(),
                    ]);
                }

                return [];
            }
        );
    }
}
