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
 * @property string $company_name
 * @property string $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string $source website|referral|trade_show|cold_call|social_media|other
 * @property string $status new|contacted|qualified|converted|disqualified
 * @property int|null $assigned_to_id
 * @property string|null $notes
 * @property int|null $converted_customer_id
 * @property Carbon|null $converted_at
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $assignedTo
 * @property-read Customer|null $convertedCustomer
 * @property-read User $createdBy
 */
final class Lead extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'source',
        'status',
        'assigned_to_id',
        'notes',
        'converted_customer_id',
        'converted_at',
        'created_by_id',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'contactable');
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isDisqualified(): bool
    {
        return $this->status === 'disqualified';
    }
}
