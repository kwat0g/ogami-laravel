<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Leave request — requester-specific simplified approval chain.
 *
 * State machine:
 *   staff:        submitted → head_approved    → approved
 *   head_officer: submitted → manager_approved → approved
 *   dept_manager: submitted → hr_approved      → approved
 *   hr_manager:   submitted                     → approved
 *   Any pending state → cancelled (by submitter)
 *
 * Step 2 — Department Head     (Approved By)  → head_approved
 * Step 3 — Department Manager  (Approved By)  → manager_approved
 * Step 4 — HR Manager          (Approved By)  → hr_approved
 * Final — HR Manager / VP final approval      → approved
 *
 * LV-004: each approver <> submitted_by enforced at service layer.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $submitted_by FK users.id
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property float $total_days
 * @property bool $is_half_day
 * @property string|null $half_day_period AM|PM
 * @property string $reason
 * @property string $requester_type staff|head_officer|dept_manager|hr_manager
 * @property string $status draft|submitted|head_approved|manager_approved|hr_approved|approved|rejected|cancelled
 * @property int|null $head_id FK users.id — dept head who approved (step 2)
 * @property string|null $head_remarks
 * @property Carbon|null $head_approved_at
 * @property int|null $manager_approved_by FK users.id — dept manager who approved
 * @property string|null $manager_approved_remarks
 * @property Carbon|null $manager_approved_at
 * @property int|null $hr_approved_by FK users.id — HR manager who approved
 * @property string|null $hr_remarks
 * @property Carbon|null $hr_approved_at
 * @property int|null $vp_id FK users.id — VP who approved final step
 * @property string|null $vp_remarks
 * @property Carbon|null $vp_noted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee  $employee
 * @property-read LeaveType $leaveType
 * @property-read User      $submitter
 * @property-read User|null $head
 * @property-read User|null $managerApprover
 * @property-read User|null $hrApprover
 * @property-read User|null $vp
 */
final class LeaveRequest extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'leave_requests';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'submitted_by',
        'requester_type',
        'date_from',
        'date_to',
        'total_days',
        'is_half_day',
        'half_day_period',
        'reason',
        'status',
        // Step 2 — Head
        'head_id',
        'head_remarks',
        'head_approved_at',
        // Step 3 — Manager
        'manager_approved_by',
        'manager_approved_remarks',
        'manager_approved_at',
        // Step 4 — HR
        'hr_approved_by',
        'hr_remarks',
        'hr_approved_at',
        // Final — VP
        'vp_id',
        'vp_remarks',
        'vp_noted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'total_days' => 'float',
            'is_half_day' => 'boolean',
            'head_approved_at' => 'datetime',
            'manager_approved_at' => 'datetime',
            'hr_approved_at' => 'datetime',
            'vp_noted_at' => 'datetime',
        ];
    }

    // ── State helpers ─────────────────────────────────────────────────────────

    /** Returns true while the request still needs any action. */
    public function isPending(): bool
    {
        return in_array($this->status, [
            'draft',
            'submitted',
            'head_approved',
            'manager_approved',
            'hr_approved',
        ], true);
    }

    public function isHeadApproved(): bool
    {
        return $this->status === 'head_approved';
    }

    public function isManagerApproved(): bool
    {
        return $this->status === 'manager_approved';
    }

    public function isHrApproved(): bool
    {
        return $this->status === 'hr_approved';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /** Only cancellable before the head approves. */
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

    /** Step 2 — Department Head. */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    /** Step 3 — Plant Manager. */
    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    /** Step 4 — HR Manager. */
    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    /** Step 5 — Vice President. */
    public function vp(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vp_id');
    }
}
