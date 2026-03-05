<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Government Report Data Service.
 *
 * Aggregates frozen payroll_details rows into the shapes required by
 * BIR, SSS, PhilHealth, and Pag-IBIG government report forms.
 *
 * Design principles:
 *  — Never re-derives contribution amounts from rate tables; uses columns
 *    frozen at computation time (sss_ee/er, philhealth_ee/er, pagibig_ee/er).
 *  — Decrypts government IDs (TIN, SSS No., etc.) inside this service so
 *    raw encrypted strings never leak into report layers.
 *  — All monetary outputs are integer centavos. Report layers divide by 100.
 */
final class GovReportDataService implements ServiceContract
{
    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Aggregate payroll_details for a given month across ALL completed runs
     * whose pay_date falls within that month.
     *
     * Returns one stdClass per employee with summed contribution columns.
     * Employees with zero gross pay in the period are excluded.
     *
     * @return Collection<int, object>
     */
    public function aggregateMonthly(int $year, int $month): Collection
    {
        $rows = DB::table('payroll_details as pd')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pd.payroll_run_id')
            ->join('employees as e', 'e.id', '=', 'pd.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('positions as p', 'p.id', '=', 'e.position_id')
            ->where('pr.status', 'completed')
            ->whereYear('pr.pay_date', $year)
            ->whereMonth('pr.pay_date', $month)
            ->select([
                'e.id as employee_id',
                'e.employee_code',
                'e.first_name',
                'e.last_name',
                'e.bir_status',
                'e.tin_encrypted',
                'e.sss_no_encrypted',
                'e.philhealth_no_encrypted',
                'e.pagibig_no_encrypted',
                'd.name as department_name',
                'p.title as position_title',
                DB::raw('SUM(pd.gross_pay_centavos) as gross_pay_centavos'),
                DB::raw('SUM(pd.sss_ee_centavos) as sss_ee_centavos'),
                DB::raw('SUM(pd.sss_er_centavos) as sss_er_centavos'),
                DB::raw('SUM(pd.philhealth_ee_centavos) as philhealth_ee_centavos'),
                DB::raw('SUM(pd.philhealth_er_centavos) as philhealth_er_centavos'),
                DB::raw('SUM(pd.pagibig_ee_centavos) as pagibig_ee_centavos'),
                DB::raw('SUM(pd.pagibig_er_centavos) as pagibig_er_centavos'),
                DB::raw('SUM(pd.withholding_tax_centavos) as withholding_tax_centavos'),
                DB::raw('SUM(pd.net_pay_centavos) as net_pay_centavos'),
                DB::raw('SUM(pd.basic_pay_centavos) as basic_pay_centavos'),
            ])
            ->groupBy([
                'e.id', 'e.employee_code', 'e.first_name', 'e.last_name', 'e.bir_status',
                'e.tin_encrypted', 'e.sss_no_encrypted', 'e.philhealth_no_encrypted',
                'e.pagibig_no_encrypted', 'd.name', 'p.title',
            ])
            ->having(DB::raw('SUM(pd.gross_pay_centavos)'), '>', 0)
            ->orderBy('e.last_name')
            ->orderBy('e.first_name')
            ->get();

        return $rows->map(fn ($row) => $this->decryptIds($row));
    }

    /**
     * Aggregate annual payroll data per employee using the YTD accumulators
     * from the LAST completed payroll run of the year.
     *
     * YTD accumulators on the last run already reflect the full-year totals
     * (they accumulate across all 24 semi-monthly periods).
     *
     * @return Collection<int, object>
     */
    public function aggregateAnnual(int $year): Collection
    {
        // Subquery: get the last completed run's detail per employee for the year
        $lastRunIds = DB::table('payroll_details as pd')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pd.payroll_run_id')
            ->where('pr.status', 'completed')
            ->where('pr.run_type', 'regular')
            ->whereYear('pr.pay_date', $year)
            ->select('pd.employee_id', DB::raw('MAX(pr.pay_date) as last_pay_date'))
            ->groupBy('pd.employee_id');

        $rows = DB::table('payroll_details as pd')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pd.payroll_run_id')
            ->joinSub($lastRunIds, 'last', function ($join) {
                $join->on('last.employee_id', '=', 'pd.employee_id')
                    ->on('pr.pay_date', '=', 'last.last_pay_date');
            })
            ->join('employees as e', 'e.id', '=', 'pd.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('positions as p', 'p.id', '=', 'e.position_id')
            ->where('pr.status', 'completed')
            ->whereYear('pr.pay_date', $year)
            ->select([
                'e.id as employee_id',
                'e.employee_code',
                'e.first_name',
                'e.last_name',
                'e.bir_status',
                'e.tin_encrypted',
                'e.sss_no_encrypted',
                'e.philhealth_no_encrypted',
                'e.pagibig_no_encrypted',
                'd.name as department_name',
                'p.title as position_title',
                'pd.ytd_taxable_income_centavos',
                'pd.ytd_tax_withheld_centavos',
                // Full-year gross = basic_monthly * 12 approximated via annual sum
                // sum up all completed detail rows for the year for aggregate totals
                DB::raw('(
                    SELECT COALESCE(SUM(pd2.gross_pay_centavos), 0)
                    FROM payroll_details pd2
                    JOIN payroll_runs pr2 ON pr2.id = pd2.payroll_run_id
                    WHERE pd2.employee_id = e.id
                      AND pr2.status = \'completed\'
                      AND pr2.run_type = \'regular\'
                      AND EXTRACT(YEAR FROM pr2.pay_date) = '.$year.'
                ) as annual_gross_centavos'),
                DB::raw('(
                    SELECT COALESCE(SUM(pd2.sss_ee_centavos), 0)
                    FROM payroll_details pd2
                    JOIN payroll_runs pr2 ON pr2.id = pd2.payroll_run_id
                    WHERE pd2.employee_id = e.id
                      AND pr2.status = \'completed\'
                      AND pr2.run_type = \'regular\'
                      AND EXTRACT(YEAR FROM pr2.pay_date) = '.$year.'
                ) as annual_sss_ee_centavos'),
                DB::raw('(
                    SELECT COALESCE(SUM(pd2.philhealth_ee_centavos), 0)
                    FROM payroll_details pd2
                    JOIN payroll_runs pr2 ON pr2.id = pd2.payroll_run_id
                    WHERE pd2.employee_id = e.id
                      AND pr2.status = \'completed\'
                      AND pr2.run_type = \'regular\'
                      AND EXTRACT(YEAR FROM pr2.pay_date) = '.$year.'
                ) as annual_philhealth_ee_centavos'),
                DB::raw('(
                    SELECT COALESCE(SUM(pd2.pagibig_ee_centavos), 0)
                    FROM payroll_details pd2
                    JOIN payroll_runs pr2 ON pr2.id = pd2.payroll_run_id
                    WHERE pd2.employee_id = e.id
                      AND pr2.status = \'completed\'
                      AND pr2.run_type = \'regular\'
                      AND EXTRACT(YEAR FROM pr2.pay_date) = '.$year.'
                ) as annual_pagibig_ee_centavos'),
            ])
            ->orderBy('e.last_name')
            ->orderBy('e.first_name')
            ->get();

        return $rows->map(fn ($row) => $this->decryptIds($row));
    }

    /**
     * Read company-level settings needed by all government forms.
     *
     * @return array{
     *   company_name: string,
     *   company_address: string,
     *   company_tin: string,
     *   rdo_code: string,
     * }
     */
    public function companySettings(): array
    {
        $rows = DB::table('system_settings')
            ->whereIn('key', ['company.name', 'company.address', 'company.tin', 'bir.rdo_code'])
            ->pluck('value', 'key')
            ->map(fn ($v) => json_decode((string) $v, true))
            ->toArray();

        return [
            'company_name' => (string) ($rows['company.name'] ?? ''),
            'company_address' => (string) ($rows['company.address'] ?? ''),
            'company_tin' => (string) ($rows['company.tin'] ?? ''),
            'rdo_code' => (string) ($rows['bir.rdo_code'] ?? ''),
        ];
    }

    /**
     * Get 13th-month pay amount for a specific employee in a given year.
     */
    public function get13thMonthCentavos(int $employeeId, int $year): int
    {
        return (int) DB::table('payroll_details as pd')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pd.payroll_run_id')
            ->where('pd.employee_id', $employeeId)
            ->where('pr.run_type', 'thirteenth_month')
            ->where('pr.status', 'completed')
            ->whereYear('pr.pay_date', $year)
            ->value('pd.basic_pay_centavos');
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Decrypt government ID fields on a result row in-place.
     * Replaces *_encrypted columns with plain *_no versions.
     */
    private function decryptIds(object $row): object
    {
        $row->tin = $this->safeDecrypt($row->tin_encrypted ?? null);
        $row->sss_no = $this->safeDecrypt($row->sss_no_encrypted ?? null);
        $row->philhealth_no = $this->safeDecrypt($row->philhealth_no_encrypted ?? null);
        $row->pagibig_no = $this->safeDecrypt($row->pagibig_no_encrypted ?? null);

        unset(
            $row->tin_encrypted,
            $row->sss_no_encrypted,
            $row->philhealth_no_encrypted,
            $row->pagibig_no_encrypted,
        );

        return $row;
    }

    private function safeDecrypt(?string $encrypted): string
    {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }

        try {
            return (string) Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return '';
        }
    }
}
