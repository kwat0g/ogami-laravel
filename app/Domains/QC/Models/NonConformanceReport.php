<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class NonConformanceReport extends Model implements AuditableContract
{
    use HasPublicUlid, Auditable, SoftDeletes;

    protected $table = 'non_conformance_reports';

    protected $fillable = [
        'inspection_id',
        'title',
        'description',
        'severity',
        'status',
        'raised_by_id',
        'closed_at',
        'closed_by_id',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'raised_by_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by_id');
    }

    /** @return HasMany<CapaAction, $this> */
    public function capaActions(): HasMany
    {
        return $this->hasMany(CapaAction::class, 'ncr_id');
    }
}
