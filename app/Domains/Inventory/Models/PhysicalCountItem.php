<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $physical_count_id
 * @property int $item_id
 * @property string $system_qty
 * @property string|null $counted_qty
 * @property string|null $variance_qty
 * @property string|null $remarks
 * @property-read PhysicalCount $physicalCount
 * @property-read ItemMaster $item
 */
final class PhysicalCountItem extends Model
{
    protected $table = 'physical_count_items';

    protected $fillable = [
        'physical_count_id',
        'item_id',
        'system_qty',
        'counted_qty',
        'variance_qty',
        'remarks',
    ];

    protected $casts = [
        'system_qty' => 'decimal:4',
        'counted_qty' => 'decimal:4',
        'variance_qty' => 'decimal:4',
    ];

    public function physicalCount(): BelongsTo
    {
        return $this->belongsTo(PhysicalCount::class, 'physical_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }
}
