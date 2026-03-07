<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class CapaAction extends Model implements AuditableContract
{
    use HasPublicUlid, Auditable, SoftDeletes;

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
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'verified_at'  => 'datetime',
    ];

    /** @return BelongsTo<NonConformanceReport, $this> */
    public function ncr(): BelongsTo
    {
        return $this->belongsTo(NonConformanceReport::class, 'ncr_id');
    }

    /** @return BelongsTo<\App\Domains\ISO\Models\AuditFinding, $this> */
    public function auditFinding(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\ISO\Models\AuditFinding::class, 'audit_finding_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
