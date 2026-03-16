<?php

declare(strict_types=1);

namespace App\Domains\QC\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $ulid
 * @property string $ncr_reference
 * @property int $inspection_id
 * @property string $title
 * @property string $description
 * @property string $severity
 * @property string $status
 * @property int|null $raised_by_id
 * @property Carbon|null $closed_at
 * @property int|null $closed_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class NonConformanceReport extends Model implements AuditableContract
{
    use Auditable, HasPublicUlid, SoftDeletes;

    protected $table = 'non_conformance_reports';

    protected $fillable = [
        'inspection_id',
        'title',
        'description',
        'severity',
        'status',
        'raised_by_id',
        'closed_at',
        'closed_by_id',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    /** @return HasMany<CapaAction, $this> */
    public function capaActions(): HasMany
    {
        return $this->hasMany(CapaAction::class, 'ncr_id');
    }
}
