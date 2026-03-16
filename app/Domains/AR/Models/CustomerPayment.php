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
 * @property int $id
 * @property int $customer_invoice_id
 * @property int $customer_id
 * @property Carbon $payment_date
 * @property float $amount
 * @property string|null $reference_number
 * @property string|null $payment_method bank_transfer|check|cash|online
 * @property string|null $notes
 * @property int|null $journal_entry_id
 * @property int $created_by
 */
class CustomerPayment extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $fillable = [
        'customer_invoice_id',
        'customer_id',
        'payment_date',
        'amount',
        'reference_number',
        'payment_method',
        'notes',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
