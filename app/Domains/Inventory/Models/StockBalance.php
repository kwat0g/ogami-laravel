<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $item_id
 * @property int    $location_id
 * @property numeric-string $quantity_on_hand
 * @property \Carbon\Carbon $updated_at
 */
final class StockBalance extends Model
{
    protected $table = 'stock_balances';

    public $timestamps = false;

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = ['item_id', 'location_id', 'quantity_on_hand'];

    protected $casts = ['updated_at' => 'datetime'];

    /** @return BelongsTo<ItemMaster, StockBalance> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /** @return BelongsTo<WarehouseLocation, StockBalance> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }
}
