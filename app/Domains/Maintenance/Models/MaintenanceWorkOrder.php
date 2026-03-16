<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $ulid
 * @property int $equipment_id
 * @property int|null $mold_master_id
 * @property string $type
 * @property string $priority
 * @property string $status
 * @property string $title
 * @property string|null $description
 * @property int|null $reported_by_id
 * @property int|null $assigned_to_id
 * @property int $created_by_id
 * @property Carbon|null $scheduled_date
 * @property Carbon|null $completed_at
 * @property string|null $completion_notes
 * @property float|null $labor_hours
 * @property string|null $mwo_reference
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class MaintenanceWorkOrder extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'maintenance_work_orders';

    protected $fillable = [
        'equipment_id',
        'mold_master_id',
        'type',
        'priority',
        'status',
        'title',
        'description',
        'reported_by_id',
        'assigned_to_id',
        'scheduled_date',
        'completed_at',
        'completion_notes',
        'labor_hours',
        'created_by_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_at' => 'datetime',
        'labor_hours' => 'float',
    ];

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<WorkOrderPart, $this> */
    public function spareParts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class, 'work_order_id');
    }
}
