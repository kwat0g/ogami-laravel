<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Enums\CandidateSource;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\Recruitment\ApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $application_number
 * @property int $job_posting_id
 * @property int $candidate_id
 * @property string|null $cover_letter
 * @property string $application_date
 * @property string $source
 * @property string $status
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string|null $withdrawn_reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class Application extends Model implements Auditable
{
    /** @use HasFactory<ApplicationFactory> */
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected $table = 'applications';

    protected $fillable = [
        'job_posting_id',
        'candidate_id',
        'cover_letter',
        'application_date',
        'source',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'withdrawn_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => CandidateSource::class,
            'application_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ApplicationFactory
    {
        return ApplicationFactory::new();
    }

    // ── Auto-number ───────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->application_number ??= static::generateNumber();
        });
    }

    private static function generateNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "APP-{$year}-";
        $lastNumber = static::withTrashed()
            ->where('application_number', 'LIKE', "{$prefix}%")
            ->lockForUpdate()
            ->selectRaw("MAX(CAST(SUBSTRING(application_number FROM '.{5}$') AS INTEGER)) as max_num")
            ->value('max_num') ?? 0;

        return sprintf('APP-%s-%05d', $year, $lastNumber + 1);
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(InterviewSchedule::class)->orderBy('round');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(JobOffer::class);
    }

    public function preEmploymentChecklist(): HasOne
    {
        return $this->hasOne(PreEmploymentChecklist::class);
    }

    public function hiring(): HasOne
    {
        return $this->hasOne(Hiring::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeByStatus(Builder $query, ApplicationStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ApplicationStatus::Rejected->value,
            ApplicationStatus::Withdrawn->value,
        ]);
    }
}
