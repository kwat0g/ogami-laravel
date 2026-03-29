<?php

declare(strict_types=1);

namespace App\Shared\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REC-01: Audit log entry for SoD override grants.
 *
 * @property int $id
 * @property string $ulid
 * @property string $override_type
 * @property string $entity_type
 * @property int $entity_id
 * @property int $original_actor_id
 * @property int $granted_by_id
 * @property string $reason
 * @property string $granted_at
 * @property string $expires_at
 * @property bool $was_used
 */
final class SodOverrideAuditLog extends Model
{
    use HasPublicUlid;
    use SoftDeletes;

    protected $table = 'sod_override_audit_log';

    protected $fillable = [
        'override_type',
        'entity_type',
        'entity_id',
        'original_actor_id',
        'granted_by_id',
        'reason',
        'granted_at',
        'expires_at',
        'was_used',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'was_used' => 'boolean',
    ];

    /** @return BelongsTo<User, self> */
    public function originalActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_actor_id');
    }

    /** @return BelongsTo<User, self> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->was_used;
    }
}
