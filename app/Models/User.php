<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\AP\Models\Vendor;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Services\DepartmentModuleService;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * User — core identity + RBAC + department scoping
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $department_id Legacy column — prefer departments() relation
 * @property int|null $vendor_id
 * @property int|null $client_id
 * @property string|null $timezone
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Department> $departments
 * @property-read Department|null $primaryDepartment
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    // Alias Spatie's hasPermissionTo so we can override it with department scoping.
    use HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
    }

    use Notifiable;

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
        'employee_id',
        'is_active',
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
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Many-to-many: Departments this user belongs to.
     * Pivot table: user_department_access
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'user_department_access',
            'user_id',
            'department_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    /**
     * Primary department (convenience relation for legacy lookups).
     */
    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Employee profile linked to this user account (if any).
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Vendor profile linked to this user account (if any).
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->whereNull('locked_until')
            ->orWhere('locked_until', '<', now());
    }

    public function scopeWithDepartment($query, int $departmentId)
    {
        return $query->whereHas('departments', fn ($q) => $q->where('department_id', $departmentId));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Check if this user is a portal account (vendor or client).
     */
    public function isPortalAccount(): bool
    {
        return $this->vendor_id !== null || $this->client_id !== null;
    }

    /**
     * Get cached department codes for quick lookup.
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
     * Check if user has access to a specific department.
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

        return $this->departments()->where('departments.id', $departmentId)->exists();
    }

    /**
     * Override Spatie's hasPermissionTo with RBAC v2 department-module filtering.
     *
     * Priority (first match wins):
     *  1. Super admin — bypass all checks.
     *  2. Users WITH department assignments — use DepartmentModuleService (RBAC v2).
     *  3. Users WITHOUT department assignments — fall back to Spatie.
     *
     * @param  string|Permission  $permission
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $permName = is_string($permission) ? $permission : $permission->name;

        // Step 1: Super admin bypass.
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Step 2: System roles (admin, executive, vice_president) always use Spatie's
        // native role-based permission check — DepartmentModuleService only applies
        // to core operational roles (manager, officer, head, staff).
        if ($this->hasAnyRole(['admin', 'executive', 'vice_president'])) {
            return $this->spatieHasPermissionTo($permission, $guardName);
        }

        // Step 3: For users WITH department assignments, use RBAC v2 module permissions.
        if ($this->departments()->exists()) {
            return DepartmentModuleService::userHasPermission($this, $permName);
        }

        // Step 4: For users without departments, fall back to Spatie's check.
        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    /**
     * Compute the effective permission set for this user (department-scoped).
     *
     * Used by UserPermissionsResource to send the correct permission list
     * to the frontend, ensuring sidebar and UI reflect actual access.
     *
     * @return Collection<int, string>
     */
    public function getEffectivePermissions(): Collection
    {
        // System roles (super_admin, admin, executive, vice_president) always use
        // their full Spatie role permissions — DepartmentModuleService only handles
        // core roles (manager, officer, head, staff).
        if ($this->hasAnyRole(['super_admin', 'admin', 'executive', 'vice_president'])) {
            return $this->permissions->pluck('name')
                ->merge($this->getPermissionsViaRoles()->pluck('name'))
                ->unique()
                ->values();
        }

        // RBAC v2: Use DepartmentModuleService for department-assigned users
        if ($this->departments()->exists()) {
            $deptPerms = DepartmentModuleService::getUserPermissions($this);

            // If department-scoped permissions are empty, fall back to Spatie role permissions
            if (empty($deptPerms)) {
                return $this->permissions->pluck('name')
                    ->merge($this->getPermissionsViaRoles()->pluck('name'))
                    ->unique()
                    ->values();
            }

            return collect($deptPerms);
        }

        // Fallback to Spatie permissions for users without departments
        return $this->permissions->pluck('name')
            ->merge($this->getPermissionsViaRoles()->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * Clear department-related caches when departments are updated.
     */
    public function clearDepartmentCache(): void
    {
        Cache::forget("user:{$this->id}:dept_codes");

        // Clear module permission caches for all user's departments
        $role = $this->roles->first()?->name;
        if ($role) {
            foreach ($this->departments as $dept) {
                Cache::forget("dept_mod_perms:{$role}:{$dept->id}");
            }
        }
    }

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Rate limit key for login attempts.
     */
    public function rateLimitKey(): string
    {
        return 'login:'.$this->email;
    }

    /**
     * Remaining login attempts before lockout.
     */
    public function remainingLoginAttempts(): int
    {
        return max(0, 5 - $this->failed_login_attempts);
    }

    /**
     * Check if the user account is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Increment failed login attempts and lock if necessary.
     * Alias for recordFailedLogin() for backward compatibility.
     */
    public function incrementFailedAttempts(): void
    {
        $this->recordFailedLogin();
    }

    /**
     * Increment failed login attempts and lock if necessary.
     */
    public function recordFailedLogin(): void
    {
        $this->increment('failed_login_attempts');

        if ($this->failed_login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
            Log::warning('User account locked due to failed login attempts', [
                'user_id' => $this->id,
                'email' => $this->email,
            ]);
        }
    }

    /**
     * Reset failed login attempts on successful login.
     * Alias for recordSuccessfulLogin() for backward compatibility.
     */
    public function resetFailedAttempts(): void
    {
        $this->recordSuccessfulLogin();
    }

    /**
     * Reset failed login attempts on successful login.
     */
    public function recordSuccessfulLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }
}
