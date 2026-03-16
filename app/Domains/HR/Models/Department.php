<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'permission_profile_role',
        'custom_permissions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'custom_permissions' => 'array',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }
}
