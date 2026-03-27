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
 * @property string $quotation_number
 * @property int $customer_id
 * @property int|null $contact_id
 * @property int|null $opportunity_id
 * @property string $validity_date
 * @property int $total_centavos
 * @property string $status draft|sent|accepted|converted_to_order|rejected|expired
 * @property string|null $notes
 * @property string|null $terms_and_conditions
 * @property int $created_by_id
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Contact|null $contact
 * @property-read Opportunity|null $opportunity
 * @property-read User $createdBy
 * @property-read Collection<int, QuotationItem> $items
 */
final class Quotation extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'quotations';

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'contact_id',
        'opportunity_id',
        'validity_date',
        'total_centavos',
        'status',
        'notes',
        'terms_and_conditions',
        'created_by_id',
        'approved_by_id',
        'approved_at',
    ];

    protected $casts = [
        'validity_date' => 'date',
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
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function isExpired(): bool
    {
        return $this->status !== 'expired'
            && now()->greaterThan($this->validity_date);
    }
}
