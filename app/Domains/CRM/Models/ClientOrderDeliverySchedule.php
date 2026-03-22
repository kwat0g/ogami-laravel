<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\Production\Models\DeliverySchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model for linking Client Orders to Delivery Schedules
 * Supports multiple items per order (one schedule per item)
 */
class ClientOrderDeliverySchedule extends Model
{
    use HasFactory;

    protected $table = 'client_order_delivery_schedules';

    protected $fillable = [
        'client_order_id',
        'client_order_item_id',
        'delivery_schedule_id',
    ];

    public function clientOrder(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class);
    }

    public function clientOrderItem(): BelongsTo
    {
        return $this->belongsTo(ClientOrderItem::class);
    }

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class);
    }
}
