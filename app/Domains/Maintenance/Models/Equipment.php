<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $ulid
 * @property string $equipment_code
 * @property string $name
 * @property string $category
 * @property string|null $manufacturer
 * @property string|null $model_number
 * @property string|null $serial_number
 * @property string|null $location
 * @property string|null $commissioned_on
 * @property string $status
 * @property bool $is_active
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class Equipment extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'equipment_code',
        'name',
        'category',
        'manufacturer',
        'model_number',
        'serial_number',
        'location',
        'commissioned_on',
        'status',
        'is_active',
        'created_by_id',
    ];

    protected $casts = [
        'commissioned_on' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<MaintenanceWorkOrder, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'equipment_id');
    }

    /** @return HasMany<PmSchedule, $this> */
    public function pmSchedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class, 'equipment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
