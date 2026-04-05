<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Domains\Payroll\Models\PayrollAdjustment;
use App\Domains\Payroll\Models\PayrollRun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollAdjustmentRequest;
use App\Http\Resources\Payroll\PayrollAdjustmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manage ad-hoc earnings/deductions attached to a locked payroll run.
 */
final class PayrollAdjustmentController extends Controller
{
    /**
     * GET /api/v1/payroll/runs/{payrollRun}/adjustments
     */
    public function index(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyPermission([
                'payroll.initiate',
                'payroll.compute',
                'payroll.hr_approve',
                'payroll.acctg_approve',
                'payroll.approve',
            ]),
            403,
            'You do not have permission to view payroll adjustments.',
        );

        $adjustments = $payrollRun->adjustments()
            ->with('employee:id,first_name,last_name,employee_code')
            ->orderBy('id', 'desc')
            ->get();

        return PayrollAdjustmentResource::collection($adjustments)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/v1/payroll/runs/{payrollRun}/adjustments
     */
    public function store(StorePayrollAdjustmentRequest $request, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyPermission([
                'payroll.initiate',
                'payroll.compute',
                'payroll.hr_approve',
                'payroll.approve',
            ]),
            403,
            'You do not have permission to manage payroll adjustments.',
        );

        // Can only add adjustments to a draft or locked run
        abort_if(
            in_array($payrollRun->status, ['processing', 'completed', 'cancelled'], true),
            422,
            "Cannot add adjustments to a {$payrollRun->status} run."
        );

        $adjustment = $payrollRun->adjustments()->create([
            'employee_id' => $request->integer('employee_id'),
            'type' => $request->input('type'),
            'nature' => $request->input('nature'),
            'description' => $request->input('description'),
            'amount_centavos' => $request->integer('amount_centavos'),
        ]);

        return (new PayrollAdjustmentResource($adjustment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/v1/payroll/adjustments/{payrollAdjustment}
     */
    public function destroy(Request $request, PayrollAdjustment $payrollAdjustment): JsonResponse
    {
        // Require same payroll operational permissions as store()
        abort_unless(
            $request->user()->hasAnyPermission([
                'payroll.initiate',
                'payroll.compute',
                'payroll.hr_approve',
                'payroll.approve',
            ]),
            403,
            'You do not have permission to manage payroll adjustments.',
        );

        /** @var PayrollRun $run */
        $run = $payrollAdjustment->payrollRun;

        abort_if(
            in_array($run->status, ['processing', 'completed'], true),
            422,
            "Cannot remove adjustments from a {$run->status} run."
        );

        $payrollAdjustment->delete();

        return response()->json(['message' => 'Adjustment removed.']);
    }
}
