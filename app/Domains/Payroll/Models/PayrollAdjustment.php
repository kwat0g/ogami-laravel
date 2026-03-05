<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int $amount_centavos
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read PayrollRun $payrollRun
 */
final class PayrollAdjustment extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'payroll_adjustments';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'type',
        'nature',
        'description',
        'amount_centavos',
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
}
