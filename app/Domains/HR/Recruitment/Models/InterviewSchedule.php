<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Recruitment\Enums\InterviewStatus;
use App\Domains\HR\Recruitment\Enums\InterviewType;
use App\Models\User;
use Database\Factories\Recruitment\InterviewScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $application_id
 * @property int $round
 * @property string $type
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property int $duration_minutes
 * @property string|null $location
 * @property int $interviewer_id
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class InterviewSchedule extends Model implements Auditable
{
    /** @use HasFactory<InterviewScheduleFactory> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'interview_schedules';

    protected $fillable = [
        'application_id',
        'round',
        'type',
        'scheduled_at',
        'duration_minutes',
        'location',
        'interviewer_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'type' => InterviewType::class,
            'status' => InterviewStatus::class,
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    protected static function newFactory(): InterviewScheduleFactory
    {
        return InterviewScheduleFactory::new();
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(InterviewEvaluation::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now())
            ->where('status', InterviewStatus::Scheduled->value)
            ->orderBy('scheduled_at');
    }

    public function scopeByStatus(Builder $query, InterviewStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
