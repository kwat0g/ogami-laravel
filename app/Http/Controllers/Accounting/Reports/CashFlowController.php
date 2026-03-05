<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting\Reports;

use App\Domains\Accounting\Services\CashFlowService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\FinancialStatementRequest;
use App\Http\Resources\Accounting\Reports\CashFlowResource;
use Illuminate\Support\Carbon;

/**
 * GL-005 — Cash Flow Statement (Indirect Method, PFRS)
 *
 * GET /api/v1/finance/reports/cash-flow?date_from=&date_to=
 */
final class CashFlowController extends Controller
{
    public function __construct(
        private readonly CashFlowService $cashFlowService,
    ) {}

    public function __invoke(FinancialStatementRequest $request): CashFlowResource
    {
        $this->authorize('viewAny', \App\Domains\Accounting\Models\JournalEntry::class);

        $report = $this->cashFlowService->generate(
            dateFrom: Carbon::parse($request->validated('date_from')),
            dateTo: Carbon::parse($request->validated('date_to')),
        );

        return new CashFlowResource($report);
    }
}
