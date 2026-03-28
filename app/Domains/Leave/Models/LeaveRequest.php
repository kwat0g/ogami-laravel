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
 * Leave request — 4-step approval chain matching physical form AD-084-00.
 *
 * State machine:
 *   draft → submitted → head_approved → manager_checked → ga_processed → approved
 *                                                      ↘ rejected  (GA disapproves)
 *   Any pending state → cancelled (by submitter)
 *
 * Step 2 — Department Head     (Approved By)  → head_approved
 * Step 3 — Plant Manager       (Checked By)   → manager_checked
 * Step 4 — GA Officer          (Received By)  → ga_processed | rejected
 * Step 5 — Vice President      (Noted By)     → approved (balance deducted here)
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
 * @property string $status draft|submitted|head_approved|manager_checked|ga_processed|approved|rejected|cancelled
 * @property int|null $head_id FK users.id — dept head who approved (step 2)
 * @property string|null $head_remarks
 * @property Carbon|null $head_approved_at
 * @property int|null $manager_checked_by FK users.id — plant manager who checked (step 3)
 * @property string|null $manager_check_remarks
 * @property Carbon|null $manager_checked_at
 * @property int|null $ga_processed_by FK users.id — GA officer who received (step 4)
 * @property string|null $ga_remarks
 * @property Carbon|null $ga_processed_at
 * @property string|null $action_taken approved_with_pay|approved_without_pay|disapproved
 * @property float|null $beginning_balance balance snapshot at ga_process time
 * @property float|null $applied_days days to deduct (= total_days for full, 0 for without_pay)
 * @property float|null $ending_balance beginning_balance − applied_days
 * @property int|null $vp_id FK users.id — VP who noted (step 5)
 * @property string|null $vp_remarks
 * @property Carbon|null $vp_noted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee  $employee
 * @property-read LeaveType $leaveType
 * @property-read User      $submitter
 * @property-read User|null $head
 * @property-read User|null $managerChecker
 * @property-read User|null $gaProcessor
 * @property-read User|null $vp
 */
final class LeaveRequest extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes, SoftDeletes;

    protected $table = 'leave_requests';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'submitted_by',
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
        'manager_checked_by',
        'manager_check_remarks',
        'manager_checked_at',
        // Step 4 — GA Officer
        'ga_processed_by',
        'ga_remarks',
        'ga_processed_at',
        'action_taken',
        'beginning_balance',
        'applied_days',
        'ending_balance',
        // Step 5 — VP
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
            'manager_checked_at' => 'datetime',
            'ga_processed_at' => 'datetime',
            'vp_noted_at' => 'datetime',
            'beginning_balance' => 'float',
            'applied_days' => 'float',
            'ending_balance' => 'float',
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
            'manager_checked',
            'ga_processed',
        ], true);
    }

    public function isHeadApproved(): bool
    {
        return $this->status === 'head_approved';
    }

    public function isManagerChecked(): bool
    {
        return $this->status === 'manager_checked';
    }

    public function isGaProcessed(): bool
    {
        return $this->status === 'ga_processed';
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
    public function managerChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_checked_by');
    }

    /** Step 4 — GA Officer. */
    public function gaProcessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ga_processed_by');
    }

    /** Step 5 — Vice President. */
    public function vp(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vp_id');
    }
}
