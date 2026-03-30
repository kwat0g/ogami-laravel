<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\QC\Models\Inspection;
use App\Domains\Sales\Models\SalesOrder;
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
 * @property int|null $delivery_schedule_item_id
 * @property int|null $client_order_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $sales_order_id
 * @property int $product_item_id
 * @property int $bom_id
 * @property string $qty_required
 * @property string $qty_produced
 * @property string $qty_rejected
 * @property int $standard_unit_cost_centavos
 * @property int $estimated_total_cost_centavos
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
        'delivery_schedule_item_id',
        'client_order_id',
        'source_type',
        'source_id',
        'sales_order_id',
        'product_item_id',
        'bom_id',
        'bom_snapshot',
        'qty_required',
        'standard_unit_cost_centavos',
        'estimated_total_cost_centavos',
        'target_start_date',
        'target_end_date',
        'status',
        'notes',
        'hold_reason',
        'held_from_state',
        'created_by_id',
    ];

    protected $casts = [
        'qty_required' => 'decimal:4',
        'qty_produced' => 'decimal:4',
        'qty_rejected' => 'decimal:4',
        'standard_unit_cost_centavos' => 'integer',
        'estimated_total_cost_centavos' => 'integer',
        'bom_snapshot' => 'array',
        'target_start_date' => 'date',
        'target_end_date' => 'date',
    ];

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class, 'delivery_schedule_id');
    }

    public function deliveryScheduleItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryScheduleItem::class, 'delivery_schedule_item_id');
    }

    public function clientOrder(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class, 'client_order_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
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

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'production_order_id');
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
