<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use App\Models\RBAC\DepartmentModuleException;
use App\Models\RBAC\Module;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $parent_department_id
 * @property int|null $plant_id
 * @property string|null $cost_center_code
 * @property int $annual_budget_centavos
 * @property int $fiscal_year_start_month
 * @property bool $is_active
 * @property string|null $module_key
 * @property array|null $permissions_override
 * @property string|null $permission_profile_role
 * @property array|null $custom_permissions
 */
final class Department extends Model implements Auditable
{
    /** @use HasFactory<DepartmentFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'departments';

    protected $fillable = [
        'code',
        'name',
        'parent_department_id',
        'plant_id',
        'cost_center_code',
        'annual_budget_centavos',
        'fiscal_year_start_month',
        'is_active',
        'module_key',
        'permissions_override',
        'permission_profile_role',
        'custom_permissions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'permissions_override' => 'array',
            'custom_permissions' => 'array',
        ];
    }

    // ============================================================================
    // Boot - Model Validation
    // ============================================================================

    protected static function boot(): void
    {
        parent::boot();

        self::saving(function ($department) {
            // Validate module_key if provided
            if ($department->module_key !== null) {
                $exists = Module::where('module_key', $department->module_key)
                    ->where('is_active', true)
                    ->exists();

                if (! $exists) {
                    $validModules = Module::active()->pluck('module_key')->implode(', ');
                    throw new \InvalidArgumentException(
                        "Invalid module_key: {$department->module_key}. ".
                        "Valid modules: {$validModules}"
                    );
                }
            }
        });

        self::saved(function ($department) {
            // Log warning if department has no module assigned
            if ($department->module_key === null) {
                Log::warning('Department saved without module_key', [
                    'department_id' => $department->id,
                    'department_code' => $department->code,
                    'department_name' => $department->name,
                ]);
            }
        });
    }

    // ============================================================================
    // Relationships
    // ============================================================================

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * The module assigned to this department.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_key', 'module_key');
    }

    /**
     * Permission exceptions for this department.
     */
    public function moduleExceptions(): HasMany
    {
        return $this->hasMany(DepartmentModuleException::class);
    }

    // ============================================================================
    // Scopes
    // ============================================================================

    /**
     * Scope to departments with a specific module.
     */
    public function scopeWithModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }

    /**
     * Scope to departments without a module assignment.
     */
    public function scopeWithoutModule($query)
    {
        return $query->whereNull('module_key');
    }

    /**
     * Scope to active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ============================================================================
    // Module Helpers
    // ============================================================================

    /**
     * Check if this department has a module assigned.
     */
    public function hasModule(): bool
    {
        return $this->module_key !== null;
    }

    /**
     * Get the module label, or null if no module.
     */
    public function moduleLabel(): ?string
    {
        return $this->module?->label;
    }

    /**
     * Check if this department has permission exceptions defined.
     */
    public function hasExceptions(): bool
    {
        return $this->moduleExceptions()->active()->exists();
    }

    /**
     * Get permission exception for a specific role.
     */
    public function getExceptionForRole(string $role): ?DepartmentModuleException
    {
        return $this->moduleExceptions()
            ->active()
            ->forRole($role)
            ->first();
    }

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }
}
