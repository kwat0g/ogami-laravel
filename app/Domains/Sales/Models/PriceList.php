<?php

declare(strict_types=1);

namespace App\Domains\Sales\Models;

use App\Domains\AR\Models\Customer;
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
 * @property string $name
 * @property string $effective_from
 * @property string|null $effective_to
 * @property bool $is_default
 * @property int|null $customer_id
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer|null $customer
 * @property-read User $createdBy
 * @property-read Collection<int, PriceListItem> $items
 */
final class PriceList extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'price_lists';

    protected $fillable = [
        'name',
        'effective_from',
        'effective_to',
        'is_default',
        'customer_id',
        'created_by_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }

    public function isActive(): bool
    {
        $today = now()->toDateString();

        return $this->effective_from <= $today
            && ($this->effective_to === null || $this->effective_to >= $today);
    }
}
