<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Department Scope Middleware — RDAC enforcement.
 *
 * Resolves the authenticated user's accessible department IDs from the
 * `user_department_access` pivot table and binds them into the service
 * container so Eloquent global scopes (HasDepartmentScope) can filter
 * queries to the user's assigned departments only.
 *
 * Bypass roles (see all departments):
 *   admin, executive
 *
 * Usage: `Route::middleware('dept_scope')`
 */
class DepartmentScopeMiddleware
{
    private const BYPASS_ROLES = ['admin', 'executive'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasAnyRole(self::BYPASS_ROLES)) {
            // Multi-department RDAC: load all assigned department IDs.
            // Falls back to legacy users.department_id when pivot is empty.
            $departmentIds = Cache::remember(
                "user:{$user->id}:dept_ids",
                now()->addMinutes(15),
                function () use ($user): array {
                    // Try pivot table first (new multi-dept RDAC)
                    $pivotIds = $user->departments()->pluck('departments.id')
                        ->map(fn ($v) => (int) $v)
                        ->all();

                    if (! empty($pivotIds)) {
                        return $pivotIds;
                    }

                    // Fall back to single department_id column (legacy)
                    return $user->department_id ? [(int) $user->department_id] : [];
                }
            );

            app()->instance('dept_scope.active', true);
            app()->instance('dept_scope.department_ids', $departmentIds);
            // Keep single-value alias for backward compat
            app()->instance('dept_scope.department_id', $departmentIds[0] ?? null);
        } else {
            app()->instance('dept_scope.active', false);
            app()->instance('dept_scope.department_ids', []);
            app()->instance('dept_scope.department_id', null);
        }

        return $next($request);
    }
}
