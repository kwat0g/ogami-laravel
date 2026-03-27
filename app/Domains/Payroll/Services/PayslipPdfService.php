<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * Payslip PDF Service — generates individual payslip data for PDF rendering.
 *
 * Returns structured payslip data that can be rendered by a PDF library
 * (e.g., DomPDF, Snappy) or returned as JSON for frontend PDF generation.
 */
final class PayslipPdfService implements ServiceContract
{
    /**
     * Generate payslip data for a single employee in a payroll run.
     *
     * @return array{employee: array, earnings: array, deductions: array, summary: array, company: array}|null
     */
    public function generatePayslipData(int $payrollRunId, int $employeeId): ?array
    {
        $detail = DB::table('payroll_details')
            ->where('payroll_run_id', $payrollRunId)
            ->where('employee_id', $employeeId)
            ->first();

        if ($detail === null) {
            return null;
        }

        $run = DB::table('payroll_runs')->find($payrollRunId);
        $employee = DB::table('employees')->find($employeeId);

        if ($employee === null || $run === null) {
            return null;
        }

        // Build earnings breakdown
        $earnings = [
            ['label' => 'Basic Pay', 'amount_centavos' => (int) (($detail->basic_pay ?? 0) * 100)],
        ];

        if (($detail->overtime_pay ?? 0) > 0) {
            $earnings[] = ['label' => 'Overtime Pay', 'amount_centavos' => (int) ($detail->overtime_pay * 100)];
        }
        if (($detail->holiday_pay ?? 0) > 0) {
            $earnings[] = ['label' => 'Holiday Pay', 'amount_centavos' => (int) ($detail->holiday_pay * 100)];
        }
        if (($detail->night_diff_pay ?? 0) > 0) {
            $earnings[] = ['label' => 'Night Differential', 'amount_centavos' => (int) ($detail->night_diff_pay * 100)];
        }
        if (($detail->allowances ?? 0) > 0) {
            $earnings[] = ['label' => 'Allowances', 'amount_centavos' => (int) ($detail->allowances * 100)];
        }

        // Build deductions breakdown
        $deductions = [];
        if (($detail->sss_ee ?? 0) > 0) {
            $deductions[] = ['label' => 'SSS', 'amount_centavos' => (int) ($detail->sss_ee * 100)];
        }
        if (($detail->philhealth_ee ?? 0) > 0) {
            $deductions[] = ['label' => 'PhilHealth', 'amount_centavos' => (int) ($detail->philhealth_ee * 100)];
        }
        if (($detail->pagibig_ee ?? 0) > 0) {
            $deductions[] = ['label' => 'Pag-IBIG', 'amount_centavos' => (int) ($detail->pagibig_ee * 100)];
        }
        if (($detail->tax_withheld ?? 0) > 0) {
            $deductions[] = ['label' => 'Withholding Tax', 'amount_centavos' => (int) ($detail->tax_withheld * 100)];
        }

        // Loan deductions
        $loanDeductions = DB::table('loan_amortization_schedules')
            ->join('loans', 'loan_amortization_schedules.loan_id', '=', 'loans.id')
            ->join('loan_types', 'loans.loan_type_id', '=', 'loan_types.id')
            ->where('loan_amortization_schedules.payroll_detail_id', $detail->id)
            ->select('loan_types.name', 'loan_amortization_schedules.amount')
            ->get();

        foreach ($loanDeductions as $loan) {
            $deductions[] = [
                'label' => "Loan: {$loan->name}",
                'amount_centavos' => (int) ($loan->amount * 100),
            ];
        }

        $grossPay = (int) (($detail->gross_pay ?? 0) * 100);
        $totalDeductions = (int) (($detail->total_deductions ?? 0) * 100);
        $netPay = (int) (($detail->net_pay ?? 0) * 100);

        return [
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code ?? '',
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'position' => $employee->position ?? '',
                'department' => $employee->department_id ? DB::table('departments')->find($employee->department_id)?->name : '',
            ],
            'payroll_run' => [
                'id' => $run->id,
                'period_start' => $run->period_start ?? '',
                'period_end' => $run->period_end ?? '',
                'pay_date' => $run->pay_date ?? '',
                'run_type' => $run->run_type ?? 'regular',
            ],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'summary' => [
                'gross_pay_centavos' => $grossPay,
                'total_deductions_centavos' => $totalDeductions,
                'net_pay_centavos' => $netPay,
            ],
        ];
    }

    /**
     * Generate payslip data for all employees in a payroll run.
     *
     * @return array<int, array>
     */
    public function generateBatchPayslips(int $payrollRunId): array
    {
        $employeeIds = DB::table('payroll_details')
            ->where('payroll_run_id', $payrollRunId)
            ->pluck('employee_id');

        $payslips = [];
        foreach ($employeeIds as $employeeId) {
            $data = $this->generatePayslipData($payrollRunId, $employeeId);
            if ($data !== null) {
                $payslips[] = $data;
            }
        }

        return $payslips;
    }
}
