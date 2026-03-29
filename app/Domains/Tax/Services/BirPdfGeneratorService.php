<?php

declare(strict_types=1);

namespace App\Domains\Tax\Services;

use App\Domains\Tax\Models\BirFiling;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * BIR Form PDF Generator Service — Items 28 & 29.
 *
 * Generates printable BIR-format data for Philippine tax forms.
 * Uses the existing BirFormGeneratorService for data, then formats
 * it into a structure suitable for PDF rendering via DomPDF.
 *
 * Forms supported:
 *   - 1601C: Monthly WHT on compensation
 *   - 0619E: Monthly EWT remittance
 *   - 2550M: Monthly VAT declaration
 *   - 2316: Annual employee alphalist (per employee)
 *   - 2307: Quarterly EWT certificates (per vendor)
 *
 * Alphalist Generation (Item 29):
 *   - 2316: per-employee annual compensation summary
 *   - 2307: per-vendor EWT withholding certificates
 */
final class BirPdfGeneratorService implements ServiceContract
{
    public function __construct(
        private readonly BirFormGeneratorService $formGenerator,
    ) {}

    /**
     * Generate PDF data for a BIR filing.
     *
     * @return array{form_type: string, period: string, data: array, pdf_view: string}
     */
    public function generateFormData(BirFiling $filing): array
    {
        $formData = $this->formGenerator->generate($filing);

        return [
            'form_type' => $filing->form_type,
            'period' => $filing->tax_period,
            'tin' => $this->getCompanyTin(),
            'company_name' => $this->getCompanyName(),
            'rdo_code' => $this->getSystemSetting('tax.rdo_code', ''),
            'address' => $this->getSystemSetting('tax.company_address', ''),
            'data' => $formData,
            'pdf_view' => "tax.forms.{$filing->form_type}",
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate 2316 Alphalist — per-employee annual compensation summary.
     *
     * @return array{year: int, employees: list<array{employee_name: string, tin: string, compensation_centavos: int, tax_withheld_centavos: int, sss_centavos: int, philhealth_centavos: int, pagibig_centavos: int, thirteenth_month_centavos: int}>, summary: array}
     */
    public function alphalist2316(int $year): array
    {
        $employees = DB::table('payroll_details as pd')
            ->join('payroll_runs as pr', 'pd.payroll_run_id', '=', 'pr.id')
            ->join('employees as e', 'pd.employee_id', '=', 'e.id')
            ->where('pr.status', 'PUBLISHED')
            ->whereYear('pr.cutoff_start', $year)
            ->select(
                'pd.employee_id',
                DB::raw("CONCAT(e.last_name, ', ', e.first_name) as employee_name"),
                'e.tin_encrypted as tin',
                DB::raw('SUM(pd.basic_pay_centavos + pd.overtime_pay_centavos + pd.holiday_pay_centavos + pd.night_diff_pay_centavos) as total_compensation_centavos'),
                DB::raw('SUM(pd.gross_pay_centavos) as gross_pay_centavos'),
                DB::raw('SUM(pd.withholding_tax_centavos) as tax_withheld_centavos'),
                DB::raw('SUM(pd.sss_ee_centavos) as sss_amount_centavos'),
                DB::raw('SUM(pd.philhealth_ee_centavos) as philhealth_amount_centavos'),
                DB::raw('SUM(pd.pagibig_ee_centavos) as pagibig_amount_centavos'),
                DB::raw('SUM(COALESCE(pd.thirteenth_month_centavos, 0)) as thirteenth_month_centavos'),
                DB::raw('SUM(pd.net_pay_centavos) as net_pay_centavos'),
            )
            ->groupBy('pd.employee_id', 'e.last_name', 'e.first_name', 'e.tin_encrypted')
            ->orderBy('e.last_name')
            ->get()
            ->map(fn ($row) => [
                'employee_id' => $row->employee_id,
                'employee_name' => $row->employee_name,
                'tin' => $row->tin ?? '—',
                'total_compensation_centavos' => (int) $row->total_compensation_centavos,
                'gross_pay_centavos' => (int) $row->gross_pay_centavos,
                'tax_withheld_centavos' => (int) $row->tax_withheld_centavos,
                'sss_centavos' => (int) $row->sss_centavos,
                'philhealth_centavos' => (int) $row->philhealth_centavos,
                'pagibig_centavos' => (int) $row->pagibig_centavos,
                'thirteenth_month_centavos' => (int) $row->thirteenth_month_centavos,
                'net_pay_centavos' => (int) $row->net_pay_centavos,
            ])
            ->toArray();

        return [
            'form_type' => '2316',
            'year' => $year,
            'company_name' => $this->getCompanyName(),
            'company_tin' => $this->getCompanyTin(),
            'employee_count' => count($employees),
            'employees' => $employees,
            'summary' => [
                'total_compensation' => collect($employees)->sum('total_compensation_centavos'),
                'total_tax_withheld' => collect($employees)->sum('tax_withheld_centavos'),
                'total_sss' => collect($employees)->sum('sss_centavos'),
                'total_philhealth' => collect($employees)->sum('philhealth_centavos'),
                'total_pagibig' => collect($employees)->sum('pagibig_centavos'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate 2307 Alphalist — per-vendor EWT certificates.
     *
     * @return array{year: int, quarter: int, vendors: list<array>, summary: array}
     */
    public function alphalist2307(int $year, int $quarter): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;

        $vendors = DB::table('vendor_invoices as vi')
            ->join('vendors as v', 'vi.vendor_id', '=', 'v.id')
            ->whereYear('vi.invoice_date', $year)
            ->whereMonth('vi.invoice_date', '>=', $startMonth)
            ->whereMonth('vi.invoice_date', '<=', $endMonth)
            ->whereIn('vi.status', ['approved', 'partially_paid', 'paid'])
            ->whereNull('vi.deleted_at')
            ->where('vi.ewt_amount', '>', 0)
            ->select(
                'vi.vendor_id',
                'v.name as vendor_name',
                'v.tin as vendor_tin',
                'v.address as vendor_address',
                DB::raw('SUM(vi.net_amount) as total_purchases'),
                DB::raw('SUM(vi.ewt_amount) as total_ewt'),
                DB::raw('COUNT(*) as invoice_count'),
            )
            ->groupBy('vi.vendor_id', 'v.name', 'v.tin', 'v.address')
            ->orderBy('v.name')
            ->get()
            ->map(fn ($row) => [
                'vendor_id' => $row->vendor_id,
                'vendor_name' => $row->vendor_name,
                'vendor_tin' => $row->vendor_tin ?? '—',
                'vendor_address' => $row->vendor_address ?? '—',
                'total_purchases' => round((float) $row->total_purchases, 2),
                'total_ewt' => round((float) $row->total_ewt, 2),
                'invoice_count' => (int) $row->invoice_count,
            ])
            ->toArray();

        return [
            'form_type' => '2307',
            'year' => $year,
            'quarter' => $quarter,
            'company_name' => $this->getCompanyName(),
            'company_tin' => $this->getCompanyTin(),
            'vendor_count' => count($vendors),
            'vendors' => $vendors,
            'summary' => [
                'total_purchases' => collect($vendors)->sum('total_purchases'),
                'total_ewt' => collect($vendors)->sum('total_ewt'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function getCompanyTin(): string
    {
        return $this->getSystemSetting('tax.company_tin', '000-000-000-000');
    }

    private function getCompanyName(): string
    {
        return $this->getSystemSetting('company.name', 'Ogami Manufacturing Corp.');
    }

    private function getSystemSetting(string $key, string $default): string
    {
        return (string) (DB::table('system_settings')->where('key', $key)->value('value') ?? $default);
    }
}
