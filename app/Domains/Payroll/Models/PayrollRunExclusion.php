<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * PayrollRunExclusion — manual per-employee exclusion from a payroll run.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $reason
 * @property int $excluded_by_id
 * @property Carbon $excluded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PayrollRun $run
 * @property-read Employee   $employee
 * @property-read User       $excludedBy
 */
final class PayrollRunExclusion extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'payroll_run_exclusions';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'reason',
        'excluded_by_id',
        'excluded_at',
    ];

    protected function casts(): array
    {
        return [
            'excluded_at' => 'datetime',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function excludedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluded_by_id');
    }
}
