<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Enums\EmploymentType;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Models\User;
use App\Shared\Concerns\HasApprovalWorkflow;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\Recruitment\JobRequisitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $requisition_number
 * @property int $department_id
 * @property int $position_id
 * @property int $requested_by
 * @property int|null $approved_by
 * @property string $employment_type
 * @property int $headcount
 * @property string $reason
 * @property string|null $justification
 * @property int|null $salary_range_min
 * @property int|null $salary_range_max
 * @property string|null $target_start_date
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class JobRequisition extends Model implements Auditable
{
    /** @use HasFactory<JobRequisitionFactory> */
    use AuditableTrait, HasApprovalWorkflow, HasFactory, HasPublicUlid, SoftDeletes;

    protected $table = 'job_requisitions';

    protected $fillable = [
        'department_id',
        'position_id',
        'requested_by',
        'approved_by',
        'employment_type',
        'headcount',
        'reason',
        'justification',
        'salary_range_min',
        'salary_range_max',
        'target_start_date',
        'status',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'employment_type' => EmploymentType::class,
            'headcount' => 'integer',
            'salary_range_min' => 'integer',
            'salary_range_max' => 'integer',
            'target_start_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function newFactory(): JobRequisitionFactory
    {
        return JobRequisitionFactory::new();
    }

    // ── Auto-number ───────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->requisition_number ??= static::generateNumber();
        });
    }

    private static function generateNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "REQ-{$year}-";
        $lastNumber = static::withTrashed()
            ->where('requisition_number', 'LIKE', "{$prefix}%")
            ->lockForUpdate()
            ->selectRaw("MAX(CAST(SUBSTRING(requisition_number FROM '.{5}$') AS INTEGER)) as max_num")
            ->value('max_num') ?? 0;

        return sprintf('REQ-%s-%05d', $year, $lastNumber + 1);
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function hirings(): HasMany
    {
        return $this->hasMany(Hiring::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequisitionApproval::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeByStatus(Builder $query, RequisitionStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeByDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RequisitionStatus::Approved->value,
            RequisitionStatus::Open->value,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function hiredCount(): int
    {
        return $this->hirings()->where('status', 'hired')->count();
    }

    public function isHeadcountFulfilled(): bool
    {
        return $this->hiredCount() >= $this->headcount;
    }
}
