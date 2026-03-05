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
 * Payroll Detail — one row per employee per payroll run.
 * All monetary amounts are stored as integer centavos.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property int $basic_monthly_rate_centavos
 * @property int $daily_rate_centavos
 * @property int $hourly_rate_centavos
 * @property int $working_days_in_period
 * @property string $pay_basis
 * @property int $days_worked
 * @property int $days_absent
 * @property int $days_late_minutes
 * @property int $undertime_minutes
 * @property int $overtime_regular_minutes
 * @property int $overtime_rest_day_minutes
 * @property int $overtime_holiday_minutes
 * @property int $night_diff_minutes
 * @property int $regular_holiday_days
 * @property int $special_holiday_days
 * @property int $leave_days_paid
 * @property int $leave_days_unpaid
 * @property int $basic_pay_centavos
 * @property int $overtime_pay_centavos
 * @property int $holiday_pay_centavos
 * @property int $night_diff_pay_centavos
 * @property int $gross_pay_centavos
 * @property int $sss_ee_centavos
 * @property int $sss_er_centavos
 * @property int $philhealth_ee_centavos
 * @property int $philhealth_er_centavos
 * @property int $pagibig_ee_centavos
 * @property int $pagibig_er_centavos
 * @property int $withholding_tax_centavos
 * @property int $loan_deductions_centavos
 * @property array|null $loan_deduction_detail
 * @property int $other_deductions_centavos
 * @property int $total_deductions_centavos
 * @property int $net_pay_centavos
 * @property bool $is_below_min_wage
 * @property bool $has_deferred_deductions
 * @property int $ytd_taxable_income_centavos
 * @property int $ytd_tax_withheld_centavos
 * @property string $status computed|approved|reversed
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read PayrollRun $payrollRun
 */
final class PayrollDetail extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'payroll_details';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_monthly_rate_centavos',
        'daily_rate_centavos',
        'hourly_rate_centavos',
        'working_days_in_period',
        'pay_basis',
        'days_worked',
        'days_absent',
        'days_late_minutes',
        'undertime_minutes',
        'overtime_regular_minutes',
        'overtime_rest_day_minutes',
        'overtime_holiday_minutes',
        'night_diff_minutes',
        'regular_holiday_days',
        'special_holiday_days',
        'leave_days_paid',
        'leave_days_unpaid',
        'basic_pay_centavos',
        'overtime_pay_centavos',
        'holiday_pay_centavos',
        'night_diff_pay_centavos',
        'gross_pay_centavos',
        'sss_ee_centavos',
        'sss_er_centavos',
        'philhealth_ee_centavos',
        'philhealth_er_centavos',
        'pagibig_ee_centavos',
        'pagibig_er_centavos',
        'withholding_tax_centavos',
        'loan_deductions_centavos',
        'loan_deduction_detail',
        'other_deductions_centavos',
        'total_deductions_centavos',
        'net_pay_centavos',
        'is_below_min_wage',
        'has_deferred_deductions',
        'ytd_taxable_income_centavos',
        'ytd_tax_withheld_centavos',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'loan_deduction_detail' => 'array',
            'is_below_min_wage' => 'boolean',
            'has_deferred_deductions' => 'boolean',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Net pay as a float in pesos for display. */
    public function netPayPhp(): float
    {
        return $this->net_pay_centavos / 100;
    }

    /** Gross pay as a float in pesos for display. */
    public function grossPayPhp(): float
    {
        return $this->gross_pay_centavos / 100;
    }
}
