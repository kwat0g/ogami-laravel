<?php

declare(strict_types=1);

namespace App\Domains\ISO\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $controlled_document_id
 * @property int $distributed_to_id
 * @property string $status pending|distributed|acknowledged|recalled
 * @property Carbon|null $distributed_at
 * @property Carbon|null $acknowledged_at
 * @property string|null $notes
 * @property int $distributed_by_id
 * @property-read User $distributedTo
 * @property-read User $distributedBy
 */
final class DocumentDistribution extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'document_distributions';

    protected $fillable = [
        'controlled_document_id', 'distributed_to_id',
        'status', 'distributed_at', 'acknowledged_at',
        'notes', 'distributed_by_id',
    ];

    protected $casts = [
        'distributed_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function distributedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_to_id');
    }

    public function distributedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by_id');
    }
}
