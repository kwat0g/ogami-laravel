<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A leave request follows this state machine:
 *   draft → submitted → approved → (consumed by payroll)
 *                     ↘ rejected
 *   Any state → cancelled (by submitter, before payroll run)
 *
 * LV-004: reviewed_by <> submitted_by enforced at DB level.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $submitted_by FK users.id
 * @property \Illuminate\Support\Carbon $date_from
 * @property \Illuminate\Support\Carbon $date_to
 * @property float $total_days 0 < x ≤ 365
 * @property bool $is_half_day
 * @property string|null $half_day_period am|pm
 * @property string $reason
 * @property string $status draft|submitted|supervisor_approved|approved|rejected|cancelled
 * @property int|null $supervisor_id FK users.id — supervisor first approval
 * @property string|null $supervisor_remarks
 * @property \Illuminate\Support\Carbon|null $supervisor_reviewed_at
 * @property int|null $reviewed_by FK users.id — manager final approval (was reviewer)
 * @property string|null $review_remarks
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property int|null $executive_id FK users.id — executive approval for manager requests
 * @property string|null $executive_remarks
 * @property \Illuminate\Support\Carbon|null $executive_reviewed_at
 * @property string|null $requester_role staff|supervisor|manager
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 * @property-read User $submitter
 */
final class LeaveRequest extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'leave_requests';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'submitted_by',
        'requester_role',
        'date_from',
        'date_to',
        'total_days',
        'is_half_day',
        'half_day_period',
        'reason',
        'status',
        'supervisor_id',
        'supervisor_remarks',
        'supervisor_reviewed_at',
        'reviewed_by',
        'review_remarks',
        'reviewed_at',
        'executive_id',
        'executive_remarks',
        'executive_reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'total_days' => 'float',
            'is_half_day' => 'boolean',
            'reviewed_at' => 'datetime',
            'supervisor_reviewed_at' => 'datetime',
            'executive_reviewed_at' => 'datetime',
        ];
    }

    // ── State helpers ─────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return in_array($this->status, ['draft', 'submitted', 'supervisor_approved'], true);
    }

    public function isSupervisorApproved(): bool
    {
        return $this->status === 'supervisor_approved';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function executive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executive_id');
    }
}
