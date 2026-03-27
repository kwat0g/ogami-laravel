<?php

declare(strict_types=1);

namespace App\Domains\AR\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $customer_id
 * @property int $customer_invoice_id
 * @property int $dunning_level_id
 * @property int $amount_due_centavos
 * @property int $days_overdue
 * @property string $status generated|sent|acknowledged|escalated|resolved
 * @property Carbon|null $sent_at
 * @property string|null $notes
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read CustomerInvoice $invoice
 * @property-read DunningLevel $dunningLevel
 * @property-read User $createdBy
 */
final class DunningNotice extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'dunning_notices';

    protected $fillable = [
        'customer_id',
        'customer_invoice_id',
        'dunning_level_id',
        'amount_due_centavos',
        'days_overdue',
        'status',
        'sent_at',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'amount_due_centavos' => 'integer',
        'days_overdue' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function dunningLevel(): BelongsTo
    {
        return $this->belongsTo(DunningLevel::class, 'dunning_level_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
