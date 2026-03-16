<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Models\User;
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
 * @property string $po_reference
 * @property int|null $delivery_schedule_id
 * @property int $product_item_id
 * @property int $bom_id
 * @property string $qty_required
 * @property string $qty_produced
 * @property string $qty_rejected
 * @property string $target_start_date
 * @property string $target_end_date
 * @property string $status
 * @property string|null $notes
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ProductionOrder extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'production_orders';

    protected $fillable = [
        'ulid',
        'delivery_schedule_id',
        'product_item_id',
        'bom_id',
        'qty_required',
        'target_start_date',
        'target_end_date',
        'status',
        'notes',
        'hold_reason',
        'created_by_id',
    ];

    protected $casts = [
        'qty_required' => 'decimal:4',
        'qty_produced' => 'decimal:4',
        'qty_rejected' => 'decimal:4',
        'target_start_date' => 'date',
        'target_end_date' => 'date',
    ];

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class, 'delivery_schedule_id');
    }

    public function productItem(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'product_item_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function outputLogs(): HasMany
    {
        return $this->hasMany(ProductionOutputLog::class, 'production_order_id');
    }

    public function materialRequisitions(): HasMany
    {
        return $this->hasMany(MaterialRequisition::class, 'production_order_id');
    }

    public function progressPct(): float
    {
        $req = (float) $this->qty_required;
        if ($req <= 0) {
            return 0.0;
        }

        return min(100.0, ((float) $this->qty_produced / $req) * 100);
    }
}
