<?php

declare(strict_types=1);

namespace App\Domains\Tax\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * BIR Form Generator Service — generates structured data matching BIR form fields.
 *
 * Produces data arrays ready to populate BIR forms:
 * - 2550M/2550Q (Monthly/Quarterly VAT)
 * - 2307 (Certificate of Creditable Tax Withheld at Source)
 * - 1601-C (Monthly Remittance of Creditable Income Taxes Withheld)
 * - 2316 (Annual Income Tax for Employees)
 */
final class BirFormGeneratorService implements ServiceContract
{
    /**
     * Generate BIR Form 2550M/2550Q (VAT Return) data.
     *
     * @return array{period: string, output_vat_centavos: int, input_vat_centavos: int, vat_payable_centavos: int, taxable_sales_centavos: int, zero_rated_sales_centavos: int, exempt_sales_centavos: int}
     */
    public function generateVatReturn(int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // Output VAT from customer invoices
        $outputVat = (int) DB::table('vat_ledger')
            ->where('transaction_type', 'output')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('CAST(vat_amount AS bigint)'));

        // Input VAT from vendor invoices
        $inputVat = (int) DB::table('vat_ledger')
            ->where('transaction_type', 'input')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('CAST(vat_amount AS bigint)'));

        // Taxable sales
        $taxableSales = (int) DB::table('vat_ledger')
            ->where('transaction_type', 'output')
            ->where('tax_type', 'vatable')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('CAST(net_amount AS bigint)'));

        $zeroRated = (int) DB::table('vat_ledger')
            ->where('transaction_type', 'output')
            ->where('tax_type', 'zero_rated')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('CAST(net_amount AS bigint)'));

        $exempt = (int) DB::table('vat_ledger')
            ->where('transaction_type', 'output')
            ->where('tax_type', 'exempt')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('CAST(net_amount AS bigint)'));

        return [
            'form' => '2550M',
            'period' => sprintf('%04d-%02d', $year, $month),
            'output_vat_centavos' => $outputVat,
            'input_vat_centavos' => $inputVat,
            'vat_payable_centavos' => max(0, $outputVat - $inputVat),
            'excess_input_vat_centavos' => max(0, $inputVat - $outputVat),
            'taxable_sales_centavos' => $taxableSales,
            'zero_rated_sales_centavos' => $zeroRated,
            'exempt_sales_centavos' => $exempt,
        ];
    }

    /**
     * Generate BIR Form 1601-C data (Monthly Withholding Tax on Compensation).
     *
     * @return array{period: string, total_compensation_centavos: int, tax_withheld_centavos: int, employee_count: int}
     */
    public function generateWithholdingTax(int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $data = DB::table('payroll_details')
            ->join('payroll_runs', 'payroll_details.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.status', 'completed')
            ->whereBetween('payroll_runs.pay_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(DISTINCT payroll_details.employee_id) as employee_count,
                COALESCE(SUM(payroll_details.gross_pay), 0) as total_gross,
                COALESCE(SUM(payroll_details.tax_withheld), 0) as total_tax
            ')
            ->first();

        return [
            'form' => '1601-C',
            'period' => sprintf('%04d-%02d', $year, $month),
            'total_compensation_centavos' => (int) (($data->total_gross ?? 0) * 100),
            'tax_withheld_centavos' => (int) (($data->total_tax ?? 0) * 100),
            'employee_count' => (int) ($data->employee_count ?? 0),
        ];
    }

    /**
     * Generate BIR Form 2307 data for a specific vendor.
     *
     * @return array{vendor_name: string, tin: string|null, total_payments_centavos: int, total_ewt_centavos: int, payments: array}
     */
    public function generateForm2307(int $vendorId, int $quarter, int $year): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $startDate = sprintf('%04d-%02d-01', $year, $startMonth);
        $endDate = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $startMonth + 2)));

        $payments = DB::table('vendor_payments')
            ->join('vendor_invoices', 'vendor_payments.vendor_invoice_id', '=', 'vendor_invoices.id')
            ->join('vendors', 'vendor_payments.vendor_id', '=', 'vendors.id')
            ->where('vendor_payments.vendor_id', $vendorId)
            ->whereBetween('vendor_payments.payment_date', [$startDate, $endDate])
            ->whereNull('vendor_payments.deleted_at')
            ->select(
                'vendors.name as vendor_name',
                'vendors.tin as vendor_tin',
                'vendor_payments.payment_date',
                'vendor_payments.amount',
                'vendor_invoices.ewt_amount',
                'vendor_invoices.invoice_number'
            )
            ->get();

        $vendorName = $payments->first()->vendor_name ?? 'Unknown';
        $vendorTin = $payments->first()->vendor_tin ?? null;

        return [
            'form' => '2307',
            'quarter' => $quarter,
            'year' => $year,
            'vendor_name' => $vendorName,
            'tin' => $vendorTin,
            'total_payments_centavos' => (int) ($payments->sum('amount') * 100),
            'total_ewt_centavos' => (int) ($payments->sum('ewt_amount') * 100),
            'payment_details' => $payments->map(fn ($p) => [
                'date' => $p->payment_date,
                'invoice_number' => $p->invoice_number,
                'amount_centavos' => (int) ($p->amount * 100),
                'ewt_centavos' => (int) (($p->ewt_amount ?? 0) * 100),
            ])->toArray(),
        ];
    }
}
