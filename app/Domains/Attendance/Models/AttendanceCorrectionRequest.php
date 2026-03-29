<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\Attendance\Enums\CorrectionRequestStatus;
use App\Domains\Attendance\Enums\CorrectionType;
use App\Domains\HR\Models\Employee;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property int $attendance_log_id
 * @property int $employee_id
 * @property string $correction_type
 * @property string|null $requested_time_in
 * @property string|null $requested_time_out
 * @property string|null $requested_remarks
 * @property string $reason
 * @property string|null $supporting_document_path
 * @property string $status
 * @property int|null $reviewed_by
 * @property string|null $reviewed_at
 * @property string|null $review_remarks
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read AttendanceLog $attendanceLog
 * @property-read Employee $employee
 * @property-read User|null $reviewer
 */
final class AttendanceCorrectionRequest extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'attendance_correction_requests';

    /** @var list<string> */
    protected $fillable = [
        'attendance_log_id',
        'employee_id',
        'correction_type',
        'requested_time_in',
        'requested_time_out',
        'requested_remarks',
        'reason',
        'supporting_document_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'correction_type' => CorrectionType::class,
            'status' => CorrectionRequestStatus::class,
            'requested_time_in' => 'datetime',
            'requested_time_out' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            CorrectionRequestStatus::Draft,
            CorrectionRequestStatus::Submitted,
        ], true);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
