<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Position;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Loan\Models\Loan;
use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Services\GovReportDataService;
use App\Domains\Payroll\Services\PayrollQueryService;
use App\Http\Controllers\Controller;
use App\Http\Resources\HR\EmployeeResource;
use App\Http\Resources\Payroll\PayrollDetailResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee Self-Service Controller.
 *
 * Provides read-only access to an employee's own payslips and YTD data.
 * The authenticated user must have a linked Employee record (via user_id).
 *
 * Routes (all GET, auth:sanctum required):
 *   GET /api/v1/employee/me/payslips?year=
 *   GET /api/v1/employee/me/payslips/{payrollDetail}/pdf
 *   GET /api/v1/employee/me/ytd?year=
 */
final class EmployeeSelfServiceController extends Controller
{
    public function __construct(
        private readonly GovReportDataService $govReportDataService,
        private readonly PayrollQueryService $payrollQueryService,
    ) {}

    /**
     * GET /api/v1/employee/me/payslips
     *
     * Return the authenticated employee's paginated payroll history.
     * Optionally filter by year (?year=2026).
     */
    public function payslips(Request $request): AnonymousResourceCollection
    {
        $employee = $this->resolveEmployee();

        $year = $request->integer('year', 0);
        $perPage = 15;

        $query = PayrollDetail::with([
            'payrollRun:id,reference_no,pay_period_label,cutoff_start,cutoff_end,pay_date,status,run_type',
            'employee:id,employee_code,first_name,last_name',
        ])
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', ['PUBLISHED', 'completed', 'posted']))
            ->orderBy('id', 'desc');

        if ($year > 0) {
            $query->whereHas(
                'payrollRun',
                fn ($q) => $q->whereYear('pay_date', $year),
            );
        }

        return PayrollDetailResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/v1/employee/me/payslips/{payrollDetail}
     *
     * Return full breakdown of a single payslip for the authenticated employee.
     * Includes complete payroll details with attendance, OT, leave, and deductions.
     */
    public function payslipDetail(PayrollDetail $payrollDetail): JsonResponse
    {
        $employee = $this->resolveEmployee();

        abort_if(
            $payrollDetail->employee_id !== $employee->id,
            403,
            'You do not have access to this payslip.'
        );

        $run = $payrollDetail->payrollRun;

        // Support both new 'PUBLISHED' status and legacy 'completed'/'posted' statuses
        $isAvailable = $run->isPublished() || in_array($run->status, ['completed', 'posted'], true);
        abort_unless(
            $isAvailable,
            422,
            'Payslip is only available for published payroll runs.'
        );

        // Load full details with employee info
        $detail = $payrollDetail->load([
            'employee:id,employee_code,first_name,last_name,middle_name,employment_type,department_id,position_id',
            'payrollRun:id,reference_no,pay_period_label,cutoff_start,cutoff_end,pay_date,status,run_type',
        ]);

        $departmentName = $detail->employee?->department_id
            ? Department::find($detail->employee->department_id)?->name
            : null;

        $positionName = $detail->employee?->position_id
            ? Position::find($detail->employee->position_id)?->title
            : null;

        return response()->json([
            'data' => [
                // Payroll run info
                'payroll_run' => [
                    'id' => $run->id,
                    'reference_no' => $run->reference_no,
                    'pay_period_label' => $run->pay_period_label,
                    'cutoff_start' => $run->cutoff_start,
                    'cutoff_end' => $run->cutoff_end,
                    'pay_date' => $run->pay_date,
                    'run_type' => $run->run_type,
                    'status' => $run->status,
                ],
                // Employee info
                'employee' => [
                    'id' => $detail->employee->id,
                    'employee_code' => $detail->employee->employee_code,
                    'first_name' => $detail->employee->first_name,
                    'last_name' => $detail->employee->last_name,
                    'middle_name' => $detail->employee->middle_name,
                    'employment_type' => $detail->employee->employment_type,
                    'department_name' => $departmentName,
                    'position_name' => $positionName,
                ],
                // Rate info
                'basic_monthly_rate_centavos' => $detail->basic_monthly_rate_centavos,
                'daily_rate_centavos' => $detail->daily_rate_centavos,
                'hourly_rate_centavos' => $detail->hourly_rate_centavos,
                'working_days_in_period' => $detail->working_days_in_period,
                'pay_basis' => $detail->pay_basis,
                // Attendance breakdown
                'attendance' => [
                    'days_worked' => $detail->days_worked,
                    'days_absent' => $detail->days_absent,
                    'days_late_minutes' => $detail->days_late_minutes,
                    'undertime_minutes' => $detail->undertime_minutes,
                    'leave_days_paid' => $detail->leave_days_paid,
                    'leave_days_unpaid' => $detail->leave_days_unpaid,
                    'regular_holiday_days' => $detail->regular_holiday_days,
                    'special_holiday_days' => $detail->special_holiday_days,
                ],
                // Overtime breakdown
                'overtime' => [
                    'regular_minutes' => $detail->overtime_regular_minutes,
                    'rest_day_minutes' => $detail->overtime_rest_day_minutes,
                    'holiday_minutes' => $detail->overtime_holiday_minutes,
                    'night_diff_minutes' => $detail->night_diff_minutes,
                ],
                // Earnings breakdown
                'earnings' => [
                    'basic_pay_centavos' => $detail->basic_pay_centavos,
                    'overtime_pay_centavos' => $detail->overtime_pay_centavos,
                    'holiday_pay_centavos' => $detail->holiday_pay_centavos,
                    'night_diff_pay_centavos' => $detail->night_diff_pay_centavos,
                    'gross_pay_centavos' => $detail->gross_pay_centavos,
                ],
                // Deductions breakdown
                'deductions' => [
                    'sss_ee_centavos' => $detail->sss_ee_centavos,
                    'sss_er_centavos' => $detail->sss_er_centavos,
                    'philhealth_ee_centavos' => $detail->philhealth_ee_centavos,
                    'philhealth_er_centavos' => $detail->philhealth_er_centavos,
                    'pagibig_ee_centavos' => $detail->pagibig_ee_centavos,
                    'pagibig_er_centavos' => $detail->pagibig_er_centavos,
                    'withholding_tax_centavos' => $detail->withholding_tax_centavos,
                    'loan_deductions_centavos' => $detail->loan_deductions_centavos,
                    'loan_deduction_detail' => $detail->loan_deduction_detail,
                    'other_deductions_centavos' => $detail->other_deductions_centavos,
                    'total_deductions_centavos' => $detail->total_deductions_centavos,
                ],
                // Summary
                'summary' => [
                    'gross_pay_centavos' => $detail->gross_pay_centavos,
                    'total_deductions_centavos' => $detail->total_deductions_centavos,
                    'net_pay_centavos' => $detail->net_pay_centavos,
                    'is_below_min_wage' => $detail->is_below_min_wage,
                    'has_deferred_deductions' => $detail->has_deferred_deductions,
                ],
                // YTD
                'ytd' => [
                    'ytd_taxable_income_centavos' => $detail->ytd_taxable_income_centavos,
                    'ytd_tax_withheld_centavos' => $detail->ytd_tax_withheld_centavos,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/employee/me/payslips/{payrollDetail}/pdf
     *
     * Stream the payslip PDF for one of the auth employee's own payroll details.
     * Aborts 403 if the detail belongs to a different employee.
     */
    public function downloadPayslip(PayrollDetail $payrollDetail): Response
    {
        $employee = $this->resolveEmployee();

        abort_if(
            $payrollDetail->employee_id !== $employee->id,
            403,
            'You do not have access to this payslip.'
        );

        $run = $payrollDetail->payrollRun;

        // Support both new 'PUBLISHED' status and legacy 'completed'/'posted' statuses
        $isAvailable = $run->isPublished() || in_array($run->status, ['completed', 'posted'], true);
        abort_unless(
            $isAvailable,
            422,
            'Payslip is only available for published payroll runs.'
        );

        $detail = $payrollDetail->load(
            'employee:id,employee_code,first_name,last_name,middle_name,employment_type,department_id,position_id'
        );

        $departmentName = $detail->employee?->department_id
            ? Department::find($detail->employee->department_id)?->name
            : null;

        $positionName = $detail->employee?->position_id
            ? Position::find($detail->employee->position_id)?->title
            : null;

        $settings = $this->govReportDataService->companySettings();

        $pdf = Pdf::loadView('payroll.payslip', [
            'run' => $run,
            'detail' => $detail,
            'departmentName' => $departmentName,
            'positionName' => $positionName,
            'settings' => $settings,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'payslip-%s-%s.pdf',
            $run->reference_no,
            $detail->employee?->employee_code ?? $detail->employee_id,
        );

        return $pdf->stream($filename);
    }

    /**
     * GET /api/v1/employee/me/ytd
     *
     * Return year-to-date financial summary for the authenticated employee.
     * Uses YTD accumulators from the last completed run of the specified year.
     */
    public function ytdSummary(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $year = $request->integer('year', (int) now()->format('Y'));

        $ytd = $this->payrollQueryService->ytdForEmployee($employee->id, $year);

        return response()->json([
            'year' => $year,
            'ytd_gross_centavos' => $ytd['gross'],
            'ytd_net_centavos' => $ytd['net'],
            'ytd_sss_centavos' => $ytd['sss'],
            'ytd_philhealth_centavos' => $ytd['philhealth'],
            'ytd_pagibig_centavos' => $ytd['pagibig'],
            'ytd_withholding_tax_centavos' => $ytd['wtax'],
            'ytd_taxable_income_centavos' => $ytd['ytd_taxable_income'],
            'ytd_tax_withheld_centavos' => $ytd['ytd_tax_withheld'],
        ]);
    }

    /**
     * GET /api/v1/employee/me/leave
     *
     * Return the authenticated employee's leave balances and recent request history.
     */
    public function myLeave(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->with('leaveType')
            ->get()
            ->map(fn ($b) => [
                'leave_type_id' => $b->leave_type_id,
                'leave_type_name' => $b->leaveType?->name,
                'balance_days' => $b->balance_days,
                'used_days' => $b->used_days,
            ]);

        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('date_from')
            ->paginate((int) $request->query('per_page', '20'));

        return response()->json([
            'data' => [
                'balances' => $balances,
                'requests' => $requests,
            ],
        ]);
    }

    /**
     * GET /api/v1/employee/me/loans
     *
     * Return the authenticated employee's active loans and amortisation schedules.
     */
    public function myLoans(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $loans = Loan::where('employee_id', $employee->id)
            ->with(['loanType', 'amortizationSchedules'])
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', '20'));

        return response()->json(['data' => $loans]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/employee/me/profile
     *
     * Return the authenticated employee's own HR record.
     */
    public function profile(): EmployeeResource
    {
        return new EmployeeResource($this->resolveEmployee()->load(['department', 'position', 'salaryGrade', 'supervisor', 'shiftAssignments.shiftSchedule']));
    }

    /**
     * PATCH /api/v1/employee/me/profile
     *
     * Allow self-service updates to non-sensitive personal fields only.
     * Salary, department, position, and employment status remain HR-only.
     */
    public function updateProfile(Request $request): EmployeeResource
    {
        $employee = $this->resolveEmployee();

        $validated = $request->validate([
            'personal_email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'personal_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'present_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_no' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $employee->update($validated);

        return new EmployeeResource($employee->load(['department', 'position', 'salaryGrade', 'supervisor']));
    }

    /**
     * GET /api/v1/employee/me/attendance
     *
     * Return the authenticated employee's attendance logs for a given month/year.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();

        $month = $request->integer('month', (int) now()->format('n'));
        $year = $request->integer('year', (int) now()->format('Y'));

        $logs = \App\Domains\Attendance\Models\AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereMonth('work_date', $month)
            ->whereYear('work_date', $year)
            ->orderBy('work_date')
            ->get();

        $entries = $logs->map(fn ($log) => [
            'date' => (string) $log->work_date,
            'day_name' => \Carbon\Carbon::parse($log->work_date)->format('D'),
            'time_in' => $log->time_in,
            'time_out' => $log->time_out,
            'late_minutes' => (int) ($log->late_minutes ?? 0),
            'undertime_minutes' => (int) ($log->undertime_minutes ?? 0),
            'overtime_minutes' => (int) ($log->overtime_minutes ?? 0),
            'is_absent' => (bool) ($log->is_absent ?? false),
            'remarks' => $log->remarks ?? null,
        ]);

        return response()->json([
            'data' => $entries,
            'summary' => [
                'days_worked' => $entries->where('is_absent', false)->where('time_in', '!=', null)->count(),
                'days_absent' => $entries->where('is_absent', true)->count(),
                'total_late_minutes' => $entries->sum('late_minutes'),
                'total_undertime_minutes' => $entries->sum('undertime_minutes'),
                'total_overtime_minutes' => $entries->sum('overtime_minutes'),
            ],
            'period' => sprintf('%04d-%02d', $year, $month),
        ]);
    }

    /**
     * GET /api/v1/employee/me/attendance/pdf
     *
     * Download a PDF Daily Time Record for a given month/year.
     */
    public function downloadDtr(Request $request): Response
    {
        $employee = $this->resolveEmployee();
        $employee->loadMissing(['department', 'position']);

        $month = $request->integer('month', (int) now()->format('n'));
        $year = $request->integer('year', (int) now()->format('Y'));

        $logs = \App\Domains\Attendance\Models\AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereMonth('work_date', $month)
            ->whereYear('work_date', $year)
            ->orderBy('work_date')
            ->get();

        $entries = $logs->map(fn ($log) => [
            'date' => \Carbon\Carbon::parse($log->work_date)->format('M d'),
            'day_name' => \Carbon\Carbon::parse($log->work_date)->format('D'),
            'time_in' => $log->time_in,
            'time_out' => $log->time_out,
            'late_minutes' => (int) ($log->late_minutes ?? 0),
            'undertime_minutes' => (int) ($log->undertime_minutes ?? 0),
            'overtime_minutes' => (int) ($log->overtime_minutes ?? 0),
            'is_absent' => (bool) ($log->is_absent ?? false),
            'is_weekend' => in_array(\Carbon\Carbon::parse($log->work_date)->dayOfWeek, [0, 6]),
            'remarks' => $log->remarks ?? null,
        ]);

        $summary = [
            'days_worked' => $entries->where('is_absent', false)->where('time_in', '!=', null)->count(),
            'days_absent' => $entries->where('is_absent', true)->count(),
            'total_late_minutes' => $entries->sum('late_minutes'),
            'total_undertime_minutes' => $entries->sum('undertime_minutes'),
            'total_overtime_minutes' => $entries->sum('overtime_minutes'),
        ];

        $settings = app(\App\Services\SystemSettingService::class)->getCompanyInfo();

        $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

        $pdf = Pdf::loadView('employee.dtr', [
            'employee' => $employee,
            'entries' => $entries,
            'summary' => $summary,
            'periodLabel' => ($monthNames[$month] ?? '') . ' ' . $year,
            'settings' => $settings,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('dtr-%s-%04d-%02d.pdf', $employee->employee_code, $year, $month);

        return $pdf->stream($filename);
    }

    /**
     * Resolve the Employee record for the current authenticated user.
     * Aborts 403 if no linked employee record exists.
     */
    private function resolveEmployee(): Employee
    {
        $employee = Employee::where('user_id', auth()->id())->first();

        abort_if($employee === null, 403, 'No employee profile is linked to your account.');

        return $employee;
    }
}
