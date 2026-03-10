<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * VendorItem — an item in a vendor's product/price catalog.
 *
 * @property int         $id
 * @property string      $ulid
 * @property int         $vendor_id
 * @property string      $item_code
 * @property string      $item_name
 * @property string|null $description
 * @property string      $unit_of_measure
 * @property int         $unit_price      centavos
 * @property bool        $is_active
 * @property int         $created_by_id
 */
final class VendorItem extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'vendor_items';

    protected $fillable = [
        'vendor_id',
        'item_code',
        'item_name',
        'description',
        'unit_of_measure',
        'unit_price',
        'is_active',
        'created_by_id',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'unit_price' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /** @return BelongsTo<Vendor, VendorItem> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
