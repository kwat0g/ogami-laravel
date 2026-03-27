<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $price_list_id
 * @property int $item_id
 * @property int $unit_price_centavos
 * @property string $min_qty
 * @property string|null $max_qty
 * @property-read PriceList $priceList
 * @property-read ItemMaster $item
 */
final class PriceListItem extends Model
{
    protected $table = 'price_list_items';

    protected $fillable = [
        'price_list_id',
        'item_id',
        'unit_price_centavos',
        'min_qty',
        'max_qty',
    ];

    protected $casts = [
        'unit_price_centavos' => 'integer',
        'min_qty' => 'decimal:4',
        'max_qty' => 'decimal:4',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }
}
