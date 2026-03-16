<?php

declare(strict_types=1);

namespace App\Domains\Mold\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Mold shot log - tracks mold usage for maintenance scheduling.
 * HIGH-002: Audit trail enabled for mold lifecycle tracking.
 */
final class MoldShotLog extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    public $timestamps = false;

    /**
     * Attributes to include in the audit trail.
     * Shot counts are audited for maintenance traceability.
     *
     * @var list<string>
     */
    protected $auditInclude = [
        'mold_id',
        'production_order_id',
        'shot_count',
        'operator_id',
        'log_date',
        'remarks',
    ];

    const CREATED_AT = 'created_at';

    protected $table = 'mold_shot_logs';

    protected $fillable = [
        'mold_id',
        'production_order_id',
        'shot_count',
        'operator_id',
        'log_date',
        'remarks',
    ];

    protected $casts = [
        'log_date' => 'date',
        'shot_count' => 'integer',
    ];

    /** @return BelongsTo<MoldMaster, $this> */
    public function mold(): BelongsTo
    {
        return $this->belongsTo(MoldMaster::class, 'mold_id');
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
