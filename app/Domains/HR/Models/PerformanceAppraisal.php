<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Performance Appraisal — periodic employee performance evaluation.
 *
 * Supports multiple review types: annual, mid_year, probationary, project_based.
 * Each appraisal has KPI criteria with ratings and weights.
 *
 * Workflow: draft -> submitted -> manager_reviewed -> hr_approved -> completed
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property int $reviewer_id
 * @property string $review_type annual|mid_year|probationary|project_based
 * @property string $review_period_start
 * @property string $review_period_end
 * @property string $status draft|submitted|manager_reviewed|hr_approved|completed
 * @property int|null $overall_rating_pct 0-100
 * @property string|null $employee_comments
 * @property string|null $reviewer_comments
 * @property string|null $hr_comments
 * @property int|null $hr_approved_by_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $hr_approved_at
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read User $reviewer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PerformanceAppraisalCriteria> $criteria
 */
final class PerformanceAppraisal extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'performance_appraisals';

    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'review_type',
        'review_period_start',
        'review_period_end',
        'status',
        'overall_rating_pct',
        'employee_comments',
        'reviewer_comments',
        'hr_comments',
        'hr_approved_by_id',
        'submitted_at',
        'reviewed_at',
        'hr_approved_at',
        'created_by_id',
    ];

    protected $casts = [
        'review_period_start' => 'date',
        'review_period_end' => 'date',
        'overall_rating_pct' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'hr_approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function hrApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(PerformanceAppraisalCriteria::class, 'appraisal_id');
    }

    /**
     * Compute overall rating from weighted criteria.
     */
    public function computeOverallRating(): int
    {
        $criteria = $this->criteria;
        if ($criteria->isEmpty()) {
            return 0;
        }

        $totalWeight = $criteria->sum('weight_pct');
        if ($totalWeight <= 0) {
            return 0;
        }

        $weightedSum = $criteria->sum(fn ($c) => ((float) $c->rating_pct) * ((float) $c->weight_pct));

        return (int) round($weightedSum / $totalWeight);
    }

    /**
     * Get the performance level based on overall rating.
     */
    public function performanceLevel(): string
    {
        $rating = $this->overall_rating_pct ?? 0;

        return match (true) {
            $rating >= 90 => 'outstanding',
            $rating >= 80 => 'exceeds_expectations',
            $rating >= 60 => 'meets_expectations',
            $rating >= 40 => 'needs_improvement',
            default => 'unsatisfactory',
        };
    }
}
