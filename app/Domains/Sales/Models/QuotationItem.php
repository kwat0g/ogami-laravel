<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quotation_id
 * @property int $item_id
 * @property string $quantity
 * @property int $unit_price_centavos
 * @property int $line_total_centavos
 * @property string|null $remarks
 * @property-read Quotation $quotation
 * @property-read ItemMaster $item
 */
final class QuotationItem extends Model
{
    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id',
        'item_id',
        'quantity',
        'unit_price_centavos',
        'line_total_centavos',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price_centavos' => 'integer',
        'line_total_centavos' => 'integer',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }
}
