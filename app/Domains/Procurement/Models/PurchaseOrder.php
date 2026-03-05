<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\AP\Models\Vendor;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * PurchaseOrder — Purchasing Officer converts an approved PR into a PO for a vendor.
 *
 * @property int         $id
 * @property string      $ulid
 * @property string      $po_reference   PO-YYYY-MM-NNNNN
 * @property int         $purchase_request_id
 * @property int         $vendor_id
 * @property string      $po_date
 * @property string      $delivery_date
 * @property string      $payment_terms
 * @property string|null $delivery_address
 * @property string      $status         draft|sent|partially_received|fully_received|closed|cancelled
 * @property numeric-string $total_po_amount  updated by trigger
 * @property int         $created_by_id
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $closed_at
 * @property string|null $cancellation_reason
 * @property string|null $notes
 */
final class PurchaseOrder extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_reference',
        'purchase_request_id',
        'vendor_id',
        'po_date',
        'delivery_date',
        'payment_terms',
        'delivery_address',
        'status',
        'total_po_amount',
        'created_by_id',
        'sent_at',
        'closed_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'po_date'       => 'date',
        'delivery_date' => 'date',
        'sent_at'       => 'datetime',
        'closed_at'     => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<PurchaseRequest, PurchaseOrder> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /** @return BelongsTo<\App\Domains\AP\Models\Vendor, PurchaseOrder> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<\App\Models\User, PurchaseOrder> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<PurchaseOrderItem, PurchaseOrder> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_order');
    }

    /** @return HasMany<GoodsReceipt, PurchaseOrder> */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function canReceiveGoods(): bool
    {
        return in_array($this->status, ['sent', 'partially_received'], true);
    }
}
