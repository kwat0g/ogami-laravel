<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $contactable_type
 * @property int $contactable_id
 * @property string $type call|meeting|email|note|task
 * @property string $subject
 * @property string|null $notes
 * @property Carbon $activity_date
 * @property Carbon|null $next_action_date
 * @property string|null $next_action_description
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $contactable
 * @property-read User $createdBy
 */
final class CrmActivity extends Model
{
    use HasPublicUlid, SoftDeletes;

    protected $table = 'crm_activities';

    protected $fillable = [
        'contactable_type',
        'contactable_id',
        'type',
        'subject',
        'notes',
        'activity_date',
        'next_action_date',
        'next_action_description',
        'created_by_id',
    ];

    protected $casts = [
        'activity_date' => 'datetime',
        'next_action_date' => 'datetime',
    ];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
