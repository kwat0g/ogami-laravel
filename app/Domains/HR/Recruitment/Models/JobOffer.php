<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Enums\EmploymentType;
use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\Recruitment\JobOfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property int $application_id
 * @property string $offer_number
 * @property int $offered_position_id
 * @property int $offered_department_id
 * @property int $offered_salary
 * @property string $employment_type
 * @property string $start_date
 * @property string|null $offer_letter_path
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property string|null $rejection_reason
 * @property int $prepared_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class JobOffer extends Model implements Auditable
{
    /** @use HasFactory<JobOfferFactory> */
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected $table = 'job_offers';

    protected $fillable = [
        'application_id',
        'offered_position_id',
        'offered_department_id',
        'offered_salary',
        'employment_type',
        'start_date',
        'offer_letter_path',
        'status',
        'sent_at',
        'expires_at',
        'responded_at',
        'rejection_reason',
        'prepared_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'employment_type' => EmploymentType::class,
            'offered_salary' => 'integer',
            'start_date' => 'date',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): JobOfferFactory
    {
        return JobOfferFactory::new();
    }

    // ── Auto-number ───────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->offer_number ??= static::generateNumber();
        });
    }

    private static function generateNumber(): string
    {
        $year = now()->format('Y');
        $last = static::whereYear('created_at', $year)->count();

        return sprintf('OFR-%s-%05d', $year, $last + 1);
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function offeredPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'offered_position_id');
    }

    public function offeredDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'offered_department_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeByStatus(Builder $query, OfferStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeExpirable(Builder $query): Builder
    {
        return $query->where('status', OfferStatus::Sent->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }
}
