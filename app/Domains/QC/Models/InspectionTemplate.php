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

final class InspectionTemplate extends Model implements AuditableContract
{
    use HasPublicUlid, Auditable, SoftDeletes;

    protected $table = 'inspection_templates';

    protected $fillable = [
        'name',
        'stage',
        'description',
        'is_active',
        'created_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    /** @return HasMany<InspectionTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InspectionTemplateItem::class, 'inspection_template_id')->orderBy('sort_order');
    }
}
