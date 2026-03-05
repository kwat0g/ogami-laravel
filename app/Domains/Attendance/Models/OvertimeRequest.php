<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Pre-approval request for overtime work.
 *
 * ATT-005: OT must be approved BEFORE work date cutoff.
 * SoD ATT-003: approver must differ from employee.user_id.
 *
 * Approval workflow:
 *   Staff      : pending → supervisor_approved → approved
 *   Supervisor : pending → approved  (manager approves directly, no endorsement step)
 *   Manager    : pending_executive → approved  (executive approves)
 *
 * @property int $id
 * @property int $employee_id
 * @property string|null $requester_role staff|supervisor|manager
 * @property \Illuminate\Support\Carbon $work_date
 * @property int $requested_minutes 1–480
 * @property int|null $approved_minutes
 * @property string $reason
 * @property string $status pending|supervisor_approved|pending_executive|approved|rejected|cancelled
 * @property int|null $requested_by FK users.id — who filed the request
 * @property int|null $supervisor_id FK users.id — supervisor who endorsed (staff requests)
 * @property string|null $supervisor_remarks
 * @property \Illuminate\Support\Carbon|null $supervisor_approved_at
 * @property int|null $approved_by FK users.id — manager final approval
 * @property string|null $approver_remarks
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property int|null $executive_id FK users.id — executive approval for manager requests
 * @property string|null $executive_remarks
 * @property \Illuminate\Support\Carbon|null $executive_approved_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Employee $employee
 */
final class OvertimeRequest extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'overtime_requests';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'requester_role',
        'work_date',
        'ot_start_time',
        'ot_end_time',
        'requested_minutes',
        'approved_minutes',
        'reason',
        'status',
        'requested_by',
        'supervisor_id',
        'supervisor_remarks',
        'supervisor_approved_at',
        'approved_by',
        'approver_remarks',
        'reviewed_at',
        'executive_id',
        'executive_remarks',
        'executive_approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'reviewed_at' => 'datetime',
            'supervisor_approved_at' => 'datetime',
            'executive_approved_at' => 'datetime',
            'requested_minutes' => 'integer',
            'approved_minutes' => 'integer',
        ];
    }

    // ── State helpers ─────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'supervisor_approved'], true);
    }

    public function isSupervisorApproved(): bool
    {
        return $this->status === 'supervisor_approved';
    }

    public function isPendingExecutive(): bool
    {
        return $this->status === 'pending_executive';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'pending_executive'], true);
    }

    public function requestedHours(): float
    {
        return round($this->requested_minutes / 60, 2);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
