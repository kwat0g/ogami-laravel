<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use App\Domains\HR\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Redis;

/**
 * Transforms the authenticated User into the API representation
 * that the frontend uses to hydrate its permission store.
 *
 * Response shape:
 * {
 *   "id": 1,
 *   "name": "Jane Doe",
 *   "email": "jane@ogamierp.local",
 *   "roles": ["manager"],
 *   "permissions": ["employees.view", "payroll.initiate", ...],
 *   "department_ids": [1, 2],
 *   "primary_department_id": 1,
 *   "primary_department_code": "ACCTG",
 *   "employee_id": 5,
 *   "timezone": "Asia/Manila",
 *   "must_change_password": false
 * }
 *
 * @mixin User
 */
class UserPermissionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        // Eager load relationships
        $user->loadMissing(['employee', 'employee.department', 'departments']);

        // Resolve department IDs: pivot table first, fall back to legacy column, then employee department
        $deptRows = $user->departments()->get(['departments.id', 'departments.code', 'user_department_access.is_primary']);
        $deptIds = $deptRows->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

        // Fallback 1: legacy department_id column
        if (empty($deptIds) && $user->department_id) {
            $deptIds = [(int) $user->department_id];
        }

        // Fallback 2: employee's department
        if (empty($deptIds) && $user->employee?->department_id) {
            $deptIds = [(int) $user->employee->department_id];
        }

        // Get primary department from pivot table
        $primaryDeptRow = $deptRows->firstWhere('pivot.is_primary', true) ?? ($deptRows->first() ?? null);
        $primaryDeptId = $primaryDeptRow?->id;
        $primaryDeptCode = $primaryDeptRow?->code;

        // Fallback: get department code from employee's department if pivot table is empty
        if (! $primaryDeptCode && $user->employee?->department) {
            $primaryDeptCode = $user->employee->department->code;
        }

        // Fallback: get department code from legacy column
        if (! $primaryDeptCode && $user->department_id) {
            $dept = Department::find($user->department_id);
            $primaryDeptCode = $dept?->code;
        }

        // Get employee_id from loaded relationship
        $employeeId = $user->employee?->id;

        // Track user activity for "active users" count (only if Redis is available)
        if (config('database.redis.default') !== null) {
            try {
                Redis::setex(
                    "user_activity:{$user->id}",
                    1800, // 30 minutes in seconds
                    now()->timestamp
                );
            } catch (\Throwable $e) {
                // Redis not available, skip activity tracking
            }
        }

        $roles = $user->getRoleNames()->values();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roles,
            'permissions' => $user->getEffectivePermissions()->values(),
            'department_ids' => $deptIds,
            'primary_department_id' => $primaryDeptId ? (int) $primaryDeptId : null,
            'primary_department_code' => $primaryDeptCode,
            'employee_id' => $employeeId,
            'timezone' => $user->timezone ?? config('app.timezone', 'Asia/Manila'),
            'must_change_password' => $user->password_changed_at === null,
        ];
    }
}
