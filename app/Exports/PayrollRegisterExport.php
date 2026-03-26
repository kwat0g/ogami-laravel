<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Payroll Register Excel Export.
 *
 * One row per employee. All monetary values displayed as peso amounts
 * (integer centavos ÷ 100), two decimal places.
 */
final class PayrollRegisterExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private readonly PayrollRun $run) {}

    public function title(): string
    {
        return 'Payroll Register';
    }

    public function collection(): Collection
    {
        return $this->run
            ->details()
            ->with([
                'employee:id,employee_code,first_name,last_name,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,name',
            ])
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Emp Code',
            'Last Name',
            'First Name',
            'Department',
            'Position',
            'Pay Basis',
            'Days Worked',
            'Days Absent',
            'Days Late (min)',
            'OT Minutes',
            'Basic Pay',
            'OT Pay',
            'Holiday Pay',
            'Night Diff Pay',
            'Gross Pay',
            'SSS (EE)',
            'PhilHealth (EE)',
            'Pag-IBIG (EE)',
            'Withholding Tax',
            'Loan Deductions',
            'Other Deductions',
            'Total Deductions',
            'Net Pay',
        ];
    }

    /** @param PayrollDetail $detail */
    public function map($detail): array
    {
        $emp = $detail->employee;

        return [
            $emp?->employee_code ?? '',
            $emp?->last_name ?? '',
            $emp?->first_name ?? '',
            $emp?->department?->name ?? '',
            $emp?->position?->title ?? '',
            ucfirst($detail->pay_basis),
            $detail->days_worked,
            $detail->days_absent,
            $detail->days_late_minutes,
            $detail->overtime_regular_minutes + $detail->overtime_rest_day_minutes + $detail->overtime_holiday_minutes,
            $this->centavos($detail->basic_pay_centavos),
            $this->centavos($detail->overtime_pay_centavos),
            $this->centavos($detail->holiday_pay_centavos),
            $this->centavos($detail->night_diff_pay_centavos),
            $this->centavos($detail->gross_pay_centavos),
            $this->centavos($detail->sss_ee_centavos),
            $this->centavos($detail->philhealth_ee_centavos),
            $this->centavos($detail->pagibig_ee_centavos),
            $this->centavos($detail->withholding_tax_centavos),
            $this->centavos($detail->loan_deductions_centavos),
            $this->centavos($detail->other_deductions_centavos),
            $this->centavos($detail->total_deductions_centavos),
            $this->centavos($detail->net_pay_centavos),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row: bold + blue background, white text
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    private function centavos(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
