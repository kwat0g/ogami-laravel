<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\HR\Models\Department;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * PurchaseRequest — 5-stage SoD approval chain (Staff → Head → Manager → Officer → VP).
 *
 * @property int         $id
 * @property string      $ulid
 * @property string      $pr_reference   PR-YYYY-MM-NNNNN
 * @property int         $department_id
 * @property int         $requested_by_id
 * @property string      $urgency        normal|urgent|critical
 * @property string      $justification
 * @property string|null $notes
 * @property string      $status         draft|submitted|noted|checked|reviewed|budget_checked|returned|approved|rejected|cancelled|converted_to_po
 * @property int|null    $submitted_by_id
 * @property \Carbon\Carbon|null $submitted_at
 * @property int|null    $noted_by_id
 * @property \Carbon\Carbon|null $noted_at
 * @property string|null $noted_comments
 * @property int|null    $checked_by_id
 * @property \Carbon\Carbon|null $checked_at
 * @property string|null $checked_comments
 * @property int|null    $reviewed_by_id
 * @property \Carbon\Carbon|null $reviewed_at
 * @property string|null $reviewed_comments
 * @property int|null    $vp_approved_by_id
 * @property \Carbon\Carbon|null $vp_approved_at
 * @property string|null $vp_comments
 * @property int|null    $budget_checked_by_id
 * @property \Carbon\Carbon|null $budget_checked_at
 * @property string|null $budget_checked_comments
 * @property int|null    $returned_by_id
 * @property \Carbon\Carbon|null $returned_at
 * @property string|null $return_reason
 * @property int|null    $rejected_by_id
 * @property \Carbon\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property string|null $rejection_stage
 * @property int|null    $converted_to_po_id
 * @property \Carbon\Carbon|null $converted_at
 * @property numeric-string $total_estimated_cost
 */
final class PurchaseRequest extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'purchase_requests';

    protected $fillable = [
        'pr_reference',
        'department_id',
        'requested_by_id',
        'vendor_id',
        'urgency',
        'justification',
        'notes',
        'status',
        'submitted_by_id',
        'submitted_at',
        'noted_by_id',
        'noted_at',
        'noted_comments',
        'checked_by_id',
        'checked_at',
        'checked_comments',
        'reviewed_by_id',
        'reviewed_at',
        'reviewed_comments',
        'vp_approved_by_id',
        'vp_approved_at',
        'vp_comments',
        'budget_checked_by_id',
        'budget_checked_at',
        'budget_checked_comments',
        'returned_by_id',
        'returned_at',
        'return_reason',
        'rejected_by_id',
        'rejected_at',
        'rejection_reason',
        'rejection_stage',
        'converted_to_po_id',
        'converted_at',
        'total_estimated_cost',
    ];

    protected $casts = [
        'submitted_at'   => 'datetime',
        'noted_at'       => 'datetime',
        'checked_at'     => 'datetime',
        'reviewed_at'    => 'datetime',
        'vp_approved_at'      => 'datetime',
        'budget_checked_at'  => 'datetime',
        'returned_at'        => 'datetime',
        'rejected_at'        => 'datetime',
        'converted_at'       => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return HasMany<PurchaseRequestItem, PurchaseRequest> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('line_order');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function notedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'noted_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function vpApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vp_approved_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function budgetCheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_checked_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_id');
    }

    /** @return BelongsTo<\App\Models\User, PurchaseRequest> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /** @return BelongsTo<Department, PurchaseRequest> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        // returned PRs can be revised by the requester (treated as draft)
        return in_array($this->status, ['draft', 'returned'], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }
}
