<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting\Reports;

use App\Domains\Accounting\Services\GeneralLedgerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\GeneralLedgerRequest;
use App\Http\Resources\Accounting\Reports\GeneralLedgerResource;
use Illuminate\Support\Carbon;

/**
 * GL-001 — General Ledger Report
 *
 * GET /api/v1/finance/reports/gl
 */
final class GeneralLedgerController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $glService,
    ) {}

    public function __invoke(GeneralLedgerRequest $request): GeneralLedgerResource
    {
        $this->authorize('viewAny', \App\Domains\Accounting\Models\JournalEntry::class);

        $report = $this->glService->generate(
            accountId: (int) $request->validated('account_id'),
            dateFrom: Carbon::parse($request->validated('date_from')),
            dateTo: Carbon::parse($request->validated('date_to')),
            costCenterId: $request->validated('cost_center_id') ? (int) $request->validated('cost_center_id') : null,
        );

        return new GeneralLedgerResource($report);
    }
}
