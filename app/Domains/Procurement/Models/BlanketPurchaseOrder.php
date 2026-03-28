<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\AP\Models\Vendor;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Blanket Purchase Order — Item 31.
 *
 * Long-term agreement with a vendor for agreed prices/quantities.
 * Individual POs (releases) draw against the blanket's committed amount.
 *
 * @property int $id
 * @property string $ulid
 * @property string $bpo_reference
 * @property int $vendor_id
 * @property string $start_date
 * @property string $end_date
 * @property int $committed_amount_centavos Total committed spend
 * @property int $released_amount_centavos Sum of all release POs
 * @property string $status draft|active|expired|closed
 * @property string|null $terms
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class BlanketPurchaseOrder extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'blanket_purchase_orders';

    protected $fillable = [
        'bpo_reference',
        'vendor_id',
        'start_date',
        'end_date',
        'committed_amount_centavos',
        'released_amount_centavos',
        'status',
        'terms',
        'created_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'committed_amount_centavos' => 'integer',
        'released_amount_centavos' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'blanket_po_id');
    }

    public function remainingAmountCentavos(): int
    {
        return max(0, $this->committed_amount_centavos - $this->released_amount_centavos);
    }

    public function utilizationPct(): float
    {
        return $this->committed_amount_centavos > 0
            ? round(($this->released_amount_centavos / $this->committed_amount_centavos) * 100, 2)
            : 0.0;
    }
}
