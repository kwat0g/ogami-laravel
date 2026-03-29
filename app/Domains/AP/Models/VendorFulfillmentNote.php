<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * VendorFulfillmentNote — tracks vendor-side fulfillment updates on a PO.
 *
 * @property int $id
 * @property string $ulid
 * @property int $purchase_order_id
 * @property int $vendor_user_id
 * @property string $note_type in_transit|delivered|partial
 * @property string|null $notes
 * @property array|null $items [{po_item_id, qty_delivered}]
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class VendorFulfillmentNote extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'vendor_fulfillment_notes';

    protected $fillable = [
        'purchase_order_id',
        'vendor_user_id',
        'note_type',
        'notes',
        'delivery_date',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
        'delivery_date' => 'date',
    ];

    /** @return BelongsTo<PurchaseOrder, VendorFulfillmentNote> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<User, VendorFulfillmentNote> */
    public function vendorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }
}
