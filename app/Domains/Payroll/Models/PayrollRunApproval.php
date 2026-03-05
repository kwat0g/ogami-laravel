<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * PayrollRunApproval — one row per approval stage action.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property string $stage 'HR_REVIEW' | 'ACCOUNTING'
 * @property string $action 'APPROVED' | 'RETURNED' | 'REJECTED'
 * @property int $actor_id
 * @property string|null $comments
 * @property array|null $checkboxes_checked
 * @property Carbon $acted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User        $actor
 * @property-read PayrollRun  $run
 */
final class PayrollRunApproval extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'payroll_run_approvals';

    protected $fillable = [
        'payroll_run_id',
        'stage',
        'action',
        'actor_id',
        'comments',
        'checkboxes_checked',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
            'checkboxes_checked' => 'array',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
