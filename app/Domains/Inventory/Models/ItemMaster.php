<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Domains\AP\Models\Vendor;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\ItemMasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property string $costing_method standard|fifo|weighted_average
 * @property bool $requires_iqc
 * @property bool $is_active
 * @property int|null $preferred_vendor_id
 * @property-read Vendor|null $preferredVendor
 */
final class ItemMaster extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected static function newFactory(): ItemMasterFactory
    {
        return ItemMasterFactory::new();
    }

    protected $table = 'item_masters';

    protected $fillable = [
        'item_code',
        'category_id',
        'name',
        'unit_of_measure',
        'description',
        'standard_price_centavos',
        'costing_method',
        'reorder_point',
        'reorder_qty',
        'type',
        'requires_iqc',
        'is_active',
        'preferred_vendor_id',
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

    /** @return BelongsTo<Vendor, $this> */
    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
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
