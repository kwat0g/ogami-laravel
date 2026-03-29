<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Recruitment\Enums\EvaluationRecommendation;
use App\Models\User;
use Database\Factories\Recruitment\InterviewEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $interview_schedule_id
 * @property int $submitted_by
 * @property array $scorecard
 * @property float $overall_score
 * @property string $recommendation
 * @property string|null $general_remarks
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class InterviewEvaluation extends Model implements Auditable
{
    /** @use HasFactory<InterviewEvaluationFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'interview_evaluations';

    protected $fillable = [
        'interview_schedule_id',
        'submitted_by',
        'scorecard',
        'overall_score',
        'recommendation',
        'general_remarks',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'scorecard' => 'array',
            'overall_score' => 'decimal:2',
            'recommendation' => EvaluationRecommendation::class,
            'submitted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): InterviewEvaluationFactory
    {
        return InterviewEvaluationFactory::new();
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function interviewSchedule(): BelongsTo
    {
        return $this->belongsTo(InterviewSchedule::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
