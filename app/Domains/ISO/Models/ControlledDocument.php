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

/**
 * @property int         $id
 * @property string      $ulid
 * @property string      $title
 * @property string|null $category
 * @property string      $document_type
 * @property int|null    $owner_id
 * @property string      $current_version
 * @property string      $status
 * @property \Carbon\Carbon|null $effective_date
 * @property \Carbon\Carbon|null $review_date
 * @property bool        $is_active
 * @property int|null    $created_by_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
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
