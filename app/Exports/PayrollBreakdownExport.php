<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domains\Payroll\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Comprehensive payroll breakdown export for HR and Accounting Managers.
 * Includes full attendance, overtime, leave, tax, and deduction details.
 */
class PayrollBreakdownExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private readonly PayrollRun $payrollRun) {}

    public function collection()
    {
        return $this->payrollRun->details()
            ->with('employee.department', 'employee.position')
            ->get()
            ->sortBy('employee.last_name');
    }

    public function title(): string
    {
        return 'Full Breakdown';
    }

    public function headings(): array
    {
        return [
            // Employee Info
            'Employee Code',
            'Last Name',
            'First Name',
            'Department',
            'Position',
            'Pay Basis',

            // Rate Info
            'Monthly Rate',
            'Daily Rate',
            'Hourly Rate',

            // Attendance
            'Working Days in Period',
            'Days Worked',
            'Days Absent',
            'Late (Minutes)',
            'Undertime (Minutes)',

            // Overtime
            'OT Regular (Minutes)',
            'OT Rest Day (Minutes)',
            'OT Holiday (Minutes)',
            'Night Diff (Minutes)',

            // Leave
            'Leave Days (Paid)',
            'Leave Days (Unpaid)',

            // Holidays
            'Regular Holiday Days',
            'Special Holiday Days',

            // Earnings
            'Basic Pay',
            'Overtime Pay',
            'Holiday Pay',
            'Night Differential',
            'Gross Pay',

            // Employee Contributions
            'SSS (EE)',
            'PhilHealth (EE)',
            'Pag-IBIG (EE)',
            'Total EE Contributions',

            // Employer Contributions
            'SSS (ER)',
            'PhilHealth (ER)',
            'Pag-IBIG (ER)',
            'Total ER Contributions',

            // Deductions
            'Withholding Tax',
            'Loan Deductions',
            'Other Deductions',
            'Total Deductions',

            // Net Pay
            'Net Pay',

            // YTD Info
            'YTD Taxable Income',
            'YTD Tax Withheld',

            // Bank Info
            'Bank Name',
            'Bank Account',

            // Flags
            'Below Min Wage?',
            'Deferred Deductions?',
            'Status',
            'Notes',
        ];
    }

    public function map($detail): array
    {
        $employee = $detail->employee;

        // Calculate total contributions
        $totalEe = ($detail->sss_ee_centavos ?? 0) + ($detail->philhealth_ee_centavos ?? 0) + ($detail->pagibig_ee_centavos ?? 0);
        $totalEr = ($detail->sss_er_centavos ?? 0) + ($detail->philhealth_er_centavos ?? 0) + ($detail->pagibig_er_centavos ?? 0);

        return [
            // Employee Info
            $employee?->employee_code ?? 'N/A',
            $employee?->last_name ?? 'N/A',
            $employee?->first_name ?? 'N/A',
            $employee?->department?->name ?? 'N/A',
            $employee?->position?->title ?? 'N/A',
            $detail->pay_basis ?? 'monthly',

            // Rate Info
            $this->formatAmount($detail->basic_monthly_rate_centavos),
            $this->formatAmount($detail->daily_rate_centavos),
            $this->formatAmount($detail->hourly_rate_centavos),

            // Attendance
            $detail->working_days_in_period ?? 0,
            $detail->days_worked ?? 0,
            $detail->days_absent ?? 0,
            $detail->days_late_minutes ?? 0,
            $detail->undertime_minutes ?? 0,

            // Overtime
            $detail->overtime_regular_minutes ?? 0,
            $detail->overtime_rest_day_minutes ?? 0,
            $detail->overtime_holiday_minutes ?? 0,
            $detail->night_diff_minutes ?? 0,

            // Leave
            $detail->leave_days_paid ?? 0,
            $detail->leave_days_unpaid ?? 0,

            // Holidays
            $detail->regular_holiday_days ?? 0,
            $detail->special_holiday_days ?? 0,

            // Earnings
            $this->formatAmount($detail->basic_pay_centavos),
            $this->formatAmount($detail->overtime_pay_centavos),
            $this->formatAmount($detail->holiday_pay_centavos),
            $this->formatAmount($detail->night_diff_pay_centavos),
            $this->formatAmount($detail->gross_pay_centavos),

            // Employee Contributions
            $this->formatAmount($detail->sss_ee_centavos),
            $this->formatAmount($detail->philhealth_ee_centavos),
            $this->formatAmount($detail->pagibig_ee_centavos),
            $this->formatAmount($totalEe),

            // Employer Contributions
            $this->formatAmount($detail->sss_er_centavos),
            $this->formatAmount($detail->philhealth_er_centavos),
            $this->formatAmount($detail->pagibig_er_centavos),
            $this->formatAmount($totalEr),

            // Deductions
            $this->formatAmount($detail->withholding_tax_centavos),
            $this->formatAmount($detail->loan_deductions_centavos),
            $this->formatAmount($detail->other_deductions_centavos),
            $this->formatAmount($detail->total_deductions_centavos),

            // Net Pay
            $this->formatAmount($detail->net_pay_centavos),

            // YTD Info
            $this->formatAmount($detail->ytd_taxable_income_centavos),
            $this->formatAmount($detail->ytd_tax_withheld_centavos),

            // Bank Info
            $employee?->bank_name ?? 'N/A',
            $this->maskAccount($employee?->bank_account_no),

            // Flags
            $detail->is_below_min_wage ? 'YES' : 'NO',
            $detail->has_deferred_deductions ? 'YES' : 'NO',
            $detail->status ?? 'N/A',
            $detail->notes ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header row styling
        $sheet->getStyle('A1:AJ1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']],
        ]);

        // Auto-fit columns
        foreach (range('A', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        foreach (['AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format number columns (monetary amounts)
        $sheet->getStyle('G:I')->getNumberFormat()->setFormatCode('#,##0.00'); // Rates
        $sheet->getStyle('AA:AN')->getNumberFormat()->setFormatCode('#,##0.00'); // Earnings, Contributions, Deductions
        $sheet->getStyle('AO:AP')->getNumberFormat()->setFormatCode('#,##0.00'); // Net Pay, YTD

        // Center align numeric columns
        $sheet->getStyle('J:Z')->getAlignment()->setHorizontal('center');

        // Freeze header row
        $sheet->freezePane('A2');

        return [];
    }

    private function formatAmount(?int $amount): string
    {
        if ($amount === null) {
            return '0.00';
        }

        return number_format($amount / 100, 2);
    }

    private function maskAccount(?string $account): string
    {
        if (empty($account)) {
            return 'N/A';
        }
        $len = strlen($account);
        if ($len <= 4) {
            return $account;
        }

        return str_repeat('*', $len - 4).substr($account, -4);
    }
}
