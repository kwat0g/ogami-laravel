<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

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
 * Material Requisition — 5-stage SoD (Staff → Head → Manager → Officer → VP).
 *
 * @property int $id
 * @property string $ulid
 * @property string $mr_reference
 * @property int $requested_by_id
 * @property int $department_id
 * @property string $purpose
 * @property string $status draft|submitted|noted|checked|reviewed|approved|rejected|cancelled|fulfilled
 */
final class MaterialRequisition extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'material_requisitions';

    protected $fillable = [
        'mr_reference',
        'requested_by_id',
        'department_id',
        'production_order_id',
        'purpose',
        'status',
        'submitted_by_id', 'submitted_at',
        'noted_by_id', 'noted_at', 'noted_comments',
        'checked_by_id', 'checked_at', 'checked_comments',
        'reviewed_by_id', 'reviewed_at', 'reviewed_comments',
        'vp_approved_by_id', 'vp_approved_at', 'vp_comments',
        'rejected_by_id', 'rejected_at', 'rejection_reason',
        'fulfilled_by_id', 'fulfilled_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'noted_at' => 'datetime',
        'checked_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'vp_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    /** @return HasMany<MaterialRequisitionItem, MaterialRequisition> */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequisitionItem::class)->orderBy('line_order');
    }

    /** @return BelongsTo<User, MaterialRequisition> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<Department, MaterialRequisition> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, MaterialRequisition> */
    public function notedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'noted_by_id');
    }

    /** @return BelongsTo<User, MaterialRequisition> */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    /** @return BelongsTo<User, MaterialRequisition> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** @return BelongsTo<User, MaterialRequisition> */
    public function vpApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vp_approved_by_id');
    }

    /** @return BelongsTo<User, MaterialRequisition> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /** @return BelongsTo<User, MaterialRequisition> */
    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by_id');
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }
}
