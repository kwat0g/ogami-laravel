<?php

declare(strict_types=1);

namespace App\Domains\Mold\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $ulid
 * @property string $mold_code
 * @property string $name
 * @property string|null $description
 * @property int $cavity_count
 * @property string|null $material
 * @property string|null $location
 * @property int|null $max_shots
 * @property int $current_shots
 * @property string $status active|under_maintenance|retired|inactive
 * @property bool $is_active
 * @property int|null $created_by_id
 * @property Carbon|null $last_maintenance_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class MoldMaster extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'mold_masters';

    protected $fillable = [
        'mold_code',
        'name',
        'description',
        'cavity_count',
        'material',
        'location',
        'max_shots',
        'cost_centavos',
        'expected_total_shots',
        'status',
        'is_active',
        'created_by_id',
    ];

    protected $casts = [
        'last_maintenance_at' => 'datetime',
        'is_active' => 'boolean',
        'cavity_count' => 'integer',
        'max_shots' => 'integer',
        'current_shots' => 'integer',
    ];

    public function isCritical(): bool
    {
        if ($this->max_shots === null) {
            return false;
        }

        return $this->current_shots >= ($this->max_shots * 0.9);
    }

    /** @return HasMany<MoldShotLog, $this> */
    public function shotLogs(): HasMany
    {
        return $this->hasMany(MoldShotLog::class, 'mold_id')->orderByDesc('log_date');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
