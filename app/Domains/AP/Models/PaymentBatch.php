<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $batch_number
 * @property string $status draft|submitted|approved|processing|completed|cancelled
 * @property string $payment_date
 * @property string $payment_method bank_transfer|check|cash
 * @property int $total_amount_centavos
 * @property int $payment_count
 * @property string|null $notes
 * @property int $created_by_id
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $createdBy
 * @property-read User|null $approvedBy
 * @property-read Collection<int, PaymentBatchItem> $items
 */
final class PaymentBatch extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'payment_batches';

    protected $fillable = [
        'batch_number',
        'status',
        'payment_date',
        'payment_method',
        'total_amount_centavos',
        'payment_count',
        'notes',
        'created_by_id',
        'approved_by_id',
        'approved_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'total_amount_centavos' => 'integer',
        'payment_count' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentBatchItem::class, 'payment_batch_id');
    }
}
