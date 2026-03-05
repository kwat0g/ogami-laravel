<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Services\GovReportDataService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payroll\PayrollDetailResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Read-only payslip viewer.
 * Scoped under a PayrollRun parent via route model binding.
 */
final class PayrollDetailController extends Controller
{
    public function __construct(
        private readonly GovReportDataService $govReportDataService,
    ) {}

    /**
     * GET /api/v1/payroll/runs/{payrollRun}/details
     */
    public function index(PayrollRun $payrollRun): AnonymousResourceCollection
    {
        $this->authorize('view', $payrollRun);

        $details = $payrollRun->details()
            ->with('employee:id,employee_code,first_name,last_name')
            ->orderBy('id')
            ->paginate(50);

        return PayrollDetailResource::collection($details);
    }

    /**
     * GET /api/v1/payroll/runs/{payrollRun}/details/{payrollDetail}
     */
    public function show(PayrollRun $payrollRun, PayrollDetail $payrollDetail): PayrollDetailResource
    {
        $this->authorize('view', $payrollRun);

        // Ensure the detail belongs to this run (scoped route model binding)
        abort_if(
            $payrollDetail->payroll_run_id !== $payrollRun->id,
            404,
            'Payroll detail not found in this run.'
        );

        return new PayrollDetailResource($payrollDetail->load('employee'));
    }

    /**
     * GET /api/v1/payroll/runs/{payrollRun}/details/{payrollDetail}/payslip
     * Stream a PDF payslip for a single employee. Run must be completed.
     */
    public function payslip(PayrollRun $payrollRun, PayrollDetail $payrollDetail): Response
    {
        $this->authorize('view', $payrollRun);

        abort_if(
            $payrollDetail->payroll_run_id !== $payrollRun->id,
            404,
            'Payroll detail not found in this run.'
        );

        abort_unless(
            $payrollRun->isCompleted(),
            422,
            'Payslip is only available for completed payroll runs.'
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

        $brandedSettings = $this->govReportDataService->companySettings();

        $pdf = Pdf::loadView('payroll.payslip', [
            'run' => $payrollRun,
            'detail' => $detail,
            'departmentName' => $departmentName,
            'positionName' => $positionName,
            'settings' => $brandedSettings,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'payslip-%s-%s.pdf',
            $payrollRun->reference_no,
            $detail->employee?->employee_code ?? $detail->employee_id
        );

        return $pdf->stream($filename);
    }
}
