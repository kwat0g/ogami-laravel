<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DepartmentPermissionServiceV3 as DepartmentPermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property int|null $department_id
 * @property int $failed_login_attempts
 * @property \Illuminate\Support\Carbon|null $locked_until
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon|null $password_changed_at
 * @property string|null $timezone
 */
class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use AuditableTrait, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // Alias Spatie's hasPermissionTo so we can override it with department scoping.
    use HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'vendor_id',
        'client_id',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
        'password_changed_at',
        'timezone',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'failed_login_attempts' => 'integer',
        ];
    }

    // ── Account lock helpers ──────────────────────────────────────────────────

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');
    }

    public function lockAccount(int $minutes = 30): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
            'failed_login_attempts' => 0,
        ]);
    }

    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);
    }

    // ── Auditable configuration ───────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function transformAudit(array $data): array
    {
        return $data;
    }

    // ── RDAC — Role-Restricted Department Access Control ─────────────────────

    /**
     * The employee record linked to this user account (null for admin/executive system accounts).
     */
    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domains\HR\Models\Employee::class);
    }

    /**
     * The vendor account linked to this user (set for vendor portal users).
     */
    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\AP\Models\Vendor::class);
    }

    /**
     * The customer account linked to this user (set for client portal users).
     */
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\AR\Models\Customer::class, 'client_id');
    }

    /**
     * All departments this user has access to (multi-department RDAC).
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\HR\Models\Department::class,
            'user_department_access',
            'user_id',
            'department_id',
        )->withPivot('is_primary')->withTimestamps();
    }

    /**
     * Cached list of department IDs assigned to this user.
     *
     * @return list<int>
     */
    public function accessibleDepartmentIds(): array
    {
        return Cache::remember(
            "user:{$this->id}:dept_ids",
            now()->addMinutes(15),
            fn (): array => $this->departments()->pluck('departments.id')->map(fn ($v) => (int) $v)->all(),
        );
    }

    /**
     * Returns true when the user has explicit access to a given department.
     * Admin and Executive bypass RDAC (they see all departments).
     *
     * When $departmentId is 0 (null cast to int) or null, the record has
     * no department assignment yet — we allow access rather than blocking
     * on unscoped records (covers migrated data and test fixtures).
     */
    public function hasDepartmentAccess(int $departmentId): bool
    {
        if ($this->hasAnyRole(['admin', 'executive'])) {
            return true;
        }

        // Records not yet assigned to a department are unscoped — allow.
        if ($departmentId === 0) {
            return true;
        }

        return in_array($departmentId, $this->accessibleDepartmentIds(), true);
    }

    // ── Department-Scoped Permission Override ───────────────────────────────

    /**
     * Cached list of department CODES this user belongs to.
     *
     * @return list<string>
     */
    public function accessibleDepartmentCodes(): array
    {
        return Cache::remember(
            "user:{$this->id}:dept_codes",
            now()->addMinutes(15),
            fn (): array => $this->departments()->pluck('departments.code')->all(),
        );
    }

    /**
     * Override Spatie's hasPermissionTo with department-scoped filtering.
     *
     * Priority (first match wins):
     *  1. Spatie base check — if the user lacks the permission entirely, deny.
     *  2. Admin / Executive / Staff — bypass department scoping.
     *  3. Direct user permissions — bypass department scoping (admin override).
     *  4. No departments assigned — fall back to full role permissions
     *     (graceful degradation for fresh accounts / test fixtures).
     *  5. Department-scoped role permissions — permission must exist in the
     *     user's department permission config group.
     *
     * @param  string|\Spatie\Permission\Contracts\Permission  $permission
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Step 1: Check Spatie's native permission resolution (role + direct).
        if (! $this->spatieHasPermissionTo($permission, $guardName)) {
            return false;
        }

        // Step 2: Roles that bypass department scoping entirely.
        if ($this->hasAnyRole(['admin', 'executive', 'staff'])) {
            return true;
        }

        // Step 3: Direct permissions (givePermissionTo) bypass department scoping.
        // This lets admins grant specific permissions that override the department map.
        $permName = is_string($permission) ? $permission : $permission->name;
        if ($this->hasDirectPermission($permName)) {
            return true;
        }

        // Step 4: If no departments are assigned yet, allow all role permissions.
        // Department scoping only activates once a user has ≥ 1 department.
        $deptCodes = $this->accessibleDepartmentCodes();
        if (empty($deptCodes)) {
            return true;
        }

        // Step 5: Role-based permissions go through department permission map.
        $roleName = $this->getRoleNames()->first() ?? 'staff';

        if (! DepartmentPermissionService::isRoleDepartmentScoped($roleName)) {
            return true; // Role not configured for scoping — allow.
        }

        return DepartmentPermissionService::isPermissionAllowed($roleName, $deptCodes, $permName);
    }

    /**
     * Compute the effective permission set for this user (department-scoped).
     *
     * Used by UserPermissionsResource to send the correct permission list
     * to the frontend, ensuring sidebar and UI reflect actual access.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getEffectivePermissions(): \Illuminate\Support\Collection
    {
        $allPerms = $this->getAllPermissions()->pluck('name');

        // Roles that bypass department scoping.
        if ($this->hasAnyRole(['admin', 'executive', 'staff'])) {
            return $allPerms;
        }

        // No departments assigned — return full role permissions.
        $deptCodes = $this->accessibleDepartmentCodes();
        if (empty($deptCodes)) {
            return $allPerms;
        }

        $roleName = $this->getRoleNames()->first() ?? 'staff';

        if (! DepartmentPermissionService::isRoleDepartmentScoped($roleName)) {
            return $allPerms;
        }

        $allowed = DepartmentPermissionService::getAllowedPermissions($roleName, $deptCodes);

        // Also include any direct permissions (admin overrides).
        $directPerms = $this->getDirectPermissions()->pluck('name');
        $effectiveSet = collect($allowed)->merge($directPerms)->unique();

        return $allPerms->intersect($effectiveSet)->values();
    }

    /**
     * Clear all cached department data for this user.
     * Call this whenever the user's department assignment changes.
     */
    public function clearDepartmentCache(): void
    {
        Cache::forget("user:{$this->id}:dept_ids");
        Cache::forget("user:{$this->id}:dept_codes");

        $roleName = $this->getRoleNames()->first() ?? 'staff';
        $deptCodes = $this->accessibleDepartmentCodes();
        DepartmentPermissionService::clearCacheForUser($roleName, $deptCodes);
    }

    // ── Role convenience helpers ──────────────────────────────────────────────

    /** The set of department-scoped manager roles. */
    public const MANAGER_ROLES = ['manager', 'plant_manager', 'production_manager', 'qc_manager', 'mold_manager'];

    /** True when user holds any department-scoped manager role. */
    public function isManager(): bool
    {
        return $this->hasAnyRole(self::MANAGER_ROLES);
    }

    /** True when user holds the `supervisor` role. */
    public function isSupervisor(): bool
    {
        return $this->hasRole('supervisor');
    }
}
