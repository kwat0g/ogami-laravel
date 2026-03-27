<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $route_number
 * @property string $planned_date
 * @property int|null $vehicle_id
 * @property int|null $driver_id
 * @property string $status planned|in_transit|completed|cancelled
 * @property int $stop_count
 * @property string|null $notes
 * @property int $created_by_id
 */
final class DeliveryRoute extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'delivery_routes';

    protected $fillable = [
        'route_number', 'planned_date', 'vehicle_id',
        'driver_id', 'status', 'stop_count', 'notes',
        'created_by_id',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'stop_count' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
