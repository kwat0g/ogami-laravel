<?php

declare(strict_types=1);

namespace App\Domains\ISO\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $ulid
 * @property int $audit_id
 * @property string $finding_type nonconformity|observation|opportunity
 * @property string|null $clause_ref
 * @property string $description
 * @property string $severity minor|major
 * @property string $status open|in_progress|closed|verified
 * @property int|null $raised_by_id
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
        return $this->belongsTo(User::class, 'raised_by_id');
    }

    public function improvementActions(): HasMany
    {
        return $this->hasMany(ImprovementAction::class, 'finding_id');
    }
}
