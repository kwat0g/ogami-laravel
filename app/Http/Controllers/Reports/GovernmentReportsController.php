<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Services\GovReportDataService;
use App\Exports\AlphalistExport;
use App\Exports\PagIbigMonthlyExport;
use App\Exports\PhilHealthRf1Export;
use App\Exports\SssSbr2Export;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Government Report Controller.
 *
 * Generates BIR, SSS, PhilHealth, and Pag-IBIG compliance reports.
 * All endpoints require the authenticated user to be able to view payroll runs.
 *
 * Routes (all GET, authenticated):
 *   /api/v1/reports/bir/1601c?year=&month=
 *   /api/v1/reports/bir/2316?year=&employee_id=
 *   /api/v1/reports/bir/alphalist?year=
 *   /api/v1/reports/sss/sbr2?year=&month=
 *   /api/v1/reports/philhealth/rf1?year=&month=
 *   /api/v1/reports/pagibig/monthly?year=&month=
 */
final class GovernmentReportsController extends Controller
{
    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function __construct(
        private readonly GovReportDataService $dataService,
    ) {}

    /**
     * BIR Form 1601-C — Monthly Remittance Return of Income Taxes Withheld.
     */
    public function form1601c(Request $request): Response
    {
        $this->authorize('viewAny', PayrollRun::class);

        $input = $this->validateYearMonth($request);
        ['year' => $year, 'month' => $month] = $input;

        $employees = $this->dataService->aggregateMonthly($year, $month);
        $settings = $this->dataService->companySettings();

        $totalGrossPay = $employees->sum('gross_pay_centavos');
        $totalTaxWithheld = $employees->sum('withholding_tax_centavos');

        // Taxable income: for monthly report, derive from annual YTD delta would be complex.
        // Use gross_pay as proxy for this period's compensation; the taxable portion is
        // gross_pay minus all gov contributions for the period.
        $totalGovEe = $employees->sum(fn ($e) => $e->sss_ee_centavos + $e->philhealth_ee_centavos + $e->pagibig_ee_centavos
        );
        $totalTaxableIncome = max(0, $totalGrossPay - $totalGovEe);

        // For employee-level taxable, use period gross - period gov EE as approximation
        $employeesWithTaxable = $employees->map(function ($emp) {
            $emp->ytd_taxable_income_centavos = max(
                0,
                $emp->gross_pay_centavos
                    - $emp->sss_ee_centavos
                    - $emp->philhealth_ee_centavos
                    - $emp->pagibig_ee_centavos,
            );

            return $emp;
        });

        $monthLabel = self::MONTH_NAMES[$month].' '.$year;

        $pdf = Pdf::loadView('payroll.form_1601c', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $monthLabel,
            'settings' => $settings,
            'employees' => $employeesWithTaxable,
            'totalEmployees' => $employees->count(),
            'totalGrossPay' => $totalGrossPay,
            'totalTaxableIncome' => $totalTaxableIncome,
            'totalTaxWithheld' => $totalTaxWithheld,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream(sprintf('bir-1601c-%d-%02d.pdf', $year, $month));
    }

    /**
     * BIR Form 2316 — Certificate of Compensation Payment/Tax Withheld (per employee).
     */
    public function form2316(Request $request): Response
    {
        $this->authorize('viewAny', PayrollRun::class);

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ])->validate();

        $year = (int) $validated['year'];
        $employeeId = (int) $validated['employee_id'];

        // Get annual aggregate for this specific employee
        $all = $this->dataService->aggregateAnnual($year);
        $employee = $all->firstWhere('employee_id', $employeeId);

        abort_if($employee === null, 404, 'No payroll data found for this employee in the specified year.');

        $settings = $this->dataService->companySettings();

        // Get 13th month pay from thirteenth_month run for this employee/year
        $thirteenthMonthCentavos = $this->dataService->get13thMonthCentavos($employeeId, $year);

        // taxDueCentavos — provisional: for now use ytd_tax_withheld (the over/under difference
        // will be computed by the December reconciliation step in Sprint 17 golden test).
        $taxDueCentavos = (int) $employee->ytd_tax_withheld_centavos;

        $pdf = Pdf::loadView('payroll.form_2316', [
            'year' => $year,
            'settings' => $settings,
            'employee' => $employee,
            'thirteenthMonthCentavos' => $thirteenthMonthCentavos,
            'taxDueCentavos' => $taxDueCentavos,
        ])->setPaper('a4', 'portrait');

        $code = $employee->employee_code;

        return $pdf->stream("bir-2316-{$year}-{$code}.pdf");
    }

    /**
     * BIR Alphalist of Employees — Annual Excel.
     */
    public function alphalist(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
        ])->validate();

        $year = (int) $validated['year'];

        return Excel::download(
            new AlphalistExport($this->dataService, $year),
            "bir-alphalist-{$year}.xlsx",
        );
    }

    /**
     * SSS SBR2 Monthly Contribution Report — Excel.
     */
    public function sssSbr2(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $input = $this->validateYearMonth($request);
        ['year' => $year, 'month' => $month] = $input;

        return Excel::download(
            new SssSbr2Export($this->dataService, $year, $month),
            sprintf('sss-sbr2-%d-%02d.xlsx', $year, $month),
        );
    }

    /**
     * PhilHealth RF-1 Monthly Premium Report — Excel.
     */
    public function philhealthRf1(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $input = $this->validateYearMonth($request);
        ['year' => $year, 'month' => $month] = $input;

        return Excel::download(
            new PhilHealthRf1Export($this->dataService, $year, $month),
            sprintf('philhealth-rf1-%d-%02d.xlsx', $year, $month),
        );
    }

    /**
     * Pag-IBIG Monthly Contribution Report — Excel.
     */
    public function pagibigMonthly(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $input = $this->validateYearMonth($request);
        ['year' => $year, 'month' => $month] = $input;

        return Excel::download(
            new PagIbigMonthlyExport($this->dataService, $year, $month),
            sprintf('pagibig-monthly-%d-%02d.xlsx', $year, $month),
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * @return array{year: int, month: int}
     */
    private function validateYearMonth(Request $request): array
    {
        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ])->validate();

        return [
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
        ];
    }
}
