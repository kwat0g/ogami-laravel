<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $delivery_receipt_id
 * @property string|null $carrier
 * @property string|null $tracking_number
 * @property Carbon|null $shipped_at
 * @property Carbon|null $estimated_arrival
 * @property Carbon|null $actual_arrival
 * @property string|null $status
 * @property string|null $notes
 * @property bool $ar_invoice_created
 * @property int|null $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class Shipment extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'shipments';

    protected $fillable = [
        'delivery_receipt_id', 'carrier', 'tracking_number',
        'shipped_at', 'estimated_arrival', 'actual_arrival',
        'status', 'notes', 'ar_invoice_created', 'created_by_id',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'estimated_arrival' => 'date',
        'actual_arrival' => 'date',
        'ar_invoice_created' => 'boolean',
    ];

    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function impexDocuments(): HasMany
    {
        return $this->hasMany(ImpexDocument::class);
    }
}
