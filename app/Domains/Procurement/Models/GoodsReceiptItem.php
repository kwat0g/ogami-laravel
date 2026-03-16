<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * GoodsReceiptItem — line item on a Goods Receipt linked to a PO item.
 *
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $po_item_id
 * @property int|null $item_master_id Set by Warehouse Head to link to inventory
 * @property numeric-string $quantity_received
 * @property string $unit_of_measure
 * @property string $condition good|damaged|partial|rejected
 * @property string|null $remarks
 */
final class GoodsReceiptItem extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'goods_receipt_items';

    protected $fillable = [
        'goods_receipt_id',
        'po_item_id',
        'item_master_id',
        'quantity_received',
        'unit_of_measure',
        'condition',
        'remarks',
    ];

    /** @return BelongsTo<GoodsReceipt, GoodsReceiptItem> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<PurchaseOrderItem, GoodsReceiptItem> */
    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }
}
