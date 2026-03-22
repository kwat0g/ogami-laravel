<?php

declare(strict_types=1);

namespace App\Models\RBAC;

use App\Domains\HR\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DepartmentModuleException - Custom permissions for specific departments.
 * 
 * @property int $id
 * @property int $department_id
 * @property string $role
 * @property list<string>|null $permissions_add
 * @property list<string>|null $permissions_remove
 * @property string|null $reason
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class DepartmentModuleException extends Model
{
    /** @use HasFactory<\Database\Factories\RBAC\DepartmentModuleExceptionFactory> */
    use HasFactory;

    protected $table = 'department_module_exceptions';

    protected $fillable = [
        'department_id',
        'role',
        'permissions_add',
        'permissions_remove',
        'reason',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'permissions_add' => 'array',
            'permissions_remove' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ============================================================================
    // Relationships
    // ============================================================================

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    // ============================================================================
    // Static Helpers
    // ============================================================================

    /**
     * Get exception for a specific department+role, or null.
     */
    public static function forDepartmentAndRole(int $departmentId, string $role): ?self
    {
        return self::active()
            ->forDepartment($departmentId)
            ->forRole($role)
            ->first();
    }

    /**
     * Check if an exception exists.
     */
    public static function exists(int $departmentId, string $role): bool
    {
        return self::active()
            ->forDepartment($departmentId)
            ->forRole($role)
            ->exists();
    }
}
