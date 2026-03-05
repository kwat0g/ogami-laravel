<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryReceiptItem extends Model
{
    protected $table = 'delivery_receipt_items';

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'delivery_receipt_id', 'item_master_id',
        'quantity_expected', 'quantity_received',
        'unit_of_measure', 'lot_batch_number', 'remarks',
    ];

    protected $casts = [
        'quantity_expected' => 'float',
        'quantity_received' => 'float',
    ];

    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function itemMaster(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\ItemMaster::class);
    }
}
