<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Domains\AR\Services\ArAgingService;
use App\Domains\Budget\Services\BudgetVarianceService;
use App\Domains\CRM\Services\SalesAnalyticsService;
use App\Domains\HR\Services\OrgChartService;
use App\Domains\Production\Services\MrpService;
use App\Domains\QC\Services\QualityAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Executive Dashboard Controller — aggregates KPIs from all analytics services.
 *
 * GET /api/v1/dashboard/executive
 *
 * Returns a single JSON payload with key metrics from every major domain,
 * suitable for rendering an executive overview dashboard with Recharts.
 */
final class ExecutiveDashboardController extends Controller
{
    public function __construct(
        private readonly ArAgingService $arAging,
        private readonly BudgetVarianceService $budgetVariance,
        private readonly SalesAnalyticsService $salesAnalytics,
        private readonly MrpService $mrp,
        private readonly QualityAnalyticsService $qcAnalytics,
        private readonly OrgChartService $orgChart,
    ) {}

    /**
     * GET /api/v1/dashboard/executive
     */
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', \App\Domains\Payroll\Models\PayrollRun::class);

        $currentYear = (int) now()->format('Y');

        return response()->json([
            'data' => [
                // ── Revenue ──────────────────────────────────────────────
                'sales' => [
                    'pipeline' => $this->salesAnalytics->pipelineFunnel(),
                    'win_rate' => $this->salesAnalytics->winRate($currentYear),
                    'monthly_trend' => $this->salesAnalytics->monthlyRevenueTrend($currentYear),
                    'top_customers' => $this->salesAnalytics->revenueByCustomer($currentYear, 5),
                ],

                // ── Accounts Receivable ──────────────────────────────────
                'ar_aging' => $this->arAging->agingTotals(),

                // ── Budget ───────────────────────────────────────────────
                'budget' => $this->budgetVariance->varianceByCostCenter($currentYear)->take(10),

                // ── Production / MRP ─────────────────────────────────────
                'mrp_summary' => $this->mrp->summary(),

                // ── Quality ──────────────────────────────────────────────
                'quality_kpi' => $this->qcAnalytics->kpiSummary(),

                // ── People ───────────────────────────────────────────────
                'headcount' => $this->orgChart->headcountByDepartment()->take(10),

                // ── Support ──────────────────────────────────────────────
                'tickets' => $this->salesAnalytics->ticketStats(),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
