<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $material_requisition_id
 * @property int    $item_id
 * @property numeric-string $qty_requested
 * @property numeric-string|null $qty_issued
 * @property string|null $remarks
 * @property int    $line_order
 */
final class MaterialRequisitionItem extends Model
{
    use SoftDeletes;

    protected $table = 'material_requisition_items';

    public $timestamps = false;

    protected $fillable = [
        'material_requisition_id',
        'item_id',
        'qty_requested',
        'qty_issued',
        'remarks',
        'line_order',
    ];

    /** @return BelongsTo<MaterialRequisition, MaterialRequisitionItem> */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class, 'material_requisition_id');
    }

    /** @return BelongsTo<ItemMaster, MaterialRequisitionItem> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }
}
