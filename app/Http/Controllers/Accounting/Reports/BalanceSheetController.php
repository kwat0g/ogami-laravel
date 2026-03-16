<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting\Reports;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BalanceSheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\FinancialStatementRequest;
use App\Http\Resources\Accounting\Reports\BalanceSheetResource;
use Illuminate\Support\Carbon;

/**
 * GL-003 — Balance Sheet (PFRS classified)
 *
 * GET /api/v1/finance/reports/balance-sheet?as_of_date=&comparative_date=
 */
final class BalanceSheetController extends Controller
{
    public function __construct(
        private readonly BalanceSheetService $balanceSheetService,
    ) {}

    public function __invoke(FinancialStatementRequest $request): BalanceSheetResource
    {
        $this->authorize('viewAny', JournalEntry::class);

        $comparativeDate = $request->validated('comparative_date')
            ? Carbon::parse($request->validated('comparative_date'))
            : null;

        $report = $this->balanceSheetService->generate(
            asOfDate: Carbon::parse($request->validated('as_of_date')),
            comparativeDate: $comparativeDate,
        );

        return new BalanceSheetResource($report);
    }
}
