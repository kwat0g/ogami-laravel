<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $bom_id
 * @property int|null $parent_bom_component_id
 * @property int $component_item_id
 * @property string $qty_per_unit
 * @property string $unit_of_measure
 * @property string $scrap_factor_pct
 * @property-read BillOfMaterials $bom
 * @property-read ItemMaster $componentItem
 */
final class BomComponent extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'bom_components';

    protected $fillable = [
        'bom_id',
        'parent_bom_component_id',
        'component_item_id',
        'qty_per_unit',
        'unit_of_measure',
        'scrap_factor_pct',
    ];

    protected $casts = [
        'qty_per_unit' => 'decimal:4',
        'scrap_factor_pct' => 'decimal:2',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class);
    }

    public function componentItem(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'component_item_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_bom_component_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_bom_component_id');
    }
}
