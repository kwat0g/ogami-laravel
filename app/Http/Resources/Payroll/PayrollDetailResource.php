<?php

declare(strict_types=1);

namespace App\Http\Resources\Payroll;

use App\Domains\Payroll\Models\PayrollDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollDetail */
final class PayrollDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,

            // ── Snapshot ──────────────────────────────────────────────────
            'basic_monthly_rate_centavos' => $this->basic_monthly_rate_centavos,
            'daily_rate_centavos' => $this->daily_rate_centavos,
            'hourly_rate_centavos' => $this->hourly_rate_centavos,
            'working_days_in_period' => $this->working_days_in_period,
            'pay_basis' => $this->pay_basis,

            // ── Attendance ────────────────────────────────────────────────
            'days_worked' => $this->days_worked,
            'days_absent' => $this->days_absent,
            'days_late_minutes' => $this->days_late_minutes,
            'undertime_minutes' => $this->undertime_minutes,
            'overtime_regular_minutes' => $this->overtime_regular_minutes,
            'overtime_rest_day_minutes' => $this->overtime_rest_day_minutes,
            'overtime_holiday_minutes' => $this->overtime_holiday_minutes,
            'night_diff_minutes' => $this->night_diff_minutes,
            'regular_holiday_days' => $this->regular_holiday_days,
            'special_holiday_days' => $this->special_holiday_days,
            'leave_days_paid' => $this->leave_days_paid,
            'leave_days_unpaid' => $this->leave_days_unpaid,

            // ── Earnings ──────────────────────────────────────────────────
            'basic_pay_centavos' => $this->basic_pay_centavos,
            'overtime_pay_centavos' => $this->overtime_pay_centavos,
            'holiday_pay_centavos' => $this->holiday_pay_centavos,
            'night_diff_pay_centavos' => $this->night_diff_pay_centavos,
            'gross_pay_centavos' => $this->gross_pay_centavos,

            // ── Gov deductions ────────────────────────────────────────────
            'sss_ee_centavos' => $this->sss_ee_centavos,
            'sss_er_centavos' => $this->sss_er_centavos,
            'philhealth_ee_centavos' => $this->philhealth_ee_centavos,
            'philhealth_er_centavos' => $this->philhealth_er_centavos,
            'pagibig_ee_centavos' => $this->pagibig_ee_centavos,
            'pagibig_er_centavos' => $this->pagibig_er_centavos,
            'withholding_tax_centavos' => $this->withholding_tax_centavos,

            // ── Loan deductions ───────────────────────────────────────────
            'loan_deductions_centavos' => $this->loan_deductions_centavos,
            'loan_deduction_detail' => $this->loan_deduction_detail,

            // ── Other deductions & net pay ────────────────────────────────
            'other_deductions_centavos' => $this->other_deductions_centavos,
            'total_deductions_centavos' => $this->total_deductions_centavos,
            'net_pay_centavos' => $this->net_pay_centavos,
            'is_below_min_wage' => $this->is_below_min_wage,
            'has_deferred_deductions' => $this->has_deferred_deductions,

            // ── YTD ───────────────────────────────────────────────────────
            'ytd_taxable_income_centavos' => $this->ytd_taxable_income_centavos,
            'ytd_tax_withheld_centavos' => $this->ytd_tax_withheld_centavos,

            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // ── Related ───────────────────────────────────────────────────
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
            ] : null),
            'payroll_run' => $this->payrollRun ? [
                'id' => $this->payrollRun->id,
                'reference_no' => $this->payrollRun->reference_no,
                'pay_period_label' => $this->payrollRun->pay_period_label,
                'pay_date' => $this->payrollRun->pay_date,
                'run_type' => $this->payrollRun->run_type,
                'status' => $this->payrollRun->status,
            ] : null,
        ];
    }
}
