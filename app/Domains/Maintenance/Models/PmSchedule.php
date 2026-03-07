<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PmSchedule extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'pm_schedules';

    protected $fillable = [
        'equipment_id',
        'task_name',
        'frequency_days',
        'last_done_on',
        'is_active',
    ];

    protected $casts = [
        'last_done_on' => 'date',
        'next_due_on'  => 'date',
        'is_active'    => 'boolean',
    ];

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}
