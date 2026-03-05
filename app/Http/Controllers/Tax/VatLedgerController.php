<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tax;

use App\Domains\Tax\Models\VatLedger;
use App\Domains\Tax\Services\VatLedgerService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tax\VatLedgerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class VatLedgerController extends Controller
{
    public function __construct(
        private readonly VatLedgerService $service,
    ) {}

    /**
     * List VAT ledger rows.
     *   ?fiscal_period_id=1,2,3   (comma-separated, optional)
     *   ?is_closed=1|0
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VatLedger::class);

        $query = VatLedger::with('closedByUser')
            ->when($request->filled('fiscal_period_id'), function ($q) use ($request) {
                $ids = array_filter(array_map('intval', explode(',', $request->input('fiscal_period_id'))));
                $q->whereIn('fiscal_period_id', $ids);
            })
            ->when($request->filled('is_closed'), fn ($q) => $q->where('is_closed', $request->boolean('is_closed')))
            ->orderBy('fiscal_period_id', 'desc');

        return VatLedgerResource::collection($query->paginate($request->integer('per_page', 12)));
    }

    public function show(VatLedger $vatLedger): VatLedgerResource
    {
        $this->authorize('view', $vatLedger);

        return new VatLedgerResource($vatLedger->load('closedByUser'));
    }

    /** VAT-004: close the period and carry-forward negative net_vat. */
    public function closePeriod(Request $request, VatLedger $vatLedger): VatLedgerResource
    {
        $this->authorize('closePeriod', $vatLedger);

        $request->validate([
            'next_fiscal_period_id' => ['nullable', 'integer', 'exists:fiscal_periods,id'],
        ]);

        $ledger = $this->service->closePeriod(
            ledger: $vatLedger,
            userId: auth()->id(),
            nextFiscalPeriodId: $request->integer('next_fiscal_period_id') ?: null,
        );

        return new VatLedgerResource($ledger->load('closedByUser'));
    }
}
