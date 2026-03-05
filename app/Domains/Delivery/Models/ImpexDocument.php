<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class ImpexDocument extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid;

    protected $table = 'impex_documents';

    protected $fillable = [
        'shipment_id', 'document_type', 'document_number',
        'issued_date', 'expiry_date', 'file_path', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
