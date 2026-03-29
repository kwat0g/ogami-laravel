<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Services\RecruitmentDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecruitmentDashboardController extends Controller
{
    public function __construct(
        private readonly RecruitmentDashboardService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('recruitment.requisitions.view'), 403);

        return response()->json([
            'data' => [
                'kpis' => $this->service->getKpis(),
                'pipeline_funnel' => $this->service->getPipelineFunnel(),
                'source_mix' => $this->service->getSourceMix(),
                'recent_requisitions' => $this->service->getRecentRequisitions(),
                'upcoming_interviews' => $this->service->getUpcomingInterviews(),
            ],
        ]);
    }
}
