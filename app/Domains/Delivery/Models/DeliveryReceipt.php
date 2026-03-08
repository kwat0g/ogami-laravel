<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Shared\Traits\HasPublicUlid;
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
 * @property string $direction
 * @property string $status
 * @property string|null $receipt_date
 * @property string|null $remarks
 * @property int|null $received_by_id
 * @property int|null $created_by_id
 * @property int|null $vehicle_id
 * @property string|null $driver_name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryReceiptItem> $items
 */
final class DeliveryReceipt extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'delivery_receipts';

    protected $fillable = [
        'vendor_id', 'customer_id', 'delivery_schedule_id', 'direction', 'status',
        'receipt_date', 'remarks', 'received_by_id', 'created_by_id',
        'vehicle_id', 'driver_name',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\DeliverySchedule::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\AP\Models\Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\AR\Models\Customer::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'received_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
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
