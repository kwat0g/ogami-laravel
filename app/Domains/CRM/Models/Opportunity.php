<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\AR\Models\Customer;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property int $customer_id
 * @property int|null $contact_id
 * @property string $title
 * @property int $expected_value_centavos
 * @property int $probability_pct
 * @property string|null $expected_close_date
 * @property string $stage prospecting|qualification|proposal|negotiation|closed_won|closed_lost
 * @property int|null $assigned_to_id
 * @property string|null $notes
 * @property string|null $loss_reason
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Contact|null $contact
 * @property-read User|null $assignedTo
 * @property-read User $createdBy
 */
final class Opportunity extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'customer_id',
        'contact_id',
        'title',
        'expected_value_centavos',
        'probability_pct',
        'expected_close_date',
        'stage',
        'assigned_to_id',
        'notes',
        'loss_reason',
        'created_by_id',
    ];

    protected $casts = [
        'expected_value_centavos' => 'integer',
        'probability_pct' => 'integer',
        'expected_close_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'contactable');
    }

    /**
     * Weighted pipeline value = expected_value * probability / 100
     */
    public function weightedValueCentavos(): int
    {
        return (int) round($this->expected_value_centavos * $this->probability_pct / 100);
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, ['closed_won', 'closed_lost'], true);
    }
}
