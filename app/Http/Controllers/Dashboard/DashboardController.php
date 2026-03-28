<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Domains\Dashboard\Services\DashboardQueryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Controller -- thin delegate to DashboardQueryService.
 *
 * ARCH-001 compliant: no DB:: calls in controller.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardQueryService $queryService,
    ) {}

    /** Role-Based Dashboard. GET /api/v1/dashboard/my */
    public function my(Request $request): JsonResponse
    {
        $service = app(\App\Domains\Dashboard\Services\RoleBasedDashboardService::class);

        return response()->json(['data' => $service->forUser($request->user())]);
    }

    /** Manager Dashboard. GET /api/v1/dashboard/manager */
    public function manager(Request $request)
    {
        return $this->queryService->managerData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Supervisor Dashboard. GET /api/v1/dashboard/supervisor */
    public function supervisor(Request $request)
    {
        return $this->queryService->supervisorData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Hr Dashboard. GET /api/v1/dashboard/hr */
    public function hr(Request $request)
    {
        return $this->queryService->hrData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Accounting Dashboard. GET /api/v1/dashboard/accounting */
    public function accounting(Request $request)
    {
        return $this->queryService->accountingData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Admin Dashboard. GET /api/v1/dashboard/admin */
    public function admin(Request $request)
    {
        return $this->queryService->adminData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Staff Dashboard. GET /api/v1/dashboard/staff */
    public function staff(Request $request)
    {
        return $this->queryService->staffData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Executive Dashboard. GET /api/v1/dashboard/executive */
    public function executive(Request $request)
    {
        return $this->queryService->executiveData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Vp Dashboard. GET /api/v1/dashboard/vp */
    public function vp(Request $request)
    {
        return $this->queryService->vpData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Officer Dashboard. GET /api/v1/dashboard/officer */
    public function officer(Request $request)
    {
        return $this->queryService->officerData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Purchasingofficer Dashboard. GET /api/v1/dashboard/purchasingOfficer */
    public function purchasingOfficer(Request $request)
    {
        return $this->queryService->purchasingOfficerData(
            $request->user(),
            $request->integer('department_id') ?: null,
        );
    }

    /** Supplementary KPIs. GET /api/v1/dashboard/kpis/supplementary */
    public function supplementaryKpis(Request $request): JsonResponse
    {
        $service = app(\App\Domains\Dashboard\Services\DashboardKpiService::class);

        return response()->json(['data' => $service->supplementaryKpis()]);
    }
}
