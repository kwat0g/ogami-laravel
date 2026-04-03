<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\AR\Models\Customer;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Opportunity extends Model
{
    use HasPublicUlid, SoftDeletes;

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
}
