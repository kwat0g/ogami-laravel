<?php

declare(strict_types=1);

namespace App\Models\RBAC;

use Carbon\Carbon;
use Database\Factories\RBAC\ModuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module - Reference table for department permission modules.
 *
 * @property int $id
 * @property string $module_key
 * @property string $label
 * @property string|null $description
 * @property array|null $default_permissions
 * @property array|null $permission_groups
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    protected $table = 'modules';

    protected $fillable = [
        'module_key',
        'label',
        'description',
        'default_permissions',
        'permission_groups',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_permissions' => 'array',
            'permission_groups' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ============================================================================
    // Relationships
    // ============================================================================

    /**
     * Permission definitions for each role in this module.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(ModulePermission::class, 'module_key', 'module_key');
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to only active modules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to find by module_key.
     */
    public function scopeByKey($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }

    // ============================================================================
    // Static Helpers
    // ============================================================================

    /**
     * Check if a module key exists.
     */
    public static function exists(string $moduleKey): bool
    {
        return self::active()->byKey($moduleKey)->exists();
    }

    /**
     * Get all valid module keys.
     *
     * @return list<string>
     */
    public static function validKeys(): array
    {
        return self::active()->pluck('module_key')->toArray();
    }

    /**
     * Get module by key or null.
     */
    public static function findByKey(string $moduleKey): ?self
    {
        return self::active()->byKey($moduleKey)->first();
    }
}
