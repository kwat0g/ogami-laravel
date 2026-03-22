<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\ItemMaster;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property int $product_item_id
 * @property string $version
 * @property bool $is_active
 * @property string|null $notes
 * @property int $standard_production_days
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ItemMaster $productItem
 * @property-read Collection<int,BomComponent> $components
 */
final class BillOfMaterials extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'ulid',
        'product_item_id',
        'version',
        'is_active',
        'standard_production_days',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'standard_production_days' => 'integer',
    ];

    public function productItem(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'product_item_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(BomComponent::class, 'bom_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'bom_id');
    }
}
