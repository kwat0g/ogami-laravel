<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $lot_number
 * @property int $item_id
 * @property string $received_from vendor|production
 * @property Carbon $received_date
 * @property Carbon|null $expiry_date
 * @property numeric-string $quantity_received
 * @property numeric-string $quantity_remaining
 */
final class LotBatch extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'lot_batches';

    protected $fillable = [
        'lot_number',
        'item_id',
        'received_from',
        'received_date',
        'expiry_date',
        'quantity_received',
        'quantity_remaining',
    ];

    protected $casts = [
        'received_date' => 'date',
        'expiry_date' => 'date',
    ];

    /** @return BelongsTo<ItemMaster, LotBatch> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }
}
