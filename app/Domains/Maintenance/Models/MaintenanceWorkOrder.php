<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class MaintenanceWorkOrder extends Model implements AuditableContract
{
    use HasPublicUlid, Auditable;

    protected $table = 'maintenance_work_orders';

    protected $fillable = [
        'equipment_id',
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
        'created_by_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_at'   => 'datetime',
    ];

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reported_by_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
