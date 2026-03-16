<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $item_code
 * @property int $category_id
 * @property string $name
 * @property string $unit_of_measure
 * @property string|null $description
 * @property numeric-string $reorder_point
 * @property numeric-string $reorder_qty
 * @property string $type raw_material|semi_finished|finished_good|consumable|spare_part
 * @property bool $requires_iqc
 * @property bool $is_active
 */
final class ItemMaster extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'item_masters';

    protected $fillable = [
        'item_code',
        'category_id',
        'name',
        'unit_of_measure',
        'description',
        'reorder_point',
        'reorder_qty',
        'type',
        'requires_iqc',
        'is_active',
    ];

    protected $casts = [
        'requires_iqc' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<ItemCategory, ItemMaster> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    /** @return HasMany<StockBalance, ItemMaster> */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'item_id');
    }

    /** @return HasMany<LotBatch, ItemMaster> */
    public function lots(): HasMany
    {
        return $this->hasMany(LotBatch::class, 'item_id');
    }
}
