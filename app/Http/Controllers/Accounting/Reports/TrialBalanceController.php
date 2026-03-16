<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting\Reports;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\TrialBalanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\FinancialStatementRequest;
use App\Http\Resources\Accounting\Reports\TrialBalanceResource;
use Illuminate\Support\Carbon;

/**
 * GL-002 — Trial Balance
 *
 * GET /api/v1/finance/reports/trial-balance?date_from=&date_to=
 */
final class TrialBalanceController extends Controller
{
    public function __construct(
        private readonly TrialBalanceService $trialBalanceService,
    ) {}

    public function __invoke(FinancialStatementRequest $request): TrialBalanceResource
    {
        $this->authorize('viewAny', JournalEntry::class);

        $report = $this->trialBalanceService->generate(
            dateFrom: Carbon::parse($request->validated('date_from')),
            dateTo: Carbon::parse($request->validated('date_to')),
        );

        return new TrialBalanceResource($report);
    }
}
