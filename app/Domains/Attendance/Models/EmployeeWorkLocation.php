<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $work_location_id
 * @property Carbon $effective_date
 * @property Carbon|null $end_date
 * @property bool $is_primary
 * @property int|null $assigned_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read WorkLocation $workLocation
 * @property-read User|null $assignedByUser
 */
final class EmployeeWorkLocation extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'employee_work_locations';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'work_location_id',
        'effective_date',
        'end_date',
        'is_primary',
        'assigned_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'end_date' => 'date',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Scope to assignments active on a specific date.
     */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('effective_date', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
