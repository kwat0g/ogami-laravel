<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\AR\Models\Customer;
use App\Domains\Inventory\Models\ItemMaster;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int         $id
 * @property string      $ulid
 * @property string      $ds_reference
 * @property int         $customer_id
 * @property int         $product_item_id
 * @property string      $qty_ordered
 * @property string      $target_delivery_date
 * @property string      $type
 * @property string      $status
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class DeliverySchedule extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'delivery_schedules';

    protected $fillable = [
        'ulid',
        'customer_id',
        'product_item_id',
        'qty_ordered',
        'target_delivery_date',
        'type',
        'status',
        'notes',
    ];

    protected $casts = [
        'qty_ordered'          => 'decimal:4',
        'target_delivery_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function productItem(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'product_item_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'delivery_schedule_id');
    }
}
