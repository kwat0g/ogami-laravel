<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Domains\AP\Models\Vendor;
use App\Domains\AR\Models\Customer;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Database\Factories\DeliveryReceiptFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int|null $vendor_id
 * @property int|null $customer_id
 * @property int|null $sales_order_id
 * @property string $direction
 * @property string $status
 * @property string|null $receipt_date
 * @property string|null $remarks
 * @property int|null $received_by_id
 * @property int|null $created_by_id
 * @property int|null $vehicle_id
 * @property string|null $driver_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, DeliveryReceiptItem> $items
 */
final class DeliveryReceipt extends Model implements AuditableContract
{
    use Auditable, HasFactory, HasPublicUlid, SoftDeletes;

    protected static function newFactory(): DeliveryReceiptFactory
    {
        return DeliveryReceiptFactory::new();
    }

    protected $table = 'delivery_receipts';

    protected $fillable = [
        'vendor_id', 'customer_id', 'delivery_schedule_id', 'sales_order_id',
        'direction', 'status',
        'receipt_date', 'remarks', 'received_by_id', 'created_by_id',
        'vehicle_id', 'driver_name',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryReceiptItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
