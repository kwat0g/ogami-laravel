<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

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
 *   "employee_id": 5,
 *   "timezone": "Asia/Manila"
 * }
 *
 * @mixin \App\Models\User
 */
class UserPermissionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;

        // Eager load employee relationship
        $user->loadMissing('employee');

        // Resolve department IDs: pivot table first, fall back to legacy column.
        $deptRows = $user->departments()->get(['departments.id', 'user_department_access.is_primary']);
        $deptIds = $deptRows->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
        if (empty($deptIds) && $user->department_id) {
            $deptIds = [(int) $user->department_id];
        }
        $primaryDeptId = $deptRows->firstWhere('pivot.is_primary', true)?->id
            ?? ($deptIds[0] ?? null);

        // Get employee_id from loaded relationship
        $employeeId = $user->employee?->id;

        // Track user activity for "active users" count
        Redis::setex(
            "user_activity:{$user->id}",
            1800, // 30 minutes in seconds
            now()->timestamp
        );

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getEffectivePermissions()->values(),
            'department_ids' => $deptIds,
            'primary_department_id' => $primaryDeptId ? (int) $primaryDeptId : null,
            'employee_id' => $employeeId,
            'timezone' => $user->timezone ?? config('app.timezone', 'Asia/Manila'),
        ];
    }
}
