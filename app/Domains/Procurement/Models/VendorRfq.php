<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int         $id
 * @property string      $ulid
 * @property string      $rfq_reference       RFQ-YYYY-MM-NNNNN
 * @property int|null    $purchase_request_id
 * @property string      $status              draft|sent|quote_received|closed|cancelled
 * @property string|null $deadline_date
 * @property string      $scope_description
 * @property string|null $notes
 * @property int         $created_by_id
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class VendorRfq extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'vendor_rfqs';

    protected $fillable = [
        'rfq_reference',
        'purchase_request_id',
        'status',
        'deadline_date',
        'scope_description',
        'notes',
        'created_by_id',
        'sent_at',
        'closed_at',
    ];

    protected $casts = [
        'deadline_date' => 'date',
        'sent_at'       => 'datetime',
        'closed_at'     => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<VendorRfqVendor, $this> */
    public function vendorInvitations(): HasMany
    {
        return $this->hasMany(VendorRfqVendor::class, 'rfq_id');
    }
}
