<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\ItemMaster;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property int $delivery_schedule_id
 * @property int $product_item_id
 * @property string $qty_ordered
 * @property string|null $unit_price
 * @property string $status
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class DeliveryScheduleItem extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'delivery_schedule_items';

    protected $fillable = [
        'ulid',
        'delivery_schedule_id',
        'product_item_id',
        'qty_ordered',
        'unit_price',
        'status',
        'notes',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:4',
        'unit_price' => 'decimal:4',
    ];

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class, 'delivery_schedule_id');
    }

    public function productItem(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'product_item_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'delivery_schedule_item_id');
    }
}
