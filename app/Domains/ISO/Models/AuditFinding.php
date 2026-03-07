<?php

declare(strict_types=1);

namespace App\Domains\ISO\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class AuditFinding extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'audit_findings';

    protected $fillable = [
        'audit_id', 'finding_type', 'clause_ref',
        'description', 'severity', 'status', 'raised_by_id', 'closed_at',
    ];

    protected $casts = ['closed_at' => 'datetime'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InternalAudit::class, 'audit_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'raised_by_id');
    }

    public function improvementActions(): HasMany
    {
        return $this->hasMany(ImprovementAction::class, 'finding_id');
    }
}
