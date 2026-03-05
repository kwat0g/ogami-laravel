<?php

declare(strict_types=1);

namespace App\Domains\Mold\Models;

use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

final class MoldMaster extends Model implements AuditableContract
{
    use HasPublicUlid, Auditable;

    protected $table = 'mold_masters';

    protected $fillable = [
        'mold_code',
        'name',
        'description',
        'cavity_count',
        'material',
        'location',
        'max_shots',
        'status',
        'is_active',
        'created_by_id',
    ];

    protected $casts = [
        'last_maintenance_at' => 'datetime',
        'is_active'           => 'boolean',
        'cavity_count'        => 'integer',
        'max_shots'           => 'integer',
        'current_shots'       => 'integer',
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

    /** @return BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }
}
