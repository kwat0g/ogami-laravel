<?php

declare(strict_types=1);

namespace App\Domains\ISO\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class InternalAudit extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'internal_audits';

    protected $fillable = [
        'audit_scope', 'standard', 'lead_auditor_id',
        'audit_date', 'status', 'summary', 'closed_at', 'created_by_id',
    ];

    protected $casts = [
        'audit_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class, 'audit_id');
    }
}
