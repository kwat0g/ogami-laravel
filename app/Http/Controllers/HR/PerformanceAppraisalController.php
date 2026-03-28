<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Domains\HR\Models\PerformanceAppraisal;
use App\Domains\HR\Services\PerformanceAppraisalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PerformanceAppraisalController extends Controller
{
    public function __construct(private readonly PerformanceAppraisalService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_id', 'status', 'review_type', 'reviewer_id', 'per_page']);

        return response()->json($this->service->paginate($filters));
    }

    public function show(PerformanceAppraisal $appraisal): JsonResponse
    {
        return response()->json(['data' => $appraisal->load(['employee', 'reviewer', 'criteria', 'hrApprovedBy'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'reviewer_id' => 'required|exists:users,id',
            'review_type' => 'required|in:annual,mid_year,probationary,project_based',
            'review_period_start' => 'required|date',
            'review_period_end' => 'required|date|after:review_period_start',
            'employee_comments' => 'nullable|string',
            'criteria' => 'required|array|min:1',
            'criteria.*.criteria_name' => 'required|string|max:200',
            'criteria.*.description' => 'nullable|string',
            'criteria.*.weight_pct' => 'required|integer|min:1|max:100',
        ]);

        $appraisal = $this->service->store($data, $data['criteria'], $request->user());

        return response()->json(['data' => $appraisal], 201);
    }

    public function submit(PerformanceAppraisal $appraisal, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->submit($appraisal, $request->user())]);
    }

    public function review(PerformanceAppraisal $appraisal, Request $request): JsonResponse
    {
        $data = $request->validate([
            'ratings' => 'required|array|min:1',
            'ratings.*.criteria_id' => 'required|integer',
            'ratings.*.rating_pct' => 'required|integer|min:0|max:100',
            'ratings.*.comments' => 'nullable|string',
            'reviewer_comments' => 'required|string',
        ]);

        $result = $this->service->managerReview($appraisal, $data['ratings'], $data['reviewer_comments'], $request->user());

        return response()->json(['data' => $result]);
    }

    public function hrApprove(PerformanceAppraisal $appraisal, Request $request): JsonResponse
    {
        $data = $request->validate(['hr_comments' => 'required|string']);

        return response()->json(['data' => $this->service->hrApprove($appraisal, $data['hr_comments'], $request->user())]);
    }

    public function complete(PerformanceAppraisal $appraisal): JsonResponse
    {
        return response()->json(['data' => $this->service->complete($appraisal)]);
    }

    public function employeeHistory(int $employeeId): JsonResponse
    {
        return response()->json(['data' => $this->service->employeeHistory($employeeId)]);
    }

    public function departmentSummary(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: null;

        return response()->json(['data' => $this->service->departmentSummary($year)]);
    }
}
