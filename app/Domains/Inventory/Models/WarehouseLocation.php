<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Domains\HR\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $code
 * @property string $name
 * @property string|null $zone
 * @property string|null $bin
 * @property int|null $department_id
 * @property bool   $is_active
 */
final class WarehouseLocation extends Model
{
    use SoftDeletes;

    protected $table = 'warehouse_locations';

    protected $fillable = ['code', 'name', 'zone', 'bin', 'department_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return BelongsTo<Department, WarehouseLocation> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<StockBalance, WarehouseLocation> */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'location_id');
    }
}
