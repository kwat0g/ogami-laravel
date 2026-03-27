<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Opportunity;
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
 * @property string $order_number
 * @property int $customer_id
 * @property int|null $contact_id
 * @property int|null $quotation_id
 * @property int|null $opportunity_id
 * @property string $status draft|confirmed|in_production|partially_delivered|delivered|invoiced|cancelled
 * @property string|null $requested_delivery_date
 * @property string|null $promised_delivery_date
 * @property int $total_centavos
 * @property string|null $notes
 * @property int $created_by_id
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Contact|null $contact
 * @property-read Quotation|null $quotation
 * @property-read Opportunity|null $opportunity
 * @property-read User $createdBy
 * @property-read Collection<int, SalesOrderItem> $items
 */
final class SalesOrder extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'sales_orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'contact_id',
        'quotation_id',
        'opportunity_id',
        'status',
        'requested_delivery_date',
        'promised_delivery_date',
        'total_centavos',
        'notes',
        'created_by_id',
        'approved_by_id',
        'approved_at',
    ];

    protected $casts = [
        'requested_delivery_date' => 'date',
        'promised_delivery_date' => 'date',
        'total_centavos' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }
}
