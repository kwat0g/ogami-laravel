<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class Vehicle extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid;

    protected $table = 'vehicles';

    protected $fillable = [
        'code', 'name', 'type', 'make_model',
        'plate_number', 'status', 'notes',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function deliveryReceipts(): HasMany
    {
        return $this->hasMany(DeliveryReceipt::class);
    }
}
