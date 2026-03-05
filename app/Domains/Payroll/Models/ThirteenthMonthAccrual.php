<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Records the basic salary earned per employee per month for 13th month calculation.
 *
 * 13TH-001: Created when a regular payroll run is posted.
 * 13TH-002: 13th month pay = sum(accrual_amount_centavos for year) / 12.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $year
 * @property int $month
 * @property int $basic_salary_earned_centavos
 * @property int $accrual_amount_centavos
 * @property int|null $payroll_run_id
 */
final class ThirteenthMonthAccrual extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'thirteenth_month_accruals';

    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'basic_salary_earned_centavos',
        'accrual_amount_centavos',
        'payroll_run_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
