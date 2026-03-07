<?php

declare(strict_types=1);

namespace App\Domains\ISO\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class ControlledDocument extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'controlled_documents';

    protected $fillable = [
        'title', 'category', 'document_type', 'owner_id',
        'current_version', 'status', 'effective_date', 'review_date',
        'is_active', 'created_by_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'review_date'    => 'date',
        'is_active'      => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->orderByDesc('created_at');
    }
}
