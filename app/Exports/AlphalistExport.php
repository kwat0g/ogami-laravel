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
 * BIR Alphalist of Employees — Annual Excel Export.
 *
 * BIR-prescribed columns matching the alphalist submission format.
 * One row per employee. Monetary values in pesos (2 decimal places).
 */
final class AlphalistExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private Collection $employees;

    private array $settings;

    private int $seq = 0;

    public function __construct(
        private readonly GovReportDataService $dataService,
        private readonly int $year,
    ) {
        $this->employees = $this->dataService->aggregateAnnual($this->year);
        $this->settings = $this->dataService->companySettings();
    }

    public function title(): string
    {
        return "Alphalist {$this->year}";
    }

    public function collection(): Collection
    {
        return $this->employees;
    }

    public function headings(): array
    {
        return [
            'Seq No.',
            'TIN',
            'Last Name',
            'First Name',
            'BIR Status',
            'Department',
            'Position',
            'Annual Gross Compensation (PHP)',
            'Non-Taxable / Exempt (PHP)',
            'Net Taxable Compensation (PHP)',
            'SSS Contributions (PHP)',
            'PhilHealth Contributions (PHP)',
            'Pag-IBIG Contributions (PHP)',
            'Tax Withheld for the Year (PHP)',
        ];
    }

    /** @param object $emp */
    public function map($emp): array
    {
        $this->seq++;

        $nonTaxable = max(0, $emp->annual_gross_centavos - $emp->ytd_taxable_income_centavos);

        return [
            $this->seq,
            $emp->tin ?: '',
            $emp->last_name,
            $emp->first_name,
            $emp->bir_status,
            $emp->department_name ?? '',
            $emp->position_title ?? '',
            $this->pesos($emp->annual_gross_centavos),
            $this->pesos($nonTaxable),
            $this->pesos($emp->ytd_taxable_income_centavos),
            $this->pesos($emp->annual_sss_ee_centavos),
            $this->pesos($emp->annual_philhealth_ee_centavos),
            $this->pesos($emp->annual_pagibig_ee_centavos),
            $this->pesos($emp->ytd_tax_withheld_centavos),
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
