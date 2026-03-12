<?php

declare(strict_types=1);

namespace App\Domains\Budget\Models;

use App\Shared\Traits\HasPublicUlid;
use App\Domains\HR\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int|null $department_id
 * @property int|null $parent_id
 * @property bool $is_active
 * @property int $created_by_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Department|null $department
 * @property-read CostCenter|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CostCenter> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnnualBudget> $budgets
 * @property-read User $createdBy
 */
final class CostCenter extends Model implements Auditable
{
    use HasPublicUlid;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cost_centers';

    protected $fillable = [
        'name',
        'code',
        'description',
        'department_id',
        'parent_id',
        'is_active',
        'created_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(AnnualBudget::class, 'cost_center_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
