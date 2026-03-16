<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use App\Domains\ISO\Models\AuditFinding;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $ncr_id
 * @property int|null $audit_finding_id
 * @property string $type
 * @property string $description
 * @property Carbon|null $due_date
 * @property int|null $assigned_to_id
 * @property string $status
 * @property Carbon|null $completed_at
 * @property int|null $verified_by_id
 * @property Carbon|null $verified_at
 * @property string|null $evidence_note
 * @property int|null $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class CapaAction extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'capa_actions';

    protected $fillable = [
        'ncr_id',
        'audit_finding_id',
        'type',
        'description',
        'due_date',
        'assigned_to_id',
        'status',
        'completed_at',
        'verified_by_id',
        'verified_at',
        'evidence_note',
        'created_by_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /** @return BelongsTo<NonConformanceReport, $this> */
    public function ncr(): BelongsTo
    {
        return $this->belongsTo(NonConformanceReport::class, 'ncr_id');
    }

    /** @return BelongsTo<AuditFinding, $this> */
    public function auditFinding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
