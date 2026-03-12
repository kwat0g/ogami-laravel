<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Domains\Budget\Models\AnnualBudget;
use App\Domains\Budget\Models\CostCenter;
use App\Domains\Budget\Services\BudgetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\ListBudgetRequest;
use App\Http\Requests\Budget\SetBudgetLineRequest;
use App\Http\Requests\Budget\StoreCostCenterRequest;
use App\Http\Requests\Budget\UpdateCostCenterRequest;
use App\Http\Requests\Budget\UtilisationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $service) {}

    // ── Cost Centres ──────────────────────────────────────────────────────────

    public function indexCostCenters(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CostCenter::class);

        $query = CostCenter::with(['department', 'parent'])
            ->orderBy('code');

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeCostCenter(StoreCostCenterRequest $request): JsonResponse
    {
        $this->authorize('create', CostCenter::class);

        $data = $request->validated();

        $cc = $this->service->storeCostCenter($data, $request->user());

        return response()->json(['data' => $cc->load(['department', 'parent'])], 201);
    }

    public function updateCostCenter(UpdateCostCenterRequest $request, CostCenter $costCenter): JsonResponse
    {
        $this->authorize('update', $costCenter);

        $data = $request->validated();

        $updated = $this->service->updateCostCenter($costCenter, $data, $request->user());

        return response()->json(['data' => $updated->load(['department', 'parent'])]);
    }

    // ── Annual Budgets ────────────────────────────────────────────────────────

    public function indexBudgets(ListBudgetRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AnnualBudget::class);

        $data = $request->validated();

        $budgets = AnnualBudget::with('account')
            ->where('cost_center_id', $data['cost_center_id'])
            ->where('fiscal_year', $data['fiscal_year'])
            ->orderBy('account_id')
            ->get();

        return response()->json(['data' => $budgets]);
    }

    public function setBudgetLine(SetBudgetLineRequest $request): JsonResponse
    {
        $this->authorize('create', AnnualBudget::class);

        $data = $request->validated();

        $budget = $this->service->setBudgetLine($data, $request->user());

        return response()->json(['data' => $budget->load('account')], 201);
    }

    // ── Utilisation Report ────────────────────────────────────────────────────

    public function utilisation(UtilisationRequest $request, CostCenter $costCenter): JsonResponse
    {
        $this->authorize('view', $costCenter);

        $data = $request->validated();

        $report = $this->service->getUtilisation($costCenter, $data['fiscal_year']);

        return response()->json([
            'data' => [
                'cost_center' => $costCenter->only(['id', 'ulid', 'name', 'code']),
                'fiscal_year' => $data['fiscal_year'],
                'lines'       => $report,
            ],
        ]);
    }

    // ── Approval Workflow ────────────────────────────────────────────────────

    public function submitBudget(Request $request, AnnualBudget $annualBudget): JsonResponse
    {
        $this->authorize('create', AnnualBudget::class);

        $updated = $this->service->submitBudget($annualBudget, $request->user());

        return response()->json(['data' => $updated->load('account')]);
    }

    public function approveBudget(Request $request, AnnualBudget $annualBudget): JsonResponse
    {
        $this->authorize('approve', AnnualBudget::class);

        $remarks = $request->input('remarks');
        $updated = $this->service->approveBudget($annualBudget, $request->user(), $remarks);

        return response()->json(['data' => $updated->load('account')]);
    }

    public function rejectBudget(Request $request, AnnualBudget $annualBudget): JsonResponse
    {
        $this->authorize('approve', AnnualBudget::class);

        $remarks = $request->input('remarks');
        $updated = $this->service->rejectBudget($annualBudget, $request->user(), $remarks);

        return response()->json(['data' => $updated->load('account')]);
    }
}
