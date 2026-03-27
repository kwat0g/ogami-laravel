<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $reference_number
 * @property int $location_id
 * @property string $status draft|in_progress|pending_approval|approved|cancelled
 * @property string $count_date
 * @property string|null $notes
 * @property int $created_by_id
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read WarehouseLocation $location
 * @property-read User $createdBy
 * @property-read User|null $approvedBy
 * @property-read Collection<int, PhysicalCountItem> $items
 */
final class PhysicalCount extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'physical_counts';

    protected $fillable = [
        'reference_number',
        'location_id',
        'status',
        'count_date',
        'notes',
        'created_by_id',
        'approved_by_id',
        'approved_at',
    ];

    protected $casts = [
        'count_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PhysicalCountItem::class, 'physical_count_id');
    }
}
