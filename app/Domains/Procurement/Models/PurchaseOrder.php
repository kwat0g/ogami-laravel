<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorFulfillmentNote;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * PurchaseOrder — Purchasing Officer converts an approved PR into a PO for a vendor.
 *
 * @property int $id
 * @property string $ulid
 * @property string $po_reference PO-YYYY-MM-NNNNN
 * @property int $purchase_request_id
 * @property int|null $vendor_id
 * @property string $po_date
 * @property string $delivery_date
 * @property string $payment_terms
 * @property string|null $delivery_address
 * @property string $status draft|sent|negotiating|acknowledged|in_transit|delivered|partially_received|fully_received|closed|cancelled
 * @property numeric-string $total_po_amount updated by trigger
 * @property int $created_by_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $closed_at
 * @property string|null $cancellation_reason
 * @property string|null $notes
 */
final class PurchaseOrder extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_reference',
        'purchase_request_id',
        'parent_po_id',
        'vendor_id',
        'po_date',
        'delivery_date',
        'payment_terms',
        'delivery_address',
        'status',
        'po_type',
        'total_po_amount',
        'created_by_id',
        'sent_at',
        'closed_at',
        'cancellation_reason',
        'notes',
        'vendor_remarks',
        'negotiation_round',
        'change_requested_at',
        'change_reviewed_at',
        'change_reviewed_by_id',
        'change_review_remarks',
        'vendor_acknowledged_at',
        'in_transit_at',
        'tracking_number',
        'proposed_delivery_date',
        'proposed_payment_terms',
        'original_total_po_amount',
        'requires_budget_recheck',
    ];

    protected $casts = [
        'po_date' => 'date',
        'delivery_date' => 'date',
        'proposed_delivery_date' => 'date',
        'sent_at' => 'datetime',
        'closed_at' => 'datetime',
        'po_type' => 'string',
        'change_requested_at' => 'datetime',
        'change_reviewed_at' => 'datetime',
        'vendor_acknowledged_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'negotiation_round' => 'integer',
        'requires_budget_recheck' => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<PurchaseRequest, PurchaseOrder> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /** @return BelongsTo<PurchaseOrder, PurchaseOrder> */
    public function parentPo(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'parent_po_id');
    }

    /** @return HasMany<PurchaseOrder, PurchaseOrder> */
    public function childPos(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'parent_po_id')->orderBy('created_at');
    }

    /** @return BelongsTo<Vendor, PurchaseOrder> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<User, PurchaseOrder> */
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

    /** @return HasMany<VendorFulfillmentNote, PurchaseOrder> */
    public function fulfillmentNotes(): HasMany
    {
        return $this->hasMany(VendorFulfillmentNote::class)->orderBy('created_at', 'desc');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function canReceiveGoods(): bool
    {
        return in_array($this->status, ['acknowledged', 'in_transit', 'delivered', 'partially_received'], true);
    }
}
