<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class Equipment extends Model implements AuditableContract
{
    use HasPublicUlid, Auditable;

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
        'is_active'       => 'boolean',
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

    /** @return BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
