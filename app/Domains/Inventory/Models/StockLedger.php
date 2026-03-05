<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only stock movement ledger. Never update or delete rows.
 *
 * @property int    $id
 * @property int    $item_id
 * @property int    $location_id
 * @property int|null $lot_batch_id
 * @property string $transaction_type
 * @property string|null $reference_type
 * @property int|null   $reference_id
 * @property numeric-string $quantity
 * @property numeric-string $balance_after
 * @property string|null $remarks
 * @property int    $created_by_id
 * @property \Carbon\Carbon $created_at
 */
final class StockLedger extends Model
{
    protected $table = 'stock_ledger';

    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'location_id',
        'lot_batch_id',
        'transaction_type',
        'reference_type',
        'reference_id',
        'quantity',
        'balance_after',
        'remarks',
        'created_by_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<ItemMaster, StockLedger> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /** @return BelongsTo<WarehouseLocation, StockLedger> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /** @return BelongsTo<LotBatch, StockLedger> */
    public function lotBatch(): BelongsTo
    {
        return $this->belongsTo(LotBatch::class, 'lot_batch_id');
    }

    /** @return BelongsTo<User, StockLedger> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
