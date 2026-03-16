<?php

declare(strict_types=1);

namespace App\Domains\AR\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tracks overpayments routed as advance payments per AR-005.
 *
 * @property int $id
 * @property int $customer_id
 * @property Carbon $received_date
 * @property float $amount
 * @property float $applied_amount
 * @property string|null $reference_number
 * @property string $status available|partially_applied|fully_applied
 * @property string|null $notes
 * @property int|null $journal_entry_id
 * @property int $created_by
 */
class CustomerAdvancePayment extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'received_date',
        'amount',
        'applied_amount',
        'reference_number',
        'status',
        'notes',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Remaining unapplied balance on this advance. */
    public function getUnappliedAmountAttribute(): float
    {
        return round((float) $this->amount - (float) $this->applied_amount, 2);
    }

    public function isFullyApplied(): bool
    {
        return $this->status === 'fully_applied';
    }
}
