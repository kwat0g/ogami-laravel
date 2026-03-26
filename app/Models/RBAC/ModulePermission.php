<?php

declare(strict_types=1);

namespace App\Models\RBAC;

use App\Models\User;
use Carbon\Carbon;
use Database\Factories\RBAC\ModulePermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ModulePermission - Defines permissions for a role within a module.
 *
 * @property int $id
 * @property string $module_key
 * @property string $role
 * @property list<string> $permissions
 * @property array|null $sod_restrictions
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ModulePermission extends Model
{
    /** @use HasFactory<ModulePermissionFactory> */
    use HasFactory;

    protected $table = 'module_permissions';

    protected $fillable = [
        'module_key',
        'role',
        'permissions',
        'sod_restrictions',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'sod_restrictions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ============================================================================
    // Relationships
    // ============================================================================

    /**
     * The module this permission belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_key', 'module_key');
    }

    /**
     * User who created this permission set.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this permission set.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to only active permissions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by module key.
     */
    public function scopeForModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }

    /**
     * Scope by role.
     */
    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    // ============================================================================
    // Static Helpers
    // ============================================================================

    /**
     * Get permissions for a specific role+module combination.
     *
     * @return list<string>
     */
    public static function getPermissions(string $moduleKey, string $role): array
    {
        $record = self::active()
            ->forModule($moduleKey)
            ->forRole($role)
            ->first();

        return $record?->permissions ?? [];
    }

    /**
     * Check if permissions exist for a role+module.
     */
    public static function exists(string $moduleKey, string $role): bool
    {
        return self::active()
            ->forModule($moduleKey)
            ->forRole($role)
            ->exists();
    }

    /**
     * Get all roles that have permissions defined for a module.
     *
     * @return list<string>
     */
    public static function rolesForModule(string $moduleKey): array
    {
        return self::active()
            ->forModule($moduleKey)
            ->pluck('role')
            ->toArray();
    }
}
