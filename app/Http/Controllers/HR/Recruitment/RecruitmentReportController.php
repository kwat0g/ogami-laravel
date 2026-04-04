<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR\Recruitment;

use App\Domains\HR\Recruitment\Services\RecruitmentReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecruitmentReportController extends Controller
{
    public function __construct(
        private readonly RecruitmentReportService $service,
    ) {}

    private function canAccessRecruitmentReports(Request $request): bool
    {
        $user = $request->user();

        if ($user->hasRole('admin') || $user->can('hr.full_access')) {
            return true;
        }

        $isHrRole = $user->hasRole('manager') || $user->hasRole('officer') || $user->hasRole('head');

        return $isHrRole && $user->departments()->where('code', 'HR')->exists();
    }

    public function pipeline(Request $request): JsonResponse
    {
        abort_unless(
            $this->canAccessRecruitmentReports($request) || $request->user()->can('recruitment.reports.view'),
            403,
        );

        return response()->json([
            'data' => $this->service->pipeline($request->only(['department_id'])),
        ]);
    }

    public function timeToFill(Request $request): JsonResponse
    {
        abort_unless(
            $this->canAccessRecruitmentReports($request) || $request->user()->can('recruitment.reports.view'),
            403,
        );

        return response()->json([
            'data' => $this->service->timeToFill($request->only(['department_id'])),
        ]);
    }

    public function sourceMix(Request $request): JsonResponse
    {
        abort_unless(
            $this->canAccessRecruitmentReports($request) || $request->user()->can('recruitment.reports.view'),
            403,
        );

        return response()->json([
            'data' => $this->service->sourceMix($request->only(['from_date', 'to_date'])),
        ]);
    }
}
