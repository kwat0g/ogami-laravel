<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Recruitment\Enums\EmploymentType;
use App\Domains\HR\Recruitment\Enums\PostingStatus;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Models\SalaryGrade;
use App\Infrastructure\DocumentNumberService;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\Recruitment\JobPostingFactory;
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
 * @property string $posting_number
 * @property int|null $job_requisition_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $salary_grade_id
 * @property int|null $headcount
 * @property string $title
 * @property string $description
 * @property string $requirements
 * @property string|null $location
 * @property string $employment_type
 * @property bool $is_internal
 * @property bool $is_external
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $closes_at
 * @property string $status
 * @property int $views_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class JobPosting extends Model implements Auditable
{
    /** @use HasFactory<JobPostingFactory> */
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected $table = 'job_postings';

    protected $fillable = [
        'job_requisition_id',
        'department_id',
        'position_id',
        'salary_grade_id',
        'headcount',
        'title',
        'description',
        'requirements',
        'location',
        'employment_type',
        'is_internal',
        'is_external',
        'published_at',
        'closes_at',
        'status',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostingStatus::class,
            'employment_type' => EmploymentType::class,
            'is_internal' => 'boolean',
            'is_external' => 'boolean',
            'published_at' => 'datetime',
            'closes_at' => 'datetime',
            'views_count' => 'integer',
            'headcount' => 'integer',
        ];
    }

    protected static function newFactory(): JobPostingFactory
    {
        return JobPostingFactory::new();
    }

    // ── Auto-number ───────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->posting_number ??= static::generateNumber();
        });
    }

    private static function generateNumber(): string
    {
        return app(DocumentNumberService::class)->generate('job_posting', 'JP');
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class, 'salary_grade_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostingStatus::Published->value);
    }

    public function scopeByStatus(Builder $query, PostingStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === PostingStatus::Published
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }
}
