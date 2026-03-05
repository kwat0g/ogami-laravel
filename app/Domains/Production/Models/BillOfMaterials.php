<?php

declare(strict_types=1);

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\ItemMaster;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int         $id
 * @property string      $ulid
 * @property int         $product_item_id
 * @property string      $version
 * @property bool        $is_active
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ItemMaster $productItem
 * @property-read \Illuminate\Database\Eloquent\Collection<int,BomComponent> $components
 */
final class BillOfMaterials extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'ulid',
        'product_item_id',
        'version',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
