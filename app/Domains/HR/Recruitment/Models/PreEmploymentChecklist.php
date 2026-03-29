<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Recruitment\Enums\PreEmploymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $application_id
 * @property string $status
 * @property string|null $waiver_reason
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $verified_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class PreEmploymentChecklist extends Model
{
    protected $table = 'pre_employment_checklists';

    protected $fillable = [
        'application_id',
        'status',
        'waiver_reason',
        'completed_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PreEmploymentStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(PreEmploymentRequirement::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function completionProgress(): array
    {
        $total = $this->requirements()->where('is_required', true)->count();
        $verified = $this->requirements()
            ->where('is_required', true)
            ->whereIn('status', ['verified', 'waived'])
            ->count();

        return [
            'total' => $total,
            'completed' => $verified,
            'percentage' => $total > 0 ? round(($verified / $total) * 100) : 0,
        ];
    }

    public function isComplete(): bool
    {
        $progress = $this->completionProgress();

        return $progress['total'] > 0 && $progress['completed'] >= $progress['total'];
    }
}
