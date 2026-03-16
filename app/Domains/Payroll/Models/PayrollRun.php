<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Payroll Run — one record per semi-monthly pay period.
 *
 * @property int $id
 * @property string $reference_no PR-YYYY-NNNNNN
 * @property string $pay_period_label e.g. "Feb 2026 1st"
 * @property int|null $pay_period_id
 * @property string $cutoff_start
 * @property string $cutoff_end
 * @property string $pay_date
 * @property string $status
 * @property string $run_type
 * @property int $total_employees
 * @property int $gross_pay_total_centavos
 * @property int $total_deductions_centavos
 * @property int $net_pay_total_centavos
 * @property int $created_by
 * @property int|null $initiated_by_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $locked_at
 * @property int|null $hr_approved_by_id
 * @property Carbon|null $hr_approved_at
 * @property int|null $acctg_approved_by_id
 * @property Carbon|null $acctg_approved_at
 * @property Carbon|null $scope_confirmed_at
 * @property Carbon|null $pre_run_checked_at
 * @property Carbon|null $pre_run_acknowledged_at
 * @property int|null $pre_run_acknowledged_by_id
 * @property Carbon|null $computation_started_at
 * @property Carbon|null $computation_completed_at
 * @property array|null $progress_json
 * @property Carbon|null $published_at
 * @property Carbon|null $publish_scheduled_at
 * @property array|null $scope_departments
 * @property array|null $scope_positions
 * @property array|null $scope_employment_types
 * @property bool $scope_include_unpaid_leave
 * @property bool $scope_include_probation_end
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User                     $creator
 * @property-read User|null                $approver
 * @property-read User|null                $hrApprovedBy
 * @property-read User|null                $acctgApprovedBy
 * @property-read Collection<int, PayrollDetail>       $details
 * @property-read Collection<int, PayrollAdjustment>   $adjustments
 * @property-read Collection<int, PayrollRunApproval>  $approvals
 * @property-read Collection<int, PayrollRunExclusion> $exclusions
 */
final class PayrollRun extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected static function newFactory(): PayrollRunFactory
    {
        return PayrollRunFactory::new();
    }

    protected $table = 'payroll_runs';

    protected $fillable = [
        'reference_no',
        'pay_period_label',
        'pay_period_id',
        'cutoff_start',
        'cutoff_end',
        'pay_date',
        'status',
        'run_type',
        'total_employees',
        'gross_pay_total_centavos',
        'total_deductions_centavos',
        'net_pay_total_centavos',
        'created_by',
        'initiated_by_id',
        'approved_by',
        'approved_at',
        'locked_at',
        'submitted_by',
        'submitted_at',
        'posted_at',
        'failure_reason',
        'hr_approved_by_id',
        'hr_approved_at',
        'acctg_approved_by_id',
        'acctg_approved_at',
        'scope_confirmed_at',
        'pre_run_checked_at',
        'pre_run_acknowledged_at',
        'pre_run_acknowledged_by_id',
        'computation_started_at',
        'computation_completed_at',
        'progress_json',
        'published_at',
        'publish_scheduled_at',
        'scope_departments',
        'scope_positions',
        'scope_employment_types',
        'scope_include_unpaid_leave',
        'scope_include_probation_end',
        'scope_exclude_no_attendance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'submitted_at' => 'datetime',
            'posted_at' => 'datetime',
            'hr_approved_at' => 'datetime',
            'acctg_approved_at' => 'datetime',
            'scope_confirmed_at' => 'datetime',
            'pre_run_checked_at' => 'datetime',
            'pre_run_acknowledged_at' => 'datetime',
            'computation_started_at' => 'datetime',
            'computation_completed_at' => 'datetime',
            'published_at' => 'datetime',
            'publish_scheduled_at' => 'datetime',
            'progress_json' => 'array',
            'scope_departments' => 'array',
            'scope_positions' => 'array',
            'scope_employment_types' => 'array',
            'scope_include_unpaid_leave' => 'boolean',
            'scope_include_probation_end' => 'boolean',
            'scope_exclude_no_attendance' => 'boolean',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    /** The user who initiated (created) this run — SoD subject (PR-003). */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_id');
    }

    public function hrApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by_id');
    }

    public function acctgApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acctg_approved_by_id');
    }

    public function preRunAcknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pre_run_acknowledged_by_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class, 'payroll_run_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class, 'payroll_run_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PayrollRunApproval::class, 'payroll_run_id');
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(PayrollRunExclusion::class, 'payroll_run_id');
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return in_array($this->status, ['DRAFT', 'draft'], true);
    }

    public function isScopeSet(): bool
    {
        return $this->status === 'SCOPE_SET';
    }

    public function isPreRunChecked(): bool
    {
        return $this->status === 'PRE_RUN_CHECKED';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['PROCESSING', 'processing'], true);
    }

    public function isComputed(): bool
    {
        return $this->status === 'COMPUTED';
    }

    public function isInReview(): bool
    {
        return $this->status === 'REVIEW';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['SUBMITTED', 'submitted'], true);
    }

    public function isHrApproved(): bool
    {
        return $this->status === 'HR_APPROVED';
    }

    public function isAcctgApproved(): bool
    {
        return $this->status === 'ACCTG_APPROVED';
    }

    public function isDisbursed(): bool
    {
        return $this->status === 'DISBURSED';
    }

    public function isPublished(): bool
    {
        return $this->status === 'PUBLISHED';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'COMPUTED', 'REVIEW'], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['FAILED', 'failed'], true);
    }

    public function isReturned(): bool
    {
        return $this->status === 'RETURNED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isThirteenthMonth(): bool
    {
        return $this->run_type === 'thirteenth_month';
    }

    public function isRegular(): bool
    {
        return $this->run_type === 'regular';
    }

    public function isAdjustment(): bool
    {
        return $this->run_type === 'adjustment';
    }

    public function isFinalPay(): bool
    {
        return $this->run_type === 'final_pay';
    }

    /** Count of active employees covered by this run's cutoff range. */
    public function employeesInScope(): Builder
    {
        return Employee::where('is_active', true)
            ->where('employment_status', 'active')
            ->where('date_hired', '<=', $this->cutoff_end);
    }
}
