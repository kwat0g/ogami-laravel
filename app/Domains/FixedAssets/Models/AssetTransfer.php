<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Models;

use App\Domains\HR\Models\Department;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property int $fixed_asset_id
 * @property int $from_department_id
 * @property int $to_department_id
 * @property string $transfer_date
 * @property string $status pending|approved|completed|rejected
 * @property string|null $reason
 * @property int $requested_by_id
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 */
final class AssetTransfer extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'asset_transfers';

    protected $fillable = [
        'fixed_asset_id', 'from_department_id', 'to_department_id',
        'transfer_date', 'status', 'reason',
        'requested_by_id', 'approved_by_id', 'approved_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
