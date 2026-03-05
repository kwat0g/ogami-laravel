<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\HR\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Department Permission Profile — v2
 *
 * Stores the set of Spatie permission names that are effective for a given
 * role when assigned to a specific department.
 *
 * One row = one (department × role) profile, e.g.:
 *   - HRD   + manager    → full HR + payroll-HR permissions
 *   - ACCTG + accounting_manager → full accounting permissions
 *   - HRD   + hr_manager         → full HR permissions
 *   - HRD   + supervisor         → limited HR permissions (no approve/delete)
 *   - PROD  + hr_manager         → self-service only (common manager permissions)
 *
 * @property int $id
 * @property int $department_id
 * @property string $role 'hr_manager' | 'accounting_manager' | 'supervisor'
 * @property list<string> $permissions Spatie permission name array
 * @property string|null $profile_label
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Department $department
 */
class DepartmentPermissionProfile extends Model
{
    protected $table = 'department_permission_profiles';

    protected $fillable = [
        'department_id',
        'role',
        'permissions',
        'profile_label',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<static> $query */
    public function scopeForRole(Builder $query, string $role): void
    {
        $query->where('role', $role);
    }
}
