<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One row per employee per work date (UNIQUE constraint).
 * All time-tracking, absence flags, holiday info and overtime credits for a
 * single worked day live here.
 *
 * @property int $id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $work_date
 * @property string $source biometric|csv_import|manual|system
 * @property string|null $time_in
 * @property string|null $time_out
 * @property int $worked_minutes 0–1440
 * @property int $late_minutes
 * @property int $undertime_minutes
 * @property int $overtime_minutes pre-approved OT credited (ATT-005)
 * @property bool $is_present
 * @property bool $is_absent
 * @property bool $is_rest_day
 * @property bool $is_holiday
 * @property string|null $holiday_type regular|special_non_working|special_working
 * @property bool $is_night_diff
 * @property int $night_diff_minutes
 * @property string|null $remarks
 * @property string|null $import_batch_id UUID — set when imported via CSV batch
 * @property int|null $processed_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Employee $employee
 */
final class AttendanceLog extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'attendance_logs';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'work_date',
        'source',
        'time_in',
        'time_out',
        'worked_minutes',
        'late_minutes',
        'undertime_minutes',
        'overtime_minutes',
        'is_present',
        'is_absent',
        'is_rest_day',
        'is_holiday',
        'holiday_type',
        'is_night_diff',
        'night_diff_minutes',
        'remarks',
        'import_batch_id',
        'processed_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'night_diff_minutes' => 'integer',
            'is_present' => 'boolean',
            'is_absent' => 'boolean',
            'is_rest_day' => 'boolean',
            'is_holiday' => 'boolean',
            'is_night_diff' => 'boolean',
        ];
    }

    /** Worked hours as a float. */
    public function workedHours(): float
    {
        return round($this->worked_minutes / 60, 2);
    }

    /** Overtime worked in hours. */
    public function overtimeHours(): float
    {
        return round($this->overtime_minutes / 60, 2);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
