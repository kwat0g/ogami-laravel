<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PurchaseOrderItem — line item on a Purchase Order linked to a PR item.
 *
 * @property int         $id
 * @property int         $purchase_order_id
 * @property int|null    $pr_item_id         FK to purchase_request_items — three-way match
 * @property string      $item_description
 * @property string      $unit_of_measure
 * @property numeric-string $quantity_ordered
 * @property numeric-string $agreed_unit_cost
 * @property numeric-string $total_cost        GENERATED ALWAYS AS (quantity_ordered * agreed_unit_cost)
 * @property numeric-string $quantity_received updated as GRs come in
 * @property numeric-string $quantity_pending  GENERATED ALWAYS AS (quantity_ordered - quantity_received)
 * @property int         $line_order
 */
final class PurchaseOrderItem extends Model
{
    use SoftDeletes;

    public $timestamps = true;
    protected $table   = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'pr_item_id',
        'item_description',
        'unit_of_measure',
        'quantity_ordered',
        'agreed_unit_cost',
        'quantity_received',
        'line_order',
    ];

    /** @var list<string> DB-computed columns */
    protected $guarded = ['total_cost', 'quantity_pending'];

    /** @return BelongsTo<PurchaseOrder, PurchaseOrderItem> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<PurchaseRequestItem, PurchaseOrderItem> */
    public function prItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'pr_item_id');
    }
}
