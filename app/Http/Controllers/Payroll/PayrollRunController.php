<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Services\PayrollBatchDispatcher;
use App\Domains\Payroll\Services\PayrollPreRunService;
use App\Domains\Payroll\Services\PayrollRunService;
use App\Domains\Payroll\Services\PayrollScopeService;
use App\Domains\Payroll\Services\PayrollWorkflowService;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Domains\Payroll\Validators\PayrollRunValidator;
use App\Exports\PayrollBreakdownExport;
use App\Exports\PayrollRegisterExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AcctgApprovePayrollRunRequest;
use App\Http\Requests\Payroll\AcknowledgePreRunRequest;
use App\Http\Requests\Payroll\ApprovePayrollRunRequest;
use App\Http\Requests\Payroll\ConfirmScopeRequest;
use App\Http\Requests\Payroll\HrApprovePayrollRunRequest;
use App\Http\Requests\Payroll\PublishPayrollRunRequest;
use App\Http\Requests\Payroll\StorePayrollRunRequest;
use App\Http\Resources\Payroll\PayrollRunResource;
use App\Services\BankDisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin HTTP adapter for PayrollRun domain.
 * Validation → Service → Resource transform only; no business logic here.
 */
final class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollRunService $service,
        private readonly PayrollBatchDispatcher $dispatcher,
        private readonly BankDisbursementService $bankDisbursementService,
        private readonly PayrollScopeService $scopeService,
        private readonly PayrollPreRunService $preRunService,
        private readonly PayrollWorkflowService $workflowService,
    ) {}

    /**
     * GET /api/v1/payroll/runs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PayrollRun::class);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $filters = $request->only(['status', 'year']);

        // Accounting Managers (acctg_approve without initiate/hr_approve) only
        // need to see runs that are pending their action or already published.
        $isAcctgOnly = $user->hasPermissionTo('payroll.acctg_approve')
            && ! $user->hasAnyPermission(['payroll.initiate', 'payroll.hr_approve']);

        if ($isAcctgOnly) {
            // If the user requested a specific status that is outside their
            // scope, honour it only if it is in the allowed set; otherwise
            // fall back to the full allowed set.
            $acctgStatuses = ['SUBMITTED', 'PUBLISHED'];
            if (isset($filters['status']) && ! in_array($filters['status'], $acctgStatuses, true)) {
                unset($filters['status']);
            }
            $filters['statuses'] = $acctgStatuses;
        }

        $runs = $this->service->list(
            $filters,
            (int) $request->query('per_page', '25'),
        );

        return PayrollRunResource::collection($runs);
    }

    /**
     * POST /api/v1/payroll/runs
     */
    public function store(StorePayrollRunRequest $request): JsonResponse
    {
        $run = $this->service->create($request->validated(), (int) $request->user()->id);

        return (new PayrollRunResource($run))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/payroll/runs/{payrollRun}
     */
    public function show(PayrollRun $payrollRun): PayrollRunResource
    {
        $this->authorize('view', $payrollRun);

        $payrollRun->loadMissing(['exclusions.employee']);

        return new PayrollRunResource($payrollRun);
    }

    /**
     * PATCH /api/v1/payroll/runs/{payrollRun}/lock
     * Locks the run and dispatches the computation batch.
     */
    public function lock(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('lock', $payrollRun);

        $this->service->lock($payrollRun);

        $result = $this->dispatcher->dispatch($payrollRun);

        return response()->json([
            'message' => 'Payroll run locked and computation dispatched.',
            'batch_id' => $result['batch_id'],
            'total_jobs' => $result['total_jobs'],
            'run' => new PayrollRunResource($payrollRun->fresh()),
        ]);
    }

    /**
     * PATCH /api/v1/payroll/runs/{payrollRun}/approve
     * Approves a completed run (SoD: approver ≠ creator).
     */
    public function approve(ApprovePayrollRunRequest $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->service->approve($payrollRun, (int) $request->user()->id);

        return response()->json([
            'message' => 'Payroll run approved.',
            'run' => new PayrollRunResource($payrollRun->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/payroll/runs/{payrollRun}
     */
    public function cancel(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('cancel', $payrollRun);

        $this->service->cancel($payrollRun);

        return response()->json(['message' => 'Payroll run cancelled.']);
    }

    /**
     * PATCH /api/v1/payroll/runs/{payrollRun}/submit
     * HR Manager submits the computed run for Accounting Manager review.
     * Transitions: completed → submitted.
     */
    public function submit(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('submit', $payrollRun);

        $this->service->submit($payrollRun, (int) $request->user()->id);

        return response()->json([
            'message' => 'Payroll run submitted for accounting approval.',
            'run' => new PayrollRunResource($payrollRun->fresh()),
        ]);
    }

    /**
     * PATCH /api/v1/payroll/runs/{payrollRun}/accounting-approve
     * Accounting Manager gives final sign-off.
     * Transitions: submitted → approved (SoD gated).
     */
    public function accountingApprove(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('accountingApprove', $payrollRun);

        $this->service->accountingApprove($payrollRun, (int) $request->user()->id);

        return response()->json([
            'message' => 'Payroll run approved by Accounting.',
            'run' => new PayrollRunResource($payrollRun->fresh()),
        ]);
    }

    /**
     * PATCH /api/v1/payroll/runs/{payrollRun}/post
     * Post the approved run to the General Ledger (terminal).
     * Transitions: approved → posted.
     */
    public function post(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('post', $payrollRun);

        $this->service->post($payrollRun);

        return response()->json([
            'message' => 'Payroll run posted to General Ledger.',
            'run' => new PayrollRunResource($payrollRun->fresh()),
        ]);
    }

    /**
     * GET /api/v1/payroll/runs/{payrollRun}/export/register
     * Download the Payroll Register as an Excel (.xlsx) file.
     * Run must be completed.
     */
    public function exportRegister(PayrollRun $payrollRun): BinaryFileResponse
    {
        $this->authorize('view', $payrollRun);

        abort_unless(
            $payrollRun->isCompleted(),
            422,
            'Payroll register is only available for completed runs.'
        );

        $filename = "payroll-register-{$payrollRun->reference_no}.xlsx";

        return Excel::download(new PayrollRegisterExport($payrollRun), $filename);
    }

    /**
     * GET /api/v1/payroll/runs/{payrollRun}/export/disbursement
     * Download the BDO salary crediting CSV file.
     * Run must be completed or disbursed.
     */
    public function exportDisbursement(PayrollRun $payrollRun): StreamedResponse
    {
        $this->authorize('view', $payrollRun);

        abort_unless(
            $payrollRun->isCompleted() || $payrollRun->isDisbursed() || $payrollRun->isPublished(),
            422,
            'Bank disbursement file is only available for completed, disbursed, or published runs.'
        );

        $csv = $this->bankDisbursementService->generateBdo($payrollRun);
        $filename = "disbursement-bdo-{$payrollRun->reference_no}.csv";

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * GET /api/v1/payroll/runs/{payrollRun}/export/breakdown
     * Download comprehensive payroll breakdown Excel file.
     * Only available when status is DISBURSED (after accounting approval).
     * Accessible by HR Manager for record keeping.
     */
    public function exportBreakdown(PayrollRun $payrollRun): BinaryFileResponse
    {
        $this->authorize('exportBreakdown', $payrollRun);

        abort_unless(
            in_array($payrollRun->status, ['HR_APPROVED', 'ACCTG_APPROVED', 'DISBURSED', 'PUBLISHED'], true),
            422,
            'Payroll breakdown export is only available after HR approval (Step 7 onwards).'
        );

        $filename = "payroll-breakdown-{$payrollRun->reference_no}.xlsx";

        return Excel::download(new PayrollBreakdownExport($payrollRun), $filename);
    }

    /**
     * GET /api/v1/payroll/runs/validate
     * Pre-run validation checks (PR-001 to PR-004).
     * Returns structured results so the UI can render a pre-run checklist.
     */
    public function validatePreRun(Request $request): JsonResponse
    {
        $this->authorize('create', PayrollRun::class);

        $validator = app(PayrollRunValidator::class);
        $cutoffStart = $request->query('cutoff_start', now()->startOfMonth()->toDateString());
        $cutoffEnd = $request->query('cutoff_end', now()->endOfMonth()->toDateString());
        $payDate = $request->query('pay_date', now()->endOfMonth()->addDays(5)->toDateString());
        $runType = $request->query('run_type', 'regular');

        $checks = [];

        foreach ([
            ['code' => 'PR-001', 'label' => 'No overlapping run for this period',
                'fn' => fn () => $validator->assertNonOverlapping($cutoffStart, $cutoffEnd, $runType),
                'severity' => 'block'],
            ['code' => 'PR-002', 'label' => 'Cutoff start < end < pay date',
                'fn' => fn () => $validator->assertCutoffOrder($cutoffStart, $cutoffEnd, $payDate),
                'severity' => 'block'],
            ['code' => 'PR-003', 'label' => 'Active employees exist',
                'fn' => fn () => $validator->assertActiveEmployeesExist(),
                'severity' => 'block'],
            ['code' => 'PR-004', 'label' => 'Open pay period exists',
                'fn' => fn () => $validator->assertOpenPayPeriodExists($cutoffStart, $cutoffEnd),
                'severity' => 'warn'],
        ] as $check) {
            try {
                ($check['fn'])();
                $checks[] = ['code' => $check['code'], 'label' => $check['label'], 'status' => 'pass'];
            } catch (\Throwable $e) {
                $checks[] = [
                    'code' => $check['code'],
                    'label' => $check['label'],
                    'status' => $check['severity'],
                    'message' => $e->getMessage(),
                ];
            }
        }

        $hasBlocker = collect($checks)->contains(fn ($c) => $c['status'] === 'block');

        return response()->json([
            'data' => ['can_proceed' => ! $hasBlocker, 'checks' => $checks],
        ]);
    }

    /**
     * PATCH /api/v1/payroll/runs/{payrollRun}/reject
     * Kick back a submitted or locked payroll run with an optional reason.
     */
    public function reject(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('update', $payrollRun);

        $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $sm = app(PayrollRunStateMachine::class);

        $target = match ($payrollRun->status) {
            'submitted' => 'completed',
            'locked' => 'draft',
            default => 'cancelled',
        };

        $sm->transition($payrollRun, $target);

        if ($request->filled('reason')) {
            $payrollRun->notes = ltrim(($payrollRun->notes ?? '')."\n[REJECTED] ".$request->input('reason'));
            $payrollRun->save();
        }

        return response()->json(['data' => ['id' => $payrollRun->id, 'status' => $payrollRun->status]]);
    }

    /**
     * GET /api/v1/payroll/runs/{payrollRun}/exceptions
     * Returns detail lines with anomalies: min-wage breach, deferred deductions, or notes.
     */
    public function exceptions(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $rows = PayrollDetail::with('employee')
            ->where('payroll_run_id', $payrollRun->id)
            ->where(fn ($q) => $q
                ->where('is_below_min_wage', true)
                ->orWhere('has_deferred_deductions', true)
                ->orWhere('ln007_applied', true)
                ->orWhereNotNull('edge_cases_applied')
                ->orWhereNotNull('notes')
            )
            ->orderBy('employee_id')
            ->get()
            ->map(fn (PayrollDetail $d) => [
                'id' => $d->id,
                'employee_id' => $d->employee_id,
                'employee_name' => $d->employee
                    ? "{$d->employee->first_name} {$d->employee->last_name}"
                    : null,
                'department' => $d->employee?->department?->name,
                'is_below_min_wage' => $d->is_below_min_wage,
                'has_deferred_deductions' => $d->has_deferred_deductions,
                'ln007_applied' => $d->ln007_applied,
                'ln007_truncated_amt' => $d->ln007_truncated_amt,
                'ln007_carried_fwd' => $d->ln007_carried_fwd,
                'edge_cases_applied' => $d->edge_cases_applied,
                'net_pay_centavos' => $d->net_pay_centavos,
                'employee_flag' => $d->employee_flag ?? 'none',
                'review_note' => $d->review_note,
                'notes' => $d->notes,
            ]);

        return response()->json(['data' => $rows, 'total' => $rows->count()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WORKFLOW v1.0 — Steps 2 through 8
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/payroll/runs/draft-scope-preview
     * Returns a live employee-count preview for a *new, unsaved* payroll run.
     * Called by the wizard Steps 1–2 before the run record is created in the DB.
     * Accepts the same filters as the run-bound scope-preview, plus cutoff_end.
     */
    public function draftScopePreview(Request $request): JsonResponse
    {
        $this->authorize('create', PayrollRun::class);

        $validated = $request->validate([
            'cutoff_end' => ['required', 'date'],
            'departments' => ['array'],
            'departments.*' => ['integer'],
            'positions' => ['array'],
            'positions.*' => ['integer'],
            'employment_types' => ['array'],
            'employment_types.*' => ['string'],
            'include_unpaid_leave' => ['boolean'],
            'include_probation_end' => ['boolean'],
            'exclude_employee_ids' => ['array'],
            'exclude_employee_ids.*' => ['integer'],
        ]);

        $preview = $this->scopeService->draftScopePreview(
            $validated['cutoff_end'],
            $validated['departments'] ?? null,
            $validated['positions'] ?? null,
            $validated['employment_types'] ?? null,
            (bool) ($validated['include_unpaid_leave'] ?? false),
            (bool) ($validated['include_probation_end'] ?? false),
            $validated['exclude_employee_ids'] ?? [],
        );

        return response()->json(['data' => $preview]);
    }

    /**
     * PATCH /api/v1/payroll/runs/{id}/scope
     * Step 2: Confirm employee scope → SCOPE_SET.
     */
    public function confirmScope(ConfirmScopeRequest $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('update', $payrollRun);

        // Process any new manual exclusions provided in this request
        if (! empty($request->validated('exclusions'))) {
            foreach ($request->validated('exclusions') as $excl) {
                $this->scopeService->addExclusion(
                    $payrollRun,
                    (int) $excl['employee_id'],
                    $excl['reason'],
                    (int) $request->user()->id,
                );
            }
        }

        $run = $this->scopeService->confirmScope($payrollRun, $request->validated());

        return response()->json([
            'message' => 'Scope confirmed. Run is ready for pre-run validation.',
            'run' => new PayrollRunResource($run),
        ]);
    }

    /**
     * GET /api/v1/payroll/runs/{id}/scope-preview
     * Returns live employee count by scope filter selection (Step 2 live preview).
     */
    public function scopePreview(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $preview = $this->scopeService->scopePreview(
            $payrollRun,
            $request->array('departments'),
            $request->array('positions'),
            $request->array('employment_types'),
            (bool) $request->input('include_unpaid_leave', false),
            (bool) $request->input('include_probation_end', false),
        );

        return response()->json(['data' => $preview]);
    }

    /**
     * PATCH /api/v1/payroll/runs/{id}/scope/exclusions
     * Add a manual exclusion to this run's scope.
     */
    public function addExclusion(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('update', $payrollRun);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $exclusion = $this->scopeService->addExclusion(
            $payrollRun,
            (int) $validated['employee_id'],
            $validated['reason'],
            (int) $request->user()->id,
        );

        return response()->json(['data' => $exclusion->load('employee')], 201);
    }

    /**
     * DELETE /api/v1/payroll/runs/{id}/scope/exclusions/{employeeId}
     * Remove a manual exclusion.
     */
    public function removeExclusion(Request $request, PayrollRun $payrollRun, int $employeeId): JsonResponse
    {
        $this->authorize('update', $payrollRun);

        $this->scopeService->removeExclusion($payrollRun, $employeeId);

        return response()->json(['message' => 'Exclusion removed.']);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/validate
     * Step 3: Run PR-001 to PR-008 pre-run checks.
     */
    public function preRunValidate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $result = $this->preRunService->runAllChecks($payrollRun);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/acknowledge
     * Step 3 → 4: Acknowledge warnings + transition to PRE_RUN_CHECKED.
     */
    public function acknowledgePreRun(AcknowledgePreRunRequest $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('update', $payrollRun);

        $run = $this->preRunService->acknowledge($payrollRun, (int) $request->user()->id);

        return response()->json([
            'message' => 'Pre-run checks acknowledged. Ready to begin computation.',
            'run' => new PayrollRunResource($run),
        ]);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/compute
     * Step 3 → 4: Begin async computation from PRE_RUN_CHECKED state.
     * Transitions run to PROCESSING and dispatches per-employee batch jobs.
     */
    public function beginComputation(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('update', $payrollRun);

        if (! in_array($payrollRun->status, ['PRE_RUN_CHECKED', 'FAILED'], true)) {
            return response()->json([
                'message' => 'Run must be in PRE_RUN_CHECKED or FAILED status to begin computation.',
                'status' => $payrollRun->status,
            ], 422);
        }

        // Transition to PROCESSING via state machine (sets computation_started_at)
        $stateMachine = app(PayrollRunStateMachine::class);
        $stateMachine->transition($payrollRun, 'PROCESSING');

        // Dispatch per-employee batch jobs
        $result = $this->dispatcher->dispatch($payrollRun);

        return response()->json([
            'message' => 'Computation started. Jobs dispatched.',
            'batch_id' => $result['batch_id'],
            'total_jobs' => $result['total_jobs'],
            'run' => new PayrollRunResource($payrollRun->fresh()),
        ]);
    }

    /**
     * GET /api/v1/payroll/runs/{id}/progress
     * Step 4: Poll computation progress (JSON, also stored in progress_json column).
     */
    public function progress(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $isProcessing = in_array(strtolower((string) $payrollRun->status), ['processing'], true);

        // During PROCESSING, count live from payroll_details so the bar moves
        // without requiring per-job DB writes to progress_json.
        $processed = $isProcessing
            ? PayrollDetail::where('payroll_run_id', $payrollRun->id)->count()
            : ($payrollRun->total_employees ?? 0);

        $total = $payrollRun->total_employees ?: 0;
        $pct = $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 0;
        $progress = $payrollRun->progress_json ?? [];

        return response()->json(['data' => array_merge($progress, [
            'status' => $payrollRun->status,
            'started_at' => $payrollRun->computation_started_at,
            'finished_at' => $payrollRun->computation_completed_at,
            'employees_processed' => $processed,
            'total_employees' => $total,
            'percent_complete' => $pct,
        ])]);
    }

    /**
     * GET /api/v1/payroll/runs/{id}/breakdown
     * Step 5: Full employee breakdown (paginated).
     */
    public function breakdown(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $query = PayrollDetail::with(['employee.department', 'employee.position'])
            ->where('payroll_run_id', $payrollRun->id);

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }
        if ($request->filled('flag')) {
            $query->where('employee_flag', $request->string('flag'));
        }
        if ($request->filled('search')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $term = $request->string('search');
                $q->where('first_name', 'ilike', "%{$term}%")
                    ->orWhere('last_name', 'ilike', "%{$term}%")
                    ->orWhere('employee_code', 'ilike', "%{$term}%");
            });
        }

        $details = $query->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $details->items(),
            'meta' => [
                'current_page' => $details->currentPage(),
                'last_page' => $details->lastPage(),
                'per_page' => $details->perPage(),
                'total' => $details->total(),
            ],
            'summary' => [
                'total_gross' => $payrollRun->gross_pay_total_centavos,
                'total_deductions' => $payrollRun->total_deductions_centavos,
                'total_net' => $payrollRun->net_pay_total_centavos,
                'total_employees' => $payrollRun->total_employees,
            ],
        ]);
    }

    /**
     * GET /api/v1/payroll/runs/{id}/breakdown/{detailId}
     * Step 5: Per-employee deduction stack trace.
     */
    public function breakdownDetail(PayrollRun $payrollRun, PayrollDetail $payrollDetail): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        abort_unless($payrollDetail->payroll_run_id === $payrollRun->id, 404);

        $payrollDetail->load(['employee.department', 'employee.position']);

        return response()->json(['data' => $payrollDetail]);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/review/flag/{detailId}
     * Step 5: Flag or un-flag an individual employee for HR review.
     */
    public function flagEmployee(Request $request, PayrollRun $payrollRun, int $detailId): JsonResponse
    {
        $this->authorize('update', $payrollRun);

        $validated = $request->validate([
            'flag' => ['required', 'string', 'in:none,flagged,resolved'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflowService->flagEmployee(
            $payrollRun,
            $detailId,
            $validated['flag'],
            $validated['review_note'] ?? null,
        );

        return response()->json(['message' => 'Employee flag updated.']);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/submit
     * Step 5 → 6: Submit reviewed run for HR Manager approval.
     * Transitions REVIEW → SUBMITTED.
     */
    public function submitForHrApproval(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('submit', $payrollRun);

        $run = $this->workflowService->submitForHrApproval($payrollRun, (int) $request->user()->id);

        return response()->json([
            'message' => 'Payroll run submitted for HR Manager approval.',
            'run' => new PayrollRunResource($run),
        ]);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/hr-approve
     * Step 6: HR Manager approve or return.
     */
    public function hrApprove(HrApprovePayrollRunRequest $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('hrApprove', $payrollRun);

        $action = $request->validated('action');

        if ($action === 'APPROVED') {
            $run = $this->workflowService->hrApprove($payrollRun, (int) $request->user()->id, $request->validated());

            return response()->json(['message' => 'HR Manager approved. Forwarded to Accounting Manager.', 'run' => new PayrollRunResource($run)]);
        }

        $run = $this->workflowService->hrReturn($payrollRun, (int) $request->user()->id, $request->validated('return_comments'));

        return response()->json(['message' => 'Run returned to initiator with reason.', 'run' => new PayrollRunResource($run)]);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/acctg-approve
     * Step 7: Accounting Manager final approval or rejection.
     */
    public function acctgApprove(AcctgApprovePayrollRunRequest $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('acctgApprove', $payrollRun);

        $action = $request->validated('action');

        if ($action === 'APPROVED') {
            $run = $this->workflowService->acctgApprove($payrollRun, (int) $request->user()->id, $request->validated());

            return response()->json(['message' => 'Accounting Manager approved. Ready for disbursement.', 'run' => new PayrollRunResource($run)]);
        }

        $run = $this->workflowService->acctgReject($payrollRun, (int) $request->user()->id, $request->validated('rejection_reason'));

        return response()->json(['message' => 'Payroll run permanently rejected. Must restart from Step 1.', 'run' => new PayrollRunResource($run)]);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/disburse
     * Step 8a: Disburse — trigger GL posting and bank file generation.
     */
    public function disburse(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('post', $payrollRun);

        $run = $this->workflowService->disburse($payrollRun);

        return response()->json([
            'message' => 'Payroll disbursement completed. GL journal entry posted.',
            'run' => new PayrollRunResource($run),
        ]);
    }

    /**
     * POST /api/v1/payroll/runs/{id}/publish
     * Step 8b: Publish payslips to employees (immediate or scheduled).
     */
    public function publish(PublishPayrollRunRequest $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('publish', $payrollRun);

        $run = $this->workflowService->publish($payrollRun, $request->validated());

        return response()->json([
            'message' => 'Payslips published. Employees can now view their payslips.',
            'run' => new PayrollRunResource($run),
        ]);
    }

    /**
     * GET /api/v1/payroll/runs/{id}/approvals
     * Returns the approval history for a run (Steps 6 and 7 records).
     */
    public function approvals(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $approvals = $payrollRun->approvals()->with('actor')->orderBy('acted_at')->get();

        return response()->json(['data' => $approvals]);
    }

    /**
     * GET /api/v1/payroll/runs/{id}/gl-preview
     * Step 7: Returns the GL journal entry preview before Accounting Manager approves.
     */
    public function glPreview(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('view', $payrollRun);

        $gross = $payrollRun->gross_pay_total_centavos / 100;
        $netPay = $payrollRun->net_pay_total_centavos / 100;
        $deductions = $payrollRun->total_deductions_centavos / 100;

        // These are preview values — actual amounts computed from details
        $details = $payrollRun->details;
        $sssTotal = $details->sum('sss_ee_centavos') / 100;
        $sssErTotal = $details->sum('sss_er_centavos') / 100;
        $phTotal = $details->sum('philhealth_ee_centavos') / 100;
        $phErTotal = $details->sum('philhealth_er_centavos') / 100;
        $pagTotal = $details->sum('pagibig_ee_centavos') / 100;
        $pagErTotal = $details->sum('pagibig_er_centavos') / 100;
        $whtTotal = $details->sum('withholding_tax_centavos') / 100;
        $loanTotal = $details->sum('loan_deductions_centavos') / 100;
        $otherTotal = $details->sum('other_deductions_centavos') / 100;

        return response()->json(['data' => [
            'total_net_pay' => $netPay,
            'total_gross' => $gross,
            'total_deductions' => $deductions,
            'total_sss_ee' => $sssTotal,
            'total_sss_er' => $sssErTotal,
            'total_philhealth_ee' => $phTotal,
            'total_philhealth_er' => $phErTotal,
            'total_pagibig_ee' => $pagTotal,
            'total_pagibig_er' => $pagErTotal,
            'total_withholding_tax' => $whtTotal,
            'total_loan_deductions' => $loanTotal,
            'total_other_deductions' => $otherTotal,
            // Net cash the company must disburse:
            // net pay to employees + all gov't remittances (EE withheld + ER expense).
            'total_cash_outflow' => $netPay + ($sssTotal + $sssErTotal) + ($phTotal + $phErTotal) + ($pagTotal + $pagErTotal) + $whtTotal,
            // ── Debits & credits mirror exactly what PayrollPostingService posts ──
            'debits' => [
                ['account' => '5001', 'description' => 'Salaries and Wages Expense', 'amount' => $gross],
            ],
            'credits' => array_values(array_filter([
                ['account' => '2100', 'description' => 'SSS Contributions Payable',      'amount' => $sssTotal],
                ['account' => '2101', 'description' => 'PhilHealth Payable',              'amount' => $phTotal],
                ['account' => '2102', 'description' => 'PagIBIG Payable',                 'amount' => $pagTotal],
                ['account' => '2103', 'description' => 'Withholding Tax Payable',         'amount' => $whtTotal],
                $loanTotal > 0 ? ['account' => '2104', 'description' => 'Loan Deductions Payable',       'amount' => $loanTotal] : null,
                $otherTotal > 0 ? ['account' => '2001', 'description' => 'Other Deductions Payable',      'amount' => $otherTotal] : null,
                ['account' => '2200', 'description' => 'Payroll Payable (Net Pay)',       'amount' => $netPay],
            ])),
        ]]);
    }
}
