<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\AP\Models\VendorInvoice;
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
 * GoodsReceipt — Warehouse Head records delivery against a Purchase Order.
 *
 * @property int $id
 * @property string $ulid
 * @property string $gr_reference GR-YYYY-MM-NNNNN
 * @property int $purchase_order_id
 * @property int $received_by_id
 * @property string $received_date
 * @property string|null $delivery_note_number
 * @property string|null $condition_notes
 * @property string $status draft|pending_qc|qc_passed|qc_failed|partial_accept|confirmed|rejected|returned
 * @property int|null $confirmed_by_id
 * @property Carbon|null $confirmed_at
 * @property string|null $rejection_reason
 * @property int|null $rejected_by_id
 * @property Carbon|null $rejected_at
 * @property int|null $submitted_for_qc_by_id
 * @property Carbon|null $submitted_for_qc_at
 * @property string|null $qc_result passed|failed|partial
 * @property Carbon|null $qc_completed_at
 * @property int|null $qc_completed_by_id
 * @property string|null $qc_notes
 * @property Carbon|null $returned_at
 * @property int|null $returned_by_id
 * @property string|null $return_reason
 * @property bool $three_way_match_passed
 * @property bool $ap_invoice_created
 * @property int|null $ap_invoice_id
 */
final class GoodsReceipt extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

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
        'rejection_reason',
        'rejected_by_id',
        'rejected_at',
        'submitted_for_qc_by_id',
        'submitted_for_qc_at',
        'qc_result',
        'qc_completed_at',
        'qc_completed_by_id',
        'qc_notes',
        'returned_at',
        'returned_by_id',
        'return_reason',
    ];

    protected $casts = [
        'received_date' => 'date',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'submitted_for_qc_at' => 'datetime',
        'qc_completed_at' => 'datetime',
        'returned_at' => 'datetime',
        'three_way_match_passed' => 'boolean',
        'ap_invoice_created' => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<PurchaseOrder, GoodsReceipt> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<User, GoodsReceipt> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    /** @return BelongsTo<User, GoodsReceipt> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    /** @return HasMany<GoodsReceiptItem, GoodsReceipt> */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /** @return BelongsTo<VendorInvoice, GoodsReceipt> */
    public function apInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'ap_invoice_id');
    }

    /** @return BelongsTo<User, GoodsReceipt> */
    public function submittedForQcBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_for_qc_by_id');
    }

    /** @return BelongsTo<User, GoodsReceipt> */
    public function qcCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_completed_by_id');
    }

    /** @return BelongsTo<User, GoodsReceipt> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /** @return BelongsTo<User, GoodsReceipt> */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_id');
    }

    /** @return HasMany<\App\Domains\QC\Models\Inspection, GoodsReceipt> */
    public function inspections(): HasMany
    {
        return $this->hasMany(\App\Domains\QC\Models\Inspection::class, 'goods_receipt_id');
    }

    /**
     * True when one or more GR line items have no linked item_master_id.
     * These items will be skipped by the auto-receive stock listener and need
     * manual resolution by the Warehouse Head.
     */
    public function hasUnlinkedItems(): bool
    {
        return $this->items->contains(fn ($item) => $item->item_master_id === null);
    }

    /**
     * True when all items that require IQC have passed inspection.
     */
    public function allIqcPassed(): bool
    {
        return $this->inspections()
            ->where('stage', 'iqc')
            ->where('status', '!=', 'passed')
            ->doesntExist()
            && $this->inspections()->where('stage', 'iqc')->exists();
    }
}
