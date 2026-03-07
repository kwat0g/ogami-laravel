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

final class Shipment extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'shipments';

    protected $fillable = [
        'delivery_receipt_id', 'carrier', 'tracking_number',
        'shipped_at', 'estimated_arrival', 'actual_arrival',
        'status', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'shipped_at'        => 'datetime',
        'estimated_arrival' => 'date',
        'actual_arrival'    => 'date',
    ];

    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    public function impexDocuments(): HasMany
    {
        return $this->hasMany(ImpexDocument::class);
    }
}
