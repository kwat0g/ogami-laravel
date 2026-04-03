<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\AR\Models\Customer;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Contact extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'role',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'contact_id');
    }
}
