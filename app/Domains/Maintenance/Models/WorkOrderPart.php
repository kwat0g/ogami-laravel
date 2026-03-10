<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $work_order_id
 * @property int         $item_id
 * @property int         $location_id
 * @property float       $qty_required
 * @property float|null  $qty_consumed
 * @property string|null $remarks
 * @property int         $added_by_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class WorkOrderPart extends Model
{
    protected $table = 'maintenance_work_order_parts';

    protected $fillable = [
        'work_order_id',
        'item_id',
        'location_id',
        'qty_required',
        'qty_consumed',
        'remarks',
        'added_by_id',
    ];

    protected $casts = [
        'qty_required' => 'float',
        'qty_consumed' => 'float',
    ];

    /** @return BelongsTo<MaintenanceWorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'work_order_id');
    }

    /** @return BelongsTo<ItemMaster, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'added_by_id');
    }
}
