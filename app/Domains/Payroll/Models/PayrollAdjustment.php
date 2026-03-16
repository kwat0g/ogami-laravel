<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Payroll Adjustment — ad-hoc earnings or deductions for a pay period.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $type earning|deduction
 * @property string $nature taxable|non_taxable
 * @property string $description
 * @property int|null $gl_account_id
 * @property int $amount_centavos
 * @property string $status pending|applied|deferred
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read PayrollRun $payrollRun
 */
final class PayrollAdjustment extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'payroll_adjustments';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'type',
        'nature',
        'description',
        'gl_account_id',
        'amount_centavos',
        'status',
        'created_by',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }
}
