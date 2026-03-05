<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domains\Payroll\Services\GovReportDataService;
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
 * Pag-IBIG (HDMF) Monthly Contribution Report Excel Export.
 *
 * Columns per HDMF prescribed format:
 * Pag-IBIG MID No., Employee Name, EE Contribution, ER Contribution, Total.
 * One row per employee. Monetary values in pesos, 2 decimal places.
 */
final class PagIbigMonthlyExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private Collection $employees;

    private int $seq = 0;

    public function __construct(
        private readonly GovReportDataService $dataService,
        private readonly int $year,
        private readonly int $month,
    ) {
        $this->employees = $this->dataService->aggregateMonthly($this->year, $this->month);
    }

    public function title(): string
    {
        return 'Pag-IBIG Monthly';
    }

    public function collection(): Collection
    {
        return $this->employees;
    }

    public function headings(): array
    {
        return [
            'Seq No.',
            'Pag-IBIG MID No.',
            'Last Name',
            'First Name',
            'Employee Code',
            'EE Contribution (PHP)',
            'ER Contribution (PHP)',
            'Total Contribution (PHP)',
        ];
    }

    /** @param object $emp */
    public function map($emp): array
    {
        $this->seq++;

        // Monthly totals: per-period values × 2 (two semi-monthly cutoffs per month)
        $eeMonthly = $emp->pagibig_ee_centavos;
        $erMonthly = $emp->pagibig_er_centavos;
        $total = $eeMonthly + $erMonthly;

        return [
            $this->seq,
            $emp->pagibig_no ?: '',
            $emp->last_name,
            $emp->first_name,
            $emp->employee_code,
            $this->pesos($eeMonthly),
            $this->pesos($erMonthly),
            $this->pesos($total),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    private function pesos(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
