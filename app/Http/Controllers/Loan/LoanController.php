<?php

declare(strict_types=1);

namespace App\Http\Controllers\Loan;

use App\Domains\HR\Models\Employee;
use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanAmortizationSchedule;
use App\Domains\Loan\Services\LoanAmortizationService;
use App\Domains\Loan\Services\LoanRequestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\ApproveLoanRequest;
use App\Http\Requests\Loan\StoreLoanRequest;
use App\Http\Resources\Loan\LoanAmortizationScheduleResource;
use App\Http\Resources\Loan\LoanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LoanController extends Controller
{
    public function __construct(
        private readonly LoanRequestService $service,
        private readonly LoanAmortizationService $amortizationService,
    ) {}

    /**
     * GET /api/v1/loans
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Loan::class);

        $loans = Loan::with(['loanType', 'employee'])
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('status_in'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('loan_type_id'), fn ($q, $t) => $q->where('loan_type_id', $t))
            ->latest()
            ->paginate((int) $request->query('per_page', '25'));

        return LoanResource::collection($loans);
    }

    /**
     * GET /api/v1/loans/team
     * Department-scoped loans for team managers.
     */
    public function team(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewDepartment', Loan::class);

        $user = $request->user();
        $departmentIds = $user->departments()->pluck('departments.id')->toArray();

        $loans = Loan::with(['loanType', 'employee'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->whereHas('employee', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->latest()
            ->paginate((int) $request->query('per_page', '25'));

        return LoanResource::collection($loans);
    }

    /**
     * POST /api/v1/loans
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $employee = Employee::findOrFail($request->validated('employee_id'));
        $this->authorize('create', [Loan::class, $employee]);

        $loan = $this->service->submit(
            $employee,
            $request->validated(),
            (int) $request->user()->id,
        );

        return (new LoanResource($loan->load('loanType')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/loans/{loan}
     */
    public function show(Loan $loan): LoanResource
    {
        $this->authorize('view', $loan);

        return new LoanResource($loan->load(['loanType', 'employee', 'approver', 'accountingApprover']));
    }

    /**
     * GET /api/v1/loans/{loan}/employee-history
     */
    public function employeeHistory(Loan $loan): JsonResponse
    {
        $this->authorize('view', $loan);

        $history = $this->service->getEmployeeLoanHistory(
            $loan->employee_id,
            $loan->id
        );

        return response()->json([
            'data' => LoanResource::collection($history),
        ]);
    }

    /**
     * PATCH /api/v1/loans/{loan}/approve
     */
    public function approve(ApproveLoanRequest $request, Loan $loan): LoanResource
    {
        $this->authorize('approve', $loan);

        $updated = $this->service->approve(
            $loan,
            (int) $request->user()->id,
            $request->validated('remarks', ''),
            $request->validated('first_deduction_date'),
        );

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * PATCH /api/v1/loans/{loan}/accounting-approve
     */
    public function accountingApprove(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('accountingApprove', $loan);

        $updated = $this->service->accountingApprove(
            $loan,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * PATCH /api/v1/loans/{loan}/disburse
     */
    public function disburse(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('disburse', $loan);

        $updated = $this->service->disburse($loan, (int) $request->user()->id);

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * PATCH /api/v1/loans/{loan}/reject
     */
    public function reject(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('reject', $loan);

        $updated = $this->service->reject(
            $loan,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * DELETE /api/v1/loans/{loan}  (cancel)
     */
    public function cancel(Request $request, Loan $loan): JsonResponse
    {
        $this->authorize('cancel', $loan);

        $this->service->cancel($loan, (int) $request->user()->id);

        return response()->json(['message' => 'Loan application cancelled.']);
    }

    // ── Workflow v2 actions ───────────────────────────────────────────────────

    /**
     * PATCH /api/v1/loans/{loan}/head-note
     */
    public function headNote(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('headNote', $loan);

        $updated = $this->service->headNote(
            $loan,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * PATCH /api/v1/loans/{loan}/manager-check
     */
    public function managerCheck(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('managerCheck', $loan);

        $updated = $this->service->managerCheck(
            $loan,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * PATCH /api/v1/loans/{loan}/officer-review
     */
    public function officerReview(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('officerReview', $loan);

        $updated = $this->service->officerReview(
            $loan,
            (int) $request->user()->id,
            $request->input('remarks', ''),
        );

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * PATCH /api/v1/loans/{loan}/vp-approve
     */
    public function vpApprove(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('vpApprove', $loan);

        $updated = $this->service->vpApprove(
            $loan,
            (int) $request->user()->id,
            $request->input('remarks', ''),
            $request->input('first_deduction_date'),
        );

        return new LoanResource($updated->load('loanType'));
    }

    /**
     * GET /api/v1/loans/{loan}/schedule
     */
    public function schedule(Loan $loan): AnonymousResourceCollection
    {
        $this->authorize('view', $loan);

        $installments = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('installment_no')
            ->get();

        return LoanAmortizationScheduleResource::collection($installments);
    }

    /**
     * POST /api/v1/loans/{loan}/payments
     * Record a manual repayment for an active loan installment.
     */
    public function recordPayment(Request $request, Loan $loan): JsonResponse
    {
        $this->authorize('recordPayment', $loan);

        $request->validate([
            'installment_number' => ['required', 'integer', 'min:1'],
            'paid_amount_centavos' => ['required', 'integer', 'min:1'],
        ]);

        $installment = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->where('installment_number', $request->integer('installment_number'))
            ->firstOrFail();

        $this->amortizationService->recordPayment(
            $installment,
            $request->integer('paid_amount_centavos'),
        );

        return response()->json(['message' => 'Payment recorded.']);
    }
}
