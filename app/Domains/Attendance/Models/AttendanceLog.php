<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One row per employee per work date (UNIQUE constraint).
 * All time-tracking, absence flags, holiday info and overtime credits for a
 * single worked day live here.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property string $source biometric|csv_import|manual|system|web_clock|leave_correction
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
 * @property int|null $work_location_id
 * @property string|null $time_in_latitude
 * @property string|null $time_in_longitude
 * @property string|null $time_in_accuracy_meters
 * @property string|null $time_in_distance_meters
 * @property bool|null $time_in_within_geofence
 * @property array|null $time_in_device_info
 * @property string|null $time_in_override_reason
 * @property string|null $time_out_latitude
 * @property string|null $time_out_longitude
 * @property string|null $time_out_accuracy_meters
 * @property string|null $time_out_distance_meters
 * @property bool|null $time_out_within_geofence
 * @property array|null $time_out_device_info
 * @property string|null $time_out_override_reason
 * @property string|null $attendance_status
 * @property bool $is_flagged
 * @property string|null $flag_reason
 * @property string|null $correction_note
 * @property int|null $corrected_by
 * @property string|null $corrected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read WorkLocation|null $workLocation
 * @property-read User|null $correctedByUser
 * @property-read Collection<int, AttendanceCorrectionRequest> $correctionRequests
 * @property-read int $tardiness_minutes Alias for late_minutes — used by Step03
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
        // Geolocation columns
        'work_location_id',
        'time_in_latitude',
        'time_in_longitude',
        'time_in_accuracy_meters',
        'time_in_distance_meters',
        'time_in_within_geofence',
        'time_in_device_info',
        'time_in_override_reason',
        'time_out_latitude',
        'time_out_longitude',
        'time_out_accuracy_meters',
        'time_out_distance_meters',
        'time_out_within_geofence',
        'time_out_device_info',
        'time_out_override_reason',
        // Status & flagging
        'attendance_status',
        'is_flagged',
        'flag_reason',
        // Correction audit trail
        'correction_note',
        'corrected_by',
        'corrected_at',
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
            'is_flagged' => 'boolean',
            'time_in_within_geofence' => 'boolean',
            'time_out_within_geofence' => 'boolean',
            'time_in_device_info' => 'array',
            'time_out_device_info' => 'array',
            'time_in_latitude' => 'decimal:7',
            'time_in_longitude' => 'decimal:7',
            'time_out_latitude' => 'decimal:7',
            'time_out_longitude' => 'decimal:7',
            'corrected_at' => 'datetime',
        ];
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Alias for late_minutes — Step03AttendanceSummaryStep reads
     * $log->tardiness_minutes. This accessor bridges the name mismatch.
     */
    public function getTardinessMinutesAttribute(): int
    {
        return $this->late_minutes ?? 0;
    }

    /**
     * Resolve the AttendanceStatus enum from the stored string.
     */
    public function getAttendanceStatusEnumAttribute(): ?AttendanceStatus
    {
        return $this->attendance_status
            ? AttendanceStatus::tryFrom($this->attendance_status)
            : null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function correctedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }
}
