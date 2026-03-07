<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\LotBatch;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $stage
 * @property string $status
 * @property int|null $inspection_template_id
 * @property int|null $goods_receipt_id
 * @property int|null $production_order_id
 * @property int|null $item_master_id
 * @property int|null $lot_batch_id
 * @property string $qty_inspected
 * @property string $qty_passed
 * @property string $qty_failed
 * @property string|null $inspection_date
 * @property int|null $inspector_id
 * @property string|null $remarks
 * @property int|null $created_by_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class Inspection extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'inspections';

    protected $fillable = [
        'stage',
        'status',
        'inspection_template_id',
        'goods_receipt_id',
        'production_order_id',
        'item_master_id',
        'lot_batch_id',
        'qty_inspected',
        'qty_passed',
        'qty_failed',
        'inspection_date',
        'inspector_id',
        'remarks',
        'created_by_id',
    ];

    protected $casts = [
        'qty_inspected' => 'decimal:4',
        'qty_passed' => 'decimal:4',
        'qty_failed' => 'decimal:4',
        'inspection_date' => 'date',
    ];

    /** @return BelongsTo<InspectionTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }

    /** @return BelongsTo<ItemMaster, $this> */
    public function itemMaster(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_master_id');
    }

    /** @return BelongsTo<LotBatch, $this> */
    public function lotBatch(): BelongsTo
    {
        return $this->belongsTo(LotBatch::class, 'lot_batch_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\HR\Models\Employee::class, 'inspector_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    /** @return HasMany<InspectionResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class, 'inspection_id');
    }

    /** @return HasMany<NonConformanceReport, $this> */
    public function ncrs(): HasMany
    {
        return $this->hasMany(NonConformanceReport::class, 'inspection_id');
    }
}
