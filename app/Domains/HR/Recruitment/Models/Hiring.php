<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Models;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Recruitment\Enums\HiringStatus;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\Recruitment\HiringFactory;
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
 * @property int $job_requisition_id
 * @property int|null $employee_id
 * @property array<string, mixed>|null $employee_payload
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $hired_at
 * @property string $start_date
 * @property int $hired_by
 * @property int|null $submitted_by_id
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property int|null $vp_approved_by_id
 * @property \Illuminate\Support\Carbon|null $vp_approved_at
 * @property int|null $vp_rejected_by_id
 * @property \Illuminate\Support\Carbon|null $vp_rejected_at
 * @property string|null $vp_rejection_reason
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class Hiring extends Model implements Auditable
{
    /** @use HasFactory<HiringFactory> */
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected $table = 'hirings';

    protected $fillable = [
        'application_id',
        'job_requisition_id',
        'employee_id',
        'employee_payload',
        'status',
        'hired_at',
        'start_date',
        'hired_by',
        'submitted_by_id',
        'submitted_at',
        'vp_approved_by_id',
        'vp_approved_at',
        'vp_rejected_by_id',
        'vp_rejected_at',
        'vp_rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => HiringStatus::class,
            'hired_at' => 'datetime',
            'start_date' => 'date',
            'employee_payload' => 'array',
            'submitted_at' => 'datetime',
            'vp_approved_at' => 'datetime',
            'vp_rejected_at' => 'datetime',
        ];
    }

    protected static function newFactory(): HiringFactory
    {
        return HiringFactory::new();
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function hirer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hired_by');
    }
}
