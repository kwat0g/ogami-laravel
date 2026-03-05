<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting\Reports;

use App\Domains\Accounting\Services\IncomeStatementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\FinancialStatementRequest;
use App\Http\Resources\Accounting\Reports\IncomeStatementResource;
use Illuminate\Support\Carbon;

/**
 * GL-004 — Income Statement (PFRS)
 *
 * GET /api/v1/finance/reports/income-statement?date_from=&date_to=
 */
final class IncomeStatementController extends Controller
{
    public function __construct(
        private readonly IncomeStatementService $incomeStatementService,
    ) {}

    public function __invoke(FinancialStatementRequest $request): IncomeStatementResource
    {
        $this->authorize('viewAny', \App\Domains\Accounting\Models\JournalEntry::class);

        $report = $this->incomeStatementService->generate(
            dateFrom: Carbon::parse($request->validated('date_from')),
            dateTo: Carbon::parse($request->validated('date_to')),
        );

        return new IncomeStatementResource($report);
    }
}
