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
 * @property string|null $qc_status pending|passed|failed|accepted_with_ncr
 * @property numeric-string|null $quantity_accepted
 * @property numeric-string|null $quantity_rejected
 * @property int|null $ncr_id
 * @property string|null $defect_type cosmetic|dimensional|functional|material|other
 * @property string|null $defect_description
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
        'qc_status',
        'quantity_accepted',
        'quantity_rejected',
        'ncr_id',
        'defect_type',
        'defect_description',
        'reject_disposition',
        'disposition_completed_at',
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

    /** @return BelongsTo<\App\Domains\Inventory\Models\ItemMaster, GoodsReceiptItem> */
    public function itemMaster(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\ItemMaster::class, 'item_master_id');
    }

    /** @return BelongsTo<\App\Domains\QC\Models\NonConformanceReport, GoodsReceiptItem> */
    public function ncr(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\QC\Models\NonConformanceReport::class, 'ncr_id');
    }

    /**
     * Effective quantity for three-way match: uses quantity_accepted if QC
     * has split the receipt, otherwise falls back to quantity_received.
     */
    public function effectiveAcceptedQuantity(): float
    {
        return (float) ($this->quantity_accepted ?? $this->quantity_received);
    }

    public function isAccepted(): bool
    {
        return in_array($this->qc_status, ['passed', 'accepted_with_ncr'], true);
    }

    public function isRejected(): bool
    {
        return $this->qc_status === 'failed' && $this->quantity_accepted === null;
    }

    public function hasDefects(): bool
    {
        return $this->defect_type !== null || $this->ncr_id !== null;
    }
}
