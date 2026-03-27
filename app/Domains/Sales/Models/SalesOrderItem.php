<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sales_order_id
 * @property int $item_id
 * @property string $quantity
 * @property int $unit_price_centavos
 * @property int $line_total_centavos
 * @property string $quantity_delivered
 * @property string|null $remarks
 * @property-read SalesOrder $salesOrder
 * @property-read ItemMaster $item
 */
final class SalesOrderItem extends Model
{
    protected $table = 'sales_order_items';

    protected $fillable = [
        'sales_order_id',
        'item_id',
        'quantity',
        'unit_price_centavos',
        'line_total_centavos',
        'quantity_delivered',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price_centavos' => 'integer',
        'line_total_centavos' => 'integer',
        'quantity_delivered' => 'decimal:4',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    public function isFullyDelivered(): bool
    {
        return (float) $this->quantity_delivered >= (float) $this->quantity;
    }
}
