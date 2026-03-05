<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * GoodsReceipt — Warehouse Head records delivery against a Purchase Order.
 *
 * @property int         $id
 * @property string      $ulid
 * @property string      $gr_reference       GR-YYYY-MM-NNNNN
 * @property int         $purchase_order_id
 * @property int         $received_by_id
 * @property string      $received_date
 * @property string|null $delivery_note_number
 * @property string|null $condition_notes
 * @property string      $status             draft|confirmed
 * @property int|null    $confirmed_by_id
 * @property \Carbon\Carbon|null $confirmed_at
 * @property bool        $three_way_match_passed
 * @property bool        $ap_invoice_created
 * @property int|null    $ap_invoice_id
 */
final class GoodsReceipt extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid;

    protected $table = 'goods_receipts';

    protected $fillable = [
        'gr_reference',
        'purchase_order_id',
        'received_by_id',
        'received_date',
        'delivery_note_number',
        'condition_notes',
        'status',
        'confirmed_by_id',
        'confirmed_at',
        'three_way_match_passed',
        'ap_invoice_created',
        'ap_invoice_id',
    ];

    protected $casts = [
        'received_date'          => 'date',
        'confirmed_at'           => 'datetime',
        'three_way_match_passed' => 'boolean',
        'ap_invoice_created'     => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<PurchaseOrder, GoodsReceipt> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<\App\Models\User, GoodsReceipt> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    /** @return BelongsTo<\App\Models\User, GoodsReceipt> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    /** @return HasMany<GoodsReceiptItem, GoodsReceipt> */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
