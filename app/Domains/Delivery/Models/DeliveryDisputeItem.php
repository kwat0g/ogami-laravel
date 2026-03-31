<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeliveryDisputeItem -- a line item in a delivery dispute.
 *
 * @property int $id
 * @property int $delivery_dispute_id
 * @property int $item_master_id
 * @property string $expected_qty
 * @property string $received_qty
 * @property string $condition good|damaged|missing|wrong_item
 * @property string|null $notes
 * @property string|null $resolution_action replace|credit|accept
 * @property string|null $resolution_qty
 */
final class DeliveryDisputeItem extends Model
{
    protected $table = 'delivery_dispute_items';

    protected $fillable = [
        'delivery_dispute_id',
        'item_master_id',
        'expected_qty',
        'received_qty',
        'condition',
        'notes',
        'resolution_action',
        'resolution_qty',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(DeliveryDispute::class, 'delivery_dispute_id');
    }

    public function itemMaster(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class);
    }
}
