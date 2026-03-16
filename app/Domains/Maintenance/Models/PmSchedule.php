<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property int $equipment_id
 * @property string $task_name
 * @property int $frequency_days
 * @property string|null $last_done_on
 * @property string|null $next_due_on
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Equipment $equipment
 */
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
        'next_due_on' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}
