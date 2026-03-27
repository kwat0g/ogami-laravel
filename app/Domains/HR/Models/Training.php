<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $ulid
 * @property string $title
 * @property string|null $description
 * @property string $type internal|external|online|on_the_job
 * @property string|null $provider
 * @property string $start_date
 * @property string|null $end_date
 * @property int $cost_centavos
 * @property string $status scheduled|in_progress|completed|cancelled
 * @property int $created_by_id
 * @property-read User $createdBy
 * @property-read Collection<int, TrainingAttendee> $attendees
 */
final class Training extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'trainings';

    protected $fillable = [
        'title', 'description', 'type', 'provider',
        'start_date', 'end_date', 'cost_centavos',
        'status', 'created_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cost_centavos' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(TrainingAttendee::class, 'training_id');
    }
}
