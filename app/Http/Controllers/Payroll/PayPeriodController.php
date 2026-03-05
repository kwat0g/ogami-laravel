<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Domains\Payroll\Models\PayPeriod;
use App\Http\Controllers\Controller;
use App\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD for pay periods.
 *
 * A pay period defines the cutoff window and release date that payroll runs
 * are linked to.  HR managers create periods in advance; staff/supervisors
 * can list them to select the correct period when initiating a payroll run.
 */
class PayPeriodController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/payroll/periods
     *
     * List pay periods, newest first.  Optionally filter by status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PayPeriod::query()->orderBy('cutoff_end', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(
            $query->paginate((int) $request->query('per_page', 20)),
        );
    }

    /**
     * POST /api/v1/payroll/periods
     *
     * Create a new pay period.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'cutoff_start' => ['required', 'date'],
            'cutoff_end' => ['required', 'date', 'after_or_equal:cutoff_start'],
            'pay_date' => ['required', 'date', 'after_or_equal:cutoff_end'],
            'frequency' => ['required', 'in:semi_monthly,monthly,weekly'],
        ]);

        $period = PayPeriod::create($validated);

        return $this->successResponse($period, 'Pay period created.', 201);
    }

    /**
     * GET /api/v1/payroll/periods/{payPeriod}
     */
    public function show(PayPeriod $payPeriod): JsonResponse
    {
        return response()->json($payPeriod->load('payrollRuns:id,reference_no,status,pay_date'));
    }

    /**
     * PATCH /api/v1/payroll/periods/{payPeriod}/close
     *
     * Mark a pay period as closed.  A closed period cannot be re-opened
     * through the API (admin migration required).
     */
    public function close(PayPeriod $payPeriod): JsonResponse
    {
        if ($payPeriod->isClosed()) {
            return $this->errorResponse('Pay period is already closed.', 'ALREADY_CLOSED', 409);
        }

        $payPeriod->update(['status' => 'closed']);

        return $this->successResponse($payPeriod, 'Pay period closed.');
    }
}
