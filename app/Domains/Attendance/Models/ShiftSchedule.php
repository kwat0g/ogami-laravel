<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $start_time HH:MM:SS
 * @property string $end_time HH:MM:SS
 * @property int $break_minutes 0–120
 * @property bool $is_night_shift virtual AS PostgreSQL expression
 * @property string $work_days comma-separated ISO weekdays e.g. "1,2,3,4,5"
 * @property bool $is_flexible
 * @property int $grace_period_minutes
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, EmployeeShiftAssignment> $assignments
 */
final class ShiftSchedule extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'shift_schedules';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'start_time',
        'end_time',
        'break_minutes',
        'work_days',
        'is_flexible',
        'grace_period_minutes',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_night_shift' => 'boolean',
            'is_flexible' => 'boolean',
            'is_active' => 'boolean',
            'break_minutes' => 'integer',
            'grace_period_minutes' => 'integer',
        ];
    }

    /** Work days as an array of ISO weekday integers. */
    public function getWorkDaysArrayAttribute(): array
    {
        return array_map('intval', explode(',', $this->work_days));
    }

    /** Net working minutes per day (end - start - break). */
    public function netWorkingMinutes(): int
    {
        [$sh, $sm] = explode(':', $this->start_time);
        [$eh, $em] = explode(':', $this->end_time);

        $startMins = (int) $sh * 60 + (int) $sm;
        $endMins = (int) $eh * 60 + (int) $em;

        // Handle overnight shifts
        if ($endMins <= $startMins) {
            $endMins += 24 * 60;
        }

        return $endMins - $startMins - $this->break_minutes;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }
}
