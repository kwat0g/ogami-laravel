<?php

declare(strict_types=1);

namespace App\Shared\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REC-01: Approval delegation -- allows a role holder to designate an
 * acting approver for a specific permission scope during their absence.
 *
 * @property int $id
 * @property string $ulid
 * @property int $delegator_id
 * @property int $delegate_id
 * @property string $permission_scope
 * @property string $effective_from
 * @property string $effective_until
 * @property string $reason
 * @property int $created_by_id
 */
final class ApprovalDelegate extends Model
{
    use HasPublicUlid;
    use SoftDeletes;

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'permission_scope',
        'effective_from',
        'effective_until',
        'reason',
        'created_by_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    /** @return BelongsTo<User, self> */
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    /** @return BelongsTo<User, self> */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function isActive(): bool
    {
        $today = now()->toDateString();

        return (string) $this->effective_from <= $today
            && (string) $this->effective_until >= $today;
    }
}
