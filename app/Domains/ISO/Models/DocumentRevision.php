<?php

declare(strict_types=1);

namespace App\Domains\ISO\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;

final class DocumentRevision extends Model implements \OwenIt\Auditing\Contracts\Auditable
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'document_revisions';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'controlled_document_id', 'version', 'change_summary',
        'file_path', 'revised_by_id', 'approved_by_id', 'approved_at',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function controlledDocument(): BelongsTo
    {
        return $this->belongsTo(ControlledDocument::class);
    }

    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
